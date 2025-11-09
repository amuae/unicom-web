<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装完成</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>🎉 安装完成</h1>
        <p class="text-center text-muted mb-20">系统已成功安装</p>
        
        <div class="alert alert-success">
            恭喜！联通流量查询系统已成功安装
        </div>
        
        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-bottom: 16px;">✓ 安装清单</h3>
            <p>✓ 环境检查通过</p>
            <p>✓ 数据库初始化完成</p>
            <p>✓ 管理员账号创建成功</p>
            <p>✓ 系统配置完成</p>
        </div>
        
        <div style="background: #fff3cd; padding: 16px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;">
            <h4 style="margin-bottom: 8px;">⚠️ 安全提示</h4>
            <ul style="margin-left: 20px;">
                <li>请立即修改管理员密码（使用强密码）</li>
                <li>定期备份数据库文件（database/unicom_flow.db）</li>
                <li>建议配置HTTPS访问</li>
                <li>定期检查系统日志（storage/logs/）</li>
            </ul>
        </div>
        
        <div style="background: #fff8e1; padding: 16px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ff9800;">
            <h4 style="margin-bottom: 8px;">🔧 定时任务配置（重要）</h4>
            <p style="margin-bottom: 12px;">为了让定时任务正常工作，需要配置sudo权限和文件权限。我们提供了一键配置脚本和手动配置两种方式：</p>
            
            <div style="background: #e8f5e9; padding: 16px; border-radius: 8px; margin: 12px 0; border-left: 4px solid #4caf50;">
                <h4 style="color: #2e7d32; margin-bottom: 12px;">🚀 推荐方式：一键自动配置</h4>
                <p style="color: #666; margin-bottom: 12px;">运行自动配置脚本，一键完成所有权限设置：</p>
                <div style="position: relative;">
                    <pre id="autoSetupCmd" style="background: #fff; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 13px; margin: 0; border: 1px solid #c8e6c9;">cd <?= dirname(__DIR__, 3) . "\n" ?>sudo bash scripts/setup_permissions.sh</pre>
                    <button onclick="copyToClipboard('autoSetupCmd')" style="position: absolute; top: 12px; right: 12px; padding: 6px 14px; background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                        📋 复制
                    </button>
                </div>
                <p style="color: #666; margin-top: 12px; font-size: 13px; line-height: 1.8;">
                    ✨ <strong>此脚本将自动完成以下配置：</strong><br>
                    • 自动检测Web服务器用户（www-data/apache/nginx）<br>
                    • 配置sudoers权限（允许Web用户执行crontab命令）<br>
                    • 设置数据库文件权限（0664）和所有者<br>
                    • 设置脚本目录和文件权限（0755）<br>
                    • 验证所有配置是否成功
                </p>
                <p style="color: #2e7d32; margin-top: 12px; font-size: 13px; background: #fff; padding: 8px; border-radius: 4px;">
                    💡 <strong>提示：</strong>如果脚本检测到多个Web服务器用户，将提示您选择要使用的用户。
                </p>
            </div>
            
            <div style="background: #f5f5f5; padding: 16px; border-radius: 8px; margin: 12px 0;">
                <h4 style="color: #555; margin-bottom: 12px;">📝 手动配置方式</h4>
                <p style="color: #666; margin-bottom: 12px;">如果自动脚本无法运行，请按以下步骤手动配置：</p>
            </div>
            
            <div style="background: #fff; padding: 12px; border-radius: 4px; margin: 12px 0;">
                <p style="font-weight: bold; margin-bottom: 8px;">步骤1：使用sudoers.d配置文件（推荐）</p>
                <p style="font-size: 14px; color: #666; margin-bottom: 8px;">创建独立的sudoers配置文件，方便管理和维护：</p>
                <div style="position: relative;">
                    <pre id="sudoersCmd" style="background: #f5f5f5; padding: 8px; border-radius: 4px; overflow-x: auto; font-size: 12px; margin: 0;">echo "# Allow www-data to manage crontab for automated tasks
www-data ALL=(ALL) NOPASSWD: /usr/bin/crontab" > /etc/sudoers.d/unicom-cron && \
chmod 440 /etc/sudoers.d/unicom-cron && \
echo "✓ sudoers配置已创建" && \
cat /etc/sudoers.d/unicom-cron</pre>
                    <button onclick="copyToClipboard('sudoersCmd')" style="position: absolute; top: 8px; right: 8px; padding: 4px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                        📋 复制
                    </button>
                </div>
            </div>
            
            <div style="background: #fff; padding: 12px; border-radius: 4px; margin: 12px 0;">
                <p style="font-weight: bold; margin-bottom: 8px;">步骤2：或者直接编辑sudoers文件</p>
                <div style="position: relative;">
                    <pre id="visudoCmd" style="background: #f5f5f5; padding: 8px; border-radius: 4px; overflow-x: auto; margin: 0;">sudo visudo</pre>
                    <button onclick="copyToClipboard('visudoCmd')" style="position: absolute; top: 8px; right: 8px; padding: 4px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                        📋 复制
                    </button>
                </div>
                <p style="font-size: 14px; color: #666; margin-top: 8px;">然后在文件末尾添加：</p>
                <div style="position: relative;">
                    <pre id="visudoContent" style="background: #f5f5f5; padding: 8px; border-radius: 4px; overflow-x: auto; margin: 0; font-size: 12px;"># Allow www-data to manage crontab for automated tasks
