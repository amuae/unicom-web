<?php
/**
 * 用户模型类
 * 支持两种认证方式：full（手机号+APPID+TOKEN）和 cookie（手机号+Cookie）
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/Config.php';

class User {
    private $db;
    
    public $id;
    public $mobile;
    public $appid;
    public $tokenOnline;
    public $cookie;
    public $authType;
    public $accessToken;
    public $isActive;
    public $status;         // 新增：用户状态（active/disabled）
    public $userType;       // 新增：用户类型（beta/activated）
    public $createdAt;
    public $updatedAt;
    public $lastQueryAt;
    public $remark;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 创建新用户（方式一：完整凭证）
     */
    public static function createWithFullAuth($mobile, $appid, $tokenOnline, $remark = '') {
        $db = Database::getInstance();
        
        try {
            // 验证手机号
            if (!Utils::validateMobile($mobile)) {
                return ['success' => false, 'message' => '手机号格式不正确'];
            }
            
            // 检查是否已存在
            if (self::existsByMobile($mobile)) {
                return ['success' => false, 'message' => '该手机号已添加'];
            }
            
            // 检查用户数量限制
            $config = Config::getInstance();
            $maxUsers = $config->getMaxUsers();
            if ($maxUsers > 0 && self::count() >= $maxUsers) {
                return ['success' => false, 'message' => "用户数量已达上限（{$maxUsers}）"];
            }
            
            // 加密敏感数据
            $encryptedAppid = Utils::encrypt($appid);
            $encryptedToken = Utils::encrypt($tokenOnline);
            
            // 生成访问token
            $accessToken = Utils::generateToken(32);
            
            // 插入数据库
            $stmt = $db->prepare(
                "INSERT INTO users (mobile, appid, token_online, auth_type, access_token, remark) 
                 VALUES (:mobile, :appid, :token, 'full', :access_token, :remark)"
            );
            $stmt->bindValue(':mobile', $mobile, SQLITE3_TEXT);
            $stmt->bindValue(':appid', $encryptedAppid, SQLITE3_TEXT);
            $stmt->bindValue(':token', $encryptedToken, SQLITE3_TEXT);
            $stmt->bindValue(':access_token', $accessToken, SQLITE3_TEXT);
            $stmt->bindValue(':remark', $remark, SQLITE3_TEXT);
            $stmt->execute();
            
            $userId = $db->lastInsertId();
            
            // 记录日志
            Utils::logOperation('user_create', "创建用户 {$mobile} (完整凭证)", $userId);
            
            return [
                'success' => true,
                'message' => '用户创建成功',
                'data' => [
                    'user_id' => $userId,
                    'mobile' => $mobile,
                    'access_token' => $accessToken,
                    'access_url' => self::getAccessUrl($accessToken)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Create User Error: " . $e->getMessage());
            return ['success' => false, 'message' => '创建失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 创建新用户（方式二：Cookie）
     */
    public static function createWithCookie($mobile, $cookie, $remark = '') {
        $db = Database::getInstance();
        
        try {
            // 验证
            if (!Utils::validateMobile($mobile)) {
                return ['success' => false, 'message' => '手机号格式不正确'];
            }
            
            if (!Utils::validateCookie($cookie)) {
                return ['success' => false, 'message' => 'Cookie格式不正确'];
            }
            
            // 检查是否已存在
            if (self::existsByMobile($mobile)) {
                return ['success' => false, 'message' => '该手机号已添加'];
            }
            
            // 检查用户数量限制
            $config = Config::getInstance();
            $maxUsers = $config->getMaxUsers();
            if ($maxUsers > 0 && self::count() >= $maxUsers) {
                return ['success' => false, 'message' => "用户数量已达上限（{$maxUsers}）"];
            }
            
            // 加密Cookie
            $encryptedCookie = Utils::encrypt($cookie);
            
            // 生成访问token
            $accessToken = Utils::generateToken(32);
            
            // 插入数据库
            $stmt = $db->prepare(
                "INSERT INTO users (mobile, cookie, auth_type, access_token, cookie_updated_at, remark) 
                 VALUES (:mobile, :cookie, 'cookie', :access_token, datetime('now'), :remark)"
            );
            $stmt->bindValue(':mobile', $mobile, SQLITE3_TEXT);
            $stmt->bindValue(':cookie', $encryptedCookie, SQLITE3_TEXT);
            $stmt->bindValue(':access_token', $accessToken, SQLITE3_TEXT);
            $stmt->bindValue(':remark', $remark, SQLITE3_TEXT);
            $stmt->execute();
            
            $userId = $db->lastInsertId();
            
            // 记录日志
            Utils::logOperation('user_create', "创建用户 {$mobile} (Cookie)", $userId);
            
            return [
                'success' => true,
                'message' => '用户创建成功',
                'data' => [
                    'user_id' => $userId,
                    'mobile' => $mobile,
                    'access_token' => $accessToken,
                    'access_url' => self::getAccessUrl($accessToken)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Create User Error: " . $e->getMessage());
            return ['success' => false, 'message' => '创建失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 通过 access_token 查找用户
     */
    public static function findByToken($token) {
        $db = Database::getInstance();
        
        try {
            // 使用status字段而不是is_active
            $stmt = $db->prepare("SELECT * FROM users WHERE access_token = :token AND (status = 'active' OR is_active = 1) LIMIT 1");
            $stmt->bindValue(':token', $token, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            
            if (!$row) {
                return null;
            }
            
            return self::fromArray($row);
            
        } catch (Exception $e) {
            error_log("Find User Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 通过 ID 查找用户
     */
    public static function findById($id) {
        $db = Database::getInstance();
        
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            
            if (!$row) {
                return null;
            }
            
            return self::fromArray($row);
            
        } catch (Exception $e) {
            error_log("Find User Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 检查手机号是否存在
     */
    public static function existsByMobile($mobile) {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE mobile = :mobile");
        $stmt->bindValue(':mobile', $mobile, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        return $row['count'] > 0;
    }
    
    /**
     * 根据手机号查找用户
     */
    public static function findByMobile($mobile) {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT * FROM users WHERE mobile = :mobile LIMIT 1");
        $stmt->bindValue(':mobile', $mobile, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        // 创建User对象
        $user = new self();
        $user->id = $row['id'];
        $user->mobile = $row['mobile'];
        $user->authType = $row['auth_type'];
        $user->accessToken = $row['access_token'];
        $user->isActive = $row['is_active'];
        $user->status = $row['status'] ?? 'active';
        $user->userType = $row['user_type'] ?? 'beta';
        $user->createdAt = $row['created_at'];
        $user->updatedAt = $row['updated_at'];
        $user->lastQueryAt = $row['last_query_at'] ?? null;
        $user->remark = $row['remark'] ?? '';
        
        // 解密敏感数据
        if ($row['auth_type'] === 'full') {
            $user->appid = Utils::decrypt($row['appid']);
            $user->tokenOnline = Utils::decrypt($row['token_online']);
        } else {
            $user->cookie = Utils::decrypt($row['cookie']);
        }
        
        return $user;
    }
    
    /**
     * 获取用户总数
     */
    public static function count($activeOnly = true) {
        $db = Database::getInstance();
        
        $sql = "SELECT COUNT(*) as count FROM users";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        
        $result = $db->query($sql);
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        return $row['count'];
    }
    
    /**
     * 获取所有用户列表
     */
    public static function getAll($page = 1, $perPage = 20) {
        $db = Database::getInstance();
        
        $offset = ($page - 1) * $perPage;
        
        $stmt = $db->prepare(
            "SELECT * FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $users = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = self::fromArray($row);
        }
        
        return $users;
    }
    
    /**
     * 从数组创建用户对象
     */
    private static function fromArray($row) {
        $user = new self();
        $user->id = $row['id'];
        $user->mobile = $row['mobile'];
        $user->appid = $row['appid'] ? Utils::decrypt($row['appid']) : null;
        $user->tokenOnline = $row['token_online'] ? Utils::decrypt($row['token_online']) : null;
        $user->cookie = $row['cookie'] ? Utils::decrypt($row['cookie']) : null;
        $user->authType = $row['auth_type'];
        $user->accessToken = $row['access_token'];
        $user->isActive = $row['is_active'];
        $user->status = $row['status'] ?? 'active';
        $user->userType = $row['user_type'] ?? 'beta';
        $user->createdAt = $row['created_at'];
        $user->updatedAt = $row['updated_at'];
        $user->lastQueryAt = $row['last_query_at'];
        $user->remark = $row['remark'];
        
        return $user;
    }
    
    /**
     * 更新 Cookie
     */
    public function updateCookie($cookie) {
        try {
            $encryptedCookie = Utils::encrypt($cookie);
            
            $stmt = $this->db->prepare(
                "UPDATE users SET cookie = :cookie, cookie_updated_at = datetime('now'), 
                 updated_at = datetime('now') WHERE id = :id"
            );
            $stmt->bindValue(':cookie', $encryptedCookie, SQLITE3_TEXT);
            $stmt->bindValue(':id', $this->id, SQLITE3_INTEGER);
            $stmt->execute();
            
            $this->cookie = $cookie;
            
            Utils::logOperation('user_update_cookie', "更新Cookie", $this->id);
            
            return ['success' => true, 'message' => 'Cookie更新成功'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Cookie更新失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 更新最后查询时间
     */
    public function updateLastQueryTime() {
        try {
            $stmt = $this->db->prepare(
                "UPDATE users SET last_query_at = datetime('now') WHERE id = :id"
            );
            $stmt->bindValue(':id', $this->id, SQLITE3_INTEGER);
            $stmt->execute();
            
            return true;
        } catch (Exception $e) {
            error_log("Update Last Query Time Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除用户
     */
    public function delete() {
        try {
            // 删除数据库记录
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindValue(':id', $this->id, SQLITE3_INTEGER);
            $stmt->execute();
            
            // 删除用户数据文件夹
            if (!empty($this->accessToken)) {
                $userDataDir = __DIR__ . '/../data/' . $this->accessToken;
                if (is_dir($userDataDir)) {
                    self::deleteDirectory($userDataDir);
                    Utils::logOperation('user_delete', "删除用户 {$this->mobile} 及数据文件夹", $this->id);
                } else {
                    Utils::logOperation('user_delete', "删除用户 {$this->mobile} (数据文件夹不存在)", $this->id);
                }
            } else {
                Utils::logOperation('user_delete', "删除用户 {$this->mobile}", $this->id);
            }
            
            return ['success' => true, 'message' => '用户删除成功'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => '删除失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 递归删除目录
     */
    private static function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * 启用/禁用用户
     */
    public function setActive($isActive) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE users SET is_active = :active, updated_at = datetime('now') WHERE id = :id"
            );
            $stmt->bindValue(':active', $isActive ? 1 : 0, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $this->id, SQLITE3_INTEGER);
            $stmt->execute();
            
            $this->isActive = $isActive;
            
            $action = $isActive ? '启用' : '禁用';
            Utils::logOperation("user_{$action}", "{$action}用户 {$this->mobile}", $this->id);
            
            return ['success' => true, 'message' => "{$action}成功"];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => '操作失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 获取访问URL
     */
    public static function getAccessUrl($accessToken) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocol}://{$host}/views/index.html?token={$accessToken}";
    }
    
    /**
     * 转换为数组（用于API返回，不包含敏感信息）
     */
    public function toArray($includeSensitive = false) {
        $data = [
            'id' => $this->id,
            'mobile' => $this->mobile,
            'auth_type' => $this->authType,
            'access_token' => $this->accessToken,
            'access_url' => self::getAccessUrl($this->accessToken),
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'last_query_at' => $this->lastQueryAt,
            'remark' => $this->remark
        ];
        
        if ($includeSensitive) {
            $data['appid'] = $this->appid;
            $data['token_online'] = $this->tokenOnline;
            $data['cookie'] = $this->cookie;
        }
        
        return $data;
    }
}
