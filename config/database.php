<?php
/**
 * 数据库配置
 */

return [
    'driver' => 'sqlite',
    'database' => dirname(__DIR__) . '/database/unicom_flow.db',
    'charset' => 'utf8',
    'prefix' => '',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
