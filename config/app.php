<?php
/**
 * 应用配置
 */

return [
    'app_name' => '联通流量查询系统',
    'app_version' => '2.0.0',
    'timezone' => 'Asia/Shanghai',
    'charset' => 'UTF-8',
    
    // 调试模式
    'debug' => true,
    
    // Session配置
    'session' => [
        'name' => 'UNICOM_SESSION',
        'lifetime' => 7200, // 2小时
        'path' => '/',
        'secure' => false,
        'httponly' => true
    ],
    
    // 安装检查文件
    'install_lock' => dirname(__DIR__) . '/storage/install.lock',
    
    // 静态资源版本号（用于缓存控制）
    'asset_version' => '20250105'
];
