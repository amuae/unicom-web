/**
 * 开发者工具防护脚本（增强版）
 * 多重防护机制，禁用开发者工具访问
 */
(function() {
    'use strict';
    
    // ===== 1. 禁用右键菜单 =====
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    }, true);
    
    // ===== 2. 禁用快捷键 =====
    document.addEventListener('keydown', function(e) {
        // F12 - 开发者工具
        if (e.keyCode === 123) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        // Ctrl+Shift+I - 开发者工具
        if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        // Ctrl+Shift+J - 控制台
        if (e.ctrlKey && e.shiftKey && e.keyCode === 74) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        // Ctrl+U - 查看源代码
        if (e.ctrlKey && e.keyCode === 85) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        // Ctrl+Shift+C - 元素选择器
        if (e.ctrlKey && e.shiftKey && e.keyCode === 67) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        // Ctrl+S - 保存页面
        if (e.ctrlKey && e.keyCode === 83) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        // Ctrl+P - 打印
        if (e.ctrlKey && e.keyCode === 80) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }, true);
    
    // ===== 3. 禁用选择和复制（可选） =====
    document.addEventListener('selectstart', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return true; // 允许输入框选择
        }
        e.preventDefault();
        return false;
    });
    
    // ===== 4. 检测开发者工具 - 方法1：窗口尺寸检测 =====
    let devtoolsOpen = false;
    const threshold = 160;
    
    function detectDevToolsBySize() {
        const widthThreshold = window.outerWidth - window.innerWidth > threshold;
        const heightThreshold = window.outerHeight - window.innerHeight > threshold;
        
        if (widthThreshold || heightThreshold) {
            if (!devtoolsOpen) {
                devtoolsOpen = true;
                handleDevToolsOpen();
            }
        }
    }
    
    // ===== 5. 检测开发者工具 - 方法2：toString检测 =====
    const element = new Image();
    Object.defineProperty(element, 'id', {
        get: function() {
            devtoolsOpen = true;
            handleDevToolsOpen();
        }
    });
    
    setInterval(function() {
        console.log(element);
        console.clear();
    }, 1000);
    
    // ===== 6. 检测开发者工具 - 方法3：debugger检测 =====
    function detectDevToolsByDebugger() {
        const start = new Date();
        debugger;
        const end = new Date();
        if (end - start > 100) {
            devtoolsOpen = true;
            handleDevToolsOpen();
        }
    }
    
    // ===== 7. 检测开发者工具 - 方法4：Firebug检测 =====
    function detectDevToolsByFirebug() {
        if (window.console && (window.console.firebug || window.console.table && /firebug/i.test(window.console.table()))) {
            devtoolsOpen = true;
            handleDevToolsOpen();
        }
    }
    
    // ===== 8. 检测开发者工具 - 方法5：Chrome DevTools检测 =====
    function detectDevToolsByChrome() {
        const checkStatus = function() {
            if (typeof window.chrome !== 'undefined' && typeof window.chrome.runtime !== 'undefined') {
                const startTime = new Date();
                debugger;
                const endTime = new Date();
                if (endTime - startTime > 100) {
                    devtoolsOpen = true;
                    handleDevToolsOpen();
                }
            }
        };
        checkStatus();
    }
    
    // ===== 9. 处理开发者工具打开 =====
    function handleDevToolsOpen() {
        // 清空页面内容
        document.body.innerHTML = `
            <div style="
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100vh;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-align: center;
                padding: 20px;
            ">
                <div style="
                    background: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                    border-radius: 20px;
                    padding: 40px;
                    max-width: 500px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                ">
                    <div style="font-size: 64px; margin-bottom: 20px;">⚠️</div>
                    <div style="font-size: 28px; font-weight: bold; margin-bottom: 15px;">检测到开发者工具</div>
                    <div style="font-size: 16px; line-height: 1.8; opacity: 0.9;">
                        为了保护系统安全和用户隐私，<br>
                        页面已停止运行。<br><br>
                        请关闭开发者工具后刷新页面。
                    </div>
                    <button onclick="location.reload()" style="
                        margin-top: 30px;
                        padding: 12px 32px;
                        background: white;
                        color: #667eea;
                        border: none;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: transform 0.2s;
                    " onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        刷新页面
                    </button>
                </div>
            </div>
        `;
        
        // 停止所有脚本执行
        throw new Error('Developer tools detected');
    }
    
    // ===== 10. 重写console方法 =====
    (function() {
        const noop = function() {};
        const methods = ['log', 'debug', 'info', 'warn', 'error', 'table', 'trace', 'dir', 'dirxml', 'group', 'groupCollapsed', 'groupEnd', 'clear', 'count', 'assert', 'profile', 'profileEnd'];
        const consoleBackup = {};
        
        methods.forEach(function(method) {
            consoleBackup[method] = console[method];
            console[method] = function() {
                // 检测是否打开了开发者工具
                devtoolsOpen = true;
            };
        });
        
        // 显示警告信息
        setTimeout(function() {
            consoleBackup.log('%c⚠️ 警 告', 'color: red; font-size: 50px; font-weight: bold; text-shadow: 3px 3px 0 rgba(0,0,0,0.2);');
            consoleBackup.log('%c此浏览器功能仅供开发者使用！', 'color: #ff6b6b; font-size: 20px; font-weight: bold; margin: 10px 0;');
            consoleBackup.log('%c如果有人告诉您在此复制/粘贴内容，这是诈骗行为，会导致您的账号被盗！', 'color: #ffa500; font-size: 18px; margin: 10px 0;');
            consoleBackup.log('%c危险警告：', 'color: #ff4757; font-size: 16px; font-weight: bold;');
            consoleBackup.log('%c• 账号可能被盗\n• 个人信息泄露\n• 财产损失\n• 恶意代码执行', 'color: #ff4757; font-size: 14px; line-height: 2;');
            consoleBackup.log('%c如需帮助，请联系官方客服', 'color: #4CAF50; font-size: 16px; font-weight: bold; margin: 10px 0;');
        }, 100);
    })();
    
    // ===== 11. 定期检测 =====
    setInterval(detectDevToolsBySize, 500);
    setInterval(detectDevToolsByDebugger, 1000);
    setInterval(detectDevToolsByFirebug, 1000);
    setInterval(detectDevToolsByChrome, 1000);
    
    // ===== 12. 页面可见性检测 =====
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            detectDevToolsBySize();
        }
    });
    
    // ===== 13. 禁止iframe嵌入 =====
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }
    
    // ===== 14. 监听window.onerror =====
    window.addEventListener('error', function() {
        return true;
    }, true);
    
    // ===== 15. 初始检测 =====
    setTimeout(function() {
        detectDevToolsBySize();
        detectDevToolsByDebugger();
    }, 500);
    
})();
