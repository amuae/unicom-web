<?php include __DIR__ . '/header.php'; ?>

<h2>⏰ 定时任务管理 (系统Cron)</h2>

<?php if (isset($flash)): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <button class="btn btn-secondary" onclick="refreshTaskList()">🔄 刷新</button>
        <button class="btn btn-primary" onclick="viewSystemCrontab()">📋 查看完整Crontab</button>
    </div>
</div>

<!-- 任务统计 -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
    <div style="background: #f0f9ff; padding: 16px; border-radius: 8px; border-left: 4px solid #0284c7;">
        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">总任务数</div>
        <div style="font-size: 24px; font-weight: bold; color: #0284c7;"><?= $stats['total'] ?? 0 ?></div>
    </div>
    <div style="background: #f0fdf4; padding: 16px; border-radius: 8px; border-left: 4px solid #16a34a;">
        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">活跃任务</div>
        <div style="font-size: 24px; font-weight: bold; color: #16a34a;"><?= $stats['active'] ?? 0 ?></div>
    </div>
    <div style="background: #fef3c7; padding: 16px; border-radius: 8px; border-left: 4px solid #f59e0b;">
        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">启用通知</div>
        <div style="font-size: 24px; font-weight: bold; color: #f59e0b;"><?= $stats['users_with_notify'] ?? 0 ?></div>
    </div>
    <div style="background: #e0e7ff; padding: 16px; border-radius: 8px; border-left: 4px solid #6366f1;">
        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">总间隔时间</div>
        <div style="font-size: 24px; font-weight: bold; color: #6366f1;"><?= $stats['total_intervals'] ?? 0 ?> 分钟</div>
    </div>
</div>

<p style="color: #666; margin-bottom: 16px;">
    ℹ️ 说明：这些任务直接由系统Cron管理，用户在专属页面配置通知后自动创建。
    任务标记格式：<code># unicom_flow_user_{用户ID}</code>
</p>

<table id="cronTable">
    <thead>
        <tr>
            <th>用户ID</th>
            <th>手机号</th>
            <th>Cron表达式</th>
            <th>查询间隔</th>
            <th>通知状态</th>
            <th>用户状态</th>
            <th>最后查询</th>
            <th>最后通知</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($tasks)): ?>
            <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><?= $task['user_id'] ?></td>
                    <td><strong><?= htmlspecialchars($task['user_mobile']) ?></strong></td>
                    <td><code><?= htmlspecialchars($task['cron_expression']) ?></code></td>
                    <td>
                        <span style="padding: 4px 8px; background: #dbeafe; border-radius: 4px; font-size: 12px;">
                            每 <?= $task['interval_minutes'] ?> 分钟
                        </span>
                    </td>
                    <td>
                        <?php if ($task['notify_enabled']): ?>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                                         background: #d1fae5; color: #065f46;">
                                ✅ 已启用
                            </span>
                        <?php else: ?>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                                         background: #fee2e2; color: #991b1b;">
                                ❌ 未启用
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $statusColor = [
                            'active' => ['bg' => '#d1fae5', 'text' => '#065f46', 'label' => '正常'],
                            'disabled' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'label' => '禁用'],
                        ];
                        $color = $statusColor[$task['user_status']] ?? ['bg' => '#e5e7eb', 'text' => '#374151', 'label' => '未知'];
                        ?>
                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                                     background: <?= $color['bg'] ?>; color: <?= $color['text'] ?>;">
                            <?= $color['label'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($task['last_query_time']): ?>
                            <small><?= date('Y-m-d H:i:s', $task['last_query_time']) ?></small>
                        <?php else: ?>
                            <span style="color: #9ca3af;">未查询</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($task['last_notify_time']): ?>
                            <small><?= date('Y-m-d H:i:s', $task['last_notify_time']) ?></small>
                        <?php else: ?>
                            <span style="color: #9ca3af;">未通知</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-danger" style="padding: 4px 8px; font-size: 12px;" 
                                onclick="deleteUserCronTask(<?= $task['user_id'] ?>)">🗑️ 删除</button>
                        <button class="btn btn-primary" style="padding: 4px 8px; font-size: 12px;" 
                                onclick="viewUserInfo(<?= $task['user_id'] ?>)">👤 查看用户</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="9" class="text-center text-muted">
                    暂无定时任务<br>
                    <small>用户在专属页面配置通知后会自动创建定时任务</small>
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- 系统Crontab查看模态框 -->
<div id="crontabModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
                                  background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 24px; border-radius: 8px; width: 800px; max-width: 90%; max-height: 80vh; overflow: auto;">
        <h3>完整的系统Crontab</h3>
        <pre id="crontabContent" style="background: #f3f4f6; padding: 16px; border-radius: 4px; overflow-x: auto;"></pre>
        <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="closeCrontabModal()">关闭</button>
        </div>
    </div>
</div>

<script>
function refreshTaskList() {
    location.reload();
}

function viewSystemCrontab() {
    fetch('/admin.php?action=getSystemCrontab', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('crontabContent').textContent = data.crontab || '(空)';
            document.getElementById('crontabModal').style.display = 'flex';
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(err => showMessage('获取失败: ' + err.message, 'error'));
}

function closeCrontabModal() {
    document.getElementById('crontabModal').style.display = 'none';
}

function deleteUserCronTask(userId) {
    if (!confirm(`确认删除用户 ${userId} 的定时任务？\n\n注意：用户下次保存通知配置时会自动重新创建。`)) return;
    
    fetch('/admin.php?action=deleteUserCronTask', {
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
            showMessage(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(err => showMessage('删除失败: ' + err.message, 'error'));
}

function viewUserInfo(userId) {
    window.location.href = '/admin.php?action=users&user_id=' + userId;
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
