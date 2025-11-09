<?php

namespace App\Models;

/**
 * 管理员模型 - 纯数据访问层
 */
class Admin extends Database
{
    protected $table = 'admins';
    
    /**
     * 根据用户名查找管理员
     */
    public function findByUsername($username)
    {
        return $this->findOne(['username' => $username]);
    }
    
    /**
     * getByUsername的别名（兼容Controller调用）
     */
    public function getByUsername($username)
    {
        return $this->findByUsername($username);
    }
    
    /**
     * 更新最后登录时间
     */
    public function updateLastLogin($id)
    {
        return $this->update($id, [
            'last_login_at' => time()
        ]);
    }
    
    /**
     * 更新密码
     */
    public function updatePassword($id, $newPassword)
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        return $this->update($id, [
            'password' => $hashedPassword
        ]);
    }
    
    /**
     * 创建管理员
     */
    public function createAdmin($data)
    {
        return $this->create([
            'username' => $data['username'],
            'password' => $data['password'],
            'created_at' => time(),
            'last_login_at' => null
        ]);
    }
}
