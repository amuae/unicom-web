<?php
/**
 * 管理员统一接口
 * 处理：登录、登出、状态检查、统计数据
 */

session_start();

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Utils.php';

header('Content-Type: application/json; charset=utf-8');

// 处理 OPTIONS 请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // ==================== 管理员管理操作（来自 admin_manage.php）====================

    // GET请求：获取管理员列表
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        if ($action === 'list') {
            if (!Admin::check()) {
                Utils::error('未登录', 401);
                exit;
            }

            $db = Database::getInstance();
            $result = $db->query("SELECT id, username, email, created_at FROM admins ORDER BY created_at DESC");

            $admins = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $admins[] = $row;
            }

            Utils::success($admins, '获取成功');
            exit;
        }

        Utils::error('无效的action参数', 400);
        exit;
    }

    // POST请求：获取请求数据
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['action'])) {
        Utils::error('无效的请求', 400);
        exit;
    }

    $action = $input['action'];

    switch ($action) {
        case 'update_username':
            // 修改用户名
            if (!Admin::check()) {
                Utils::error('未登录', 401);
                exit;
            }

            $id = $input['id'] ?? 0;
            $username = trim($input['username'] ?? '');

            if (empty($username)) {
                Utils::error('用户名不能为空', 400);
                exit;
            }

            if (strlen($username) < 3 || strlen($username) > 20) {
                Utils::error('用户名长度必须在3-20个字符之间', 400);
                exit;
            }

            $db = Database::getInstance();

            // 检查用户名是否已存在
            $stmt = $db->prepare("SELECT id FROM admins WHERE username = :username AND id != :id");
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $result = $stmt->execute();

            if ($result->fetchArray()) {
                Utils::error('用户名已存在', 400);
                exit;
            }

            // 更新用户名
            $stmt = $db->prepare("UPDATE admins SET username = :username WHERE id = :id");
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                Utils::success(null, '用户名修改成功');
            } else {
                Utils::error('修改失败', 500);
            }
            break;

        case 'update_password':
            // 修改密码
            if (!Admin::check()) {
                Utils::error('未登录', 401);
                exit;
            }

            $id = $input['id'] ?? 0;
            $password = $input['password'] ?? '';

            if (empty($password)) {
                Utils::error('密码不能为空', 400);
                exit;
            }

            if (strlen($password) < 6) {
                Utils::error('密码长度不能少于6个字符', 400);
                exit;
            }

            $db = Database::getInstance();
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("UPDATE admins SET password = :password WHERE id = :id");
            $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                Utils::success(null, '密码修改成功');
            } else {
                Utils::error('修改失败', 500);
            }
            break;

        case 'delete':
            // 删除管理员
            if (!Admin::check()) {
                Utils::error('未登录', 401);
                exit;
            }

            $id = $input['id'] ?? 0;

            $db = Database::getInstance();

            // 检查是否是最后一个管理员
            $count = $db->querySingle("SELECT COUNT(*) FROM admins");
            if ($count <= 1) {
                Utils::error('不能删除最后一个管理员', 400);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM admins WHERE id = :id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                Utils::success(null, '删除成功');
            } else {
                Utils::error('删除失败', 500);
            }
            break;

        case 'login':
            // 管理员登录
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';

            if (empty($username) || empty($password)) {
                Utils::error('用户名和密码不能为空', 400);
                exit;
            }

            // 速率限制
            $ip = Utils::getClientIP();
            if (!Utils::rateLimitCheck('admin_login_' . $ip, 100, 300)) {
                Utils::error('登录尝试过于频繁，请5分钟后再试', 429);
                exit;
            }

            $result = Admin::login($username, $password);

            if ($result['success']) {
                Utils::success([
                    'username' => $result['data']['username'],
                    'email' => $result['data']['email']
                ], '登录成功');
            } else {
                Utils::error($result['message'], 401);
            }
            break;

        case 'logout':
            // 管理员登出
            Admin::logout();
            Utils::success(null, '已登出');
            break;

        case 'check':
            // 检查登录状态
            if (Admin::check()) {
                $admin = $_SESSION['admin'] ?? [];
                Utils::success([
                    'logged_in' => true,
                    'username' => $admin['username'] ?? '',
                    'email' => $admin['email'] ?? ''
                ]);
            } else {
                Utils::error('未登录', 401);
            }
            break;

        case 'stats':
            // 获取统计数据
            if (!Admin::check()) {
                Utils::error('未登录', 401);
                exit;
            }

            $db = Database::getInstance();

            // 总用户数
            $totalUsers = User::count(false);

            // 活跃用户数
            $activeUsers = User::count(true);

            // 今日查询次数
            $today = date('Y-m-d');
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM flow_stats WHERE date = :date");
            $stmt->bindValue(':date', $today, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $todayQueries = $row['count'] ?? 0;

            // 总查询次数
            $result = $db->query("SELECT COUNT(*) as count FROM flow_stats");
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $totalQueries = $row['count'] ?? 0;

            // 最近7天查询趋势
            $stmt = $db->prepare(
                "SELECT date, COUNT(*) as count
                 FROM flow_stats
                 WHERE date >= date('now', '-7 days')
                 GROUP BY date
                 ORDER BY date DESC"
            );
            $result = $stmt->execute();
            $queryTrend = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $queryTrend[] = $row;
            }

            Utils::success([
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'today_queries' => $todayQueries,
                'total_queries' => $totalQueries,
                'query_trend' => $queryTrend
            ], '获取成功');
            break;

        default:
            Utils::error('未知操作', 400);
            break;
    }

} catch (Exception $e) {
    Utils::error('服务器错误：' . $e->getMessage(), 500);
}
