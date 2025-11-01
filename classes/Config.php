<?php
/**
 * 配置管理类
 * 从数据库读取和管理系统配置
 */

class Config {
    private static $instance = null;
    private $db;
    private $cache = [];
    
    private function __construct() {
        $this->db = Database::getInstance();
        $this->loadConfig();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 从数据库加载配置到缓存
     */
    private function loadConfig() {
        try {
            $result = $this->db->query("SELECT key, value FROM system_configs");
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $this->cache[$row['key']] = $row['value'];
            }
        } catch (Exception $e) {
            error_log("Load Config Error: " . $e->getMessage());
        }
    }
    
    /**
     * 获取配置值
     */
    public function get($key, $default = null) {
        return $this->cache[$key] ?? $default;
    }
    
    /**
     * 设置配置值
     */
    public function set($key, $value) {
        try {
            $stmt = $this->db->prepare(
                "INSERT OR REPLACE INTO system_configs (key, value, updated_at) 
                 VALUES (:key, :value, datetime('now'))"
            );
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $stmt->bindValue(':value', $value, SQLITE3_TEXT);
            $stmt->execute();
            
            // 更新缓存
            $this->cache[$key] = $value;
            return true;
        } catch (Exception $e) {
            error_log("Set Config Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取所有配置
     */
    public function getAll() {
        return $this->cache;
    }
    
    /**
     * 检查配置是否存在
     */
    public function has($key) {
        return isset($this->cache[$key]);
    }
    
    /**
     * 删除配置
     */
    public function delete($key) {
        try {
            $stmt = $this->db->prepare("DELETE FROM system_configs WHERE key = :key");
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $stmt->execute();
            
            unset($this->cache[$key]);
            return true;
        } catch (Exception $e) {
            error_log("Delete Config Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 重新加载配置
     */
    public function reload() {
        $this->cache = [];
        $this->loadConfig();
    }
    
    // ==========================================
    // 便捷方法
    // ==========================================
    
    /**
     * 检查是否开放模式
     */
    public function isOpenMode() {
        return $this->get('open_mode', '0') === '1';
    }
    
    /**
     * 启用开放模式
     */
    public function enableOpenMode() {
        return $this->set('open_mode', '1');
    }
    
    /**
     * 禁用开放模式
     */
    public function disableOpenMode() {
        return $this->set('open_mode', '0');
    }
    
    /**
     * 获取网站名称
     */
    public function getSiteName() {
        return $this->get('site_name', '联通流量监控');
    }
    
    /**
     * 检查是否允许用户自行删除
     */
    public function allowSelfDelete() {
        return $this->get('allow_self_delete', '1') === '1';
    }
    
    /**
     * 获取最大用户数
     */
    public function getMaxUsers() {
        return (int)$this->get('max_users', '100');
    }
    
    /**
     * 获取加密密钥
     */
    public function getEncryptionKey() {
        $key = $this->get('encryption_key');
        if (empty($key)) {
            // 自动生成加密密钥
            $key = bin2hex(random_bytes(32));
            $this->set('encryption_key', $key);
        }
        return $key;
    }
}
