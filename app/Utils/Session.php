<?php
namespace App\Utils;

/**
 * Session管理类
 */
class Session {
    private static $started = false;
    
    /**
     * 启动Session
     */
    public static function start() {
        if (!self::$started && session_status() === PHP_SESSION_NONE) {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            
            session_name($config['session']['name']);
            session_set_cookie_params([
                'lifetime' => $config['session']['lifetime'],
                'path' => $config['session']['path'],
                'secure' => $config['session']['secure'],
                'httponly' => $config['session']['httponly'],
                'samesite' => 'Lax'
            ]);
            
            session_start();
            self::$started = true;
        }
    }
    
    /**
     * 设置Session值
     */
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    /**
     * 获取Session值
     */
    public static function get($key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * 检查Session是否存在
     */
    public static function has($key) {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    /**
     * 删除Session值
     */
    public static function delete($key) {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * 清空所有Session
     */
    public static function clear() {
        self::start();
        $_SESSION = [];
    }
    
    /**
     * 销毁Session
     */
    public static function destroy() {
        self::start();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        self::$started = false;
    }
    
    /**
     * 重新生成Session ID
     */
    public static function regenerate() {
        self::start();
        session_regenerate_id(true);
    }
    
    /**
     * 设置Flash消息（一次性消息）
     */
    public static function flash($key, $message) {
        self::set("_flash_{$key}", $message);
    }
    
    /**
     * 获取Flash消息（获取后自动删除）
     */
    public static function getFlash($key, $default = null) {
        $flashKey = "_flash_{$key}";
        $message = self::get($flashKey, $default);
        self::delete($flashKey);
        return $message;
    }
    
    /**
     * 检查用户是否已登录
     */
    public static function isLoggedIn() {
        return self::has('admin_id');
    }
    
    /**
     * 获取当前登录的管理员ID
     */
    public static function getAdminId() {
        return self::get('admin_id');
    }
    
    /**
     * 设置管理员登录
     */
    public static function setAdminLogin($adminId, $username) {
        self::start();
        self::regenerate(); // 安全：重新生成Session ID防止会话固定攻击
        self::set('admin_id', $adminId);
        self::set('admin_username', $username);
        self::set('login_time', time());
    }
    
    /**
     * 管理员登出
     */
    public static function logout() {
        self::destroy();
    }
}
