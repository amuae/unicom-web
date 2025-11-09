-- Users table
-- auth_type: 'password' (密码认证) | 'token_online' (Token在线认证) | 'cookie' (Cookie认证)
-- status: 'active' (正常) | 'disabled' (禁用) | 'expired' (过期)
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mobile VARCHAR(11) NOT NULL UNIQUE,  -- 手机号
    nickname VARCHAR(50) DEFAULT '',     -- 昵称
    query_password VARCHAR(255) NOT NULL,-- 查询密码（用户在本平台查询流量时使用的密码）
    auth_type VARCHAR(20) DEFAULT 'password', -- 认证类型
    appid VARCHAR(100) DEFAULT '',       -- APPID（用于token_online认证）
    token_online VARCHAR(255) DEFAULT '',-- Token（用于token_online认证）
    cookie TEXT DEFAULT '',              -- Cookie（用于cookie认证）
    status VARCHAR(20) DEFAULT 'active', -- 状态
    last_query_at DATETIME,              -- 最后查询时间
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- 创建时间
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- 更新时间
    today_query_data TEXT DEFAULT '',    -- 今日首次查询数据（用于统计当日用量）
    last_query_data TEXT DEFAULT '',     -- 最近一次查询数据（用于统计两次查询间用量）
    last_query_time DATETIME,            -- 最近一次查询时间（北京时间）
    token VARCHAR(24) DEFAULT '',        -- 用户专属查询Token（24位随机字符串）
    notify_enabled INTEGER DEFAULT 0,    -- 是否启用通知（0=禁用，1=启用）
    notify_type VARCHAR(50) DEFAULT '',  -- 通知类型（telegram, pushplus, email等）
    notify_params TEXT DEFAULT '',       -- 通知参数（JSON格式）
    notify_title VARCHAR(200) DEFAULT '联通流量提醒', -- 通知标题
    notify_subtitle VARCHAR(200) DEFAULT '', -- 通知副标题
    notify_content TEXT DEFAULT '',      -- 通知内容模板
    notify_threshold INTEGER DEFAULT 5120, -- 通知阈值（MB，流量低于此值时发送通知）
    query_interval INTEGER DEFAULT 30,   -- 查询间隔（分钟）
    last_notify_time TEXT                -- 最后通知时间
);

CREATE INDEX idx_users_mobile ON users(mobile);
CREATE INDEX idx_users_status ON users(status);

-- Admins table
CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,-- 管理员账号
    password VARCHAR(255) NOT NULL,      -- 密码（bcrypt加密）
    real_name VARCHAR(50) DEFAULT '',    -- 真实姓名
    email VARCHAR(100) DEFAULT '',       -- 邮箱
    last_login_at DATETIME,              -- 最后登录时间
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP -- 创建时间
);

CREATE INDEX idx_admins_username ON admins(username);

-- Invite codes table
-- type: 'normal' (普通邀请码，可能被移除该字段后默认为此值) | 'single' (一次性) | 'multiple' (多次)
-- status: 'active' (启用) | 'disabled' (禁用)
CREATE TABLE IF NOT EXISTS invite_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(32) NOT NULL UNIQUE,    -- 邀请码
    type VARCHAR(20) DEFAULT 'normal',   -- 邀请码类型
    max_usage INTEGER DEFAULT 1,         -- 最大使用次数
    used_count INTEGER DEFAULT 0,        -- 已使用次数
    status VARCHAR(20) DEFAULT 'active', -- 状态
    expire_at DATETIME,                  -- 过期时间（NULL表示永久有效）
    created_by INTEGER,                  -- 创建者ID（关联admins表）
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- 创建时间
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- 更新时间
    remark TEXT                          -- 备注说明
);

CREATE INDEX idx_invite_codes_code ON invite_codes(code);
CREATE INDEX idx_invite_codes_status ON invite_codes(status);

-- Query logs table
-- query_status: 'success' (成功) | 'failed' (失败)
CREATE TABLE IF NOT EXISTS query_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,            -- 用户ID
    mobile VARCHAR(11) NOT NULL,         -- 手机号
    query_result TEXT,                   -- 查询结果（JSON格式）
    query_status VARCHAR(20) DEFAULT 'success', -- 查询状态
    error_message TEXT,                  -- 错误信息
    ip_address VARCHAR(45),              -- 查询IP
    user_agent TEXT,                     -- User Agent
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP -- 创建时间
);

