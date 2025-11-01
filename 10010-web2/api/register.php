<?php
/**
 * 用户注册 API
 * 支持公开注册和激活码注册
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Utils.php';
require_once __DIR__ . '/../activecode/ActivationCode.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';

// 处理GET请求（查询系统模式）
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'check_mode') {
        try {
            $db = Database::getInstance();
            $siteMode = $db->querySingle("SELECT value FROM site_config WHERE key = 'site_mode'");
            if ($siteMode === false || $siteMode === null) {
                $siteMode = 'public'; // 默认公开模式
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'mode' => $siteMode
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => '查询失败: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => '不支持的操作'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    echo json_encode(['success' => false, 'message' => '不支持的请求方法'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// 获取参数
$mobile = trim($input['mobile'] ?? '');
$authType = $input['auth_type'] ?? 'full'; // full 或 cookie
$appid = trim($input['appid'] ?? '');
$tokenOnline = trim($input['token_online'] ?? '');
$cookie = trim($input['cookie'] ?? '');
$activationCode = trim($input['activation_code'] ?? '');

// 验证手机号
if (empty($mobile) || !preg_match('/^1[3-9]\d{9}$/', $mobile)) {
    echo json_encode(['success' => false, 'message' => '请输入有效的手机号'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证认证信息
if ($authType === 'full') {
    if (empty($appid) || empty($tokenOnline)) {
        echo json_encode(['success' => false, 'message' => 'AppID 和 Token 不能为空'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else if ($authType === 'cookie') {
    if (empty($cookie)) {
        echo json_encode(['success' => false, 'message' => 'Cookie 不能为空'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => '无效的认证类型'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = Database::getInstance();
    
    // 检查手机号是否已存在
    $existingUser = User::findByMobile($mobile);
    if ($existingUser) {
        echo json_encode(['success' => false, 'message' => '该手机号已注册'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 检查系统模式
    $siteMode = $db->querySingle("SELECT value FROM site_config WHERE key = 'site_mode'");
    if ($siteMode === false || $siteMode === null) {
        $siteMode = 'public'; // 默认公开模式
    }
    
    $userType = 'beta'; // 默认公测用户
    
    if ($siteMode === 'private') {
        // 私有模式，需要验证激活码
        if (empty($activationCode)) {
            echo json_encode(['success' => false, 'message' => '私有模式下需要激活码'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 验证激活码
        $validation = ActivationCode::validate($activationCode);
        if (!$validation['success']) {
            echo json_encode(['success' => false, 'message' => $validation['message']], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $userType = 'activated'; // 激活码用户
    }
    
    // 创建用户
    if ($authType === 'full') {
        $result = User::createWithFullAuth($mobile, $appid, $tokenOnline, '');
    } else {
        $result = User::createWithCookie($mobile, $cookie, '');
    }
    
    if (!$result['success']) {
        echo json_encode(['success' => false, 'message' => $result['message']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $userId = $result['data']['user_id'];
    $accessToken = $result['data']['access_token'];
    
    // 更新用户类型（如果使用了激活码）
    if ($siteMode === 'private' && !empty($activationCode)) {
        $stmt = $db->prepare("UPDATE users SET user_type = :type WHERE id = :id");
        $stmt->bindValue(':type', $userType, SQLITE3_TEXT);
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        
        // 标记激活码为已使用
        ActivationCode::use($activationCode, $userId);
    }
    
    // 获取用户对象
    $user = User::findById($userId);
    
    // 生成访问URL
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $accessUrl = "{$protocol}://{$host}/views/index.html?token={$user->accessToken}";
    
    echo json_encode([
        'success' => true,
        'message' => '注册成功',
        'data' => [
            'mobile' => $mobile,
            'access_token' => $user->accessToken,
            'query_url' => $accessUrl,  // 前端期望的字段名
            'access_url' => $accessUrl,  // 保留兼容性
            'user_type' => $userType
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '注册失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
