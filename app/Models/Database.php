<?php
namespace App\Models;

use PDO;
use PDOException;

/**
 * 数据库基类
 * 提供PDO连接和基础CRUD操作
 */
class Database {
    protected static $pdo = null;
    protected $table = '';
    protected $primaryKey = 'id';
    
    /**
     * 获取PDO连接实例
     */
    public static function getConnection() {
        if (self::$pdo === null) {
            $config = require dirname(__DIR__, 2) . '/config/database.php';
            
            try {
                self::$pdo = new PDO(
                    'sqlite:' . $config['database'],
                    null,
                    null,
                    $config['options']
                );
                
                // 设置北京时区
                self::$pdo->exec("PRAGMA timezone = 'Asia/Shanghai'");
            } catch (PDOException $e) {
                throw new \Exception('数据库连接失败: ' . $e->getMessage());
            }
        }
        
        return self::$pdo;
    }
    
    /**
     * 执行查询
     */
    protected function query($sql, $params = []) {
        try {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new \Exception('查询失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 查询所有记录
     */
    public function all($orderBy = null, $limit = null) {
        $sql = "SELECT * FROM {$this->table}";
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->query($sql)->fetchAll();
    }
    
    /**
     * 根据ID查找记录
     */
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->query($sql, [$id])->fetch();
    }
    
    /**
     * 根据ID查找记录（find的别名）
     */
    public function findById($id) {
        return $this->find($id);
    }
    
    /**
     * 根据条件查找一条记录
     */
    public function findBy($conditions) {
        list($where, $params) = $this->buildWhere($conditions);
        $sql = "SELECT * FROM {$this->table} WHERE {$where}";
        return $this->query($sql, $params)->fetch();
    }
    
    /**
     * 根据条件查找一条记录（findBy的别名）
     */
    public function findOne($conditions) {
        return $this->findBy($conditions);
    }
    
    /**
     * 根据条件查找多条记录
     */
    public function findAllBy($conditions, $orderBy = null, $limit = null) {
        list($where, $params) = $this->buildWhere($conditions);
        $sql = "SELECT * FROM {$this->table} WHERE {$where}";
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->query($sql, $params)->fetchAll();
    }
    
    /**
     * 查找所有记录（可选条件）
     */
    public function findAll($conditions = [], $orderBy = null, $limit = null) {
        if (empty($conditions)) {
            $sql = "SELECT * FROM {$this->table}";
            $params = [];
        } else {
            list($where, $params) = $this->buildWhere($conditions);
            $sql = "SELECT * FROM {$this->table} WHERE {$where}";
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->query($sql, $params)->fetchAll();
    }
    
    /**
     * 插入记录
     */
    public function insert($data) {
        // 自动添加时间戳
        if (isset($data['created_at']) || in_array('created_at', $this->getTableColumns())) {
            $data['created_at'] = $this->now();
        }
        if (isset($data['updated_at']) || in_array('updated_at', $this->getTableColumns())) {
            $data['updated_at'] = $this->now();
        }
        
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );
        
        $this->query($sql, array_values($data));
        return self::getConnection()->lastInsertId();
    }
    
    /**
     * 创建记录（insert的别名）
     */
    public function create($data) {
        return $this->insert($data);
    }
    
    /**
     * 更新记录
     */
    public function update($id, $data) {
        // 自动更新时间戳
        if (in_array('updated_at', $this->getTableColumns())) {
            $data['updated_at'] = $this->now();
        }
        
        $sets = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            $sets[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            $this->table,
            implode(', ', $sets),
            $this->primaryKey
        );
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 删除记录
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->query($sql, [$id]);
        return $stmt->rowCount();
    }
    
    /**
     * 统计记录数
     */
    public function count($conditions = []) {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            list($where, $params) = $this->buildWhere($conditions);
            $sql .= " WHERE {$where}";
        }
        
        return (int) $this->query($sql, $params)->fetchColumn();
    }
    
    /**
     * 构建WHERE子句
     */
    protected function buildWhere($conditions) {
        $where = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                // IN查询
                $placeholders = array_fill(0, count($value), '?');
                $where[] = "{$field} IN (" . implode(', ', $placeholders) . ")";
                $params = array_merge($params, $value);
            } else {
                $where[] = "{$field} = ?";
                $params[] = $value;
            }
        }
        
        return [implode(' AND ', $where), $params];
    }
    
    /**
     * 获取表的所有列名
     */
    protected function getTableColumns() {
        static $columns = [];
        
        if (!isset($columns[$this->table])) {
            $sql = "PRAGMA table_info({$this->table})";
            $result = $this->query($sql)->fetchAll();
            $columns[$this->table] = array_column($result, 'name');
        }
        
        return $columns[$this->table];
    }
    
    /**
     * 获取当前北京时间
     */
    protected function now() {
        return date('Y-m-d H:i:s');
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        return self::getConnection()->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        return self::getConnection()->commit();
    }
    
    /**
     * 回滚事务
     */
    public function rollBack() {
        return self::getConnection()->rollBack();
    }
    
    /**
     * 执行SQL语句（用于INSERT/UPDATE/DELETE等不需要返回结果集的操作）
     */
    protected function execute($sql, $params = []) {
        try {
            $stmt = self::getConnection()->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new \Exception('执行失败: ' . $e->getMessage());
        }
    }
}
