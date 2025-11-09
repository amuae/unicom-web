<?php
/**
 * 为已存在的用户生成token
 */

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', str_replace('App\\', 'app/', $class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Models\User;

$userModel = new User();
$users = $userModel->findAll();

$updated = 0;
foreach ($users as $user) {
    if (empty($user['token'])) {
        // 生成24位token
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $token = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < 24; $i++) {
            $token .= $characters[random_int(0, $max)];
        }
        
        // 确保唯一性
        $existing = $userModel->findByToken($token);
        while ($existing) {
            $token = '';
            for ($i = 0; $i < 24; $i++) {
                $token .= $characters[random_int(0, $max)];
            }
            $existing = $userModel->findByToken($token);
        }
        
        $userModel->update($user['id'], ['token' => $token]);
        $updated++;
        echo "User {$user['mobile']} token: $token\n";
    }
}

echo "\n已为 $updated 个用户生成token\n";
