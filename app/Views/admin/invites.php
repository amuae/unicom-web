<?php include __DIR__ . '/header.php'; ?>

<h2>🎟️ 邀请码管理</h2>

<?php if (isset($flash)): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h3>生成邀请码</h3>
    <form id="generateForm" method="POST" action="/admin.php?action=generateInviteCodes">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <div class="form-group" style="margin: 0;">
                <label>邀请码类型 *</label>
                <select name="type" id="invite_type" required onchange="toggleMaxUsage()">
                    <option value="single">一次性邀请码</option>
                    <option value="multiple">多次邀请码</option>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>生成数量 *</label>
                <input type="number" name="count" value="10" min="1" max="1000" required>
            </div>
            
            <div class="form-group" style="margin: 0;" id="max_usage_group">
                <label>最大使用次数 *</label>
                <input type="number" name="max_usage" id="max_usage" value="10" min="2" max="9999">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>有效期（天）*</label>
                <input type="number" name="expire_days" value="30" min="0" max="365" required>
                <small style="color: #666;">0表示永久有效</small>
            </div>
        </div>
        
        <div class="form-group">
            <label>备注说明</label>
            <input type="text" name="remark" placeholder="可选，例如：活动推广码、内测邀请等">
        </div>
        
        <button type="submit" class="btn btn-primary" id="generateBtn">生成邀请码</button>
    </form>
</div>

<!-- 筛选 -->
<div style="margin: 20px 0; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
    <select id="typeFilter" onchange="filterInvites()" style="padding: 8px;">
        <option value="">全部类型</option>
        <option value="single">一次性</option>
        <option value="multiple">多次</option>
    </select>
    
    <select id="statusFilter" onchange="filterInvites()" style="padding: 8px;">
        <option value="">全部状态</option>
        <option value="active">已启用</option>
        <option value="disabled">已禁用</option>
    </select>
    
    <input type="text" id="searchInput" placeholder="搜索邀请码或备注..." style="flex: 1; min-width: 200px; padding: 8px;" onkeyup="filterInvites()">
    
    <button class="btn btn-secondary" onclick="resetFilters()">🔄 重置筛选</button>
</div>

<!-- 批量操作 -->
<div style="margin: 20px 0; display: flex; gap: 12px; align-items: center;">
    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
        <span>全选</span>
    </label>
    <span id="selectedCount" style="color: #666;">已选择 0 项</span>
    <div style="flex: 1;"></div>
    <button class="btn btn-success" onclick="batchUpdateStatus('active')" id="batchEnableBtn" disabled>✓ 批量启用</button>
    <button class="btn btn-secondary" onclick="batchUpdateStatus('disabled')" id="batchDisableBtn" disabled>✗ 批量禁用</button>
    <button class="btn btn-danger" onclick="batchDelete()" id="batchDeleteBtn" disabled>🗑️ 批量删除</button>
</div>

