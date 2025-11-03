<?php
/**
 * 通知发送API
 * 支持多种通知方式：Bark、Telegram、钉钉、企业微信、PushPlus、Server酱
 */

require_once __DIR__ . '/../classes/ApiHelper.php';
require_once __DIR__ . '/../classes/Utils.php';
require_once __DIR__ . '/../classes/CronManager.php';

ApiHelper::init();

$method = $_SERVER['REQUEST_METHOD'];
$token = $_GET['token'] ?? '';
$dataDir = __DIR__ . '/../data';

// GET请求：获取通知配置
if ($method === 'GET') {
    ApiHelper::requireParams(['token' => $token], ['token']);
    
    $userDataDir = "$dataDir/$token";
    if (!is_dir($userDataDir)) mkdir($userDataDir, 0775, true);
    
    $notifyFile = "$userDataDir/notify.json";
    $config = file_exists($notifyFile) ? json_decode(file_get_contents($notifyFile), true) : null;
    
    ApiHelper::success($config, $config ? '获取成功' : '暂无配置');
}

// POST请求
$input = ApiHelper::getInput();

// 保存通知配置
if ($token) {
    $userDataDir = "$dataDir/$token";
    if (!is_dir($userDataDir)) mkdir($userDataDir, 0775, true);
    
    file_put_contents("$userDataDir/notify.json", json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $hasNotifyType = !empty($input['type']);
    $hasThreshold = isset($input['threshold']) && $input['threshold'] > 0;
    $hasInterval = isset($input['interval']) && $input['interval'] > 0;
    
    ($hasNotifyType && $hasThreshold && $hasInterval) ? 
        CronManager::addCronJob($token, $input['interval']) : 
        CronManager::removeCronJob($token);
    
    ApiHelper::success(null, '配置已保存');
}

// 发送通知
ApiHelper::requireParams($input, ['type']);

$type = $input['type'];
$params = $input['params'] ?? [];
$title = $input['title'] ?? '流量通知';
$subtitle = $input['subtitle'] ?? '';
$content = $input['content'] ?? '';

$handlers = [
    'bark' => 'sendBark',
    'telegram' => 'sendTelegram',
    'dingtalk' => 'sendDingTalk',
    'qywx' => 'sendQYWX',
    'pushplus' => 'sendPushPlus',
    'serverchan' => 'sendServerChan'
];

if (!isset($handlers[$type])) {
    ApiHelper::error('不支持的通知类型: ' . $type);
}

list($result, $message) = $handlers[$type]($params, $title, $subtitle, $content);
$result ? ApiHelper::success(['sent' => true], $message) : ApiHelper::error($message);

// ==================== 通知发送函数 ====================

/**
 * 优化的HTTP请求函数
 * 增强错误处理和SSL兼容性
 */
function httpRequest($url, $method = 'GET', $data = null, $proxy = null) {
    $ch = curl_init();
    
    // 基础配置
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,              // 增加超时时间
        CURLOPT_CONNECTTIMEOUT => 10,       // 增加连接超时
        CURLOPT_FOLLOWLOCATION => true,     // 跟随重定向
        CURLOPT_MAXREDIRS => 3,             // 最多3次重定向
        CURLOPT_ENCODING => '',             // 支持gzip等压缩
        CURLOPT_USERAGENT => 'UnicomFlowMonitor/1.0',
    ]);
    
    // SSL配置 - 优先使用系统CA，失败时禁用验证
    $sslVerify = file_exists('/etc/ssl/certs/ca-certificates.crt') || 
                 file_exists('/etc/pki/tls/certs/ca-bundle.crt');
    
    if ($sslVerify) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        // 尝试设置CA证书路径
        if (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
            curl_setopt($ch, CURLOPT_CAINFO, '/etc/ssl/certs/ca-certificates.crt');
        } elseif (file_exists('/etc/pki/tls/certs/ca-bundle.crt')) {
            curl_setopt($ch, CURLOPT_CAINFO, '/etc/pki/tls/certs/ca-bundle.crt');
        }
    } else {
        // 系统无CA证书，禁用验证（开发环境）
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    
    // POST请求配置
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        $postData = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ]);
    }
    
    // 代理配置
    if ($proxy && !empty($proxy['host']) && !empty($proxy['port'])) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy['host']);
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        if (!empty($proxy['auth'])) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['auth']);
        }
    }
    
    // 执行请求
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    
    // 错误处理
    if ($errno !== 0) {
        error_log("HTTP Request Error: [{$errno}] {$error} - URL: {$url}");
        return null;
    }
    
    // 只有2xx状态码才认为成功
    if ($httpCode >= 200 && $httpCode < 300) {
        $decoded = json_decode($response, true);
        return $decoded !== null ? $decoded : ['raw_response' => $response];
    }
    
    error_log("HTTP Request Failed: HTTP {$httpCode} - URL: {$url}");
    return null;
}

