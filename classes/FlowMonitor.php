<?php
/**
 * 联通流量监控核心类（多用户版本）
 * 支持两种认证方式：
 * 1. 完整凭证（mobile + appid + token_online）
 * 2. Cookie方式（mobile + cookie）
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';

class FlowMonitor {
    
    private $user;          // User对象
    private $mobile;
    private $appId;
    private $tokenOnline;
    private $cookie;
    private $authType;      // 'full' 或 'cookie'
    private $db;
    
    const LOGIN_URL = 'https://m.client.10010.com/mobileService/onLine.htm';
    const QUERY_URL = 'https://m.client.10010.com/servicequerybusiness/operationservice/queryOcsPackageFlowLeftContentRevisedInJune';
    const USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15';
    
    const BUCKET_TYPES = [
        'common_limited',
        'common_unlimited',
        'regional_limited',
        'regional_unlimited',
        'targeted_limited',
        'targeted_unlimited'
    ];
    
    /**
     * 构造函数 - 接收User对象
     */
    public function __construct($user) {
        if (!($user instanceof User)) {
            throw new Exception("FlowMonitor requires a User object");
        }
        
        $this->user = $user;
        $this->mobile = $user->mobile;
        $this->authType = $user->authType;
        $this->db = Database::getInstance();
        
        // 根据认证类型设置凭证
        if ($this->authType === 'full') {
            $this->appId = $user->appid;
            $this->tokenOnline = $user->tokenOnline;
        }
        
        // 从文件读取cookie（优先于数据库）
        $this->cookie = $this->loadCookieFromFile();
        if (!$this->cookie) {
            // 文件中没有，尝试从数据库读取并保存到文件
            $this->cookie = $user->cookie;
            if ($this->cookie) {
                $this->saveCookieToFile($this->cookie);
            }
        }
    }
    
    /**
     * 获取Cookie（不进行查询）
     * @param bool $forceRefresh 是否强制刷新cookie（忽略文件时间判断）
     * 返回: ['success' => true, 'cookie' => '...', 'cookie_updated' => true/false]
     */
    public function getCookie($forceRefresh = false) {
        try {
            // 检查是否需要更新cookie（除非强制刷新）
            $needUpdateCookie = $forceRefresh || $this->needUpdateCookie();
            
            // 如果有cookie且不需要更新，直接返回
            if ($this->cookie && !$needUpdateCookie) {
                return [
                    'success' => true,
                    'cookie' => $this->cookie,
                    'cookie_updated' => false,
                    'forced' => false
                ];
            }
            
            // 需要更新cookie（仅appid+token用户）
            if ($needUpdateCookie || !$this->cookie) {
                if ($this->authType === 'full') {
                    error_log("FlowMonitor: User {$this->user->id} getting new cookie " . 
                        ($forceRefresh ? "(forced refresh)" : "(daily first query or no cookie)"));
                    
                    $loginResult = $this->login();
                    if (!$loginResult['success']) {
                        return $loginResult;
                    }
                    
                    $cookie = $loginResult['cookie'];
                    
                    // 保存新cookie
                    $this->saveCookieToFile($cookie);
                    $this->user->updateCookie($cookie);
                    $this->cookie = $cookie;
                    
                    return [
                        'success' => true,
                        'cookie' => $cookie,
                        'cookie_updated' => true,
                        'forced' => $forceRefresh
                    ];
                } else {
                    // cookie注册用户没有cookie
                    return [
                        'success' => false,
                        'error' => 'Cookie不存在，请在用户配置中设置Cookie'
                    ];
                }
            }
            
            return [
                'success' => true,
                'cookie' => $this->cookie,
                'cookie_updated' => false,
                'forced' => false
            ];
            
        } catch (Exception $e) {
            error_log("FlowMonitor Get Cookie Error (User {$this->user->id}): " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 使用已有的Cookie查询流量数据
     */
    public function queryWithCookie() {
        try {
            // 重新从文件读取最新cookie（防止get_cookie.php刚更新）
            $this->cookie = $this->loadCookieFromFile();
            if (!$this->cookie) {
                // 文件中没有，尝试从数据库读取
                $this->cookie = $this->user->cookie;
            }
            
            // 确保有cookie
            if (!$this->cookie) {
                return [
                    'success' => false,
                    'error' => 'Cookie不存在'
                ];
            }
            
            // 查询流量
            $result = $this->queryFlow($this->cookie);
            if (!$result['success']) {
                // Cookie失效
                if ($this->authType === 'full') {
                    // appid+token用户：cookie意外失效，需要重新获取
                    return [
                        'success' => false,
                        'error' => 'Cookie已失效，请重新获取',
                        'need_refresh_cookie' => true
                    ];
                } else {
                    // cookie注册用户：cookie失效
                    return [
                        'success' => false,
                        'error' => 'Cookie已失效，请在用户配置中更新Cookie'
                    ];
                }
            }
            
            $responseData = $result['data'];
            
            // 分析流量数据
            $currentStats = $this->analyzeFlow($responseData);
            
            // 从数据库读取历史数据
            $previousStats = $this->loadStatsFromDB();
            
            // 计算差异
            $diff = $this->calculateDiff($currentStats, $previousStats);
            $currentStats['diff'] = $diff;
            $currentStats['previousTimestamp'] = $previousStats ? ($previousStats['timestamp'] ?? null) : null;
            $currentStats['timestamp'] = time();
            
            // 保存统计数据到数据库
            $this->saveStatsToDB($currentStats);
            
            // 更新用户最后查询时间
            $this->user->updateLastQueryTime();
            
            return [
                'success' => true,
                'data' => $currentStats
            ];
            
        } catch (Exception $e) {
            error_log("FlowMonitor Query Error (User {$this->user->id}): " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 主查询方法（保持兼容性，内部调用getCookie和queryWithCookie）
     */
    public function query() {
        try {
            // 步骤1: 获取Cookie
            $cookieResult = $this->getCookie();
            if (!$cookieResult['success']) {
                return $cookieResult;
            }
            
            $cookieUpdated = $cookieResult['cookie_updated'];
            
            // 步骤2: 使用Cookie查询流量
            $queryResult = $this->queryWithCookie();
            if (!$queryResult['success']) {
                return $queryResult;
            }
            
            // 返回结果，附带cookie_updated信息
            $queryResult['cookie_updated'] = $cookieUpdated;
            return $queryResult;
            
        } catch (Exception $e) {
            error_log("FlowMonitor Query Error (User {$this->user->id}): " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 判断是否需要更新cookie
     * appid+token用户：每天首次查询时更新
     * cookie注册用户：不自动更新
     */
    private function needUpdateCookie() {
        // 非appid+token用户，不自动更新cookie
        if ($this->authType !== 'full') {
            return false;
        }
        
        // 没有cookie，需要获取
        if (!$this->cookie) {
            return true;
        }
        
        // 检查cookie文件的修改时间，如果不是今天的，需要重新获取
        $cookieFile = $this->getCookieFilePath();
        if (!file_exists($cookieFile)) {
            return true; // 文件不存在，需要获取
        }
        
        // 判断文件修改时间是否是今天
        $fileModTime = filemtime($cookieFile);
        $today = date('Y-m-d');
        $fileDate = date('Y-m-d', $fileModTime);
        
        return $fileDate !== $today; // 不是今天的文件，需要更新
    }
    
    /**
     * 获取cookie文件路径
     */
    private function getCookieFilePath() {
        $userDir = __DIR__ . '/../data/' . $this->user->accessToken;
        if (!is_dir($userDir)) {
            mkdir($userDir, 0755, true);
        }
        return $userDir . '/cookie.txt';
    }
    
    /**
     * 从文件加载cookie
     */
    private function loadCookieFromFile() {
        $cookieFile = $this->getCookieFilePath();
        if (!file_exists($cookieFile)) {
            return null;
        }
        
        $cookie = file_get_contents($cookieFile);
        return $cookie ? trim($cookie) : null;
    }
    
    /**
     * 保存cookie到文件
     */
    private function saveCookieToFile($cookie) {
        $cookieFile = $this->getCookieFilePath();
        file_put_contents($cookieFile, $cookie);
        return true;
    }
    
    /**
     * 通用 cURL 请求方法
     * @param string $url 请求URL
     * @param array $data POST数据
     * @param array $headers 额外的请求头
     * @param bool $returnHeader 是否返回响应头
     * @return array ['success' => bool, 'data' => mixed, 'headers' => string]
     */
    private function curlRequest($url, $data = [], $headers = [], $returnHeader = false) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => !empty($data),
            CURLOPT_POSTFIELDS => !empty($data) ? http_build_query($data) : '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => $returnHeader,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 16,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_HTTPHEADER => array_merge([
                'User-Agent: ' . self::USER_AGENT,
                'Content-Type: application/x-www-form-urlencoded'
            ], $headers)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if ($returnHeader) {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            
            if ($error) {
                return ['success' => false, 'error' => $error, 'http_code' => $httpCode];
            }
            
            return [
                'success' => true,
                'headers' => substr($response, 0, $headerSize),
                'body' => substr($response, $headerSize),
                'http_code' => $httpCode
            ];
        }
        
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => $error, 'http_code' => $httpCode];
        }
        
        return ['success' => true, 'data' => $response, 'http_code' => $httpCode];
    }
    
    /**
     * 登录获取 Cookie
     */
    private function login() {
        error_log("FlowMonitor Login: mobile={$this->mobile}, appId=" . substr($this->appId, 0, 20) . "...");
        
        $data = [
            'isFirstInstall' => '1',
            'reqtime' => round(microtime(true) * 1000),
            'deviceOS' => 'android15',
            'netWay' => 'Wifi',
            'version' => 'android@12.0500',
            'pushPlatform' => 'OPPO',
            'token_online' => $this->tokenOnline,
            'provinceChanel' => 'general',
            'appId' => $this->appId,
            'simOperator' => '5,中国联通,460,01,cn@5,--,460,01,cn',
            'deviceModel' => 'PKB110',
            'step' => 'bindlist',
            'deviceBrand' => 'OPPO',
            'flushkey' => '1'
        ];
        
        $result = $this->curlRequest(self::LOGIN_URL, $data, [], true);
        
        if (!$result['success']) {
            error_log("FlowMonitor Login Failed: User {$this->user->id}, Error: {$result['error']}");
            return ['success' => false, 'error' => '登录失败：' . $result['error']];
        }
        
        error_log("FlowMonitor Login: User {$this->user->id}, HTTP={$result['http_code']}");
        
        // 提取 Cookie
        preg_match_all('/Set-Cookie:\s*([^;]+)/i', $result['headers'], $matches);
        if (empty($matches[1])) {
            error_log("FlowMonitor Login Failed: User {$this->user->id}, No cookies in response");
            return ['success' => false, 'error' => '登录失败：未获取到Cookie (HTTP ' . $result['http_code'] . ')'];
        }
        
        $cookie = implode('; ', $matches[1]);
        error_log("FlowMonitor Login Success: User {$this->user->id}, Cookie=" . substr($cookie, 0, 100) . "...");
        
        return ['success' => true, 'cookie' => $cookie];
    }
    
    /**
     * 查询流量数据
     */
    private function queryFlow($cookie) {
        $result = $this->curlRequest(self::QUERY_URL, [], ['Cookie: ' . $cookie]);
        
        if (!$result['success']) {
            error_log("FlowMonitor queryFlow Error: {$result['error']}");
            return ['success' => false, 'error' => '查询失败：' . $result['error']];
        }
        
        if ($result['http_code'] != 200) {
            error_log("FlowMonitor queryFlow Error: HTTP {$result['http_code']}");
            return ['success' => false, 'error' => '查询失败：HTTP ' . $result['http_code']];
        }
        
        $data = json_decode($result['data'], true);
        if (!$data || !isset($data['code']) || $data['code'] !== '0000') {
            $errorCode = $data['code'] ?? 'unknown';
            $errorMsg = $data['msg'] ?? $data['message'] ?? 'unknown';
            error_log("FlowMonitor queryFlow Error: code={$errorCode}, msg={$errorMsg}");
            return ['success' => false, 'error' => "Cookie已失效或查询失败 (code: {$errorCode})"];
        }
        
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    /**
     * 分析流量数据
     */
    private function analyzeFlow($data) {
        $mainPackage = $data['packageName'] ?? '未知套餐';
        $hasShare = isset($data['shareData']);
        
        // 初始化流量桶
        $buckets = [];
        foreach (self::BUCKET_TYPES as $type) {
            $buckets[$type] = ['total' => 0, 'used' => 0, 'remain' => 0];
        }
        
        // 解析流量包
        $packages = [];
        $this->processPackages($data, $packages, $hasShare);
        
        // 统计流量桶
        foreach ($packages as $pkg) {
            $bucketKey = $this->determineBucketKey($pkg);
            $buckets[$bucketKey]['total'] += $pkg['total'];
            $buckets[$bucketKey]['used'] += $pkg['used'];
            $buckets[$bucketKey]['remain'] += $pkg['remain'];
        }
        
        // 生成聚合桶
        $fullBuckets = $this->generateFullBuckets($buckets);
        
        return [
            'mobile' => $this->mobile,
            'mainPackage' => $mainPackage,
            'packages' => $packages,
            'buckets' => $fullBuckets
        ];
    }
    
    /**
     * 处理流量包数据
     */
    private function processPackages($data, &$packages, $hasShare) {
        // 处理共享数据
        if ($hasShare && isset($data['shareData']['details'])) {
            foreach ($data['shareData']['details'] as $pkg) {
                if (empty($pkg['flowType']) || empty($pkg['resourceType'])) continue;
                
                $used = floatval($pkg['use'] ?? 0);
                if (isset($pkg['viceCardlist']) && !empty($pkg['viceCardlist'])) {
                    $used = $this->getCurrentMobileUsage($pkg['viceCardlist']);
                }
                
                $packages[] = [
                    'tag' => $pkg['feePolicyId'] ?? '',
                    'name' => $pkg['feePolicyName'] ?? '',
                    'flowType' => $pkg['flowType'],
                    'resourceType' => $pkg['resourceType'],
                    'total' => floatval($pkg['total'] ?? 0),
                    'used' => $used,
                    'remain' => floatval($pkg['remain'] ?? 0),
                    'viceCardlist' => $pkg['viceCardlist'] ?? null,
                    'endDate' => $pkg['endDate'] ?? '',
                    'endXsbDate' => $pkg['endXsbDate'] ?? '',
                    'isPublicFree' => false
                ];
            }
            
            // 处理非共享数据
            if (isset($data['unshared'])) {
                foreach ($data['unshared'] as $group) {
                    if (isset($group['details'])) {
                        foreach ($group['details'] as $pkg) {
                            if (empty($pkg['flowType']) || empty($pkg['resourceType'])) continue;
                            $packages[] = $this->parsePackage($pkg);
                        }
                    }
                }
            }
        } else {
            // 无共享数据
            if (isset($data['resources'])) {
                foreach ($data['resources'] as $group) {
                    if (isset($group['details'])) {
                        foreach ($group['details'] as $pkg) {
                            if (empty($pkg['flowType']) || empty($pkg['resourceType'])) continue;
                            $packages[] = $this->parsePackage($pkg);
                        }
                    }
                }
            }
        }
        
        // 处理公免流量
        if (isset($data['MlResources'])) {
            foreach ($data['MlResources'] as $group) {
                if (isset($group['details'])) {
                    foreach ($group['details'] as $pkg) {
                        if (empty($pkg['flowType'])) continue;
                        
                        $parsed = $this->parsePackage($pkg);
                        $parsed['resourceType'] = '13';
                        $parsed['total'] = 0;
                        $parsed['remain'] = 0;
                        $parsed['isPublicFree'] = true;
                        $packages[] = $parsed;
                    }
                }
            }
        }
    }
    
    /**
     * 解析单个流量包
     */
    private function parsePackage($pkg) {
        return [
            'tag' => $pkg['feePolicyId'] ?? '',
            'name' => $pkg['feePolicyName'] ?? '',
            'flowType' => $pkg['flowType'] ?? '',
            'resourceType' => $pkg['resourceType'] ?? '',
            'total' => floatval($pkg['total'] ?? 0),
            'used' => floatval($pkg['use'] ?? 0),
            'remain' => floatval($pkg['remain'] ?? 0),
            'viceCardlist' => $pkg['viceCardlist'] ?? null,
            'endDate' => $pkg['endDate'] ?? '',
            'endXsbDate' => $pkg['endXsbDate'] ?? '',
            'isPublicFree' => false
        ];
    }
    
    /**
     * 获取当前手机号的流量使用
     */
    private function getCurrentMobileUsage($viceCards) {
        foreach ($viceCards as $card) {
            if ($this->isCurrentMobile($card['usernumber'])) {
                return floatval($card['use'] ?? 0);
            }
        }
        return 0;
    }
    
    /**
     * 判断是否为当前手机号
     */
    private function isCurrentMobile($phoneNumber) {
        if (preg_match('/^(\d{3})\*{4}(\d{4})$/', $phoneNumber, $matches)) {
            return substr($this->mobile, 0, 3) === $matches[1] 
                && substr($this->mobile, -4) === $matches[2];
        }
        return $phoneNumber === $this->mobile;
    }
    
    /**
     * 确定流量桶类型
     */
    private function determineBucketKey($pkg) {
        $ft = $pkg['flowType'];
        $rt = $pkg['resourceType'];
        $isLimited = $pkg['total'] > 0;
        
        if ($ft === '1' && ($rt === '01' || $rt === '1')) {
            return $isLimited ? 'common_limited' : 'common_unlimited';
        }
        
        if (($ft === '2' || $ft === '3') && ($rt === '01' || $rt === '1')) {
            return $isLimited ? 'regional_limited' : 'regional_unlimited';
        }
        
        if (($ft === '2' || $ft === '3') && ($rt === '13' || $rt === 'I3')) {
            return $isLimited ? 'targeted_limited' : 'targeted_unlimited';
        }
        
        if ($pkg['isPublicFree'] || $rt === '13' || $rt === 'I3') {
            return $isLimited ? 'targeted_limited' : 'targeted_unlimited';
        }
        
        return $isLimited ? 'common_limited' : 'common_unlimited';
    }
    
    /**
     * 生成完整的流量桶（包括聚合桶）
     */
    private function generateFullBuckets($baseBuckets) {
        $fullBuckets = $baseBuckets;
        
        $commonKeys = ['common_limited', 'common_unlimited', 'regional_limited', 'regional_unlimited'];
        $targetedKeys = ['targeted_limited', 'targeted_unlimited'];
        
        // 计算聚合桶时，对于不限流量的桶（total=0，remain为负数），不计入remain统计
        $commonRemain = 0;
        foreach ($commonKeys as $key) {
            if (isset($baseBuckets[$key])) {
                $bucket = $baseBuckets[$key];
                // 只有有限流量桶（total > 0）才计入剩余流量
                if ($bucket['total'] > 0) {
                    $commonRemain += $bucket['remain'];
                }
            }
        }
        
        $targetedRemain = 0;
        foreach ($targetedKeys as $key) {
            if (isset($baseBuckets[$key])) {
                $bucket = $baseBuckets[$key];
                // 只有有限流量桶（total > 0）才计入剩余流量
                if ($bucket['total'] > 0) {
                    $targetedRemain += $bucket['remain'];
                }
            }
        }
        
        $fullBuckets['所有通用'] = [
            'total' => array_sum(array_column(array_intersect_key($baseBuckets, array_flip($commonKeys)), 'total')),
            'used' => array_sum(array_column(array_intersect_key($baseBuckets, array_flip($commonKeys)), 'used')),
            'remain' => $commonRemain
        ];
        
        $fullBuckets['所有免流'] = [
            'total' => array_sum(array_column(array_intersect_key($baseBuckets, array_flip($targetedKeys)), 'total')),
            'used' => array_sum(array_column(array_intersect_key($baseBuckets, array_flip($targetedKeys)), 'used')),
            'remain' => $targetedRemain
        ];
        
        $fullBuckets['所有流量'] = [
            'total' => $fullBuckets['所有通用']['total'] + $fullBuckets['所有免流']['total'],
            'used' => $fullBuckets['所有通用']['used'] + $fullBuckets['所有免流']['used'],
            'remain' => $fullBuckets['所有通用']['remain'] + $fullBuckets['所有免流']['remain']
        ];
        
        return $fullBuckets;
    }
    
    /**
     * 计算流量差异（支持跨月）
     */
    private function calculateDiff($currentStats, $previousStats) {
        $diff = [];
        
        foreach (self::BUCKET_TYPES as $key) {
            $diff[$key] = ['used' => 0, 'today' => 0];
        }
        
        if (!$previousStats) {
            return $this->addAggregateDiff($diff);
        }
        
        $currentTime = time();
        $previousTime = $previousStats['timestamp'];
        
        // 检查是否跨月
        $currentMonth = date('Y', $currentTime) * 12 + date('n', $currentTime);
        $previousMonth = date('Y', $previousTime) * 12 + date('n', $previousTime);
        $isCrossMonth = $currentMonth > $previousMonth;
        
        // 检查是否同一天
        $isSameDay = date('Y-m-d', $currentTime) === date('Y-m-d', $previousTime);
        
        foreach (self::BUCKET_TYPES as $key) {
            $current = $currentStats['buckets'][$key] ?? ['used' => 0];
            $previous = $previousStats['buckets'][$key] ?? ['used' => 0];
            $previousDiff = $previousStats['diff'][$key] ?? ['used' => 0, 'today' => 0];
            
            if ($isCrossMonth) {
                // 跨月：直接使用当前已用量
                $used = $current['used'];
                $today = $used;
            } else {
                // 同月：计算差值
                $used = max(0, $current['used'] - $previous['used']);
                $today = $isSameDay ? ($previousDiff['today'] + $used) : $used;
            }
            
            $diff[$key] = ['used' => $used, 'today' => $today];
        }
        
        return $this->addAggregateDiff($diff);
    }
    
    /**
     * 添加聚合差异
     */
    private function addAggregateDiff($diff) {
        $commonKeys = ['common_limited', 'common_unlimited', 'regional_limited', 'regional_unlimited'];
        $targetedKeys = ['targeted_limited', 'targeted_unlimited'];
        
        $diff['所有通用'] = [
            'used' => array_sum(array_column(array_intersect_key($diff, array_flip($commonKeys)), 'used')),
            'today' => array_sum(array_column(array_intersect_key($diff, array_flip($commonKeys)), 'today'))
        ];
        
        $diff['所有免流'] = [
            'used' => array_sum(array_column(array_intersect_key($diff, array_flip($targetedKeys)), 'used')),
            'today' => array_sum(array_column(array_intersect_key($diff, array_flip($targetedKeys)), 'today'))
        ];
        
        $diff['所有流量'] = [
            'used' => $diff['所有通用']['used'] + $diff['所有免流']['used'],
            'today' => $diff['所有通用']['today'] + $diff['所有免流']['today']
        ];
        
        return $diff;
    }
    
    /**
     * 从数据库加载历史统计数据
     */
    private function loadStatsFromDB() {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM flow_stats WHERE user_id = :user_id 
                 ORDER BY timestamp DESC LIMIT 1"
            );
            $stmt->bindValue(':user_id', $this->user->id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            
            if (!$row) {
                return null;
            }
            
            return [
                'timestamp' => $row['timestamp'],
                'date' => $row['date'],
                'mobile' => $row['mobile'],
                'mainPackage' => $row['main_package'],
                'buckets' => json_decode($row['buckets'], true),
                'diff' => json_decode($row['diff'], true),
                'packages' => json_decode($row['packages'], true)
            ];
            
        } catch (Exception $e) {
            error_log("Load Stats Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 保存统计数据到数据库
     */
    private function saveStatsToDB($stats) {
        try {
            $now = new DateTime();
            $date = $now->format('Y-m-d');
            
            $stmt = $this->db->prepare(
                "INSERT INTO flow_stats 
                 (user_id, timestamp, date, mobile, main_package, buckets, diff, packages) 
                 VALUES (:user_id, :timestamp, :date, :mobile, :main_package, :buckets, :diff, :packages)"
            );
            
            $stmt->bindValue(':user_id', $this->user->id, SQLITE3_INTEGER);
            $stmt->bindValue(':timestamp', $stats['timestamp'], SQLITE3_INTEGER);
            $stmt->bindValue(':date', $date, SQLITE3_TEXT);
            $stmt->bindValue(':mobile', $stats['mobile'], SQLITE3_TEXT);
            $stmt->bindValue(':main_package', $stats['mainPackage'], SQLITE3_TEXT);
            $stmt->bindValue(':buckets', json_encode($stats['buckets']), SQLITE3_TEXT);
            $stmt->bindValue(':diff', json_encode($stats['diff']), SQLITE3_TEXT);
            $stmt->bindValue(':packages', json_encode($stats['packages']), SQLITE3_TEXT);
            
            $stmt->execute();
            
            // 清理旧数据：只保留最近30天的记录
            $this->cleanOldStats();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Save Stats Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 清理旧的统计数据
     */
    private function cleanOldStats() {
        try {
            $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
            
            $stmt = $this->db->prepare(
                "DELETE FROM flow_stats WHERE user_id = :user_id AND timestamp < :cutoff"
            );
            $stmt->bindValue(':user_id', $this->user->id, SQLITE3_INTEGER);
            $stmt->bindValue(':cutoff', $thirtyDaysAgo, SQLITE3_INTEGER);
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Clean Old Stats Error: " . $e->getMessage());
        }
    }
}
