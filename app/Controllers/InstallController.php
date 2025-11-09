<?php
namespace App\Controllers;

use App\Models\Database;
use App\Utils\Helper;
use App\Utils\Logger;

class InstallController {
    
    private function render($view, $data = []) {
        extract($data);
        $viewPath = dirname(__DIR__) . "/Views/install/{$view}.php";
        if (!file_exists($viewPath)) {
            die("View not found: {$view}");
        }
        include $viewPath;
    }
    
    public function index() {
        if (Helper::isInstalled()) {
            die('系统已安装完成。<br>如需重新安装，请删除 <code>storage/install.lock</code> 文件。<br><a href="/admin.php">进入管理后台</a>');
        }
        
        $step = intval($_GET['step'] ?? 1);
        
        // 验证步骤顺序
        if (!$this->validateStep($step)) {
            header('Location: /install.php?step=1');
            exit;
        }
        
        switch ($step) {
            case 1:
                $this->step1();
                break;
            case 2:
                $this->step2();
                break;
            case 3:
                $this->step3();
                break;
            case 4:
                $this->step4();
                break;
            default:
                $this->step1();
        }
    }
    
    /**
     * 验证步骤是否可访问
     */
    private function validateStep($step) {
        $dbPath = dirname(__DIR__, 2) . '/database/unicom_flow.db';
        
        switch ($step) {
            case 1:
                // 步骤1始终可访问
                return true;
                
            case 2:
                // 步骤2始终可访问（用于初始化数据库）
                return true;
                
            case 3:
                // 步骤3需要数据库已初始化
                return file_exists($dbPath);
                
            case 4:
                // 步骤4需要数据库已初始化且有管理员
                if (!file_exists($dbPath)) {
                    return false;
                }
                try {
                    $pdo = new \PDO('sqlite:' . $dbPath);
                    $count = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
                    return $count > 0;
                } catch (\Exception $e) {
                    return false;
                }
                
            default:
                return false;
        }
    }
    
    private function step1() {
        $rootPath = dirname(__DIR__, 2);
        $storagePath = $rootPath . '/storage';
        $databasePath = $rootPath . '/database';
        $scriptsPath = $rootPath . '/scripts';
        $cronPath = $scriptsPath . '/cron';
        
        // 确保目录存在
        if (!is_dir($storagePath)) {
            @mkdir($storagePath, 0755, true);
        }
        if (!is_dir($databasePath)) {
            @mkdir($databasePath, 0755, true);
        }
        if (!is_dir($cronPath)) {
            @mkdir($cronPath, 0755, true);
        }
        
        $checks = [
            // PHP环境检查
            [
                'name' => 'PHP 版本 >= 7.4',
                'passed' => version_compare(PHP_VERSION, '7.4.0', '>='),
                'detail' => '当前版本: ' . PHP_VERSION,
                'category' => 'php'
            ],
            [
                'name' => 'PDO 扩展',
                'passed' => extension_loaded('pdo'),
                'detail' => extension_loaded('pdo') ? '已安装' : '未安装',
                'category' => 'php'
            ],
            [
                'name' => 'PDO SQLite 扩展',
                'passed' => extension_loaded('pdo_sqlite'),
                'detail' => extension_loaded('pdo_sqlite') ? '已安装' : '未安装',
                'category' => 'php'
            ],
            [
                'name' => 'cURL 扩展',
                'passed' => extension_loaded('curl'),
                'detail' => extension_loaded('curl') ? '已安装' : '未安装',
                'category' => 'php'
            ],
            [
                'name' => 'JSON 扩展',
                'passed' => extension_loaded('json'),
                'detail' => extension_loaded('json') ? '已安装' : '未安装',
                'category' => 'php'
            ],
            
            // 目录权限检查
            [
                'name' => 'storage 目录可写',
                'passed' => is_writable($storagePath),
                'detail' => is_writable($storagePath) ? '可写' : '不可写',
                'category' => 'permission'
            ],
            [
                'name' => 'database 目录可写',
                'passed' => is_writable($databasePath),
                'detail' => is_writable($databasePath) ? '可写' : '不可写',
                'category' => 'permission'
            ],
            [
                'name' => 'scripts/cron 目录可写',
                'passed' => is_writable($cronPath),
                'detail' => is_writable($cronPath) ? '可写' : '不可写',
                'category' => 'permission'
            ],
            
            // 文件检查
            [
                'name' => 'schema.sql 文件存在',
                'passed' => file_exists($databasePath . '/schema.sql'),
                'detail' => file_exists($databasePath . '/schema.sql') ? '存在' : '不存在',
                'category' => 'file'
            ],
            
            // 系统功能检查
            [
                'name' => 'shell_exec 函数可用',
                'passed' => function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions'))),
                'detail' => (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) ? '可用（定时任务需要）' : '不可用',
                'category' => 'system'
            ],
            [
                'name' => 'sudo 命令可用',
                'passed' => !empty(shell_exec('which sudo 2>/dev/null')),
                'detail' => !empty(shell_exec('which sudo 2>/dev/null')) ? '可用（定时任务需要）' : '不可用',
                'category' => 'system'
            ]
        ];
        
        // 检查是否所有检查都通过
        $canContinue = true;
        foreach ($checks as $check) {
            if (!$check['passed']) {
                $canContinue = false;
                break;
            }
        }
        
        $this->render('step1', [
            'checks' => $checks,
            'can_continue' => $canContinue
        ]);
    }
    
