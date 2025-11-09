<?php

namespace App\Models;

/**
 * 系统日志模型 - 纯数据访问层
 */
class SystemLog extends Database
{
    protected $table = 'system_logs';
    
    /**
     * 根据ID获取日志
     */
    public function getById($id)
    {
        return $this->findOne(['id' => $id]);
    }
    
    /**
     * 根据类型获取日志
     */
    public function getByType($type, $limit = 100)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE type = :type 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        return $this->query($sql, [
            'type' => $type,
            'limit' => $limit
        ]);
    }
    
    /**
     * 根据级别获取日志
     */
    public function getByLevel($level, $limit = 100)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE level = :level 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        return $this->query($sql, [
            'level' => $level,
            'limit' => $limit
        ]);
    }
    
    /**
     * 清理旧日志
     */
    public function cleanOldLogs($days = 90)
    {
        $threshold = time() - ($days * 86400);
        
        return $this->deleteWhere(['created_at <' => $threshold]);
    }
    
    /**
     * 获取最近的日志
     */
    public function getRecentLogs($limit = 50)
    {
        $sql = "SELECT * FROM {$this->table} 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        return $this->query($sql, ['limit' => $limit]);
    }
    
    /**
     * 记录系统日志
     */
    public function log($type, $level, $message, $context = '')
    {
        return $this->create([
            'type' => $type,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => time()
        ]);
    }
    
    /**
     * 获取错误日志统计
     */
    public function getErrorStats($days = 7)
    {
        $threshold = time() - ($days * 86400);
        
        $sql = "SELECT 
                    log_type,
                    COUNT(*) as count
                FROM {$this->table} 
                WHERE log_level = 'error' AND created_at >= :threshold
                GROUP BY log_type
                ORDER BY count DESC";
        
        $stmt = $this->query($sql, ['threshold' => $threshold]);
        return $stmt->fetchAll();
    }
    
    /**
     * 获取日志列表（带分页）
     */
    public function getList($page = 1, $limit = 50, $where = '', $params = [])
    {
        $offset = ($page - 1) * $limit;
        
        $whereSql = $where ? "WHERE {$where}" : '';
        $sql = "SELECT * FROM {$this->table} 
                {$whereSql}
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * 统计日志数量
     */
    public function count($where = '', $params = [])
    {
        $whereSql = $where ? "WHERE {$where}" : '';
        $sql = "SELECT COUNT(*) as total FROM {$this->table} {$whereSql}";
        
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    
    /**
     * 按级别统计
     */
    public function countByLevel($level)
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE log_level = :level";
        $stmt = $this->query($sql, ['level' => $level]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    
    /**
     * 删除旧日志
     */
    public function deleteOldLogs($timestamp)
    {
        $sql = "DELETE FROM {$this->table} WHERE created_at < :timestamp";
        $stmt = $this->query($sql, ['timestamp' => $timestamp]);
        return $stmt->rowCount();
    }
}
