<?php include __DIR__ . '/header.php'; ?>

<h2>📋 系统日志</h2>

<!-- 筛选器 -->
<div style="background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
        <div>
            <label style="display: block; font-size: 12px; margin-bottom: 4px; color: #666;">日志类型</label>
            <select id="typeFilter" onchange="filterLogs()">
                <option value="">全部类型</option>
                <option value="system">系统日志</option>
                <option value="admin">管理员操作</option>
                <option value="user">用户操作</option>
                <option value="cron">定时任务</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 12px; margin-bottom: 4px; color: #666;">日志级别</label>
            <select id="levelFilter" onchange="filterLogs()">
                <option value="">全部级别</option>
                <option value="debug">Debug</option>
                <option value="info">Info</option>
                <option value="warning">Warning</option>
                <option value="error">Error</option>
                <option value="critical">Critical</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 12px; margin-bottom: 4px; color: #666;">时间范围</label>
            <select id="timeFilter" onchange="applyFilters()">
                <option value="today">今天</option>
                <option value="yesterday">昨天</option>
                <option value="week">最近7天</option>
                <option value="month">最近30天</option>
                <option value="all" selected>全部</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 12px; margin-bottom: 4px; color: #666;">搜索</label>
            <input type="text" id="searchInput" placeholder="搜索日志内容..." onkeyup="filterLogs()">
        </div>
    </div>
    <div style="margin-top: 12px; display: flex; gap: 8px;">
        <button class="btn btn-secondary" onclick="refreshLogs()">🔄 刷新</button>
        <button class="btn btn-danger" onclick="clearOldLogs()">🗑️ 清理旧日志</button>
        <button class="btn" onclick="exportLogs()">📥 导出</button>
    </div>
</div>

<!-- 统计卡片 -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px;">
    <div style="background: #dbeafe; padding: 12px; border-radius: 6px;">
        <div style="font-size: 11px; color: #666;">总日志数</div>
        <div style="font-size: 20px; font-weight: bold; color: #1e40af;"><?= number_format($stats['total'] ?? 0) ?></div>
    </div>
    <div style="background: #d1fae5; padding: 12px; border-radius: 6px;">
        <div style="font-size: 11px; color: #666;">Info</div>
        <div style="font-size: 20px; font-weight: bold; color: #065f46;"><?= number_format($stats['info'] ?? 0) ?></div>
    </div>
    <div style="background: #fef3c7; padding: 12px; border-radius: 6px;">
        <div style="font-size: 11px; color: #666;">Warning</div>
        <div style="font-size: 20px; font-weight: bold; color: #92400e;"><?= number_format($stats['warning'] ?? 0) ?></div>
    </div>
    <div style="background: #fee2e2; padding: 12px; border-radius: 6px;">
        <div style="font-size: 11px; color: #666;">Error</div>
        <div style="font-size: 20px; font-weight: bold; color: #991b1b;"><?= number_format($stats['error'] ?? 0) ?></div>
    </div>
</div>

