    </div>
    
    <script src="/assets/js/app.js?v=<?php echo time(); ?>"></script>
    <script>
    // 通用辅助函数
    function showMessage(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; padding: 16px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); animation: slideIn 0.3s ease-out;';
        
        const colors = {
            success: { bg: '#d1fae5', text: '#065f46', border: '#10b981' },
            error: { bg: '#fee2e2', text: '#991b1b', border: '#ef4444' },
            warning: { bg: '#fef3c7', text: '#92400e', border: '#f59e0b' },
            info: { bg: '#dbeafe', text: '#1e40af', border: '#3b82f6' }
        };
        
        const color = colors[type] || colors.info;
        alertDiv.style.background = color.bg;
        alertDiv.style.color = color.text;
        alertDiv.style.borderLeft = `4px solid ${color.border}`;
        
        alertDiv.textContent = message;
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => alertDiv.remove(), 300);
        }, 3000);
    }
    
    function setLoading(button, loading) {
        if (loading) {
            button.dataset.originalText = button.textContent;
            button.textContent = '处理中...';
            button.disabled = true;
            button.style.opacity = '0.6';
        } else {
            button.textContent = button.dataset.originalText || button.textContent;
            button.disabled = false;
            button.style.opacity = '1';
        }
    }
    
    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                showMessage('已复制到剪贴板', 'success');
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
            showMessage('已复制到剪贴板', 'success');
        } catch (err) {
            showMessage('复制失败', 'error');
        }
        document.body.removeChild(textarea);
    }
    
    // 添加动画样式
    if (!document.getElementById('alertAnimations')) {
        const style = document.createElement('style');
        style.id = 'alertAnimations';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(400px); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(400px); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    }
    </script>
</body>
</html>
