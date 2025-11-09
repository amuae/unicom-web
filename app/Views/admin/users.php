<?php include __DIR__ . '/header.php'; ?>

<h2>👥 用户管理</h2>

<?php if (isset($flash)): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<div style="margin: 20px 0; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <button class="btn btn-primary" onclick="openAddUserModal()">➕ 添加用户</button>
    </div>
    <div>
        <input type="text" placeholder="搜索手机号..." style="width: 300px;" id="searchInput">
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>手机号</th>
            <th>状态</th>
            <th>最后查询</th>
            <th>创建时间</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($users)): ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= htmlspecialchars($user['mobile']) ?></td>
                    <td>
                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                                     background: <?= $user['status'] === 'active' ? '#d1fae5' : '#fee2e2' ?>; 
                                     color: <?= $user['status'] === 'active' ? '#065f46' : '#991b1b' ?>;">
                            <?= $user['status'] === 'active' ? '活跃' : '禁用' ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        if (!empty($user['last_query_time'])) {
                            echo htmlspecialchars($user['last_query_time']);
                        } elseif (!empty($user['last_query_at'])) {
                            echo is_numeric($user['last_query_at']) 
                                ? date('Y-m-d H:i:s', $user['last_query_at']) 
                                : htmlspecialchars($user['last_query_at']);
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        echo !empty($user['created_at']) 
                            ? (is_numeric($user['created_at']) 
                                ? date('Y-m-d H:i:s', $user['created_at']) 
                                : htmlspecialchars($user['created_at']))
                            : '-';
                        ?>
                    </td>
                    <td>
                        <button class="btn btn-success" style="padding: 6px 12px; font-size: 12px;" 
                                onclick="queryFlow(<?= $user['id'] ?>)">查询</button>
                        <button class="btn btn-info" style="padding: 6px 12px; font-size: 12px;" 
                                onclick="showToken('<?= htmlspecialchars($user['token'] ?? '') ?>', <?= $user['id'] ?>)">Token</button>
                        <button class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" 
                                onclick="editUser(<?= $user['id'] ?>)">编辑</button>
                        <button class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" 
                                onclick="deleteUser(<?= $user['id'] ?>)">删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" class="text-center text-muted">暂无用户</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- 添加用户模态框 -->
<div id="addUserModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
                                background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 24px; border-radius: 8px; width: 600px; max-width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>添加用户</h3>
            <button onclick="closeAddUserModal()" style="border: none; background: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        
        <form id="addUserForm">
            <div class="form-group">
                <label>认证方式 *</label>
                <select id="authType" name="auth_type" onchange="toggleAuthFields()" required>
                    <option value="token_online">token_online</option>
                    <option value="cookie">cookie</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>手机号 *</label>
                <input type="tel" name="mobile" id="mobile" placeholder="请输入11位手机号" 
                       pattern="1[3-9]\d{9}" required>
            </div>
            
            <div class="form-group">
                <label>昵称（可选）</label>
                <input type="text" name="nickname" id="nickname" placeholder="用户昵称">
            </div>
            
            <div class="form-group">
                <label>查询密码 *</label>
                <input type="text" name="query_password" id="queryPassword" 
                       placeholder="用户在本平台查询信息时使用的密码" required>
                <small style="color: #666;">用于用户在本平台查询自己的流量信息</small>
            </div>
            
            <!-- Token Online 字段 -->
            <div id="tokenOnlineFields">
                <div class="form-group">
                    <label>AppID *</label>
                    <input type="text" name="appid" id="appid" placeholder="联通AppID">
                </div>
                
                <div class="form-group">
                    <label>Token Online *</label>
                    <textarea name="token_online" id="tokenOnline" rows="3" 
                              placeholder="联通Token Online" style="font-family: monospace; font-size: 12px;"></textarea>
                    <small style="color: #666;">从联通APP抓包获取</small>
                </div>
            </div>
            
            <!-- Cookie 字段 -->
            <div id="cookieFields" style="display: none;">
                <div class="form-group">
                    <label>Cookie *</label>
                    <textarea name="cookie" id="cookie" rows="4" 
                              placeholder="联通Cookie" style="font-family: monospace; font-size: 12px;"></textarea>
                    <small style="color: #666;">从联通APP抓包获取，有效期较短</small>
                </div>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">取消</button>
                <button type="submit" class="btn btn-primary" id="addUserBtn">验证并添加</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑用户模态框 -->
<div id="editUserModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
                                background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 24px; border-radius: 8px; width: 600px; max-width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>编辑用户</h3>
            <button onclick="closeEditUserModal()" style="border: none; background: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        
        <form id="editUserForm">
            <input type="hidden" name="user_id" id="editUserId">
            
            <div class="form-group">
                <label>手机号</label>
                <input type="text" id="editMobile" disabled style="background: #f3f4f6;">
            </div>
            
            <div class="form-group">
                <label>昵称（可选）</label>
                <input type="text" name="nickname" id="editNickname" placeholder="用户昵称">
            </div>
            
            <div class="form-group">
                <label>查询密码 *</label>
                <input type="text" name="query_password" id="editQueryPassword" 
                       placeholder="用户在本平台查询信息时使用的密码" required>
                <small style="color: #666;">用于用户在本平台查询自己的流量信息</small>
            </div>
            
            <div class="form-group">
                <label>状态</label>
                <select name="status" id="editStatus">
                    <option value="active">活跃</option>
                    <option value="inactive">禁用</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>认证方式</label>
                <select id="editAuthType" name="auth_type" onchange="toggleEditAuthFields()">
                    <option value="token_online">token_online</option>
                    <option value="cookie">cookie</option>
                </select>
            </div>
            
            <!-- Token Online 字段 -->
            <div id="editTokenOnlineFields">
                <div class="form-group">
                    <label>AppID</label>
                    <input type="text" name="appid" id="editAppid" placeholder="联通AppID">
                </div>
                
                <div class="form-group">
                    <label>Token Online</label>
                    <textarea name="token_online" id="editTokenOnline" rows="3" 
                              placeholder="联通Token Online" style="font-family: monospace; font-size: 12px;"></textarea>
                </div>
            </div>
            
            <!-- Cookie 字段 -->
            <div id="editCookieFields" style="display: none;">
                <div class="form-group">
                    <label>Cookie</label>
                    <textarea name="cookie" id="editCookie" rows="4" 
                              placeholder="联通Cookie" style="font-family: monospace; font-size: 12px;"></textarea>
                </div>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">取消</button>
                <button type="submit" class="btn btn-primary" id="editUserBtn">保存修改</button>
            </div>
        </form>
    </div>
</div>

<!-- Token弹框 -->
<div id="tokenModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
                             background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 24px; border-radius: 8px; width: 500px; max-width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">用户专属查询链接</h3>
            <button onclick="closeTokenModal()" style="border: none; background: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>
        
        <div style="background: #f8f9fa; padding: 16px; border-radius: 6px; margin-bottom: 20px;">
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 6px; color: #495057;">查询链接：</label>
                <div style="background: white; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 13px; 
                            word-break: break-all; border: 1px solid #dee2e6;" id="tokenUrl"></div>
            </div>
            
            <div>
                <label style="display: block; font-weight: 500; margin-bottom: 6px; color: #495057;">Token：</label>
                <div style="background: white; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 14px; 
                            letter-spacing: 1px; border: 1px solid #dee2e6;" id="tokenValue"></div>
            </div>
        </div>
        
        <div style="color: #6c757d; font-size: 13px; margin-bottom: 20px; line-height: 1.6;">
            <p style="margin: 0 0 8px 0;">💡 用户可通过此链接直接查看自己的流量信息，无需登录。</p>
            <p style="margin: 0;">🔒 请妥善保管Token，不要泄露给他人。</p>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button onclick="copyTokenUrl()" class="btn btn-primary" style="flex: 1;">
                📋 复制链接
            </button>
            <button onclick="openTokenUrl()" class="btn btn-success" style="flex: 1;">
                🔗 访问页面
            </button>
        </div>
    </div>
</div>

<script>
function openAddUserModal() {
    document.getElementById('addUserModal').style.display = 'flex';
    document.getElementById('addUserForm').reset();
    toggleAuthFields();
}

function closeAddUserModal() {
    document.getElementById('addUserModal').style.display = 'none';
}

function toggleAuthFields() {
    const authType = document.getElementById('authType').value;
    const tokenOnlineFields = document.getElementById('tokenOnlineFields');
    const cookieFields = document.getElementById('cookieFields');
    
    if (authType === 'token_online') {
        tokenOnlineFields.style.display = 'block';
        cookieFields.style.display = 'none';
        
        document.getElementById('appid').required = true;
        document.getElementById('tokenOnline').required = true;
        document.getElementById('cookie').required = false;
    } else {
        tokenOnlineFields.style.display = 'none';
        cookieFields.style.display = 'block';
        
        document.getElementById('appid').required = false;
        document.getElementById('tokenOnline').required = false;
        document.getElementById('cookie').required = true;
    }
}

// 添加用户表单提交
document.getElementById('addUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('addUserBtn');
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    // 移除空字段
    Object.keys(data).forEach(key => {
        if (!data[key]) delete data[key];
    });
    
    setLoading(btn, true);
    
    fetch('/admin.php?action=addUser', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(data => {
        setLoading(btn, false);
        if (data.success) {
            showMessage('用户添加成功！正在刷新...', 'success');
            closeAddUserModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage('添加失败: ' + data.message, 'error');
        }
    })
    .catch(err => {
        setLoading(btn, false);
        showMessage('请求失败: ' + err.message, 'error');
    });
});

function editUser(userId) {
    // 获取用户信息
    fetch(`/admin.php?action=getUser&id=${userId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                showMessage('获取用户信息失败: ' + data.message, 'error');
                return;
            }
            
            const user = data.data;
            
            // 填充表单基本信息
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editMobile').value = user.mobile;
            document.getElementById('editNickname').value = user.nickname || '';
            document.getElementById('editQueryPassword').value = user.query_password || '';
            document.getElementById('editStatus').value = user.status;
            
            // 设置认证方式并直接填充当前凭证值到输入框
            if (user.has_token_online) {
                document.getElementById('editAuthType').value = 'token_online';
                // 直接填充当前值到输入框
                document.getElementById('editAppid').value = user.appid || '';
                document.getElementById('editTokenOnline').value = user.token_online || '';
            } else if (user.has_cookie) {
                document.getElementById('editAuthType').value = 'cookie';
                // 直接填充当前值到输入框
                document.getElementById('editCookie').value = user.cookie || '';
            }
            
            // 切换字段显示
            toggleEditAuthFields();
            
            // 显示模态框
            document.getElementById('editUserModal').style.display = 'flex';
        })
        .catch(err => {
            showMessage('请求失败: ' + err.message, 'error');
        });
}

function closeEditUserModal() {
    document.getElementById('editUserModal').style.display = 'none';
    document.getElementById('editUserForm').reset();
}

function toggleEditAuthFields() {
    const authType = document.getElementById('editAuthType').value;
    const tokenFields = document.getElementById('editTokenOnlineFields');
    const cookieFields = document.getElementById('editCookieFields');
    
    if (authType === 'token_online') {
        tokenFields.style.display = 'block';
        cookieFields.style.display = 'none';
    } else {
        tokenFields.style.display = 'none';
        cookieFields.style.display = 'block';
    }
}

// 提交编辑表单
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('editUserBtn');
    setLoading(btn, true);
    
    const formData = new FormData(this);
    const data = {};
    
    // 必须先添加user_id
    const userId = document.getElementById('editUserId').value;
    if (!userId) {
        showMessage('用户ID丢失，请重新打开编辑窗口', 'error');
        setLoading(btn, false);
        return;
    }
    data['user_id'] = userId;
    
    // 添加其他非空字段
    formData.forEach((value, key) => {
        if (key !== 'user_id' && value && value.trim()) {
            data[key] = value;
        }
    });
    
    // 调试：输出将要提交的数据
    console.log('提交数据:', data);
    
    fetch('/admin.php?action=updateUser', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(data => {
        setLoading(btn, false);
        if (data.success) {
            showMessage('用户更新成功！正在刷新...', 'success');
            closeEditUserModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage('更新失败: ' + data.message, 'error');
        }
    })
    .catch(err => {
        setLoading(btn, false);
        showMessage('请求失败: ' + err.message, 'error');
    });
});

// Token相关功能
let currentToken = '';
let currentTokenUrl = '';

function showToken(token, userId) {
    if (!token) {
        showMessage('该用户没有Token，请重新添加用户', 'error');
        return;
    }
    
    currentToken = token;
    // 使用当前域名和协议
    const protocol = window.location.protocol;
    const host = window.location.host;
    currentTokenUrl = `${protocol}//${host}/query.php?token=${token}`;
    
    document.getElementById('tokenValue').textContent = token;
    document.getElementById('tokenUrl').textContent = currentTokenUrl;
    document.getElementById('tokenModal').style.display = 'flex';
}

function closeTokenModal() {
    document.getElementById('tokenModal').style.display = 'none';
}

function copyTokenUrl() {
    copyToClipboard(currentTokenUrl, '链接已复制到剪贴板！');
}

function openTokenUrl() {
    window.open(currentTokenUrl, '_blank');
}

function queryFlow(userId) {
    if (!confirm('确认立即查询该用户的流量？')) return;
    
    fetch('/admin.php?action=queryUserFlow', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ user_id: userId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage('查询成功！' + data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage('查询失败: ' + data.message, 'error');
        }
    });
}

function deleteUser(userId) {
    if (!confirm('确认删除该用户？此操作不可恢复！')) return;
    
    fetch('/admin.php?action=deleteUser', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ user_id: userId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage('删除成功', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage('删除失败: ' + data.message, 'error');
        }
    });
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
