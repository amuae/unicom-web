<?php
/**
 * 更新用户配置
 */

// 自动加载类
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../../' . str_replace('\\', '/', str_replace('App\\', 'app/', $class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Models\User;
use App\Utils\Logger;

// 设置JSON响应头
header('Content-Type: application/json');

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '不支持的请求方法']);
    exit;
}

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);

// 验证Token
$token = $input['token'] ?? '';
if (empty($token) || strlen($token) !== 24) {
    echo json_encode(['success' => false, 'message' => 'Token无效']);
    exit;
}

try {
    $userModel = new User();
    $user = $userModel->findByToken($token);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '用户不存在']);
        exit;
    }
    
    // 准备更新数据
    $updateData = [];
    
    // 昵称
    if (isset($input['nickname'])) {
        $updateData['nickname'] = trim($input['nickname']);
    }
    
    // 查询密码（可选）
    if (!empty($input['query_password'])) {
        $updateData['query_password'] = password_hash($input['query_password'], PASSWORD_DEFAULT);
    }
    
    // 认证方式
    if (isset($input['auth_type']) && in_array($input['auth_type'], ['cookie', 'token_online'])) {
        $updateData['auth_type'] = $input['auth_type'];
        
        // 如果切换到token_online，需要提供appid和token_online
        if ($input['auth_type'] === 'token_online') {
            if (empty($input['appid']) || empty($input['token_online'])) {
                echo json_encode(['success' => false, 'message' => 'Token Online认证需要提供AppID和Token']);
                exit;
            }
            $updateData['appid'] = trim($input['appid']);
            $updateData['token_online'] = trim($input['token_online']);
        }
    }
    
    if (empty($updateData)) {
        echo json_encode(['success' => false, 'message' => '没有需要更新的数据']);
        exit;
    }
    
    $result = $userModel->update($user['id'], $updateData);
    
    if ($result) {
        Logger::system("用户 {$user['mobile']} 更新配置", 'info');
        echo json_encode(['success' => true, 'message' => '保存成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '保存失败']);
    }
    
} catch (Exception $e) {
    Logger::error("更新用户配置失败: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
