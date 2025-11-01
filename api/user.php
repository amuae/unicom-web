<?php
/**
 * 用户管理统一接口
 * 支持：GET(获取/列表) POST(创建/更新) DELETE(删除)
 */

// 关闭错误显示，避免破坏JSON输出
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// 记录错误到日志而不是显示
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../storage/logs/php_errors.log');

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../classes/Config.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/User.php';
    require_once __DIR__ . '/../classes/Admin.php';
    require_once __DIR__ . '/../classes/Utils.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '加载类失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';
$mobile = $_GET['mobile'] ?? '';

// GET请求：获取用户信息或用户列表
if ($method === 'GET') {
    // 1. 通过手机号查询用户（注册页面使用）
    if ($mobile && !$token && !$action) {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT mobile, auth_type, access_token, user_type, status, created_at FROM users WHERE mobile = :mobile");
            $stmt->bindValue(':mobile', $mobile, SQLITE3_TEXT);
            $result = $stmt->execute();
            $user = $result->fetchArray(SQLITE3_ASSOC);
            
            if (!$user) {
                echo json_encode(['success' => false, 'message' => '用户不存在'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 生成查询URL
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $queryUrl = "{$protocol}://{$host}/views/index.html?token={$user['access_token']}";
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'mobile' => $user['mobile'],
                    'auth_type' => $user['auth_type'],
                    'user_type' => $user['user_type'] === 'beta' ? '公测用户' : '激活码用户',
                    'status' => $user['status'] === 'active' ? '正常' : '已禁用',
                    'created_at' => $user['created_at'],
                    'query_url' => $queryUrl
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => '查询失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // 2. 获取单个用户信息（基于token）
    if ($token && !$action) {
        $user = User::findByToken($token);
        if (!$user) {
            echo json_encode(['success' => false, 'message' => '用户不存在'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'mobile' => $user->mobile,
                'auth_type' => $user->authType,
                'appid' => $user->appid ?? '',
                'token_online' => $user->tokenOnline ?? '',
                'cookie' => $user->cookie ?? '',
                'status' => $user->status
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 3. 获取用户列表（需要管理员权限）
    if ($action === 'list') {
        if (!Admin::check()) {
            echo json_encode(['success' => false, 'message' => '未登录'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        try {
            $db = Database::getInstance();
            $result = $db->query("SELECT id, mobile, auth_type, access_token, status, created_at, last_query_at, appid, token_online, cookie, remark FROM users ORDER BY created_at DESC");
            
            $users = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                // 添加访问URL
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $row['access_url'] = $protocol . '://' . $host . '/views/index.html?token=' . $row['access_token'];
                $row['is_active'] = $row['status'] === 'active' ? 1 : 0;
                
                // 解密敏感数据
                if (!empty($row['appid'])) {
                    $row['appid'] = Utils::decrypt($row['appid']);
                }
                if (!empty($row['token_online'])) {
                    $row['token_online'] = Utils::decrypt($row['token_online']);
                }
                if (!empty($row['cookie'])) {
                    $row['cookie'] = Utils::decrypt($row['cookie']);
                }
                
                $users[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $users], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => '查询失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}

// POST请求：更新用户信息或管理操作
if ($method === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => '无效的请求数据'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 检查是否是管理操作（基于action）
    if (isset($data['action'])) {
        if (!Admin::check()) {
            echo json_encode(['success' => false, 'message' => '未登录'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $action = $data['action'];
        
        switch ($action) {
            case 'add':
                // 添加用户
                $authType = $data['auth_type'] ?? 'full';
                $mobile = trim($data['mobile'] ?? '');
                $remark = trim($data['remark'] ?? '');
                
                if (empty($mobile)) {
                    echo json_encode(['success' => false, 'message' => '手机号不能为空'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                if ($authType === 'full') {
                    $appid = trim($data['appid'] ?? '');
                    $tokenOnline = trim($data['token_online'] ?? '');
                    
                    if (empty($appid) || empty($tokenOnline)) {
                        echo json_encode(['success' => false, 'message' => 'APPID和TOKEN不能为空'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    
                    $result = User::createWithFullAuth($mobile, $appid, $tokenOnline, $remark);
                } else {
                    $cookie = trim($data['cookie'] ?? '');
                    
                    if (empty($cookie)) {
                        echo json_encode(['success' => false, 'message' => 'Cookie不能为空'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    
                    $result = User::createWithCookie($mobile, $cookie, $remark);
                }
                
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                exit;
                
            case 'update':
                // 更新用户信息
                $userId = $data['user_id'] ?? 0;
                $authType = $data['auth_type'] ?? '';
                $remark = trim($data['remark'] ?? '');
                
                if (!$userId || empty($authType)) {
                    echo json_encode(['success' => false, 'message' => '必填参数不能为空'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $user = User::findById($userId);
                if (!$user) {
                    echo json_encode(['success' => false, 'message' => '用户不存在'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                try {
                    $db = Database::getInstance();
                    $updates = ['auth_type = :auth_type', 'remark = :remark', 'updated_at = :updated_at'];
                    $params = [
                        ':auth_type' => $authType,
                        ':remark' => $remark,
                        ':updated_at' => date('Y-m-d H:i:s'),
                        ':id' => $userId
                    ];
                    
                    if ($authType === 'full') {
                        $appid = trim($data['appid'] ?? '');
                        $tokenOnline = trim($data['token_online'] ?? '');
                        
                        if (empty($appid) || empty($tokenOnline)) {
                            echo json_encode(['success' => false, 'message' => '完整认证需要填写APPID和TOKEN'], JSON_UNESCAPED_UNICODE);
                            exit;
                        }
                        
                        $updates[] = 'appid = :appid';
                        $updates[] = 'token_online = :token_online';
                        $updates[] = 'cookie = NULL';
                        $params[':appid'] = Utils::encrypt($appid);
                        $params[':token_online'] = Utils::encrypt($tokenOnline);
                    } else {
                        $cookie = trim($data['cookie'] ?? '');
                        
                        if (empty($cookie)) {
                            echo json_encode(['success' => false, 'message' => 'Cookie认证需要填写Cookie'], JSON_UNESCAPED_UNICODE);
                            exit;
                        }
                        
                        $updates[] = 'cookie = :cookie';
                        $updates[] = 'appid = NULL';
                        $updates[] = 'token_online = NULL';
                        $params[':cookie'] = Utils::encrypt($cookie);
                    }
                    
                    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id';
                    $stmt = $db->prepare($sql);
                    
                    foreach ($params as $key => $value) {
                        $stmt->bindValue($key, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
                    }
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => '更新成功'], JSON_UNESCAPED_UNICODE);
                    } else {
                        echo json_encode(['success' => false, 'message' => '更新失败'], JSON_UNESCAPED_UNICODE);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => '更新失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                }
                exit;
                
            case 'toggle_status':
                // 启用/禁用用户
                $userId = $data['user_id'] ?? 0;
                $isActive = $data['is_active'] ?? false;
                
                if (!$userId) {
                    echo json_encode(['success' => false, 'message' => '用户ID不能为空'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $user = User::findById($userId);
                if (!$user) {
                    echo json_encode(['success' => false, 'message' => '用户不存在'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $result = $user->setActive($isActive);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                exit;
                
            case 'delete':
                // 删除用户（管理操作）
                $userId = $data['user_id'] ?? 0;
                
                if (!$userId) {
                    echo json_encode(['success' => false, 'message' => '用户ID不能为空'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $user = User::findById($userId);
                if (!$user) {
                    echo json_encode(['success' => false, 'message' => '用户不存在'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $result = $user->delete();
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                exit;
                
            default:
                echo json_encode(['success' => false, 'message' => '不支持的操作'], JSON_UNESCAPED_UNICODE);
                exit;
        }
    }
    
    // 基于token的更新（用户自己更新认证信息）
    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => '缺少token参数'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => '无效的请求数据'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $user = User::findByToken($token);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '用户不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        $db = Database::getInstance();
        $authType = $data['auth_type'] ?? $user->authType;
        
        if ($authType === 'full') {
            // 完整凭证模式 - 加密存储
            $encryptedAppid = Utils::encrypt($data['appid'] ?? '');
            $encryptedToken = Utils::encrypt($data['token_online'] ?? '');
            
            $sql = "UPDATE users SET auth_type = :auth_type, appid = :appid, token_online = :token_online, cookie = '' WHERE access_token = :token";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':auth_type', 'full', SQLITE3_TEXT);
            $stmt->bindValue(':appid', $encryptedAppid, SQLITE3_TEXT);
            $stmt->bindValue(':token_online', $encryptedToken, SQLITE3_TEXT);
            $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        } else {
            // Cookie模式 - 加密存储
            $encryptedCookie = Utils::encrypt($data['cookie'] ?? '');
            
            $sql = "UPDATE users SET auth_type = :auth_type, cookie = :cookie, appid = '', token_online = '' WHERE access_token = :token";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':auth_type', 'cookie', SQLITE3_TEXT);
            $stmt->bindValue(':cookie', $encryptedCookie, SQLITE3_TEXT);
            $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        }
        
        $result = $stmt->execute();
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => '更新成功'], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => '更新失败'], JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '更新失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// DELETE请求：删除用户
if ($method === 'DELETE') {
    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => '缺少token参数'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $user = User::findByToken($token);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '用户不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 使用 User 类的 delete 方法，它会自动删除数据文件夹
    $result = $user->delete();
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'message' => '不支持的请求方法'], JSON_UNESCAPED_UNICODE);