    private function step2() {
        $dbPath = dirname(__DIR__, 2) . '/database/unicom_flow.db';
        $schemaPath = dirname(__DIR__, 2) . '/database/schema.sql';
        $status = 'success';
        $error = '';
        
        try {
            if (file_exists($dbPath)) {
                // 如果数据库已存在，跳过初始化
                $status = 'success';
            } else {
                // 创建数据库目录
                $dbDir = dirname($dbPath);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }
                
                // 创建数据库连接
                $pdo = new \PDO('sqlite:' . $dbPath);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                
                // 读取并执行schema.sql
                if (!file_exists($schemaPath)) {
                    throw new \Exception('数据库schema文件不存在');
                }
                
                $schema = file_get_contents($schemaPath);
                $pdo->exec($schema);
                
                // 验证关键表是否创建成功
                $requiredTables = ['users', 'admins', 'invite_codes', 'query_logs', 'system_config'];
                $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
                
                foreach ($requiredTables as $table) {
                    if (!in_array($table, $tables)) {
                        throw new \Exception("必需的数据表 {$table} 创建失败");
                    }
                }
                
                $status = 'success';
            }
        } catch (\Exception $e) {
            $status = 'failed';
            $error = $e->getMessage();
            
            // 回滚：删除创建的数据库文件
            if (file_exists($dbPath)) {
                @unlink($dbPath);
            }
            
            Logger::system("数据库初始化失败: {$error}", 'error');
        }
        
