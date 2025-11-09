<?php
/**
 * 单用户流量查询脚本（独立版）
 * 用法：php query_user.php <token>
 * 说明：查询指定用户的流量信息，判断是否达到通知阈值，达到则发送通知
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Shanghai');

// 获取项目根目录
$rootDir = dirname(__DIR__, 2);

// 获取token参数
$token = $argv[1] ?? '';
if (empty($token)) {
    error_log("错误: 缺少token参数");
    exit(1);
}

// 加载必要的类
require_once $rootDir . '/app/Models/Database.php';
require_once $rootDir . '/app/Models/SystemLog.php';
require_once $rootDir . '/app/Models/User.php';
require_once $rootDir . '/app/Services/UnicomService.php';
require_once $rootDir . '/app/Services/NotifyService.php';
require_once $rootDir . '/app/Utils/Logger.php';

use App\Models\User;
use App\Services\UnicomService;
use App\Services\NotifyService;
use App\Utils\Logger;

try {
    $userModel = new User();
    $unicomService = new UnicomService();
    
    // 验证用户
    $user = validateUser($userModel, $token);
    
    Logger::cron("开始定时查询 (user_id: {$user['id']}, mobile: {$user['mobile']})");
    
    // 查询流量
    $queryData = queryUserFlow($user, $unicomService, $userModel);
    
    // 检查阈值并发送通知
    checkThresholdAndNotify($user, $queryData, $unicomService, $userModel);
    
    exit(0);
    
} catch (Exception $e) {
    Logger::error("定时查询异常 (token: $token): " . $e->getMessage());
    exit(1);
}

// ==================== 辅助函数 ====================

/**
 * 验证用户并检查状态
 */
function validateUser($userModel, $token) {
    $user = $userModel->findByToken($token);
    if (!$user) {
        Logger::error("token无效: $token");
        exit(1);
    }
    
    if ($user['status'] !== 'active') {
        Logger::cron("用户已禁用 (user_id: {$user['id']})");
        exit(0);
    }
    
    if (!$user['notify_enabled']) {
        Logger::cron("未启用通知 (user_id: {$user['id']})");
        exit(0);
    }
    
    return $user;
}

/**
 * 查询用户流量
 */
function queryUserFlow($user, $unicomService, $userModel) {
    try {
        $result = $unicomService->getCookieAndFlow($user);
    } catch (Exception $e) {
        Logger::error("查询失败 (user_id: {$user['id']}): " . $e->getMessage());
        
        // 检查凭证失效
        if (isCredentialError($e->getMessage())) {
            sendCredentialExpiredNotify($user);
        }
        exit(1);
    }
    
    // 分析数据
    $analyzed = $unicomService->analyze($user['mobile'], $result['data']);
    $fullBuckets = $unicomService->generateFullBuckets($analyzed['buckets']);
    
    // 计算差值
    $previousStats = !empty($user['last_query_data']) 
        ? json_decode($user['last_query_data'], true) 
        : null;
    $diffStats = $unicomService->calculateDiff($fullBuckets, $previousStats);
    
    // 组装结果
    return [
        'timestamp' => date('Y-m-d H:i:s'),
        'mainPackage' => $analyzed['mainPackage'],
        'packages' => $analyzed['packages'],
        'buckets' => $fullBuckets,
        'diff' => $diffStats,
        'timeInterval' => calculateTimeInterval($user['last_query_time']),
        'needUpdateCookie' => $result['needUpdateCookie'],
        'newCookie' => $result['cookie']
    ];
}

/**
 * 检查阈值并发送通知
 */
