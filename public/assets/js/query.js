// 全局变量
let currentToken = null;
let userToken = null;
let currentData = null;
let currentTab = 'notify';

// 页面初始化
document.addEventListener('DOMContentLoaded', function() {
    // 从body获取token
    const body = document.body;
    currentToken = body.dataset.token || '';
    userToken = body.dataset.userToken || '';
    
    if (currentToken) {
        // 自动查询流量
        queryFlow();
    }
});

// 显示加载动画
function showLoading(text = '正在查询流量...') {
    const overlay = document.getElementById('loadingOverlay');
    const textEl = overlay.querySelector('.loading-text');
    if (textEl) textEl.textContent = text;
    overlay.classList.add('show');
}

// 隐藏加载动画
function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('show');
}

// 查询流量
async function queryFlow() {
    try {
        showLoading('正在查询流量...');
        
        const response = await fetch(`/query.php?action=query_flow&token=${encodeURIComponent(currentToken)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const result = await response.json();
        hideLoading();
        
        if (result.success) {
            currentData = result.data;
            displayResult(result.data);
        } else {
            showError(result.message || '查询失败');
        }
    } catch (error) {
        hideLoading();
        showError('网络错误：' + error.message);
    }
}

// 显示查询结果
function displayResult(data) {
    // 保存查询数据（包括cookie）到全局变量
    currentData = data;
    
    // 隐藏错误卡片
    document.getElementById('errorCard').style.display = 'none';
    
    // 显示头部
    const header = document.getElementById('header');
    header.style.display = 'block';
    
    // 更新套餐名称
    document.getElementById('packageName').textContent = data.mainPackage || '中国联通';
    
    // 更新余额和费用（从balance对象中获取）
    const balance = data.balance || {};
    document.getElementById('balanceAmount').textContent = formatBalance(balance.balance || 0);
    document.getElementById('monthlyFee').textContent = formatBalance(balance.realFee || 0);
    
    // 更新查询时间
    const updateTime = data.timestamp || new Date().toISOString().replace('T', ' ').substring(0, 19);
    document.getElementById('updateTime').textContent = `查询时间：${updateTime}`;
    
    // 渲染流量桶（横向滑动小卡片） - 转换buckets对象为数组
    if (data.buckets) {
        const bucketsArray = convertBucketsToArray(data.buckets, data.diff || {});
        renderBucketMiniCards(bucketsArray);
        document.getElementById('summaryCard').style.display = 'block';
        
        // 显示时长
        document.getElementById('timeInterval').textContent = data.timeInterval || '本次查询';
    }
    
    // 渲染流量包
    if (data.packages && data.packages.length > 0) {
        renderPackages(data.packages);
    }
}

// 转换buckets对象为数组格式（适配前端显示）
function convertBucketsToArray(buckets, diff) {
    const result = [];
    // 桶名称映射和显示顺序（与v1.0.1保持一致）
    const displayBuckets = [
        { key: '所有通用', name: '📱 所有通用', type: 'common' },
        { key: '所有免流', name: '🎯 所有免流', type: 'targeted' },
        { key: 'common_limited', name: '通用有限', type: 'common' },
        { key: 'common_unlimited', name: '通用不限', type: 'common' },
        { key: 'regional_limited', name: '区域有限', type: 'regional' },
        { key: 'regional_unlimited', name: '区域不限', type: 'regional' },
        { key: 'targeted_limited', name: '免流有限', type: 'targeted' },
        { key: 'targeted_unlimited', name: '免流不限', type: 'targeted' }
    ];
    
    displayBuckets.forEach(item => {
        const bucket = buckets[item.key];
        const diffData = diff[item.key] || { uused: 0, today: 0 };
        
        // 只显示有数据的桶
        if (bucket && (bucket.total > 0 || bucket.used > 0 || bucket.remain > 0)) {
            result.push({
                resourcesName: item.name,
                total_mb: bucket.total,
                used_mb: bucket.used,
                remain_mb: bucket.remain,
                type: item.type,
                uused: diffData.uused || 0,
                today: diffData.today || 0
            });
        }
    });
    
    return result;
}

// 渲染横向滑动的流量桶小卡片
function renderBucketMiniCards(buckets) {
    const wrapper = document.getElementById('bucketScrollWrapper');
    wrapper.innerHTML = '';
    
    buckets.forEach(bucket => {
        const card = document.createElement('div');
        card.className = 'bucket-mini-card';
        
        // 根据类型添加不同样式
        const bucketType = bucket.type || '';
        if (bucketType === 'targeted' || (bucket.resourcesName && (bucket.resourcesName.includes('免流') || bucket.resourcesName.includes('🎯')))) {
            card.classList.add('targeted');
        } else if (bucketType === 'regional' || (bucket.resourcesName && bucket.resourcesName.includes('区域'))) {
            card.classList.add('regional');
        }
        
        const total = bucket.total_mb || 0;
        const used = bucket.used_mb || 0;
        const remain = bucket.remain_mb || 0;
        const uused = bucket.uused || 0;
        const today = bucket.today || 0;
        
        // 判断是否无限流量
        const isUnlimited = total >= 999999 || bucket.resourcesName.includes('不限');
        
        // 使用v1.0.1的优秀设计：emoji + 清晰的标签
        card.innerHTML = `
            <div class="bucket-mini-name">${bucket.resourcesName}</div>
            <div class="bucket-mini-used">本次: ${formatFlow(uused)}</div>
            <div class="bucket-mini-detail">
                <div>📆 今日: ${formatFlow(today)}</div>
                <div>💾 已用: ${formatFlow(used)}</div>
                <div>📦 剩余: ${isUnlimited ? '无限' : formatFlow(remain)}</div>
            </div>
        `;
        
        wrapper.appendChild(card);
    });
    
    // 初始化拖动功能
    initBucketScrollDrag();
}

// 初始化流量桶横向滑动的鼠标拖动功能
function initBucketScrollDrag() {
    const container = document.querySelector('.bucket-scroll-container');
    if (!container) return;
    
    let isDown = false;
    let startX;
    let scrollLeft;
    
    container.addEventListener('mousedown', (e) => {
        isDown = true;
        container.style.cursor = 'grabbing';
        startX = e.pageX - container.offsetLeft;
        scrollLeft = container.scrollLeft;
    });
    
    container.addEventListener('mouseleave', () => {
        isDown = false;
        container.style.cursor = 'grab';
    });
    
    container.addEventListener('mouseup', () => {
        isDown = false;
        container.style.cursor = 'grab';
    });
    
    container.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - container.offsetLeft;
        const walk = (x - startX) * 2; // 滚动速度
        container.scrollLeft = scrollLeft - walk;
    });
}

// 渲染流量包列表（进度条样式）
function renderPackages(packages) {
    if (!packages || packages.length === 0) return;

    const container = document.getElementById('packagesContainer');
    container.innerHTML = '';

    // 分离公免流量包和普通流量包
    const publicFreePackages = [];
    const normalPackages = [];
    packages.forEach(pkg => {
        if (pkg.isPublicFree) {
            publicFreePackages.push(pkg);
        } else {
            normalPackages.push(pkg);
        }
    });

    // 先渲染普通流量包
    let pkgIndex = 0;
    normalPackages.forEach(pkg => {
        const card = document.createElement('div');
        card.className = 'package-card fade-in';
        
        // 适配后端字段名
        const total = pkg.total || 0;
        const used = pkg.use || pkg.used || 0;
        const remain = pkg.remain || 0;
        const name = pkg.name || '未知套餐';
        
        const isUnlimited = total >= 999999 || total === 0;
        const isFree = pkg.isPublicFree;
        const percent = (isUnlimited || total === 0) ? 0 : ((used / total) * 100).toFixed(1);
        
        // 处理主副卡信息（默认收起）
        let viceHtml = '';
        if (pkg.viceCardlist && pkg.viceCardlist.length > 0) {
            const viceId = `vice-${pkgIndex}`;
            viceHtml = `<div class="vice-card">
                <div class="vice-title" onclick="toggleViceCard('${viceId}')">
                    🔗 主副卡使用详情
                    <span class="vice-toggle collapsed" id="${viceId}-toggle">▼</span>
                </div>
                <div class="vice-content collapsed" id="${viceId}-content">`;
            pkg.viceCardlist.forEach(vice => {
                const isCurrent = vice.currentLoginFlag === '1';
                const isMainCard = vice.viceCardflag === '1';  // viceCardflag='1'表示主卡，'0'表示副卡
                viceHtml += `<div class="vice-item">
                    <div>
                        <span class="vice-number">${vice.usernumber}</span>
                        ${isCurrent ? '<span class="vice-current">（当前登录）</span>' : ''}
                        ${isMainCard ? '<span style="color: #999; font-size: 11px;">（主卡）</span>' : '<span style="color: #999; font-size: 11px;">（副卡）</span>'}
                    </div>
                    <span class="vice-usage">${formatFlow(parseFloat(vice.use))}</span>
                </div>`;
            });
            viceHtml += '</div></div>';
        }
        pkgIndex++;
        
        // 处理到期时间（作为内联元素显示）
        let expireText = '';
        if (pkg.endDate && pkg.endDate !== '长期有效') {
            expireText = `⏰ ${pkg.endDate}`;
        } else if (pkg.endDate === '长期有效') {
            expireText = `✓ 长期有效`;
        }
        
        card.innerHTML = `
            <div class="package-header">
                <div class="package-name">${name}</div>
                ${isFree ? '<span class="package-badge">免费</span>' : ''}
            </div>
            <div class="package-info">
                <span class="package-used">${formatFlow(used)} / ${isUnlimited ? '∞' : formatFlow(total)}</span>
                <span class="package-percent">${isUnlimited ? '不限量' : percent + '%'}</span>
            </div>
            ${!isUnlimited ? `<div class="package-bar">
                <div class="package-bar-fill" style="width: ${Math.min(percent, 100)}%"></div>
            </div>` : ''}
            <div class="package-detail">
                <span>剩余 ${isUnlimited ? '∞' : formatFlow(remain)}</span>
                <span style="color: ${pkg.endDate === '长期有效' ? '#4caf50' : '#ff9800'}; font-size: 11px; font-weight: 500;">${expireText || (pkg.isPublicFree ? '公免流量' : '已订购')}</span>
            </div>
            ${viceHtml}
        `;
        
        container.appendChild(card);
    });

    // 最后渲染公免流量合并卡片
    if (publicFreePackages.length > 0) {
        const publicFreeCard = document.createElement('div');
        publicFreeCard.className = 'package-card fade-in';
        
        // 计算公免流量总和
        let publicFreeTotal = 0;
        let publicFreeUsed = 0;
        let publicFreeRemain = 0;
        publicFreePackages.forEach(pkg => {
            publicFreeUsed += pkg.use || pkg.used || 0;
            publicFreeTotal += pkg.total || 0;
            publicFreeRemain += pkg.remain || 0;
        });
        
        const isUnlimited = publicFreeTotal >= 999999 || publicFreeTotal === 0;
        const percent = (isUnlimited || publicFreeTotal === 0) ? 0 : ((publicFreeUsed / publicFreeTotal) * 100).toFixed(1);
        
        // 生成各个公免流量包的详情列表
        let detailsHtml = '<div class="vice-card"><div class="vice-title">🎁 公免流量详情</div>';
        publicFreePackages.forEach(pkg => {
            const pkgTotal = pkg.total || 0;
            const pkgUsed = pkg.use || pkg.used || 0;
            const pkgPercent = (pkgTotal === 0 || pkgTotal >= 999999) ? 0 : ((pkgUsed / pkgTotal) * 100).toFixed(1);
            detailsHtml += `<div class="vice-item">
                <div style="flex: 1;">
                    <div style="font-weight: 500; color: #333; margin-bottom: 4px;">${pkg.name}</div>
                    <div style="font-size: 11px; color: #999;">已用 ${formatFlow(pkgUsed)} / ${pkgTotal === 0 || pkgTotal >= 999999 ? '∞' : formatFlow(pkgTotal)}</div>
                </div>
                <span class="vice-usage">${(pkgTotal === 0 || pkgTotal >= 999999) ? '不限量' : pkgPercent + '%'}</span>
            </div>`;
        });
        detailsHtml += '</div>';
        
        publicFreeCard.innerHTML = `
            <div class="package-header">
                <div class="package-name">公免流量</div>
                <span class="package-badge">免费</span>
            </div>
            ${detailsHtml}
        `;
        container.appendChild(publicFreeCard);
    }
}

// 切换副卡信息展开/折叠
function toggleViceCard(viceId) {
    const content = document.getElementById(`${viceId}-content`);
    const toggle = document.getElementById(`${viceId}-toggle`);
    
    if (content && toggle) {
        content.classList.toggle('collapsed');
        toggle.classList.toggle('collapsed');
    }
}

// 确保函数在全局作用域可访问
window.toggleViceCard = toggleViceCard;

// 显示错误信息
function showError(message) {
    document.getElementById('header').style.display = 'none';
    document.getElementById('summaryCard').style.display = 'none';
    document.getElementById('packagesContainer').innerHTML = '';
    
    const errorCard = document.getElementById('errorCard');
    document.getElementById('errorMessage').textContent = message;
    errorCard.style.display = 'block';
}

// 刷新数据
async function refreshData() {
    const btn = document.getElementById('refreshBtn');
    btn.disabled = true;
    
    await queryFlow();
    
    setTimeout(() => {
        btn.disabled = false;
    }, 2000);
}

// 重置统计周期
async function resetStats() {
    if (!confirm('确定要重置统计周期吗？\n重置后将以当前查询结果作为新的基准点。')) {
        return;
    }
    
    try {
        showLoading('正在重置...');
        
        const response = await fetch(`/query.php?action=reset_baseline&token=${encodeURIComponent(currentToken)}`, {
            method: 'POST'
        });
        
        const result = await response.json();
        hideLoading();
        
        if (result.success) {
            alert('✅ 统计周期已重置');
            queryFlow();
        } else {
            alert('❌ 重置失败：' + (result.message || '未知错误'));
        }
    } catch (error) {
        hideLoading();
        alert('❌ 网络错误：' + error.message);
    }
}

// 打开配置弹窗
async function openConfigModal() {
    try {
        // 加载配置
        showLoading('加载配置中...');
        const response = await fetch(`/query.php?action=get_config&token=${encodeURIComponent(currentToken)}`);
        const result = await response.json();
        hideLoading();
        
        if (!result.success) {
            alert('加载配置失败：' + (result.message || '未知错误'));
            return;
        }
        
        const config = result.data || {};
        const notifyParams = config.notify_params || {};
        
        // 填充通知配置
        const notifyEnabled = document.getElementById('notifyEnabled');
        const notifyType = document.getElementById('notifyType');
        const notifyTitle = document.getElementById('notifyTitle');
        const notifySubtitle = document.getElementById('notifySubtitle');
        const notifyContent = document.getElementById('notifyContent');
        const notifyThreshold = document.getElementById('notifyThreshold');
        const queryInterval = document.getElementById('queryInterval');
        
        if (notifyEnabled) notifyEnabled.checked = config.notify_enabled === 1;
        if (notifyType) notifyType.value = config.notify_type || '';
        if (notifyTitle) notifyTitle.value = config.notify_title || '联通流量提醒';
        if (notifySubtitle) notifySubtitle.value = config.notify_subtitle || '';
        if (notifyContent) notifyContent.value = config.notify_content || '';
        if (notifyThreshold) notifyThreshold.value = config.notify_threshold || 0;
        if (queryInterval) queryInterval.value = config.query_interval || 30;
        
        // 动态生成通知参数表单
        updateNotifyParamsForm(config.notify_type, notifyParams);
        
        // 填充用户配置
        const userNickname = document.getElementById('userNickname');
        const userPassword = document.getElementById('userPassword');
        const userAuthType = document.getElementById('userAuthType');
        const userAppid = document.getElementById('userAppid');
        const userTokenOnline = document.getElementById('userTokenOnline');
        const userCookie = document.getElementById('userCookie');
        
        if (userNickname) userNickname.value = config.nickname || '';
        if (userPassword) userPassword.value = config.query_password || '';
        if (userAuthType) userAuthType.value = config.auth_type || '';
        if (userAppid) userAppid.value = config.appid || '';
        if (userTokenOnline) userTokenOnline.value = config.token_online || '';
        
        // 填充Cookie
        if (userCookie) {
            if (config.auth_type === 'token_online') {
                // Token方式：显示本次查询使用的Cookie（如果有）
                userCookie.value = (currentData && currentData.cookie) ? currentData.cookie : '';
            } else {
                // Cookie方式：显示用户保存的Cookie
                userCookie.value = config.cookie || '';
            }
        }
        
        // 根据认证方式显示/隐藏字段（会再次更新Cookie框状态）
        updateAuthFields();
        
        // 显示弹窗
        const modal = document.getElementById('configModal');
        if (modal) {
            modal.classList.add('show');
        } else {
            throw new Error('找不到配置弹窗元素 (configModal)');
        }
    } catch (error) {
        hideLoading();
        alert('加载配置失败：' + error.message);
    }
}

// 打开配置弹窗并切换到用户配置
async function openConfigModalToUser() {
    await openConfigModal();
    switchConfigTab('user');
}

// 关闭配置弹窗
function closeConfigModal() {
    document.getElementById('configModal').classList.remove('show');
}

// 切换配置标签页
function switchConfigTab(tab) {
    currentTab = tab;
    
    // 更新按钮状态
    document.getElementById('tabNotify').classList.toggle('active', tab === 'notify');
    document.getElementById('tabUser').classList.toggle('active', tab === 'user');
    
    // 切换内容
    document.getElementById('notifyConfigTab').style.display = tab === 'notify' ? 'block' : 'none';
    document.getElementById('userConfigTab').style.display = tab === 'user' ? 'block' : 'none';
}

// 通知类型改变时动态生成表单
function onNotifyTypeChange() {
    const notifyType = document.getElementById('notifyType').value;
    updateNotifyParamsForm(notifyType, {});
}

// 动态生成通知参数表单
function updateNotifyParamsForm(notifyType, params = {}) {
    const paramsDiv = document.getElementById('notifyParams');
    paramsDiv.innerHTML = '';
    
    if (!notifyType) {
        return;
    }
    
    let fields = [];
    
    switch (notifyType) {
        case 'telegram':
            fields = [
                { name: 'bot_token', label: 'Bot Token', placeholder: '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11' },
                { name: 'chat_id', label: 'Chat ID', placeholder: '123456789' }
            ];
            break;
        case 'wecom':
            fields = [
                { name: 'webhook', label: 'Webhook URL', placeholder: 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx' }
            ];
            break;
        case 'serverchan':
            fields = [
                { name: 'key', label: 'SendKey', placeholder: 'SCTxxxxx' }
            ];
            break;
        case 'dingtalk':
            fields = [
                { name: 'webhook', label: 'Webhook URL', placeholder: 'https://oapi.dingtalk.com/robot/send?access_token=xxx' },
                { name: 'secret', label: 'Secret (可选)', placeholder: 'SECxxx' }
            ];
            break;
        case 'pushplus':
            fields = [
                { name: 'token', label: 'Token', placeholder: 'xxx' }
            ];
            break;
    }
    
    fields.forEach(field => {
        const group = document.createElement('div');
        group.className = 'form-group';
        
        const label = document.createElement('label');
        label.className = 'form-label';
        label.textContent = field.label;
        group.appendChild(label);
        
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-input';
        input.id = `notify_param_${field.name}`;
        input.placeholder = field.placeholder;
        input.value = params[field.name] || '';
        group.appendChild(input);
        
        paramsDiv.appendChild(group);
    });
}

// 认证方式改变时切换字段显示
function onAuthTypeChange() {
    updateAuthFields();
}

// 根据认证方式显示/隐藏字段
function updateAuthFields() {
    const authType = document.getElementById('userAuthType').value;
    const tokenGroup = document.getElementById('tokenGroup');
    const userCookie = document.getElementById('userCookie');
    const cookieLabel = document.getElementById('cookieLabel');
    const cookieHint = document.getElementById('cookieHint');
    
    if (!tokenGroup || !userCookie) {
        console.error('认证字段容器未找到');
        return;
    }
    
    if (authType === 'token_online') {
        // Token方式：显示AppID和Token，Cookie框只读
        tokenGroup.style.display = 'block';
        userCookie.readOnly = true;
        userCookie.style.background = '#f5f5f5';
        userCookie.style.cursor = 'pointer';
        if (cookieLabel) cookieLabel.textContent = '本次查询使用的 Cookie';
        if (cookieHint) cookieHint.textContent = 'Token方式自动生成的Cookie（只读）';
        
        // 如果有查询数据，填充Cookie
        if (currentData && currentData.cookie) {
            userCookie.value = currentData.cookie;
        }
    } else if (authType === 'cookie') {
        // Cookie方式：隐藏Token字段，Cookie框可编辑
        tokenGroup.style.display = 'none';
        userCookie.readOnly = false;
        userCookie.style.background = '';
        userCookie.style.cursor = '';
        if (cookieLabel) cookieLabel.textContent = 'Cookie';
        if (cookieHint) cookieHint.textContent = 'Cookie 可以更新，建议定期更新以保持有效性';
    }
}

// 保存通知配置
async function saveNotifyConfig() {
    try {
        const notifyType = document.getElementById('notifyType').value;
        
        // 从动态生成的表单中收集参数
        const notifyParams = {};
        document.querySelectorAll('#notifyParams input').forEach(input => {
            const paramName = input.id.replace('notify_param_', '');
            notifyParams[paramName] = input.value.trim();
        });
        
        const data = {
            notify_enabled: document.getElementById('notifyEnabled').checked ? 1 : 0,
            notify_type: notifyType,
            notify_params: notifyParams,
            notify_title: document.getElementById('notifyTitle').value,
            notify_subtitle: document.getElementById('notifySubtitle').value,
            notify_content: document.getElementById('notifyContent').value,
            notify_threshold: parseInt(document.getElementById('notifyThreshold').value) || 0,
            query_interval: parseInt(document.getElementById('queryInterval').value) || 30
        };
        
        showLoading('保存通知配置...');
        const response = await fetch(`/query.php?action=save_notify_config&token=${encodeURIComponent(currentToken)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        hideLoading();
        
        if (result.success) {
            let message = '✅ 通知配置已保存';
            if (result.cron_info) {
                message += '\n\n' + result.cron_info;
            }
            alert(message);
            closeConfigModal();
        } else {
            alert('❌ 保存失败：' + (result.message || '未知错误'));
        }
    } catch (error) {
        hideLoading();
        alert('❌ 保存失败：' + error.message);
    }
}

// 保存用户配置
async function saveUserConfig() {
    try {
        const authType = document.getElementById('userAuthType').value;
        const data = {
            nickname: document.getElementById('userNickname').value,
            query_password: document.getElementById('userPassword').value
        };
        
        if (authType === 'token_online') {
            data.appid = document.getElementById('userAppid').value.trim();
            data.token_online = document.getElementById('userTokenOnline').value.trim();
        } else if (authType === 'cookie') {
            data.cookie = document.getElementById('userCookie').value.trim();
        }
        
        showLoading('保存用户配置...');
        const response = await fetch(`/query.php?action=save_user_config&token=${encodeURIComponent(currentToken)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        hideLoading();
        
        if (result.success) {
            alert('✅ 用户配置已保存');
            closeConfigModal();
            // 重新查询
            queryFlow();
        } else {
            alert('❌ 保存失败：' + (result.message || '未知错误'));
        }
    } catch (error) {
        hideLoading();
        alert('❌ 保存失败：' + error.message);
    }
}

// 复制Cookie
function copyCookie() {
    const userCookie = document.getElementById('userCookie');
    if (!userCookie || !userCookie.value) {
        alert('❌ 暂无可复制的Cookie，请先完成一次流量查询');
        return;
    }
    
    userCookie.select();
    
    try {
        // 尝试使用现代API
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(userCookie.value).then(() => {
                alert('✅ Cookie已复制到剪贴板');
            }).catch(() => {
                // 降级到旧方法
                document.execCommand('copy');
                alert('✅ Cookie已复制到剪贴板');
            });
        } else {
            // 使用旧方法
            document.execCommand('copy');
            alert('✅ Cookie已复制到剪贴板');
        }
    } catch (error) {
        alert('❌ 复制失败：' + error.message);
    }
}

// 测试通知
async function testNotification() {
    try {
        const notifyType = document.getElementById('notifyType').value;
        
        if (!notifyType) {
            alert('❌ 请先选择通知方式');
            return;
        }
        
        // 从动态生成的表单中收集参数
        const notifyParams = {};
        document.querySelectorAll('#notifyParams input').forEach(input => {
            const paramName = input.id.replace('notify_param_', '');
            notifyParams[paramName] = input.value.trim();
        });
        
        // 验证必要参数
        const requiredParams = {
            'telegram': ['bot_token', 'chat_id'],
            'wecom': ['webhook'],
            'serverchan': ['key'],
            'dingtalk': ['webhook'],
            'pushplus': ['token']
        };
        
        const required = requiredParams[notifyType] || [];
        const missing = [];
        
        for (const param of required) {
            if (!notifyParams[param] || notifyParams[param] === '') {
                missing.push(param);
            }
        }
        
        if (missing.length > 0) {
            alert('❌ 请填写必需参数: ' + missing.join(', '));
            return;
        }
        
        const data = {
            notify_type: notifyType,
            notify_params: notifyParams,
            notify_title: document.getElementById('notifyTitle').value,
            notify_subtitle: document.getElementById('notifySubtitle').value,
            notify_content: document.getElementById('notifyContent').value
        };
        
        showLoading('发送测试通知...');
        const response = await fetch(`/query.php?action=test_notify&token=${encodeURIComponent(currentToken)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        hideLoading();
        
        if (result.success) {
            alert('✅ 测试通知已发送\n\n请检查您的通知渠道是否收到消息');
        } else {
            alert('❌ 发送失败：' + (result.message || '未知错误'));
        }
    } catch (error) {
        hideLoading();
        alert('❌ 发送失败：' + error.message);
    }
}

// 确认删除用户
async function confirmDeleteUser() {
    if (!confirm('⚠️ 警告！\n\n确定要删除此用户吗？\n删除后将无法恢复，所有配置和数据将被清除！')) {
        return;
    }
    
    if (!confirm('🚨 最终确认\n\n请再次确认是否删除用户？')) {
        return;
    }
    
    try {
        showLoading('删除用户中...');
        const response = await fetch(`/query.php?action=delete_user&token=${encodeURIComponent(currentToken)}`, {
            method: 'POST'
        });
        
        const result = await response.json();
        hideLoading();
        
        if (result.success) {
            alert('✅ 用户已删除');
            window.location.href = '/';
        } else {
            alert('❌ 删除失败：' + (result.message || '未知错误'));
        }
    } catch (error) {
        hideLoading();
        alert('❌ 删除失败：' + error.message);
    }
}

// 工具函数：格式化流量
function formatFlow(mb) {
    if (mb >= 1024) {
        return (mb / 1024).toFixed(2) + ' GB';
    }
    return mb.toFixed(2) + ' MB';
}

// 工具函数：格式化余额
function formatBalance(balance) {
    if (typeof balance === 'string') {
        balance = parseFloat(balance);
    }
    return balance.toFixed(2);
}

// 工具函数：格式化时间间隔
function formatTimeInterval(seconds) {
    if (seconds < 60) {
        return seconds + ' 秒';
    } else if (seconds < 3600) {
        return Math.floor(seconds / 60) + ' 分钟';
    } else if (seconds < 86400) {
        return Math.floor(seconds / 3600) + ' 小时';
    } else {
        return Math.floor(seconds / 86400) + ' 天';
    }
}

// 工具函数：转义HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
