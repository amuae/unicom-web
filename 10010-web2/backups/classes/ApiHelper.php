<?php
/**
 * API辅助类
 * 提供API通用功能，减少重复代码
 */

class ApiHelper {
    
    /**
     * 初始化API响应（设置JSON头）
     */
    public static function init() {
        header('Content-Type: application/json; charset=utf-8');
        
        // 处理 OPTIONS 请求
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * 获取请求数据
     * @return array
     */
    public static function getInput() {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
    
    /**
     * 发送JSON响应并退出
     * @param array $data 响应数据
     * @param int $code HTTP状态码
     */
    public static function response($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 成功响应
     * @param mixed $data 数据
     * @param string $message 消息
     */
    public static function success($data = [], $message = '操作成功') {
        self::response([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * 错误响应
     * @param string $message 错误消息
     * @param int $code HTTP状态码
     * @param mixed $data 额外数据
     */
    public static function error($message, $code = 400, $data = []) {
        self::response([
            'success' => false,
            'message' => $message,
            'data' => $data
        ], $code);
    }
    
    /**
     * 验证必填参数
     * @param array $input 输入数据
     * @param array $required 必填字段列表
     * @return bool 如果缺少必填字段则直接返回错误响应并退出
     */
    public static function requireParams($input, $required) {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            self::error('缺少必填参数: ' . implode(', ', $missing), 400);
        }
        
        return true;
    }
    
    /**
     * 验证Token并获取用户
     * @param string|null $token Token字符串
     * @return User|null
     */
    public static function getUserByToken($token = null) {
        if ($token === null) {
            $token = $_GET['token'] ?? '';
        }
        
        if (empty($token)) {
            self::error('缺少token参数', 400);
        }
        
        require_once __DIR__ . '/User.php';
        $user = User::findByToken($token);
        
        if (!$user) {
            self::error('用户不存在或未激活', 404);
        }
        
        return $user;
    }
    
    /**
     * 检查管理员登录
     * @return bool
     */
    public static function checkAdmin() {
        require_once __DIR__ . '/Admin.php';
        
        if (!Admin::check()) {
            self::error('未登录或登录已过期', 401);
        }
        
        return true;
    }
    
    /**
     * 验证手机号格式
     * @param string $mobile 手机号
     * @return bool
     */
    public static function validateMobile($mobile) {
        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            self::error('手机号格式不正确', 400);
        }
        return true;
    }
    
    /**
     * 验证认证类型
     * @param string $authType 认证类型
     * @return bool
     */
    public static function validateAuthType($authType) {
        if (!in_array($authType, ['full', 'cookie'])) {
            self::error('无效的认证类型', 400);
        }
        return true;
    }
    
    /**
     * 获取请求方法
     * @return string
     */
    public static function getMethod() {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    /**
     * 检查请求方法
     * @param string $expected 期望的方法
     */
    public static function checkMethod($expected) {
        if (self::getMethod() !== strtoupper($expected)) {
            self::error('不支持的请求方法', 405);
        }
    }
    
    /**
     * 安全读取JSON文件
     * @param string $filePath 文件路径
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function readJsonFile($filePath, $default = null) {
        if (!file_exists($filePath)) {
            return $default;
        }
        
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        
        return $data !== null ? $data : $default;
    }
    
    /**
     * 安全写入JSON文件
     * @param string $filePath 文件路径
     * @param mixed $data 数据
     * @return bool
     */
    public static function writeJsonFile($filePath, $data) {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return file_put_contents($filePath, $json) !== false;
    }
    
    /**
     * 记录API错误日志
     * @param string $message 错误消息
     * @param array $context 上下文信息
     */
    public static function logError($message, $context = []) {
        $logMessage = date('[Y-m-d H:i:s] ') . $message;
        if (!empty($context)) {
            $logMessage .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        error_log($logMessage);
    }
}
