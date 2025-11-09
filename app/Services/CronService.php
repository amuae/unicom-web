<?php
namespace App\Services;

use App\Utils\Logger;

/**
 * 系统Cron任务管理服务
 * 直接操作系统crontab，不使用数据库调度器
 */
class CronService {
    
    // Cron任务的注释标记，用于识别由本系统创建的任务
    private const CRON_COMMENT_PREFIX = '# unicom_flow_user_';
    private const CRON_USER = 'www-data';
    
    public function __construct() {
        // 不再需要数据库模型
    }
    
    /**
     * 为用户创建或更新系统Cron任务
     * @param int $userId 用户ID
     * @param string $userToken 用户Token
     * @param int $intervalMinutes 查询间隔（分钟）
     * @return array
     */
    public function createOrUpdateUserQueryTask($userId, $userToken, $intervalMinutes) {
        try {
            // 验证间隔时间（最小5分钟）
            if ($intervalMinutes < 5) {
                return ['success' => false, 'message' => '查询间隔不能小于5分钟'];
            }
            
            // 先删除旧任务（如果存在）
            $this->deleteUserQueryTask($userId);
            
            // 构建Cron表达式（每N分钟执行一次）
            $cronExpression = "*/{$intervalMinutes} * * * *";
            
            // 构建执行命令
            $scriptPath = dirname(__DIR__, 2) . '/scripts/cron/query_user.php';
            $phpBin = '/usr/bin/php';
            $command = "{$phpBin} {$scriptPath} {$userToken} >> /dev/null 2>&1";
            
            // 构建完整的cron行（带注释标记）
            $cronComment = self::CRON_COMMENT_PREFIX . $userId;
            $cronLine = "{$cronExpression} {$command} {$cronComment}";
            
            // 添加到系统crontab
            $result = $this->addSystemCron($cronLine);
            
            if ($result['success']) {
                Logger::cron("创建系统Cron任务: User ID {$userId}, 间隔 {$intervalMinutes}分钟", 'info');
                return ['success' => true, 'message' => '定时任务已创建'];
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Logger::error("创建用户查询任务失败: " . $e->getMessage());
            return ['success' => false, 'message' => '操作失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 删除用户的系统Cron任务
     * @param int $userId 用户ID
     * @return array
     */
    public function deleteUserQueryTask($userId) {
        try {
            $cronComment = self::CRON_COMMENT_PREFIX . $userId;
            $result = $this->removeSystemCron($cronComment);
            
            if ($result['success']) {
                Logger::cron("删除系统Cron任务: User ID {$userId}", 'info');
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Logger::error("删除用户查询任务失败: " . $e->getMessage());
            return ['success' => false, 'message' => '操作失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 添加系统Cron任务
     * @param string $cronLine 完整的cron行（包括注释）
     * @return array
     */
    private function addSystemCron($cronLine) {
        try {
            // 获取当前crontab内容
            $currentCrontab = $this->getCurrentCrontab();
            
            // 添加新任务
            $newCrontab = $currentCrontab . "\n" . $cronLine . "\n";
            
            // 写入临时文件
            $tempFile = tempnam(sys_get_temp_dir(), 'cron_');
            file_put_contents($tempFile, $newCrontab);
            
            // 安装crontab
            $command = "sudo crontab -u " . self::CRON_USER . " {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // 删除临时文件
            unlink($tempFile);
            
            if ($output === null || strpos($output, 'error') === false) {
                return ['success' => true, 'message' => 'Cron任务添加成功'];
            } else {
                return ['success' => false, 'message' => 'Cron任务添加失败: ' . $output];
            }
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => '操作失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 删除系统Cron任务
     * @param string $commentPattern 注释标记模式
     * @return array
     */
    private function removeSystemCron($commentPattern) {
        try {
            // 获取当前crontab内容
            $currentCrontab = $this->getCurrentCrontab();
            
            if (empty($currentCrontab)) {
                return ['success' => true, 'message' => '任务不存在或已删除'];
            }
            
            // 按行分割
            $lines = explode("\n", $currentCrontab);
            $newLines = [];
            $removed = false;
            
            // 过滤掉包含指定注释的行
            foreach ($lines as $line) {
                if (strpos($line, $commentPattern) === false) {
                    $newLines[] = $line;
                } else {
                    $removed = true;
                }
            }
            
            if (!$removed) {
                return ['success' => true, 'message' => '任务不存在或已删除'];
            }
            
            // 重新组装crontab
            $newCrontab = implode("\n", $newLines);
            
            // 写入临时文件
            $tempFile = tempnam(sys_get_temp_dir(), 'cron_');
            file_put_contents($tempFile, $newCrontab);
            
            // 安装crontab
            $command = "sudo crontab -u " . self::CRON_USER . " {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // 删除临时文件
            unlink($tempFile);
            
            if ($output === null || strpos($output, 'error') === false) {
                return ['success' => true, 'message' => 'Cron任务删除成功'];
            } else {
                return ['success' => false, 'message' => 'Cron任务删除失败: ' . $output];
            }
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => '操作失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 获取当前的crontab内容
     * @return string
     */
    private function getCurrentCrontab() {
        $command = "sudo crontab -u " . self::CRON_USER . " -l 2>/dev/null";
        $output = shell_exec($command);
        
        return $output ?: '';
    }
    
    /**
     * 列出所有由本系统创建的Cron任务（带用户信息）
     * @return array
     */
    public function listUserQueryTasks() {
        try {
            $currentCrontab = $this->getCurrentCrontab();
            
            if (empty($currentCrontab)) {
                return ['success' => true, 'data' => []];
            }
            
            $lines = explode("\n", $currentCrontab);
            $tasks = [];
            
            // 需要查询用户信息
            require_once dirname(__DIR__) . '/Models/Database.php';
            require_once dirname(__DIR__) . '/Models/User.php';
            $userModel = new \App\Models\User();
            
            foreach ($lines as $line) {
                // 查找包含我们标记的任务
                if (strpos($line, self::CRON_COMMENT_PREFIX) !== false) {
                    // 提取用户ID
                    if (preg_match('/' . preg_quote(self::CRON_COMMENT_PREFIX, '/') . '(\d+)/', $line, $matches)) {
                        $userId = $matches[1];
                        
                        // 提取cron表达式（前5个字段）
                        if (preg_match('/^([\d\*\/\,\-]+ [\d\*\/\,\-]+ [\d\*\/\,\-]+ [\d\*\/\,\-]+ [\d\*\/\,\-]+)/', $line, $cronMatches)) {
                            $cronExpression = $cronMatches[1];
                            
                            // 获取用户信息
                            $user = $userModel->find($userId);
                            
                            // 解析间隔时间
                            $interval = $this->parseCronInterval($cronExpression);
                            
                            // 确保时间戳是整数
                            $lastQueryTime = $user['last_query_time'] ?? null;
                            if ($lastQueryTime && !is_numeric($lastQueryTime)) {
                                $lastQueryTime = strtotime($lastQueryTime);
                            }
                            
                            $lastNotifyTime = $user['last_notify_time'] ?? null;
                            if ($lastNotifyTime && !is_numeric($lastNotifyTime)) {
                                $lastNotifyTime = strtotime($lastNotifyTime);
                            }
                            
                            $tasks[] = [
                                'user_id' => $userId,
                                'user_mobile' => $user['mobile'] ?? '未知',
                                'user_status' => $user['status'] ?? 'unknown',
                                'notify_enabled' => $user['notify_enabled'] ?? 0,
                                'cron_expression' => $cronExpression,
                                'interval_minutes' => $interval,
                                'last_query_time' => $lastQueryTime ? (int)$lastQueryTime : null,
                                'last_notify_time' => $lastNotifyTime ? (int)$lastNotifyTime : null,
                                'full_line' => $line,
                                'status' => 'active' // 系统cron中的都是活动状态
                            ];
                        }
                    }
                }
            }
            
            return ['success' => true, 'data' => $tasks];
            
        } catch (\Exception $e) {
            Logger::error("列出Cron任务失败: " . $e->getMessage());
            return ['success' => false, 'message' => '操作失败', 'data' => []];
        }
    }
    
    /**
     * 解析Cron表达式获取间隔分钟数
     * @param string $cronExpression
     * @return int
     */
    private function parseCronInterval($cronExpression) {
        $parts = explode(' ', $cronExpression);
        $minute = $parts[0] ?? '*';
        
        // 匹配 */N 格式
        if (preg_match('/^\*\/(\d+)$/', $minute, $matches)) {
            return intval($matches[1]);
        }
        
        return 0; // 无法解析
    }
    
    /**
     * 获取所有Cron任务的统计信息
     * @return array
     */
    public function getStats() {
        $result = $this->listUserQueryTasks();
        $tasks = $result['data'] ?? [];
        
        return [
            'total' => count($tasks),
            'active' => count(array_filter($tasks, fn($t) => $t['status'] === 'active')),
            'users_with_notify' => count(array_filter($tasks, fn($t) => $t['notify_enabled'] == 1)),
            'total_intervals' => array_sum(array_column($tasks, 'interval_minutes'))
        ];
    }
}
