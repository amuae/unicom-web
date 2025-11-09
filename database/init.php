<?php
/**
 * 数据库初始化脚本
 */

define('ROOT_PATH', dirname(__DIR__));
define('DATABASE_PATH', ROOT_PATH . '/database');
define('DB_FILE', DATABASE_PATH . '/unicom_flow.db');

echo "🚀 开始初始化数据库...\n\n";

// 创建数据库目录
if (!is_dir(DATABASE_PATH)) {
    mkdir(DATABASE_PATH, 0755, true);
    echo "✓ 创建数据库目录\n";
}

// 备份已存在的数据库
if (file_exists(DB_FILE)) {
    $backup = DB_FILE . '.' . date('YmdHis') . '.bak';
    copy(DB_FILE, $backup);
    echo "✓ 备份旧数据库: " . basename($backup) . "\n";
    unlink(DB_FILE);
}

// 创建数据库连接
try {
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ 创建数据库文件\n\n";
    
    // 读取并执行schema.sql
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schema);
    echo "✓ 执行数据表创建\n";
    
    // 验证表
    echo "\n验证数据表:\n";
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo "  ✓ {$table} (记录数: {$count})\n";
    }
    
    echo "\n✅ 数据库初始化完成！\n";
    echo "📁 数据库文件: " . DB_FILE . "\n";
    echo "📊 文件大小: " . round(filesize(DB_FILE) / 1024, 2) . " KB\n";
    echo "\n⚠️  请通过安装向导创建管理员账号\n";
    
} catch (PDOException $e) {
    echo "❌ 数据库初始化失败: " . $e->getMessage() . "\n";
    exit(1);
}
