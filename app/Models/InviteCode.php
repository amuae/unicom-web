<?php

namespace App\Models;

/**
 * 邀请码模型 - 纯数据访问层
 */
class InviteCode extends Database
{
    protected $table = 'invite_codes';
    
    /**
     * 根据邀请码查找
     */
    public function findByCode($code)
    {
        return $this->findOne(['code' => $code]);
    }
    
    /**
     * 获取可用的邀请码
     */
    public function getAvailableCodes($limit = 100)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE status = 'active' 
                AND (expire_at IS NULL OR expire_at > :now)
                AND used_count < max_usage
                ORDER BY created_at DESC
                LIMIT :limit";
        
        return $this->query($sql, [
            'now' => time(),
            'limit' => $limit
        ]);
    }
    
    /**
     * 增加使用次数
     */
    public function incrementUsage($id)
    {
        $code = $this->findById($id);
        if (!$code) {
            return false;
        }
        
        $newUsedCount = $code['used_count'] + 1;
        $updates = ['used_count' => $newUsedCount];
        
        // 如果达到最大使用次数，更新状态
        if ($newUsedCount >= $code['max_usage']) {
            $updates['status'] = 'used';
        }
        
        return $this->update($id, $updates);
    }
    
    /**
     * 批量插入邀请码
     */
    public function batchInsert($codes)
    {
        if (empty($codes)) {
            return false;
        }
        
        $placeholders = [];
        $values = [];
        
        foreach ($codes as $code) {
            $placeholders[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $values[] = $code['code'];
            $values[] = $code['type'] ?? 'single';
            $values[] = $code['max_usage'];
            $values[] = $code['used_count'];
            $values[] = $code['status'];
            $values[] = $code['expire_at'];
            $values[] = $code['remark'] ?? '';
            $values[] = $code['created_by'] ?? null;
            $values[] = $code['created_at'];
            $values[] = $code['updated_at'] ?? $code['created_at'];
        }
        
        $sql = "INSERT INTO {$this->table} 
                (code, type, max_usage, used_count, status, expire_at, remark, created_by, created_at, updated_at) 
                VALUES " . implode(', ', $placeholders);
        
        return $this->execute($sql, $values);
    }
    
    /**
     * 获取邀请码列表（带分页）
     */
    public function getList($page = 1, $limit = 50)
    {
        $offset = ($page - 1) * $limit;
        
        // 查询总数
        $countSql = "SELECT COUNT(*) as total FROM {$this->table}";
        $totalResult = $this->query($countSql, [])->fetch();
        $total = $totalResult['total'] ?? 0;
        
        // 查询数据
        $sql = "SELECT * FROM {$this->table} 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->query($sql, [
            'limit' => $limit,
            'offset' => $offset
        ]);
        
        $data = $stmt->fetchAll();
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }
    
    /**
     * 统计可用邀请码数量
     */
    public function countAvailable()
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE status = 'active' 
                AND (expire_at IS NULL OR expire_at > :now)
                AND used_count < max_usage";
        
        $result = $this->queryOne($sql, ['now' => time()]);
        return $result['count'] ?? 0;
    }
}
