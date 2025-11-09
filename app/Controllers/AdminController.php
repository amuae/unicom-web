<?php
namespace App\Controllers;

use App\Services\AdminService;
use App\Services\UserService;
use App\Services\InviteCodeService;
use App\Services\CronService;
use App\Services\UnicomService;
use App\Models\User;
use App\Models\QueryLog;
use App\Models\SystemLog;
use App\Models\SystemConfig;
use App\Utils\Session;
use App\Utils\Helper;
use App\Utils\Logger;

class AdminController {
    private $adminService;
    private $userService;
    private $inviteCodeService;
    private $cronService;
    private $unicomService;
    private $userModel;
    
    public function __construct() {
        Session::start();
        $this->adminService = new AdminService();
        $this->userService = new UserService();
        $this->inviteCodeService = new InviteCodeService();
        $this->cronService = new CronService();
        $this->unicomService = new UnicomService();
        $this->userModel = new User();
    }
    
    private function checkAuth() {
        if (!Session::isLoggedIn()) {
            if ($this->isAjax()) {
                Helper::error('未登录', 401);
            } else {
                Helper::redirect('/admin.php?action=login');
            }
        }
    }
    
    private function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    private function render($view, $data = []) {
        extract($data);
        $viewPath = dirname(__DIR__) . "/Views/admin/{$view}.php";
        if (!file_exists($viewPath)) {
            die("View not found: {$view}");
        }
        include $viewPath;
    }
    
    public function login() {
        if (Session::isLoggedIn()) {
            Helper::redirect('admin.php?action=users');
        }
        $this->render('login');
    }
    
    public function doLogin() {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $this->render('login', ['error' => '用户名和密码不能为空']);
            return;
        }
        
        $result = $this->adminService->login($username, $password);
        