<table id="inviteTable">
    <thead>
        <tr>
            <th width="40"><input type="checkbox" id="selectAllTable" onchange="toggleSelectAll()"></th>
            <th>ID</th>
            <th>邀请码</th>
            <th>类型</th>
            <th>状态</th>
            <th>使用情况</th>
            <th>有效期</th>
            <th>备注</th>
            <th>创建时间</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($invites)): ?>
            <?php foreach ($invites as $invite): ?>
                <tr data-type="<?= htmlspecialchars($invite['type']) ?>" data-status="<?= htmlspecialchars($invite['status']) ?>" data-id="<?= $invite['id'] ?>">
                    <td>
                        <input type="checkbox" class="invite-checkbox" value="<?= $invite['id'] ?>" onchange="updateSelectedCount()">
                    </td>
                    <td><?= $invite['id'] ?></td>
                    <td>
                        <code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; user-select: all;">
                            <?= htmlspecialchars($invite['code']) ?>
                        </code>
                        <button onclick="copyToClipboard('<?= htmlspecialchars($invite['code']) ?>')" 
                                style="border: none; background: none; cursor: pointer; padding: 4px;" title="复制">📋</button>
                    </td>
                    <td>
                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                                     background: <?= $invite['type'] === 'single' ? '#e0e7ff' : '#fef3c7' ?>; 
                                     color: <?= $invite['type'] === 'single' ? '#3730a3' : '#92400e' ?>;">
                            <?= $invite['type'] === 'single' ? '一次性' : '多次' ?>
                        </span>
                    </td>
                    <td>
                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                                     background: <?= $invite['status'] === 'active' ? '#d1fae5' : '#fee2e2' ?>; 
                                     color: <?= $invite['status'] === 'active' ? '#065f46' : '#991b1b' ?>;">
                            <?= $invite['status'] === 'active' ? '已启用' : '已禁用' ?>
                        </span>
                    </td>
                    <td>
                        <span><?= $invite['used_count'] ?> / <?= $invite['max_usage'] ?></span>
                        <?php if ($invite['type'] === 'multiple'): ?>
                            <button onclick="editMaxUsage(<?= $invite['id'] ?>, <?= $invite['used_count'] ?>, <?= $invite['max_usage'] ?>)" 
                                    style="border: none; background: none; cursor: pointer; color: #3b82f6;" title="修改上限">✏️</button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($invite['expire_at']): ?>
                            <?= date('Y-m-d', $invite['expire_at']) ?>
                            <?php if ($invite['expire_at'] < time()): ?>
                                <span style="color: #ef4444;">（已过期）</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #10b981;">永久</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($invite['remark'] ?? '-') ?></td>
                    <td>
                        <?php 
                        echo !empty($invite['created_at']) 
                            ? (is_numeric($invite['created_at']) 
                                ? date('Y-m-d H:i', $invite['created_at']) 
                                : date('Y-m-d H:i', strtotime($invite['created_at'])))
                            : '-';
                        ?>
                    </td>
                    <td>
                        <?php if ($invite['status'] === 'active'): ?>
                            <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" 
                                    onclick="toggleStatus(<?= $invite['id'] ?>, 'disabled')">禁用</button>
                        <?php else: ?>
                            <button class="btn btn-success" style="padding: 6px 12px; font-size: 12px;" 
                                    onclick="toggleStatus(<?= $invite['id'] ?>, 'active')">启用</button>
                        <?php endif; ?>
                        <button class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" 
                                onclick="deleteInvite(<?= $invite['id'] ?>)">删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="10" class="text-center text-muted">暂无邀请码</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<script>
// 切换类型时显示/隐藏最大使用次数
function toggleMaxUsage() {
    const type = document.getElementById('invite_type').value;
    const maxUsageGroup = document.getElementById('max_usage_group');
    const maxUsageInput = document.getElementById('max_usage');
    
    if (type === 'single') {
        maxUsageGroup.style.display = 'none';
        maxUsageInput.removeAttribute('required');
    } else {
        maxUsageGroup.style.display = 'block';
        maxUsageInput.setAttribute('required', 'required');
    }
}

// 页面加载时调用一次
toggleMaxUsage();

// 生成邀请码表单提交
document.getElementById('generateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('generateBtn');
    setLoading(btn, true);
    
    const formData = new FormData(this);
    
    fetch('/admin.php?action=generateInviteCodes', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        setLoading(btn, false);
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(err => {
        setLoading(btn, false);
        showMessage('请求失败: ' + err.message, 'error');
    });
});

// 切换状态
function toggleStatus(id, status) {
    const text = status === 'active' ? '启用' : '禁用';
    if (!confirm(`确认${text}该邀请码？`)) return;
    
    fetch('/admin.php?action=updateInviteStatus', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id, status })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
    });
}

// 修改使用上限
function editMaxUsage(id, usedCount, currentMax) {
    const newMax = prompt(`修改使用上限\n已使用: ${usedCount}次\n当前上限: ${currentMax}次\n\n请输入新的上限（必须大于已使用次数）:`, currentMax);
    
    if (newMax === null) return;
    
    const maxUsage = parseInt(newMax);
    if (isNaN(maxUsage) || maxUsage < usedCount) {
        showMessage('使用上限不能小于已使用次数', 'error');
        return;
    }
    
    fetch('/admin.php?action=updateInviteMaxUsage', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id, max_usage: maxUsage })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
    });
}

// 删除邀请码
function deleteInvite(id) {
    if (!confirm('确认删除该邀请码？此操作不可恢复！')) return;
    
    fetch('/admin.php?action=deleteInviteCode', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
    });
}

