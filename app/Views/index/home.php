<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>联通流量查询系统</title>
    <link rel="stylesheet" href="/assets/css/home.css">
</head>
<body>
    <div class="tab-container">
        <div class="tab-header">
            <button class="tab-button active" onclick="switchTab('query')">
                🔍 查询链接
            </button>
            <button class="tab-button" onclick="switchTab('register')">
                ➕ 添加用户
            </button>
        </div>
        
        <div class="tab-content">
            <!-- 查询链接标签页 -->
            <div id="query-panel" class="tab-panel active">
                <h2 class="page-title">查询专属链接</h2>
                <p class="page-subtitle">
                    输入您的手机号和查询密码，获取您的专属查询链接
                </p>
                
                <form id="queryForm" onsubmit="handleQuery(event)">
                    <div class="form-group">
                        <label for="query_mobile">手机号</label>
                        <input type="tel" id="query_mobile" name="mobile" 
                               placeholder="请输入11位手机号" 
                               pattern="[0-9]{11}" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="query_password">查询密码</label>
                        <input type="password" id="query_password" name="password" 
                               placeholder="请输入查询密码" required>
                    </div>
                    
                    <button type="submit" class="btn-primary" id="queryBtn">
                        🔍 查询链接
                    </button>
                </form>
                
                <div id="queryResult" class="result-box">
                    <h3>✅ 您的专属查询链接</h3>
                    <div class="result-link" id="queryLink"></div>
                    <div>
                        <button class="btn-copy" onclick="copyLink()">📋 复制链接</button>
                        <button class="btn-goto" onclick="gotoLink()">🚀 立即访问</button>
                    </div>
                </div>
                
                <div id="queryError" class="error-message"></div>
                
                <div class="info-text">
                    💡 <strong>提示：</strong>首次使用请先在「添加用户」标签页注册账号
                </div>
            </div>
            
            <!-- 添加用户标签页 -->
            <div id="register-panel" class="tab-panel">
                <h2 class="page-title">注册新用户</h2>
                <p class="page-subtitle">
                    创建账号后即可查询流量并设置自动通知
                </p>
                
                <form id="registerForm" onsubmit="handleRegister(event)">
                    <div class="form-group">
                        <label for="reg_auth_type">认证方式 *</label>
                        <select id="reg_auth_type" name="auth_type" onchange="toggleRegAuthFields()" required>
                            <option value="token_online">Token Online（推荐）</option>
                            <option value="cookie">Cookie</option>
                        </select>
                        <small>Token Online 可自动更新凭证，Cookie 需手动更新</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_mobile">手机号 *</label>
                        <input type="tel" id="reg_mobile" name="mobile" 
                               placeholder="请输入11位手机号" 
                               pattern="1[3-9][0-9]{9}" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_nickname">昵称（可选）</label>
                        <input type="text" id="reg_nickname" name="nickname" 
                               placeholder="给自己起个昵称">
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_query_password">查询密码 *</label>
                        <input type="password" id="reg_query_password" name="query_password" 
                               placeholder="设置查询密码（至少6位）" 
                               minlength="6" required>
                        <small>用于在本平台查询自己的流量信息</small>
                    </div>
                    
                    <!-- Token Online 字段 -->
                    <div id="reg_token_online_fields">
                        <div class="form-group">
                            <label for="reg_appid">AppID *</label>
                            <input type="text" id="reg_appid" name="appid" 
                                   placeholder="联通 AppID">
                            <small>从联通手机营业厅 APP 抓包获取</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_token_online">Token Online *</label>
                            <textarea id="reg_token_online" name="token_online" rows="3" 
                                      placeholder="联通 Token Online" 
                                      style="resize: vertical;"></textarea>
                            <small>从联通手机营业厅 APP 抓包获取</small>
                        </div>
                    </div>
                    
                    <!-- Cookie 字段 -->
                    <div id="reg_cookie_fields" style="display: none;">
                        <div class="form-group">
                            <label for="reg_cookie">Cookie *</label>
                            <textarea id="reg_cookie" name="cookie" rows="4" 
                                      placeholder="联通 Cookie" 
                                      style="resize: vertical;"></textarea>
                            <small>从联通手机营业厅 APP 抓包获取，有效期较短</small>
                        </div>
                    </div>
                    
                    <?php if ($siteMode === 'invite'): ?>
                    <!-- 邀请码字段（仅邀请模式显示） -->
                    <div class="form-group">
                        <label for="reg_invite_code">邀请码 *</label>
                        <input type="text" id="reg_invite_code" name="invite_code" 
                               placeholder="请输入邀请码" required>
                        <small>当前为邀请注册模式，需要邀请码才能注册</small>
                    </div>
                    <?php elseif ($siteMode === 'closed'): ?>
                    <!-- 关闭注册提示 -->
                    <div class="warning-box">
                        <p>⚠️ 网站已关闭注册</p>
                        <p>目前暂停接受新用户注册，请联系管理员。</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($siteMode !== 'closed'): ?>
                    <button type="submit" class="btn-primary" id="registerBtn">
                        ✨ 验证并注册
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn-primary" disabled>
                        ✨ 注册已关闭
                    </button>
                    <?php endif; ?>
                </form>
                
                <div id="registerResult" class="result-box">
                    <h3>🎉 注册成功！</h3>
                    <p>您的专属查询链接：</p>
                    <div class="result-link" id="registerLink"></div>
                    <div>
                        <button class="btn-copy" onclick="copyRegisterLink()">📋 复制链接</button>
                        <button class="btn-goto" onclick="gotoRegisterLink()">🚀 立即访问</button>
                    </div>
                </div>
                
                <div id="registerError" class="error-message"></div>
                
                <?php if ($siteMode === 'open'): ?>
                <div class="info-text">
                    💡 <strong>说明：</strong>系统将验证您的联通凭证，验证成功后即可注册并获得专属查询链接
                </div>
                <?php elseif ($siteMode === 'invite'): ?>
                <div class="info-text">
                    💡 <strong>说明：</strong>当前为邀请注册模式，需要有效的邀请码才能注册。验证通过后将获得专属查询链接
                </div>
                <?php else: ?>
                <div class="info-text" style="background: linear-gradient(to right, #fef2f2, #fee2e2); border-left-color: #ef4444;">
                    💡 <strong>说明：</strong>网站目前已关闭注册功能，如需注册请联系管理员
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="/assets/js/home.js"></script>
</body>
</html>
