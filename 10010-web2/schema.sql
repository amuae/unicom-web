-- ==========================================
-- 联通流量监控多用户系统 - 数据库结构
-- 版本: 2.2
-- 更新日期: 2025-10-31
-- 数据库: SQLite3
-- ==========================================
--
-- 更新记录:
-- v2.2 (2025-10-31)
--   - users表新增字段：
--     * notify_url: 通知推送URL（webhook）
--     * notify_enabled: 是否启用通知（0/1）
--
-- v2.1 (2025-10-31)
--   - activation_codes表新增字段：
--     * status: 激活码状态（unused/used/expired）
--     * remark: 备注信息
--     * expires_at: 过期时间
--   - 说明：stats.json文件中新增stats_start_time字段（统计周期开始时间）
--   - 说明：notify.json文件字段名规范化（tgBotToken, tgUserId等）
--
-- v2.0 (2025-10-31)
--   - 初始完整版本
--   - 包含9个主表、17个索引、3个视图、5个触发器
-- ==========================================

-- ==========================================
-- 1. 管理员表 (admins)
-- 存储系统管理员账号信息
-- ==========================================
CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,           -- 管理员用户名（唯一）
    password VARCHAR(255) NOT NULL,                 -- 密码哈希（bcrypt）
    email VARCHAR(100),                             -- 邮箱地址（可选）
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,  -- 创建时间
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,  -- 更新时间
    last_login_at DATETIME                          -- 最后登录时间
);

-- ==========================================
-- 2. 用户表 (users)
-- 存储联通用户的认证信息和基本资料
-- ==========================================
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mobile VARCHAR(11) UNIQUE NOT NULL,             -- 手机号（唯一）
    auth_type VARCHAR(10) DEFAULT 'full' CHECK(auth_type IN ('full', 'cookie')), -- 认证类型
    appid TEXT,                                     -- 联通AppID（完整认证）
    token_online TEXT,                              -- 在线token（完整认证）
    cookie TEXT,                                    -- Cookie认证信息
    access_token VARCHAR(64) UNIQUE NOT NULL,       -- 访问令牌（唯一，用于查询）
    user_type VARCHAR(20) DEFAULT 'beta' CHECK(user_type IN ('beta', 'activated')), -- 用户类型
    status VARCHAR(20) DEFAULT 'active' CHECK(status IN ('active', 'disabled')),   -- 账号状态
    activation_code VARCHAR(32) DEFAULT NULL,       -- 使用的激活码
    is_active INTEGER DEFAULT 1 CHECK(is_active IN (0, 1)), -- 激活状态（兼容旧版）
    remark TEXT,                                    -- 备注信息
    notify_url TEXT DEFAULT NULL,                   -- 通知推送URL（webhook）
    notify_enabled INTEGER DEFAULT 0 CHECK(notify_enabled IN (0, 1)), -- 是否启用通知
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,  -- 创建时间
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,  -- 更新时间
    last_query_at DATETIME,                         -- 最后查询时间
    cookie_updated_at DATETIME                      -- Cookie更新时间
);

-- ==========================================
-- 3. 激活码表 (activation_codes)
-- 管理系统激活码，用于私有模式下的用户注册
-- ==========================================
CREATE TABLE IF NOT EXISTS activation_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(24) UNIQUE NOT NULL,               -- 激活码（唯一）
    status VARCHAR(20) DEFAULT 'unused' CHECK(status IN ('unused', 'used', 'expired')), -- 状态
    used INTEGER DEFAULT 0 CHECK(used IN (0, 1)),   -- 使用状态（兼容旧版）：0=未使用，1=已使用
    used_by VARCHAR(11),                            -- 使用者手机号
    used_at DATETIME,                               -- 使用时间
    remark TEXT,                                    -- 备注信息
    expires_at DATETIME,                            -- 过期时间
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP   -- 创建时间
);

-- ==========================================
-- 4. 网站配置表 (site_config)
-- 存储网站运营模式等配置项
-- ==========================================
CREATE TABLE IF NOT EXISTS site_config (
    key VARCHAR(50) PRIMARY KEY,                    -- 配置键（主键）
    value TEXT,                                     -- 配置值
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP   -- 更新时间
);

-- ==========================================
-- 5. 系统配置表 (system_configs)
-- 存储系统级别的配置参数
-- ==========================================
CREATE TABLE IF NOT EXISTS system_configs (
    key VARCHAR(50) PRIMARY KEY,                    -- 配置键（主键）
    value TEXT,                                     -- 配置值
    description TEXT,                               -- 配置说明
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP   -- 更新时间
);

