<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['nickname'] ?: $user['mobile']) ?> - 流量查询</title>
    <link rel="stylesheet" href="/assets/css/query.css?v=<?= time() ?>">
</head>
<body data-token="<?= htmlspecialchars($token) ?>" data-user-token="<?= htmlspecialchars($user['token']) ?>">
    <!-- 加载动画 -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <div class="loading-text">正在查询流量...</div>
    </div>

    <div class="container">
        <!-- 头部信息 -->
        <div class="header fade-in" id="header" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div>
                    <div class="package-name" id="packageName" style="font-size: 18px; font-weight: 600; color: #333;">中国联通</div>
                    <div class="mobile-number" id="mobileNumber" style="font-size: 13px; color: #999; margin-top: 4px;"><?= htmlspecialchars(substr($user['mobile'], 0, 3) . '****' . substr($user['mobile'], -4)) ?></div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="header-icon-btn" onclick="openConfigModal()" title="用户配置">
                        <span>⚙️</span>
                    </button>
                    <button class="header-icon-btn" onclick="refreshData()" id="refreshBtn" title="刷新数据">
                        <span>🔄</span>
                    </button>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px;">
                <div style="background: #f8f9ff; border-radius: 8px; padding: 12px;">
                    <div style="font-size: 12px; color: #999; margin-bottom: 4px;">💰 话费余额</div>
                    <div style="font-size: 20px; font-weight: 600; color: #667eea;">
                        <span id="balanceAmount">--</span>
                        <span style="font-size: 12px; font-weight: normal; margin-left: 2px;">元</span>
                    </div>
                </div>
                <div style="background: #fff5f5; border-radius: 8px; padding: 12px;">
                    <div style="font-size: 12px; color: #999; margin-bottom: 4px;">📊 当月出账</div>
                    <div style="font-size: 20px; font-weight: 600; color: #f56c6c;">
                        <span id="monthlyFee">--</span>
                        <span style="font-size: 12px; font-weight: normal; margin-left: 2px;">元</span>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- 错误提示 -->
        <div class="error-card fade-in" id="errorCard" style="display: none;">
            <div class="error-title">❌ 查询失败</div>
            <div class="error-message" id="errorMessage"></div>
            <div style="margin-top: 16px; text-align: center;">
                <button onclick="openConfigModalToUser()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 10px 24px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity=\'0.9\'" onmouseout="this.style.opacity=\'1\'">
                    ⚙️ 修改认证信息
                </button>
            </div>
        </div>

        <!-- 流量汇总卡片 -->
        <div class="summary-card fade-in" id="summaryCard" style="display: none;">
            <div class="bucket-scroll-container">
                <div class="bucket-scroll-wrapper" id="bucketScrollWrapper">
                    <!-- 流量桶小卡片将在这里动态生成 -->
                </div>
            </div>
            <div class="summary-footer" id="summaryFooter">
                <div style="display: flex; align-items: center;">
                    <span>⏱️ 时长: </span><span id="timeInterval">计算中...</span>
                </div>
                <div style="display: flex; align-items: center;">
                    <span>🕐</span>
                    <span style="margin-left: 4px;" id="updateTime">查询时间：加载中...</span>
                </div>
            </div>
        </div>

        <!-- 流量包列表 -->
        <div id="packagesContainer"></div>

        <!-- 底部 -->
        <div class="footer">
            <div class="footer-content">
                <span>📱 联通查询</span>
                <span class="footer-divider">|</span>
                <span>📦 10010-web2</span>
                <span class="footer-divider">|</span>
                <span>数据仅供参考，以运营商账单为准</span>
            </div>
            <button class="footer-btn" onclick="resetStats()">
                <span>🔄</span>
                <span>重置统计周期</span>
            </button>
        </div>
    </div>

    <!-- 设置模态框 -->
    <div class="modal-overlay" id="configModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">⚙️ 设置</div>
                <button class="modal-close" onclick="closeConfigModal()">×</button>
            </div>
            
            <!-- 标签页切换 -->
            <div style="display: flex; border-bottom: 2px solid #f0f0f0; margin-bottom: 20px;">
                <button class="tab-btn active" onclick="switchConfigTab('notify')" id="tabNotify">
                    <span>�</span>
                    <span>通知配置</span>
                </button>
                <button class="tab-btn" onclick="switchConfigTab('user')" id="tabUser">
                    <span>👤</span>
                    <span>用户配置</span>
                </button>
            </div>
            
            <div style="max-height: 500px; overflow-y: auto;">
                <!-- 通知配置标签页 -->
                <div id="notifyConfigTab">
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center;">
                            <input type="checkbox" id="notifyEnabled" style="margin-right: 8px;">
                            <span>启用流量通知</span>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">通知方式</label>
                        <select class="form-select" id="notifyType" onchange="onNotifyTypeChange()">
                            <option value="">-- 请选择 --</option>
                            <option value="telegram">Telegram</option>
                            <option value="wecom">企业微信</option>
                            <option value="serverchan">Server 酱</option>
                            <option value="dingtalk">钉钉</option>
                            <option value="pushplus">PushPlus</option>
                        </select>
                    </div>
                    
                    <div id="notifyParams">
                        <!-- 通知参数将根据选择的通知方式动态生成 -->
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">通知标题</label>
                        <input type="text" class="form-input" id="notifyTitle" placeholder="支持占位符: [套餐] [时间]" value="联通流量提醒">
                        <div class="form-hint">占位符: [套餐] [时间]</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">通知副标题（可选）</label>
                        <input type="text" class="form-input" id="notifySubtitle" placeholder="仅部分通知方式支持">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">通知内容</label>
                        <textarea class="form-textarea" id="notifyContent" rows="5" placeholder="支持占位符: [套餐] [时间] [所有流量.总量] [所有流量.已用] [所有流量.用量] [所有流量.今日用量]"></textarea>
                        <div class="form-hint">占位符: [套餐] [时间] [所有流量.总量] [所有流量.已用] [所有流量.用量] [所有流量.今日用量]</div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">通知阈值 (MB)</label>
                            <input type="number" class="form-input" id="notifyThreshold" min="0" value="0">
                            <div class="form-hint">0 表示无限制，每次查询都通知</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">查询频率 (分钟)</label>
                            <input type="number" class="form-input" id="queryInterval" min="5" value="30">
                            <div class="form-hint">定时任务查询间隔，最小 5 分钟</div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button class="btn-secondary" onclick="testNotification()">🧪 测试通知</button>
                        <button class="btn-primary" onclick="saveNotifyConfig()">💾 保存配置</button>
                    </div>
                </div>
                
                <!-- 用户配置标签页 -->
                <div id="userConfigTab" style="display: none;">
                    <!-- 认证方式 -->
                    <div class="form-group">
                        <label class="form-label">认证方式</label>
                        <select class="form-select" id="userAuthType" onchange="onAuthTypeChange()">
                            <option value="token_online">token_online</option>
                            <option value="cookie">cookie</option>
                        </select>
                    </div>
                    
                    <!-- 查询密码和昵称同行 -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">查询密码</label>
                            <input type="text" class="form-input" id="userPassword" placeholder="输入查询密码">
                            <div class="form-hint">留空表示不修改</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">昵称</label>
                            <input type="text" class="form-input" id="userNickname" placeholder="输入昵称">
                        </div>
                    </div>
                    
                    <!-- Token方式：显示AppID和Token -->
                    <div id="tokenGroup" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">AppID</label>
                            <input type="text" class="form-input" id="userAppid" placeholder="应用ID">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Token Online</label>
                            <textarea class="form-textarea" id="userTokenOnline" rows="3" placeholder="在线凭证Token"></textarea>
                        </div>
                    </div>
                    
                    <!-- Cookie框（所有方式都显示，但状态不同） -->
                    <div class="form-group">
                        <label class="form-label">
                            <span id="cookieLabel">Cookie</span>
                            <button type="button" onclick="copyCookie()" style="margin-left: 8px; padding: 4px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                                📋 复制
                            </button>
                        </label>
                        <textarea class="form-textarea" id="userCookie" rows="4" placeholder="从浏览器或抓包工具获取的Cookie"></textarea>
                        <div class="form-hint" id="cookieHint">Cookie 可以更新，建议定期更新以保持有效性</div>
                    </div>
                    
                    <div class="modal-footer">
                        <button class="btn-danger" onclick="deleteAccount()">🗑️ 删除账户</button>
                        <button class="btn-primary" onclick="saveUserConfig()">💾 保存配置</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="/assets/js/query.js?v=<?= time() ?>"></script>
</body>
</html>
