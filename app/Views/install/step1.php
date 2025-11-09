<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装 - 步骤1</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>⚙️ 系统安装向导</h1>
        <p class="text-center text-muted mb-20">步骤 1/4 - 环境检查</p>
        
        <?php if (!empty($checks)): ?>
            <div style="background: #f9fafb; padding: 20px; border-radius: 8px;">
                <?php foreach ($checks as $check): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                        <div style="flex: 1;">
                            <div style="font-weight: 500;"><?= htmlspecialchars($check['name'] ?? '') ?></div>
                            <?php if (!empty($check['detail'])): ?>
                                <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                                    <?= htmlspecialchars($check['detail']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span style="font-weight: 600; color: <?= ($check['passed'] ?? false) ? '#10b981' : '#ef4444' ?>; margin-left: 16px;">
                            <?= ($check['passed'] ?? false) ? '✓ 通过' : '✗ 失败' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($can_continue ?? false): ?>
            <a href="/install.php?step=2" class="btn btn-primary btn-full mt-20">
                下一步 →
            </a>
        <?php else: ?>
            <div class="alert alert-error mt-20">
                环境检查未通过，请先解决上述问题后再继续安装。
            </div>
        <?php endif; ?>
    </div>
    
    <script src="/assets/js/app.js"></script>
</body>
</html>
