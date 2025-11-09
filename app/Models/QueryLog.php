<?php
namespace App\Models;

use PDO;

/**
 * 查询日志模型
 * 处理流量查询日志的数据库操作
 */
class QueryLog {
    private $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    /**
     * 创建查询日志
     * @param array $data 日志数据
     * @return int|false 插入的日志ID或false
     */
    public function create($data) {
        $sql = "INSERT INTO query_logs (
            user_id, mobile, query_result, query_status, 
            error_message, flow_used, flow_total, 
            ip_address, user_agent, created_at
        ) VALUES (
            :user_id, :mobile, :query_result, :query_status,
            :error_message, :flow_used, :flow_total,
            :ip_address, :user_agent, :created_at
        )";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $data['user_id'],
            ':mobile' => $data['mobile'],
            ':query_result' => $data['query_result'] ?? null,
            ':query_status' => $data['query_status'] ?? 'success',
            ':error_message' => $data['error_message'] ?? null,
            ':flow_used' => $data['flow_used'] ?? 0,
            ':flow_total' => $data['flow_total'] ?? 0,
            ':ip_address' => $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':created_at' => time()
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    /**
     * 获取用户的查询日志
     * @param int $userId 用户ID
     * @param int $limit 返回数量
     * @return array 日志列表
     */
    public function getByUserId($userId, $limit = 10) {
        $sql = "SELECT * FROM query_logs 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取所有查询日志（分页）
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array 包含total和data的数组
     */
    public function getAll($page = 1, $pageSize = 20) {
        $offset = ($page - 1) * $pageSize;
        
        // 获取总数
        $countSql = "SELECT COUNT(*) as count FROM query_logs";
        $total = $this->db->query($countSql)->fetch(PDO::FETCH_ASSOC)['count'];
        
        // 获取数据
        $sql = "SELECT ql.*, u.mobile, u.nickname 
                FROM query_logs ql
                LEFT JOIN users u ON ql.user_id = u.id
                ORDER BY ql.created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }
    
    /**
     * 清理旧日志
     * @param int $days 保留天数
     * @return int 删除的记录数
     */
    public function cleanOldLogs($days = 30) {
        $timestamp = time() - ($days * 86400);
        
        $sql = "DELETE FROM query_logs WHERE created_at < :timestamp";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':timestamp' => $timestamp]);
        
        return $stmt->rowCount();
    }
    
    /**
     * 获取统计信息
     * @return array 统计数据
     */
    public function getStats() {
        $sql = "SELECT 
            COUNT(*) as total_queries,
            COUNT(CASE WHEN query_status = 'success' THEN 1 END) as success_count,
            COUNT(CASE WHEN query_status = 'failed' THEN 1 END) as failed_count,
            SUM(flow_used) as total_flow_used,
            DATE(created_at, 'unixepoch') as query_date
            FROM query_logs 
            WHERE created_at >= :start_time
            GROUP BY query_date
            ORDER BY query_date DESC
            LIMIT 30";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':start_time' => time() - (30 * 86400)]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取今日查询统计
     * @return array 今日统计
     */
    public function getTodayStats() {
        $todayStart = strtotime('today');
        
        $sql = "SELECT 
            COUNT(*) as total_queries,
            COUNT(CASE WHEN query_status = 'success' THEN 1 END) as success_count,
            COUNT(CASE WHEN query_status = 'failed' THEN 1 END) as failed_count,
            COUNT(DISTINCT user_id) as unique_users
            FROM query_logs 
            WHERE created_at >= :today_start";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':today_start' => $todayStart]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
