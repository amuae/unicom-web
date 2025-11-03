<?php
// 防止直接访问HTML源码
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - 联通流量监控</title>
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
            max-width: 420px;
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
        
        .error-msg {
            display: none;
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #fcc;
        }
        
        .error-msg.show {
            display: block;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 10px;
        }
        
        .spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid rgba(102, 126, 234, 0.3);
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .links {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>🔐 管理员登录</h1>
            <p>联通流量监控系统</p>
        </div>
        
        <div class="error-msg" id="errorMsg"></div>
        
        <form id="loginForm">
            <div class="form-group">
                <label class="form-label">用户名</label>
                <input type="text" class="form-input" name="username" id="username" 
                       required autofocus autocomplete="username">
            </div>
            
            <div class="form-group">
                <label class="form-label">密码</label>
                <input type="password" class="form-input" name="password" id="password" 
                       required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn" id="loginBtn">登录</button>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
            </div>
        </form>
    </div>
    
    <script>
        const API_BASE = '../api';
        
        // 页面加载时检查是否已登录
        window.addEventListener('DOMContentLoaded', async function() {
            try {
                const response = await fetch(`${API_BASE}/admin.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'check' })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // 已登录，直接跳转到管理面板
                    window.location.href = 'admin_panel.php';
                }
            } catch (error) {
                // 检查失败，继续显示登录表单
                console.log('未登录，显示登录表单');
            }
        });
        
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const errorMsg = document.getElementById('errorMsg');
            const loginBtn = document.getElementById('loginBtn');
            const loading = document.getElementById('loading');
            
            // 隐藏错误消息
            errorMsg.classList.remove('show');
            
            // 验证
            if (!username || !password) {
                showError('请输入用户名和密码');
                return;
            }
            
            // 显示加载
            loginBtn.style.display = 'none';
            loading.style.display = 'block';
            
            try {
                const response = await fetch(`${API_BASE}/admin.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'login',
                        username: username,
                        password: password
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // 登录成功，跳转到管理面板
                    window.location.href = 'admin_panel.php';
                } else {
                    showError(result.message || '登录失败');
                    loginBtn.style.display = 'block';
                    loading.style.display = 'none';
                }
            } catch (error) {
                showError('网络错误：' + error.message);
                loginBtn.style.display = 'block';
                loading.style.display = 'none';
            }
        });
        
        function showError(message) {
            const errorMsg = document.getElementById('errorMsg');
            errorMsg.textContent = message;
            errorMsg.classList.add('show');
        }
        
        // 回车键提交
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').dispatchEvent(new Event('submit'));
            }
        });
    </script>
    
    <!-- 开发者工具防护 -->
    <script src="js/anti-devtools.js"></script>
</body>
</html>
