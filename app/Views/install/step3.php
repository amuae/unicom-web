<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装 - 步骤3</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>⚙️ 系统安装向导</h1>
        <p class="text-center text-muted mb-20">步骤 3/4 - 创建管理员账号</p>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
            
            <?php if (isset($critical) && $critical): ?>
                <div style="background: #fff3cd; padding: 16px; border-radius: 8px; margin: 16px 0; border-left: 4px solid #ffc107;">
                    <strong>⚠️ 严重错误</strong><br>
                    由于创建管理员账号失败，系统已自动回滚整个安装过程。<br>
                    数据库文件已被删除，请重新开始安装。
                </div>
                
                <a href="/install.php?step=1" class="btn btn-primary btn-full mt-20">
                    重新开始安装
                </a>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (!isset($critical) || !$critical): ?>
        
        <form method="POST" action="/install.php?action=saveAdmin">
            <div class="form-group">
                <label for="username">管理员用户名</label>
                <input type="text" id="username" name="username" required 
                       placeholder="建议使用字母+数字组合" minlength="4" maxlength="20">
            </div>
            
            <div class="form-group">
                <label for="password">管理员密码</label>
                <input type="password" id="password" name="password" required 
                       placeholder="至少8位，建议包含字母、数字、符号" minlength="8">
            </div>
            
            <div class="form-group">
                <label for="password_confirm">确认密码</label>
                <input type="password" id="password_confirm" name="password_confirm" required 
                       placeholder="再次输入密码" minlength="8">
            </div>
            
            <button type="submit" class="btn btn-primary btn-full">
                创建账号并继续 →
            </button>
        </form>
        <?php endif; ?>
    </div>
    
    <script src="/assets/js/app.js"></script>
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirm').value;
            
            if (password !== confirm) {
                e.preventDefault();
                showMessage('两次输入的密码不一致', 'error');
            }
        });
    </script>
</body>
</html>
