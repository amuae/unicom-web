<?php
/**
 * 管理员入口 - 自动路由到登录页或管理面板
 */

session_start();

// 检查是否已登录
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // 已登录，跳转到管理面板
    header('Location: /views/admin_panel.php');
    exit;
} else {
    // 未登录，跳转到登录页面
    header('Location: /views/admin_login.php');
    exit;
}
