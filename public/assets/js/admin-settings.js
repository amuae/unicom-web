/* 管理员设置页面脚本 - 联通流量查询系统 */
// 标签切换
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

// 保存基本设置
document.getElementById('basicForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveSettings('basic', new FormData(this));
});

// 保存通知设置
document.getElementById('notifyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveSettings('notify', new FormData(this));
});

// 保存安全设置
document.getElementById('securityForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    // 验证密码
    if (formData.get('new_password') !== formData.get('confirm_password')) {
        showMessage('两次输入的密码不一致', 'error');
        return;
    }
    
    // 转换为JSON
    const data = {
        old_password: formData.get('old_password'),
        new_password: formData.get('new_password'),
        confirm_password: formData.get('confirm_password')
    };
    
    fetch('/admin.php?action=changePassword', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            this.reset();
        } else {
            showMessage(data.message, 'error');
        }
    });
});

// 保存系统设置
document.getElementById('systemForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveSettings('system', new FormData(this));
});

function saveSettings(type, formData) {
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    fetch('/admin.php?action=saveSettings', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ type, settings: data })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
        } else {
            showMessage(data.message, 'error');
        }
    });
}

function testNotify() {
    // 让用户选择要测试的通知类型
    const types = [
        { value: 'telegram', label: 'Telegram' },
        { value: 'wecom', label: '企业微信' },
        { value: 'serverchan', label: 'Server酱' },
        { value: 'dingtalk', label: '钉钉' },
        { value: 'pushplus', label: 'PushPlus' }
    ];
    
    const options = types.map(t => `<option value="${t.value}">${t.label}</option>`).join('');
    const html = `
        <div style="padding: 20px;">
            <p>请选择要测试的通知渠道：</p>
            <select id="notifyType" style="width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">
                ${options}
            </select>
        </div>
    `;
    
    if (!confirm('确认发送测试通知？\n\n请确保已正确配置相应渠道的参数。')) return;
    
    // 简单的类型选择
    const type = prompt('请输入通知类型：\n1. telegram\n2. wecom\n3. serverchan\n4. dingtalk\n5. pushplus', 'telegram');
    if (!type) return;
    
    fetch('admin.php?action=testNotify', { 
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest' 
        },
        body: JSON.stringify({ type })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMessage('测试通知已发送，请检查您的设备', 'success');
            } else {
                showMessage(data.message, 'error');
            }
        })
        .catch(err => {
            showMessage('请求失败: ' + err.message, 'error');
        });
}

function backupDatabase() {
    if (!confirm('确认备份数据库？')) return;
    
    fetch('/admin.php?action=backupDatabase', { 
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
            } else {
                showMessage(data.message, 'error');
            }
        });
}

function clearAllData() {
    const confirmed = prompt('此操作将清空所有数据且不可恢复！\n请输入 "DELETE ALL" 确认：');
    if (confirmed !== 'DELETE ALL') return;
    
    fetch('/admin.php?action=clearAllData', { 
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest' 
        },
        body: JSON.stringify({ confirm: true })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                setTimeout(() => location.href = '/install.php', 2000);
            } else {
                showMessage(data.message, 'error');
            }
        });
}

function clearCache() {
    fetch('/admin.php?action=clearCache', { 
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
            } else {
                showMessage(data.message, 'error');
            }
        });
}
