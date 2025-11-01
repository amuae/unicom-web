#!/bin/bash

# Android Studio 中文语言包自动安装脚本
# 作者: AI Assistant
# 日期: 2025-10-30

set -e

echo "=========================================="
echo "  Android Studio 中文语言包安装工具"
echo "=========================================="
echo ""

# 配置变量
AS_CONFIG_DIR="$HOME/.config/Google/AndroidStudio2025.1.4"
PLUGINS_DIR="$AS_CONFIG_DIR/plugins"
TEMP_DIR="/tmp/as-chinese-pack"

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检查 Android Studio 是否正在运行
check_as_running() {
    if pgrep -f "AndroidStudio" > /dev/null; then
        echo -e "${YELLOW}⚠️  检测到 Android Studio 正在运行${NC}"
        echo -e "${YELLOW}请先关闭 Android Studio，然后按回车继续...${NC}"
        read -r
    fi
}

# 创建目录
create_directories() {
    echo "📁 创建插件目录..."
    mkdir -p "$PLUGINS_DIR"
    mkdir -p "$TEMP_DIR"
    echo -e "${GREEN}✓${NC} 目录创建完成"
}

# 下载语言包
download_language_pack() {
    echo ""
    echo "📥 下载中文语言包..."

    cd "$TEMP_DIR"

    # 尝试使用 wget
    if command -v wget &> /dev/null; then
        wget -O chinese.zip "https://plugins.jetbrains.com/plugin/download?rel=true&updateId=884926" || \
        wget -O chinese.zip "https://plugins.jetbrains.com/files/13710/884926/zh-253.162.zip"
    # 尝试使用 curl
    elif command -v curl &> /dev/null; then
        curl -L -o chinese.zip "https://plugins.jetbrains.com/plugin/download?rel=true&updateId=884926" || \
        curl -L -o chinese.zip "https://plugins.jetbrains.com/files/13710/884926/zh-253.162.zip"
    else
        echo -e "${RED}❌ 错误: 未找到 wget 或 curl 命令${NC}"
        echo "请手动下载语言包: https://plugins.jetbrains.com/plugin/13710-chinese-simplified-language-pack----/versions"
        exit 1
    fi

    if [ -f "chinese.zip" ] && [ -s "chinese.zip" ]; then
        echo -e "${GREEN}✓${NC} 下载完成"
    else
        echo -e "${RED}❌ 下载失败${NC}"
        exit 1
    fi
}

# 解压并安装
install_language_pack() {
    echo ""
    echo "📦 安装中文语言包..."

    cd "$TEMP_DIR"

    if [ ! -f "chinese.zip" ]; then
        echo -e "${RED}❌ 未找到语言包文件${NC}"
        exit 1
    fi

    # 解压到插件目录
    unzip -q -o "chinese.zip" -d "$PLUGINS_DIR/"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓${NC} 安装完成"
    else
        echo -e "${RED}❌ 安装失败${NC}"
        exit 1
    fi
}

# 清理临时文件
cleanup() {
    echo ""
    echo "🧹 清理临时文件..."
    rm -rf "$TEMP_DIR"
    echo -e "${GREEN}✓${NC} 清理完成"
}

# 显示完成信息
show_completion() {
    echo ""
    echo "=========================================="
    echo -e "${GREEN}✅ 中文语言包安装成功！${NC}"
    echo "=========================================="
    echo ""
    echo "📋 下一步操作："
    echo "  1. 启动 Android Studio"
    echo "  2. 等待 IDE 完全加载"
    echo "  3. 界面将自动切换为中文"
    echo ""
    echo "如果界面未切换，请："
    echo "  1. 打开 Settings (Ctrl+Alt+S)"
    echo "  2. 进入 Plugins"
    echo "  3. 找到 'Chinese Language Pack'"
    echo "  4. 确保已启用，然后重启 IDE"
    echo ""
    echo -e "${YELLOW}现在可以启动 Android Studio 了！${NC}"
}

# 手动安装说明
show_manual_instructions() {
    echo ""
    echo "=========================================="
    echo "  📖 手动安装说明"
    echo "=========================================="
    echo ""
    echo "如果自动安装失败，请按以下步骤手动安装："
    echo ""
    echo "方法一：通过 Android Studio 插件市场"
    echo "  1. 启动 Android Studio"
    echo "  2. 按 Ctrl+Alt+S 打开设置"
    echo "  3. 点击左侧 'Plugins'"
    echo "  4. 点击 'Marketplace' 标签"
    echo "  5. 搜索 'Chinese'"
    echo "  6. 找到 'Chinese (Simplified) Language Pack'"
    echo "  7. 点击 'Install' 并重启"
    echo ""
    echo "方法二：从磁盘安装"
    echo "  1. 下载语言包: https://plugins.jetbrains.com/plugin/13710"
    echo "  2. Android Studio -> Settings -> Plugins"
    echo "  3. 点击 ⚙️ -> Install Plugin from Disk"
    echo "  4. 选择下载的 zip 文件"
    echo "  5. 重启 Android Studio"
    echo ""
}

# 主函数
main() {
    check_as_running
    create_directories

    echo ""
    echo "选择安装方式："
    echo "  1) 自动下载并安装（推荐）"
    echo "  2) 显示手动安装说明"
    echo "  3) 退出"
    echo ""
    read -p "请选择 [1-3]: " choice

    case $choice in
        1)
            download_language_pack
            install_language_pack
            cleanup
            show_completion
            ;;
        2)
            show_manual_instructions
            ;;
        3)
            echo "已取消安装"
            exit 0
            ;;
        *)
            echo -e "${RED}无效选择${NC}"
            exit 1
            ;;
    esac
}

# 运行主函数
main

