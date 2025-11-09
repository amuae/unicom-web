<?php
namespace App\Utils;

use App\Models\SystemLog;

class Logger {
    private static $config = null;
    private static $logPath = null;
    
    private static function init() {
        if (self::$config === null) {
            self::$config = require dirname(__DIR__, 2) . '/config/log.php';
            self::$logPath = self::$config['log_path'];
            date_default_timezone_set(self::$config['timezone']);
            
            if (!is_dir(self::$logPath)) {
                mkdir(self::$logPath, 0755, true);
            }
        }
    }
    
    public static function system($message, $level = 'info', $context = []) {
        return self::log('system', $message, $level, $context);
    }
    
    public static function query($message, $level = 'info', $context = []) {
        return self::log('query', $message, $level, $context);
    }
    
    public static function cron($message, $level = 'info', $context = []) {
        return self::log('cron', $message, $level, $context);
    }
    
    public static function error($message, $context = []) {
        return self::log('error', $message, 'error', $context);
    }
    
    private static function log($type, $message, $level, $context = []) {
        self::init();
        
        if (!isset(self::$config['channels'][$type]) || !self::$config['channels'][$type]['enabled']) {
            return false;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        $logFile = self::$logPath . '/' . self::$config['channels'][$type]['file'];
        $logLine = sprintf(
            "[%s] [%s] %s %s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $contextStr ? "| Context: {$contextStr}" : ''
        );
        
        file_put_contents($logFile, $logLine, FILE_APPEND);
        
        if (self::$config['log_to_database']) {
            try {
                $model = new SystemLog();
                $model->insert([
                    'log_type' => $type,
                    'log_level' => $level,
                    'message' => $message,
                    'context' => $contextStr,
                    'user_id' => $context['user_id'] ?? null,
                    'ip_address' => $context['ip'] ?? self::getClientIp()
                ]);
            } catch (\Exception $e) {
                error_log("Failed to write log to database: " . $e->getMessage());
            }
        }
        
        return true;
    }
    
    public static function cleanOldLogs($days = null) {
        self::init();
        $days = $days ?? self::$config['retention_days'];
        $expireTime = time() - ($days * 86400);
        $cleaned = 0;
        
        if (is_dir(self::$logPath)) {
            $files = glob(self::$logPath . '/*.log');
            foreach ($files as $file) {
                if (filemtime($file) < $expireTime) {
                    if (unlink($file)) {
                        $cleaned++;
                    }
                }
            }
        }
        
        try {
            $model = new SystemLog();
            $model->cleanOldLogs($days);
        } catch (\Exception $e) {
            self::error("Failed to clean database logs: " . $e->getMessage());
        }
        
        return $cleaned;
    }
    
    private static function getClientIp() {
        $ip = '';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        if (strpos($ip, ',') !== false) {
            $ip = explode(',', $ip)[0];
        }
        
        return trim($ip);
    }
}
