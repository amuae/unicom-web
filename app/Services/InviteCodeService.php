<?php
namespace App\Services;

use App\Models\InviteCode;
use App\Utils\Helper;
use App\Utils\Logger;

/**
 * 邀请码业务逻辑服务
 */
class InviteCodeService {
    private $inviteCodeModel;
    
    public function __construct() {
        $this->inviteCodeModel = new InviteCode();
    }
    
    /**
     * 批量生成邀请码
     */
    public function batchGenerate($data) {
        try {
            $count = intval($data['count'] ?? 1);
            $type = $data['type'] ?? 'single'; // single=一次性, multiple=多次
            $maxUsage = intval($data['max_usage'] ?? 1);
            $expireDays = intval($data['expire_days'] ?? 0);
            $remark = trim($data['remark'] ?? '');
            $createdBy = $data['created_by'] ?? null;
            
            // 验证参数
            if ($count < 1 || $count > 1000) {
                return ['success' => false, 'message' => '生成数量必须在1-1000之间'];
            }
            
            if ($type === 'multiple' && $maxUsage < 2) {
                return ['success' => false, 'message' => '多次邀请码的使用次数必须大于1'];
            }
            
            if ($type === 'single') {
                $maxUsage = 1; // 一次性邀请码固定为1次
            }
            
            // 生成邀请码
            $codes = [];
            $expireAt = $expireDays > 0 ? time() + ($expireDays * 86400) : null;
            
            for ($i = 0; $i < $count; $i++) {
                $codes[] = [
                    'code' => Helper::generateInviteCode(),
                    'type' => $type,
                    'max_usage' => $maxUsage,
                    'used_count' => 0,
                    'status' => 'active',
                    'expire_at' => $expireAt,
                    'remark' => $remark,
                    'created_by' => $createdBy,
                    'created_at' => time(),
                    'updated_at' => time()
                ];
            }
            
            $result = $this->inviteCodeModel->batchInsert($codes);
            
            if ($result) {
                Logger::system("批量生成邀请码: {$count}个，类型:{$type}", 'info', [
                    'count' => $count,
                    'type' => $type,
                    'max_usage' => $maxUsage
                ]);
                // 提取生成的邀请码字符串
                $generatedCodes = array_column($codes, 'code');
                return [
                    'success' => true, 
                    'message' => "成功生成{$count}个邀请码",
                    'codes' => $generatedCodes,
                    'count' => $count
                ];
            }
            
            return ['success' => false, 'message' => '生成失败'];
        } catch (\Exception $e) {
            Logger::error("生成邀请码异常: " . $e->getMessage());
            return ['success' => false, 'message' => '生成失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 验证邀请码
     */
    public function validate($code) {
        try {
            $inviteCode = $this->inviteCodeModel->findByCode($code);
            
            if (!$inviteCode) {
                return ['success' => false, 'message' => '邀请码不存在'];
            }
            
            // 检查状态
            if ($inviteCode['status'] !== 'active') {
                return ['success' => false, 'message' => '邀请码已被禁用'];
            }
            
            // 检查使用次数
            if ($inviteCode['used_count'] >= $inviteCode['max_usage']) {
                return ['success' => false, 'message' => '邀请码已达使用上限'];
            }
            
            // 检查过期时间
            if ($inviteCode['expire_at'] && $inviteCode['expire_at'] < time()) {
                return ['success' => false, 'message' => '邀请码已过期'];
            }
            
            return ['success' => true, 'data' => $inviteCode];
        } catch (\Exception $e) {
            Logger::error("验证邀请码异常: " . $e->getMessage());
            return ['success' => false, 'message' => '验证失败'];
        }
    }
    
    /**
     * 使用邀请码
     */
    public function use($code) {
        try {
            $validateResult = $this->validate($code);
            if (!$validateResult['success']) {
                return $validateResult;
            }
            
            $inviteCode = $validateResult['data'];
            
            // 增加使用次数
            $this->inviteCodeModel->incrementUsage($inviteCode['id']);
            
            Logger::system("使用邀请码: {$code}", 'info', ['invite_code_id' => $inviteCode['id']]);
            
            return ['success' => true, 'message' => '邀请码验证成功'];
        } catch (\Exception $e) {
            Logger::error("使用邀请码异常: " . $e->getMessage());
            return ['success' => false, 'message' => '使用失败'];
        }
    }
    
    /**
     * 获取邀请码列表
     */
    public function getList($page = 1, $limit = 50, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $conditions = [];
            
            // 状态过滤
            if (isset($filters['status']) && $filters['status'] !== '') {
                $conditions['status'] = $filters['status'];
            }
            
            // 类型过滤
            if (isset($filters['type']) && $filters['type'] !== '') {
                $conditions['type'] = $filters['type'];
            }
            
            $result = $this->inviteCodeModel->getList($page, $limit);
            
            return [
                'success' => true, 
                'data' => $result['data'],
                'total' => $result['total'],
                'page' => $result['page'],
                'limit' => $result['limit']
            ];
        } catch (\Exception $e) {
            Logger::error("获取邀请码列表异常: " . $e->getMessage());
            return ['success' => false, 'message' => '获取列表失败'];
        }
    }
    
    /**
     * 更新邀请码状态
     */
    public function updateStatus($id, $status) {
        try {
            if (!in_array($status, ['active', 'disabled'])) {
                return ['success' => false, 'message' => '无效的状态'];
            }
            
            $result = $this->inviteCodeModel->update($id, [
                'status' => $status,
                'updated_at' => time()
            ]);
            
            if ($result) {
                Logger::system("更新邀请码状态: ID={$id}, 状态={$status}", 'info');
                return ['success' => true, 'message' => '状态更新成功'];
            }
            
            return ['success' => false, 'message' => '状态更新失败'];
        } catch (\Exception $e) {
            Logger::error("更新邀请码状态异常: " . $e->getMessage());
            return ['success' => false, 'message' => '更新失败'];
        }
    }
    
    /**
     * 更新多次邀请码的使用上限
     */
    public function updateMaxUsage($id, $maxUsage) {
        try {
            $inviteCode = $this->inviteCodeModel->findById($id);
            
            if (!$inviteCode) {
                return ['success' => false, 'message' => '邀请码不存在'];
            }
            
            if ($inviteCode['type'] !== 'multiple') {
                return ['success' => false, 'message' => '只能修改多次邀请码的使用上限'];
            }
            
            $maxUsage = intval($maxUsage);
            if ($maxUsage < $inviteCode['used_count']) {
                return ['success' => false, 'message' => '使用上限不能小于已使用次数'];
            }
            
            if ($maxUsage < 2) {
                return ['success' => false, 'message' => '多次邀请码的使用次数必须大于1'];
            }
            
            $result = $this->inviteCodeModel->update($id, [
                'max_usage' => $maxUsage,
                'updated_at' => time()
            ]);
            
            if ($result) {
                Logger::system("更新邀请码使用上限: ID={$id}, 新上限={$maxUsage}", 'info');
                return ['success' => true, 'message' => '使用上限更新成功'];
            }
            
            return ['success' => false, 'message' => '更新失败'];
        } catch (\Exception $e) {
            Logger::error("更新邀请码使用上限异常: " . $e->getMessage());
            return ['success' => false, 'message' => '更新失败'];
        }
    }
    
    /**
     * 删除邀请码
     */
    public function delete($id) {
        try {
            $inviteCode = $this->inviteCodeModel->findById($id);
            
            if (!$inviteCode) {
                return ['success' => false, 'message' => '邀请码不存在'];
            }
            
            $result = $this->inviteCodeModel->delete($id);
            
            if ($result) {
                Logger::system("删除邀请码: {$inviteCode['code']}", 'info', ['id' => $id]);
                return ['success' => true, 'message' => '删除成功'];
            }
            
            return ['success' => false, 'message' => '删除失败'];
        } catch (\Exception $e) {
            Logger::error("删除邀请码异常: " . $e->getMessage());
            return ['success' => false, 'message' => '删除失败'];
        }
    }
}