<table id="logsTable">
    <thead>
        <tr>
            <th width="60">ID</th>
            <th width="100">级别</th>
            <th width="100">类型</th>
            <th>消息</th>
            <th width="120">IP地址</th>
            <th width="150">时间</th>
            <th width="80">操作</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($logs)): ?>
            <?php foreach ($logs as $log): ?>
                <?php
                $levelColors = [
                    'debug' => ['bg' => '#f3f4f6', 'text' => '#374151'],
                    'info' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
                    'warning' => ['bg' => '#fef3c7', 'text' => '#92400e'],
                    'error' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
                    'critical' => ['bg' => '#fecaca', 'text' => '#7f1d1d']
                ];
                $color = $levelColors[$log['log_level']] ?? $levelColors['info'];
                ?>
                <tr data-type="<?= htmlspecialchars($log['log_type']) ?>" 
                    data-level="<?= htmlspecialchars($log['log_level']) ?>">
                    <td><?= $log['id'] ?></td>
                    <td>
                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 500;
                                     background: <?= $color['bg'] ?>; color: <?= $color['text'] ?>;">
                            <?= strtoupper($log['log_level']) ?>
                        </span>
                    </td>
                    <td>
                        <span style="padding: 4px 8px; background: #e5e7eb; border-radius: 4px; font-size: 11px;">
                            <?= htmlspecialchars($log['log_type']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="max-width: 500px; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars($log['message']) ?>
                        </div>
                        <?php if ($log['context']): ?>
                            <button onclick="showContext(<?= $log['id'] ?>)" 
                                    style="border: none; background: none; color: #3b82f6; cursor: pointer; font-size: 11px;">
                                查看详情
                            </button>
                        <?php endif; ?>
                    </td>
                    <td><code style="font-size: 11px;"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></code></td>
                    <td style="font-size: 12px;"><?= date('Y-m-d H:i:s', is_numeric($log['created_at']) ? $log['created_at'] : strtotime($log['created_at'])) ?></td>
                    <td>
                        <button class="btn" style="padding: 4px 8px; font-size: 11px;" 
                                onclick="viewLog(<?= $log['id'] ?>)">详情</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="text-center text-muted">暂无日志记录</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- 分页 -->
<?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
<div style="margin-top: 20px; display: flex; justify-content: center; align-items: center; gap: 8px;">
    <?php if ($pagination['page'] > 1): ?>
        <a href="?action=logs&page=1" class="btn">首页</a>
        <a href="?action=logs&page=<?= $pagination['page'] - 1 ?>" class="btn">上一页</a>
    <?php endif; ?>
    
    <span style="padding: 0 16px;">
        第 <?= $pagination['page'] ?> / <?= $pagination['total_pages'] ?> 页 
        (共 <?= number_format($pagination['total']) ?> 条)
    </span>
    
    <?php if ($pagination['page'] < $pagination['total_pages']): ?>
        <a href="?action=logs&page=<?= $pagination['page'] + 1 ?>" class="btn">下一页</a>
        <a href="?action=logs&page=<?= $pagination['total_pages'] ?>" class="btn">末页</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 日志详情模态框 -->
<div id="logDetailModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
                                background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 24px; border-radius: 8px; width: 700px; max-width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3>日志详情</h3>
            <button onclick="closeLogDetail()" style="border: none; background: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div id="logDetailContent"></div>
    </div>
</div>

<script>
function filterLogs() {
    const type = document.getElementById('typeFilter').value;
    const level = document.getElementById('levelFilter').value;
    const search = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#logsTable tbody tr');
    
    rows.forEach(row => {
        if (row.querySelector('td[colspan]')) return;
        
        const rowType = row.getAttribute('data-type');
        const rowLevel = row.getAttribute('data-level');
        const message = row.textContent.toLowerCase();
        
        const typeMatch = !type || rowType === type;
        const levelMatch = !level || rowLevel === level;
        const searchMatch = !search || message.includes(search);
        
        row.style.display = (typeMatch && levelMatch && searchMatch) ? '' : 'none';
    });
}

function applyFilters() {
    const time = document.getElementById('timeFilter').value;
    window.location.href = `?action=logs&time=${time}`;
}

function refreshLogs() {
    location.reload();
}

function clearOldLogs() {
    const days = prompt('清理多少天前的日志？', '30');
    if (!days) return;
    
    if (!confirm(`确认清理${days}天前的日志？此操作不可恢复！`)) return;
    
    fetch('/admin.php?action=cleanLogs', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ days: parseInt(days) })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage(data.message, 'error');
        }
    });
}

function exportLogs() {
    showMessage('导出功能开发中...', 'info');
}

function viewLog(id) {
    fetch(`/admin.php?action=getLog&id=${id}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const log = data.data;
                const html = `
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px; background: #f3f4f6; width: 120px;"><strong>ID</strong></td>
                            <td style="padding: 8px;">${log.id}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; background: #f3f4f6;"><strong>类型</strong></td>
                            <td style="padding: 8px;">${log.log_type}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; background: #f3f4f6;"><strong>级别</strong></td>
                            <td style="padding: 8px;">${log.log_level}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; background: #f3f4f6;"><strong>消息</strong></td>
                            <td style="padding: 8px;">${log.message}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; background: #f3f4f6;"><strong>上下文</strong></td>
                            <td style="padding: 8px;"><pre style="background: #f9fafb; padding: 8px; overflow-x: auto;">${log.context || '-'}</pre></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; background: #f3f4f6;"><strong>IP地址</strong></td>
                            <td style="padding: 8px;">${log.ip_address || '-'}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; background: #f3f4f6;"><strong>User Agent</strong></td>
                            <td style="padding: 8px; word-break: break-all;">${log.user_agent || '-'}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; background: #f3f4f6;"><strong>时间</strong></td>
                            <td style="padding: 8px;">${log.created_at}</td>
                        </tr>
                    </table>
                `;
                document.getElementById('logDetailContent').innerHTML = html;
                document.getElementById('logDetailModal').style.display = 'flex';
            } else {
                showMessage(data.message, 'error');
            }
        });
}

function showContext(id) {
    viewLog(id);
}

function closeLogDetail() {
    document.getElementById('logDetailModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
