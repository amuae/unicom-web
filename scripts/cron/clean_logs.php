<?php
/**
 * 定时任务：清理旧日志
 * 建议：每天凌晨2点执行一次
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Shanghai');

require_once dirname(__DIR__, 2) . '/app/Models/Database.php';
require_once dirname(__DIR__, 2) . '/app/Models/QueryLog.php';
require_once dirname(__DIR__, 2) . '/app/Models/SystemLog.php';
require_once dirname(__DIR__, 2) . '/app/Utils/Logger.php';

use App\Models\QueryLog;
use App\Models\SystemLog;
use App\Utils\Logger;

echo "========================================\n";
echo "开始清理旧日志\n";
echo "时间: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    $queryLogModel = new QueryLog();
    $systemLogModel = new SystemLog();
    
    // 清理30天前的查询日志
    echo "清理30天前的查询日志... ";
    $queryCount = $queryLogModel->cleanOldLogs(30);
    echo "完成，删除 $queryCount 条记录\n";
    
    // 清理90天前的系统日志
    echo "清理90天前的系统日志... ";
    $systemCount = $systemLogModel->cleanOldLogs(90);
    echo "完成，删除 $systemCount 条记录\n";
    
    // 清理日志文件
    echo "\n清理日志文件:\n";
    $logDir = dirname(__DIR__, 2) . '/storage/logs';
    $files = glob("$logDir/*.log");
    $cleanedSize = 0;
    
    foreach ($files as $file) {
        $mtime = filemtime($file);
        $age = (time() - $mtime) / 86400; // 天数
        
        if ($age > 30) {
            $size = filesize($file);
            if (unlink($file)) {
                $cleanedSize += $size;
                echo "  - 删除: " . basename($file) . " (" . round($size/1024/1024, 2) . " MB)\n";
            }
        }
    }
    
    echo "\n========================================\n";
    echo "清理完成\n";
    echo "查询日志: $queryCount 条\n";
    echo "系统日志: $systemCount 条\n";
    echo "日志文件: " . round($cleanedSize/1024/1024, 2) . " MB\n";
    echo "========================================\n";
    
    Logger::cron("日志清理完成: 查询日志 $queryCount 条, 系统日志 $systemCount 条");
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    Logger::error("日志清理失败: " . $e->getMessage());
    exit(1);
}