-- ==========================================
-- 6. 流量统计表 (flow_stats)
-- 存储用户的流量查询历史记录
-- ==========================================
CREATE TABLE IF NOT EXISTS flow_stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,                       -- 用户ID（外键）
    mobile VARCHAR(11) NOT NULL,                    -- 手机号
    timestamp INTEGER NOT NULL,                     -- Unix时间戳
    date VARCHAR(10) NOT NULL,                      -- 日期（YYYY-MM-DD）
    main_package VARCHAR(255),                      -- 主套餐名称
    buckets TEXT,                                   -- 流量桶数据（JSON）
    diff TEXT,                                      -- 差异数据（JSON）
    packages TEXT,                                  -- 流量包详情（JSON）
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,  -- 创建时间
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ==========================================
-- 7. 通知配置表 (notify_configs)
-- 存储用户的通知推送配置
-- ==========================================
CREATE TABLE IF NOT EXISTS notify_configs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER UNIQUE NOT NULL,                -- 用户ID（外键，唯一）
    type VARCHAR(20),                               -- 通知类型：bark/telegram/dingtalk/qywx/pushplus/serverchan
    params TEXT,                                    -- 通知参数（JSON）
    title VARCHAR(255),                             -- 通知标题模板
    subtitle VARCHAR(255),                          -- 通知副标题模板
    content TEXT,                                   -- 通知内容模板
    threshold INTEGER DEFAULT 0,                    -- 触发阈值（MB）
    is_enabled INTEGER DEFAULT 1 CHECK(is_enabled IN (0, 1)), -- 是否启用
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,  -- 创建时间
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,  -- 更新时间
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ==========================================
-- 8. 通知日志表 (notify_logs)
-- 记录通知发送的历史
-- ==========================================
CREATE TABLE IF NOT EXISTS notify_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,                       -- 用户ID（外键）
    type VARCHAR(20),                               -- 通知类型
    title VARCHAR(255),                             -- 通知标题
    status VARCHAR(10) CHECK(status IN ('success', 'failed')), -- 发送状态
    error_message TEXT,                             -- 错误信息
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,  -- 创建时间
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ==========================================
-- 9. 操作日志表 (operation_logs)
-- 记录用户和管理员的关键操作
-- ==========================================
CREATE TABLE IF NOT EXISTS operation_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,                                -- 用户ID（外键，可空）
    admin_id INTEGER,                               -- 管理员ID（外键，可空）
    action VARCHAR(50) NOT NULL,                    -- 操作类型
    ip_address VARCHAR(45),                         -- IP地址
    user_agent TEXT,                                -- User Agent
    details TEXT,                                   -- 操作详情（JSON）
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,  -- 创建时间
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

-- ==========================================
-- 索引优化
-- ==========================================

-- 用户表索引
CREATE INDEX IF NOT EXISTS idx_users_mobile ON users(mobile);
CREATE INDEX IF NOT EXISTS idx_users_access_token ON users(access_token);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_user_type ON users(user_type);
CREATE INDEX IF NOT EXISTS idx_users_is_active ON users(is_active);

-- 激活码表索引
CREATE INDEX IF NOT EXISTS idx_activation_codes_code ON activation_codes(code);
CREATE INDEX IF NOT EXISTS idx_activation_codes_used ON activation_codes(used);

-- 流量统计表索引
CREATE INDEX IF NOT EXISTS idx_flow_stats_user_id ON flow_stats(user_id);
CREATE INDEX IF NOT EXISTS idx_flow_stats_mobile ON flow_stats(mobile);
CREATE INDEX IF NOT EXISTS idx_flow_stats_timestamp ON flow_stats(timestamp);
CREATE INDEX IF NOT EXISTS idx_flow_stats_date ON flow_stats(date);

-- 通知配置表索引
CREATE INDEX IF NOT EXISTS idx_notify_configs_user_id ON notify_configs(user_id);
CREATE INDEX IF NOT EXISTS idx_notify_configs_is_enabled ON notify_configs(is_enabled);

-- 通知日志表索引
CREATE INDEX IF NOT EXISTS idx_notify_logs_user_id ON notify_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_notify_logs_status ON notify_logs(status);
CREATE INDEX IF NOT EXISTS idx_notify_logs_created_at ON notify_logs(created_at);

-- 操作日志表索引
CREATE INDEX IF NOT EXISTS idx_operation_logs_user_id ON operation_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_operation_logs_admin_id ON operation_logs(admin_id);
CREATE INDEX IF NOT EXISTS idx_operation_logs_action ON operation_logs(action);
CREATE INDEX IF NOT EXISTS idx_operation_logs_created_at ON operation_logs(created_at);

-- ==========================================
-- 视图定义
-- ==========================================

-- 用户流量统计视图
CREATE VIEW IF NOT EXISTS v_user_flow_summary AS
SELECT 
    u.id,
    u.mobile,
    u.access_token,
    u.user_type,
    u.status,
    u.is_active,
    u.created_at as user_created_at,
    u.last_query_at,
    COUNT(fs.id) as total_queries,
    MAX(fs.timestamp) as last_stat_time,
    MAX(fs.date) as last_stat_date
