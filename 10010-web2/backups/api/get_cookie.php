<?php
/**
 * 获取Cookie API - 仅负责获取/验证cookie
 * 不进行任何流量查询操作
 */

require_once __DIR__ . '/../classes/ApiHelper.php';
require_once __DIR__ . '/../classes/FlowMonitor.php';

ApiHelper::init();

try {
    $token = $_GET['token'] ?? '';
    $forceRefresh = isset($_GET['force']) && $_GET['force'] == '1';

    // 验证并获取用户
    $user = ApiHelper::getUserByToken($token);

    // 创建FlowMonitor实例并获取cookie
    $monitor = new FlowMonitor($user);
    $result = $monitor->getCookie($forceRefresh);

    ApiHelper::response($result);

} catch (Exception $e) {
    ApiHelper::error('获取Cookie失败: ' . $e->getMessage());
}

