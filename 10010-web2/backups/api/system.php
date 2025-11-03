<?php
/**
 * 系统管理统一接口
 * 整合：cleanup.php、system_check.php、site_config.php、stats.php
 *
 * GET ?action=check - 系统自检
 * GET ?action=config - 获取网站配置
 * GET ?action=stats&token=xxx - 获取用户统计数据
 *
 * POST action=cleanup_orphan - 清理孤立文件夹
 * POST action=create_missing - 创建缺失文件夹
 * POST action=update_config - 更新网站配置
 * POST action=reset_stats&token=xxx - 重置用户统计
 */

session_start();

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/Config.php';

header('Content-Type: application/json; charset=utf-8');

// 处理 OPTIONS 请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $db = Database::getInstance();

    // ==================== GET 请求 ====================
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        // 1. 系统自检（来自 system_check.php）
        if ($action === 'check') {
            $dataDir = __DIR__ . '/../data';
            $orphanFolders = [];
            $missingFolders = [];

            // 获取所有token
            $result = $db->query("SELECT mobile, access_token FROM users");
            $tokens = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $tokens[] = $row['access_token'];
            }

            // 检查孤立文件夹（有文件夹但无数据库记录）
            if (is_dir($dataDir)) {
                $folders = array_diff(scandir($dataDir), ['.', '..']);
                foreach ($folders as $folder) {
                    $folderPath = $dataDir . '/' . $folder;
                    if (is_dir($folderPath) && !in_array($folder, $tokens)) {
                        $size = getDirectorySize($folderPath);
                        $orphanFolders[] = [
                            'token' => $folder,
                            'path' => $folderPath,
                            'size' => $size,
                            'size_formatted' => formatBytes($size)
                        ];
                    }
                }
            }

            // 检查缺失文件夹（有数据库记录但无文件夹）
            foreach ($tokens as $token) {
                $folderPath = $dataDir . '/' . $token;
                if (!is_dir($folderPath)) {
                    $result = $db->query("SELECT mobile FROM users WHERE access_token = '{$token}'");
                    $row = $result->fetchArray(SQLITE3_ASSOC);
                    $missingFolders[] = [
                        'token' => $token,
                        'mobile' => $row['mobile'] ?? 'unknown',
                        'path' => $folderPath
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'orphan_folders' => $orphanFolders,
                    'missing_folders' => $missingFolders,
                    'total_orphan' => count($orphanFolders),
                    'total_missing' => count($missingFolders)
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 2. 获取网站配置（来自 site_config.php）
        if ($action === 'config') {
            $siteMode = $db->querySingle("SELECT value FROM site_config WHERE key = 'site_mode'");

            if ($siteMode === null || $siteMode === false) {
                // 如果没有配置，插入默认值
                $stmt = $db->prepare("INSERT OR REPLACE INTO site_config (key, value) VALUES ('site_mode', 'public')");
                $stmt->execute();
                $siteMode = 'public';
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'site_mode' => $siteMode
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 3. 获取用户统计数据（来自 stats.php）
        if ($action === 'stats') {
            $token = $_GET['token'] ?? '';

            if (!$token) {
                echo json_encode(['success' => false, 'message' => '缺少token参数'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $userDataDir = __DIR__ . '/../data/' . $token;
            $statsFile = $userDataDir . '/stats.json';

            if (file_exists($statsFile)) {
                $stats = json_decode(file_get_contents($statsFile), true);
                echo json_encode(['success' => true, 'data' => $stats], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => true, 'data' => null], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        echo json_encode(['success' => false, 'message' => '无效的action参数'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ==================== POST 请求 ====================
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? $_POST['action'] ?? '';

        // 1. 清理孤立文件夹（来自 cleanup.php）
        if ($action === 'cleanup_orphan') {
            $tokens = $input['tokens'] ?? [];

            if (empty($tokens) || !is_array($tokens)) {
                echo json_encode(['success' => false, 'message' => '缺少tokens参数'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $deletedCount = 0;
            $failedTokens = [];
            $dataDir = __DIR__ . '/../data';

            foreach ($tokens as $token) {
                // 安全检查：确保token格式正确
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $token)) {
                    $failedTokens[] = $token;
                    continue;
                }

                $folderPath = $dataDir . '/' . $token;

                // 安全检查：确保路径在data目录内
                $realPath = realpath($folderPath);
                if ($realPath === false || strpos($realPath, realpath($dataDir)) !== 0) {
                    $failedTokens[] = $token;
                    continue;
                }

                if (is_dir($folderPath)) {
                    if (deleteDirectory($folderPath)) {
                        $deletedCount++;
                    } else {
                        $failedTokens[] = $token;
                    }
                }
            }

            $message = "成功删除 {$deletedCount} 个孤立文件夹";
            if (!empty($failedTokens)) {
                $message .= "，失败 " . count($failedTokens) . " 个";
            }

            echo json_encode([
                'success' => true,
                'message' => $message,
                'deleted' => $deletedCount,
                'failed' => $failedTokens
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 2. 创建缺失文件夹（来自 cleanup.php）
        if ($action === 'create_missing') {
            $tokens = $input['tokens'] ?? [];

            if (empty($tokens) || !is_array($tokens)) {
                echo json_encode(['success' => false, 'message' => '缺少tokens参数'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $createdCount = 0;
            $failedTokens = [];
            $dataDir = __DIR__ . '/../data';

            foreach ($tokens as $token) {
                // 安全检查：确保token格式正确
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $token)) {
                    $failedTokens[] = $token;
                    continue;
                }

                $folderPath = $dataDir . '/' . $token;

                if (!is_dir($folderPath)) {
                    if (mkdir($folderPath, 0755, true)) {
                        $createdCount++;
                    } else {
                        $failedTokens[] = $token;
                    }
                }
            }

            $message = "成功创建 {$createdCount} 个缺失文件夹";
            if (!empty($failedTokens)) {
                $message .= "，失败 " . count($failedTokens) . " 个";
            }

            echo json_encode([
                'success' => true,
                'message' => $message,
                'created' => $createdCount,
                'failed' => $failedTokens
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 3. 更新网站配置（来自 site_config.php）
        if ($action === 'update_config') {
            if (!Admin::check()) {
                echo json_encode(['success' => false, 'message' => '未登录'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $siteMode = $input['site_mode'] ?? '';

            if (!in_array($siteMode, ['public', 'private'])) {
                echo json_encode(['success' => false, 'message' => '无效的运营模式'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $db->prepare("INSERT OR REPLACE INTO site_config (key, value, updated_at) VALUES ('site_mode', :value, datetime('now'))");
            $stmt->bindValue(':value', $siteMode, SQLITE3_TEXT);

            if ($stmt->execute()) {
                $modeText = $siteMode === 'public' ? '公开注册' : '私有模式（需要激活码）';
                echo json_encode([
                    'success' => true,
                    'message' => "已切换到{$modeText}"
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => '设置失败'], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        // 4. 重置用户统计数据（来自 stats.php）
        if ($action === 'reset_stats') {
            $token = $input['token'] ?? $_GET['token'] ?? '';

            if (!$token) {
                echo json_encode(['success' => false, 'message' => '缺少token参数'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $userDataDir = __DIR__ . '/../data/' . $token;
            $statsFile = $userDataDir . '/stats.json';

            if (!file_exists($statsFile)) {
                echo json_encode(['success' => false, 'message' => '暂无统计数据'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 读取当前stats
            $stats = json_decode(file_get_contents($statsFile), true);

            // 重置diff：used=0, today保留
            foreach ($stats['diff'] as $key => &$diff) {
                $diff['used'] = 0;
            }

            // 更新时间和统计周期开始时间
            $stats['timestamp'] = date('c');
            $stats['date'] = date('Y-m-d H:i:s');
            $stats['stats_start_time'] = date('c'); // 重置统计周期开始时间

            // 保存
            file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            echo json_encode(['success' => true, 'message' => '重置成功'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['success' => false, 'message' => '无效的action参数'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'message' => '不支持的请求方法'], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '服务器错误：' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== 辅助函数 ====================

/**
 * 递归删除目录
 */
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }

    return rmdir($dir);
}

/**
 * 获取目录大小
 */
function getDirectorySize($dir) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

/**
 * 格式化字节大小
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}
