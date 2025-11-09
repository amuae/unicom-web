# 联通流量查询系统

一个基于Web的中国联通流量查询和管理系统，支持定时自动查询、流量统计、多用户管理等功能。

## ✨ 主要特性

- 🔍 **流量查询**：支持联通用户流量实时查询
- 📊 **数据可视化**：流量包、流量桶数据清晰展示
- ⏰ **定时任务**：支持自动定时查询，无需手动操作
- 📱 **通知推送**：支持多种通知方式（ServerChan、Telegram、Bark等）
- 👥 **多用户管理**：支持多个联通账号管理
- 🔐 **权限管理**：完善的管理员后台和权限控制
- 📈 **流量统计**：流量使用趋势分析和统计报表

## 🚀 快速开始

### 系统要求

- PHP 7.4 或更高版本
- SQLite 3 扩展
- Web服务器（Apache/Nginx）
- Linux系统（用于定时任务功能）

### 安装步骤

1. **克隆或下载项目**
   ```bash
   git clone <repository-url>
   cd 10010-web2
   ```

2. **配置Web服务器**
   
   确保Web服务器将 `public/` 目录作为根目录。

   **Apache 配置示例：**
   ```apache
   <VirtualHost *:80>
       ServerName yourdomain.com
       DocumentRoot /path/to/10010-web2/public
       
       <Directory /path/to/10010-web2/public>
           Options -Indexes +FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

   **Nginx 配置示例：**
   ```nginx
   server {
       listen 80;
       server_name yourdomain.com;
       root /path/to/10010-web2/public;
       index index.php;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
           fastcgi_index index.php;
           include fastcgi_params;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
       }
   }
   ```

3. **运行安装向导**
   
   访问 `http://yourdomain.com/install.php`，按照向导完成安装：
   - 步骤1：环境检查
   - 步骤2：数据库初始化
   - 步骤3：创建管理员账号
   - 步骤4：完成安装（配置权限）

4. **配置系统权限（重要）**

   为了让定时任务功能正常工作，需要配置sudo权限和文件权限。

   **推荐方式：一键自动配置**
   ```bash
   cd /path/to/10010-web2
   sudo bash scripts/setup_permissions.sh
   ```
   
   此脚本将自动完成：
   - ✅ 检测Web服务器用户（www-data/apache/nginx）
   - ✅ 配置sudoers权限
   - ✅ 设置数据库和脚本文件权限
   - ✅ 验证配置是否成功

   **手动配置方式：**
   
   如果自动脚本无法运行，请参考安装向导第4步的详细说明，或查看 `scripts/setup_permissions.sh` 了解具体配置内容。

5. **开始使用**
   
   - 访问首页：`http://yourdomain.com/`
   - 管理后台：`http://yourdomain.com/admin.php`
   - 使用安装时创建的管理员账号登录

## 📚 功能说明

### 流量查询

支持两种认证方式：
- **Token方式**：需要提供AppID和Token Online
- **Cookie方式**：直接使用Cookie进行查询

查询结果包括：
- 主套餐流量信息
- 流量包详情
- 流量桶统计（通用流量、定向流量等）
- 话费余额

### 定时任务

管理员可以为用户配置定时查询任务：
- 支持自定义查询时间（时、分）
- 自动记录查询历史
- 支持流量变化统计
- 可选通知推送

### 通知推送

支持多种通知方式：
- ServerChan（Server酱）
- Telegram Bot
- Bark（iOS）
- PushPlus
- 企业微信
- 钉钉机器人

每个用户可以独立配置通知方式和参数。

### 管理后台

- **用户管理**：添加、编辑、删除用户
- **定时任务**：配置和管理定时查询任务
- **邀请码**：生成和管理注册邀请码
- **查询日志**：查看所有查询记录
- **系统设置**：配置系统参数

## 🔧 项目结构

```
10010-web2/
├── app/
│   ├── Controllers/      # 控制器
│   ├── Models/          # 数据模型
│   ├── Services/        # 业务逻辑服务
│   ├── Utils/           # 工具类
│   └── Views/           # 视图模板
├── config/              # 配置文件
├── database/            # 数据库文件和Schema
├── public/              # Web根目录
│   ├── assets/          # 静态资源（CSS、JS）
│   ├── api/             # API接口
│   ├── index.php        # 查询页面
│   ├── admin.php        # 管理后台
│   └── install.php      # 安装向导
├── scripts/             # 脚本文件
│   ├── cron/            # 定时任务脚本
│   └── setup_permissions.sh  # 权限配置脚本
└── storage/             # 存储目录
    └── logs/            # 日志文件
```

## 🛠️ 技术栈

- **后端**：PHP 7.4+ (MVC架构)
- **数据库**：SQLite 3
- **前端**：原生JavaScript + CSS3
- **定时任务**：Linux Crontab
- **通知推送**：多种第三方推送服务API

## ⚠️ 注意事项

1. **安全性**
   - 建议使用HTTPS协议
   - 定期更换管理员密码
   - 妥善保管Cookie和Token信息
   - 限制install.php访问（安装完成后可删除或重命名）

2. **权限配置**
   - 定时任务功能需要sudo权限
   - 确保数据库文件和日志目录可写
   - Web服务器用户需要执行crontab命令的权限

3. **性能优化**
   - 定时任务不宜设置过于频繁
   - 定期清理过期日志（系统会自动清理30天前的日志）
   - SQLite数据库适合中小规模使用

4. **远程部署**
   - 可以通过SSH远程执行权限配置命令
   - 参考安装向导第4步的远程部署示例

## 📝 常见问题

### Q1: 定时任务不工作怎么办？

**A:** 请检查以下几点：
1. 是否已运行权限配置脚本
2. 验证sudo权限：`sudo -u www-data sudo crontab -l`
3. 检查脚本文件权限：`ls -l scripts/cron/`
4. 查看系统日志：`tail -f storage/logs/system.log`

### Q2: 如何更换Web服务器用户？

**A:** 重新运行权限配置脚本：
```bash
sudo bash scripts/setup_permissions.sh
```
脚本会自动检测当前的Web服务器用户。

### Q3: 流量查询失败怎么办？

**A:** 
1. 检查Cookie或Token是否过期
2. 确认联通账号是否正常
3. 查看查询日志中的错误信息
4. 尝试手动查询一次获取新的Cookie

### Q4: 如何备份数据？

**A:** 只需备份以下文件：
```bash
# 数据库文件
database/unicom_flow.db

# 配置文件（如有修改）
config/*.php
```

### Q5: 如何升级系统？

**A:**
1. 备份数据库文件
2. 下载新版本代码
3. 覆盖除数据库外的所有文件
4. 访问install.php检查更新

## 📄 开源协议

本项目采用 MIT 协议开源。

## 🤝 贡献

欢迎提交Issue和Pull Request！

## 📧 联系方式

如有问题或建议，请通过以下方式联系：
- 提交Issue
- 发送邮件

---

**免责声明**：本项目仅供学习交流使用，请遵守相关法律法规和运营商服务条款。
