<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? '管理后台' ?> - 10010</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body style="background: #f5f7fa; display: block; padding: 0;">
    <div class="admin-header">
        <div>
            <h2 style="margin: 0;">📊 10010 管理后台</h2>
        </div>
        <div class="admin-nav">
            <a href="admin.php?action=users" class="<?= ($current ?? '') === 'users' ? 'active' : '' ?>">用户管理</a>
            <a href="admin.php?action=inviteCodes" class="<?= ($current ?? '') === 'invites' ? 'active' : '' ?>">邀请码</a>
            <a href="admin.php?action=cronTasks" class="<?= ($current ?? '') === 'cron' ? 'active' : '' ?>">定时任务</a>
            <a href="admin.php?action=logs" class="<?= ($current ?? '') === 'logs' ? 'active' : '' ?>">日志</a>
            <a href="admin.php?action=settings" class="<?= ($current ?? '') === 'settings' ? 'active' : '' ?>">设置</a>
            <a href="admin.php?action=logout" style="color: #ef4444;">退出</a>
        </div>
    </div>
    
    <div class="admin-content">
