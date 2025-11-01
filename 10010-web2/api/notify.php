<?php
/**
 * 通知发送API
 * 支持多种通知方式：Bark、Telegram、钉钉、企业微信、PushPlus、Server酱
 */

require_once __DIR__ . '/../classes/Utils.php';

header('Content-Type: application/json');

// ==================== 通知配置管理（来自 notify_config.php）====================

// GET请求：获取通知配置
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['token'] ?? '';
    
    if (!$token) {
        Utils::error('缺少token参数');
    }
    
    // 数据目录
    $userDataDir = __DIR__ . '/../data/' . $token;
    if (!is_dir($userDataDir)) {
        mkdir($userDataDir, 0775, true);
    }
    
    $notifyFile = $userDataDir . '/notify.json';
    
    if (file_exists($notifyFile)) {
        $config = json_decode(file_get_contents($notifyFile), true);
        Utils::success($config, '获取成功');
    } else {
        Utils::success(null, '暂无配置');
    }
}

// ==================== 通知发送（原有功能）====================

// POST请求分两种情况：
// 1. 带token参数：保存通知配置
// 2. 不带token参数：发送通知
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取请求数据
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        Utils::error('无效的请求数据');
    }
    
    // 如果URL中有token参数，是保存配置
    $token = $_GET['token'] ?? '';
    if ($token) {
        // 保存通知配置
        $userDataDir = __DIR__ . '/../data/' . $token;
        if (!is_dir($userDataDir)) {
            mkdir($userDataDir, 0775, true);
        }
        
        $notifyFile = $userDataDir . '/notify.json';
        
        // 保存配置
        file_put_contents($notifyFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // 检查定时任务条件并管理cron
        require_once __DIR__ . '/../classes/CronManager.php';
        
        $hasNotifyType = !empty($data['type']);
        $hasThreshold = isset($data['threshold']) && $data['threshold'] > 0;
        $hasInterval = isset($data['interval']) && $data['interval'] > 0;
        
        if ($hasNotifyType && $hasThreshold && $hasInterval) {
            // 三个条件都满足，添加/更新定时任务
            CronManager::addCronJob($token, $data['interval']);
        } else {
            // 条件不满足，删除定时任务
            CronManager::removeCronJob($token);
        }
        
        Utils::success(null, '配置已保存');
        exit;
    }
    
    // 否则是发送通知
    $type = $data['type'] ?? '';
    $params = $data['params'] ?? [];
    $title = $data['title'] ?? '流量通知';
    $subtitle = $data['subtitle'] ?? '';
    $content = $data['content'] ?? '';
    
    if (!$type) {
        Utils::error('未指定通知类型');
    }
    
    // 根据类型调用对应的发送函数
    try {
        $result = false;
        $message = '';
        
        switch ($type) {
            case 'bark':
                list($result, $message) = sendBark($params, $title, $subtitle, $content);
                break;
            case 'telegram':
                list($result, $message) = sendTelegram($params, $title, $subtitle, $content);
                break;
            case 'dingtalk':
                list($result, $message) = sendDingTalk($params, $title, $content);
                break;
            case 'qywx':
                list($result, $message) = sendQYWX($params, $title, $content);
                break;
            case 'pushplus':
                list($result, $message) = sendPushPlus($params, $title, $content);
                break;
            case 'serverchan':
                list($result, $message) = sendServerChan($params, $title, $content);
                break;
            default:
                Utils::error('不支持的通知类型: ' . $type);
        }
        
        if ($result) {
            Utils::success(['sent' => true], $message);
        } else {
            Utils::error($message);
        }
    } catch (Exception $e) {
        Utils::error('发送通知失败: ' . $e->getMessage());
    }
}

// 不支持的请求方法
Utils::error('只支持GET和POST请求');

// ==================== 通知发送函数 ====================

/**
 * 发送 Bark 通知
 */
