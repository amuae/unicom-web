<?php
/**
 * 安装向导入口文件
 * 首次部署时的安装引导
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Shanghai');

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use App\Controllers\InstallController;
use App\Utils\Helper;

try {
    // 如果已安装，跳转到首页
    if (Helper::isInstalled()) {
        header('Location: /index.php');
        exit;
    }
    
    $controller = new InstallController();
    $action = $_GET['action'] ?? 'index';
    
    // 路由分发
    switch ($action) {
        case 'index':
            $controller->index();
            break;
            
        case 'saveAdmin':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->saveAdmin();
            }
            break;
            
        default:
            $controller->index();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo '<h1>500 Installation Error</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="/install.php">Back to installation</a></p>';
}
