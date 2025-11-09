<?php
namespace App\Models;

/**
 * 用户模型 - 纯数据访问层
 */
class User extends Database {
    protected $table = 'users';
    
    /**
     * 根据手机号查找用户
     */
    public function findByMobile($mobile) {
        return $this->findBy(['mobile' => $mobile]);
    }
    
    /**
     * 根据token查找用户
     */
    public function findByToken($token) {
        return $this->findBy(['token' => $token]);
    }
    
    /**
     * 获取活跃用户列表
     */
    public function getActiveUsers() {
        return $this->findAllBy(['status' => 'active'], 'created_at DESC');
    }
    
    /**
     * 获取用户统计信息
     */
    public function getStats() {
        $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'disabled' THEN 1 ELSE 0 END) as disabled
        FROM {$this->table}";
        
        return $this->query($sql)->fetch();
    }
    
    /**
     * 更新最后查询时间
     */
    public function updateLastQuery($id) {
        return $this->update($id, ['last_query_at' => $this->now()]);
    }
}
