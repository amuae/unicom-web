<?php
namespace App\Services;

use App\Models\User;
use App\Utils\Logger;
use Exception;

/**
 * 查询业务服务类
 * 整合 UnicomService 和 QueryLog，处理完整的查询业务流程
 */
class QueryService {
    private $unicomService;
    private $userModel;
    
    public function __construct() {
        $this->unicomService = new UnicomService();
        $this->userModel = new User();
    }
    
    /**
     * 通过 Token 验证并获取用户信息
     * @param string $token 访问令牌
     * @return array ['success' => bool, 'user' => array|null, 'message' => string]
     */
    public function validateToken($token) {
        if (empty($token)) {
            return ['success' => false, 'message' => '缺少访问令牌'];
        }
        
        if (strlen($token) !== 24) {
            return ['success' => false, 'message' => '无效的访问令牌格式'];
        }
        
        $user = $this->userModel->findByToken($token);
        
        if (!$user) {
            return ['success' => false, 'message' => '用户不存在'];
        }
        
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => '用户已被禁用'];
        }
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * 处理 Token 查询请求
     * @param string $token 访问令牌
     * @return array 查询结果
     */
    public function handleTokenQuery($token) {
        // 验证 token
        $validation = $this->validateToken($token);
        if (!$validation['success']) {
            return $validation;
        }
        
        $user = $validation['user'];
        
        try {
            // 执行查询
            $queryResult = $this->queryUserFlow($user['id']);
            
            if ($queryResult['success']) {
                Logger::query('Token查询成功', 'info', [
                    'user_id' => $user['id'],
                    'mobile' => $user['mobile'],
                    'token' => substr($token, 0, 6) . '...'
                ]);
                
                return [
                    'success' => true,
                    'data' => $queryResult['data']
                ];
            } else {
                Logger::query('Token查询失败', 'error', [
                    'user_id' => $user['id'],
                    'mobile' => $user['mobile'],
                    'error' => $queryResult['message']
                ]);
                
                return [
                    'success' => false,
                    'message' => $queryResult['message']
                ];
            }
        } catch (Exception $e) {
            Logger::error('Token查询异常', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => '查询失败：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 处理重置基准请求
     * @param string $token 访问令牌
     * @return array 操作结果
     */
    public function handleResetBaseline($token) {
        // 验证 token
        $validation = $this->validateToken($token);
        if (!$validation['success']) {
            return $validation;
        }
        
        $user = $validation['user'];
        
        try {
            // 执行查询并保存到 last_query_data
            $queryResult = $this->queryUserFlow($user['id'], true);
            
            if ($queryResult['success']) {
                Logger::query('重置基准成功', 'info', [
                    'user_id' => $user['id'],
                    'mobile' => $user['mobile'],
                    'token' => substr($token, 0, 6) . '...'
                ]);
                
                return [
                    'success' => true,
                    'message' => '基准已重置',
                    'data' => $queryResult['data']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $queryResult['message']
                ];
            }
        } catch (Exception $e) {
            Logger::error('重置基准异常', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => '重置失败：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 执行用户流量查询
     * @param int $userId 用户ID
     * @param bool $saveToLast 是否保存到 last_query_data（重置按钮使用）
     * @return array 查询结果
     */
    public function queryUserFlow($userId, $saveToLast = false) {
        try {
            // 获取用户信息
            $user = $this->userModel->find($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => '用户不存在'
                ];
            }
            
            if ($user['status'] !== 'active') {
                return [
                    'success' => false,
                    'message' => '用户已被禁用'
                ];
            }
            
            Logger::query('开始查询流量和余额', 'info', [
                'user_id' => $userId,
                'mobile' => $user['mobile']
            ]);
            
            // 获取Cookie和流量数据
            $result = $this->unicomService->getCookieAndFlow($user);
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'message' => '获取流量数据失败'
                ];
            }
            
            // 并发查询余额（使用相同的Cookie）
            $balanceData = null;
            try {
                $balanceData = $this->unicomService->queryBalance($result['cookie']);
                Logger::query('查询余额成功', 'info', [
                    'user_id' => $userId,
                    'balance' => $balanceData['balance'] ?? null,
                    'realFee' => $balanceData['realFee'] ?? null
                ]);
            } catch (Exception $e) {
                Logger::query('查询余额失败', 'warning', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                // 余额查询失败不影响流量查询，继续执行
            }
            
            // 分析流量数据
            $analyzed = $this->unicomService->analyze($user['mobile'], $result['data']);
            
            // 生成完整流量桶（9个桶）
            $fullBuckets = $this->unicomService->generateFullBuckets($analyzed['buckets']);
            
            // 获取上次查询数据（用于计算差值统计）
            $previousStats = null;
            if (!empty($user['last_query_data'])) {
                $previousStats = json_decode($user['last_query_data'], true);
            }
            
            // 使用 UnicomService 的方法计算差值统计
            $diffStats = $this->unicomService->calculateDiff($fullBuckets, $previousStats);
            
            // 计算时间间隔
            $timeInterval = $this->calculateTimeInterval($user['last_query_time']);
            
            // 准备完整的查询结果
            $queryResult = [
                'timestamp' => date('Y-m-d H:i:s'),
                'mainPackage' => $analyzed['mainPackage'],
                'packages' => $analyzed['packages'],
                'buckets' => $fullBuckets,
                'diff' => $diffStats,
                'timeInterval' => $timeInterval,
                'balance' => $balanceData,  // 添加余额数据
                'cookie' => $result['cookie'],  // 添加本次查询使用的Cookie
                'needUpdateCookie' => $result['needUpdateCookie']
            ];
            
            // 判断是否需要保存数据
            $isTodayFirstQuery = $this->isTodayFirstQuery($user); // 今日首次查询
            $isMonthFirstQuery = $this->isMonthFirstQuery($user); // 本月首次查询
            
            // 准备更新数据
            $updateData = [];
            
            // 如果需要更新Cookie
            if ($result['needUpdateCookie']) {
                $updateData['cookie'] = $result['cookie'];
            }
            
            // 每日首次查询：保存到 today_query_data
            if ($isTodayFirstQuery) {
                $updateData['today_query_data'] = json_encode($queryResult);
                Logger::query('每日首次查询，保存到 today_query_data', 'info', [
                    'user_id' => $userId
                ]);
            }
            
            // 每月首次查询或重置按钮：保存到 last_query_data 和 last_query_time
            if ($isMonthFirstQuery || $saveToLast) {
                $updateData['last_query_data'] = json_encode($queryResult);
                $updateData['last_query_time'] = date('Y-m-d H:i:s');
                Logger::query($saveToLast ? '重置基准，保存到 last_query_data' : '每月首次查询，保存到 last_query_data', 'info', [
                    'user_id' => $userId
                ]);
            }
            
            // 检查是否达到通知阈值（仅用于日志记录，不发送通知）
            $threshold = $user['notify_threshold'] ?? 0;
            if ($threshold > 0) {
                $generalUsed = $diffStats['所有通用']['uused'] ?? 0;
                if ($generalUsed >= $threshold) {
                    // 达到阈值：保存到 last_query_data 和 last_query_time（覆盖前面的判断）
                    $updateData['last_query_data'] = json_encode($queryResult);
                    $updateData['last_query_time'] = date('Y-m-d H:i:s');
                    Logger::query('达到通知阈值，保存到 last_query_data', 'info', [
                        'user_id' => $userId,
                        'used' => $generalUsed,
                        'threshold' => $threshold
                    ]);
                }
            }
            
            // 总是更新最后查询时间戳
            $updateData['last_query_at'] = date('Y-m-d H:i:s');
            $updateData['updated_at'] = time();
            
            // 执行更新
            if (!empty($updateData)) {
                $this->userModel->update($userId, $updateData);
            }
            
            Logger::query('查询流量成功', 'info', [
                'user_id' => $userId,
                'mobile' => $user['mobile'],
                'main_package' => $analyzed['mainPackage'],
                'is_today_first' => $isTodayFirstQuery,
                'is_month_first' => $isMonthFirstQuery,
                'save_to_last' => $saveToLast
            ]);
            
            return [
                'success' => true,
                'data' => $queryResult
            ];
            
        } catch (Exception $e) {
            Logger::query('查询流量失败', 'error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 计算时间间隔
     * @param string|null $lastQueryTime 上次查询时间
     * @return string 时间间隔描述
     */
    private function calculateTimeInterval($lastQueryTime) {
        if (!$lastQueryTime) {
            return '首次查询';
        }
        
        $lastTimestamp = strtotime($lastQueryTime);
        $currentTimestamp = time();
        $diffSeconds = $currentTimestamp - $lastTimestamp;
        
        if ($diffSeconds < 60) {
            return $diffSeconds . '秒';
        } elseif ($diffSeconds < 3600) {
            $minutes = floor($diffSeconds / 60);
            return $minutes . '分钟';
        } elseif ($diffSeconds < 86400) {
            $hours = floor($diffSeconds / 3600);
            $minutes = floor(($diffSeconds % 3600) / 60);
            return $hours . '小时' . ($minutes > 0 ? $minutes . '分钟' : '');
        } else {
            $days = floor($diffSeconds / 86400);
            $hours = floor(($diffSeconds % 86400) / 3600);
            return $days . '天' . ($hours > 0 ? $hours . '小时' : '');
        }
    }
    
    /**
     * 判断是否为本月首次查询
     * @param array $user 用户信息
     * @return bool
     */
    private function isMonthFirstQuery($user) {
        if (empty($user['last_query_time'])) {
            return true;
        }
        
        $lastQueryMonth = date('Y-m', strtotime($user['last_query_time']));
        $currentMonth = date('Y-m');
        
        return $lastQueryMonth !== $currentMonth;
    }
    
    /**
     * 判断是否为今日首次查询
     * @param array $user 用户信息
     * @return bool
     */
    private function isTodayFirstQuery($user) {
        if (empty($user['last_query_at'])) {
            return true;
        }
        
        $lastQueryDate = date('Y-m-d', strtotime($user['last_query_at']));
        $todayDate = date('Y-m-d');
        
        return $lastQueryDate !== $todayDate;
    }
    
    /**
     * 获取用户最后一次查询结果
     * @param int $userId 用户ID
     * @return array|null 查询结果或null
     */
    public function getLastQueryResult($userId) {
        $user = $this->userModel->find($userId);
        if (!$user || empty($user['last_query_data'])) {
            return null;
        }
        
        return json_decode($user['last_query_data'], true);
    }
    
    /**
     * 构建通知内容
     * @param int $userId 用户ID
     * @param array $queryResult 查询结果
     * @param array $notifyTemplate 通知模板
     * @return array ['title' => string, 'content' => string]
     */
    public function buildNotifyContent($userId, $queryResult, $notifyTemplate) {
        $placeholders = $this->unicomService->buildPlaceholders(
            $queryResult['buckets'],
            $queryResult['diff'],
            $queryResult['mainPackage'],
            $queryResult['timeInterval']
        );
        
        $title = $notifyTemplate['title'] ?? '[套餐] 流量使用通知';
        $content = $notifyTemplate['content'] ?? "套餐：[套餐]\n时间：[时间]\n\n所有流量：[所有流量.已用] / [所有流量.总量]\n本次用量：[所有流量.用量]\n今日用量：[所有流量.今日用量]";
        
        return [
            'title' => $this->unicomService->applyPlaceholders($title, $placeholders),
            'content' => $this->unicomService->applyPlaceholders($content, $placeholders)
        ];
    }
    
    /**
     * 检查是否需要发送通知
     * @param array $queryResult 查询结果
     * @param int $threshold 通知阈值（MB）
     * @return bool 是否需要通知
     */
    public function shouldNotify($queryResult, $threshold = 0) {
        if ($threshold <= 0) {
            return true;
        }
        
        $commonUsage = $queryResult['diff']['所有通用']['uused'] ?? 0;
        return $commonUsage >= $threshold;
    }
    
    /**
     * 获取用户配置信息
     * @param string $token 访问令牌
     * @return array 用户配置信息
     */
    public function getUserConfig($token) {
        $validation = $this->validateToken($token);
        if (!$validation['success']) {
            return $validation;
        }
        
        $user = $validation['user'];
        
        // 解析通知参数
        $notifyParams = [];
        if (!empty($user['notify_params'])) {
            $notifyParams = json_decode($user['notify_params'], true) ?: [];
        }
        
        return [
            'success' => true,
            'data' => [
                'user_id' => $user['id'],
                'mobile' => $user['mobile'],
                'nickname' => $user['nickname'],
                'query_password' => $user['query_password'],
                'auth_type' => $user['auth_type'],
                'appid' => $user['appid'],
                'token_online' => $user['token_online'],
                'cookie' => $user['cookie'],
                'token' => $user['token'],
                'notify_enabled' => (int)$user['notify_enabled'],
                'notify_type' => $user['notify_type'],
                'notify_params' => $notifyParams,
                'notify_title' => $user['notify_title'],
                'notify_subtitle' => $user['notify_subtitle'],
                'notify_content' => $user['notify_content'],
                'notify_threshold' => (int)$user['notify_threshold'],
                'query_interval' => (int)$user['query_interval']
            ]
        ];
    }
    
    /**
     * 保存通知配置
     * @param string $token 访问令牌
     * @param array $config 通知配置
     * @return array 操作结果
     */
    public function saveNotifyConfig($token, $config) {
        $validation = $this->validateToken($token);
        if (!$validation['success']) {
            return $validation;
        }
        
        $user = $validation['user'];
        
        try {
            $notifyEnabled = (int)($config['notify_enabled'] ?? 0);
            $notifyThreshold = (int)($config['notify_threshold'] ?? 0);
            $queryInterval = max(5, (int)($config['query_interval'] ?? 30)); // 最小5分钟
            
            $updateData = [
                'notify_enabled' => $notifyEnabled,
                'notify_type' => $config['notify_type'] ?? '',
                'notify_params' => json_encode($config['notify_params'] ?? []),
                'notify_title' => $config['notify_title'] ?? '联通流量提醒',
                'notify_subtitle' => $config['notify_subtitle'] ?? '',
                'notify_content' => $config['notify_content'] ?? '',
                'notify_threshold' => $notifyThreshold,
                'query_interval' => $queryInterval,
                'updated_at' => time()
            ];
            
            $this->userModel->update($user['id'], $updateData);
            
            // 引入定时任务服务
            require_once dirname(__DIR__) . '/Services/CronService.php';
            $cronService = new \App\Services\CronService();
            
            // 验证四个条件是否全部满足
            // 条件一：通知配置完整（通知类型和通知参数都不为空）
            $notifyParams = is_array($config['notify_params']) ? $config['notify_params'] : json_decode($config['notify_params'] ?? '[]', true);
            $hasNotifyConfig = !empty($config['notify_type']) && !empty($notifyParams);
            
            // 条件二：通知阈值已配置（大于0）
            $hasThreshold = $notifyThreshold > 0;
            
            // 条件三：查询频率已配置（大于等于5分钟）
            $hasQueryInterval = $queryInterval >= 5;
            
            // 条件四：启用通知
            $isNotifyEnabled = $notifyEnabled === 1;
            
            // 判断是否满足所有条件
            $allConditionsMet = $hasNotifyConfig && $hasThreshold && $hasQueryInterval && $isNotifyEnabled;
            
            if ($allConditionsMet) {
                // 四个条件全满足：创建或覆盖定时任务（确保一个用户只有一个任务）
                $cronResult = $cronService->createOrUpdateUserQueryTask(
                    $user['id'],
                    $user['token'],
                    $queryInterval
                );
                
                if (!$cronResult['success']) {
                    Logger::error('创建/更新系统Cron任务失败', [
                        'user_id' => $user['id'],
                        'mobile' => $user['mobile'],
                        'error' => $cronResult['message']
                    ]);
                } else {
                    Logger::system('系统Cron任务已创建/更新', 'info', [
                        'user_id' => $user['id'],
                        'mobile' => $user['mobile'],
                        'interval' => $queryInterval . '分钟',
                        'notify_type' => $config['notify_type']
                    ]);
                }
            } else {
                // 只要有一个不满足：删除该用户的定时任务
                $deleteResult = $cronService->deleteUserQueryTask($user['id']);
                
                // 记录删除原因
                $reasons = [];
                if (!$hasNotifyConfig) $reasons[] = '通知配置不完整';
                if (!$hasThreshold) $reasons[] = '通知阈值未配置';
                if (!$hasQueryInterval) $reasons[] = '查询频率未配置或小于5分钟';
                if (!$isNotifyEnabled) $reasons[] = '通知未启用';
                
                Logger::system('系统Cron任务已删除', 'info', [
                    'user_id' => $user['id'],
                    'mobile' => $user['mobile'],
                    'reason' => implode('、', $reasons)
                ]);
            }
            
            Logger::system('保存通知配置', 'info', [
                'user_id' => $user['id'],
                'mobile' => $user['mobile'],
                'notify_type' => $updateData['notify_type'],
                'notify_enabled' => $notifyEnabled,
                'threshold' => $notifyThreshold,
                'interval' => $queryInterval,
                'cron_task' => $allConditionsMet ? '✅ 已创建/更新' : '❌ 已删除',
                'conditions' => [
                    '通知配置完整' => $hasNotifyConfig ? '✓' : '✗',
                    '通知阈值已配置' => $hasThreshold ? '✓' : '✗',
                    '查询频率已配置' => $hasQueryInterval ? '✓' : '✗',
                    '启用通知' => $isNotifyEnabled ? '✓' : '✗'
                ]
            ]);
            
            return [
                'success' => true,
                'message' => '通知配置已保存',
                'cron_task' => $allConditionsMet ? 'created' : 'deleted',
                'cron_info' => $allConditionsMet ? 
                    "定时任务已创建，每 {$queryInterval} 分钟自动查询一次" : 
                    "定时任务已删除（" . implode('、', $reasons ?? []) . "）"
            ];
        } catch (Exception $e) {
            Logger::error('保存通知配置失败', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => '保存失败：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 测试通知发送
     * @param string $token 访问令牌
     * @param array $config 通知配置
     * @return array 测试结果
     */
    public function testNotify($token, $config) {
        $validation = $this->validateToken($token);
        if (!$validation['success']) {
            return $validation;
        }
        
        $user = $validation['user'];
        
        try {
            // 获取通知渠道配置
            $notifyType = $config['notify_type'] ?? '';
            $notifyParams = $config['notify_params'] ?? [];
            
            if (empty($notifyType)) {
                return [
                    'success' => false,
                    'message' => '请选择通知方式'
                ];
            }
            
            // 验证必要参数
            $requiredParams = $this->getRequiredNotifyParams($notifyType);
            $missingParams = [];
            
            foreach ($requiredParams as $param) {
                if (empty($notifyParams[$param])) {
                    $missingParams[] = $param;
                }
            }
            
            if (!empty($missingParams)) {
                return [
                    'success' => false,
                    'message' => '请填写必需参数: ' . implode(', ', $missingParams)
                ];
            }
            
            // 先执行一次真实查询获取用户流量数据
            Logger::system('测试通知：开始查询用户流量数据', 'info', [
                'user_id' => $user['id'],
                'mobile' => $user['mobile']
            ]);
            
            $queryResult = $this->queryUserFlow($user['id'], false); // 不保存到last_query_data
            
            if (!$queryResult['success']) {
                return [
                    'success' => false,
                    'message' => '无法获取流量数据：' . $queryResult['message']
                ];
            }
            
            $flowData = $queryResult['data'];
            
            // 使用真实的流量数据构建占位符
            $placeholders = $this->unicomService->buildPlaceholders(
                $flowData['buckets'],
                $flowData['diff'],
                $flowData['mainPackage'],
                $flowData['timeInterval']
            );
            
            // 构建测试通知内容
            $title = $config['notify_title'] ?? '联通流量提醒（测试）';
            $subtitle = $config['notify_subtitle'] ?? '';
            $content = $config['notify_content'] ?? '这是一条测试通知';
            
            // 替换占位符
            $title = $this->unicomService->applyPlaceholders($title, $placeholders);
            $subtitle = $this->unicomService->applyPlaceholders($subtitle, $placeholders);
            $content = $this->unicomService->applyPlaceholders($content, $placeholders);
            
            // 如果有副标题，将其添加到内容前面
            $fullContent = $content;
            if (!empty($subtitle)) {
                $fullContent = $subtitle . "\n" . $content;
            }
            
            // 准备通知配置
            $notifyConfig = [
                'type' => $notifyType,
                'params' => $notifyParams
            ];
            
            // 调用 NotifyService 发送测试通知
            $result = NotifyService::send(
                $title, 
                $fullContent, 
                $notifyConfig,
                [
                    'user_id' => $user['id'],
                    'mobile' => $user['mobile'],
                    'test' => true
                ]
            );
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error('通知测试异常', [
                'user_id' => $user['id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => '测试失败：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取通知渠道必需参数
     * @param string $notifyType 通知类型
     * @return array 必需参数列表
     */
    private function getRequiredNotifyParams($notifyType) {
        $required = [
            'telegram' => ['bot_token', 'chat_id'],
            'wecom' => ['webhook'],
            'serverchan' => ['key'],
            'dingtalk' => ['webhook'],
            'pushplus' => ['token']
        ];
        
        return $required[$notifyType] ?? [];
    }
    
    /**
     * 保存用户配置
     * @param string $token 访问令牌
     * @param array $config 用户配置
     * @return array 操作结果
     */
    public function saveUserConfig($token, $config) {
        $validation = $this->validateToken($token);
        if (!$validation['success']) {
            return $validation;
        }
        
        $user = $validation['user'];
        
        try {
            $updateData = [];
            
            // 允许更新的字段
            if (isset($config['nickname'])) {
                $updateData['nickname'] = trim($config['nickname']);
            }
            
            if (isset($config['query_password']) && !empty($config['query_password'])) {
                $updateData['query_password'] = $config['query_password'];
            }
            
            // cookie 方式用户可以更新 cookie
            if ($user['auth_type'] === 'cookie' && isset($config['cookie'])) {
                $updateData['cookie'] = trim($config['cookie']);
            }
            
            if (!empty($updateData)) {
                $this->userModel->update($user['id'], $updateData);
                
                Logger::system('保存用户配置', 'info', [
                    'user_id' => $user['id'],
                    'mobile' => $user['mobile'],
                    'updated_fields' => array_keys($updateData)
                ]);
            }
            
            return [
                'success' => true,
                'message' => '用户配置已保存'
            ];
        } catch (Exception $e) {
            Logger::error('保存用户配置失败', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => '保存失败：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 删除用户账户
     * @param string $token 访问令牌
     * @return array 操作结果
     */
    public function deleteUser($token) {
        $validation = $this->validateToken($token);
        if (!$validation['success']) {
            return $validation;
        }
        
        $user = $validation['user'];
        
        try {
            $this->userModel->delete($user['id']);
            
            Logger::system('用户删除账户', 'warning', [
                'user_id' => $user['id'],
                'mobile' => $user['mobile']
            ]);
            
            return [
                'success' => true,
                'message' => '账户已删除'
            ];
        } catch (Exception $e) {
            Logger::error('删除用户失败', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ];
        }
    }
}