function sendBark($params, $title, $subtitle, $content) {
    $barkPush = $params['barkPush'] ?? '';
    if (!$barkPush) {
        return [false, 'Bark Push地址不能为空'];
    }
    
    // 构建URL
    $url = rtrim($barkPush, '/');
    $url .= '/' . urlencode($title);
    
    // 添加可选参数
    $query = [];
    if ($subtitle) $query['body'] = $subtitle . "\n" . $content;
    if (!empty($params['barkSound'])) $query['sound'] = $params['barkSound'];
    if (!empty($params['barkGroup'])) $query['group'] = $params['barkGroup'];
    if (!empty($params['barkIcon'])) $query['icon'] = $params['barkIcon'];
    if (!empty($params['barkLevel'])) $query['level'] = $params['barkLevel'];
    if (!empty($params['barkUrl'])) $query['url'] = $params['barkUrl'];
    if (!empty($params['barkArchive'])) $query['isArchive'] = $params['barkArchive'];
    
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    
    $response = httpGet($url);
    if ($response && isset($response['code']) && $response['code'] == 200) {
        return [true, 'Bark通知发送成功'];
    }
    
    return [false, 'Bark通知发送失败'];
}

/**
 * 发送 Telegram 通知
 */
function sendTelegram($params, $title, $subtitle, $content) {
    // 兼容两种参数格式：tgBotToken/botToken, tgUserId/chatId
    $botToken = $params['tgBotToken'] ?? $params['botToken'] ?? '';
    $userId = $params['tgUserId'] ?? $params['chatId'] ?? '';
    
    if (!$botToken || !$userId) {
        return [false, 'Telegram Bot Token和User ID不能为空'];
    }
    
    $apiHost = $params['tgApiHost'] ?? $params['apiHost'] ?? 'api.telegram.org';
    // 如果apiHost为空，使用默认值
    if (empty($apiHost)) {
        $apiHost = 'api.telegram.org';
    }
    $url = "https://{$apiHost}/bot{$botToken}/sendMessage";
    
    $text = "📊 {$title}\n\n{$subtitle}\n{$content}";
    
    $postData = [
        'chat_id' => $userId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    // 代理设置（兼容两种格式）
    $proxyHost = $params['tgProxyHost'] ?? $params['proxyHost'] ?? '';
    $proxyPort = $params['tgProxyPort'] ?? $params['proxyPort'] ?? '';
    $proxyAuth = $params['tgProxyAuth'] ?? '';
    
    $proxy = null;
    if ($proxyHost && $proxyPort) {
        $proxy = [
            'host' => $proxyHost,
            'port' => $proxyPort,
            'auth' => $proxyAuth
        ];
    }
    
    $response = httpPost($url, $postData, $proxy);
    
    if ($response && isset($response['ok']) && $response['ok']) {
        return [true, 'Telegram通知发送成功'];
    }
    
    // 返回更详细的错误信息
    if ($response && isset($response['description'])) {
        return [false, 'Telegram通知发送失败：' . $response['description']];
    }
    
    return [false, 'Telegram通知发送失败：无法连接到Telegram服务器'];
}

/**
 * 发送钉钉通知
 */
function sendDingTalk($params, $title, $content) {
    $token = $params['ddBotToken'] ?? '';
    if (!$token) {
        return [false, '钉钉机器人Token不能为空'];
    }
    
    $url = "https://oapi.dingtalk.com/robot/send?access_token={$token}";
    
    // 签名
    $secret = $params['ddBotSecret'] ?? '';
    if ($secret) {
        $timestamp = round(microtime(true) * 1000);
        $sign = hash_hmac('sha256', $timestamp . "\n" . $secret, $secret, true);
        $sign = base64_encode($sign);
        $sign = urlencode($sign);
        $url .= "&timestamp={$timestamp}&sign={$sign}";
    }
    
    $postData = [
        'msgtype' => 'markdown',
        'markdown' => [
            'title' => $title,
            'text' => "### {$title}\n\n{$content}"
        ]
    ];
    
    $response = httpPost($url, $postData);
    if ($response && isset($response['errcode']) && $response['errcode'] == 0) {
        return [true, '钉钉通知发送成功'];
    }
    
    return [false, '钉钉通知发送失败'];
}

/**
 * 发送企业微信通知
 */
function sendQYWX($params, $title, $content) {
    $mode = $params['qywxMode'] ?? 'webhook';
    
    if ($mode === 'webhook') {
        // Webhook 模式
        $key = $params['qywxKey'] ?? '';
        if (!$key) {
            return [false, '企业微信Webhook Key不能为空'];
        }
        
        $url = "https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key={$key}";
        
        $postData = [
            'msgtype' => 'markdown',
            'markdown' => [
                'content' => "### {$title}\n\n{$content}"
            ]
        ];
        
        $response = httpPost($url, $postData);
        if ($response && isset($response['errcode']) && $response['errcode'] == 0) {
            return [true, '企业微信通知发送成功'];
        }
    } else {
        // 应用模式
        $am = $params['qywxAm'] ?? '';
        if (!$am) {
            return [false, '企业微信应用参数不能为空'];
        }
        
        $parts = explode(',', $am);
        if (count($parts) < 4) {
            return [false, '企业微信应用参数格式错误'];
        }
        
        list($corpid, $corpsecret, $touser, $agentid) = $parts;
        $msgtype = $parts[4] ?? '0';
        
        // 获取access_token
        $tokenUrl = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$corpid}&corpsecret={$corpsecret}";
        $tokenRes = httpGet($tokenUrl);
        
        if (!$tokenRes || !isset($tokenRes['access_token'])) {
            return [false, '获取企业微信access_token失败'];
        }
        
        $accessToken = $tokenRes['access_token'];
        $sendUrl = "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token={$accessToken}";
        
        // 构建消息
        $postData = [
            'touser' => $touser,
            'agentid' => (int)$agentid,
            'msgtype' => 'text',
            'text' => [
                'content' => "{$title}\n\n{$content}"
            ]
        ];
        
        $response = httpPost($sendUrl, $postData);
        if ($response && isset($response['errcode']) && $response['errcode'] == 0) {
            return [true, '企业微信通知发送成功'];
        }
    }
    
    return [false, '企业微信通知发送失败'];
}

