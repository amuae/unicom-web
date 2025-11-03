<?php
/**
 * 用户管理统一接口
 * 支持：GET(获取/列表) POST(创建/更新) DELETE(删除)
 */

require_once __DIR__ . '/../classes/ApiHelper.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/Utils.php';

ApiHelper::init();

$method = $_SERVER['REQUEST_METHOD'];
$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';
$mobile = $_GET['mobile'] ?? '';

// GET请求：获取用户信息或用户列表
if ($method === 'GET') {
    // 1. 通过手机号查询用户
    if ($mobile && !$token && !$action) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT mobile, auth_type, access_token, user_type, status, created_at FROM users WHERE mobile = :mobile");
        $stmt->bindValue(':mobile', $mobile, SQLITE3_TEXT);
        $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$user) ApiHelper::error('用户不存在');

        $protocol = ($_SERVER['HTTPS'] ?? '' === 'on') ? 'https' : 'http';
        $queryUrl = "{$protocol}://{$_SERVER['HTTP_HOST']}/views/index.php?token={$user['access_token']}";

        ApiHelper::success([
            'mobile' => $user['mobile'],
            'auth_type' => $user['auth_type'],
            'user_type' => $user['user_type'] === 'beta' ? '公测用户' : '激活码用户',
            'status' => $user['status'] === 'active' ? '正常' : '已禁用',
            'created_at' => $user['created_at'],
            'query_url' => $queryUrl
        ]);
    }

    // 2. 获取单个用户信息（基于token）
    if ($token && !$action) {
        $user = ApiHelper::getUserByToken($token);
        ApiHelper::success([
            'mobile' => $user->mobile,
            'auth_type' => $user->authType,
            'appid' => $user->appid ?? '',
            'token_online' => $user->tokenOnline ?? '',
            'cookie' => $user->cookie ?? '',
            'status' => $user->status
        ]);
    }

    // 3. 获取用户列表（需要管理员权限）
    if ($action === 'list') {
        ApiHelper::checkAdmin();

        $db = Database::getInstance();
        $result = $db->query("SELECT id, mobile, auth_type, access_token, status, created_at, last_query_at, appid, token_online, cookie, remark FROM users ORDER BY created_at DESC");

        $users = [];
        $protocol = ($_SERVER['HTTPS'] ?? '' === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['access_url'] = "{$protocol}://{$host}/views/index.php?token={$row['access_token']}";
            $row['is_active'] = $row['status'] === 'active' ? 1 : 0;
            $row['appid'] = !empty($row['appid']) ? Utils::decrypt($row['appid']) : '';
            $row['token_online'] = !empty($row['token_online']) ? Utils::decrypt($row['token_online']) : '';
            $row['cookie'] = !empty($row['cookie']) ? Utils::decrypt($row['cookie']) : '';
            $users[] = $row;
        }

        ApiHelper::success($users);
    }
}

// POST请求：更新用户信息或管理操作
if ($method === 'POST') {
    $input = ApiHelper::getInput();

    // 检查是否是管理操作
    if (isset($input['action'])) {
        ApiHelper::checkAdmin();

        switch ($input['action']) {
            case 'add':
                $authType = $input['auth_type'] ?? 'full';
                $mobile = trim($input['mobile'] ?? '');
                $remark = trim($input['remark'] ?? '');

                ApiHelper::requireParams($input, ['mobile']);
                ApiHelper::validateMobile($mobile);

                if ($authType === 'full') {
                    ApiHelper::requireParams($input, ['appid', 'token_online']);
                    $result = User::createWithFullAuth($mobile, trim($input['appid']), trim($input['token_online']), $remark);
                } else {
                    ApiHelper::requireParams($input, ['cookie']);
                    $result = User::createWithCookie($mobile, trim($input['cookie']), $remark);
                }

                ApiHelper::response($result);

            case 'update':
                ApiHelper::requireParams($input, ['user_id', 'auth_type']);
                
                $user = User::findById($input['user_id']);
                if (!$user) ApiHelper::error('用户不存在');

                $db = Database::getInstance();
                $updates = ['auth_type = :auth_type', 'remark = :remark', 'updated_at = :updated_at'];
                $params = [
                    ':auth_type' => $input['auth_type'],
                    ':remark' => trim($input['remark'] ?? ''),
                    ':updated_at' => date('Y-m-d H:i:s'),
                    ':id' => $input['user_id']
                ];

                if ($input['auth_type'] === 'full') {
                    ApiHelper::requireParams($input, ['appid', 'token_online']);
                    $updates[] = 'appid = :appid';
                    $updates[] = 'token_online = :token_online';
                    $updates[] = 'cookie = NULL';
                    $params[':appid'] = Utils::encrypt(trim($input['appid']));
                    $params[':token_online'] = Utils::encrypt(trim($input['token_online']));
                } else {
                    ApiHelper::requireParams($input, ['cookie']);
                    $updates[] = 'cookie = :cookie';
                    $updates[] = 'appid = NULL';
                    $updates[] = 'token_online = NULL';
                    $params[':cookie'] = Utils::encrypt(trim($input['cookie']));
                }

                $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id');
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
                }

                ApiHelper::response($stmt->execute() ? 
                    ['success' => true, 'message' => '更新成功'] : 
                    ['success' => false, 'message' => '更新失败']
                );

            case 'toggle_status':
                ApiHelper::requireParams($input, ['user_id', 'is_active']);
                
                $user = User::findById($input['user_id']);
                if (!$user) ApiHelper::error('用户不存在');

                ApiHelper::response($user->setActive($input['is_active']));

            case 'delete':
                ApiHelper::requireParams($input, ['user_id']);
                
                $user = User::findById($input['user_id']);
                if (!$user) ApiHelper::error('用户不存在');

                ApiHelper::response($user->delete());

            default:
                ApiHelper::error('不支持的操作');
        }
    }

    // 用户自己更新认证信息
    if (empty($token)) ApiHelper::error('缺少token参数');

    $user = ApiHelper::getUserByToken($token);
    $authType = $input['auth_type'] ?? $user->authType;

    $db = Database::getInstance();
    if ($authType === 'full') {
        $stmt = $db->prepare("UPDATE users SET auth_type = :auth_type, appid = :appid, token_online = :token_online, cookie = '' WHERE access_token = :token");
        $stmt->bindValue(':auth_type', 'full', SQLITE3_TEXT);
        $stmt->bindValue(':appid', Utils::encrypt($input['appid'] ?? ''), SQLITE3_TEXT);
        $stmt->bindValue(':token_online', Utils::encrypt($input['token_online'] ?? ''), SQLITE3_TEXT);
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    } else {
        $stmt = $db->prepare("UPDATE users SET auth_type = :auth_type, cookie = :cookie, appid = '', token_online = '' WHERE access_token = :token");
        $stmt->bindValue(':auth_type', 'cookie', SQLITE3_TEXT);
        $stmt->bindValue(':cookie', Utils::encrypt($input['cookie'] ?? ''), SQLITE3_TEXT);
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    }

    ApiHelper::response($stmt->execute() ? 
        ['success' => true, 'message' => '更新成功'] : 
        ['success' => false, 'message' => '更新失败']
    );
}

// DELETE请求：删除用户
if ($method === 'DELETE') {
    if (empty($token)) ApiHelper::error('缺少token参数');
    $user = ApiHelper::getUserByToken($token);
    ApiHelper::response($user->delete());
}

ApiHelper::error('不支持的请求方法');
