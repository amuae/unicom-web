# 联通流量监控系统 (10010-web2)

一个基于Web的联通手机流量监控和通知系统，支持多用户、定时查询、流量提醒等功能。

## ✨ 主要特性

- 🔐 **用户管理系统**
  - 支持多用户独立账号
  - 激活码注册机制
  - 管理员权限控制

- 📊 **流量监控**
  - 自动查询联通流量
  - 多流量包统计（通用、免流、不限流量）
  - 实时流量使用情况

- 🔔 **多渠道通知**
  - 支持 6 种通知方式：Bark、Telegram、钉钉、企业微信、PushPlus、Server酱
  - 自定义通知内容和格式
  - 流量阈值提醒

- ⏰ **定时任务**
  - 按用户独立管理定时任务
  - 自动根据配置启停任务
  - 支持自定义查询间隔（1-1440分钟）

- 🎨 **友好界面**
  - 响应式设计
  - 简洁易用的管理面板
  - 实时数据展示

## 📋 系统要求

- PHP 7.4 或更高版本
- SQLite3 扩展
- cURL 扩展
- 支持 crontab（用于定时任务）
- Web服务器（Apache/Nginx）

## 🚀 快速部署

### 1. 下载项目

```bash
git clone <repository-url>
cd 10010-web2
```

### 2. 配置权限

```bash
# 确保data和logs目录可写
chmod 755 data logs
chown www-data:www-data data logs
```

### 3. 初始化数据库

访问：`http://your-domain/install.php`

按照向导完成：
- 数据库初始化
- 创建管理员账号
- 设置网站运营模式

### 4. 登录管理

访问：`http://your-domain/`

使用管理员账号登录，即可开始使用。

## 📖 详细文档

- **定时任务说明**: [README_CRON.md](README_CRON.md)
- **数据库结构**: [schema.sql](schema.sql)

## 🛠️ 核心功能说明

### 用户注册

系统支持两种运营模式：

1. **开放注册模式**：任何人都可以注册
2. **激活码模式**：需要激活码才能注册

管理员可在系统设置中切换模式和生成激活码。

### 通知配置

用户可以配置以下内容：
- 通知类型：选择推送渠道
- 阈值设置：达到多少百分比时提醒（0-100）
- 查询间隔：多久查询一次（1-1440分钟）
- 通知模板：自定义通知标题和内容

### 定时查询

系统会自动为满足以下条件的用户创建定时任务：
1. 设置了通知方式
2. 设置了阈值（>0）
3. 设置了查询间隔（>0）

如果条件不满足，系统会自动停止该用户的定时任务。

查看定时任务状态：
```bash
./cron_status.sh
```

## 📁 项目结构

```
10010-web2/
├── api/                    # API接口
│   ├── admin.php          # 管理员API
│   ├── notify.php         # 通知配置API
│   ├── query.php          # 流量查询API
│   ├── register.php       # 用户注册API
│   ├── system.php         # 系统管理API
│   └── user.php           # 用户管理API
│
├── classes/               # 核心类
│   ├── Admin.php          # 管理员类
│   ├── Config.php         # 配置类
│   ├── CronManager.php    # Cron任务管理器
│   ├── Database.php       # 数据库类
│   ├── FlowMonitor.php    # 流量监控类
│   ├── User.php           # 用户类
│   └── Utils.php          # 工具类
│
├── data/                  # 数据目录（运行时）
│   ├── flow_monitor.db    # SQLite数据库
│   └── <token>/           # 用户数据目录
│       ├── notify.json    # 通知配置
│       └── stats.json     # 统计数据
│
├── logs/                  # 日志目录
│   └── cron_*.log         # 定时任务日志
│
├── index.html             # 前端页面
├── install.php            # 安装向导
├── cron_query.php         # 定时查询脚本
├── cron_status.sh         # 任务状态查看工具
├── schema.sql             # 数据库结构
└── README.md              # 本文件
```

## 🔧 常见问题

### Q1: 如何重置管理员密码？

访问 `install.php`，选择"重置管理员密码"选项。

### Q2: 定时任务不执行怎么办？

1. 检查crontab：`sudo crontab -u www-data -l`
2. 查看日志：`tail -f logs/cron_*.log`
3. 手动测试：`sudo -u www-data php cron_query.php <token>`
4. 运行状态检查：`./cron_status.sh`

### Q3: 如何备份数据？

备份以下内容：
- `data/flow_monitor.db`（数据库）
- `data/<token>/`（用户配置）

### Q4: 如何迁移到新服务器？

1. 复制整个项目目录
2. 复制数据库文件
3. 配置Web服务器
4. 确认crontab已正确配置

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📄 许可证

MIT License

## 📞 联系方式

如有问题或建议，欢迎通过 Issue 联系。

---

**项目状态**: ✅ 生产就绪

**版本**: 2.0

**最后更新**: 2025-10-31
