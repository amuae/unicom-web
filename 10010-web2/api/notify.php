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

function httpRequest($url, $method = 'GET', $data = null, $proxy = null) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    if ($proxy && !empty($proxy['host']) && !empty($proxy['port'])) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy['host']);
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']);
        if (!empty($proxy['auth'])) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['auth']);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 200 ? json_decode($response, true) : null;
}

function sendBark($params, $title, $subtitle, $content) {
    $barkPush = $params['barkPush'] ?? '';
    if (!$barkPush) return [false, 'Bark Push地址不能为空'];
    
    $url = rtrim($barkPush, '/') . '/' . urlencode($title);
    $query = array_filter([
        'body' => $subtitle ? "$subtitle\n$content" : null,
        'sound' => $params['barkSound'] ?? null,
        'group' => $params['barkGroup'] ?? null,
        'icon' => $params['barkIcon'] ?? null,
        'level' => $params['barkLevel'] ?? null,
        'url' => $params['barkUrl'] ?? null,
        'isArchive' => $params['barkArchive'] ?? null
    ]);
    
    if (!empty($query)) $url .= '?' . http_build_query($query);
    
    $response = httpRequest($url);
    return $response && ($response['code'] ?? 0) == 200 ? 
        [true, 'Bark通知发送成功'] : 
        [false, 'Bark通知发送失败'];
}

function sendTelegram($params, $title, $subtitle, $content) {
    $botToken = $params['tgBotToken'] ?? $params['botToken'] ?? '';
    $userId = $params['tgUserId'] ?? $params['chatId'] ?? '';
    
    if (!$botToken || !$userId) return [false, 'Telegram Bot Token和User ID不能为空'];
    
    $apiHost = $params['tgApiHost'] ?? $params['apiHost'] ?? 'api.telegram.org';
    $url = "https://{$apiHost}/bot{$botToken}/sendMessage";
    
    $proxy = null;
    $proxyHost = $params['tgProxyHost'] ?? $params['proxyHost'] ?? '';
    $proxyPort = $params['tgProxyPort'] ?? $params['proxyPort'] ?? '';
    if ($proxyHost && $proxyPort) {
        $proxy = ['host' => $proxyHost, 'port' => $proxyPort, 'auth' => $params['tgProxyAuth'] ?? ''];
    }
    
    $response = httpRequest($url, 'POST', [
        'chat_id' => $userId,
        'text' => "📊 {$title}\n\n{$subtitle}\n{$content}",
        'parse_mode' => 'HTML'
    ], $proxy);
    
    if ($response && ($response['ok'] ?? false)) {
        return [true, 'Telegram通知发送成功'];
    }
    
    return [false, 'Telegram通知发送失败：' . ($response['description'] ?? '无法连接到服务器')];
}

function sendDingTalk($params, $title, $content) {
    $token = $params['ddBotToken'] ?? '';
    if (!$token) return [false, '钉钉机器人Token不能为空'];
    
    $url = "https://oapi.dingtalk.com/robot/send?access_token={$token}";
    
    $secret = $params['ddBotSecret'] ?? '';
    if ($secret) {
        $timestamp = round(microtime(true) * 1000);
        $sign = urlencode(base64_encode(hash_hmac('sha256', $timestamp . "\n" . $secret, $secret, true)));
        $url .= "&timestamp={$timestamp}&sign={$sign}";
    }
    
    $response = httpRequest($url, 'POST', [
        'msgtype' => 'markdown',
        'markdown' => [
            'title' => $title,
            'text' => "### {$title}\n\n{$content}"
        ]
    ]);
    
    return $response && ($response['errcode'] ?? -1) == 0 ? 
        [true, '钉钉通知发送成功'] : 
        [false, '钉钉通知发送失败'];
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
        
        return $response && ($response['errcode'] ?? -1) == 0 ? 
            [true, '企业微信通知发送成功'] : 
            [false, '企业微信通知发送失败'];
    }
    
    $am = $params['qywxAm'] ?? '';
    if (!$am) return [false, '企业微信应用参数不能为空'];
    
    $parts = explode(',', $am);
    if (count($parts) < 4) return [false, '企业微信应用参数格式错误'];
    
    list($corpid, $corpsecret, $touser, $agentid) = $parts;
    
    $tokenRes = httpRequest("https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$corpid}&corpsecret={$corpsecret}");
    if (!$tokenRes || !isset($tokenRes['access_token'])) {
        return [false, '获取企业微信access_token失败'];
    }
    
    $response = httpRequest("https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token={$tokenRes['access_token']}", 'POST', [
        'touser' => $touser,
        'agentid' => (int)$agentid,
        'msgtype' => 'text',
        'text' => ['content' => "{$title}\n\n{$content}"]
    ]);
    
    return $response && ($response['errcode'] ?? -1) == 0 ? 
        [true, '企业微信通知发送成功'] : 
        [false, '企业微信通知发送失败'];
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
    return $response && ($response['code'] ?? -1) == 200 ? 
        [true, 'PushPlus通知发送成功'] : 
        [false, 'PushPlus通知发送失败'];
}

function sendServerChan($params, $title, $content) {
    $sendKey = $params['pushKey'] ?? '';
    if (!$sendKey) return [false, 'Server酱 SendKey不能为空'];
    
    if (strpos($sendKey, 'SCT') === 0) {
        $url = "https://sctapi.ftqq.com/{$sendKey}.send";
    } else if (strpos($sendKey, 'sctp') === 0) {
        preg_match('/sctp(\d+)t/', $sendKey, $matches);
        if (!$matches) return [false, 'Server酱 SendKey格式错误'];
        $num = $matches[1];
        $url = "https://{$num}.push.ft07.com/send/{$sendKey}.send";
    } else {
        return [false, 'Server酱 SendKey格式错误'];
    }
    
    $response = httpRequest($url, 'POST', ['title' => $title, 'desp' => $content]);
    return $response && ($response['code'] ?? -1) == 0 ? 
        [true, 'Server酱通知发送成功'] : 
        [false, 'Server酱通知发送失败'];
}
