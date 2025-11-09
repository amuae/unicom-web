<?php
/**
 * 定时任务：生成每日统计报告
 * 建议：每天晚上23:55执行
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Shanghai');

require_once dirname(__DIR__, 2) . '/app/Models/Database.php';
require_once dirname(__DIR__, 2) . '/app/Models/User.php';
require_once dirname(__DIR__, 2) . '/app/Models/QueryLog.php';
require_once dirname(__DIR__, 2) . '/app/Services/NotifyService.php';
require_once dirname(__DIR__, 2) . '/app/Utils/Logger.php';

use App\Models\User;
use App\Models\QueryLog;
use App\Services\NotifyService;
use App\Utils\Logger;

echo "========================================\n";
echo "生成每日统计报告\n";
echo "时间: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    $userModel = new User();
    $queryLogModel = new QueryLog();
    $notifyService = new NotifyService();
    
    // 获取统计数据
    $userStats = $userModel->getStats();
    $queryStats = $queryLogModel->getStats();
    
    $today = date('Y-m-d');
    
    echo "用户统计:\n";
    echo "  - 总用户数: " . ($userStats['total'] ?? 0) . "\n";
    echo "  - 活跃用户: " . ($userStats['active'] ?? 0) . "\n";
    echo "  - 新增用户: " . ($userStats['new_today'] ?? 0) . "\n\n";
    
    echo "查询统计:\n";
    echo "  - 今日查询: " . ($queryStats['today_count'] ?? 0) . "\n";
    echo "  - 成功次数: " . ($queryStats['success_count'] ?? 0) . "\n";
    echo "  - 失败次数: " . ($queryStats['fail_count'] ?? 0) . "\n\n";
    
    // 构建通知内容
    $reportContent = [
        'title' => "📊 每日统计报告 - $today",
        'user_total' => $userStats['total'] ?? 0,
        'user_active' => $userStats['active'] ?? 0,
        'user_new' => $userStats['new_today'] ?? 0,
        'query_total' => $queryStats['today_count'] ?? 0,
        'query_success' => $queryStats['success_count'] ?? 0,
        'query_fail' => $queryStats['fail_count'] ?? 0,
        'time' => date('Y-m-d H:i:s')
    ];
    
    // 发送通知
    $notifyService->send('每日报告', $reportContent);
    
    echo "通知已发送\n";
    echo "========================================\n";
    
    Logger::cron("每日报告生成完成");
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    Logger::error("每日报告生成失败: " . $e->getMessage());
    exit(1);
}