        $this->render('step2', [
            'status' => $status,
            'error' => $error
        ]);
    }
    
    private function step3() {
        $this->render('step3');
    }
    
    public function saveAdmin() {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $this->render('step3', ['error' => '用户名和密码不能为空']);
            return;
        }
        
        // 验证用户名格式
        if (strlen($username) < 4 || strlen($username) > 20) {
            $this->render('step3', ['error' => '用户名长度必须在4-20个字符之间']);
            return;
        }
        
        // 验证密码强度
        if (strlen($password) < 8) {
            $this->render('step3', ['error' => '密码长度至少8位']);
            return;
        }
        
        try {
            // 检查数据库是否已初始化
            $dbPath = dirname(__DIR__, 2) . '/database/unicom_flow.db';
            if (!file_exists($dbPath)) {
                throw new \Exception('数据库未初始化，请返回上一步');
            }
            
            $adminModel = new \App\Models\Admin();
            
            // 检查是否已有管理员
            $existingAdmin = $adminModel->findByUsername($username);
            if ($existingAdmin) {
                throw new \Exception('该用户名已存在');
            }
            
            // 创建管理员
            $adminModel->create([
                'username' => $username,
                'password' => Helper::hashPassword($password),
                'real_name' => '',
                'email' => ''
            ]);
            
            Logger::system("安装流程：创建管理员账号成功 - {$username}", 'info');
            
            // 重定向到step4
            header('Location: /install.php?step=4');
            exit;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            Logger::system("安装流程：创建管理员失败 - {$error}", 'error');
            
            // 如果创建管理员失败，回滚整个安装
            $this->rollbackInstallation();
            
            $this->render('step3', [
                'error' => '创建管理员失败: ' . $error . '<br>安装已回滚，请重新开始安装流程。',
                'critical' => true
            ]);
        }
    }
    
    /**
     * 回滚安装
     */
    private function rollbackInstallation() {
        $dbPath = dirname(__DIR__, 2) . '/database/unicom_flow.db';
        $lockFile = dirname(__DIR__, 2) . '/storage/install.lock';
        
        // 删除数据库文件
        if (file_exists($dbPath)) {
            @unlink($dbPath);
        }
        
        // 删除安装锁
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
        
        Logger::system('安装流程回滚完成', 'warning');
    }
    
    private function step4() {
        try {
            // 验证数据库是否完整
            $dbPath = dirname(__DIR__, 2) . '/database/unicom_flow.db';
            if (!file_exists($dbPath)) {
                throw new \Exception('数据库文件不存在');
            }
            
            // 验证管理员是否创建成功
            $pdo = new \PDO('sqlite:' . $dbPath);
            $count = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
            if ($count < 1) {
                throw new \Exception('未找到管理员账号');
            }
            
            // 设置文件权限
            $this->setFilePermissions();
            
            // 设置安装锁
            Helper::setInstallLock();
            Logger::system('系统安装完成', 'info');
            
            $this->render('step4');
        } catch (\Exception $e) {
            $error = $e->getMessage();
            Logger::system("安装流程验证失败: {$error}", 'error');
            
            // 回滚安装
            $this->rollbackInstallation();
            
            $this->render('step3', [
                'error' => '安装验证失败: ' . $error . '<br>安装已回滚，请重新开始安装流程。',
                'critical' => true
            ]);
        }
    }
    
    /**
     * 设置文件权限
     * 确保定时任务脚本、数据库等关键文件具有正确的权限
     */
    private function setFilePermissions() {
        $rootPath = dirname(__DIR__, 2);
        
        // 需要设置权限的目录和文件
        $permissionItems = [
            // 数据库目录和文件
            [
                'path' => $rootPath . '/database',
                'type' => 'dir',
                'mode' => 0755,
                'description' => '数据库目录'
            ],
            [
                'path' => $rootPath . '/database/unicom_flow.db',
                'type' => 'file',
                'mode' => 0664,
                'description' => '数据库文件'
            ],
            
            // 存储目录
            [
                'path' => $rootPath . '/storage',
                'type' => 'dir',
                'mode' => 0755,
                'description' => '存储目录'
            ],
            [
                'path' => $rootPath . '/storage/logs',
                'type' => 'dir',
                'mode' => 0755,
                'description' => '日志目录'
            ],
            
            // 定时任务脚本目录
            [
                'path' => $rootPath . '/scripts',
                'type' => 'dir',
                'mode' => 0755,
                'description' => '脚本目录'
            ],
            [
                'path' => $rootPath . '/scripts/cron',
                'type' => 'dir',
                'mode' => 0755,
                'description' => '定时任务脚本目录'
            ],
        ];
        
        // 定时任务脚本文件（需要可执行权限）
        $cronScripts = [
            'query_single_user.php',
            'daily_report.php',
            'clean_logs.php',
            'stats_flow.php'
        ];
        
        foreach ($cronScripts as $script) {
            $permissionItems[] = [
                'path' => $rootPath . '/scripts/cron/' . $script,
                'type' => 'file',
                'mode' => 0755,
                'description' => "定时任务脚本: {$script}"
            ];
        }
        
        $errors = [];
        $success = [];
        
        foreach ($permissionItems as $item) {
            $path = $item['path'];
            $mode = $item['mode'];
            $description = $item['description'];
            
            // 检查文件/目录是否存在
            if (!file_exists($path)) {
                if ($item['type'] === 'dir') {
                    // 如果是目录且不存在，尝试创建
                    if (@mkdir($path, $mode, true)) {
                        $success[] = "创建{$description}: {$path}";
                        Logger::system("创建目录: {$path} (权限: " . decoct($mode) . ")", 'info');
                    } else {
                        $errors[] = "无法创建{$description}: {$path}";
                    }
                } else {
                    // 文件不存在，跳过（某些脚本可能还没创建）
                    Logger::system("跳过不存在的文件: {$path}", 'warning');
                    continue;
                }
            }
            
            // 设置权限
            if (@chmod($path, $mode)) {
                $success[] = "设置{$description}权限: " . decoct($mode);
                Logger::system("设置权限: {$path} → " . decoct($mode), 'info');
            } else {
                $errors[] = "无法设置{$description}权限: {$path}";
                Logger::system("权限设置失败: {$path}", 'warning');
            }
        }
        
        // 尝试设置数据库文件所有者为Web服务器用户
        $webUser = $this->getWebServerUser();
        if ($webUser && file_exists($rootPath . '/database/unicom_flow.db')) {
            $dbFile = $rootPath . '/database/unicom_flow.db';
            $chownCmd = "sudo chown {$webUser}:{$webUser} {$dbFile} 2>&1";
            $output = @shell_exec($chownCmd);
            
            if ($output === null || strpos($output, 'error') === false) {
                $success[] = "设置数据库文件所有者: {$webUser}";
                Logger::system("设置数据库文件所有者: {$webUser}", 'info');
            } else {
                Logger::system("设置数据库文件所有者失败（可能需要手动执行）: {$chownCmd}", 'warning');
            }
        }
        
        // 记录结果
        if (!empty($success)) {
            Logger::system("文件权限设置完成: " . implode(', ', $success), 'info');
        }
        
        if (!empty($errors)) {
            Logger::system("部分权限设置失败: " . implode(', ', $errors), 'warning');
            // 不抛出异常，只记录警告
        }
        
        return true;
    }
    
    /**
     * 检测Web服务器用户
     */
    private function getWebServerUser() {
        // 常见的Web服务器用户名
        $commonUsers = ['www-data', 'apache', 'nginx', 'httpd', 'nobody'];
        
        // 首先尝试从当前进程获取
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $processUser = posix_getpwuid(posix_geteuid());
            if (isset($processUser['name'])) {
                return $processUser['name'];
            }
        }
        
        // 尝试通过环境变量获取
        $envUser = getenv('USER') ?: getenv('APACHE_RUN_USER');
        if ($envUser && in_array($envUser, $commonUsers)) {
            return $envUser;
        }
        
        // 检查常见用户是否存在
        foreach ($commonUsers as $user) {
            $output = @shell_exec("id -u {$user} 2>/dev/null");
            if ($output !== null && is_numeric(trim($output))) {
                return $user;
            }
        }
        
        return 'www-data'; // 默认返回www-data
    }
}
