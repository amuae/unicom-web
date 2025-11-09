<?php
/**
 * 通知配置
 * 支持5个通知渠道: Telegram、企业微信、Server酱、钉钉机器人、PushPlus
 */

return [
    // 是否启用通知
    'enabled' => true,
    
    // 默认通知渠道
    'default_channel' => 'telegram',
    
    // 通知渠道配置
    'channels' => [
        // Telegram Bot推送
        'telegram' => [
            'enabled' => false,
            'bot_token' => '', // Bot Token，从 @BotFather 获取
            'chat_id' => ''    // 用户或群组的 Chat ID
        ],
        
        // 企业微信机器人
        'wecom' => [
            'enabled' => false,
            'webhook' => ''    // 企业微信机器人 Webhook 地址
        ],
        
        // Server酱（支持Turbo版）
        'serverchan' => [
            'enabled' => false,
            'key' => ''        // SendKey，从 https://sct.ftqq.com 获取
        ],
        
        // 钉钉机器人
        'dingtalk' => [
            'enabled' => false,
            'webhook' => '',   // 钉钉机器人 Webhook 地址
            'secret' => ''     // 加签密钥（可选）
        ],
        
        // PushPlus推送加
        'pushplus' => [
            'enabled' => false,
            'token' => ''      // PushPlus Token，从 http://www.pushplus.plus 获取
        ]
    ]
];
