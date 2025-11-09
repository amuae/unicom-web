<?php
namespace App\Controllers;

use App\Services\UserService;
use App\Services\InviteCodeService;
use App\Services\UnicomService;
use App\Models\SystemConfig;
use App\Models\User;
use App\Utils\Helper;
use App\Utils\Logger;

class IndexController {
    private $userService;
    private $inviteCodeService;
    
    public function __construct() {
        $this->userService = new UserService();
        $this->inviteCodeService = new InviteCodeService();
    }
    
    private function render($view, $data = []) {
        extract($data);
        $viewPath = dirname(__DIR__) . "/Views/{$view}.php";
        if (!file_exists($viewPath)) {
            die("View not found: {$view}");
        }
        include $viewPath;
    }
    
    public function index() {
        $configModel = new SystemConfig();
        $siteMode = $configModel->getValue('site_mode', 'open');
        $siteName = $configModel->getValue('site_name', 'Unicom Flow Query');
        
        $this->render('index/home', [
            'siteMode' => $siteMode,
            'siteName' => $siteName
        ]);
    }
    
    /**
     * 查询用户的访问令牌
     */
    public function queryToken() {
        $input = json_decode(file_get_contents('php://input'), true);
        $mobile = $input['mobile'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($mobile) || empty($password)) {
            Helper::error('手机号和密码不能为空');
        }
        
        $result = $this->userService->authenticate($mobile, $password);
        
        if (!$result['success']) {
            Helper::error($result['message']);
        }
        
        Helper::success('查询成功', [
            'token' => $result['user']['token']
        ]);
    }
    
    public function register() {
        if (!$this->isAjax()) {
            Helper::error('Invalid request');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // 检查网站注册模式
        $configModel = new SystemConfig();
        $siteMode = $configModel->getValue('site_mode', 'invite');
        
        if ($siteMode === 'closed') {
            Helper::error('网站已关闭注册');
        }
        
        // 验证必填字段
        $mobile = trim($input['mobile'] ?? '');
        $queryPassword = trim($input['query_password'] ?? '');
        $authType = $input['auth_type'] ?? 'cookie';
        $nickname = trim($input['nickname'] ?? '');
        $inviteCode = trim($input['invite_code'] ?? '');
        
        if (empty($mobile) || empty($queryPassword)) {
            Helper::error('手机号和查询密码不能为空');
        }
        
        // 邀请码模式下验证邀请码
        if ($siteMode === 'invite') {
            if (empty($inviteCode)) {
                Helper::error('请输入邀请码');
            }
            
            $inviteResult = $this->inviteCodeService->validate($inviteCode);
            if (!$inviteResult['success']) {
                Helper::error($inviteResult['message']);
            }
        }
        
        // 验证手机号格式
        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            Helper::error('手机号格式不正确');
        }
        
        // 检查手机号是否已存在
        $userModel = new User();
        $existUser = $userModel->findByMobile($mobile);
        if ($existUser) {
            Helper::error('该手机号已注册');
        }
        
        // 根据认证类型验证凭证
        $unicomService = new UnicomService();
        $cookie = '';
        $appid = '';
        $tokenOnline = '';
        
        if ($authType === 'token_online') {
            $appid = trim($input['appid'] ?? '');
            $tokenOnline = trim($input['token_online'] ?? '');
            
            if (empty($appid) || empty($tokenOnline)) {
                Helper::error('AppID 和 Token Online 不能为空');
            }
            
            // 验证token_online：先获取cookie
            try {
                $cookie = $unicomService->login($appid, $tokenOnline);
                
                // 用获取的cookie查询流量验证
                $flowData = $unicomService->queryFlow($cookie);
                
            } catch (\Exception $e) {
                Helper::error('Token Online 验证失败: ' . $e->getMessage());
            }
            
        } else {
            // cookie方式
            $cookie = trim($input['cookie'] ?? '');
            
            if (empty($cookie)) {
                Helper::error('Cookie 不能为空');
            }
            
            // 验证cookie：直接用cookie查询流量
            try {
                $flowData = $unicomService->queryFlow($cookie);
            } catch (\Exception $e) {
                Helper::error('Cookie 验证失败: ' . $e->getMessage());
            }
        }
        
        // 生成访问令牌
        $token = Helper::generateToken();
        
        // 验证成功，保存用户
        $userData = [
            'mobile' => $mobile,
            'nickname' => $nickname,
            'query_password' => $queryPassword,
            'auth_type' => $authType,
            'status' => 'active',
            'token' => $token
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
        $userId = $userModel->insert($userData);
        
        if ($userId) {
            // 使用邀请码
            if ($siteMode === 'invite' && !empty($inviteCode)) {
                $this->inviteCodeService->use($inviteCode);
            }
            
            Logger::system("用户注册成功: {$mobile} (认证方式: {$authType}, 网站模式: {$siteMode})", 'info', [
                'user_id' => $userId,
                'mobile' => $mobile,
                'auth_type' => $authType,
                'site_mode' => $siteMode,
                'invite_code' => $siteMode === 'invite' ? $inviteCode : null
            ]);
            
            Helper::success('注册成功', [
                'token' => $token,
                'user_id' => $userId
            ]);
        } else {
            Helper::error('注册失败，请稍后重试');
        }
    }
    
    private function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