function sendBark($params, $title, $subtitle, $content) {
    $barkPush = $params['barkPush'] ?? '';
    if (!$barkPush) return [false, 'Bark Push地址不能为空'];
    
    // 清理URL
    $barkPush = rtrim($barkPush, '/');
    
    // 构建URL - Bark使用GET参数或路径参数
    $url = $barkPush . '/' . rawurlencode($title);
    
    // 构建body内容
    $body = '';
    if ($subtitle) {
        $body .= $subtitle;
    }
    if ($content) {
        $body .= ($subtitle ? "\n" : '') . $content;
    }
    
    // 构建查询参数
    $query = array_filter([
        'body' => $body ?: null,
        'sound' => $params['barkSound'] ?? null,
        'group' => $params['barkGroup'] ?? null,
        'icon' => $params['barkIcon'] ?? null,
        'level' => $params['barkLevel'] ?? null,
        'url' => $params['barkUrl'] ?? null,
        'isArchive' => $params['barkArchive'] ?? null
    ]);
    
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    
    $response = httpRequest($url);
    
    if ($response && isset($response['code']) && $response['code'] == 200) {
        return [true, 'Bark通知发送成功'];
    }
    
    $errorMsg = 'Bark通知发送失败';
    if ($response && isset($response['message'])) {
        $errorMsg .= '：' . $response['message'];
    }
    
    return [false, $errorMsg];
}

function sendTelegram($params, $title, $subtitle, $content) {
    $botToken = $params['tgBotToken'] ?? $params['botToken'] ?? '';
    $userId = $params['tgUserId'] ?? $params['chatId'] ?? '';
    
    if (!$botToken || !$userId) return [false, 'Telegram Bot Token和User ID不能为空'];
    
    // API主机配置，支持自定义和默认
    $apiHost = $params['tgApiHost'] ?? $params['apiHost'] ?? '';
    if (empty($apiHost)) {
        $apiHost = 'api.telegram.org';
    }
    
    $url = "https://{$apiHost}/bot{$botToken}/sendMessage";
    
    // 代理配置
    $proxy = null;
    $proxyHost = $params['tgProxyHost'] ?? $params['proxyHost'] ?? '';
    $proxyPort = $params['tgProxyPort'] ?? $params['proxyPort'] ?? '';
    if ($proxyHost && $proxyPort) {
        $proxy = [
            'host' => $proxyHost, 
            'port' => $proxyPort, 
            'auth' => $params['tgProxyAuth'] ?? ''
        ];
    }
    
    // 构建消息内容
    $text = "📊 {$title}";
    if ($subtitle) {
        $text .= "\n\n{$subtitle}";
    }
    if ($content) {
        $text .= "\n{$content}";
    }
    
    // 发送请求
    $response = httpRequest($url, 'POST', [
        'chat_id' => $userId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ], $proxy);
    
    if ($response && ($response['ok'] ?? false)) {
        return [true, 'Telegram通知发送成功'];
    }
    
    // 详细的错误信息
    $errorMsg = 'Telegram通知发送失败';
    if ($response && isset($response['description'])) {
        $errorMsg .= '：' . $response['description'];
    } elseif (!$response) {
        $errorMsg .= '：无法连接到Telegram服务器';
        if ($apiHost !== 'api.telegram.org') {
            $errorMsg .= "（API: {$apiHost}）";
        }
    }
    
    return [false, $errorMsg];
}

function sendDingTalk($params, $title, $content) {
    $token = $params['ddBotToken'] ?? '';
    if (!$token) return [false, '钉钉机器人Token不能为空'];
    
    $url = "https://oapi.dingtalk.com/robot/send?access_token={$token}";
    
    // 加签验证
    $secret = $params['ddBotSecret'] ?? '';
    if ($secret) {
        $timestamp = round(microtime(true) * 1000);
        $stringToSign = $timestamp . "\n" . $secret;
        $sign = urlencode(base64_encode(hash_hmac('sha256', $stringToSign, $secret, true)));
        $url .= "&timestamp={$timestamp}&sign={$sign}";
    }
    
    $response = httpRequest($url, 'POST', [
        'msgtype' => 'markdown',
        'markdown' => [
            'title' => $title,
            'text' => "### {$title}\n\n{$content}"
        ]
    ]);
    
    if ($response && isset($response['errcode']) && $response['errcode'] == 0) {
        return [true, '钉钉通知发送成功'];
    }
    
    $errorMsg = '钉钉通知发送失败';
    if ($response && isset($response['errmsg'])) {
        $errorMsg .= '：' . $response['errmsg'];
    }
    
    return [false, $errorMsg];
}

