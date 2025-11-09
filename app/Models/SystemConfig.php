<?php
namespace App\Models;

/**
 * 系统配置模型 - 纯数据访问层
 */
class SystemConfig extends Database {
    protected $table = 'system_config';
    protected $primaryKey = 'id';
    
    /**
     * 根据配置键获取值
     */
    public function getValue($key, $default = null) {
        $config = $this->findBy(['config_key' => $key]);
        return $config ? $config['config_value'] : $default;
    }
    
    /**
     * 设置配置值
     */
    public function setValue($key, $value, $description = '') {
        $existing = $this->findBy(['config_key' => $key]);
        
        if ($existing) {
            return $this->update($existing['id'], [
                'config_value' => $value,
                'description' => $description ?: $existing['description']
            ]);
        } else {
            return $this->insert([
                'config_key' => $key,
                'config_value' => $value,
                'description' => $description
            ]);
        }
    }
    
    /**
     * 批量获取配置
     */
    public function getMultiple($keys) {
        $configs = [];
        foreach ($keys as $key) {
            $configs[$key] = $this->getValue($key);
        }
        return $configs;
    }
    
    /**
     * 获取所有配置（返回key=>value数组）
     */
    public function getAllConfigs() {
        $rows = $this->all('config_key ASC');
        $configs = [];
        
        foreach ($rows as $row) {
            $configs[$row['config_key']] = $row['config_value'];
        }
        
        return $configs;
    }
    
    /**
     * 获取所有配置为关联数组
     */
    public function getAllAsKeyValue() {
        return $this->getAllConfigs();
    }
}
