<?php
namespace App\Services;

use App\Utils\Logger;

/**
 * 通知推送服务
 * 支持5个通知渠道: Telegram、企业微信、Server酱、钉钉机器人、PushPlus
 * 参考 10010/sendNotify.js 实现
 */
class NotifyService {
    
    /**
     * 发送通知
     * @param string $title 通知标题
     * @param string $content 通知内容
     * @param array $config 通知配置 [type, params]
     * @param array $context 日志上下文
     * @return array 发送结果
     */
    public static function send($title, $content, $config, $context = []) {
        try {
            if (empty($config['type']) || empty($config['params'])) {
                Logger::error('通知配置不完整', $context);
                return ['success' => false, 'message' => '通知配置不完整'];
            }
            
            $type = $config['type'];
            $params = is_string($config['params']) ? json_decode($config['params'], true) : $config['params'];
            
            if (!$params) {
                Logger::error('通知参数解析失败', $context);
                return ['success' => false, 'message' => '通知参数格式错误'];
            }
            
            Logger::system("发送{$type}通知: {$title}", 'info', array_merge($context, ['type' => $type]));
            
            // 根据类型调用不同的发送方法
            switch ($type) {
                case 'telegram':
                    return self::sendTelegram($title, $content, $params, $context);
                
                case 'wecom':
                    return self::sendWecom($title, $content, $params, $context);
                
                case 'serverchan':
                    return self::sendServerchan($title, $content, $params, $context);
                
                case 'dingtalk':
                    return self::sendDingtalk($title, $content, $params, $context);
                
                case 'pushplus':
                    return self::sendPushplus($title, $content, $params, $context);
                
                default:
                    Logger::error("不支持的通知类型: {$type}", $context);
                    return ['success' => false, 'message' => '不支持的通知类型'];
            }
            
        } catch (\Exception $e) {
            Logger::error("发送通知异常: " . $e->getMessage(), $context);
            return ['success' => false, 'message' => '发送失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 发送 Telegram 通知
     */
    private static function sendTelegram($title, $content, $params, $context = []) {
        try {
            $botToken = $params['bot_token'] ?? '';
            $chatId = $params['chat_id'] ?? '';
            $apiHost = $params['api_host'] ?? 'https://api.telegram.org';
            
            if (empty($botToken) || empty($chatId)) {
                return ['success' => false, 'message' => 'Telegram 配置不完整'];
            }
            
            // 移除 API host 末尾的斜杠
            $apiHost = rtrim($apiHost, '/');
            
            $url = "{$apiHost}/bot{$botToken}/sendMessage";
            
            // 构建消息（使用 Markdown 格式）
            $message = "*{$title}*\n\n{$content}";
            
            $postData = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true
            ];
            
            $result = self::httpPost($url, $postData, ['Content-Type: application/json'], 15);
            
            if ($result['code'] === 200) {
                $response = json_decode($result['body'], true);
                if ($response && $response['ok']) {
                    Logger::system('Telegram 发送通知消息成功🎉', 'info', $context);
                    return ['success' => true, 'message' => 'Telegram 发送成功'];
                } else {
                    $errorMsg = $response['description'] ?? '未知错误';
                    Logger::error("Telegram 发送失败: {$errorMsg}", $context);
                    return ['success' => false, 'message' => "Telegram 发送失败: {$errorMsg}"];
                }
            } else {
                Logger::error("Telegram 发送失败 HTTP {$result['code']}", $context);
                return ['success' => false, 'message' => "HTTP错误: {$result['code']}"];
            }
            
        } catch (\Exception $e) {
            Logger::error("Telegram 发送异常: " . $e->getMessage(), $context);
            return ['success' => false, 'message' => 'Telegram 发送异常'];
        }
    }
    
    /**
     * 发送企业微信机器人通知
     */
    private static function sendWecom($title, $content, $params, $context = []) {
        try {
            $webhook = $params['webhook'] ?? '';
            
            if (empty($webhook)) {
                return ['success' => false, 'message' => '企业微信 Webhook 未配置'];
            }
            
            $postData = [
                'msgtype' => 'text',
                'text' => [
                    'content' => "{$title}\n\n{$content}"
                ]
            ];
            
            $result = self::httpPost($webhook, $postData, ['Content-Type: application/json'], 15);
            
            if ($result['code'] === 200) {
                $response = json_decode($result['body'], true);
                if ($response && $response['errcode'] === 0) {
                    Logger::system('企业微信发送通知消息成功🎉', 'info', $context);
                    return ['success' => true, 'message' => '企业微信发送成功'];
                } else {
                    $errorMsg = $response['errmsg'] ?? '未知错误';
                    Logger::error("企业微信发送失败: {$errorMsg}", $context);
                    return ['success' => false, 'message' => "企业微信发送失败: {$errorMsg}"];
                }
            } else {
                Logger::error("企业微信发送失败 HTTP {$result['code']}", $context);
                return ['success' => false, 'message' => "HTTP错误: {$result['code']}"];
            }
            
        } catch (\Exception $e) {
            Logger::error("企业微信发送异常: " . $e->getMessage(), $context);
            return ['success' => false, 'message' => '企业微信发送异常'];
        }
    }
    
    /**
     * 发送 Server酱 通知
     */
    private static function sendServerchan($title, $content, $params, $context = []) {
        try {
            $key = $params['key'] ?? '';
            
            if (empty($key)) {
                return ['success' => false, 'message' => 'Server酱 SendKey 未配置'];
            }
            
            // 支持旧版和 Turbo 版
            $url = preg_match('/^sctp(\d+)t/i', $key, $matches) && $matches[1]
                ? "https://{$matches[1]}.push.ft07.com/send/{$key}.send"
                : "https://sctapi.ftqq.com/{$key}.send";
            
            // Server酱需要两个 \n 才能换行
            $desp = str_replace("\n", "\n\n", $content);
            
            $postData = http_build_query([
                'text' => $title,
                'desp' => $desp
            ]);
            
            $result = self::httpPost($url, $postData, ['Content-Type: application/x-www-form-urlencoded'], 15);
            
            if ($result['code'] === 200) {
                $response = json_decode($result['body'], true);
                // Server酱和Server酱·Turbo版的返回json格式不太一样
                if ($response && ($response['errno'] === 0 || (isset($response['data']) && $response['data']['errno'] === 0))) {
                    Logger::system('Server酱发送通知消息成功🎉', 'info', $context);
                    return ['success' => true, 'message' => 'Server酱发送成功'];
                } else {
                    $errorMsg = $response['errmsg'] ?? $response['message'] ?? '未知错误';
                    Logger::error("Server酱发送失败: {$errorMsg}", $context);
                    return ['success' => false, 'message' => "Server酱发送失败: {$errorMsg}"];
                }
            } else {
                Logger::error("Server酱发送失败 HTTP {$result['code']}", $context);
                return ['success' => false, 'message' => "HTTP错误: {$result['code']}"];
            }
            
        } catch (\Exception $e) {
            Logger::error("Server酱发送异常: " . $e->getMessage(), $context);
            return ['success' => false, 'message' => 'Server酱发送异常'];
        }
    }
    
    /**
     * 发送钉钉机器人通知
     */
    private static function sendDingtalk($title, $content, $params, $context = []) {
        try {
            $webhook = $params['webhook'] ?? '';
            $secret = $params['secret'] ?? '';
            
            if (empty($webhook)) {
                return ['success' => false, 'message' => '钉钉 Webhook 未配置'];
            }
            
            $url = $webhook;
            
            // 如果配置了加签，计算签名
            if (!empty($secret)) {
                $timestamp = round(microtime(true) * 1000);
                $stringToSign = $timestamp . "\n" . $secret;
                $sign = urlencode(base64_encode(hash_hmac('sha256', $stringToSign, $secret, true)));
                $url .= "&timestamp={$timestamp}&sign={$sign}";
            }
            
            $postData = [
                'msgtype' => 'text',
                'text' => [
                    'content' => "{$title}\n\n{$content}"
                ]
            ];
            
            $result = self::httpPost($url, $postData, ['Content-Type: application/json'], 15);
            
            if ($result['code'] === 200) {
                $response = json_decode($result['body'], true);
                if ($response && $response['errcode'] === 0) {
                    Logger::system('钉钉发送通知消息成功🎉', 'info', $context);
                    return ['success' => true, 'message' => '钉钉发送成功'];
                } else {
                    $errorMsg = $response['errmsg'] ?? '未知错误';
                    Logger::error("钉钉发送失败: {$errorMsg}", $context);
                    return ['success' => false, 'message' => "钉钉发送失败: {$errorMsg}"];
                }
            } else {
                Logger::error("钉钉发送失败 HTTP {$result['code']}", $context);
                return ['success' => false, 'message' => "HTTP错误: {$result['code']}"];
            }
            
        } catch (\Exception $e) {
            Logger::error("钉钉发送异常: " . $e->getMessage(), $context);
            return ['success' => false, 'message' => '钉钉发送异常'];
        }
    }
    
    /**
     * 发送 PushPlus 通知
     */
    private static function sendPushplus($title, $content, $params, $context = []) {
        try {
            $token = $params['token'] ?? '';
            $template = $params['template'] ?? 'html';
            $channel = $params['channel'] ?? 'wechat';
            
            if (empty($token)) {
                return ['success' => false, 'message' => 'PushPlus Token 未配置'];
            }
            
            // 默认HTML格式，替换换行符为 <br>
            $formattedContent = ($template === 'html') ? str_replace("\n", "<br>", $content) : $content;
            
            $postData = [
                'token' => $token,
                'title' => $title,
                'content' => $formattedContent,
                'template' => $template,
                'channel' => $channel
            ];
            
            $result = self::httpPost('https://www.pushplus.plus/send', $postData, ['Content-Type: application/json'], 15);
            
            if ($result['code'] === 200) {
                $response = json_decode($result['body'], true);
                if ($response && $response['code'] === 200) {
                    Logger::system('PushPlus 发送通知消息成功🎉', 'info', $context);
                    return ['success' => true, 'message' => 'PushPlus 发送成功'];
                } else {
                    $errorMsg = $response['msg'] ?? '未知错误';
                    Logger::error("PushPlus 发送失败: {$errorMsg}", $context);
                    return ['success' => false, 'message' => "PushPlus 发送失败: {$errorMsg}"];
                }
            } else {
                Logger::error("PushPlus 发送失败 HTTP {$result['code']}", $context);
                return ['success' => false, 'message' => "HTTP错误: {$result['code']}"];
            }
            
        } catch (\Exception $e) {
            Logger::error("PushPlus 发送异常: " . $e->getMessage(), $context);
            return ['success' => false, 'message' => 'PushPlus 发送异常'];
        }
    }
    
    /**
     * HTTP POST 请求（使用 cURL）
     * @param string $url 请求URL
     * @param mixed $data 请求数据（数组会转为JSON）
     * @param array $headers 请求头
     * @param int $timeout 超时时间（秒）
     * @return array ['code' => HTTP状态码, 'body' => 响应体]
     */
    private static function httpPost($url, $data, $headers = [], $timeout = 15) {
        $ch = curl_init();
        
        // 处理数据格式
        $isJson = in_array('Content-Type: application/json', $headers);
        $postData = $isJson ? json_encode($data) : $data;
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headers
        ]);
        
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            Logger::error("HTTP请求失败: {$error}");
            return ['code' => 0, 'body' => '', 'error' => $error];
        }
        
        return ['code' => $httpCode, 'body' => $body];
    }
    
    /**
     * 测试通知（从用户配置读取）
     * @param int $userId 用户ID
     * @return array 发送结果
     */
    public static function test($userId) {
        try {
            // 从数据库读取用户通知配置
            $db = new \App\Models\Database();
            $user = $db->query("SELECT notify_type, notify_params, phone FROM users WHERE id = ?", [$userId]);
            
            if (empty($user)) {
                return ['success' => false, 'message' => '用户不存在'];
            }
            
            if (empty($user[0]['notify_type']) || empty($user[0]['notify_params'])) {
                return ['success' => false, 'message' => '通知未配置'];
            }
            
            $config = [
                'type' => $user[0]['notify_type'],
                'params' => $user[0]['notify_params']
            ];
            
            $title = '🔔 通知测试';
            $content = "这是一条测试通知\n用户: {$user[0]['phone']}\n时间: " . date('Y-m-d H:i:s');
            
            return self::send($title, $content, $config, ['user_id' => $userId]);
            
        } catch (\Exception $e) {
            Logger::error("测试通知异常: " . $e->getMessage(), ['user_id' => $userId]);
            return ['success' => false, 'message' => '测试失败: ' . $e->getMessage()];
        }
    }
}