CREATE INDEX idx_query_logs_user_id ON query_logs(user_id);
CREATE INDEX idx_query_logs_created_at ON query_logs(created_at);

-- Cron tasks table
-- task_type: 'query_all' (查询所有用户) | 'clean_logs' (清理日志) | 'daily_report' (每日报告) | 'stats_flow' (流量统计)
-- status: 'active' (启用) | 'disabled' (禁用) | 'running' (运行中)
-- last_run_status: 'success' (成功) | 'failed' (失败) | 'timeout' (超时)
CREATE TABLE IF NOT EXISTS cron_tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,          -- 任务名称
    description TEXT,                    -- 任务描述
    cron_expression VARCHAR(100) NOT NULL,-- Cron表达式
    task_type VARCHAR(50) NOT NULL,      -- 任务类型
    task_params TEXT,                    -- 任务参数（JSON格式）
    status VARCHAR(20) DEFAULT 'active', -- 任务状态
    last_run_at DATETIME,                -- 最后运行时间
    last_run_status VARCHAR(20),         -- 最后运行状态
    last_run_message TEXT,               -- 最后运行消息
    next_run_at DATETIME,                -- 下次运行时间
    created_by INTEGER,                  -- 创建者ID
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- 创建时间
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- 更新时间
    total_runs INTEGER DEFAULT 0,        -- 总运行次数
    success_runs INTEGER DEFAULT 0,      -- 成功次数
    failed_runs INTEGER DEFAULT 0,       -- 失败次数
    last_run_duration REAL DEFAULT 0     -- 最后运行耗时（秒，浮点数）
);

CREATE INDEX idx_cron_tasks_status ON cron_tasks(status);
CREATE INDEX idx_cron_tasks_next_run ON cron_tasks(next_run_at);

-- System logs table
-- log_type: 'system' (系统日志) | 'admin' (管理员操作) | 'user' (用户操作) | 'cron' (定时任务) | 'query' (查询日志)
-- log_level: 'debug' | 'info' | 'warning' | 'error' | 'critical'
CREATE TABLE IF NOT EXISTS system_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    log_type VARCHAR(20) NOT NULL,       -- 日志类型
    log_level VARCHAR(20) DEFAULT 'info',-- 日志级别
    message TEXT NOT NULL,               -- 日志消息
    context TEXT,                        -- 上下文信息（JSON格式）
    user_id INTEGER,                     -- 用户ID（如果有）
    ip_address VARCHAR(45),              -- IP地址
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP -- 创建时间
);

CREATE INDEX idx_system_logs_type ON system_logs(log_type);
CREATE INDEX idx_system_logs_level ON system_logs(log_level);
CREATE INDEX idx_system_logs_created_at ON system_logs(created_at);

-- System config table
CREATE TABLE IF NOT EXISTS system_config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_key VARCHAR(100) NOT NULL UNIQUE, -- 配置键
    config_value TEXT,                   -- 配置值
    description TEXT,                    -- 配置描述
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP -- 更新时间
);

CREATE INDEX idx_system_config_key ON system_config(config_key);

-- Default system config
INSERT OR IGNORE INTO system_config (config_key, config_value, description, updated_at) VALUES 
('site_name', 'Unicom Flow Query System', '网站名称', CURRENT_TIMESTAMP),
('site_mode', 'invite', '站点模式：open=开放注册, invite=邀请码注册, closed=关闭注册', CURRENT_TIMESTAMP),
('log_retention_days', '30', '日志保留天数', CURRENT_TIMESTAMP),
('log_level', 'info', '日志级别：debug, info, warning, error, critical', CURRENT_TIMESTAMP),
('enable_notify', '1', '是否启用通知', CURRENT_TIMESTAMP),
('notify_channels', '[]', '启用的通知渠道', CURRENT_TIMESTAMP),
('timezone', 'Asia/Shanghai', '时区设置', CURRENT_TIMESTAMP);

-- Default cron tasks
INSERT OR IGNORE INTO cron_tasks (id, name, description, cron_expression, task_type, status, created_at, updated_at) VALUES
(1, '每日流量查询', '每天凌晨2点查询所有用户流量', '0 2 * * *', 'query_all', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(2, '清理查询日志', '每周日凌晨3点清理30天前的查询日志', '0 3 * * 0', 'clean_logs', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(3, '每日统计报告', '每天早上8点生成并发送统计报告', '0 8 * * *', 'daily_report', 'disabled', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
