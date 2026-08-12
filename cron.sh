#!/bin/bash
# ============================================================
# 摸鱼办 - 定时任务脚本
# ============================================================
# 此脚本用于 cron 定时任务调用，封装 php cli.php 的执行。
#
# 安装定时任务:
#   chmod +x cron.sh
#   crontab -e
#   # 添加以下行（每天早上 8:30 执行）:
#   30 8 * * * /bin/bash /path/to/moyu-office/cron.sh
#
# 查看已安装的定时任务:
#   crontab -l
#
# 日志输出到 cron.log，便于排查问题
# ============================================================

# 项目根目录（脚本所在目录）
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# PHP 路径（根据实际环境修改）
PHP_BIN=$(which php)
if [ -z "$PHP_BIN" ]; then
    echo "[ERROR] PHP 未找到，请确认已安装 PHP" >&2
    exit 1
fi

# 日志文件
LOG_FILE="${SCRIPT_DIR}/output/cron.log"

# 确保 output 目录存在
mkdir -p "${SCRIPT_DIR}/output"

# 执行时间
echo "========================================" >> "$LOG_FILE"
echo "执行时间: $(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG_FILE"
echo "========================================" >> "$LOG_FILE"

# 执行摸鱼办 CLI
$PHP_BIN "${SCRIPT_DIR}/cli.php" --send --quiet >> "$LOG_FILE" 2>&1
EXIT_CODE=$?

echo "退出码: $EXIT_CODE" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

# 清理30天前的日志（避免日志无限增长）
if [ -f "$LOG_FILE" ]; then
    TEMP_LOG=$(mktemp)
    tail -n 500 "$LOG_FILE" > "$TEMP_LOG"
    mv "$TEMP_LOG" "$LOG_FILE"
fi

exit $EXIT_CODE
