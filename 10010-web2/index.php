<?php
// 防止直接访问HTML源码
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>联通流量监控</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 32px;
        }

        .header h1 {
            font-size: 36px;
            margin-bottom: 8px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .header .subtitle {
            font-size: 16px;
            opacity: 0.9;
        }

        .mode-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 500;
            margin-top: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .mode-public {
            background: #28a745;
            color: white;
        }

        .mode-private {
            background: #ffc107;
            color: #333;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title .icon {
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #eee;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .help-text {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .btn-secondary:hover {
            background: #ebebeb;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .auth-fields {
            display: none;
            animation: fadeIn 0.3s;
        }

        .auth-fields.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .result-card {
            display: none;
            background: #f8f9fa;
            border-radius: 16px;
            padding: 24px;
            margin-top: 24px;
            animation: slideIn 0.4s;
        }

        .result-card.show {
            display: block;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .result-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
        }

        .result-title.success {
            color: #28a745;
        }

        .result-title.info {
            color: #667eea;
        }

        .result-item {
            padding: 14px;
            background: white;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .result-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .result-value {
            font-size: 15px;
            color: #333;
            word-break: break-all;
        }

        .url-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 2px dashed #667eea;
        }

        .url-value {
            font-size: 13px;
            color: #667eea;
            word-break: break-all;
            margin-bottom: 16px;
            padding: 12px;
            background: #f8f9ff;
            border-radius: 8px;
        }

        .action-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .btn-small {
            padding: 10px 16px;
            font-size: 14px;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #ddd, transparent);
            margin: 32px 0;
        }

        .notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #856404;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer {
            text-align: center;
            color: white;
            opacity: 0.8;
            margin-top: 32px;
            font-size: 14px;
        }

        .footer a {
            color: white;
            text-decoration: none;
            border-bottom: 1px solid rgba(255,255,255,0.3);
            transition: border-color 0.3s;
        }

        .footer a:hover {
            border-bottom-color: white;
        }

        .disclaimer {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            margin-top: 24px;
            color: white;
            font-size: 13px;
            line-height: 1.8;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .disclaimer-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .disclaimer ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .disclaimer li {
            margin: 6px 0;
        }

        .watermark {
            position: fixed;
            bottom: 16px;
            right: 16px;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(10px);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 1000;
        }

        .watermark a {
            color: #64b5f6;
            text-decoration: none;
        }

        .watermark a:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .container {
                padding: 0;
            }
            
            .header h1 {
                font-size: 28px;
            }
            
            .card {
                padding: 24px;
                border-radius: 16px;
            }

            .watermark {
                position: static;
                margin: 16px auto;
                width: fit-content;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 页面头部 -->
        <div class="header">
            <h1>📊 联通流量监控</h1>
            <p class="subtitle">实时监控流量使用情况</p>
            <span id="modeBadge" class="mode-badge mode-public">🔓 公开模式</span>
        </div>

        <!-- 查询卡片 -->
        <div class="card">
            <div class="card-title">
                <span class="icon">🔍</span>
                <span>查询已注册账号</span>
            </div>
            
            <div class="form-group">
                <label>手机号</label>
                <input type="tel" id="queryMobile" placeholder="输入手机号查询访问链接" maxlength="11">
            </div>
            
            <button class="btn btn-secondary" onclick="queryUser()">
                查询我的链接
            </button>
            
            <!-- 查询结果 -->
            <div id="queryResult" class="result-card">
                <div class="result-title info">📱 查询成功</div>
                <div class="result-item">
                    <div class="result-label">手机号</div>
                    <div class="result-value" id="qMobile"></div>
                </div>
                <div class="result-item">
                    <div class="result-label">认证方式</div>
                    <div class="result-value" id="qAuthType"></div>
                </div>
                <div class="result-item">
                    <div class="result-label">用户类型</div>
                    <div class="result-value" id="qUserType"></div>
                </div>
                <div class="result-item">
                    <div class="result-label">注册时间</div>
                    <div class="result-value" id="qCreatedAt"></div>
                </div>
                <div class="url-box">
                    <div class="result-label">专属查询链接</div>
                    <div class="url-value" id="qUrl"></div>
                    <div class="action-btns">
                        <button class="btn btn-small" onclick="copyQueryUrl()">📋 复制链接</button>
                        <button class="btn btn-small" onclick="openQueryUrl()">🚀 立即访问</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <!-- 注册卡片 -->
        <div class="card">
            <div class="card-title">
                <span class="icon">📝</span>
                <span>新用户注册</span>
            </div>

            <form id="registerForm" onsubmit="handleRegister(event)">
                <!-- 激活码提示（私有模式显示） -->
                <div id="activationNotice" class="notice" style="display: none;">
                    <span>⚠️</span>
                    <span>当前为私有模式，需要激活码才能注册</span>
                </div>

                <div class="form-group">
                    <label>手机号 *</label>
                    <input type="tel" id="mobile" placeholder="请输入11位手机号" maxlength="11" required>
                </div>

                <div class="form-group">
                    <label>认证方式 *</label>
                    <select id="authType" onchange="toggleAuthFields()">
                        <option value="cookie">Cookie方式（推荐）</option>
                        <option value="full">完整凭证</option>
                    </select>
                </div>

                <!-- Cookie认证 -->
                <div id="cookieFields" class="auth-fields active">
                    <div class="form-group">
                        <label>Cookie *</label>
                        <textarea id="cookie" placeholder="从浏览器或抓包工具获取完整Cookie"></textarea>
                        <div class="help-text">登录联通APP后抓包获取</div>
                    </div>
                </div>

                <!-- 完整凭证认证 -->
                <div id="fullFields" class="auth-fields">
                    <div class="form-group">
                        <label>AppID *</label>
                        <input type="text" id="appid" placeholder="请输入AppID">
                    </div>
                    <div class="form-group">
                        <label>Token Online *</label>
                        <textarea id="tokenOnline" placeholder="请输入Token Online"></textarea>
                        <div class="help-text">从联通APP抓包获取</div>
                    </div>
                </div>

                <!-- 激活码（私有模式显示） -->
                <div id="activationCodeField" style="display: none;">
                    <div class="form-group">
                        <label>激活码 *</label>
                        <input type="text" id="activationCode" placeholder="请输入24位激活码" maxlength="24">
                    </div>
                </div>

                <button type="submit" class="btn" id="registerBtn">
                    立即注册
                </button>
            </form>

            <!-- 注册成功结果 -->
            <div id="registerResult" class="result-card">
                <div class="result-title success">✅ 注册成功</div>
                <div class="result-item">
                    <div class="result-label">手机号</div>
                    <div class="result-value" id="rMobile"></div>
                </div>
                <div class="result-item">
                    <div class="result-label">用户类型</div>
                    <div class="result-value" id="rUserType"></div>
                </div>
                <div class="url-box">
                    <div class="result-label">专属查询链接</div>
                    <div class="url-value" id="rUrl"></div>
                    <div class="action-btns">
                        <button class="btn btn-small" onclick="copyRegisterUrl()">📋 复制链接</button>
                        <button class="btn btn-small" onclick="openRegisterUrl()">🚀 立即访问</button>
                    </div>
                </div>
                <button class="btn btn-secondary" onclick="resetForm()" style="margin-top: 16px;">
                    继续注册
                </button>
            </div>
        </div>

        <!-- 页脚 -->
        <div class="footer">
            <p>联通流量监控系统 v1.0</p>
        </div>
    </div>

    <script>
        let systemMode = 'public';
        let currentQueryUrl = '';
        let currentRegisterUrl = '';

        // 页面加载
        window.onload = async function() {
            await checkSystemMode();
            
            // 从LocalStorage恢复
            const lastMobile = localStorage.getItem('lastQueryMobile');
            if (lastMobile) {
                document.getElementById('queryMobile').value = lastMobile;
            }
        };

        // 检查系统模式
        async function checkSystemMode() {
            try {
                const response = await fetch('api/system.php?action=config');
                const result = await response.json();
                
                if (result.success) {
                    systemMode = result.data.site_mode;
                    updateModeDisplay();
                }
            } catch (error) {
                console.error('检查系统模式失败:', error);
            }
        }

        // 更新模式显示
        function updateModeDisplay() {
            const badge = document.getElementById('modeBadge');
            const notice = document.getElementById('activationNotice');
            const codeField = document.getElementById('activationCodeField');
            
            if (systemMode === 'private') {
                badge.textContent = '🔒 私有模式';
                badge.className = 'mode-badge mode-private';
                notice.style.display = 'flex';
                codeField.style.display = 'block';
                document.getElementById('activationCode').required = true;
            } else {
                badge.textContent = '🔓 公开模式';
                badge.className = 'mode-badge mode-public';
                notice.style.display = 'none';
                codeField.style.display = 'none';
                document.getElementById('activationCode').required = false;
            }
        }

        // 切换认证字段
        function toggleAuthFields() {
            const authType = document.getElementById('authType').value;
            const cookieFields = document.getElementById('cookieFields');
            const fullFields = document.getElementById('fullFields');
            
            if (authType === 'cookie') {
                cookieFields.classList.add('active');
                fullFields.classList.remove('active');
                document.getElementById('cookie').required = true;
                document.getElementById('appid').required = false;
                document.getElementById('tokenOnline').required = false;
            } else {
                cookieFields.classList.remove('active');
                fullFields.classList.add('active');
                document.getElementById('cookie').required = false;
                document.getElementById('appid').required = true;
                document.getElementById('tokenOnline').required = true;
            }
        }

        // 查询用户
        async function queryUser() {
            const mobile = document.getElementById('queryMobile').value.trim();
            
            if (!mobile) {
                alert('请输入手机号');
                return;
            }
            
            if (!/^1[3-9]\d{9}$/.test(mobile)) {
                alert('请输入有效的11位手机号');
                return;
            }
            
            try {
                const response = await fetch(`api/user.php?mobile=${mobile}`);
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('qMobile').textContent = result.data.mobile;
                    document.getElementById('qAuthType').textContent = 
                        result.data.auth_type === 'full' ? '完整凭证' : 'Cookie';
                    document.getElementById('qUserType').textContent = result.data.user_type;
                    document.getElementById('qCreatedAt').textContent = result.data.created_at;
                    document.getElementById('qUrl').textContent = result.data.query_url;
                    currentQueryUrl = result.data.query_url;
                    
                    document.getElementById('queryResult').classList.add('show');
                    
                    // 保存到LocalStorage
                    localStorage.setItem('lastQueryMobile', mobile);
                    localStorage.setItem('queryUrl_' + mobile, result.data.query_url);
                } else {
                    alert(result.message || '查询失败');
                }
            } catch (error) {
                alert('查询失败：' + error.message);
            }
        }

        // 处理注册
        async function handleRegister(event) {
            event.preventDefault();
            
            const btn = document.getElementById('registerBtn');
            btn.disabled = true;
            btn.textContent = '注册中...';
            
            const authType = document.getElementById('authType').value;
            const mobile = document.getElementById('mobile').value.trim();
            
            const data = {
                mobile: mobile,
                auth_type: authType
            };
            
            if (authType === 'cookie') {
                data.cookie = document.getElementById('cookie').value.trim();
            } else {
                data.appid = document.getElementById('appid').value.trim();
                data.token_online = document.getElementById('tokenOnline').value.trim();
            }
            
            if (systemMode === 'private') {
                data.activation_code = document.getElementById('activationCode').value.trim();
            }
            
            try {
                const response = await fetch('api/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('rMobile').textContent = result.data.mobile;
                    document.getElementById('rUserType').textContent = result.data.user_type;
                    document.getElementById('rUrl').textContent = result.data.query_url;
                    currentRegisterUrl = result.data.query_url;
                    
                    document.getElementById('registerForm').style.display = 'none';
                    document.getElementById('registerResult').classList.add('show');
                    
                    // 保存到LocalStorage
                    localStorage.setItem('lastRegisteredMobile', mobile);
                    localStorage.setItem('queryUrl_' + mobile, result.data.query_url);
                } else {
                    alert(result.message || '注册失败');
                }
            } catch (error) {
                alert('注册失败：' + error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = '立即注册';
            }
        }

        // 复制查询链接
        function copyQueryUrl() {
            navigator.clipboard.writeText(currentQueryUrl).then(() => {
                alert('✅ 链接已复制到剪贴板');
            }).catch(() => {
                alert('❌ 复制失败，请手动复制');
            });
        }

        // 打开查询链接
        function openQueryUrl() {
            if (currentQueryUrl) {
                window.open(currentQueryUrl, '_blank');
            }
        }

        // 复制注册链接
        function copyRegisterUrl() {
            navigator.clipboard.writeText(currentRegisterUrl).then(() => {
                alert('✅ 链接已复制到剪贴板');
            }).catch(() => {
                alert('❌ 复制失败，请手动复制');
            });
        }

        // 打开注册链接
        function openRegisterUrl() {
            if (currentRegisterUrl) {
                window.open(currentRegisterUrl, '_blank');
            }
        }

        // 重置表单
        function resetForm() {
            document.getElementById('registerForm').reset();
            document.getElementById('registerForm').style.display = 'block';
            document.getElementById('registerResult').classList.remove('show');
            toggleAuthFields();
        }
    </script>

    <!-- 免责声明 -->
    <div class="container">
        <div class="disclaimer">
            <div class="disclaimer-title">
                ⚠️ 免责声明
            </div>
            <ul>
                <li>本项目仅供学习和技术交流使用，严禁用于任何商业用途或非法活动。</li>
                <li>使用本项目所产生的一切后果由使用者自行承担，与项目作者及贡献者无关。</li>
                <li>本项目不对服务的稳定性、准确性、完整性做任何保证。</li>
                <li>用户使用本服务即表示同意自行承担所有风险，包括但不限于数据泄露、账号安全等问题。</li>
                <li>下载或使用本项目即表示您已阅读并同意本免责声明，请在下载后24小时内删除。</li>
                <li>如果您不同意本声明的任何内容，请立即停止使用本项目。</li>
            </ul>
            <p style="margin-top: 12px; opacity: 0.9;">
                本项目基于开源协议发布，代码完全公开透明。如有任何疑问或建议，欢迎访问我们的开源仓库。
            </p>
        </div>
    </div>

    <!-- 水印 -->
    <div class="watermark">
        <span>📦 开源项目</span>
        <span>|</span>
        <a href="https://github.com/amuae/unicom-web" target="_blank">GitHub: amuae/unicom-web</a>
    </div>
    
    <!-- 开发者工具防护 -->
    <script src="views/js/anti-devtools.js"></script>
</body>
</html>
