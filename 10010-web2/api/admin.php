<?php
/**
 * 管理员统一接口
 * 处理：登录、登出、状态检查、统计数据
 */

session_start();

require_once __DIR__ . '/../classes/ApiHelper.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Utils.php';

ApiHelper::init();

$method = $_SERVER['REQUEST_METHOD'];

// GET请求：获取管理员列表
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'list') {
        ApiHelper::checkAdmin();
        
        $db = Database::getInstance();
        $result = $db->query("SELECT id, username, email, created_at FROM admins ORDER BY created_at DESC");
        
        $admins = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $admins[] = $row;
        }
        
        ApiHelper::success($admins, '获取成功');
    }
    
    ApiHelper::error('无效的action参数', 400);
}

// POST请求
$input = ApiHelper::getInput();
ApiHelper::requireParams($input, ['action']);

switch ($input['action']) {
    case 'update_username':
        ApiHelper::checkAdmin();
        ApiHelper::requireParams($input, ['id', 'username']);
        
        $username = trim($input['username']);
        if (strlen($username) < 3 || strlen($username) > 20) {
            ApiHelper::error('用户名长度必须在3-20个字符之间', 400);
        }
        
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM admins WHERE username = :username AND id != :id");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':id', $input['id'], SQLITE3_INTEGER);
        
        if ($stmt->execute()->fetchArray()) {
            ApiHelper::error('用户名已存在', 400);
        }
        
        $stmt = $db->prepare("UPDATE admins SET username = :username WHERE id = :id");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':id', $input['id'], SQLITE3_INTEGER);
        
        ApiHelper::response($stmt->execute() ? 
            ['success' => true, 'message' => '用户名修改成功'] : 
            ['success' => false, 'message' => '修改失败']
        );

    case 'update_password':
        ApiHelper::checkAdmin();
        ApiHelper::requireParams($input, ['id', 'password']);
        
        if (strlen($input['password']) < 6) {
            ApiHelper::error('密码长度不能少于6个字符', 400);
        }
        
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE admins SET password = :password WHERE id = :id");
        $stmt->bindValue(':password', password_hash($input['password'], PASSWORD_DEFAULT), SQLITE3_TEXT);
        $stmt->bindValue(':id', $input['id'], SQLITE3_INTEGER);
        
        ApiHelper::success(null, $stmt->execute() ? '密码修改成功' : '修改失败');

    case 'delete':
        ApiHelper::checkAdmin();
        ApiHelper::requireParams($input, ['id']);
        
        $db = Database::getInstance();
        if ($db->querySingle("SELECT COUNT(*) FROM admins") <= 1) {
            ApiHelper::error('不能删除最后一个管理员', 400);
        }
        
        $stmt = $db->prepare("DELETE FROM admins WHERE id = :id");
        $stmt->bindValue(':id', $input['id'], SQLITE3_INTEGER);
        
        ApiHelper::success(null, $stmt->execute() ? '删除成功' : '删除失败');

    case 'login':
        ApiHelper::requireParams($input, ['username', 'password']);
        
        $ip = Utils::getClientIP();
        if (!Utils::rateLimitCheck('admin_login_' . $ip, 100, 300)) {
            ApiHelper::error('登录尝试过于频繁，请5分钟后再试', 429);
        }
        
        $result = Admin::login(trim($input['username']), $input['password']);
        
        if ($result['success']) {
            ApiHelper::success([
                'username' => $result['data']['username'],
                'email' => $result['data']['email']
            ], '登录成功');
        }
        
        ApiHelper::error($result['message'], 401);

    case 'logout':
        Admin::logout();
        ApiHelper::success(null, '已登出');

    case 'check':
        if (Admin::check()) {
            $admin = $_SESSION['admin'] ?? [];
            ApiHelper::success([
                'logged_in' => true,
                'username' => $admin['username'] ?? '',
                'email' => $admin['email'] ?? ''
            ]);
        }
        ApiHelper::error('未登录', 401);

    case 'stats':
        ApiHelper::checkAdmin();
        
        $db = Database::getInstance();
        $today = date('Y-m-d');
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM flow_stats WHERE date = :date");
        $stmt->bindValue(':date', $today, SQLITE3_TEXT);
        $todayQueries = $stmt->execute()->fetchArray(SQLITE3_ASSOC)['count'] ?? 0;
        
        $totalQueries = $db->query("SELECT COUNT(*) as count FROM flow_stats")->fetchArray(SQLITE3_ASSOC)['count'] ?? 0;
        
        $stmt = $db->prepare("SELECT date, COUNT(*) as count FROM flow_stats WHERE date >= date('now', '-7 days') GROUP BY date ORDER BY date DESC");
        $result = $stmt->execute();
        $queryTrend = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $queryTrend[] = $row;
        }
        
        ApiHelper::success([
            'total_users' => User::count(false),
            'active_users' => User::count(true),
            'today_queries' => $todayQueries,
            'total_queries' => $totalQueries,
            'query_trend' => $queryTrend
        ], '获取成功');

    default:
        ApiHelper::error('未知操作', 400);
}
