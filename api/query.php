<?php
/**
 * 流量查询API - 参照原10010项目的简单设计
 * 1. 查询流量
 * 2. 计算diff
 * 3. 保存stats
 * 4. 发送通知（如需要）
 */

header('Content-Type: application/json; charset=utf-8');

// 获取参数
$token = $_GET['token'] ?? '';
$type = $_GET['type'] ?? 'flow'; // 默认查询流量，type=balance查询余额

if (!$token) {
    echo json_encode(['success' => false, 'message' => '缺少token参数']);
    exit;
}

// 余额查询（来自 balance.php）
if ($type === 'balance') {
    // 加载用户类
    require_once __DIR__ . '/../classes/Config.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/User.php';
    require_once __DIR__ . '/../classes/FlowMonitor.php';

    // 通过token查找用户
    $user = User::findByToken($token);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => '用户不存在或未激活'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 创建FlowMonitor实例，从文件读取最新cookie（和流量查询使用同一来源）
    $monitor = new FlowMonitor($user);

    // 从文件读取cookie（优先）或数据库
    $cookieFile = __DIR__ . '/../data/' . $user->accessToken . '/cookie.txt';
    $cookie = file_exists($cookieFile) ? trim(file_get_contents($cookieFile)) : $user->cookie;

    if (empty($cookie)) {
        echo json_encode(['success' => false, 'message' => '未找到认证信息，请先查询流量'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 调用联通余额查询接口
    $url = 'https://m.client.10010.com/servicequerybusiness/balancenew/accountBalancenew.htm';

    $postData = http_build_query([
        'duanlianjieabc' => '',
        'channelCode' => '',
        'serviceType' => '',
        'saleChannel' => '',
        'externalSources' => '',
        'contactCode' => '',
        'ticket' => '',
        'ticketPhone' => '',
        'ticketChannel' => '',
        'language' => 'chinese',
        'channel' => 'client'
    ]);

    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: Mozilla/5.0 (Linux; Android 16; 23117RK66C) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/142.0.7444.48 Mobile Safari/537.36; unicom{version:android@12.0701}',
        'Accept: application/json, text/plain, */*',
        'Origin: https://img.client.10010.com',
        'Referer: https://img.client.10010.com/',
        'Cookie: ' . $cookie
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo json_encode(['success' => false, 'message' => '网络请求失败: ' . $error], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'message' => '请求失败，HTTP状态码: ' . $httpCode], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 解析响应
    $data = json_decode($response, true);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => '响应数据解析失败'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($data['code'] !== '0000') {
        // Cookie失效检测
        if ($user->authType === 'full') {
            // appid+token用户：返回需要刷新cookie的标记
            echo json_encode([
                'success' => false,
                'message' => '查询失败: ' . ($data['msg'] ?? '未知错误'),
                'need_refresh_cookie' => true
            ], JSON_UNESCAPED_UNICODE);
        } else {
            // cookie用户：提示更新cookie
            echo json_encode([
                'success' => false,
                'message' => 'Cookie已失效，请在用户配置中更新Cookie'
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // 提取关键信息
    $balanceInfo = [
        'success' => true,
        'data' => [
            'balance' => $data['curntbalancecust'] ?? '0.00',              // 当前可用余额
            'monthlyFee' => $data['realfeecust'] ?? '0.00',                // 本月实时话费
            'carryForward' => $data['carryForwardFromLastMonth'] ?? '0.00', // 上月结转
            'freeAmount' => $data['freePayFeeTotal'] ?? '0.00',            // 自由金额
            'directionalAmount' => $data['directionalPsntFeeTotal'] ?? '0.00', // 定向金额
            'queryTime' => $data['queryTime'] ?? date('Y-m-d H:i:s'),     // 查询时间
        ],
        'message' => '查询成功'
    ];

    // 保存余额信息到文件（可选）
    $dataDir = __DIR__ . '/../data/' . $token;
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }
    $balanceFile = $dataDir . '/balance.json';
    file_put_contents($balanceFile, json_encode($balanceInfo['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo json_encode($balanceInfo, JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== 流量查询（原有功能）====================

$token = $_GET['token'] ?? '';
if (!$token) {
    echo json_encode(['success' => false, 'message' => '缺少token参数']);
    exit;
}

// 数据目录
$userDataDir = __DIR__ . '/../data/' . $token;
if (!is_dir($userDataDir)) {
    mkdir($userDataDir, 0775, true);
}

$files = [
    'stats' => $userDataDir . '/stats.json',
    'notify' => $userDataDir . '/notify.json'
];

// 加载用户信息
require_once __DIR__ . '/../classes/Config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Utils.php';
require_once __DIR__ . '/../classes/User.php';

// 通过token查找用户
$user = User::findByToken($token);

if (!$user) {
    echo json_encode(['success' => false, 'message' => '用户不存在或未激活']);
    exit;
}

// 查询流量（假设cookie已经通过get_cookie.php获取）
try {
    // 直接使用Cookie查询流量，不再获取cookie
    $flowData = queryWithCookie($user);

    if (!$flowData['success']) {
        echo json_encode($flowData);
        exit;
    }

    // 获取手机号
    $mobile = $user->mobile;

    // 加载上次统计
    $lastStats = loadStats($files['stats']);

    // 计算diff
    $diff = calculateDiff($flowData['data']['buckets'], $lastStats);
    $flowData['data']['diff'] = $diff;

        // 构建新的stats
    $stats = [
        'timestamp' => date('c'),
        'date' => date('Y-m-d H:i:s'),
        'stats_start_time' => $lastStats['stats_start_time'] ?? date('c'), // 保留统计周期开始时间
        'mobile' => $mobile,
        'mainPackage' => $flowData['data']['mainPackage'],
        'packages' => $flowData['data']['packages'],
        'buckets' => $flowData['data']['buckets'],
        'diff' => $diff
    ];

    // 如果是首次运行，直接保存stats并返回
    if (!$lastStats) {
        saveStats($files['stats'], $stats);
        echo json_encode([
            'success' => true,
            'message' => '查询成功（首次运行）',
            'data' => $flowData['data']
        ]);
        exit;
    }

    // 加载通知配置
    $notifyConfig = loadNotifyConfig($files['notify']);

    // 检查是否需要通知
    $shouldNotify = checkNotifyCondition($diff, $notifyConfig, $lastStats);

    if ($shouldNotify) {
        // 发送通知
        $notifyResult = sendNotification($stats, $notifyConfig, $lastStats);

        if ($notifyResult['success']) {
            // 通知成功，将累计用量(used)归零，但保持今日用量(today)
            foreach ($stats['diff'] as $key => &$diffItem) {
                $diffItem['used'] = 0;  // 清零累计用量
                // today保持不变，只在跨日时归零
            }
            unset($diffItem); // 释放引用

            // 更新统计周期开始时间
            $stats['stats_start_time'] = date('c');
        }
    }

    // 始终保存stats，这样用户可以看到最新的统计信息
    saveStats($files['stats'], $stats);

    // 返回数据（包含stats_start_time用于前端时长计算）
    echo json_encode([
        'success' => true,
        'message' => '查询成功',
        'data' => array_merge($flowData['data'], [
            'stats_start_time' => $stats['stats_start_time']
        ])
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '查询失败: ' . $e->getMessage()
    ]);
}

// ==================== 函数定义 ====================

/**
 * 获取Cookie
 */
function getCookie($user) {
    require_once __DIR__ . '/../classes/FlowMonitor.php';

    $monitor = new FlowMonitor($user);
    return $monitor->getCookie();
}

/**
 * 使用Cookie查询流量
 */
function queryWithCookie($user) {
    require_once __DIR__ . '/../classes/FlowMonitor.php';

    $monitor = new FlowMonitor($user);
    return $monitor->queryWithCookie();
}

/**
 * 查询流量（完整流程，保持兼容）
 */
function queryFlow($user) {
    require_once __DIR__ . '/../classes/FlowMonitor.php';

    // 使用FlowMonitor查询
    $monitor = new FlowMonitor($user);
    return $monitor->query();
}

/**
 * 加载stats
 */
function loadStats($file) {
    if (!file_exists($file)) {
        return null;
    }

    $content = file_get_contents($file);
    return json_decode($content, true);
}

/**
 * 保存stats
 */
function saveStats($file, $stats) {
    file_put_contents($file, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * 加载通知配置
 */
function loadNotifyConfig($file) {
    if (!file_exists($file)) {
        return null;
    }

    $content = file_get_contents($file);
    return json_decode($content, true);
}

/**
 * 计算diff - 参照原项目逻辑
 */
function calculateDiff($currentBuckets, $lastStats) {
    $diff = [];

    // 首次运行，返回全0
    if (!$lastStats || !isset($lastStats['buckets'])) {
        foreach ($currentBuckets as $key => $bucket) {
            $diff[$key] = ['used' => 0, 'today' => 0];
        }
        return $diff;
    }

    foreach ($currentBuckets as $key => $bucket) {
        $lastBucket = $lastStats['buckets'][$key] ?? null;

        if (!$lastBucket) {
            // 新增的流量桶
            $diff[$key] = ['used' => 0, 'today' => 0];
        } else {
            // 计算累计用量差异
            $usedDiff = max(0, $bucket['used'] - $lastBucket['used']);

            // 检测跨日、跨月、跨年
            $lastDate = strtotime($lastStats['date']);
            $currentDate = time();

            // 获取日期信息
            $lastYear = date('Y', $lastDate);
            $lastMonth = date('n', $lastDate);
            $lastDay = date('j', $lastDate);

            $currentYear = date('Y', $currentDate);
            $currentMonth = date('n', $currentDate);
            $currentDay = date('j', $currentDate);

            // 检测是否跨日（跨日包括跨月和跨年）
            $isCrossDay = ($currentYear != $lastYear) ||
                          ($currentMonth != $lastMonth) ||
                          ($currentDay != $lastDay);

            // 检测是否跨月（流量重置）
            $isCrossMonth = ($currentYear * 12 + $currentMonth) > ($lastYear * 12 + $lastMonth);

            // 获取上次的diff数据
            $lastDiff = $lastStats['diff'][$key] ?? ['used' => 0, 'today' => 0];

            if ($isCrossMonth) {
                // 跨月了，流量重置，used和today都使用当前已用量
                $diff[$key] = [
                    'used' => $bucket['used'],
                    'today' => $bucket['used']
                ];
            } else if ($isCrossDay) {
                // 跨日了，today重置为当前差异，used继续累积
                $diff[$key] = [
                    'used' => $lastDiff['used'] + $usedDiff,
                    'today' => $usedDiff
                ];
            } else {
                // 同一天，today和used都继续累加
                $diff[$key] = [
                    'used' => $lastDiff['used'] + $usedDiff,
                    'today' => $lastDiff['today'] + $usedDiff
                ];
            }
        }
    }

    return $diff;
}

/**
 * 检查是否需要发送通知
 */
function checkNotifyCondition($diff, $notifyConfig, $lastStats) {
    // 没有配置通知，不发送
    if (!$notifyConfig || !isset($notifyConfig['type']) || !$notifyConfig['type']) {
        return false;
    }

    // 获取阈值，默认为0
    $threshold = $notifyConfig['threshold'] ?? 0;

    // 如果阈值为0或未设置，不发送通知
    if ($threshold <= 0) {
        return false;
    }

    // 如果是首次运行，发送通知
    if (!$lastStats) {
        return true;
    }

    // 检查"所有通用"流量用量是否达到阈值（单位MB）
    $allCommonUsed = $diff['所有通用']['used'] ?? 0;
    if ($allCommonUsed >= $threshold) {
        return true;
    }

    return false;
}

/**
 * 发送通知
 */
function sendNotification($stats, $notifyConfig, $lastStats) {
    // 构建占位符
    $placeholders = buildPlaceholders($stats, $lastStats);

    // 替换占位符
    $title = applyPlaceholders($notifyConfig['title'] ?? '[套餐] [时长]', $placeholders);
    $subtitle = applyPlaceholders($notifyConfig['subtitle'] ?? '', $placeholders);
    $content = applyPlaceholders($notifyConfig['content'] ?? '', $placeholders);

    // 直接调用通知函数
    $type = $notifyConfig['type'];
    $params = $notifyConfig['params'] ?? [];
    
    // 调用对应的通知函数
    $handlers = [
        'bark' => 'notifyBark',
        'telegram' => 'notifyTelegram',
        'dingtalk' => 'notifyDingTalk',
        'qywx' => 'notifyQYWX',
        'pushplus' => 'notifyPushPlus',
        'serverchan' => 'notifyServerChan'
    ];
    
    if (!isset($handlers[$type])) {
        return ['success' => false, 'message' => '不支持的通知类型: ' . $type];
    }
    
    list($result, $message) = $handlers[$type]($params, $title, $subtitle, $content);
    
    return [
        'success' => $result,
        'message' => $message
    ];
}

/**
 * 构建占位符
 */
function buildPlaceholders($stats, $lastStats) {
    $placeholders = [];

    // 基础信息
    $placeholders['[套餐]'] = $stats['mainPackage'];
    $placeholders['[时长]'] = calculateTimeInterval($stats['stats_start_time'] ?? $lastStats['timestamp'] ?? null);
    $placeholders['[时间]'] = date('H:i:s');

    // 流量桶占位符
    $bucketNames = [
        'common_limited' => '通用有限',
        'common_unlimited' => '通用不限',
        'regional_limited' => '区域有限',
        'regional_unlimited' => '区域不限',
        'targeted_limited' => '免流有限',
        'targeted_unlimited' => '免流不限',
        '所有通用' => '所有通用',
        '所有免流' => '所有免流',
        '所有流量' => '所有流量'
    ];

    foreach ($bucketNames as $key => $name) {
        $bucket = $stats['buckets'][$key] ?? ['total' => 0, 'used' => 0, 'remain' => 0];
        $diff = $stats['diff'][$key] ?? ['used' => 0, 'today' => 0];

        // 判断是否是不限流量桶
        $isUnlimited = strpos($key, 'unlimited') !== false;

        $placeholders["[{$name}.总量]"] = formatFlow($bucket['total'], $isUnlimited);
        $placeholders["[{$name}.已用]"] = formatFlow($bucket['used'], $isUnlimited);
        $placeholders["[{$name}.剩余]"] = formatFlow($bucket['remain'], $isUnlimited);
        $placeholders["[{$name}.用量]"] = formatFlow($diff['used'], $isUnlimited);
        $placeholders["[{$name}.今日用量]"] = formatFlow($diff['today'], $isUnlimited);
    }

    return $placeholders;
}

/**
 * 应用占位符
 */
function applyPlaceholders($template, $placeholders) {
    foreach ($placeholders as $key => $value) {
        $template = str_replace($key, $value, $template);
    }
    return $template;
}

/**
 * 格式化流量
 */
function formatFlow($mb, $isUnlimited = false) {
    // 对于不限流量桶的剩余量，如果是负值或超大值（>999999MB约976GB），显示为"无限"
    if ($isUnlimited && ($mb < 0 || $mb > 999999)) {
        return '无限';
    }

    if ($mb < 1024) {
        return round($mb, 2) . 'M';
    } else if ($mb < 1024 * 1024) {
        return round($mb / 1024, 2) . 'G';
    } else {
        return round($mb / 1024 / 1024, 2) . 'T';
    }
}

/**
 * 计算时间间隔
 */
function calculateTimeInterval($lastTimestamp) {
    if (!$lastTimestamp) {
        return '首次运行';
    }

    $last = strtotime($lastTimestamp);
    $now = time();
    $diff = $now - $last;

    $minutes = floor($diff / 60);
    $hours = floor($minutes / 60);
    $days = floor($hours / 24);

    if ($days > 0) {
        return $days . '天' . ($hours % 24) . '小时';
    }
    if ($hours > 0) {
        return $hours . '小时' . ($minutes % 60) . '分钟';
    }
    return $minutes . '分钟';
}

// ==================== 通知发送函数 ====================

function notifyHttpRequest($url, $method = 'GET', $data = null, $proxy = null) {
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

function notifyBark($params, $title, $subtitle, $content) {
    $barkPush = $params['barkPush'] ?? '';
    if (!$barkPush) return [false, 'Bark Push地址不能为空'];
    
    $url = rtrim($barkPush, '/') . '/' . urlencode($title);
    $query = array_filter([
        'body' => $subtitle ? "$subtitle\n$content" : $content,
        'sound' => $params['barkSound'] ?? null,
        'group' => $params['barkGroup'] ?? null,
        'icon' => $params['barkIcon'] ?? null,
        'level' => $params['barkLevel'] ?? null,
        'url' => $params['barkUrl'] ?? null,
        'isArchive' => $params['barkArchive'] ?? null
    ]);
    
    if (!empty($query)) $url .= '?' . http_build_query($query);
    
    $response = notifyHttpRequest($url);
    return $response && ($response['code'] ?? 0) == 200 ? 
        [true, 'Bark通知发送成功'] : 
        [false, 'Bark通知发送失败'];
}

function notifyTelegram($params, $title, $subtitle, $content) {
    $botToken = $params['tgBotToken'] ?? $params['botToken'] ?? '';
    $userId = $params['tgUserId'] ?? $params['chatId'] ?? '';
    
    if (!$botToken || !$userId) return [false, 'Telegram Bot Token和User ID不能为空'];
    
    $apiHost = $params['tgApiHost'] ?? $params['apiHost'] ?? 'api.telegram.org';
    if (empty($apiHost)) $apiHost = 'api.telegram.org';
    
    $url = "https://{$apiHost}/bot{$botToken}/sendMessage";
    
    $proxy = null;
    $proxyHost = $params['tgProxyHost'] ?? $params['proxyHost'] ?? '';
    $proxyPort = $params['tgProxyPort'] ?? $params['proxyPort'] ?? '';
    if ($proxyHost && $proxyPort) {
        $proxy = ['host' => $proxyHost, 'port' => $proxyPort, 'auth' => $params['tgProxyAuth'] ?? ''];
    }
    
    $text = "📊 {$title}";
    if ($subtitle) $text .= "\n\n{$subtitle}";
    if ($content) $text .= "\n{$content}";
    
    $response = notifyHttpRequest($url, 'POST', [
        'chat_id' => $userId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ], $proxy);
    
    if ($response && ($response['ok'] ?? false)) {
        return [true, 'Telegram通知发送成功'];
    }
    
    return [false, 'Telegram通知发送失败：' . ($response['description'] ?? '无法连接到服务器')];
}

function notifyDingTalk($params, $title, $content) {
    $token = $params['ddBotToken'] ?? '';
    if (!$token) return [false, '钉钉机器人Token不能为空'];
    
    $url = "https://oapi.dingtalk.com/robot/send?access_token={$token}";
    
    $secret = $params['ddBotSecret'] ?? '';
    if ($secret) {
        $timestamp = round(microtime(true) * 1000);
        $sign = urlencode(base64_encode(hash_hmac('sha256', $timestamp . "\n" . $secret, $secret, true)));
        $url .= "&timestamp={$timestamp}&sign={$sign}";
    }
    
    $response = notifyHttpRequest($url, 'POST', [
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

function notifyQYWX($params, $title, $content) {
    $mode = $params['qywxMode'] ?? 'webhook';
    
    if ($mode === 'webhook') {
        $key = $params['qywxKey'] ?? '';
        if (!$key) return [false, '企业微信Webhook Key不能为空'];
        
        $response = notifyHttpRequest("https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key={$key}", 'POST', [
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
    
    $tokenRes = notifyHttpRequest("https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$corpid}&corpsecret={$corpsecret}");
    if (!$tokenRes || !isset($tokenRes['access_token'])) {
        return [false, '获取企业微信access_token失败'];
    }
    
    $response = notifyHttpRequest("https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token={$tokenRes['access_token']}", 'POST', [
        'touser' => $touser,
        'agentid' => (int)$agentid,
        'msgtype' => 'text',
        'text' => ['content' => "{$title}\n\n{$content}"]
    ]);
    
    return $response && ($response['errcode'] ?? -1) == 0 ? 
        [true, '企业微信通知发送成功'] : 
        [false, '企业微信通知发送失败'];
}

function notifyPushPlus($params, $title, $content) {
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
    
    $response = notifyHttpRequest('https://www.pushplus.plus/send', 'POST', $postData);
    return $response && ($response['code'] ?? -1) == 200 ? 
        [true, 'PushPlus通知发送成功'] : 
        [false, 'PushPlus通知发送失败'];
}

function notifyServerChan($params, $title, $content) {
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
    
    $response = notifyHttpRequest($url, 'POST', ['title' => $title, 'desp' => $content]);
    return $response && ($response['code'] ?? -1) == 0 ? 
        [true, 'Server酱通知发送成功'] : 
        [false, 'Server酱通知发送失败'];
}

