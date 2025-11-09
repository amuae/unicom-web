<?php
/**
 * 日志配置
 */

return [
    // 日志根目录
    'log_path' => dirname(__DIR__) . '/storage/logs',
    
    // 日志级别: debug, info, warning, error
    'log_level' => 'info',
    
    // 是否同时写入数据库
    'log_to_database' => true,
    
    // 日志文件保留天数
    'retention_days' => 30,
    
    // 日志通道配置
    'channels' => [
        'system' => [
            'enabled' => true,
            'file' => 'system.log'
        ],
        'query' => [
            'enabled' => true,
            'file' => 'query.log'
        ],
        'cron' => [
            'enabled' => true,
            'file' => 'cron.log'
        ],
        'error' => [
            'enabled' => true,
            'file' => 'error.log'
        ]
    ],
    
    // 时区
    'timezone' => 'Asia/Shanghai'
];
