<?php

namespace App\Models;

/**
 * 定时任务模型 - 纯数据访问层
 */
class CronTask extends Database
{
    protected $table = 'cron_tasks';
    
    /**
     * 获取所有启用的任务
     */
    public function getActiveTasks()
    {
        return $this->findAllBy(['status' => 'active'], 'next_run_at ASC');
    }
    
    /**
     * 获取待执行的任务
     * @param int $currentTime 当前时间戳
     */
    public function getPendingTasks($currentTime = null)
    {
        if ($currentTime === null) {
            $currentTime = time();
        }
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE status = 'active' 
                AND next_run_at <= ? 
                AND (last_run_status IS NULL OR last_run_status != 'running')
                ORDER BY next_run_at ASC";
        
        return $this->query($sql, [$currentTime])->fetchAll();
    }
    
    /**
     * 更新任务执行记录
     */
    public function updateRunRecord($id, $success, $message = '')
    {
        $updates = [
            'last_run_at' => time(),
            'run_count' => $this->db->query("SELECT run_count FROM {$this->table} WHERE id = ?", [$id])->fetchColumn() + 1
        ];
        
        if ($success) {
            $updates['last_success_at'] = time();
        } else {
            $updates['last_error'] = $message;
        }
        
        return $this->update($id, $updates);
    }
    
    /**
     * 根据任务类型查找任务
     */
    public function findByType($taskType)
    {
        return $this->findAllBy(['task_type' => $taskType]);
    }
    
    /**
     * 获取任务列表
     */
    public function getList()
    {
        return $this->findAll([], 'created_at DESC');
    }
    
    /**
     * 更新任务状态
     */
    public function updateStatus($id, $status, $message = null)
    {
        $data = [
            'status' => $status,
            'updated_at' => time()
        ];
        
        if ($message !== null) {
            $data['last_run_message'] = $message;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * 记录任务执行结果
     */
    public function recordExecution($id, $status, $message = null, $duration = null)
    {
        $data = [
            'last_run_at' => time(),
            'last_run_status' => $status,
            'last_run_message' => $message,
            'updated_at' => time()
        ];
        
        if ($duration !== null) {
            $data['last_run_duration'] = $duration;
        }
        
        // 更新统计计数
        if ($status === 'success') {
            $sql = "UPDATE {$this->table} 
                    SET total_runs = total_runs + 1,
                        success_runs = success_runs + 1,
                        last_run_at = ?,
                        last_run_status = ?,
                        last_run_message = ?,
                        updated_at = ?
                    WHERE id = ?";
            $params = [time(), $status, $message, time(), $id];
        } else {
            $sql = "UPDATE {$this->table} 
                    SET total_runs = total_runs + 1,
                        failed_runs = failed_runs + 1,
                        last_run_at = ?,
                        last_run_status = ?,
                        last_run_message = ?,
                        updated_at = ?
                    WHERE id = ?";
            $params = [time(), $status, $message, time(), $id];
        }
        
        return $this->execute($sql, $params);
    }
    
    /**
     * 更新下次运行时间
     */
    public function updateNextRunTime($id, $nextRunAt)
    {
        return $this->update($id, [
            'next_run_at' => $nextRunAt,
            'updated_at' => time()
        ]);
    }
    
    /**
     * 根据任务类型和参数查找任务
     */
    public function findByTypeAndParams($taskType, $searchKey, $searchValue)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE task_type = ? 
                AND task_params LIKE ?";
        
        $searchPattern = '%"' . $searchKey . '":' . $searchValue . '%';
        
        $result = $this->query($sql, [$taskType, $searchPattern])->fetch();
        return $result ?: null;
    }
    
    /**
     * 获取任务统计信息
     */
    public function getStats()
    {
        $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'disabled' THEN 1 ELSE 0 END) as disabled,
            COALESCE(SUM(total_runs), 0) as total_runs,
            COALESCE(SUM(success_runs), 0) as success_runs,
            COALESCE(SUM(failed_runs), 0) as failed_runs
        FROM {$this->table}";
        
        return $this->query($sql)->fetch();
    }
}
