<?php
/**
 * 更新用户通知配置
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
    
    // 更新通知配置
    $updateData = [
        'notify_enabled' => !empty($input['notify_enabled']) ? 1 : 0,
        'notify_type' => $input['notify_type'] ?? '',
        'notify_params' => $input['notify_params'] ?? '',
        'notify_title' => $input['notify_title'] ?? '联通流量提醒',
        'notify_subtitle' => $input['notify_subtitle'] ?? '',
        'notify_content' => $input['notify_content'] ?? '',
        'notify_threshold' => max(0, intval($input['notify_threshold'] ?? 5120)),
        'query_interval' => max(5, intval($input['query_interval'] ?? 30))
    ];
    
    $result = $userModel->update($user['id'], $updateData);
    
    if ($result) {
        Logger::system("用户 {$user['mobile']} 更新通知配置", 'info');
        echo json_encode(['success' => true, 'message' => '保存成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '保存失败']);
    }
    
} catch (Exception $e) {
    Logger::error("更新通知配置失败: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