FROM users u
LEFT JOIN flow_stats fs ON u.id = fs.user_id
GROUP BY u.id, u.mobile, u.access_token, u.user_type, u.status, u.is_active, u.created_at, u.last_query_at;

-- 通知统计视图
CREATE VIEW IF NOT EXISTS v_notify_summary AS
SELECT 
    u.id as user_id,
    u.mobile,
    nc.type as notify_type,
    nc.is_enabled,
    COUNT(nl.id) as total_notifies,
    SUM(CASE WHEN nl.status = 'success' THEN 1 ELSE 0 END) as success_count,
    SUM(CASE WHEN nl.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
    MAX(nl.created_at) as last_notify_at
FROM users u
LEFT JOIN notify_configs nc ON u.id = nc.user_id
LEFT JOIN notify_logs nl ON u.id = nl.user_id
GROUP BY u.id, u.mobile, nc.type, nc.is_enabled;

-- 激活码使用统计视图
CREATE VIEW IF NOT EXISTS v_activation_code_stats AS
SELECT 
    COUNT(*) as total_codes,
    SUM(CASE WHEN used = 0 THEN 1 ELSE 0 END) as unused_codes,
    SUM(CASE WHEN used = 1 THEN 1 ELSE 0 END) as used_codes,
    MAX(created_at) as latest_created_at,
    MAX(used_at) as latest_used_at
FROM activation_codes;

-- ==========================================
-- 初始数据
-- ==========================================

-- 注意：管理员账号在安装页面创建，无需在此插入默认账号

-- 插入网站配置默认值
-- site_mode: public=公开注册（无需激活码），private=私有模式（需要激活码）
INSERT OR IGNORE INTO site_config (key, value) VALUES
('site_mode', 'public');

-- 插入系统配置默认值
INSERT OR IGNORE INTO system_configs (key, value, description) VALUES
('system_name', '联通流量监控系统', '系统名称'),
('system_version', '2.0', '系统版本号'),
('allow_self_delete', '1', '允许用户自行删除账号：0=不允许，1=允许'),
('max_users', '0', '最大用户数量：0=不限制'),
('query_interval', '300', '查询间隔（秒）：最小5分钟'),
('data_retention_days', '90', '数据保留天数：0=永久保留'),
('enable_registration', '1', '是否开放注册：0=关闭，1=开放'),
('enable_notification', '1', '是否启用通知功能：0=关闭，1=启用'),
('default_threshold', '100', '默认通知阈值（MB）');

-- ==========================================
-- 数据库版本信息
-- ==========================================
INSERT OR REPLACE INTO system_configs (key, value, description) VALUES
('db_version', '2.0', '数据库结构版本'),
('db_updated_at', datetime('now'), '数据库最后更新时间');

-- ==========================================
-- 触发器（自动更新时间戳）
-- ==========================================

-- 管理员表更新触发器
CREATE TRIGGER IF NOT EXISTS trg_admins_updated_at
AFTER UPDATE ON admins
FOR EACH ROW
BEGIN
    UPDATE admins SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

-- 用户表更新触发器
CREATE TRIGGER IF NOT EXISTS trg_users_updated_at
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

-- 通知配置表更新触发器
CREATE TRIGGER IF NOT EXISTS trg_notify_configs_updated_at
AFTER UPDATE ON notify_configs
FOR EACH ROW
BEGIN
    UPDATE notify_configs SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

-- 网站配置表更新触发器
CREATE TRIGGER IF NOT EXISTS trg_site_config_updated_at
AFTER UPDATE ON site_config
FOR EACH ROW
BEGIN
    UPDATE site_config SET updated_at = CURRENT_TIMESTAMP WHERE key = NEW.key;
END;

-- 系统配置表更新触发器
CREATE TRIGGER IF NOT EXISTS trg_system_configs_updated_at
AFTER UPDATE ON system_configs
FOR EACH ROW
BEGIN
    UPDATE system_configs SET updated_at = CURRENT_TIMESTAMP WHERE key = NEW.key;
END;

-- ==========================================
-- 数据库说明
-- ==========================================

/*
数据库设计说明：

1. 表结构
   - admins: 管理员账号，用于后台管理
   - users: 用户信息和联通账号认证数据
   - activation_codes: 激活码管理，支持私有模式
   - site_config: 网站配置（运营模式等）
   - system_configs: 系统配置（各类参数）
   - flow_stats: 流量查询历史记录
   - notify_configs: 用户通知配置
   - notify_logs: 通知发送日志
   - operation_logs: 操作审计日志

2. 认证方式
   - 完整认证 (full): 使用appid + token_online
   - Cookie认证 (cookie): 直接使用cookie

3. 用户类型
   - beta: 公测用户（公开模式注册）
   - activated: 激活码用户（使用激活码注册）

4. 账号状态
   - active: 正常激活
   - disabled: 已禁用

5. 网站模式
   - public: 公开注册，无需激活码
   - private: 私有模式，需要激活码才能注册

6. 数据加密
   - 管理员密码: bcrypt哈希
   - 用户认证信息: AES-256-CBC加密存储
   - access_token: SHA256哈希

7. 通知类型
   - bark: Bark推送
   - telegram: Telegram机器人
   - dingtalk: 钉钉机器人
   - qywx: 企业微信
   - pushplus: PushPlus
   - serverchan: Server酱

8. 索引策略
   - 所有外键字段都建立索引
   - 常用查询字段建立索引
   - 时间字段建立索引便于日志查询和清理

9. 数据清理建议
   - flow_stats: 建议保留90天数据
   - notify_logs: 建议保留30天数据
   - operation_logs: 建议保留180天数据

10. 性能优化
    - 使用索引加速查询
    - 使用视图简化统计查询
    - 使用触发器自动维护时间戳
    - 定期清理过期日志数据

11. 兼容性说明
    - is_active字段保留用于向后兼容
    - 建议使用status字段替代is_active
    - 支持从旧版本平滑升级

12. 安全建议
    - 定期更改管理员密码
    - 定期轮换加密密钥
    - 启用操作日志审计
    - 定期备份数据库
    - 限制数据库文件访问权限（chmod 600）

13. JSON文件结构说明
    
    a) stats.json（用户流量统计文件）
       位置: data/{access_token}/stats.json
       新增字段 (v2.1):
       - stats_start_time: 统计周期开始时间（ISO 8601格式）
         * 首次查询时设置为当前时间
         * 通知发送成功后更新为当前时间
         * 手动重置统计时更新为当前时间
       - timestamp: 最后查询时间
       - date: 查询日期时间
       - mobile: 手机号
       - mainPackage: 主套餐名称
       - packages: 流量包详情数组
       - buckets: 流量桶数据对象
       - diff: 差异数据对象
         * used: 累计用量（通知后归零）
         * today: 今日用量（跨日归零）
    
    b) notify.json（通知配置文件）
       位置: data/{access_token}/notify.json
       字段规范 (v2.1):
       - type: 通知类型（bark/telegram/dingtalk等）
       - threshold: 触发阈值（MB）
       - interval: 查询间隔（分钟）
       - title: 通知标题模板
       - subtitle: 通知副标题模板
       - content: 通知内容模板
       - params: 通知参数对象
         * Telegram参数: tgBotToken, tgUserId, tgApiHost, tgProxyHost, tgProxyPort, tgProxyAuth
         * Bark参数: barkPush, barkSound, barkGroup, barkIcon, barkLevel, barkUrl, barkArchive
         * 钉钉参数: ddBotToken, ddBotSecret
         * 企业微信参数: qywxMode, qywxKey, qywxAm
         * PushPlus参数: pushplusToken, pushplusUser, pushplusTemplate等
         * Server酱参数: pushKey
    
    c) balance.json（余额查询结果）
       位置: data/{access_token}/balance.json
       - timestamp: 查询时间
       - balance: 余额（元）
       - updated_at: 更新时间

14. 占位符说明（通知模板）
    - [套餐]: 主套餐名称
    - [时长]: 距离统计周期开始的时长
    - [时间]: 当前时间（HH:mm:ss）
    - [{流量桶名}.总量]: 流量桶总量
    - [{流量桶名}.已用]: 流量桶已用量
    - [{流量桶名}.剩余]: 流量桶剩余量（不限流量桶负值显示为"无限"）
    - [{流量桶名}.用量]: 统计周期内累计用量
    - [{流量桶名}.今日用量]: 今日用量
    
    流量桶名称:
    - 通用有限、通用不限
    - 区域有限、区域不限
    - 免流有限、免流不限
    - 所有通用、所有免流、所有流量
*/

-- ==========================================
-- 版本历史
-- ==========================================

/*
v2.1 (2025-10-31)
- 激活码表新增字段：status（状态）、remark（备注）、expires_at（过期时间）
- stats.json新增stats_start_time字段（统计周期开始时间）
- notify.json参数名规范化（tgBotToken替代botToken等）
- 优化流量显示逻辑：不限流量桶负值显示为"无限"
- 修复stats保存逻辑：每次查询都保存最新数据
- 完善JSON文件结构文档

v2.0 (2025-10-31)
- 完整重构数据库结构
- 添加激活码系统
- 添加网站配置表
- 添加多种通知方式支持
- 优化索引结构
- 添加统计视图
- 添加触发器自动更新时间戳
- 改进注释和文档

v1.0 (2025-10-30)
- 初始版本
- 基本用户管理
- 流量查询功能
- 简单通知功能
*/
