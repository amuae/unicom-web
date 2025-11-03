<?php
/**
 * 系统管理统一接口
 * 整合：cleanup.php、system_check.php、site_config.php、stats.php
 */

session_start();

require_once __DIR__ . '/../classes/ApiHelper.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/Config.php';

ApiHelper::init();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance();
$dataDir = __DIR__ . '/../data';

// GET请求
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'check') {
        $tokens = [];
        $result = $db->query("SELECT mobile, access_token FROM users");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tokens[] = $row['access_token'];
        }

        $orphanFolders = [];
        $missingFolders = [];

        if (is_dir($dataDir)) {
            foreach (array_diff(scandir($dataDir), ['.', '..']) as $folder) {
                $folderPath = "$dataDir/$folder";
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

        foreach ($tokens as $token) {
            $folderPath = "$dataDir/$token";
            if (!is_dir($folderPath)) {
                $result = $db->query("SELECT mobile FROM users WHERE access_token = '$token'");
                $row = $result->fetchArray(SQLITE3_ASSOC);
                $missingFolders[] = [
                    'token' => $token,
                    'mobile' => $row['mobile'] ?? 'unknown',
                    'path' => $folderPath
                ];
            }
        }

        // 获取用户统计
        $totalUsers = $db->querySingle("SELECT COUNT(*) FROM users");
        $totalFolders = is_dir($dataDir) ? count(array_diff(scandir($dataDir), ['.', '..'])) : 0;
        
        // 计算健康状态
        $orphanCount = count($orphanFolders);
        $missingCount = count($missingFolders);
        $healthStatus = ($orphanCount == 0 && $missingCount == 0) ? 'healthy' : 'warning';

        ApiHelper::success([
            'stats' => [
                'total_users' => $totalUsers,
                'total_folders' => $totalFolders,
                'orphan_count' => $orphanCount,
                'missing_count' => $missingCount,
                'health_status' => $healthStatus
            ],
            'issues' => [
                'orphan_folders' => $orphanFolders,
                'missing_folders' => $missingFolders
            ],
            // 保持向后兼容
            'orphan_folders' => $orphanFolders,
            'missing_folders' => $missingFolders,
            'total_orphan' => $orphanCount,
            'total_missing' => $missingCount
        ]);
    }

    if ($action === 'config') {
        $siteMode = $db->querySingle("SELECT value FROM site_config WHERE key = 'site_mode'") ?: 'public';
        
        if (!$siteMode) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO site_config (key, value) VALUES ('site_mode', 'public')");
            $stmt->execute();
            $siteMode = 'public';
        }

        ApiHelper::success(['site_mode' => $siteMode]);
    }

    if ($action === 'stats') {
        $token = $_GET['token'] ?? '';
        ApiHelper::requireParams(['token' => $token], ['token']);
        
        $statsFile = "$dataDir/$token/stats.json";
        $stats = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : null;
        
        ApiHelper::success($stats);
    }

    ApiHelper::error('无效的action参数');
}

// POST请求
$input = ApiHelper::getInput();
$action = $input['action'] ?? '';

if ($action === 'cleanup_orphan') {
    ApiHelper::requireParams($input, ['tokens']);
    
    $deletedCount = 0;
    $failedTokens = [];

    foreach ($input['tokens'] as $token) {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $token)) {
            $failedTokens[] = $token;
            continue;
        }

        $folderPath = "$dataDir/$token";
        $realPath = realpath($folderPath);
        
        if ($realPath === false || strpos($realPath, realpath($dataDir)) !== 0) {
            $failedTokens[] = $token;
            continue;
        }

        if (is_dir($folderPath)) {
            deleteDirectory($folderPath) ? $deletedCount++ : $failedTokens[] = $token;
        }
    }

    $message = "成功删除 {$deletedCount} 个孤立文件夹";
    if (!empty($failedTokens)) {
        $message .= "，失败 " . count($failedTokens) . " 个";
    }

    ApiHelper::success(['deleted' => $deletedCount, 'failed' => $failedTokens], $message);
}

if ($action === 'create_missing') {
    ApiHelper::requireParams($input, ['tokens']);
    
    $createdCount = 0;
    $failedTokens = [];

    foreach ($input['tokens'] as $token) {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $token)) {
            $failedTokens[] = $token;
            continue;
        }

        $folderPath = "$dataDir/$token";
        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0755, true) ? $createdCount++ : $failedTokens[] = $token;
        }
    }

    $message = "成功创建 {$createdCount} 个缺失文件夹";
    if (!empty($failedTokens)) {
        $message .= "，失败 " . count($failedTokens) . " 个";
    }

    ApiHelper::success(['created' => $createdCount, 'failed' => $failedTokens], $message);
}

if ($action === 'update_config') {
    ApiHelper::checkAdmin();
    ApiHelper::requireParams($input, ['site_mode']);
    
    if (!in_array($input['site_mode'], ['public', 'private'])) {
        ApiHelper::error('无效的运营模式');
    }

    $stmt = $db->prepare("INSERT OR REPLACE INTO site_config (key, value, updated_at) VALUES ('site_mode', :value, datetime('now'))");
    $stmt->bindValue(':value', $input['site_mode'], SQLITE3_TEXT);
    
    $modeText = $input['site_mode'] === 'public' ? '公开注册' : '私有模式（需要激活码）';
    ApiHelper::success(null, $stmt->execute() ? "已切换到{$modeText}" : '设置失败');
}

if ($action === 'reset_stats') {
    $token = $input['token'] ?? $_GET['token'] ?? '';
    ApiHelper::requireParams(['token' => $token], ['token']);
    
    $statsFile = "$dataDir/$token/stats.json";
    if (!file_exists($statsFile)) {
        ApiHelper::error('暂无统计数据');
    }

    $stats = json_decode(file_get_contents($statsFile), true);
    foreach ($stats['diff'] as &$diff) {
        $diff['used'] = 0;
    }
    
    $stats['timestamp'] = date('c');
    $stats['date'] = date('Y-m-d H:i:s');
    $stats['stats_start_time'] = date('c');

    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    ApiHelper::success(null, '重置成功');
}

ApiHelper::error('无效的action参数');

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }

    return rmdir($dir);
}

function getDirectorySize($dir) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}
