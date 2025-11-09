<?php
namespace App\Services;

use App\Utils\Logger;
use Exception;

/**
 * 联通API服务类
 * 完全复刻原始JS项目的流量查询和分析逻辑
 * 实现9大流量桶的数据处理
 */
class UnicomService {
    private $config;
    
    // 9大流量桶配置
    const BUCKET_BASE = [
        'common_limited',      // 通用有限
        'common_unlimited',    // 通用不限
        'regional_limited',    // 区域有限
        'regional_unlimited',  // 区域不限
        'targeted_limited',    // 免流有限
        'targeted_unlimited'   // 免流不限
    ];
    
    const BUCKET_AGGREGATE = [
        '所有通用',  // common + regional
        '所有免流',  // targeted
        '所有流量'   // 所有通用 + 所有免流
    ];
    
    const BUCKET_NAMES = [
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
    
    public function __construct() {
        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $this->config = $appConfig['unicom'] ?? [
            'login_url' => 'https://m.client.10010.com/mobileService/onLine.htm',
            'query_url' => 'https://m.client.10010.com/servicequerybusiness/operationservice/queryOcsPackageFlowLeftContentRevisedInJune',
            'balance_url' => 'https://m.client.10010.com/servicequerybusiness/balancenew/accountBalancenew.htm',
            'timeout' => 16,
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15'
        ];
    }
    
    /**
     * 登录获取Cookie
     * @param string $appId APPID
     * @param string $tokenOnline Token Online
     * @return string Cookie字符串
     * @throws Exception
     */
    public function login($appId, $tokenOnline) {
        Logger::query('联通登录开始', 'info', [
            'appId' => substr($appId, 0, 10) . '...'
        ]);
        
        $data = http_build_query([
            'isFirstInstall' => '1',
            'reqtime' => (string)(time() * 1000),
            'deviceOS' => 'android15',
            'netWay' => 'Wifi',
            'version' => 'android@12.0500',
            'pushPlatform' => 'OPPO',
            'token_online' => $tokenOnline,
            'provinceChanel' => 'general',
            'appId' => $appId,
            'simOperator' => '5,中国联通,460,01,cn@5,--,460,01,cn',
            'deviceModel' => 'PKB110',
            'step' => 'bindlist',
            'deviceBrand' => 'OPPO',
            'flushkey' => '1',
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->config['login_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . $this->config['user_agent'],
                'Accept-Encoding: gzip',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception('登录请求失败：' . ($curlError ?: '网络连接错误'));
        }
        
        if ($httpCode !== 200) {
            throw new Exception('登录请求失败，HTTP状态码: ' . $httpCode);
        }
        
        // 提取Cookie
        preg_match_all('/Set-Cookie:\s*([^;]+)/i', $response, $matches);
        
        if (empty($matches[1])) {
            throw new Exception('登录失败：未获取到Cookie，请检查appId和token_online');
        }
        
        $cookie = implode('; ', $matches[1]);
        
        Logger::query('联通登录成功', 'info', [
            'cookie_length' => strlen($cookie)
        ]);
        
        return $cookie;
    }
    
    /**
     * 查询流量数据
     * @param string $cookie Cookie字符串
     * @return array 流量数据
     * @throws Exception
     */
    public function queryFlow($cookie) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->config['query_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => [
                'Cookie: ' . $cookie,
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: ' . $this->config['user_agent'],
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception('查询请求失败：' . ($curlError ?: '网络连接错误'));
        }
        
        if ($httpCode !== 200) {
            throw new Exception('查询请求失败，HTTP状态码: ' . $httpCode);
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('数据解析失败: ' . json_last_error_msg());
        }
        
        // 检查是否Cookie失效
        if (is_numeric($response) || (isset($data['code']) && $data['code'] !== '0000')) {
            throw new Exception('Cookie已失效');
        }
        
        return $data;
    }
    
    /**
     * 查询余额数据
     * @param string $cookie Cookie字符串
     * @return array 余额数据 ['balance' => string, 'realFee' => string]
     * @throws Exception
     */
    public function queryBalance($cookie) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->config['balance_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => [
                'Cookie: ' . $cookie,
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: ' . $this->config['user_agent'],
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception('余额查询请求失败：' . ($curlError ?: '网络连接错误'));
        }
        
        if ($httpCode !== 200) {
            throw new Exception('余额查询请求失败，HTTP状态码: ' . $httpCode);
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('余额数据解析失败: ' . json_last_error_msg());
        }
        
        // 检查是否Cookie失效或查询失败
        if (isset($data['code']) && $data['code'] !== '0000') {
            throw new Exception('余额查询失败: ' . ($data['msg'] ?? 'Cookie已失效'));
        }
        
        // 提取关键数据
        return [
            'balance' => $data['curntbalancecust'] ?? '0.00',      // 账户当前可用余额
            'realFee' => $data['realfeecust'] ?? '0.00',           // 本月实时话费
            'canUseFee' => $data['canusefeecust'] ?? '0.00',       // 账户可用金额（含结转）
            'queryTime' => $data['queryTime'] ?? date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 获取Cookie和流量数据（带自动重试）
     * @param array $user 用户信息
     * @return array ['success' => bool, 'cookie' => string, 'data' => array, 'needUpdateCookie' => bool]
     */
    public function getCookieAndFlow($user) {
        $cookie = $user['cookie'] ?? '';
        $needLogin = false;
        
        // 判断是否需要重新登录
        if (!empty($user['appid']) && !empty($user['token_online'])) {
            // 有token_online认证方式，检查cookie是否需要更新
            if (empty($cookie)) {
                $needLogin = true;
                Logger::query('Cookie为空，需要登录', 'info', ['user_id' => $user['id']]);
            }
        } elseif (empty($cookie)) {
            throw new Exception('Cookie为空且缺少登录凭证（appId或token_online）');
        }
        
        // 如果需要登录，获取新Cookie
        if ($needLogin) {
            try {
                $cookie = $this->login($user['appid'], $user['token_online']);
            } catch (Exception $e) {
                Logger::error('登录失败', [
                    'user_id' => $user['id'],
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }
        
        // 查询流量
        try {
            $flowData = $this->queryFlow($cookie);
            return [
                'success' => true,
                'cookie' => $cookie,
                'data' => $flowData,
                'needUpdateCookie' => $needLogin,
            ];
        } catch (Exception $e) {
            // 如果查询失败且错误是Cookie失效，尝试重新登录
            if (strpos($e->getMessage(), 'Cookie已失效') !== false) {
                if (!empty($user['appid']) && !empty($user['token_online'])) {
                    Logger::query('Cookie失效，重新登录', 'info', ['user_id' => $user['id']]);
                    $cookie = $this->login($user['appid'], $user['token_online']);
                    $flowData = $this->queryFlow($cookie);
                    return [
                        'success' => true,
                        'cookie' => $cookie,
                        'data' => $flowData,
                        'needUpdateCookie' => true,
                    ];
                } else {
                    throw new Exception('Cookie已失效且缺少登录凭证（appId或token_online）');
                }
            }
            
            throw $e;
        }
    }
    
    /**
     * 分析流量数据 - 核心方法
     * 完全复刻JS版本的逻辑
     * @param string $mobile 手机号
     * @param array $flowData 联通API返回的流量数据
     * @return array ['mainPackage' => string, 'packages' => array, 'buckets' => array]
     */
    public function analyze($mobile, $flowData) {
        $mainPackage = $flowData['packageName'] ?? '未知套餐';
        
        // 初始化6个基础流量桶
        $buckets = [];
        foreach (self::BUCKET_BASE as $key) {
            $buckets[$key] = ['total' => 0, 'used' => 0, 'remain' => 0];
        }
        
        $packages = [];
        $hasShare = isset($flowData['shareData']) && !empty($flowData['shareData']);
        
        // 处理流量包
        $this->processPackages($flowData, $packages, $mobile, $hasShare);
        
        // 分类统计到流量桶
        foreach ($packages as $pkg) {
            $type = $this->determinePackageType($pkg);
            
            // 判断是有限还是无限流量包
            // - 公免流量包（total=0, remain=0）不计入桶统计
            // - 无限流量包（total=0, remain<0）计入 unlimited
            // - 普通流量包（total>0）计入 limited
            if ($pkg['total'] == 0 && $pkg['remain'] == 0) {
                // 公免流量包，跳过不计入桶
                continue;
            }
            
            $limited = ($pkg['total'] > 0) ? 'limited' : 'unlimited';
            $key = $type . '_' . $limited;
            
            if (isset($buckets[$key])) {
                $buckets[$key]['total'] += $pkg['total'];
                $buckets[$key]['used'] += $pkg['use'];
                $buckets[$key]['remain'] += $pkg['remain'];
            }
        }
        
        // 为每个流量包构建详情数据
        foreach ($packages as &$pkg) {
            $pkg['details'] = $this->buildPackageDetails($pkg, $mobile);
            $pkg['type'] = $this->getPackageTypeLabel($pkg);
        }
        unset($pkg); // 解除引用
        
        return [
            'mainPackage' => $mainPackage,
            'packages' => $packages,
            'buckets' => $buckets
        ];
    }
    
    /**
     * 构建流量包详情数组
     * @param array $pkg 流量包数据
     * @param string $mobile 当前手机号
     * @return array 详情数组
     */
    private function buildPackageDetails($pkg, $mobile) {
        $details = [];
        
        // 添加有效期
        if (!empty($pkg['endDate'])) {
            $details[] = [
                'label' => '有效期',
                'value' => $pkg['endDate']
            ];
        }
        
        // 如果有副卡列表，添加主副卡详情
        if (isset($pkg['viceCardlist']) && is_array($pkg['viceCardlist']) && count($pkg['viceCardlist']) > 0) {
            foreach ($pkg['viceCardlist'] as $card) {
                $usernumber = $card['usernumber'] ?? '';
                $use = floatval($card['use'] ?? 0);
                $isCurrent = !empty($card['currentLoginFlag']) && $card['currentLoginFlag'] == '1';
                $isVice = !empty($card['viceCardflag']) && $card['viceCardflag'] == '1';
                
                $label = $usernumber;
                if ($isCurrent) {
                    $label .= ' (当前)';
                } elseif ($isVice) {
                    $label .= ' (主卡)';
                } else {
                    $label .= ' (副卡)';
                }
                
                $details[] = [
                    'label' => $label,
                    'value' => self::formatFlow($use)
                ];
            }
        }
        
        return $details;
    }
    
    /**
     * 获取流量包类型标签
     * @param array $pkg 流量包数据
     * @return string 类型标签
     */
    private function getPackageTypeLabel($pkg) {
        // 公免流量包 - 只有标记为 isPublicFree 的才是真正的公免
        if (isset($pkg['isPublicFree']) && $pkg['isPublicFree']) {
            return '公免';
        }
        
        // 判断流量包类型
        $type = $this->determinePackageType($pkg);
        $typeLabels = [
            'common' => '通用',
            'regional' => '区域',
            'targeted' => '定向'
        ];
        
        return $typeLabels[$type] ?? '通用';
    }
    
    /**
     * 处理流量包数据
     * @param array $data 联通API数据
     * @param array &$packages 流量包列表（引用）
     * @param string $mobile 手机号
     * @param bool $hasShare 是否有共享流量
     */
    private function processPackages($data, &$packages, $mobile, $hasShare) {
        $parsePkg = function($p) {
            return [
                'tag' => $p['feePolicyId'] ?? '',
                'name' => $p['feePolicyName'] ?? '',
                'flowType' => $p['flowType'] ?? '',
                'resourceType' => $p['resourceType'] ?? '',
                'total' => floatval($p['total'] ?? 0),
                'use' => floatval($p['use'] ?? 0),
                'remain' => floatval($p['remain'] ?? 0),
                'endDate' => $p['endDate'] ?? '',
                'endXsbDate' => $p['endXsbDate'] ?? '',
            ];
        };
        
        // A. 处理共享流量（shareData.details）
        if ($hasShare && isset($data['shareData']['details'])) {
            foreach ($data['shareData']['details'] as $p) {
                if (!empty($p['flowType']) && !empty($p['resourceType'])) {
                    $pkg = $parsePkg($p);
                    
                    // 如果有副卡列表，提取当前手机号的使用量
                    if (isset($p['viceCardlist']) && is_array($p['viceCardlist'])) {
                        $pkg['use'] = $this->getCurrentMobileUsage($p['viceCardlist'], $mobile);
                        $pkg['viceCardlist'] = $p['viceCardlist'];
                    }
                    
                    $packages[] = $pkg;
                }
            }
        }
        
        // B. 处理独享流量（hasShare ? unshared : resources）
        $resourceKey = $hasShare ? 'unshared' : 'resources';
        if (isset($data[$resourceKey]) && is_array($data[$resourceKey])) {
            foreach ($data[$resourceKey] as $resource) {
                if (isset($resource['details']) && is_array($resource['details'])) {
                    foreach ($resource['details'] as $p) {
                        if (!empty($p['flowType']) && !empty($p['resourceType'])) {
                            $packages[] = $parsePkg($p);
                        }
                    }
                }
            }
        }
        
        // C. 处理公免流量（MlResources）
        if (isset($data['MlResources']) && is_array($data['MlResources'])) {
            foreach ($data['MlResources'] as $resource) {
                if (isset($resource['details']) && is_array($resource['details'])) {
                    foreach ($resource['details'] as $p) {
                        if (!empty($p['flowType'])) {
                            $pkg = $parsePkg($p);
                            $pkg['resourceType'] = '13';  // 强制标记为定向流量
                            $pkg['total'] = 0;            // 无限流量
                            $pkg['remain'] = 0;           // 无限流量
                            $pkg['isPublicFree'] = true;  // 公免标记
                            $packages[] = $pkg;
                        }
                    }
                }
            }
        }
    }
    
    /**
     * 获取当前手机号的使用量（从副卡列表中）
     * @param array $viceCardlist 副卡列表
     * @param string $mobile 手机号
     * @return float 使用量
     */
    private function getCurrentMobileUsage($viceCardlist, $mobile) {
        foreach ($viceCardlist as $card) {
            if ($this->isCurrentMobile($card['usernumber'] ?? '', $mobile)) {
                return floatval($card['use'] ?? 0);
            }
        }
        return 0;
    }
    
    /**
     * 判断是否是当前手机号
     * @param string $phoneNumber 号码（可能是隐藏格式 138****5678）
     * @param string $targetMobile 目标手机号
     * @return bool
     */
    private function isCurrentMobile($phoneNumber, $targetMobile) {
        if (empty($phoneNumber) || empty($targetMobile)) {
            return false;
        }
        
        // 处理隐藏格式 138****5678
        if (preg_match('/^(\d{3})\*{4}(\d{4})$/', $phoneNumber, $matches)) {
            return substr($targetMobile, 0, 3) === $matches[1] && 
                   substr($targetMobile, -4) === $matches[2];
        }
        
        return $phoneNumber === $targetMobile;
    }
    
    /**
     * 确定流量包类型
     * @param array $pkg 流量包数据
     * @return string 'common' | 'regional' | 'targeted'
     */
    private function determinePackageType($pkg) {
        $ft = $pkg['flowType'];
        $rt = $pkg['resourceType'];
        
        // 通用流量：flowType=1 且 resourceType=01或1
        if ($ft === '1' && ($rt === '01' || $rt === '1')) {
            return 'common';
        }
        
        // 区域流量：flowType=2/3 且 resourceType=01或1
        if (($ft === '2' || $ft === '3') && ($rt === '01' || $rt === '1')) {
            return 'regional';
        }
        
        // 定向免流：flowType=2/3 且 resourceType=13或I3，或者有isPublicFree标记
        if (($ft === '2' || $ft === '3') && ($rt === '13' || $rt === 'I3')) {
            return 'targeted';
        }
        
        if (!empty($pkg['isPublicFree']) || $rt === '13' || $rt === 'I3') {
            return 'targeted';
        }
        
        // 默认归类为通用
        return 'common';
    }
    
    /**
     * 生成完整流量桶（包含聚合桶）
     * @param array $buckets 基础6个桶
     * @return array 9个桶
     */
    public function generateFullBuckets($buckets) {
        $fullBuckets = $buckets;
        
        $commonKeys = ['common_limited', 'common_unlimited', 'regional_limited', 'regional_unlimited'];
        $targetedKeys = ['targeted_limited', 'targeted_unlimited'];
        
        // 计算聚合桶
        $fullBuckets['所有通用'] = $this->calculateAggregate($buckets, $commonKeys);
        $fullBuckets['所有免流'] = $this->calculateAggregate($buckets, $targetedKeys);
        $fullBuckets['所有流量'] = [
            'total' => $fullBuckets['所有通用']['total'] + $fullBuckets['所有免流']['total'],
            'used' => $fullBuckets['所有通用']['used'] + $fullBuckets['所有免流']['used'],
            'remain' => $fullBuckets['所有通用']['remain'] + $fullBuckets['所有免流']['remain']
        ];
        
        return $fullBuckets;
    }
    
    /**
     * 计算聚合桶
     * @param array $buckets 流量桶数据
     * @param array $keys 要聚合的键
     * @return array ['total' => float, 'used' => float, 'remain' => float]
     */
    private function calculateAggregate($buckets, $keys) {
        $result = ['total' => 0, 'used' => 0, 'remain' => 0];
        
        foreach ($keys as $key) {
            if (isset($buckets[$key])) {
                $bucket = $buckets[$key];
                
                // 检查是否为无限流量桶（total=0 且 remain<0）
                if ($bucket['total'] == 0 && $bucket['remain'] < 0) {
                    // 无限流量桶：只累加已用量，不影响total和remain
                    $result['used'] += $bucket['used'];
                } else {
                    // 有限流量桶：正常累加所有字段
                    $result['total'] += $bucket['total'];
                    $result['used'] += $bucket['used'];
                    $result['remain'] += $bucket['remain'];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 计算流量差值统计（完全复刻JS逻辑）
     * 注意：差值统计使用 uused（避免与 used 冲突）
     * @param array $currentBuckets 当前流量桶数据
     * @param array|null $previousStats 上次查询统计
     * @return array 差值统计数据
     */
    public function calculateDiff($currentBuckets, $previousStats = null) {
        $diffStats = [];
        
        // 初始化差值统计
        foreach (self::BUCKET_BASE as $key) {
            $diffStats[$key] = ['uused' => 0, 'today' => 0];  // 使用 uused 而不是 used
        }
        
        if ($previousStats && isset($previousStats['timestamp'])) {
            $now = new \DateTime();
            $today = $now->format('Y-m-d');
            $lastTime = new \DateTime($previousStats['timestamp']);
            $lastDate = $lastTime->format('Y-m-d');
            
            // 检查是否跨月
            $currentMonth = $now->format('Y') * 12 + (int)$now->format('m');
            $previousMonth = $lastTime->format('Y') * 12 + (int)$lastTime->format('m');
            $isCrossMonth = $currentMonth > $previousMonth;
            
            foreach (self::BUCKET_BASE as $key) {
                $current = $currentBuckets[$key] ?? ['used' => 0];
                $previous = $previousStats['buckets'][$key] ?? ['used' => 0];
                
                // 如果跨月，直接使用当前已用量作为本次用量（因为流量会重置）
                // 否则计算差值
                if ($isCrossMonth) {
                    $diffStats[$key]['uused'] = $current['used'];
                    $diffStats[$key]['today'] = $current['used'];
                } else {
                    $diffStats[$key]['uused'] = max(0, $current['used'] - $previous['used']);
                    
                    // 如果是同一天，累加今日用量；否则重置为本次用量
                    $diffStats[$key]['today'] = ($today === $lastDate)
                        ? ($previousStats['diff'][$key]['today'] ?? 0) + $diffStats[$key]['uused']
                        : $diffStats[$key]['uused'];
                }
            }
        } else {
            // 首次运行，直接使用当前已用量
            foreach (self::BUCKET_BASE as $key) {
                $current = $currentBuckets[$key] ?? ['used' => 0];
                $diffStats[$key]['uused'] = $current['used'];
                $diffStats[$key]['today'] = $current['used'];
            }
        }
        
        // 计算聚合桶的差值
        $commonKeys = ['common_limited', 'common_unlimited', 'regional_limited', 'regional_unlimited'];
        $targetedKeys = ['targeted_limited', 'targeted_unlimited'];
        
        $diffStats['所有通用'] = $this->sumDiff($diffStats, $commonKeys);
        $diffStats['所有免流'] = $this->sumDiff($diffStats, $targetedKeys);
        $diffStats['所有流量'] = [
            'uused' => $diffStats['所有通用']['uused'] + $diffStats['所有免流']['uused'],
            'today' => $diffStats['所有通用']['today'] + $diffStats['所有免流']['today']
        ];
        
        return $diffStats;
    }
    
    /**
     * 求和差值
     * @param array $diff 差值数据
     * @param array $keys 要求和的键
     * @return array ['uused' => float, 'today' => float]
     */
    private function sumDiff($diff, $keys) {
        $result = ['uused' => 0, 'today' => 0];
        foreach ($keys as $key) {
            if (isset($diff[$key])) {
                $result['uused'] += $diff[$key]['uused'];
                $result['today'] += $diff[$key]['today'];
            }
        }
        return $result;
    }
    
    /**
     * 格式化流量显示（完全复刻JS逻辑）
     * @param float|int $value 流量值（MB）
     * @return string 格式化后的字符串（如 "1.5G"）
     */
    public static function formatFlow($value) {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return '0';
        }
        
        $num = floatval($value);
        
        if (!is_finite($num) || $num < 0) {
            return '0';
        }
        
        $units = ['M', 'G', 'T', 'P'];
        $unitIndex = 0;
        
        while ($num >= 1024 && $unitIndex < count($units) - 1) {
            $num /= 1024;
            $unitIndex++;
        }
        
        // 去除小数点后无意义的0，如 "1.00G" -> "1G"
        return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.') . $units[$unitIndex];
    }
    
    /**
     * 构建通知占位符
     * @param array $fullBuckets 完整流量桶数据（9个桶）
     * @param array $diffStats 差值统计数据
     * @param string $mainPackage 主套餐名称
     * @param string $timeInterval 时间间隔
     * @return array 占位符数组
     */
    public function buildPlaceholders($fullBuckets, $diffStats, $mainPackage, $timeInterval = '') {
        $placeholders = [
            '[套餐]' => $mainPackage,
            '[时长]' => $timeInterval,
            '[时间]' => date('H:i:s'),
            '[日期]' => date('Y-m-d')
        ];
        
        // 所有9个桶（6个基础 + 3个聚合）
        $allBuckets = array_merge(self::BUCKET_BASE, self::BUCKET_AGGREGATE);
        
        foreach ($allBuckets as $key) {
            $bucket = $fullBuckets[$key] ?? ['total' => 0, 'used' => 0, 'remain' => 0];
            $diff = $diffStats[$key] ?? ['uused' => 0, 'today' => 0];
            $name = self::BUCKET_NAMES[$key];
            
            // 判断是否为无限流量
            $isUnlimited = ($bucket['total'] == 0 && strpos($key, 'unlimited') !== false);
            
            $placeholders["[{$name}.总量]"] = $isUnlimited ? '无限' : self::formatFlow($bucket['total']);
            $placeholders["[{$name}.已用]"] = self::formatFlow($bucket['used']);
            $placeholders["[{$name}.剩余]"] = $isUnlimited ? '无限' : self::formatFlow($bucket['remain']);
            $placeholders["[{$name}.用量]"] = self::formatFlow($diff['uused']);  // 注意这里使用 uused
            $placeholders["[{$name}.今日用量]"] = self::formatFlow($diff['today']);
        }
        
        return $placeholders;
    }
    
    /**
     * 应用占位符到文本
     * @param string $text 原始文本
     * @param array $placeholders 占位符数组
     * @return string 替换后的文本
     */
    public function applyPlaceholders($text, $placeholders) {
        foreach ($placeholders as $key => $value) {
            $text = str_replace($key, $value, $text);
        }
        return $text;
    }
}
