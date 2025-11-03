<?php
/**
 * Cron任务管理器
 * 为每个用户创建独立的定时查询任务
 */

class CronManager {
    private static $cronQueryScript;
    private static $phpBinary;
    
    public static function init() {
        // 使用绝对路径
        self::$cronQueryScript = realpath(__DIR__ . '/../cron_query.php');
        if (!self::$cronQueryScript) {
            throw new Exception('找不到cron_query.php脚本');
        }
        
        // 在FPM环境下PHP_BINARY会返回php-fpm，需要找到正确的php可执行文件
        $phpBinary = PHP_BINARY;
        if (strpos($phpBinary, 'php-fpm') !== false) {
            // 尝试常见的php路径
            $possiblePaths = [
                '/usr/bin/php',
                '/usr/bin/php8.4',
                '/usr/bin/php8.3',
                '/usr/bin/php8.2',
                '/usr/bin/php8.1',
                '/usr/local/bin/php'
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path) && is_executable($path)) {
                    $phpBinary = $path;
                    break;
                }
            }
            
            // 如果都没找到，使用which php
            if (strpos($phpBinary, 'php-fpm') !== false) {
                $which = trim(shell_exec('which php 2>/dev/null'));
                if ($which && file_exists($which)) {
                    $phpBinary = $which;
                }
            }
        }
        
        self::$phpBinary = $phpBinary;
    }
    
    /**
     * 为用户添加定时任务
     * @param string $token 用户token
     * @param int $interval 查询间隔（分钟）
     * @return array 结果
     */
    public static function addCronJob($token, $interval) {
        self::init();
        
        // 验证token格式
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return ['success' => false, 'message' => '无效的token格式'];
        }
        
        // 验证间隔
        if ($interval < 1 || $interval > 1440) {
            return ['success' => false, 'message' => '间隔必须在1-1440分钟之间'];
        }
        
        try {
            // 先删除旧的任务
            self::removeCronJob($token);
            
            // 生成cron表达式
            $cronExpr = self::generateCronExpression($interval);
            
            // 生成任务命令（使用绝对路径）
            $logDir = realpath(__DIR__ . '/../logs');
            if (!$logDir) {
                $logDir = __DIR__ . '/../logs';
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                $logDir = realpath($logDir);
            }
            
            $command = sprintf(
                '%s %s %s >> %s/cron_%s.log 2>&1',
                self::$phpBinary,
                self::$cronQueryScript,
                $token,
                $logDir,
                substr($token, 0, 8)
            );
            
            // 添加到crontab
            $cronLine = "{$cronExpr} {$command}";
            $cronTag = "# FlowMonitor: {$token}";
            
            // 获取当前crontab
            exec('crontab -l 2>/dev/null', $currentCron, $returnCode);
            
            // 添加新任务
            $newCron = $currentCron;
            $newCron[] = $cronTag;
            $newCron[] = $cronLine;
            
            // 写入crontab
            $tmpFile = tempnam(sys_get_temp_dir(), 'cron_');
            file_put_contents($tmpFile, implode("\n", $newCron) . "\n");
            exec("crontab {$tmpFile}", $output, $returnCode);
            unlink($tmpFile);
            
            if ($returnCode === 0) {
                return [
                    'success' => true,
                    'message' => '定时任务添加成功',
                    'cron' => $cronExpr,
                    'interval' => $interval
                ];
            } else {
                return ['success' => false, 'message' => '添加crontab失败'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 删除用户的定时任务
     * @param string $token 用户token
     * @return array 结果
     */
    public static function removeCronJob($token) {
        try {
            // 获取当前crontab
            exec('crontab -l 2>/dev/null', $currentCron, $returnCode);
            
            if (empty($currentCron)) {
                return ['success' => true, 'message' => '没有定时任务'];
            }
            
            // 过滤掉该用户的任务
            $cronTag = "# FlowMonitor: {$token}";
            $newCron = [];
            $skipNext = false;
            
            foreach ($currentCron as $line) {
                if ($line === $cronTag) {
                    $skipNext = true;
                    continue;
                }
                if ($skipNext) {
                    $skipNext = false;
                    continue;
                }
                $newCron[] = $line;
            }
            
            // 写入crontab
            if (empty($newCron)) {
                // 清空crontab
                exec('crontab -r 2>/dev/null');
            } else {
                $tmpFile = tempnam(sys_get_temp_dir(), 'cron_');
                file_put_contents($tmpFile, implode("\n", $newCron) . "\n");
                exec("crontab {$tmpFile}", $output, $returnCode);
                unlink($tmpFile);
            }
            
            return ['success' => true, 'message' => '定时任务已删除'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 更新用户的定时任务
     * @param string $token 用户token
     * @param int $interval 新的查询间隔
     * @return array 结果
     */
    public static function updateCronJob($token, $interval) {
        return self::addCronJob($token, $interval);
    }
    
    /**
     * 检查用户是否有定时任务
     * @param string $token 用户token
     * @return bool
     */
    public static function hasCronJob($token) {
        exec('crontab -l 2>/dev/null', $currentCron);
        $cronTag = "# FlowMonitor: {$token}";
        return in_array($cronTag, $currentCron);
    }
    
    /**
     * 获取用户的定时任务信息
     * @param string $token 用户token
     * @return array|null
     */
    public static function getCronJobInfo($token) {
        exec('crontab -l 2>/dev/null', $currentCron);
        $cronTag = "# FlowMonitor: {$token}";
        
        for ($i = 0; $i < count($currentCron); $i++) {
            if ($currentCron[$i] === $cronTag && isset($currentCron[$i + 1])) {
                $cronLine = $currentCron[$i + 1];
                // 解析cron表达式
                if (preg_match('/^(\S+\s+\S+\s+\S+\s+\S+\s+\S+)\s+/', $cronLine, $matches)) {
                    return [
                        'exists' => true,
                        'cron' => $matches[1],
                        'interval' => self::parseCronExpression($matches[1])
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * 生成cron表达式
     * @param int $interval 间隔（分钟）
     * @return string
     */
    private static function generateCronExpression($interval) {
        if ($interval == 1) {
            return '* * * * *'; // 每分钟
        } elseif ($interval < 60 && 60 % $interval == 0) {
            return "*/{$interval} * * * *"; // 每N分钟（能整除60）
        } elseif ($interval == 60) {
            return '0 * * * *'; // 每小时
        } elseif ($interval < 1440 && 1440 % $interval == 0) {
            $hours = $interval / 60;
            return "0 */{$hours} * * *"; // 每N小时
        } elseif ($interval == 1440) {
            return '0 0 * * *'; // 每天
        } else {
            // 其他情况：使用近似的分钟表达式
            return "*/{$interval} * * * *";
        }
    }
    
    /**
     * 解析cron表达式获取间隔（估算）
     * @param string $cronExpr cron表达式
     * @return int 间隔（分钟）
     */
    private static function parseCronExpression($cronExpr) {
        $parts = explode(' ', $cronExpr);
        if (count($parts) < 2) return 0;
        
        $minute = $parts[0];
        $hour = $parts[1];
        
        // 每分钟
        if ($minute === '*' && $hour === '*') {
            return 1;
        }
        
        // 每N分钟
        if (preg_match('/^\*\/(\d+)$/', $minute, $m) && $hour === '*') {
            return (int)$m[1];
        }
        
        // 每小时
        if (preg_match('/^\d+$/', $minute) && $hour === '*') {
            return 60;
        }
        
        // 每N小时
        if (preg_match('/^\d+$/', $minute) && preg_match('/^\*\/(\d+)$/', $hour, $m)) {
            return (int)$m[1] * 60;
        }
        
        // 每天
        if (preg_match('/^\d+$/', $minute) && preg_match('/^\d+$/', $hour)) {
            return 1440;
        }
        
        return 0;
    }
}
