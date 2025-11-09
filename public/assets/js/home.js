/* 首页脚本 - 联通流量查询系统 */
        let currentLink = '';
        let registerResultLink = '';
        
        // 切换标签页
        function switchTab(tabName) {
            // 更新按钮状态
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // 更新面板显示
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.remove('active');
            });
            document.getElementById(tabName + '-panel').classList.add('active');
        }
        
        // 切换注册认证方式字段
        function toggleRegAuthFields() {
            const authType = document.getElementById('reg_auth_type').value;
            const tokenFields = document.getElementById('reg_token_online_fields');
            const cookieFields = document.getElementById('reg_cookie_fields');
            const appidInput = document.getElementById('reg_appid');
            const tokenInput = document.getElementById('reg_token_online');
            const cookieInput = document.getElementById('reg_cookie');
            
            if (authType === 'token_online') {
                tokenFields.style.display = 'block';
                cookieFields.style.display = 'none';
                appidInput.required = true;
                tokenInput.required = true;
                cookieInput.required = false;
            } else {
                tokenFields.style.display = 'none';
                cookieFields.style.display = 'block';
                appidInput.required = false;
                tokenInput.required = false;
                cookieInput.required = true;
            }
        }
        
        // 处理查询请求
        async function handleQuery(event) {
            event.preventDefault();
            
            const btn = document.getElementById('queryBtn');
            const resultBox = document.getElementById('queryResult');
            const errorBox = document.getElementById('queryError');
            
            // 隐藏之前的结果
            resultBox.classList.remove('show');
            errorBox.classList.remove('show');
            
            // 禁用按钮
            btn.disabled = true;
            btn.textContent = '查询中...';
            
            const formData = new FormData(event.target);
            
            try {
                const response = await fetch('/index.php?action=queryToken', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        mobile: formData.get('mobile'),
                        password: formData.get('password')
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const link = window.location.origin + '/query.php?token=' + result.data.token;
                    currentLink = link;
                    document.getElementById('queryLink').textContent = link;
                    resultBox.classList.add('show');
                } else {
                    errorBox.textContent = '❌ ' + result.message;
                    errorBox.classList.add('show');
                }
            } catch (error) {
                errorBox.textContent = '❌ 查询失败：' + error.message;
                errorBox.classList.add('show');
            } finally {
                btn.disabled = false;
                btn.textContent = '🔍 查询链接';
            }
        }
        
        // 处理注册请求
        async function handleRegister(event) {
            event.preventDefault();
            
            const btn = document.getElementById('registerBtn');
            const resultBox = document.getElementById('registerResult');
            const errorBox = document.getElementById('registerError');
            
            // 隐藏之前的结果
            resultBox.classList.remove('show');
            errorBox.classList.remove('show');
            
            // 禁用按钮
            btn.disabled = true;
            btn.textContent = '验证中...';
            
            const formData = new FormData(event.target);
            const authType = formData.get('auth_type');
            
            const data = {
                mobile: formData.get('mobile'),
                query_password: formData.get('query_password'),
                nickname: formData.get('nickname') || '',
                auth_type: authType
            };
            
            // 根据认证方式添加不同字段
            if (authType === 'token_online') {
                data.appid = formData.get('appid');
                data.token_online = formData.get('token_online');
            } else {
                data.cookie = formData.get('cookie');
            }
            
            // 添加邀请码（如果有）
            const inviteCode = formData.get('invite_code');
            if (inviteCode) {
                data.invite_code = inviteCode;
            }
            
            try {
                const response = await fetch('/index.php?action=register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const link = window.location.origin + '/query.php?token=' + result.data.token;
                    registerResultLink = link;
                    document.getElementById('registerLink').textContent = link;
                    resultBox.classList.add('show');
                    
                    // 清空表单
                    event.target.reset();
                    // 重置认证方式字段显示
                    toggleRegAuthFields();
                } else {
                    errorBox.textContent = '❌ ' + result.message;
                    errorBox.classList.add('show');
                }
            } catch (error) {
                errorBox.textContent = '❌ 注册失败：' + error.message;
                errorBox.classList.add('show');
            } finally {
                btn.disabled = false;
                btn.textContent = '✨ 验证并注册';
            }
        }
        
        // 复制链接
        function copyLink() {
            navigator.clipboard.writeText(currentLink).then(() => {
                alert('✅ 链接已复制到剪贴板');
            }).catch(() => {
                // 降级方案
                const textarea = document.createElement('textarea');
                textarea.value = currentLink;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('✅ 链接已复制到剪贴板');
            });
        }
        
        // 跳转到查询页面
        function gotoLink() {
            window.location.href = currentLink;
        }
        
        // 复制注册结果链接
        function copyRegisterLink() {
            navigator.clipboard.writeText(registerResultLink).then(() => {
                alert('✅ 链接已复制到剪贴板');
            }).catch(() => {
                const textarea = document.createElement('textarea');
                textarea.value = registerResultLink;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('✅ 链接已复制到剪贴板');
            });
        }
        
        // 跳转到注册结果页面
        function gotoRegisterLink() {
            window.location.href = registerResultLink;
        }
