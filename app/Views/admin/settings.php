<?php include __DIR__ . '/header.php'; ?>

<h2>⚙️ 系统设置</h2>

<?php if (isset($flash)): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<!-- 设置分类标签 -->
<link rel="stylesheet" href="/assets/css/admin-settings.css">
<div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid #e5e7eb;">
    <button class="tab-btn active" onclick="switchTab('basic')">基本设置</button>
    <button class="tab-btn" onclick="switchTab('notify')">通知设置</button>
    <button class="tab-btn" onclick="switchTab('security')">安全设置</button>
    <button class="tab-btn" onclick="switchTab('system')">系统设置</button>
</div>

<!-- 基本设置 -->
<div id="tab-basic" class="tab-content active">
    <form id="basicForm">
        <div class="setting-item">
            <h4>🌐 网站名称</h4>
            <p>显示在页面标题和导航栏的网站名称</p>
            <input type="text" name="site_name" 
                   value="<?= htmlspecialchars($config['site_name'] ?? 'Unicom Flow Query System') ?>" 
                   style="width: 100%; max-width: 500px;">
        </div>

        <div class="setting-item">
            <h4>🔐 站点模式</h4>
            <p>控制用户注册方式</p>
            <select name="site_mode" style="width: 100%; max-width: 300px;">
                <option value="open" <?= ($config['site_mode'] ?? '') === 'open' ? 'selected' : '' ?>>开放注册</option>
                <option value="invite" <?= ($config['site_mode'] ?? 'invite') === 'invite' ? 'selected' : '' ?>>邀请码注册</option>
                <option value="closed" <?= ($config['site_mode'] ?? '') === 'closed' ? 'selected' : '' ?>>关闭注册</option>
            </select>
        </div>

        <div class="setting-item">
            <h4> 时区设置</h4>
            <p>系统时区，影响日志和任务的时间显示</p>
            <select name="timezone" style="width: 100%; max-width: 300px;">
                <option value="Asia/Shanghai" <?= ($config['timezone'] ?? 'Asia/Shanghai') === 'Asia/Shanghai' ? 'selected' : '' ?>>Asia/Shanghai (北京时间)</option>
                <option value="Asia/Hong_Kong">Asia/Hong_Kong (香港时间)</option>
                <option value="Asia/Tokyo">Asia/Tokyo (东京时间)</option>
                <option value="UTC">UTC (协调世界时)</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">💾 保存基本设置</button>
    </form>
</div>

<!-- 通知设置 -->
<div id="tab-notify" class="tab-content">
    <form id="notifyForm">
        <div class="setting-item">
            <h4>🔔 启用通知</h4>
            <p>开启后系统将通过配置的渠道发送通知</p>
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="enable_notify" value="1" 
                       <?= !empty($config['enable_notify']) ? 'checked' : '' ?>>
                <span>启用通知功能</span>
            </label>
        </div>

        <div class="setting-item">
            <h4>📱 Telegram 通知</h4>
            <p>通过 Telegram Bot 发送通知</p>
            <div class="form-group">
                <label>Bot Token</label>
                <input type="text" name="notify_telegram_bot_token" 
                       value="<?= htmlspecialchars($config['notify_telegram_bot_token'] ?? '') ?>" 
                       placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11">
            </div>
            <div class="form-group">
                <label>Chat ID</label>
                <input type="text" name="notify_telegram_chat_id" 
                       value="<?= htmlspecialchars($config['notify_telegram_chat_id'] ?? '') ?>" 
                       placeholder="123456789">
            </div>
            <div class="form-group">
                <label>API Host (可选)</label>
                <input type="text" name="notify_telegram_api_host" 
                       value="<?= htmlspecialchars($config['notify_telegram_api_host'] ?? 'https://api.telegram.org') ?>" 
                       placeholder="https://api.telegram.org">
                <small>如需使用代理，可修改为自定义 API 地址</small>
            </div>
        </div>

        <div class="setting-item">
            <h4>💼 企业微信通知</h4>
            <p>通过企业微信机器人发送通知</p>
            <div class="form-group">
                <label>Webhook URL</label>
                <input type="text" name="notify_wecom_webhook" 
                       value="<?= htmlspecialchars($config['notify_wecom_webhook'] ?? '') ?>" 
                       placeholder="https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=...">
            </div>
        </div>

        <div class="setting-item">
            <h4>📮 Server酱通知</h4>
            <p>通过 Server酱 发送微信通知</p>
            <div class="form-group">
                <label>SendKey</label>
                <input type="text" name="notify_serverchan_key" 
                       value="<?= htmlspecialchars($config['notify_serverchan_key'] ?? '') ?>" 
                       placeholder="SCU123456T...">
            </div>
        </div>

        <div class="setting-item">
            <h4>📞 钉钉通知</h4>
            <p>通过钉钉机器人发送通知</p>
            <div class="form-group">
                <label>Webhook URL</label>
                <input type="text" name="notify_dingtalk_webhook" 
                       value="<?= htmlspecialchars($config['notify_dingtalk_webhook'] ?? '') ?>" 
                       placeholder="https://oapi.dingtalk.com/robot/send?access_token=...">
            </div>
        </div>

        <div class="setting-item">
            <h4>🔔 PushPlus 通知</h4>
            <p>通过 PushPlus 推送通知</p>
            <div class="form-group">
                <label>Token</label>
                <input type="text" name="notify_pushplus_token" 
                       value="<?= htmlspecialchars($config['notify_pushplus_token'] ?? '') ?>" 
                       placeholder="abc123...">
            </div>
        </div>

        <button type="submit" class="btn btn-primary">💾 保存通知设置</button>
        <button type="button" class="btn btn-secondary" onclick="testNotify()">📤 发送测试通知</button>
    </form>
