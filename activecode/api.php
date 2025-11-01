<?php
/**
 * 激活码管理 API
 */

session_start();

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../activecode/ActivationCode.php';

header('Content-Type: application/json; charset=utf-8');

// 处理 OPTIONS 请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 测试模式：跳过管理员验证
    // 生产环境请取消注释以下代码
    /*
    if (!Admin::check()) {
        echo json_encode(['success' => false, 'message' => '未登录'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    */
    
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($method === 'GET') {
        // 获取激活码列表或统计
        $action = $_GET['action'] ?? 'list';
        
        if ($action === 'list') {
            $result = ActivationCode::getAll();
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } elseif ($action === 'stats') {
            $result = ActivationCode::getStats();
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    if ($method === 'POST') {
        switch ($action) {
            case 'generate':
                // 生成激活码
                $count = intval($input['count'] ?? 1);
                $remark = $input['remark'] ?? '';
                $expiresAt = $input['expires_at'] ?? null;
                
                if ($count < 1 || $count > 100) {
                    echo json_encode(['success' => false, 'message' => '生成数量必须在1-100之间'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $result = ActivationCode::generate($count, $remark, $expiresAt);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                break;
                
            case 'delete':
                // 删除激活码
                $ids = $input['ids'] ?? [];
                
                if (empty($ids)) {
                    echo json_encode(['success' => false, 'message' => '未指定要删除的激活码'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $result = ActivationCode::delete($ids);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                break;
                
            case 'validate':
                // 验证激活码
                $code = $input['code'] ?? '';
                
                if (empty($code)) {
                    echo json_encode(['success' => false, 'message' => '请输入激活码'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $result = ActivationCode::validate($code);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => '未知操作'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => '不支持的请求方法'], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '服务器错误：' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
