<?php
/**
 * 用户注册 API - 优化版
 */

require_once __DIR__ . '/../classes/ApiHelper.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../admin/ActivationCode.php';

ApiHelper::init();

// GET请求：查询系统模式
if (ApiHelper::getMethod() === 'GET') {
    if (($_GET['action'] ?? '') === 'check_mode') {
        try {
            $db = Database::getInstance();
            $mode = $db->querySingle("SELECT value FROM site_config WHERE key = 'site_mode'") ?: 'public';
            ApiHelper::success(['mode' => $mode]);
        } catch (Exception $e) {
            ApiHelper::error('查询失败: ' . $e->getMessage());
        }
    }
    ApiHelper::error('不支持的操作');
}

// POST请求：用户注册
ApiHelper::checkMethod('POST');
$input = ApiHelper::getInput();

$mobile = trim($input['mobile'] ?? '');
$authType = $input['auth_type'] ?? 'full';
$appid = trim($input['appid'] ?? '');
$tokenOnline = trim($input['token_online'] ?? '');
$cookie = trim($input['cookie'] ?? '');
$activationCode = trim($input['activation_code'] ?? '');

ApiHelper::validateMobile($mobile);

if ($authType === 'full') {
    ApiHelper::requireParams($input, ['appid', 'token_online']);
} else if ($authType === 'cookie') {
    ApiHelper::requireParams($input, ['cookie']);
} else {
    ApiHelper::error('无效的认证类型');
}

try {
    $db = Database::getInstance();

    if (User::findByMobile($mobile)) {
        ApiHelper::error('该手机号已注册');
    }

    $siteMode = $db->querySingle("SELECT value FROM site_config WHERE key = 'site_mode'") ?: 'public';
    $userType = 'beta';

    if ($siteMode === 'private') {
        if (empty($activationCode)) {
            ApiHelper::error('私有模式下需要激活码');
        }
        $validation = ActivationCode::validate($activationCode);
        if (!$validation['success']) {
            ApiHelper::error($validation['message']);
        }
        $userType = 'activated';
    }

    $result = $authType === 'full'
        ? User::createWithFullAuth($mobile, $appid, $tokenOnline, '')
        : User::createWithCookie($mobile, $cookie, '');

    if (!$result['success']) {
        ApiHelper::error($result['message']);
    }

    $userId = $result['data']['user_id'];

    if ($siteMode === 'private' && !empty($activationCode)) {
        $stmt = $db->prepare("UPDATE users SET user_type = :type WHERE id = :id");
        $stmt->bindValue(':type', $userType, SQLITE3_TEXT);
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        ActivationCode::use($activationCode, $userId);
    }

    $user = User::findById($userId);
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $url = "{$protocol}://{$_SERVER['HTTP_HOST']}/views/index.html?token={$user->accessToken}";

    ApiHelper::success([
        'mobile' => $mobile,
        'access_token' => $user->accessToken,
        'query_url' => $url,
        'access_url' => $url,
        'user_type' => $userType
    ], '注册成功');

} catch (Exception $e) {
    ApiHelper::error('注册失败: ' . $e->getMessage());
}
