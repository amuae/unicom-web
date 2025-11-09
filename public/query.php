<?php
/**
 * 用户专属查询页面入口
 * 访问方式：query.php?token=xxx 或 query.php?t=xxx
 * API调用：query.php?action=query_flow&token=xxx
 */

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 自动加载
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use App\Models\SystemConfig;
use App\Services\QueryService;
use App\Utils\Helper;

try {
    // 检查是否已安装
    if (!Helper::isInstalled()) {
        header('Location: /install.php');
        exit;
    }
    
    // 获取 token
    $token = $_GET['token'] ?? $_GET['t'] ?? $_POST['token'] ?? '';
    
    // 获取操作类型
    $action = $_GET['action'] ?? 'view';
    
    $queryService = new QueryService();
    
    if ($action === 'query_flow') {
        // API 调用 - 执行查询
        header('Content-Type: application/json');
        $result = $queryService->handleTokenQuery($token);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'reset_baseline') {
        // 重置基准
        header('Content-Type: application/json');
        $result = $queryService->handleResetBaseline($token);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'get_config') {
        // 获取用户配置
        header('Content-Type: application/json');
        $result = $queryService->getUserConfig($token);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'save_notify_config') {
        // 保存通知配置
        header('Content-Type: application/json');
        $postData = json_decode(file_get_contents('php://input'), true);
        $result = $queryService->saveNotifyConfig($token, $postData);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'test_notify') {
        // 测试通知
        header('Content-Type: application/json');
        
        try {
            $rawInput = file_get_contents('php://input');
            $postData = json_decode($rawInput, true);
            
            // 调试日志
            error_log('Test Notify - Raw Input: ' . $rawInput);
            error_log('Test Notify - Decoded Data: ' . print_r($postData, true));
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode([
                    'success' => false,
                    'message' => 'JSON解析错误: ' . json_last_error_msg()
                ]);
                exit;
            }
            
            if (empty($postData)) {
                echo json_encode([
                    'success' => false,
                    'message' => '未接收到数据'
                ]);
                exit;
            }
            
            $result = $queryService->testNotify($token, $postData);
            error_log('Test Notify - Result: ' . print_r($result, true));
            
            echo json_encode($result);
        } catch (Exception $e) {
            error_log('Test Notify - Exception: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => '测试异常: ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    if ($action === 'save_user_config') {
        // 保存用户配置
        header('Content-Type: application/json');
        $postData = json_decode(file_get_contents('php://input'), true);
        $result = $queryService->saveUserConfig($token, $postData);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'delete_user') {
        // 删除用户
        header('Content-Type: application/json');
        $result = $queryService->deleteUser($token);
        echo json_encode($result);
        exit;
    }
    
    // 默认 - 显示查询页面
    // 验证 token
    $validation = $queryService->validateToken($token);
    if (!$validation['success']) {
        http_response_code(400);
        die($validation['message']);
    }
    
    $user = $validation['user'];
    
    // 获取系统配置
    $configModel = new SystemConfig();
    $siteName = $configModel->getValue('site_name', '联通流量查询系统');
    
    // 获取最后一次查询结果（如果有）
    $lastQueryData = null;
    if (!empty($user['last_query_data'])) {
        $lastQueryData = json_decode($user['last_query_data'], true);
    }
    
    // 传递 token 变量给视图（视图中需要显示 token）
    // 渲染页面
    include dirname(__DIR__) . '/app/Views/query/index.php';
    
} catch (Exception $e) {
    http_response_code(500);
    echo '<h1>500 Internal Server Error</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
}
