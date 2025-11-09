<?php
namespace App\Utils;

/**
 * 辅助函数类
 */
class Helper {
    /**
     * 获取客户端IP地址
     */
    public static function getClientIp() {
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
    
    /**
     * 获取User Agent
     */
    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * 生成随机字符串
     */
    public static function randomString($length = 16, $type = 'alnum') {
        $pools = [
            'alpha' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'alnum' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            'numeric' => '0123456789',
            'hex' => '0123456789abcdef'
        ];
        
        $pool = $pools[$type] ?? $pools['alnum'];
        $poolLength = strlen($pool);
        $result = '';
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $pool[random_int(0, $poolLength - 1)];
        }
        
        return $result;
    }
    
    /**
     * 生成邀请码
     */
    public static function generateInviteCode($length = 12) {
        return strtoupper(self::randomString($length, 'alnum'));
    }
    
    /**
     * 密码加密
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    
    /**
     * 验证密码
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * JSON响应
     */
    public static function jsonResponse($data, $code = 200) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 成功响应
     */
    public static function success($message = '操作成功', $data = []) {
        return self::jsonResponse([
            'success' => true,
            'code' => 0,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * 失败响应
     */
    public static function error($message = '操作失败', $code = -1, $data = []) {
        return self::jsonResponse([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => $data
        ], 400);
    }
    
    /**
     * 重定向
     */
    public static function redirect($url, $permanent = false) {
        header('Location: ' . $url, true, $permanent ? 301 : 302);
        exit;
    }
    
    /**
     * 转义HTML
     */
    public static function escape($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * 格式化文件大小
     */
    public static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * 格式化时间为"xx前"
     */
    public static function timeAgo($timestamp) {
        if (is_string($timestamp)) {
            $timestamp = strtotime($timestamp);
        }
        
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . '天前';
        } elseif ($diff < 31536000) {
            return floor($diff / 2592000) . '月前';
        } else {
            return floor($diff / 31536000) . '年前';
        }
    }
    
    /**
     * 验证手机号
     */
    public static function isMobile($mobile) {
        return preg_match('/^1[3-9]\d{9}$/', $mobile);
    }
    
    /**
     * 验证邮箱
     */
    public static function isEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * 生成CSRF Token
     */
    public static function generateCsrfToken() {
        $token = bin2hex(random_bytes(32));
        Session::set('csrf_token', $token);
        return $token;
    }
    
    /**
     * 验证CSRF Token
     */
    public static function verifyCsrfToken($token) {
        $sessionToken = Session::get('csrf_token');
        return $sessionToken && hash_equals($sessionToken, $token);
    }
    
    /**
     * 检查安装状态
     */
    public static function isInstalled() {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        return file_exists($config['install_lock']);
    }
    
    /**
     * 设置安装锁
     */
    public static function setInstallLock() {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $lockFile = $config['install_lock'];
        $lockDir = dirname($lockFile);
        
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }
        
        return file_put_contents($lockFile, json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => $config['app_version']
        ]));
    }
}