www-data ALL=(ALL) NOPASSWD: /usr/bin/crontab</pre>
                    <button onclick="copyToClipboard('visudoContent')" style="position: absolute; top: 8px; right: 8px; padding: 4px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                        📋 复制
                    </button>
                </div>
            </div>
            
            <div style="background: #fff; padding: 12px; border-radius: 4px; margin: 12px 0;">
                <p style="font-weight: bold; margin-bottom: 8px;">步骤3：验证sudo配置是否生效</p>
                <div style="position: relative;">
                    <pre id="testSudoCmd" style="background: #f5f5f5; padding: 8px; border-radius: 4px; overflow-x: auto; font-size: 12px; margin: 0;">sudo -u www-data sudo crontab -l 2>&1 | head -5 && \
echo "" && \
echo "✓ www-data用户可以执行crontab命令"</pre>
                    <button onclick="copyToClipboard('testSudoCmd')" style="position: absolute; top: 8px; right: 8px; padding: 4px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                        📋 复制
                    </button>
                </div>
                <p style="font-size: 13px; color: #666; margin-top: 8px;">
                    如果没有报错，说明配置成功。如果提示"no crontab for www-data"是正常的（表示还没有创建定时任务）。
                </p>
            </div>
            
            <div style="background: #fff; padding: 12px; border-radius: 4px; margin: 12px 0;">
                <p style="font-weight: bold; margin-bottom: 8px;">步骤4：设置文件权限</p>
                <p style="font-size: 14px; color: #666; margin-bottom: 8px;">确保数据库和脚本文件具有正确的权限：</p>
                <div style="position: relative;">
                    <pre id="permCmd" style="background: #f5f5f5; padding: 8px; border-radius: 4px; overflow-x: auto; font-size: 12px; margin: 0;">cd <?= dirname(__DIR__, 3) . "\n" ?>
sudo chmod -R 755 scripts/cron/
sudo chown -R www-data:www-data database/
sudo chmod 664 database/unicom_flow.db
sudo chown www-data:www-data database/unicom_flow.db</pre>
                    <button onclick="copyToClipboard('permCmd')" style="position: absolute; top: 8px; right: 8px; padding: 4px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                        📋 复制
                    </button>
                </div>
            </div>
            
            <div style="background: #ffebee; padding: 12px; border-radius: 4px; margin: 12px 0; border-left: 4px solid #f44336;">
                <p style="font-weight: bold; margin-bottom: 8px; color: #c62828;">⚠️ 注意事项：</p>
                <ul style="margin-left: 20px; font-size: 14px; color: #666;">
                    <li>请确认您的Web服务器用户是 <code>www-data</code>，如果不是，请替换为实际用户（如 apache, nginx 等）</li>
                    <li>sudoers配置错误可能导致系统无法使用sudo，请小心操作</li>
                    <li>使用 <code>visudo</code> 编辑时，如果语法错误会有提示，不会保存错误配置</li>
                    <li>如果不配置sudo权限，定时任务功能将无法使用，但不影响手动查询功能</li>
                </ul>
            </div>
            
            <div style="background: #e8f5e9; padding: 12px; border-radius: 4px; margin: 12px 0; border-left: 4px solid #4caf50;">
                <p style="font-weight: bold; margin-bottom: 8px; color: #2e7d32;">💡 远程部署提示：</p>
                <p style="font-size: 14px; color: #666; margin-bottom: 8px;">
                    如果您需要远程部署到服务器，可以使用SSH执行以上命令。例如：
                </p>
                <div style="position: relative;">
                    <pre id="sshCmd" style="background: #f5f5f5; padding: 8px; border-radius: 4px; overflow-x: auto; font-size: 11px; margin: 0;">ssh -p PORT user@host 'echo "# Allow www-data to manage crontab for automated tasks
www-data ALL=(ALL) NOPASSWD: /usr/bin/crontab" > /etc/sudoers.d/unicom-cron && \
chmod 440 /etc/sudoers.d/unicom-cron && \
echo "✓ sudoers配置已创建"'</pre>
                    <button onclick="copyToClipboard('sshCmd')" style="position: absolute; top: 8px; right: 8px; padding: 4px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                        📋 复制
                    </button>
                </div>
                <p style="font-size: 13px; color: #666; margin-top: 8px;">
                    将 <code>PORT</code>, <code>user</code>, <code>host</code> 替换为您的实际SSH连接信息。
                </p>
            </div>
        </div>
        
        <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    showCopySuccess();
                }).catch(() => {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        }
        
        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showCopySuccess();
            } catch (err) {
                alert('复制失败，请手动复制');
            }
            document.body.removeChild(textarea);
        }
        
        function showCopySuccess() {
            const toast = document.createElement('div');
            toast.textContent = '✓ 已复制到剪贴板';
            toast.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #4caf50; color: white; padding: 12px 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 10000; animation: slideInRight 0.3s ease-out;';
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 2000);
        }
        </script>
        
        <style>
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        </style>
        
        <div style="background: #e3f2fd; padding: 16px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196f3;">
            <h4 style="margin-bottom: 8px;">ℹ️ 重要说明</h4>
            <ul style="margin-left: 20px;">
                <li>安装完成后，<code>install.php</code> 将被锁定</li>
                <li>如需重新安装，请删除 <code>storage/install.lock</code> 文件</li>
                <li>重新安装将清空所有数据，请谨慎操作</li>
            </ul>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 24px;">
            <a href="/index.php" class="btn btn-secondary" style="text-align: center;">
                前往前台
            </a>
            <a href="/admin.php" class="btn btn-primary" style="text-align: center;">
                进入后台管理 →
            </a>
        </div>
        
        <p class="text-center text-muted mt-20">
            联通流量查询系统 v2.0
        </p>
    </div>
    
    <script src="/assets/js/app.js"></script>
</body>
</html>
