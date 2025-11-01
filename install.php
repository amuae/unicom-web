<?php
/**
 * 系统安装脚本
 * 初始化数据库和创建默认管理员账号
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 引入必要的类
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Config.php';
require_once __DIR__ . '/classes/Utils.php';

// 处理 POST 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // 检查是否已经安装
        $db = Database::getInstance();
        if ($db->isInitialized()) {
            Utils::error('系统已经安装！', 400);
            exit;
        }
        
        // 获取请求数据
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['action']) || $input['action'] !== 'install') {
            Utils::error('无效的请求', 400);
            exit;
        }
        
        // 验证必填字段
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');
        $email = trim($input['email'] ?? '');
        
        if (empty($username) || strlen($username) < 3) {
            Utils::error('用户名至少3个字符', 400);
            exit;
        }
        
        if (empty($password) || strlen($password) < 6) {
            Utils::error('密码至少6个字符', 400);
            exit;
        }
        
        // 执行安装
        $schemaFile = __DIR__ . '/schema.sql';
        if (!file_exists($schemaFile)) {
            Utils::error('数据库结构文件不存在', 500);
            exit;
        }
        
        $schema = file_get_contents($schemaFile);
        if (!$db->initialize($schema)) {
            Utils::error('数据库初始化失败', 500);
            exit;
        }
        
        // 创建管理员账号
        $stmt = $db->prepare('INSERT INTO admins (username, password, email) VALUES (?, ?, ?)');
        if (!$stmt) {
            Utils::error('创建管理员失败：' . $db->lastErrorMsg(), 500);
            exit;
        }
        
        $hashedPassword = Utils::hashPassword($password);
        $stmt->bindValue(1, $username, SQLITE3_TEXT);
        $stmt->bindValue(2, $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(3, $email ?: null, SQLITE3_TEXT);
        
        if (!$stmt->execute()) {
            Utils::error('创建管理员失败', 500);
            exit;
        }
        
        Utils::success('安装成功！', ['username' => $username]);
        exit;
        
    } catch (Exception $e) {
        Utils::error('安装失败：' . $e->getMessage(), 500);
        exit;
    }
}

// 检查是否已经安装（GET 请求）
$db = Database::getInstance();
if ($db->isInitialized()) {
    die('系统已经安装！如需重新安装，请删除 data/flow_monitor.db 文件后重试。');
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装 - 联通流量监控</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-hint {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .info-box {
            background: #f8f9ff;
            border-left: 4px solid #667eea;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            margin-left: 20px;
            color: #666;
            font-size: 14px;
            line-height: 1.8;
        }
        
        .result {
            display: none;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .result.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .result.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .result h3 {
            margin-bottom: 10px;
        }
        
        .result p {
            line-height: 1.6;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(102, 126, 234, 0.3);
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .disclaimer {
            background: rgba(255, 193, 7, 0.1);
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            margin: 24px 0;
        }

        .disclaimer-title {
            font-size: 18px;
            font-weight: 600;
            color: #f57c00;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .disclaimer ul {
            margin: 10px 0;
            padding-left: 20px;
            color: #666;
        }

        .disclaimer li {
            margin: 8px 0;
            line-height: 1.6;
        }

        .disclaimer .warning-text {
            background: #fff3cd;
            padding: 12px;
            border-radius: 6px;
            margin-top: 12px;
            color: #856404;
            font-weight: 500;
        }

        .watermark {
            text-align: center;
            margin-top: 32px;
            padding: 16px;
            color: #999;
            font-size: 13px;
        }

        .watermark a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .watermark a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>📱 联通流量监控系统</h1>
            <p>系统安装向导</p>
        </div>

        <!-- 免责声明 -->
        <div class="disclaimer">
            <div class="disclaimer-title">
                ⚠️ 重要声明 - 请仔细阅读
            </div>
            <ul>
                <li><strong>本项目仅供学习和技术研究使用</strong>，严禁用于任何商业用途或非法活动。</li>
                <li>使用本项目所产生的<strong>一切后果由使用者自行承担</strong>，与项目作者及贡献者无关。</li>
                <li>本项目不对服务的稳定性、准确性、完整性做任何保证。</li>
                <li>用户数据的安全性由部署者负责，请确保采取适当的安全措施。</li>
                <li>请在充分理解相关技术和法律风险后再决定是否使用本项目。</li>
            </ul>
            <div class="warning-text">
                ⚠️ 继续安装即表示您已阅读、理解并同意上述声明。请在下载后24小时内删除。
            </div>
        </div>
        
        <div class="info-box">
            <h3>💡 安装说明</h3>
            <ul>
                <li>系统将自动创建数据库和必要的数据表</li>
                <li>请设置管理员账号和密码</li>
                <li>安装完成后请妥善保管管理员密码</li>
                <li>建议安装后立即修改默认配置</li>
            </ul>
        </div>
        
        <form id="installForm">
            <div class="form-group">
                <label class="form-label">管理员用户名 *</label>
                <input type="text" class="form-input" name="username" id="username" 
                       value="admin" required minlength="3" maxlength="50">
                <div class="form-hint">3-50个字符，建议使用字母和数字</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">管理员密码 *</label>
                <input type="password" class="form-input" name="password" id="password" 
                       required minlength="6">
                <div class="form-hint">至少6个字符，建议包含字母、数字和特殊字符</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">确认密码 *</label>
                <input type="password" class="form-input" name="password_confirm" 
                       id="password_confirm" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">管理员邮箱（可选）</label>
                <input type="email" class="form-input" name="email" id="email" 
                       placeholder="admin@example.com">
                <div class="form-hint">用于接收系统通知（可选）</div>
            </div>
            
            <button type="submit" class="btn" id="installBtn">开始安装</button>
        </form>
        
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p style="margin-top: 10px; color: #666;">正在安装，请稍候...</p>
        </div>
        
        <div class="result" id="result"></div>
    </div>
    
    <script>
        document.getElementById('installForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            const email = document.getElementById('email').value.trim();
            
            // 验证
            if (username.length < 3) {
                alert('用户名至少3个字符');
                return;
            }
            
            if (password.length < 6) {
                alert('密码至少6个字符');
                return;
            }
            
            if (password !== passwordConfirm) {
                alert('两次密码不一致');
                return;
            }
            
            // 显示加载
            document.getElementById('installForm').style.display = 'none';
            document.getElementById('loading').style.display = 'block';
            
            try {
                const response = await fetch('install.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'install',
                        username: username,
                        password: password,
                        email: email
                    })
                });
                
                const result = await response.json();
                
                document.getElementById('loading').style.display = 'none';
                const resultDiv = document.getElementById('result');
                resultDiv.style.display = 'block';
                
                if (result.success) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <h3>✅ 安装成功！</h3>
                        <p><strong>管理员用户名：</strong>${username}</p>
                        <p><strong>管理员密码：</strong>${password}</p>
                        <p style="margin-top: 10px;">请妥善保管账号密码，现在可以前往管理面板。</p>
                        <button class="btn" style="margin-top: 20px;" onclick="location.href='views/admin_login.html'">
                            前往登录
                        </button>
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `
                        <h3>❌ 安装失败</h3>
                        <p>${result.message || '未知错误'}</p>
                        <button class="btn" style="margin-top: 20px;" onclick="location.reload()">
                            重新安装
                        </button>
                    `;
                }
            } catch (error) {
                document.getElementById('loading').style.display = 'none';
                const resultDiv = document.getElementById('result');
                resultDiv.style.display = 'block';
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <h3>❌ 安装失败</h3>
                    <p>网络错误：${error.message}</p>
                    <button class="btn" style="margin-top: 20px;" onclick="location.reload()">
                        重新安装
                    </button>
                `;
            }
        });
    </script>

    <!-- 水印 -->
    <div class="watermark">
        <p>📦 开源项目 | 代码仓库: <a href="https://github.com/amuae/unicom-web" target="_blank">GitHub: amuae/unicom-web</a></p>
        <p style="margin-top: 8px; font-size: 12px; color: #bbb;">本项目基于开源协议发布，完全免费，仅供学习交流使用</p>
    </div>
</body>
</html>