function checkThresholdAndNotify($user, $queryData, $unicomService, $userModel) {
    // 判断是否为每日首次查询
    $isTodayFirstQuery = isTodayFirstQuery($user);
    
    // 判断是否为每月首次查询
    $isMonthFirstQuery = isMonthFirstQuery($user);
    
    // 准备更新数据
    $updateData = [];
    
    // 如果需要更新Cookie
    if ($queryData['needUpdateCookie']) {
        $updateData['cookie'] = $queryData['newCookie'];
    }
    
    // 每日首次查询：保存到 today_query_data
    if ($isTodayFirstQuery) {
        $updateData['today_query_data'] = json_encode($queryData);
        Logger::cron("每日首次查询，保存到 today_query_data (user_id: {$user['id']})");
    }
    
    // 每月首次查询：保存到 last_query_data 和 last_query_time
    if ($isMonthFirstQuery) {
        $updateData['last_query_data'] = json_encode($queryData);
        $updateData['last_query_time'] = date('Y-m-d H:i:s');
        Logger::cron("每月首次查询，保存到 last_query_data (user_id: {$user['id']})");
    }
    
    // 总是更新最后查询时间戳
    $updateData['last_query_at'] = date('Y-m-d H:i:s');
    $updateData['updated_at'] = time();
    
    // 执行更新
    if (!empty($updateData)) {
        $userModel->update($user['id'], $updateData);
    }
    
    // 检查通知阈值
    $threshold = $user['notify_threshold'] ?? 0;
    if ($threshold <= 0) {
        Logger::cron("未设置通知阈值 (user_id: {$user['id']})");
        exit(0);
    }
    
    // 检查是否达到阈值
    $generalUsed = $queryData['diff']['所有通用']['uused'] ?? 0;
    $thresholdInfo = sprintf(
        "所有通用流量本次用量: %s, 阈值: %s",
        UnicomService::formatFlow($generalUsed),
        UnicomService::formatFlow($threshold)
    );
    
    if ($generalUsed < $threshold) {
        Logger::cron("未达到通知阈值 (user_id: {$user['id']}), {$thresholdInfo}");
        exit(0);
    }
    
    Logger::cron("达到通知阈值 (user_id: {$user['id']}), {$thresholdInfo}");
    
    // 达到阈值：保存到 last_query_data 和 last_query_time，并发送通知
    $thresholdUpdateData = [
        'last_query_data' => json_encode($queryData),
        'last_query_time' => date('Y-m-d H:i:s'),
        'updated_at' => time()
    ];
    
    if ($queryData['needUpdateCookie']) {
        $thresholdUpdateData['cookie'] = $queryData['newCookie'];
    }
    
    $userModel->update($user['id'], $thresholdUpdateData);
    
    // 发送通知
    sendFlowNotify($user, $queryData, $unicomService, $userModel);
}

/**
 * 判断是否为今日首次查询
 */
function isTodayFirstQuery($user) {
    if (empty($user['last_query_at'])) {
        return true;
    }
    
    $lastQueryDate = date('Y-m-d', strtotime($user['last_query_at']));
    $todayDate = date('Y-m-d');
    
    return $lastQueryDate !== $todayDate;
}

/**
 * 判断是否为本月首次查询
 */
function isMonthFirstQuery($user) {
    if (empty($user['last_query_time'])) {
        return true;
    }
    
    $lastQueryMonth = date('Y-m', strtotime($user['last_query_time']));
    $currentMonth = date('Y-m');
    
    return $lastQueryMonth !== $currentMonth;
}

/**
 * 发送流量通知
 */
function sendFlowNotify($user, $queryData, $unicomService, $userModel) {
    $notifyParams = json_decode($user['notify_params'] ?? '{}', true);
    if (empty($notifyParams)) {
        Logger::error("通知参数为空 (user_id: {$user['id']})");
        exit(1);
    }
    
    // 构建占位符
    $placeholders = $unicomService->buildPlaceholders(
        $queryData['buckets'],
        $queryData['diff'],
        $queryData['mainPackage'],
        $queryData['timeInterval']
    );
    
    // 应用模板
    $title = $unicomService->applyPlaceholders(
        $user['notify_title'] ?: "联通流量提醒 - [套餐]",
        $placeholders
    );
    
    $subtitle = $unicomService->applyPlaceholders(
        $user['notify_subtitle'] ?: "",
        $placeholders
    );
    
    $content = $unicomService->applyPlaceholders(
        $user['notify_content'] ?: "套餐：[套餐]\n时间：[时间]\n时长：[时长]\n\n所有流量：[所有流量.已用] / [所有流量.总量]\n剩余流量：[所有流量.剩余]\n本次用量：[所有流量.用量]\n今日用量：[所有流量.今日用量]",
        $placeholders
    );
    
    if (!empty($subtitle)) {
        $content = $subtitle . "\n" . $content;
    }
    
    // 发送
    $result = NotifyService::send($title, $content, [
        'type' => $user['notify_type'],
        'params' => $notifyParams
    ], [
        'user_id' => $user['id'],
        'mobile' => $user['mobile'],
        'source' => 'cron'
    ]);
    
    if ($result['success']) {
        $userModel->update($user['id'], [
            'last_notify_time' => time(),
            'updated_at' => time()
        ]);
        Logger::cron("通知发送成功 (user_id: {$user['id']})");
    } else {
        Logger::error("通知发送失败 (user_id: {$user['id']}): {$result['message']}");
    }
}

