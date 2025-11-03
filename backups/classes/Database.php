<?php
/**
 * 数据库连接类
 * 使用 SQLite3 实现
 */

class Database {
    private static $instance = null;
    private $connection = null;
    private $dbPath;
    
    /**
     * 私有构造函数，防止外部实例化
     */
    private function __construct() {
        // 设置时区为中国时区
        date_default_timezone_set('Asia/Shanghai');
        
        $this->dbPath = __DIR__ . '/../data/flow_monitor.db';
        $this->connect();
    }
    
    /**
     * 获取数据库实例（单例模式）
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 建立数据库连接
     */
    private function connect() {
        try {
            // 确保 data 目录存在
            $dataDir = dirname($this->dbPath);
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            
            // 连接数据库
            $this->connection = new SQLite3($this->dbPath);
            
            // 启用外键约束
            $this->connection->exec('PRAGMA foreign_keys = ON;');
            
            // 优化性能设置
            $this->connection->exec('PRAGMA journal_mode = WAL;');
            $this->connection->exec('PRAGMA synchronous = NORMAL;');
            $this->connection->exec('PRAGMA cache_size = -64000;'); // 64MB cache
            $this->connection->exec('PRAGMA temp_store = MEMORY;');
            
            // 设置超时时间
            $this->connection->busyTimeout(5000);
            
        } catch (Exception $e) {
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取数据库连接对象
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * 执行查询
     */
    public function query($sql) {
        try {
            return $this->connection->query($sql);
        } catch (Exception $e) {
            error_log("SQL Query Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("查询执行失败: " . $e->getMessage());
        }
    }
    
    /**
     * 执行查询并返回单个值
     */
    public function querySingle($sql, $entireRow = false) {
        try {
            return $this->connection->querySingle($sql, $entireRow);
        } catch (Exception $e) {
            error_log("SQL QuerySingle Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("查询执行失败: " . $e->getMessage());
        }
    }
    
    /**
     * 执行非查询语句（INSERT, UPDATE, DELETE）
     */
    public function exec($sql) {
        try {
            return $this->connection->exec($sql);
        } catch (Exception $e) {
            error_log("SQL Exec Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("执行失败: " . $e->getMessage());
        }
    }
    
    /**
     * 预处理语句
     */
    public function prepare($sql) {
        try {
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                throw new Exception($this->connection->lastErrorMsg());
            }
            return $stmt;
        } catch (Exception $e) {
            error_log("SQL Prepare Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("预处理失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取最后插入的ID
     */
    public function lastInsertId() {
        return $this->connection->lastInsertRowID();
    }
    
    /**
     * 转义字符串
     */
    public function escape($string) {
        return $this->connection->escapeString($string);
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        return $this->connection->exec('BEGIN TRANSACTION');
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        return $this->connection->exec('COMMIT');
    }
    
    /**
     * 回滚事务
     */
    public function rollback() {
        return $this->connection->exec('ROLLBACK');
    }
    
    /**
     * 检查数据库是否已初始化
     */
    public function isInitialized() {
        try {
            $result = $this->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            return $result->fetchArray() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 初始化数据库（执行 schema.sql）
     */
    public function initialize($schema = null) {
        try {
            // 如果没有传入 schema，读取文件
            if ($schema === null) {
                $schemaFile = __DIR__ . '/../schema.sql';
                if (!file_exists($schemaFile)) {
                    throw new Exception("数据库结构文件不存在: schema.sql");
                }
                $schema = file_get_contents($schemaFile);
            }
            
            // 使用 SQLite3 的多语句执行
            // 直接执行整个 schema，SQLite3 可以处理多条语句
            $this->connection->exec($schema);
            
            return true;
            
        } catch (Exception $e) {
            throw new Exception("数据库初始化失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取数据库版本信息
     */
    public function getVersion() {
        try {
            $result = $this->query("SELECT sqlite_version()");
            $row = $result->fetchArray(SQLITE3_NUM);
            return $row[0] ?? 'Unknown';
        } catch (Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }
    
    /**
     * 获取数据库文件大小
     */
    public function getSize() {
        if (file_exists($this->dbPath)) {
            return filesize($this->dbPath);
        }
        return 0;
    }
    
    /**
     * 优化数据库（VACUUM）
     */
    public function optimize() {
        try {
            $this->exec('VACUUM');
            return true;
        } catch (Exception $e) {
            error_log("Database Optimize Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 备份数据库
     */
    public function backup($backupPath) {
        try {
            if (!copy($this->dbPath, $backupPath)) {
                throw new Exception("备份文件创建失败");
            }
            return true;
        } catch (Exception $e) {
            throw new Exception("数据库备份失败: " . $e->getMessage());
        }
    }
    
    /**
     * 关闭数据库连接
     */
    public function close() {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }
    
    /**
     * 析构函数
     */
    public function __destruct() {
        $this->close();
    }
    
    /**
     * 防止克隆
     */
    private function __clone() {}
    
    /**
     * 防止反序列化
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
