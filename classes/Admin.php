<?php
/**
 * 管理员模型类
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Utils.php';

class Admin {
    private $db;
    public $id;
    public $username;
    public $email;
    public $createdAt;
    public $lastLoginAt;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 管理员登录
     */
    public static function login($username, $password) {
        $db = Database::getInstance();
        
        try {
            $stmt = $db->prepare("SELECT * FROM admins WHERE username = :username LIMIT 1");
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            
            if (!$row) {
                return ['success' => false, 'message' => '用户名或密码错误'];
            }
            
            // 验证密码
            if (!Utils::verifyPassword($password, $row['password'])) {
                return ['success' => false, 'message' => '用户名或密码错误'];
            }
            
            // 更新最后登录时间
            $updateStmt = $db->prepare(
                "UPDATE admins SET last_login_at = datetime('now') WHERE id = :id"
            );
            $updateStmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $updateStmt->execute();
            
            // 生成会话token
            $sessionToken = Utils::generateToken(32);
            
            // 保存到session（避免重复启动session）
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_username'] = $row['username'];
            $_SESSION['admin_token'] = $sessionToken;
            $_SESSION['admin_login_time'] = time();
            // 保存完整的admin数据供其他接口使用
            $_SESSION['admin'] = [
                'id' => $row['id'],
                'username' => $row['username'],
                'email' => $row['email']
            ];
            
            // 记录日志
            Utils::logOperation('admin_login', "管理员 {$username} 登录", null, $row['id']);
            
            return [
                'success' => true,
                'data' => [
                    'id' => $row['id'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'token' => $sessionToken
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Admin Login Error: " . $e->getMessage());
            return ['success' => false, 'message' => '登录失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 检查管理员是否已登录
     */
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_token'])) {
            return false;
        }
        
        // 检查会话是否过期（2小时）
        $loginTime = $_SESSION['admin_login_time'] ?? 0;
        if (time() - $loginTime > 7200) {
            self::logout();
            return false;
        }
        
        return true;
    }
    
    /**
     * 获取当前登录的管理员
     */
    public static function current() {
        if (!self::check()) {
            return null;
        }
        
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM admins WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $_SESSION['admin_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        $admin = new self();
        $admin->id = $row['id'];
        $admin->username = $row['username'];
        $admin->email = $row['email'];
        $admin->createdAt = $row['created_at'];
        $admin->lastLoginAt = $row['last_login_at'];
        
        return $admin;
    }
    
    /**
     * 退出登录
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $adminId = $_SESSION['admin_id'] ?? null;
        if ($adminId) {
            Utils::logOperation('admin_logout', '管理员退出登录', null, $adminId);
        }
        
        session_destroy();
        return true;
    }
    
    /**
     * 修改密码
     */
    public function changePassword($oldPassword, $newPassword) {
        try {
            // 验证旧密码
            $stmt = $this->db->prepare("SELECT password FROM admins WHERE id = :id");
            $stmt->bindValue(':id', $this->id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            
            if (!Utils::verifyPassword($oldPassword, $row['password'])) {
                return ['success' => false, 'message' => '原密码错误'];
            }
            
            // 更新密码
            $newHash = Utils::hashPassword($newPassword);
            $updateStmt = $this->db->prepare(
                "UPDATE admins SET password = :password, updated_at = datetime('now') WHERE id = :id"
            );
            $updateStmt->bindValue(':password', $newHash, SQLITE3_TEXT);
            $updateStmt->bindValue(':id', $this->id, SQLITE3_INTEGER);
            $updateStmt->execute();
            
            Utils::logOperation('admin_change_password', '管理员修改密码', null, $this->id);
            
            return ['success' => true, 'message' => '密码修改成功'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => '密码修改失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 更新邮箱
     */
    public function updateEmail($email) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE admins SET email = :email, updated_at = datetime('now') WHERE id = :id"
            );
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':id', $this->id, SQLITE3_INTEGER);
            $stmt->execute();
            
            $this->email = $email;
            
            Utils::logOperation('admin_update_email', "更新邮箱为 {$email}", null, $this->id);
            
            return ['success' => true, 'message' => '邮箱更新成功'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => '邮箱更新失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 获取管理员信息
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'created_at' => $this->createdAt,
            'last_login_at' => $this->lastLoginAt
        ];
    }
}
