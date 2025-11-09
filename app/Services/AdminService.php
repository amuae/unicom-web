<?php
namespace App\Services;

use App\Models\Admin;
use App\Utils\Helper;
use App\Utils\Logger;

/**
 * 管理员业务逻辑服务
 */
class AdminService {
    private $adminModel;
    
    public function __construct() {
        $this->adminModel = new Admin();
    }
    
    /**
     * 管理员登录
     */
    public function login($username, $password) {
        try {
            // 查找管理员
            $admin = $this->adminModel->findByUsername($username);
            
            if (!$admin) {
                Logger::system("管理员登录失败: 用户名不存在 - {$username}", 'warning');
                return ['success' => false, 'message' => '用户名或密码错误'];
            }
            
            // 验证密码
            if (!Helper::verifyPassword($password, $admin['password'])) {
                Logger::system("管理员登录失败: 密码错误 - {$username}", 'warning');
                return ['success' => false, 'message' => '用户名或密码错误'];
            }
            
            // 更新最后登录时间
            $this->adminModel->updateLastLogin($admin['id']);
            
            Logger::system("管理员登录成功: {$username}", 'info', ['admin_id' => $admin['id']]);
            
            return [
                'success' => true,
                'message' => '登录成功',
                'data' => [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'real_name' => $admin['real_name'],
                    'email' => $admin['email']
                ]
            ];
        } catch (\Exception $e) {
            Logger::error("管理员登录异常: " . $e->getMessage());
            return ['success' => false, 'message' => '登录失败，请稍后重试'];
        }
    }
    
    /**
     * 修改密码
     */
    public function changePassword($adminId, $oldPassword, $newPassword) {
        try {
            $admin = $this->adminModel->find($adminId);
            
            if (!$admin) {
                return ['success' => false, 'message' => '管理员不存在'];
            }
            
            // 验证旧密码
            if (!Helper::verifyPassword($oldPassword, $admin['password'])) {
                return ['success' => false, 'message' => '原密码错误'];
            }
            
            // 验证新密码强度
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => '新密码长度不能少于6位'];
            }
            
            // 更新密码
            $this->adminModel->update($adminId, [
                'password' => Helper::hashPassword($newPassword)
            ]);
            
            Logger::system("管理员修改密码: {$admin['username']}", 'info', ['admin_id' => $adminId]);
            
            return ['success' => true, 'message' => '密码修改成功'];
        } catch (\Exception $e) {
            Logger::error("修改密码异常: " . $e->getMessage());
            return ['success' => false, 'message' => '修改失败，请稍后重试'];
        }
    }
    
    /**
     * 创建管理员
     */
    public function create($data) {
        try {
            // 验证用户名
            if (empty($data['username']) || strlen($data['username']) < 3) {
                return ['success' => false, 'message' => '用户名长度不能少于3位'];
            }
            
            // 检查用户名是否已存在
            if ($this->adminModel->findByUsername($data['username'])) {
                return ['success' => false, 'message' => '用户名已存在'];
            }
            
            // 验证密码
            if (empty($data['password']) || strlen($data['password']) < 6) {
                return ['success' => false, 'message' => '密码长度不能少于6位'];
            }
            
            // 创建管理员
            $adminId = $this->adminModel->insert([
                'username' => $data['username'],
                'password' => Helper::hashPassword($data['password']),
                'real_name' => $data['real_name'] ?? '',
                'email' => $data['email'] ?? ''
            ]);
            
            Logger::system("创建管理员: {$data['username']}", 'info', ['admin_id' => $adminId]);
            
            return ['success' => true, 'message' => '管理员创建成功', 'data' => ['id' => $adminId]];
        } catch (\Exception $e) {
            Logger::error("创建管理员异常: " . $e->getMessage());
            return ['success' => false, 'message' => '创建失败，请稍后重试'];
        }
    }
    
    /**
     * 获取管理员信息
     */
    public function getInfo($adminId) {
        try {
            $admin = $this->adminModel->find($adminId);
            
            if (!$admin) {
                return ['success' => false, 'message' => '管理员不存在'];
            }
            
            unset($admin['password']); // 移除密码字段
            
            return ['success' => true, 'data' => $admin];
        } catch (\Exception $e) {
            Logger::error("获取管理员信息异常: " . $e->getMessage());
            return ['success' => false, 'message' => '获取失败'];
        }
    }
}
