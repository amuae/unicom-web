<?php
/**
 * 管理后台入口文件
 * 处理后台管理请求
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

use App\Controllers\AdminController;
use App\Utils\Helper;

try {
    if (!Helper::isInstalled()) {
        header('Location: /install.php');
        exit;
    }
    
    $controller = new AdminController();
    $action = $_GET['action'] ?? 'login';
    
    // 路由分发
    switch ($action) {
        // 登录相关
        case 'login':
            $controller->login();
            break;
            
        case 'doLogin':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->doLogin();
            }
            break;
            
        case 'logout':
            $controller->logout();
            break;
            
        // 用户管理
        case 'users':
            $controller->users();
            break;
            
        case 'createUser':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->createUser();
            }
            break;
            
        case 'getUser':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $controller->getUser();
            }
            break;
            
        case 'updateUser':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->updateUser();
            }
            break;
            
        case 'addUser':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->addUser();
            }
            break;
            
        case 'deleteUser':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->deleteUser();
            }
            break;
            
        case 'queryUserFlow':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->queryUserFlow();
            }
            break;
            
        // 邀请码管理
        case 'inviteCodes':
            $controller->inviteCodes();
            break;
            
        case 'generateInviteCodes':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->generateInviteCodes();
            }
            break;
            
        case 'updateInviteStatus':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->updateInviteStatus();
            }
            break;
            
        case 'updateInviteMaxUsage':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->updateInviteMaxUsage();
            }
            break;
            
        case 'deleteInviteCode':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->deleteInviteCode();
            }
            break;
            
        case 'batchUpdateInviteStatus':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->batchUpdateInviteStatus();
            }
            break;
            
        case 'batchDeleteInviteCodes':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->batchDeleteInviteCodes();
            }
            break;
            
        // 定时任务
        case 'cronTasks':
            $controller->cronTasks();
            break;
            
        case 'createCronTask':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->createCronTask();
            }
            break;
            
        case 'updateCronTaskStatus':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->updateCronTaskStatus();
            }
            break;
            
        case 'runCronTask':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->runCronTask();
            }
            break;
            
        case 'deleteCronTask':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->deleteCronTask();
            }
            break;
            
        case 'deleteUserCronTask':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->deleteUserCronTask();
            }
            break;
            
        case 'getSystemCrontab':
            $controller->getSystemCrontab();
            break;
            
        // 日志查看
        case 'logs':
            $controller->logs();
            break;
            
        case 'getLog':
            $controller->getLog();
            break;
            
        case 'cleanLogs':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->cleanLogs();
            }
            break;
            
        // 系统设置
        case 'settings':
            $controller->settings();
            break;
            
        case 'saveSettings':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->saveSettings();
            }
            break;
            
        case 'changePassword':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->changePassword();
            }
            break;
            
        case 'testNotify':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->testNotify();
            }
            break;
            
        case 'backupDatabase':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->backupDatabase();
            }
            break;
            
        case 'clearCache':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->clearCache();
            }
            break;
            
        case 'clearAllData':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->clearAllData();
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
