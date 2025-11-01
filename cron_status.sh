#!/bin/bash

##############################################################################
# 定时任务状态查看脚本
##############################################################################

echo "========================================"
echo "  联通流量监控 - 定时任务状态"
echo "========================================"
echo ""

# 1. 查看www-data用户的crontab
echo "1. 当前定时任务："
echo "-----------------------------------"
sudo crontab -u www-data -l 2>/dev/null | grep -A1 FlowMonitor | while IFS= read -r line; do
    if [[ $line == \#* ]]; then
        token=$(echo "$line" | awk '{print $3}')
        token_short=$(echo "$token" | cut -c1-8)
        echo "用户Token: ${token_short}..."
    elif [[ $line == *"*"* ]]; then
        cron_expr=$(echo "$line" | awk '{print $1, $2, $3, $4, $5}')
        echo "执行频率: $cron_expr"
        echo ""
    fi
done

total_tasks=$(sudo crontab -u www-data -l 2>/dev/null | grep -c FlowMonitor)
echo "总任务数: $total_tasks"
echo ""

# 2. 查看最近的日志
echo "2. 最近的执行日志："
echo "-----------------------------------"
if ls logs/cron_*.log >/dev/null 2>&1; then
    for log in logs/cron_*.log; do
        token_short=$(basename "$log" .log | sed 's/cron_//')
        echo "[$token_short]"
        tail -3 "$log" 2>/dev/null | sed 's/^/  /'
        echo ""
    done
else
    echo "暂无日志文件"
    echo ""
fi

# 3. 当前时间和下次执行时间
echo "3. 时间信息："
echo "-----------------------------------"
echo "当前时间: $(date '+%Y-%m-%d %H:%M:%S')"
current_minute=$(date +%M)
next_5min=$((current_minute / 5 * 5 + 5))
if [ $next_5min -ge 60 ]; then
    next_hour=$(($(date +%H) + 1))
    next_minute=0
else
    next_hour=$(date +%H)
    next_minute=$next_5min
fi
echo "下次执行: $(date '+%Y-%m-%d') $(printf '%02d:%02d:00' $next_hour $next_minute)"
echo ""

# 4. 用户配置统计
echo "4. 用户配置统计："
echo "-----------------------------------"
total_configs=$(find data/*/notify.json 2>/dev/null | wc -l)
enabled_configs=0

for config in data/*/notify.json; do
    if [ -f "$config" ]; then
        type=$(jq -r '.type // ""' "$config" 2>/dev/null)
        threshold=$(jq -r '.threshold // 0' "$config" 2>/dev/null)
        interval=$(jq -r '.interval // 0' "$config" 2>/dev/null)
        
        if [[ -n "$type" && "$threshold" -gt 0 && "$interval" -gt 0 ]]; then
            ((enabled_configs++))
        fi
    fi
done

echo "总配置数: $total_configs"
echo "满足条件: $enabled_configs"
echo "定时任务: $total_tasks"
echo ""

if [ $enabled_configs -ne $total_tasks ]; then
    echo "⚠️  警告: 配置数量与任务数量不一致！"
    echo ""
fi

echo "========================================"
echo ""
echo "提示："
echo "  - 查看实时日志: tail -f logs/cron_*.log"
echo "  - 查看所有任务: sudo crontab -u www-data -l"
echo "  - 手动执行: sudo -u www-data php cron_query.php <token>"
echo ""