/**
 * 判断是否为凭证失效错误
 */
function isCredentialError($errorMsg) {
    $patterns = [
        'Cookie已失效且缺少登录凭证',
        'Cookie为空且缺少登录凭证',
        'Cookie已失效',
        '登录失败'
    ];
    
    foreach ($patterns as $pattern) {
        if (strpos($errorMsg, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * 发送登录凭证失效通知
 */
function sendCredentialExpiredNotify($user) {
    $notifyParams = json_decode($user['notify_params'] ?? '{}', true);
    if (empty($notifyParams) || empty($user['notify_type'])) {
        Logger::cron("凭证失效但未配置通知 (user_id: {$user['id']}, auth_type: {$user['auth_type']})");
        return;
    }
    
    $authType = $user['auth_type'];
    $mobile = $user['mobile'];
    $time = date('Y-m-d H:i:s');
    
    // 构建通知内容
    if ($authType === 'token_online') {
        $title = "⚠️ Token在线认证失效";
        $content = "手机号：{$mobile}\n认证方式：Token在线\n失效时间：{$time}\n\n";
        $content .= "❌ 您的 Token 在线认证已失效，无法自动查询流量。\n\n";
        $content .= "🔧 解决方法：\n1. 登录联通手机营业厅APP\n2. 重新获取 AppID 和 Token\n3. 在系统中更新您的认证信息\n\n";
        $content .= "💡 提示：定时查询任务已暂停，更新凭证后将自动恢复。";
    } elseif ($authType === 'cookie') {
        $title = "⚠️ Cookie 认证失效";
        $content = "手机号：{$mobile}\n认证方式：Cookie\n失效时间：{$time}\n\n";
        $content .= "❌ 您的 Cookie 已失效，无法自动查询流量。\n\n";
        $content .= "🔧 解决方法：\n1. 登录联通手机营业厅APP\n2. 抓包获取新的 Cookie\n3. 在系统中更新您的 Cookie\n\n";
        $content .= "💡 提示：定时查询任务已暂停，更新 Cookie 后将自动恢复。";
    } else {
        $title = "⚠️ 流量查询失败";
        $content = "手机号：{$mobile}\n认证方式：{$authType}\n失败时间：{$time}";
    }
    
    // 发送
    $result = NotifyService::send($title, $content, [
        'type' => $user['notify_type'],
        'params' => $notifyParams
    ], [
        'user_id' => $user['id'],
        'mobile' => $user['mobile'],
        'source' => 'cron_credential_expired',
        'auth_type' => $authType
    ]);
    
    if ($result['success']) {
        Logger::cron("凭证失效通知已发送 (user_id: {$user['id']}, auth_type: {$authType})");
    } else {
        Logger::error("凭证失效通知发送失败 (user_id: {$user['id']}): {$result['message']}");
    }
}

/**
 * 计算时间间隔
 */
function calculateTimeInterval($lastQueryTime) {
    if (!$lastQueryTime) {
        return '首次查询';
    }
    
    $diff = time() - strtotime($lastQueryTime);
    
    if ($diff < 60) return $diff . '秒';
    if ($diff < 3600) return floor($diff / 60) . '分钟';
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);
        return $hours . '小时' . ($minutes > 0 ? $minutes . '分钟' : '');
    }
    
    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    return $days . '天' . ($hours > 0 ? $hours . '小时' : '');
}