/**
 * 发送 PushPlus 通知
 */
function sendPushPlus($params, $title, $content) {
    $token = $params['pushplusToken'] ?? '';
    if (!$token) {
        return [false, 'PushPlus Token不能为空'];
    }
    
    $url = 'https://www.pushplus.plus/send';
    
    $postData = [
        'token' => $token,
        'title' => $title,
        'content' => $content,
        'template' => $params['pushplusTemplate'] ?? 'html'
    ];
    
    // 可选参数
    if (!empty($params['pushplusUser'])) $postData['topic'] = $params['pushplusUser'];
    if (!empty($params['pushplusChannel'])) $postData['channel'] = $params['pushplusChannel'];
    if (!empty($params['pushplusWebhook'])) $postData['webhook'] = $params['pushplusWebhook'];
    if (!empty($params['pushplusCallbackUrl'])) $postData['callbackUrl'] = $params['pushplusCallbackUrl'];
    if (!empty($params['pushplusTo'])) $postData['to'] = $params['pushplusTo'];
    
    $response = httpPost($url, $postData);
    if ($response && isset($response['code']) && $response['code'] == 200) {
        return [true, 'PushPlus通知发送成功'];
    }
    
    return [false, 'PushPlus通知发送失败'];
}

/**
 * 发送 Server酱 通知
 */
function sendServerChan($params, $title, $content) {
    $sendKey = $params['pushKey'] ?? '';
    if (!$sendKey) {
        return [false, 'Server酱 SendKey不能为空'];
    }
    
    // 判断版本
    if (strpos($sendKey, 'SCT') === 0) {
        // Turbo版
        $url = "https://sctapi.ftqq.com/{$sendKey}.send";
    } else if (strpos($sendKey, 'sctp') === 0) {
        // 私有部署版
        preg_match('/sctp(\d+)t/', $sendKey, $matches);
        if (!$matches) {
            return [false, 'Server酱 SendKey格式错误'];
        }
        $num = $matches[1];
        $url = "https://{$num}.push.ft07.com/send/{$sendKey}.send";
    } else {
        return [false, 'Server酱 SendKey格式错误'];
    }
    
    $postData = [
        'title' => $title,
        'desp' => $content
    ];
    
    $response = httpPost($url, $postData);
    if ($response && isset($response['code']) && $response['code'] == 0) {
        return [true, 'Server酱通知发送成功'];
    }
    
    return [false, 'Server酱通知发送失败'];
}

/**
 * HTTP GET 请求
 */
function httpGet($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        return json_decode($response, true);
    }
    
    return null;
}

/**
 * HTTP POST 请求
 */
function httpPost($url, $data, $proxy = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    // 设置代理
    if ($proxy && !empty($proxy['host']) && !empty($proxy['port'])) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy['host']);
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']);
        if (!empty($proxy['auth'])) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['auth']);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode == 200) {
        return json_decode($response, true);
    }
    
    return null;
}
