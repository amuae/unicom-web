<?php
// 防止直接访问HTML源码
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册 - 流量监控</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 500px;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 8px;
            font-size: 28px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 32px;
            font-size: 14px;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 24px;
        }

        .tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            color: #666;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
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
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
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
            margin-top: 12px;
        }

        .btn-secondary:hover {
            background: #ebebeb;
            box-shadow: none;
        }

        .result-card {
            display: none;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 24px;
            margin-top: 24px;
        }

        .result-card.show {
            display: block;
        }

        .result-title {
            font-size: 18px;
            font-weight: 600;
            color: #28a745;
            margin-bottom: 16px;
            text-align: center;
        }

        .result-item {
            padding: 12px;
            background: white;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .result-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .result-value {
            font-size: 14px;
            color: #333;
            word-break: break-all;
        }

        .url-box {
            background: white;
            padding: 16px;
            border-radius: 8px;
            border: 2px dashed #667eea;
        }

        .url-value {
            font-size: 13px;
            color: #667eea;
            word-break: break-all;
            margin-bottom: 12px;
        }

        .action-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
        }

        .query-section {
            margin-bottom: 32px;
        }

        .divider {
            height: 1px;
            background: #eee;
            margin: 32px 0;
        }

        .mode-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 8px;
        }

        .mode-public {
            background: #d4edda;
            color: #155724;
        }

        .mode-private {
            background: #f8d7da;
            color: #721c24;
        }

        .activation-code-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #856404;
        }

        @media (max-width: 600px) {
            .card {
                padding: 24px;
            }
            
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>流量监控注册
                <span id="modeBadge" class="mode-badge mode-public">公开模式</span>
            </h1>
            <p class="subtitle">注册后可获取专属查询链接</p>

            <!-- 查询区域 -->
            <div class="query-section">
                <div class="form-group">
                    <label>查询已注册手机号</label>
                    <input type="tel" id="queryMobile" placeholder="输入手机号查询" maxlength="11">
                </div>
                <button class="btn btn-secondary" onclick="queryUser()">查询</button>
            </div>

            <div class="divider"></div>

            <!-- 注册区域 -->
            <div class="tabs">
                <div class="tab active" onclick="switchAuthType('register')">
                    <span>📝 用户注册</span>
                </div>
            </div>

            <!-- 注册表单 -->
            <div id="registerTab" class="tab-content active">
                <form id="registerForm" onsubmit="handleRegister(event)">
                    <div class="form-group">
                        <label>手机号 *</label>
                        <input type="tel" id="mobile" placeholder="请输入手机号" maxlength="11" required>
                    </div>

                    <div class="form-group">
                        <label>认证方式 *</label>
                        <select id="authType" onchange="toggleAuthFields()">
                            <option value="full">完整凭证（推荐）</option>
                            <option value="cookie">Cookie方式</option>
                        </select>
                    </div>

                    <!-- 完整凭证字段 -->
                    <div id="fullAuthFields">
                        <div class="form-group">
                            <label>AppID *</label>
                            <input type="text" id="appid" placeholder="请输入AppID">
                            <div class="help-text">从联通APP抓包获取</div>
                        </div>

                        <div class="form-group">
                            <label>Token Online *</label>
                            <textarea id="tokenOnline" placeholder="请输入Token Online"></textarea>
                            <div class="help-text">从联通APP抓包获取</div>
                        </div>
                    </div>

                    <!-- Cookie字段 -->
                    <div id="cookieAuthFields" style="display: none;">
                        <div class="form-group">
                            <label>Cookie *</label>
                            <textarea id="cookie" placeholder="请输入完整的Cookie"></textarea>
                            <div class="help-text">从浏览器或抓包工具获取</div>
                        </div>
                    </div>

                    <!-- 激活码字段（私有模式显示） -->
                    <div id="activationCodeField" style="display: none;">
                        <div class="activation-code-notice">
                            ⚠️ 当前为私有模式，需要输入激活码才能注册
                        </div>
                        <div class="form-group">
                            <label>激活码 *</label>
                            <input type="text" id="activationCode" placeholder="请输入24位激活码" maxlength="24">
                        </div>
                    </div>

                    <button type="submit" class="btn" id="registerBtn">立即注册</button>
                </form>
            </div>

            <!-- 注册成功结果 -->
            <div id="resultCard" class="result-card">
                <div class="result-title">✅ 注册成功</div>
                <div class="result-item">
                    <div class="result-label">手机号</div>
                    <div class="result-value" id="resultMobile"></div>
                </div>
                <div class="result-item">
                    <div class="result-label">用户类型</div>
                    <div class="result-value" id="resultUserType"></div>
                </div>
                <div class="url-box">
                    <div class="result-label">专属查询链接</div>
                    <div class="url-value" id="resultUrl"></div>
                    <div class="action-btns">
                        <button class="btn btn-small" onclick="copyUrl()">复制链接</button>
                        <button class="btn btn-small" onclick="openUrl()">立即访问</button>
                    </div>
                </div>
                <button class="btn btn-secondary" onclick="resetForm()">继续注册</button>
            </div>

            <!-- 查询结果 -->
            <div id="queryResultCard" class="result-card">
                <div class="result-title">📱 查询结果</div>
                <div class="result-item">
                    <div class="result-label">手机号</div>
                    <div class="result-value" id="queryResultMobile"></div>
                </div>
                <div class="result-item">
                    <div class="result-label">认证方式</div>
                    <div class="result-value" id="queryResultAuthType"></div>
                </div>
                <div class="result-item">
                    <div class="result-label">用户类型</div>
                    <div class="result-value" id="queryResultUserType"></div>
                </div>
                <div class="result-item">
                    <div class="result-label">状态</div>
                    <div class="result-value" id="queryResultStatus"></div>
                </div>
                <div class="result-item">
                    <div class="result-label">注册时间</div>
                    <div class="result-value" id="queryResultCreatedAt"></div>
                </div>
                <div class="url-box">
                    <div class="result-label">查询链接</div>
                    <div class="url-value" id="queryResultUrl"></div>
                    <div class="action-btns">
                        <button class="btn btn-small" onclick="copyQueryUrl()">复制链接</button>
                        <button class="btn btn-small" onclick="openQueryUrl()">立即访问</button>
                    </div>
                </div>
                <button class="btn btn-secondary" onclick="closeQueryResult()">关闭</button>
            </div>
        </div>
    </div>

    <script>
        // 工具函数
        const $ = (id) => document.getElementById(id);
        const show = (el, display = 'block') => (typeof el === 'string' ? $(el) : el).style.display = display;
        const hide = (el) => (typeof el === 'string' ? $(el) : el).style.display = 'none';
        const toggle = (el, condition) => condition ? show(el) : hide(el);
        const val = (id) => $(id).value.trim();
        const setReq = (id, required) => $(id).required = required;
        
        async function apiRequest(url, options = {}) {
            const response = await fetch(url, options);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return await response.json();
        }
        
        async function copyToClip(text) {
            try {
                await navigator.clipboard.writeText(text);
                alert('链接已复制到剪贴板');
            } catch (e) { alert('复制失败'); }
        }
        
        let systemMode = 'public';
        let queryUrl = '';
        let queryResultUrl = '';

        window.onload = async function() {
            await checkSystemMode();
            const lastQuery = localStorage.getItem('lastQueryMobile');
            if (lastQuery) $('queryMobile').value = lastQuery;
        };

        async function checkSystemMode() {
            try {
                const result = await apiRequest('../api/system.php?action=config');
                if (result.success) {
                    systemMode = result.data.site_mode;
                    updateModeDisplay();
                }
            } catch (error) {
                console.error('检查系统模式失败:', error);
            }
        }

        function updateModeDisplay() {
            const badge = $('modeBadge');
            const isPrivate = systemMode === 'private';
            badge.textContent = isPrivate ? '私有模式' : '公开模式';
            badge.className = `mode-badge mode-${systemMode}`;
            toggle('activationCodeField', isPrivate);
            setReq('activationCode', isPrivate);
        }

        function toggleAuthFields() {
            const authType = $('authType').value;
            const isFull = authType === 'full';
            toggle('fullAuthFields', isFull);
            toggle('cookieAuthFields', !isFull);
            setReq('appid', isFull);
            setReq('tokenOnline', isFull);
            setReq('cookie', !isFull);
        }

        async function handleRegister(event) {
            event.preventDefault();
            const btn = $('registerBtn');
            btn.disabled = true;
            btn.textContent = '注册中...';
            
            const authType = $('authType').value;
            const data = {
                mobile: val('mobile'),
                auth_type: authType,
                ...(authType === 'full' ? 
                    { appid: val('appid'), token_online: val('tokenOnline') } : 
                    { cookie: val('cookie') }),
                ...(systemMode === 'private' && { activation_code: val('activationCode') })
            };
            
            try {
                const result = await apiRequest('../api/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                if (result.success) {
                    $('resultMobile').textContent = result.data.mobile;
                    $('resultUserType').textContent = result.data.user_type === 'beta' ? '公测用户' : '激活码用户';
                    $('resultUrl').textContent = queryUrl = result.data.access_url;
                    hide('registerForm');
                    $('resultCard').classList.add('show');
                    localStorage.setItem('lastRegisteredMobile', result.data.mobile);
                    localStorage.setItem('lastQueryUrl_' + result.data.mobile, result.data.access_url);
                } else {
                    alert('注册失败：' + result.message);
                }
            } catch (error) {
                alert('注册失败：' + error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = '立即注册';
            }
        }

        async function queryUser() {
            const mobile = val('queryMobile');
            if (!mobile) return alert('请输入手机号');
            if (!/^1[3-9]\d{9}$/.test(mobile)) return alert('请输入有效的手机号');
            
            try {
                const result = await apiRequest(`../api/user.php?mobile=${mobile}`);
                if (result.success) {
                    const d = result.data;
                    $('queryResultMobile').textContent = d.mobile;
                    $('queryResultAuthType').textContent = d.auth_type === 'full' ? '完整凭证' : 'Cookie';
                    $('queryResultUserType').textContent = d.user_type;
                    $('queryResultStatus').textContent = d.status;
                    $('queryResultCreatedAt').textContent = d.created_at;
                    $('queryResultUrl').textContent = queryResultUrl = d.query_url;
                    $('queryResultCard').classList.add('show');
                    localStorage.setItem('lastQueryMobile', mobile);
                } else {
                    alert('查询失败：' + result.message);
                }
            } catch (error) {
                alert('查询失败：' + error.message);
            }
        }

        const copyUrl = () => copyToClip(queryUrl);
        const openUrl = () => window.open(queryUrl, '_blank');
        const copyQueryUrl = () => copyToClip(queryResultUrl);
        const openQueryUrl = () => window.open(queryResultUrl, '_blank');
        const closeQueryResult = () => $('queryResultCard').classList.remove('show');
        
        function resetForm() {
            $('registerForm').reset();
            show('registerForm');
            $('resultCard').classList.remove('show');
            toggleAuthFields();
        }
    </script>
    
    <!-- 开发者工具防护 -->
    <script src="js/anti-devtools.js"></script>
</body>
</html>