// 筛选功能
function filterInvites() {
    const typeFilter = document.getElementById('typeFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    const searchText = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#inviteTable tbody tr');
    
    let visibleCount = 0;
    
    rows.forEach(row => {
        if (row.querySelector('td')?.getAttribute('colspan')) {
            row.style.display = 'none'; // 隐藏"暂无数据"行
            return;
        }
        
        const type = row.getAttribute('data-type');
        const status = row.getAttribute('data-status');
        const code = row.querySelector('code')?.textContent.toLowerCase() || '';
        const remark = row.cells[7]?.textContent.toLowerCase() || ''; // 备注列（因为多了checkbox列，索引+1）
        
        const typeMatch = !typeFilter || type === typeFilter;
        const statusMatch = !statusFilter || status === statusFilter;
        const searchMatch = !searchText || code.includes(searchText) || remark.includes(searchText);
        
        if (typeMatch && statusMatch && searchMatch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
            // 隐藏时取消选中
            const checkbox = row.querySelector('.invite-checkbox');
            if (checkbox) checkbox.checked = false;
        }
    });
    
    // 如果没有匹配的结果，显示提示
    const tbody = document.querySelector('#inviteTable tbody');
    let noResultRow = tbody.querySelector('.no-result');
    
    if (visibleCount === 0) {
        if (!noResultRow) {
            noResultRow = document.createElement('tr');
            noResultRow.className = 'no-result';
            noResultRow.innerHTML = '<td colspan="10" class="text-center text-muted">未找到匹配的邀请码</td>';
            tbody.appendChild(noResultRow);
        }
        noResultRow.style.display = '';
    } else if (noResultRow) {
        noResultRow.style.display = 'none';
    }
    
    // 更新选中计数
    updateSelectedCount();
}

// 重置筛选
function resetFilters() {
    document.getElementById('typeFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('searchInput').value = '';
    filterInvites();
}

// 全选/取消全选
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const selectAllTable = document.getElementById('selectAllTable');
    const checkboxes = document.querySelectorAll('.invite-checkbox');
    
    // 同步两个全选框的状态
    if (event.target.id === 'selectAll') {
        selectAllTable.checked = selectAll.checked;
    } else {
        selectAll.checked = selectAllTable.checked;
    }
    
    const checked = selectAll.checked || selectAllTable.checked;
    checkboxes.forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = checked;
        }
    });
    updateSelectedCount();
}

// 更新选中计数
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.invite-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').textContent = `已选择 ${count} 项`;
    
    // 更新批量操作按钮状态
    const batchEnableBtn = document.getElementById('batchEnableBtn');
    const batchDisableBtn = document.getElementById('batchDisableBtn');
    const batchDeleteBtn = document.getElementById('batchDeleteBtn');
    
    batchEnableBtn.disabled = count === 0;
    batchDisableBtn.disabled = count === 0;
    batchDeleteBtn.disabled = count === 0;
    
    // 更新全选框状态
    const allCheckboxes = document.querySelectorAll('.invite-checkbox');
    const visibleCheckboxes = Array.from(allCheckboxes).filter(cb => cb.closest('tr').style.display !== 'none');
    const selectAll = document.getElementById('selectAll');
    const selectAllTable = document.getElementById('selectAllTable');
    
    if (visibleCheckboxes.length > 0) {
        const allChecked = visibleCheckboxes.every(cb => cb.checked);
        selectAll.checked = allChecked;
        selectAllTable.checked = allChecked;
    }
}

// 批量更新状态
function batchUpdateStatus(status) {
    const checkboxes = document.querySelectorAll('.invite-checkbox:checked');
    if (checkboxes.length === 0) {
        showMessage('请选择要操作的邀请码', 'warning');
        return;
    }
    
    const action = status === 'active' ? '启用' : '禁用';
    if (!confirm(`确认${action}选中的 ${checkboxes.length} 个邀请码？`)) return;
    
    const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    fetch('/admin.php?action=batchUpdateInviteStatus', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ ids, status })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
    });
}

// 批量删除
function batchDelete() {
    const checkboxes = document.querySelectorAll('.invite-checkbox:checked');
    if (checkboxes.length === 0) {
        showMessage('请选择要删除的邀请码', 'warning');
        return;
    }
    
    if (!confirm(`确认删除选中的 ${checkboxes.length} 个邀请码？此操作不可恢复！`)) return;
    
    const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    fetch('/admin.php?action=batchDeleteInviteCodes', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ ids })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
    });
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