</div>

<!-- 安全设置 -->
<div id="tab-security" class="tab-content">
    <form id="securityForm">
        <div class="setting-item">
            <h4>🔒 修改管理员密码</h4>
            <p>修改当前管理员账户密码</p>
            <div class="form-group">
                <label>当前密码</label>
                <input type="password" name="old_password" autocomplete="current-password">
            </div>
            <div class="form-group">
                <label>新密码</label>
                <input type="password" name="new_password" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>确认新密码</label>
                <input type="password" name="confirm_password" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">🔐 修改密码</button>
        </div>

        <div class="setting-item">
            <h4>🛡️ IP 白名单</h4>
            <p>仅允许指定IP访问管理后台（留空表示不限制）</p>
            <textarea name="ip_whitelist" rows="4" 
                      placeholder="每行一个IP，例如：&#10;192.168.1.100&#10;10.0.0.0/8"
                      style="width: 100%; max-width: 500px;"><?= htmlspecialchars($config['ip_whitelist'] ?? '') ?></textarea>
            <div style="margin-top: 8px; font-size: 12px; color: #6b7280;">
                当前IP: <code><?= $_SERVER['REMOTE_ADDR'] ?? 'Unknown' ?></code>
            </div>
        </div>
    </form>
</div>

<!-- 系统设置 -->
<div id="tab-system" class="tab-content">
    <form id="systemForm">
        <div class="setting-item">
            <h4>📝 日志保留天数</h4>
            <p>系统日志和查询日志的保留天数，超过将自动清理</p>
            <input type="number" name="log_retention_days" 
                   value="<?= htmlspecialchars($config['log_retention_days'] ?? '30') ?>" 
                   min="1" max="365"
                   style="width: 100%; max-width: 200px;">
            <span style="margin-left: 8px;">天</span>
        </div>

        <div class="setting-item">
            <h4>📊 日志级别</h4>
            <p>记录的最低日志级别</p>
            <select name="log_level" style="width: 100%; max-width: 300px;">
                <option value="debug" <?= ($config['log_level'] ?? '') === 'debug' ? 'selected' : '' ?>>Debug (所有日志)</option>
                <option value="info" <?= ($config['log_level'] ?? 'info') === 'info' ? 'selected' : '' ?>>Info (信息及以上)</option>
                <option value="warning" <?= ($config['log_level'] ?? '') === 'warning' ? 'selected' : '' ?>>Warning (警告及以上)</option>
                <option value="error" <?= ($config['log_level'] ?? '') === 'error' ? 'selected' : '' ?>>Error (仅错误)</option>
            </select>
        </div>

        <div class="setting-item">
            <h4>🗄️ 数据库信息</h4>
            <p>当前数据库状态和操作</p>
            <div style="background: #f9fafb; padding: 12px; border-radius: 6px; margin-bottom: 12px;">
                <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; font-size: 13px;">
                    <div style="color: #6b7280;">数据库文件:</div>
                    <div><code><?= $dbInfo['path'] ?? 'database/unicom_flow.db' ?></code></div>
                    <div style="color: #6b7280;">数据库大小:</div>
                    <div><strong><?= $dbInfo['size'] ?? '-' ?></strong></div>
                    <div style="color: #6b7280;">总记录数:</div>
                    <div><strong><?= number_format($dbInfo['total_records'] ?? 0) ?></strong></div>
                </div>
            </div>
            <button type="button" class="btn btn-secondary" onclick="backupDatabase()">💾 备份数据库</button>
            <button type="button" class="btn btn-danger" onclick="clearAllData()">🗑️ 清空所有数据</button>
        </div>

        <div class="setting-item">
            <h4>🔄 缓存管理</h4>
            <p>清理系统缓存以提升性能</p>
            <button type="button" class="btn btn-secondary" onclick="clearCache()">🗑️ 清理缓存</button>
        </div>

        <button type="submit" class="btn btn-primary">💾 保存系统设置</button>
    </form>
</div>

<script src="/assets/js/admin-settings.js"></script>

<?php include __DIR__ . '/footer.php'; ?>
