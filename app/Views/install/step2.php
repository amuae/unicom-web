<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装 - 步骤2</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>⚙️ 系统安装向导</h1>
        <p class="text-center text-muted mb-20">步骤 2/4 - 数据库初始化</p>
        
        <?php if (($status ?? '') === 'success'): ?>
            <div class="alert alert-success">
                ✓ 数据库初始化成功
            </div>
            
            <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <p>✓ 创建数据库文件</p>
                <p>✓ 创建数据表结构</p>
                <p>✓ 插入初始配置数据</p>
                <p>✓ 验证必需数据表</p>
            </div>
            
            <a href="/install.php?step=3" class="btn btn-primary btn-full mt-20">
                下一步 →
            </a>
        <?php else: ?>
            <div class="alert alert-error">
                ❌ 数据库初始化失败
            </div>
            
            <?php if (!empty($error)): ?>
                <div style="background: #fee; padding: 16px; border-radius: 8px; margin: 16px 0; border-left: 4px solid #f44;">
                    <strong>错误详情：</strong><br>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div style="background: #fff3cd; padding: 16px; border-radius: 8px; margin: 16px 0; border-left: 4px solid #ffc107;">
                <strong>⚠️ 安装已回滚</strong><br>
                数据库初始化失败时，系统会自动删除不完整的数据库文件。<br>
                请检查以下项目：
                <ul style="margin: 8px 0 0 20px;">
                    <li>database 目录是否可写</li>
                    <li>是否安装了 PDO SQLite 扩展</li>
                    <li>schema.sql 文件是否完整</li>
                </ul>
            </div>
            
            <a href="/install.php?step=1" class="btn btn-secondary btn-full mt-20">
                ← 返回环境检查
            </a>
        <?php endif; ?>
    </div>
    
    <script src="/assets/js/app.js"></script>
</body>
</html>
