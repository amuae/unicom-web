#!/usr/bin/env php
<?php
/**
 * 单用户定时查询脚本
 * 用于单个用户的自动流量查询和通知
 * 
 * 使用方法:
 * php cron_query.php <access_token>
 */

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 获取参数
if ($argc < 2) {
    echo "错误: 缺少access_token参数\n";
    echo "使用方法: php cron_query.php <access_token>\n";
    exit(1);
}

$token = $argv[1];

// 验证token格式
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    echo "错误: 无效的access_token格式\n";
    exit(1);
}

require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';

try {
    $db = Database::getInstance();
    
    // 查找用户
    $stmt = $db->prepare("SELECT id, mobile, status FROM users WHERE access_token = :token LIMIT 1");
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$user) {
        echo "错误: 用户不存在 (token: " . substr($token, 0, 8) . "...)\n";
        exit(1);
    }
    
    if ($user['status'] !== 'active') {
        echo "用户已禁用，跳过查询 (mobile: {$user['mobile']})\n";
        exit(0);
    }
    
    // 检查通知配置
    $notifyFile = __DIR__ . "/data/{$token}/notify.json";
    if (!file_exists($notifyFile)) {
        echo "用户未配置通知，跳过查询 (mobile: {$user['mobile']})\n";
        exit(0);
    }
    
    $notifyConfig = json_decode(file_get_contents($notifyFile), true);
    
    // 验证通知配置的三个条件
    $hasNotifyType = !empty($notifyConfig['type']);
    $hasThreshold = isset($notifyConfig['threshold']) && $notifyConfig['threshold'] > 0;
    $hasInterval = isset($notifyConfig['interval']) && $notifyConfig['interval'] > 0;
    
    if (!$hasNotifyType || !$hasThreshold || !$hasInterval) {
        echo "用户通知配置不完整，跳过查询 (mobile: {$user['mobile']})\n";
        echo "  - 通知类型: " . ($hasNotifyType ? '✓' : '✗') . "\n";
        echo "  - 阈值设置: " . ($hasThreshold ? '✓' : '✗') . "\n";
        echo "  - 间隔设置: " . ($hasInterval ? '✓' : '✗') . "\n";
        exit(0);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] 查询用户: {$user['mobile']}\n";
    
    // 调用查询API（使用本地路径，避免HTTP重定向）
    $apiUrl = "https://localhost/api/query.php?token={$token}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Host: uni.suuus.de']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        $result_data = json_decode($response, true);
        if ($result_data && isset($result_data['success']) && $result_data['success']) {
            echo "✓ 查询成功\n";
            exit(0);
        } else {
            $errorMsg = $result_data['message'] ?? '未知错误';
            echo "✗ 查询失败: {$errorMsg}\n";
            exit(1);
        }
    } else {
        echo "✗ 请求失败: HTTP {$httpCode}" . ($error ? ", {$error}" : "") . "\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}

