<?php
namespace App\Services;

use App\Models\User;
use App\Utils\Helper;
use App\Utils\Logger;

/**
 * 用户业务逻辑服务
 */
class UserService {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
    }
    
    /**
     * 创建用户
     */
    public function create($data) {
        try {
            // 验证手机号
            if (!Helper::isMobile($data['mobile'])) {
                return ['success' => false, 'message' => '手机号格式不正确'];
            }
            
            // 检查手机号是否已存在
            if ($this->userModel->findByMobile($data['mobile'])) {
                return ['success' => false, 'message' => '该手机号已注册'];
            }
            
            // 验证查询密码
            if (empty($data['query_password'])) {
                return ['success' => false, 'message' => '查询密码不能为空'];
            }
            
            // 验证认证类型
            $authType = $data['auth_type'] ?? 'password';
            if (!in_array($authType, ['password', 'token_online', 'cookie'])) {
                return ['success' => false, 'message' => '认证类型不正确'];
            }
            
            // 根据认证类型验证必填字段
            if ($authType === 'token_online' && (empty($data['appid']) || empty($data['token_online']))) {
                return ['success' => false, 'message' => 'token_online认证需要提供appid和token_online'];
            }
            
            if ($authType === 'cookie' && empty($data['cookie'])) {
                return ['success' => false, 'message' => 'cookie认证需要提供cookie'];
            }
            
            // 创建用户（token由AdminController生成并传入）
            $userId = $this->userModel->insert([
                'mobile' => $data['mobile'],
                'nickname' => $data['nickname'] ?? '',
                'query_password' => Helper::hashPassword($data['query_password']),
                'token' => $data['token'] ?? '', // token应该由外部传入
                'auth_type' => $authType,
                'appid' => $data['appid'] ?? '',
                'token_online' => $data['token_online'] ?? '',
                'cookie' => $data['cookie'] ?? '',
                'status' => 'active'
            ]);
            
            Logger::system("创建用户: {$data['mobile']}", 'info', ['user_id' => $userId]);
            
            return ['success' => true, 'message' => '用户创建成功', 'data' => ['id' => $userId]];
        } catch (\Exception $e) {
            Logger::error("创建用户异常: " . $e->getMessage());
            return ['success' => false, 'message' => '创建失败，请稍后重试'];
        }
    }
    
    /**
     * 更新用户
     */
    public function update($userId, $data) {
        try {
            $user = $this->userModel->find($userId);
            if (!$user) {
                return ['success' => false, 'message' => '用户不存在'];
            }
            
            $updateData = [];
            
            // 允许更新的字段
            $allowedFields = ['nickname', 'query_password', 'auth_type', 'appid', 'token_online', 'cookie', 'status'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    if ($field === 'query_password') {
                        $updateData[$field] = Helper::hashPassword($data[$field]);
                    } else {
                        $updateData[$field] = $data[$field];
                    }
                }
            }
            
            if (empty($updateData)) {
                return ['success' => false, 'message' => '没有需要更新的字段'];
            }
            
            $this->userModel->update($userId, $updateData);
            
            Logger::system("更新用户: {$user['mobile']}", 'info', ['user_id' => $userId]);
            
            return ['success' => true, 'message' => '用户更新成功'];
        } catch (\Exception $e) {
            Logger::error("更新用户异常: " . $e->getMessage());
            return ['success' => false, 'message' => '更新失败，请稍后重试'];
        }
    }
    
    /**
     * 删除用户
     */
    public function delete($userId) {
        try {
            $user = $this->userModel->find($userId);
            if (!$user) {
                return ['success' => false, 'message' => '用户不存在'];
            }
            
            $this->userModel->delete($userId);
            
            Logger::system("删除用户: {$user['mobile']}", 'info', ['user_id' => $userId]);
            
            return ['success' => true, 'message' => '用户删除成功'];
        } catch (\Exception $e) {
            Logger::error("删除用户异常: " . $e->getMessage());
            return ['success' => false, 'message' => '删除失败，请稍后重试'];
        }
    }
    
    /**
     * 获取用户列表
     */
    public function getList($page = 1, $pageSize = 20, $status = null) {
        try {
            $offset = ($page - 1) * $pageSize;
            
            if ($status) {
                $users = $this->userModel->findAllBy(['status' => $status], 'created_at DESC', $pageSize);
                $total = $this->userModel->count(['status' => $status]);
            } else {
                $users = $this->userModel->all('created_at DESC', $pageSize);
                $total = $this->userModel->count();
            }
            
            // 移除敏感信息
            foreach ($users as &$user) {
                unset($user['query_password']);
                unset($user['token_online']);
                unset($user['cookie']);
            }
            
            return [
                'success' => true,
                'data' => [
                    'list' => $users,
                    'total' => $total,
                    'page' => $page,
                    'pageSize' => $pageSize
                ]
            ];
        } catch (\Exception $e) {
            Logger::error("获取用户列表异常: " . $e->getMessage());
            return ['success' => false, 'message' => '获取失败'];
        }
    }
    
    /**
     * 获取用户详情
     */
    public function getDetail($userId) {
        try {
            $user = $this->userModel->find($userId);
            
            if (!$user) {
                return ['success' => false, 'message' => '用户不存在'];
            }
            
            // 移除敏感信息
            unset($user['query_password']);
            
            return ['success' => true, 'data' => $user];
        } catch (\Exception $e) {
            Logger::error("获取用户详情异常: " . $e->getMessage());
            return ['success' => false, 'message' => '获取失败'];
        }
    }
    
    /**
     * 获取用户统计
     */
    public function getStats() {
        try {
            $stats = $this->userModel->getStats();
            return ['success' => true, 'data' => $stats];
        } catch (\Exception $e) {
            Logger::error("获取用户统计异常: " . $e->getMessage());
            return ['success' => false, 'message' => '获取失败'];
        }
    }
    
    /**
     * 验证用户查询密码
     */
    public function verifyQueryPassword($mobile, $password) {
        try {
            $user = $this->userModel->findByMobile($mobile);
            
            if (!$user) {
                return ['success' => false, 'message' => '用户不存在'];
            }
            
            // 验证密码：兼容明文密码和哈希密码
            $passwordValid = false;
            
            // 检查是否是哈希密码（password_hash 生成的以 $2y$ 开头）
            if (strpos($user['query_password'], '$2y$') === 0 || strpos($user['query_password'], '$2a$') === 0) {
                // 使用 password_verify 验证哈希密码
                $passwordValid = Helper::verifyPassword($password, $user['query_password']);
            } else {
                // 明文密码直接比较
                $passwordValid = ($password === $user['query_password']);
            }
            
            if (!$passwordValid) {
                return ['success' => false, 'message' => '密码错误'];
            }
            
            return ['success' => true, 'data' => $user];
        } catch (\Exception $e) {
            Logger::error("验证用户密码异常: " . $e->getMessage());
            return ['success' => false, 'message' => '验证失败'];
        }
    }
    
    /**
     * 用户认证（查询链接）
     * @param string $mobile 手机号
     * @param string $password 查询密码
     * @return array
     */
    public function authenticate($mobile, $password) {
        try {
            $user = $this->userModel->findByMobile($mobile);
            
            if (!$user) {
                return ['success' => false, 'message' => '用户不存在'];
            }
            
            if ($user['status'] !== 'active') {
                return ['success' => false, 'message' => '用户已被禁用'];
            }
            
            // 验证密码：兼容明文密码和哈希密码
            $passwordValid = false;
            
            // 检查是否是哈希密码（password_hash 生成的以 $2y$ 开头）
            if (strpos($user['query_password'], '$2y$') === 0 || strpos($user['query_password'], '$2a$') === 0) {
                // 使用 password_verify 验证哈希密码
                $passwordValid = Helper::verifyPassword($password, $user['query_password']);
            } else {
                // 明文密码直接比较
                $passwordValid = ($password === $user['query_password']);
            }
            
            if (!$passwordValid) {
                Logger::system("用户登录失败: 密码错误 - {$mobile}", 'warning');
                return ['success' => false, 'message' => '密码错误'];
            }
            
            // 移除敏感信息
            unset($user['query_password']);
            
            Logger::system("用户查询链接: {$mobile}", 'info', ['user_id' => $user['id']]);
            
            return ['success' => true, 'user' => $user];
        } catch (\Exception $e) {
            Logger::error("用户认证异常: " . $e->getMessage());
            return ['success' => false, 'message' => '认证失败'];
        }
    }
}

