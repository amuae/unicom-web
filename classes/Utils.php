<?php
/**
 * 工具类
 * 提供加密、Token生成、日志记录等通用功能
 */

class Utils {
    
    /**
     * 生成随机Token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * 加密敏感数据
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        $config = Config::getInstance();
        $key = $config->getEncryptionKey();
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            hex2bin($key),
            OPENSSL_RAW_DATA,
            $iv
        );
        
        // 组合 IV 和加密数据
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * 解密敏感数据
     */
    public static function decrypt($encryptedData) {
        if (empty($encryptedData)) {
            return '';
        }
        
        try {
            $config = Config::getInstance();
            $key = $config->getEncryptionKey();
            
            $data = base64_decode($encryptedData);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            return openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                hex2bin($key),
                OPENSSL_RAW_DATA,
                $iv
            );
        } catch (Exception $e) {
            error_log("Decrypt Error: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * 密码哈希
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
     * 获取客户端IP
     */
    public static function getClientIP() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
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
     * 记录操作日志
     */
    public static function logOperation($action, $details = '', $userId = null, $adminId = null) {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                "INSERT INTO operation_logs (user_id, admin_id, action, ip_address, user_agent, details) 
                 VALUES (:user_id, :admin_id, :action, :ip, :ua, :details)"
            );
            
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':admin_id', $adminId, SQLITE3_INTEGER);
            $stmt->bindValue(':action', $action, SQLITE3_TEXT);
            $stmt->bindValue(':ip', self::getClientIP(), SQLITE3_TEXT);
            $stmt->bindValue(':ua', self::getUserAgent(), SQLITE3_TEXT);
            $stmt->bindValue(':details', $details, SQLITE3_TEXT);
            
            return $stmt->execute() !== false;
        } catch (Exception $e) {
            error_log("Log Operation Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 记录通知日志
     */
    public static function logNotify($userId, $type, $title, $status, $errorMessage = '') {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                "INSERT INTO notify_logs (user_id, type, title, status, error_message) 
                 VALUES (:user_id, :type, :title, :status, :error)"
            );
            
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':type', $type, SQLITE3_TEXT);
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $stmt->bindValue(':error', $errorMessage, SQLITE3_TEXT);
            
            return $stmt->execute() !== false;
        } catch (Exception $e) {
            error_log("Log Notify Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 验证手机号格式
     */
    public static function validateMobile($mobile) {
        return preg_match('/^1[3-9]\d{9}$/', $mobile);
    }
    
    /**
     * 验证Cookie格式
     */
    public static function validateCookie($cookie) {
        // 基本验证：检查是否包含必要的字段
        return !empty($cookie) && strlen($cookie) > 50;
    }
    
    /**
     * 格式化文件大小
     */
    public static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * JSON响应
     */
    public static function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 成功响应
     */
    public static function success($data = [], $message = 'success') {
        self::jsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * 错误响应
     */
    public static function error($message, $code = 400, $data = []) {
        self::jsonResponse([
            'success' => false,
            'message' => $message,
            'data' => $data
        ], $code);
    }
    
    /**
     * 检查请求方法
     */
    public static function checkMethod($method) {
        if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
            self::error('请求方法不允许', 405);
        }
    }
    
    /**
     * 获取POST数据
     */
    public static function getPostData() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    /**
     * 清理HTML标签
     */
    public static function cleanHtml($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * 生成短链接码（用于用户访问链接）
     */
    public static function generateShortCode($length = 8) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }
    
    /**
     * 限流检查（简单实现）
     */
    public static function rateLimitCheck($key, $maxAttempts = 10, $timeWindow = 60) {
        $cacheFile = __DIR__ . '/../data/rate_limit_' . md5($key) . '.tmp';
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            $attempts = $data['attempts'] ?? 0;
            $firstAttempt = $data['first_attempt'] ?? time();
            
            // 检查时间窗口
            if (time() - $firstAttempt < $timeWindow) {
                if ($attempts >= $maxAttempts) {
                    return false; // 超过限制
                }
                $attempts++;
            } else {
                // 重置计数
                $attempts = 1;
                $firstAttempt = time();
            }
        } else {
            $attempts = 1;
            $firstAttempt = time();
        }
        
        // 保存数据
        file_put_contents($cacheFile, json_encode([
            'attempts' => $attempts,
            'first_attempt' => $firstAttempt
        ]));
        
        return true;
    }
    
    /**
     * 清理过期的限流文件
     */
    public static function cleanRateLimitFiles() {
        $dataDir = __DIR__ . '/../data';
        $files = glob($dataDir . '/rate_limit_*.tmp');
        $now = time();
        
        foreach ($files as $file) {
            if ($now - filemtime($file) > 3600) { // 1小时后删除
                @unlink($file);
            }
        }
    }
}