function sendQYWX($params, $title, $content) {
    $mode = $params['qywxMode'] ?? 'webhook';
    
    if ($mode === 'webhook') {
        $key = $params['qywxKey'] ?? '';
        if (!$key) return [false, '企业微信Webhook Key不能为空'];
        
        $response = httpRequest("https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key={$key}", 'POST', [
            'msgtype' => 'markdown',
            'markdown' => ['content' => "### {$title}\n\n{$content}"]
        ]);
        
        if ($response && isset($response['errcode']) && $response['errcode'] == 0) {
            return [true, '企业微信通知发送成功'];
        }
        
        $errorMsg = '企业微信通知发送失败';
        if ($response && isset($response['errmsg'])) {
            $errorMsg .= '：' . $response['errmsg'];
        }
        
        return [false, $errorMsg];
    }
    
    // 应用模式
    $am = $params['qywxAm'] ?? '';
    if (!$am) return [false, '企业微信应用参数不能为空'];
    
    $parts = explode(',', $am);
    if (count($parts) < 4) return [false, '企业微信应用参数格式错误（需要4个参数）'];
    
    list($corpid, $corpsecret, $touser, $agentid) = $parts;
    
    // 获取access_token
    $tokenRes = httpRequest("https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$corpid}&corpsecret={$corpsecret}");
    if (!$tokenRes || !isset($tokenRes['access_token'])) {
        $errorMsg = '获取企业微信access_token失败';
        if ($tokenRes && isset($tokenRes['errmsg'])) {
            $errorMsg .= '：' . $tokenRes['errmsg'];
        }
        return [false, $errorMsg];
    }
    
    // 发送消息
    $response = httpRequest("https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token={$tokenRes['access_token']}", 'POST', [
        'touser' => $touser,
        'agentid' => (int)$agentid,
        'msgtype' => 'text',
        'text' => ['content' => "{$title}\n\n{$content}"]
    ]);
    
    if ($response && isset($response['errcode']) && $response['errcode'] == 0) {
        return [true, '企业微信通知发送成功'];
    }
    
    $errorMsg = '企业微信通知发送失败';
    if ($response && isset($response['errmsg'])) {
        $errorMsg .= '：' . $response['errmsg'];
    }
    
    return [false, $errorMsg];
}

function sendPushPlus($params, $title, $content) {
    $token = $params['pushplusToken'] ?? '';
    if (!$token) return [false, 'PushPlus Token不能为空'];
    
    $postData = array_filter([
        'token' => $token,
        'title' => $title,
        'content' => $content,
        'template' => $params['pushplusTemplate'] ?? 'html',
        'topic' => $params['pushplusUser'] ?? null,
        'channel' => $params['pushplusChannel'] ?? null,
        'webhook' => $params['pushplusWebhook'] ?? null,
        'callbackUrl' => $params['pushplusCallbackUrl'] ?? null,
        'to' => $params['pushplusTo'] ?? null
    ]);
    
    $response = httpRequest('https://www.pushplus.plus/send', 'POST', $postData);
    
    if ($response && isset($response['code']) && $response['code'] == 200) {
        return [true, 'PushPlus通知发送成功'];
    }
    
    $errorMsg = 'PushPlus通知发送失败';
    if ($response && isset($response['msg'])) {
        $errorMsg .= '：' . $response['msg'];
    }
    
    return [false, $errorMsg];
}

function sendServerChan($params, $title, $content) {
    $sendKey = $params['pushKey'] ?? '';
    if (!$sendKey) return [false, 'Server酱 SendKey不能为空'];
    
    // 判断SendKey类型
    if (strpos($sendKey, 'SCT') === 0) {
        // Turbo版
        $url = "https://sctapi.ftqq.com/{$sendKey}.send";
    } else if (strpos($sendKey, 'sctp') === 0) {
        // 企业版
        preg_match('/sctp(\d+)t/', $sendKey, $matches);
        if (!$matches) return [false, 'Server酱企业版 SendKey格式错误'];
        $num = $matches[1];
        $url = "https://{$num}.push.ft07.com/send/{$sendKey}.send";
    } else {
        return [false, 'Server酱 SendKey格式错误（应以SCT或sctp开头）'];
    }
    
    $response = httpRequest($url, 'POST', [
        'title' => $title, 
        'desp' => $content
    ]);
    
    if ($response && isset($response['code']) && $response['code'] == 0) {
        return [true, 'Server酱通知发送成功'];
    }
    
    $errorMsg = 'Server酱通知发送失败';
    if ($response && isset($response['message'])) {
        $errorMsg .= '：' . $response['message'];
    }
    
    return [false, $errorMsg];
}
