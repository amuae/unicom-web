<?php
/**
 * 网站首页入口文件
 * 可用于展示首页、导航等
 */

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 自动加载
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

use App\Controllers\IndexController;
use App\Utils\Helper;

try {
    // 检查是否已安装
    if (!Helper::isInstalled()) {
        header('Location: /install.php');
        exit;
    }
    
    $controller = new IndexController();
    $action = $_GET['action'] ?? 'index';
    
    // 路由分发
    switch ($action) {
        case 'index':
            $controller->index();
            break;
            
        case 'queryToken':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->queryToken();
            } else {
                http_response_code(405);
                Helper::error('Method not allowed');
            }
            break;
            
        case 'register':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->register();
            } else {
                http_response_code(405);
                Helper::error('Method not allowed');
            }
            break;
            
        default:
            http_response_code(404);
            echo '404 Not Found';
    }
} catch (Exception $e) {
    http_response_code(500);
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        Helper::error('Server error: ' . $e->getMessage());
    } else {
        echo '<h1>500 Internal Server Error</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}
