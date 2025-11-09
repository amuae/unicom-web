<?php
/**
 * 删除用户
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
    
    // 删除用户
    $result = $userModel->delete($user['id']);
    
    if ($result) {
        Logger::system("用户 {$user['mobile']} 已删除（通过Token自助删除）", 'warning');
        echo json_encode(['success' => true, 'message' => '用户已删除']);
    } else {
        echo json_encode(['success' => false, 'message' => '删除失败']);
    }
    
} catch (Exception $e) {
    Logger::error("删除用户失败: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
