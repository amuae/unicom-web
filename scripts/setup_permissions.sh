#!/bin/bash

###############################################################################
# 联通流量查询系统 - 权限配置脚本
# 用途：自动配置sudo权限和文件权限，使定时任务功能正常工作
# 使用：sudo bash scripts/setup_permissions.sh
###############################################################################

set -e  # 遇到错误立即退出

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 项目根目录
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# 默认Web服务器用户
WEB_USER="www-data"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       联通流量查询系统 - 权限配置脚本                         ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""

# 检查是否以root权限运行
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}✗ 错误：此脚本需要root权限运行${NC}"
    echo -e "${YELLOW}  请使用: sudo bash $0${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Root权限检查通过${NC}"
echo ""

# 检测Web服务器用户
echo -e "${BLUE}[1/5] 检测Web服务器用户...${NC}"

# 尝试检测实际的Web服务器用户
DETECTED_USERS=()

for user in www-data apache nginx httpd; do
    if id "$user" &>/dev/null; then
        DETECTED_USERS+=("$user")
    fi
done

if [ ${#DETECTED_USERS[@]} -eq 0 ]; then
    echo -e "${YELLOW}  ⚠ 未检测到常见的Web服务器用户${NC}"
    echo -e "${YELLOW}  将使用默认用户: ${WEB_USER}${NC}"
elif [ ${#DETECTED_USERS[@]} -eq 1 ]; then
    WEB_USER="${DETECTED_USERS[0]}"
    echo -e "${GREEN}  ✓ 检测到Web服务器用户: ${WEB_USER}${NC}"
else
    echo -e "${YELLOW}  检测到多个Web服务器用户: ${DETECTED_USERS[*]}${NC}"
    echo -e "${YELLOW}  请选择要使用的用户（默认: www-data）:${NC}"
    echo ""
    for i in "${!DETECTED_USERS[@]}"; do
        echo "    $((i+1)). ${DETECTED_USERS[$i]}"
    done
    echo ""
    read -p "  请输入选项 (1-${#DETECTED_USERS[@]}): " choice
    
    if [[ "$choice" =~ ^[0-9]+$ ]] && [ "$choice" -ge 1 ] && [ "$choice" -le ${#DETECTED_USERS[@]} ]; then
        WEB_USER="${DETECTED_USERS[$((choice-1))]}"
    fi
    echo -e "${GREEN}  ✓ 使用Web服务器用户: ${WEB_USER}${NC}"
fi

echo ""

# 配置sudoers
echo -e "${BLUE}[2/5] 配置sudoers权限...${NC}"

SUDOERS_FILE="/etc/sudoers.d/unicom-cron"
SUDOERS_CONTENT="# Allow ${WEB_USER} to manage crontab for automated tasks
${WEB_USER} ALL=(ALL) NOPASSWD: /usr/bin/crontab"

if [ -f "$SUDOERS_FILE" ]; then
    echo -e "${YELLOW}  ⚠ sudoers配置文件已存在，将备份旧文件${NC}"
    mv "$SUDOERS_FILE" "${SUDOERS_FILE}.bak.$(date +%Y%m%d%H%M%S)"
    echo -e "${GREEN}  ✓ 旧文件已备份${NC}"
fi

echo "$SUDOERS_CONTENT" > "$SUDOERS_FILE"
chmod 440 "$SUDOERS_FILE"

echo -e "${GREEN}  ✓ sudoers配置已创建: ${SUDOERS_FILE}${NC}"
echo -e "${GREEN}  ✓ 配置内容:${NC}"
cat "$SUDOERS_FILE" | sed 's/^/    /'
echo ""

# 验证sudoers配置
echo -e "${BLUE}[3/5] 验证sudoers配置...${NC}"

if visudo -c -f "$SUDOERS_FILE" &>/dev/null; then
    echo -e "${GREEN}  ✓ sudoers语法检查通过${NC}"
else
    echo -e "${RED}  ✗ sudoers语法检查失败${NC}"
    echo -e "${YELLOW}  正在恢复备份...${NC}"
    rm -f "$SUDOERS_FILE"
    if [ -f "${SUDOERS_FILE}.bak."* ]; then
        mv "${SUDOERS_FILE}.bak."* "$SUDOERS_FILE"
    fi
    exit 1
fi

# 测试sudo权限
if sudo -u "$WEB_USER" sudo crontab -l &>/dev/null || [ $? -eq 1 ]; then
    echo -e "${GREEN}  ✓ ${WEB_USER} 用户可以执行crontab命令${NC}"
else
    echo -e "${YELLOW}  ⚠ sudo权限测试失败，可能需要重启系统${NC}"
fi

echo ""

# 设置目录权限
echo -e "${BLUE}[4/5] 设置目录和文件权限...${NC}"

cd "$PROJECT_ROOT"

# 创建必要的目录
mkdir -p storage/logs
mkdir -p scripts/cron

# 设置目录权限
chmod 755 database/ 2>/dev/null || echo -e "${YELLOW}  ⚠ database目录不存在${NC}"
chmod 755 storage/
chmod 755 storage/logs/
chmod 755 scripts/
chmod 755 scripts/cron/

echo -e "${GREEN}  ✓ 目录权限已设置 (755)${NC}"

# 设置数据库文件权限
if [ -f "database/unicom_flow.db" ]; then
    chmod 664 database/unicom_flow.db
    chown "$WEB_USER:$WEB_USER" database/unicom_flow.db
    echo -e "${GREEN}  ✓ 数据库文件权限已设置 (664, owner: ${WEB_USER})${NC}"
else
    echo -e "${YELLOW}  ⚠ 数据库文件不存在（首次安装后会自动创建）${NC}"
fi

# 设置定时任务脚本权限
CRON_SCRIPTS=(
    "scripts/cron/query_single_user.php"
    "scripts/cron/daily_report.php"
    "scripts/cron/clean_logs.php"
    "scripts/cron/stats_flow.php"
)

for script in "${CRON_SCRIPTS[@]}"; do
    if [ -f "$script" ]; then
        chmod 755 "$script"
        echo -e "${GREEN}  ✓ ${script} 权限已设置 (755)${NC}"
    else
        echo -e "${YELLOW}  ⚠ ${script} 不存在（跳过）${NC}"
    fi
done

# 设置目录所有者
chown -R "$WEB_USER:$WEB_USER" database/ 2>/dev/null || true
chown -R "$WEB_USER:$WEB_USER" storage/

echo -e "${GREEN}  ✓ 目录所有者已设置为: ${WEB_USER}${NC}"

echo ""

# 总结
echo -e "${BLUE}[5/5] 配置完成总结${NC}"
echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║                    ✓ 权限配置成功！                            ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "配置详情："
echo -e "  • Web服务器用户: ${GREEN}${WEB_USER}${NC}"
echo -e "  • Sudoers配置文件: ${GREEN}${SUDOERS_FILE}${NC}"
echo -e "  • 数据库目录: ${GREEN}${PROJECT_ROOT}/database${NC}"
echo -e "  • 日志目录: ${GREEN}${PROJECT_ROOT}/storage/logs${NC}"
echo -e "  • 脚本目录: ${GREEN}${PROJECT_ROOT}/scripts/cron${NC}"
echo ""
echo -e "${BLUE}下一步：${NC}"
echo -e "  1. 访问安装页面完成系统安装（如果还未安装）"
echo -e "  2. 登录管理后台，进入「定时任务」页面"
echo -e "  3. 为用户配置定时查询任务"
echo -e "  4. 系统将自动创建crontab定时任务"
echo ""
echo -e "${YELLOW}注意：${NC}"
echo -e "  • 如果定时任务仍然不工作，请检查系统日志: ${PROJECT_ROOT}/storage/logs/system.log"
echo -e "  • 可以手动测试crontab: ${BLUE}sudo -u ${WEB_USER} sudo crontab -l${NC}"
echo ""