        if ($result['success']) {
            Session::setAdminLogin($result['data']['id'], $result['data']['username']);
            // 直接重定向到用户管理页面
            Helper::redirect('admin.php?action=users');
        } else {
            // 登录失败，显示错误信息
            $this->render('login', ['error' => $result['message']]);
        }
    }
    
    public function logout() {
        Session::logout();
        Helper::redirect('admin.php?action=login');
    }
    
    public function users() {
        $this->checkAuth();
        $page = intval($_GET['page'] ?? 1);
        $result = $this->userService->getList($page, 20);
        $this->render('users', [
            'users' => $result['data']['list'] ?? [],
            'total' => $result['data']['total'] ?? 0,
            'page' => $page
        ]);
    }
    
    public function createUser() {
        $this->checkAuth();
        $result = $this->userService->create($_POST);
        Helper::jsonResponse($result);
    }
    
    public function deleteUser() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = intval($input['user_id'] ?? 0);
        $result = $this->userService->delete($userId);
        Helper::jsonResponse($result);
    }
    
    public function addUser() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // 验证必填字段
        $mobile = trim($input['mobile'] ?? '');
        $queryPassword = trim($input['query_password'] ?? '');
        $authType = $input['auth_type'] ?? 'cookie';
        
        if (empty($mobile) || empty($queryPassword)) {
            Helper::error('手机号和查询密码不能为空');
        }
        
        // 验证手机号格式
        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            Helper::error('手机号格式不正确');
        }
        
        // 检查手机号是否已存在
        $existUser = $this->userModel->findByMobile($mobile);
        if ($existUser) {
            Helper::error('该手机号已存在');
        }
        
        // 根据认证类型验证凭证
        $cookie = '';
        $appid = '';
        $tokenOnline = '';
        
        if ($authType === 'token_online') {
            $appid = trim($input['appid'] ?? '');
            $tokenOnline = trim($input['token_online'] ?? '');
            
            if (empty($appid) || empty($tokenOnline)) {
                Helper::error('appid和token_online不能为空');
            }
            
            // 验证token_online：先获取cookie
            Logger::system("验证token_online用户: {$mobile}", 'info');
            
            try {
                $cookie = $this->unicomService->login($appid, $tokenOnline);
                Logger::system("登录成功，Cookie长度: " . strlen($cookie), 'info');
                
                // 用获取的cookie查询流量验证
                $flowData = $this->unicomService->queryFlow($cookie);
                Logger::system("流量查询成功，套餐: " . ($flowData['packageName'] ?? '未知'), 'info');
                
            } catch (\Exception $e) {
                Logger::system("验证失败: " . $e->getMessage(), 'error');
                Helper::error('token_online验证失败: ' . $e->getMessage());
            }
            
        } else {
            // cookie方式
            $cookie = trim($input['cookie'] ?? '');
            
            if (empty($cookie)) {
                Helper::error('cookie不能为空');
            }
            
            // 验证cookie：直接用cookie查询流量
            Logger::system("验证cookie用户: {$mobile}", 'info');
            
            try {
                $flowData = $this->unicomService->queryFlow($cookie);
                Logger::system("流量查询成功，套餐: " . ($flowData['packageName'] ?? '未知'), 'info');
            } catch (\Exception $e) {
                Logger::system("验证失败: " . $e->getMessage(), 'error');
                Helper::error('cookie验证失败: ' . $e->getMessage());
            }
        }
        
        // 验证成功，统一保存用户
        $userData = [
            'mobile' => $mobile,
            'query_password' => $queryPassword,
            'auth_type' => $authType,
            'status' => 'active',
            'nickname' => $input['nickname'] ?? '',
            'token' => $this->generateToken()
        ];
        
        // 根据认证类型添加额外字段
        if ($authType === 'token_online') {
            $userData['appid'] = $appid;
            $userData['token_online'] = $tokenOnline;
            $userData['cookie'] = $cookie;  // 保存获取到的cookie
        } else {
            $userData['cookie'] = $cookie;
        }
        
        // 插入数据库
        $userId = $this->userModel->insert($userData);
        
        if ($userId) {
            Logger::system("管理员添加用户成功: {$mobile} (认证方式: {$authType})", 'info', [
                'user_id' => $userId,
                'mobile' => $mobile,
                'auth_type' => $authType
            ]);
            Helper::success('用户添加成功', ['user_id' => $userId]);
        } else {
            Helper::error('用户添加失败');
        }
    }
    
    public function getUser() {
        $this->checkAuth();
        
        $userId = intval($_GET['id'] ?? 0);
        if (!$userId) {
            Helper::error('用户ID不能为空');
        }
        
        $user = $this->userModel->find($userId);
        if (!$user) {
            Helper::error('用户不存在');
        }
        
        // 保留完整凭证信息供管理员编辑（管理员有权查看）
        // 添加标志位
        $user['has_token_online'] = !empty($user['token_online']);
        $user['has_appid'] = !empty($user['appid']);
        $user['has_cookie'] = !empty($user['cookie']);
        
        Helper::success('获取成功', $user);
    }
    
    public function updateUser() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = intval($input['user_id'] ?? $input['id'] ?? 0);
        if (!$userId) {
            Helper::error('用户ID不能为空');
        }
        
        $user = $this->userModel->find($userId);
        if (!$user) {
            Helper::error('用户不存在');
        }
        
        // 准备更新数据
        $updateData = [];
        
        // 基本信息
        if (isset($input['nickname'])) {
            $updateData['nickname'] = trim($input['nickname']);
        }
        
        if (isset($input['query_password']) && !empty($input['query_password'])) {
            $updateData['query_password'] = trim($input['query_password']);
        }
        
        if (isset($input['status'])) {
            $updateData['status'] = $input['status'];
        }
        
        // 认证方式变更
        $authType = $input['auth_type'] ?? $user['auth_type'];
        
        if ($authType !== $user['auth_type']) {
            // 认证方式改变，需要验证新凭证
            $updateData['auth_type'] = $authType;
            
            if ($authType === 'token_online') {
                $appid = trim($input['appid'] ?? '');
                $tokenOnline = trim($input['token_online'] ?? '');
                
                if (empty($appid) || empty($tokenOnline)) {
                    Helper::error('切换到token_online方式需要提供appid和token_online');
                }
                
                // 验证token_online
                Logger::system("验证更新后的token_online: {$user['mobile']}", 'info');
                $loginResult = $this->unicomService->login($appid, $tokenOnline);
                
                if (!$loginResult['success']) {
                    Helper::error('token_online验证失败: ' . $loginResult['message']);
                }
                
                $cookie = $loginResult['cookie'];
                $queryResult = $this->unicomService->query($cookie);
                
                if (!$queryResult['success']) {
                    Helper::error('token_online验证失败: ' . $queryResult['message']);
                }
                
                $updateData['appid'] = $appid;
                $updateData['token_online'] = $tokenOnline;
                $updateData['cookie'] = $cookie;
                
            } else {
                // cookie方式
                $cookie = trim($input['cookie'] ?? '');
                
                if (empty($cookie)) {
                    Helper::error('切换到cookie方式需要提供cookie');
                }
                
                Logger::system("验证更新后的cookie: {$user['mobile']}", 'info');
                $queryResult = $this->unicomService->query($cookie);
                
                if (!$queryResult['success']) {
                    Helper::error('cookie验证失败: ' . $queryResult['message']);
                }
                
                $updateData['cookie'] = $cookie;
                $updateData['appid'] = '';
                $updateData['token_online'] = '';
            }
        } else {
            // 认证方式未改变，只更新提供的字段
            if ($authType === 'token_online') {
                if (!empty($input['appid'])) {
                    $updateData['appid'] = trim($input['appid']);
                }
                if (!empty($input['token_online'])) {
                    $updateData['token_online'] = trim($input['token_online']);
                }
                
                // 如果更新了token_online相关信息，需要验证
                if (isset($updateData['appid']) || isset($updateData['token_online'])) {
                    $appid = $updateData['appid'] ?? $user['appid'];
                    $tokenOnline = $updateData['token_online'] ?? $user['token_online'];
                    
                    Logger::system("验证更新后的token_online: {$user['mobile']}", 'info');
                    $loginResult = $this->unicomService->login($appid, $tokenOnline);
                    
                    if (!$loginResult['success']) {
                        Helper::error('token_online验证失败: ' . $loginResult['message']);
                    }
                    
                    $updateData['cookie'] = $loginResult['cookie'];
                }
            } else {
                if (!empty($input['cookie'])) {
                    Logger::system("验证更新后的cookie: {$user['mobile']}", 'info');
                    $queryResult = $this->unicomService->query(trim($input['cookie']));
                    
                    if (!$queryResult['success']) {
                        Helper::error('cookie验证失败: ' . $queryResult['message']);
                    }
                    
                    $updateData['cookie'] = trim($input['cookie']);
                }
            }
        }
        
        if (empty($updateData)) {
            Helper::error('没有需要更新的数据');
        }
        
        // 执行更新
        $result = $this->userModel->update($userId, $updateData);
        
        if ($result) {
            Logger::system("管理员更新用户: {$user['mobile']}", 'info', [
                'user_id' => $userId,
                'updated_fields' => array_keys($updateData)
            ]);
            Helper::success('用户更新成功');
        } else {
            Helper::error('用户更新失败');
        }
    }
    
    public function queryUserFlow() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = intval($input['user_id'] ?? 0);
        $result = $this->unicomService->queryUserFlow($userId);
        Helper::jsonResponse($result);
    }
    
    public function inviteCodes() {
        $this->checkAuth();
        $page = intval($_GET['page'] ?? 1);
        $result = $this->inviteCodeService->getList($page, 20);
        $this->render('invites', [
            'invites' => $result['data'] ?? [],
            'total' => $result['total'] ?? 0,
            'page' => $page
        ]);
    }
    
    public function generateInviteCodes() {
        $this->checkAuth();
        
        $data = [
            'count' => intval($_POST['count'] ?? 1),
            'type' => $_POST['type'] ?? 'single',
            'max_usage' => intval($_POST['max_usage'] ?? 1),
            'expire_days' => intval($_POST['expire_days'] ?? 30),
            'remark' => trim($_POST['remark'] ?? ''),
            'created_by' => Session::getAdminId()
        ];
        
        $result = $this->inviteCodeService->batchGenerate($data);
        Helper::jsonResponse($result);
    }
    
    public function updateInviteStatus() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $status = $input['status'] ?? '';
        
        $result = $this->inviteCodeService->updateStatus($id, $status);
        Helper::jsonResponse($result);
    }
    
    public function updateInviteMaxUsage() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $maxUsage = intval($input['max_usage'] ?? 0);
        
        $result = $this->inviteCodeService->updateMaxUsage($id, $maxUsage);
        Helper::jsonResponse($result);
    }
    
    public function deleteInviteCode() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        
        $result = $this->inviteCodeService->delete($id);
        Helper::jsonResponse($result);
    }
    
    public function batchUpdateInviteStatus() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['ids'] ?? [];
        $status = $input['status'] ?? '';
        
        if (empty($ids) || !in_array($status, ['active', 'disabled'])) {
            Helper::error('参数错误');
        }
        
        $successCount = 0;
        foreach ($ids as $id) {
            $result = $this->inviteCodeService->updateStatus(intval($id), $status);
            if ($result['success']) {
                $successCount++;
            }
        }
        
        $action = $status === 'active' ? '启用' : '禁用';
        Logger::system("批量更新邀请码状态: {$successCount}个，状态:{$status}", 'info');
        Helper::success("已{$action}{$successCount}个邀请码");
    }
    
    public function batchDeleteInviteCodes() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['ids'] ?? [];
        
        if (empty($ids)) {
            Helper::error('请选择要删除的邀请码');
        }
        
        $successCount = 0;
        foreach ($ids as $id) {
            $result = $this->inviteCodeService->delete(intval($id));
            if ($result['success']) {
                $successCount++;
            }
        }
        
        Logger::system("批量删除邀请码: {$successCount}个", 'warning');
        Helper::success("已删除{$successCount}个邀请码");
    }
    
    // ========== 定时任务管理 ==========
    // ========== 定时任务管理 ==========
    
    public function cronTasks() {
        $this->checkAuth();
        
        // 使用新的系统crontab管理
        $result = $this->cronService->listUserQueryTasks();
        $tasks = $result['data'] ?? [];
        
        // 统计信息
        $stats = $this->cronService->getStats();
        
        $this->render('cron', ['tasks' => $tasks, 'stats' => $stats]);
    }
    
    // ========== 日志管理 ==========
    
    public function logs() {
        $this->checkAuth();
        
        $page = intval($_GET['page'] ?? 1);
        $limit = 50;
        $time = $_GET['time'] ?? 'all';
        
        $systemLogModel = new SystemLog();
        
        // 时间过滤
        $where = '';
        $params = [];
        if ($time !== 'all') {
            $timeMap = [
                'today' => strtotime('today'),
                'yesterday' => strtotime('yesterday'),
                'week' => strtotime('-7 days'),
                'month' => strtotime('-30 days')
            ];
            if (isset($timeMap[$time])) {
                $where = 'created_at >= :time';
                $params[':time'] = $timeMap[$time];
            }
        }
        
        $logs = $systemLogModel->getList($page, $limit, $where, $params);
        
        // 统计
        $stats = [
            'total' => $systemLogModel->count(),
            'info' => $systemLogModel->countByLevel('info'),
            'warning' => $systemLogModel->countByLevel('warning'),
            'error' => $systemLogModel->countByLevel('error')
        ];
        
        // 分页信息
        $total = $systemLogModel->count($where, $params);
        $pagination = [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ];
        
        $this->render('logs', [
            'logs' => $logs,
            'stats' => $stats,
            'pagination' => $pagination
        ]);
    }
    
    // ========== 系统设置 ==========
    
    public function settings() {
        $this->checkAuth();
        $configModel = new SystemConfig();
        $config = $configModel->getAllAsKeyValue();
        
        // 数据库信息
        $dbConfig = require dirname(__DIR__, 2) . '/config/database.php';
        $dbPath = $dbConfig['database'];
        $dbInfo = [
            'path' => $dbPath,
            'size' => file_exists($dbPath) ? Helper::formatBytes(filesize($dbPath)) : '-',
            'total_records' => 0
        ];
        
        $this->render('settings', ['config' => $config, 'dbInfo' => $dbInfo]);
    }
    
    public function saveSettings() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? '';
        $settings = $input['settings'] ?? [];
        
        $configModel = new SystemConfig();
        
        foreach ($settings as $key => $value) {
            $configModel->setValue($key, $value);
        }
        
        Logger::system("更新系统设置: {$type}", 'info', ['settings' => array_keys($settings)]);
        Helper::success('设置保存成功');
    }
    
    // ========== 定时任务管理 ==========
    
    public function createCronTask() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $cronService = new CronService();
        
        $taskData = [
            'name' => $input['name'] ?? '',
            'command' => $input['command'] ?? '',
            'schedule' => $input['schedule'] ?? '',
            'description' => $input['description'] ?? '',
            'cron_expression' => $input['schedule'] ?? '',
            'task_type' => 'command',
            'task_params' => json_encode(['command' => $input['command'] ?? '']),
            'status' => 'active'
        ];
        
        if (empty($taskData['name']) || empty($taskData['command']) || empty($taskData['schedule'])) {
            Helper::error('任务名称、命令和时间表达式不能为空');
        }
        
        $result = $cronService->addTask($taskData);
        if ($result['success']) {
            Logger::system("创建定时任务: {$taskData['name']}", 'info', $taskData);
            Helper::success('任务创建成功');
        } else {
            Helper::error($result['message'] ?? '任务创建失败');
        }
    }
    
    public function updateCronTaskStatus() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $taskId = $input['id'] ?? 0;
        $status = $input['status'] ?? '';
        
        if (!$taskId || !in_array($status, ['active', 'disabled'])) {
            Helper::error('参数错误');
        }
        
        $cronService = new CronService();
        $result = $cronService->updateStatus($taskId, $status);
        
        if ($result['success']) {
            Logger::system("更新任务状态: ID={$taskId}, Status={$status}", 'info');
            Helper::success('状态更新成功');
        } else {
            Helper::error($result['message'] ?? '状态更新失败');
        }
    }
    
    public function runCronTask() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $taskId = $input['id'] ?? 0;
        
        if (!$taskId) {
            Helper::error('任务ID不能为空');
        }
        
        $cronService = new CronService();
        $result = $cronService->runTask($taskId);
        
        if ($result['success']) {
            Logger::system("手动执行任务: ID={$taskId}", 'info');
            Helper::success('任务执行成功');
        } else {
            Helper::error($result['message'] ?? '任务执行失败');
        }
    }
    
    public function deleteCronTask() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $taskId = $input['id'] ?? 0;
        
        if (!$taskId) {
            Helper::error('任务ID不能为空');
        }
        
        // 注意：新版本使用系统cron，这个方法已废弃
        // 如果还有旧的数据库任务管理代码，可以保留
        Helper::error('此功能已废弃，请使用用户级别的任务删除');
    }
    
    /**
     * 删除用户的Cron任务
     */
    public function deleteUserCronTask() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['user_id'] ?? 0;
        
        if (!$userId) {
            Helper::error('用户ID不能为空');
        }
        
        $result = $this->cronService->deleteUserQueryTask($userId);
        
        if ($result['success']) {
            Logger::system("删除用户定时任务: User ID={$userId}", 'warning');
            Helper::success('任务删除成功');
        } else {
            Helper::error($result['message'] ?? '任务删除失败');
        }
    }
    
    /**
     * 获取系统完整的Crontab内容
     */
    public function getSystemCrontab() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $command = "sudo crontab -u www-data -l 2>/dev/null";
        $crontab = shell_exec($command);
        
        Helper::success('获取成功', ['crontab' => $crontab ?: '(空)']);
    }
    
    // ========== 日志管理 ==========
    
    public function getLog() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            Helper::error('日志ID不能为空');
        }
        
        $systemLogModel = new SystemLog();
        $log = $systemLogModel->getById($id);
        
        if (!$log) {
            Helper::error('日志不存在');
        }
        
        Helper::success('获取成功', $log);
    }
    
    public function cleanLogs() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $days = intval($input['days'] ?? 30);
        
        if ($days < 1) {
            Helper::error('天数必须大于0');
        }
        
        if ($days > 365) {
            Helper::error('天数不能超过365天');
        }
        
        $totalDeleted = 0;
        $details = [];
        
        try {
            // 计算目标日期（DATETIME 格式）
            $targetDate = date('Y-m-d H:i:s', time() - ($days * 86400));
            
            // 1. 清理系统日志（数据库）
            $systemLogModel = new SystemLog();
            $db = $systemLogModel::getConnection();
            
            $stmt = $db->prepare("DELETE FROM system_logs WHERE created_at < :target_date");
            $stmt->execute(['target_date' => $targetDate]);
            $systemCount = $stmt->rowCount();
            
            $totalDeleted += $systemCount;
            $details[] = "系统日志: {$systemCount}条";
            
            // 2. 清理查询日志（数据库）
            $queryLogModel = new QueryLog();
            
            $stmt = $db->prepare("DELETE FROM query_logs WHERE created_at < :target_date");
            $stmt->execute(['target_date' => $targetDate]);
            $queryCount = $stmt->rowCount();
            
            $totalDeleted += $queryCount;
            $details[] = "查询日志: {$queryCount}条";
            
            // 3. 清理日志文件
            $logDir = dirname(__DIR__, 2) . '/storage/logs';
            $files = glob("$logDir/*.log");
            $cleanedFiles = 0;
            $cleanedSize = 0;
            $targetTime = time() - ($days * 86400);
            
            foreach ($files as $file) {
                // 跳过空文件和非常小的文件
                $size = filesize($file);
                if ($size === 0) continue;
                
                // 读取文件最后一行获取日期
                $lines = file($file);
                if (empty($lines)) continue;
                
                // 检查文件修改时间
                $mtime = filemtime($file);
                
                // 如果整个文件都很旧，直接删除
                if ($mtime < $targetTime) {
                    if (unlink($file)) {
                        $cleanedFiles++;
                        $cleanedSize += $size;
                    }
                    continue;
                }
                
                // 否则，清理文件中的旧日志行
                $newLines = [];
                $deletedLines = 0;
                
                foreach ($lines as $line) {
                    // 匹配日志格式 [2025-11-08 10:03:44]
                    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                        $logTime = strtotime($matches[1]);
                        if ($logTime && $logTime < $targetTime) {
                            $deletedLines++;
                            continue; // 跳过旧日志
                        }
                    }
                    $newLines[] = $line;
                }
                
                // 如果有日志被删除，重写文件
                if ($deletedLines > 0) {
                    $beforeSize = filesize($file);
                    file_put_contents($file, implode('', $newLines));
                    $afterSize = filesize($file);
                    $cleanedSize += ($beforeSize - $afterSize);
                }
            }
            
            if ($cleanedFiles > 0 || $cleanedSize > 0) {
                $sizeStr = $cleanedSize > 1024*1024 
                    ? round($cleanedSize/1024/1024, 2) . 'MB' 
                    : round($cleanedSize/1024, 2) . 'KB';
                $details[] = "日志文件: {$cleanedFiles}个完整文件, {$sizeStr}";
            }
            
            $message = "成功清理{$days}天前的日志\n" . implode("\n", $details);
            Logger::system("清理{$days}天前的日志: " . implode(", ", $details), 'info');
            Helper::success($message);
            
        } catch (Exception $e) {
            Logger::error("清理日志失败: " . $e->getMessage());
            Helper::error("清理失败: " . $e->getMessage());
        }
    }
    
    // ========== 系统设置操作 ==========
    
    public function changePassword() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $oldPassword = $input['old_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';
        
        if (empty($oldPassword) || empty($newPassword)) {
            Helper::error('密码不能为空');
        }
        
        if ($newPassword !== $confirmPassword) {
            Helper::error('两次输入的新密码不一致');
        }
        
        if (strlen($newPassword) < 6) {
            Helper::error('密码长度不能少于6位');
        }
        
        $adminModel = new Admin();
        $admin = $adminModel->getByUsername($_SESSION['admin_username']);
        
        if (!$admin || !password_verify($oldPassword, $admin['password'])) {
            Helper::error('原密码错误');
        }
        
        $result = $adminModel->updatePassword($admin['id'], $newPassword);
        
        if ($result) {
            Logger::system("管理员修改密码: {$admin['username']}", 'warning');
            Helper::success('密码修改成功，请重新登录');
        } else {
            Helper::error('密码修改失败');
        }
    }
    
    public function testNotify() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? '';
        
        if (!in_array($type, ['serverchan', 'pushplus', 'telegram', 'dingtalk', 'wecom'])) {
            Helper::error('不支持的通知类型');
        }
        
        $configModel = new SystemConfig();
        $config = $configModel->getAllAsKeyValue();
        
        $params = [];
        switch ($type) {
            case 'serverchan':
                $sendkey = $config['notify_serverchan_key'] ?? '';
                if (empty($sendkey)) {
                    Helper::error('请先配置 Server酱 SendKey');
                }
                $params = ['sendkey' => $sendkey];
                break;
                
            case 'pushplus':
                $token = $config['notify_pushplus_token'] ?? '';
                if (empty($token)) {
                    Helper::error('请先配置 PushPlus Token');
                }
                $params = ['token' => $token];
                break;
                
            case 'telegram':
                $bot_token = $config['notify_telegram_bot_token'] ?? '';
                $chat_id = $config['notify_telegram_chat_id'] ?? '';
                if (empty($bot_token) || empty($chat_id)) {
                    Helper::error('请先配置 Telegram Bot Token 和 Chat ID');
                }
                $params = [
                    'bot_token' => $bot_token,
                    'chat_id' => $chat_id,
                    'api_host' => $config['notify_telegram_api_host'] ?? 'https://api.telegram.org'
                ];
                break;
                
            case 'dingtalk':
                $webhook = $config['notify_dingtalk_webhook'] ?? '';
                if (empty($webhook)) {
                    Helper::error('请先配置钉钉 Webhook');
                }
                $params = ['webhook' => $webhook];
                break;
                
            case 'wecom':
                $webhook = $config['notify_wecom_webhook'] ?? '';
                if (empty($webhook)) {
                    Helper::error('请先配置企业微信 Webhook');
                }
                $params = ['webhook' => $webhook];
                break;
        }
        
        $result = NotifyService::send(
            $type, 
            $params, 
            '管理员测试通知', 
            '这是一条来自管理员后台的测试消息。如果您能收到此消息，说明通知配置正确。'
        );
        
        if ($result) {
            Helper::success('通知发送成功');
        } else {
            Helper::error('通知发送失败，请检查日志');
        }
    }
    
    public function backupDatabase() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $dbConfig = require dirname(__DIR__, 2) . '/config/database.php';
        $dbPath = $dbConfig['database'];
        
        if (!file_exists($dbPath)) {
            Helper::error('数据库文件不存在');
        }
        
        $backupDir = dirname(__DIR__, 2) . '/database';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . '/unicom_flow.db.' . date('YmdHis') . '.bak';
        
        if (copy($dbPath, $backupFile)) {
            Logger::system("数据库备份成功: {$backupFile}", 'info');
            Helper::success('数据库备份成功', ['file' => basename($backupFile)]);
        } else {
            Helper::error('数据库备份失败');
        }
    }
    
    public function clearCache() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        // 清理日志缓存
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        $cleared = 0;
        
        if (is_dir($logDir)) {
            $files = glob($logDir . '/*.log');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $cleared++;
                }
            }
        }
        
        Logger::system("清理缓存，删除{$cleared}个日志文件", 'info');
        Helper::success("缓存清理成功，删除{$cleared}个文件");
    }
    
    public function clearAllData() {
        $this->checkAuth();
        
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $confirm = $input['confirm'] ?? false;
        
        if (!$confirm) {
            Helper::error('请确认清空操作');
        }
        
        try {
            $db = Database::getInstance();
            
            // 清空用户表（保留管理员）
            $db->exec("DELETE FROM users");
            $db->exec("DELETE FROM query_logs");
            $db->exec("DELETE FROM invite_codes WHERE status != 'used'");
            $db->exec("DELETE FROM sessions");
            $db->exec("DELETE FROM system_logs WHERE type != 'system'");
            
            Logger::system("清空所有数据", 'warning');
            Helper::success('数据清空成功');
        } catch (\Exception $e) {
            Logger::system("清空数据失败: {$e->getMessage()}", 'error');
            Helper::error('数据清空失败');
        }
    }
    
    /**
     * 生成24位随机token
     */
    private function generateToken() {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $token = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < 24; $i++) {
            $token .= $characters[random_int(0, $max)];
        }
        
        // 确保token唯一
        $existingUser = $this->userModel->findByToken($token);
        if ($existingUser) {
            return $this->generateToken(); // 递归重新生成
        }
        
        return $token;
    }
}
