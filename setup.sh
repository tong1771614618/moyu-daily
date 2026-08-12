#!/bin/bash
# ============================================================
# 摸鱼办 - 安装与初始化脚本
# ============================================================
# 运行此脚本完成项目环境检测和初始化配置。
#
# 用法:
#   chmod +x setup.sh
#   ./setup.sh
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo ""
echo "  ╔═══════════════════════════════════════╗"
echo "  ║         🐟 摸鱼办 安装向导           ║"
echo "  ╚═══════════════════════════════════════╝"
echo ""

# ==================== 1. 检查 PHP 环境 ====================
echo "▸ 检查 PHP 环境..."

PHP_BIN=$(which php 2>/dev/null)
if [ -z "$PHP_BIN" ]; then
    echo "  ❌ PHP 未安装！请先安装 PHP 7.4+"
    echo "     macOS:   brew install php"
    echo "     Ubuntu:  sudo apt install php php-cli php-gd php-curl php-mbstring"
    echo "     CentOS:  sudo yum install php php-gd php-curl php-mbstring"
    exit 1
fi

PHP_VERSION=$($PHP_BIN -v | head -n1 | awk '{print $2}')
echo "  ✅ PHP $PHP_VERSION"

# 检查 GD 扩展
if $PHP_BIN -m | grep -qi 'gd'; then
    echo "  ✅ GD 扩展已安装"
else
    echo "  ❌ GD 扩展未安装！"
    echo "     macOS:   brew install php（默认包含GD）"
    echo "     Ubuntu:  sudo apt install php-gd"
    echo "     CentOS:  sudo yum install php-gd"
fi

# 检查 curl 扩展
if $PHP_BIN -m | grep -qi 'curl'; then
    echo "  ✅ cURL 扩展已安装"
else
    echo "  ⚠ cURL 扩展未安装（天气数据将无法获取）"
    echo "     Ubuntu:  sudo apt install php-curl"
fi

# 检查 mbstring 扩展
if $PHP_BIN -m | grep -qi 'mbstring'; then
    echo "  ✅ mbstring 扩展已安装"
else
    echo "  ⚠ mbstring 扩展未安装（中文处理可能异常）"
    echo "     Ubuntu:  sudo apt install php-mbstring"
fi

# ==================== 2. 检查中文字体 ====================
echo ""
echo "▸ 检查中文字体..."

FONT_FOUND=false
FONT_PATHS=(
    "${SCRIPT_DIR}/fonts/NotoSansSC-Regular.ttf"
    "/System/Library/Fonts/PingFang.ttc"
    "/System/Library/Fonts/STHeiti Light.ttc"
    "/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc"
    "/usr/share/fonts/truetype/wqy/wqy-microhei.ttc"
    "/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc"
)

for font in "${FONT_PATHS[@]}"; do
    if [ -f "$font" ]; then
        echo "  ✅ 找到字体: $font"
        FONT_FOUND=true
        break
    fi
done

if [ "$FONT_FOUND" = false ]; then
    echo "  ⚠ 未找到中文字体，图片中的中文将无法正常显示"
    echo "  解决方案（任选其一）："
    echo "    1. 下载 Noto Sans SC 字体:"
    echo "       curl -L 'https://github.com/google/fonts/raw/main/ofl/notosanssc/NotoSansSC%5Bwght%5D.ttf' -o fonts/NotoSansSC-Regular.ttf"
    echo "    2. Ubuntu/Debian: sudo apt install fonts-wqy-zenhei"
    echo "    3. macOS 自带 PingFang 字体，通常无需额外操作"
fi

# ==================== 3. 创建必要目录 ====================
echo ""
echo "▸ 创建目录结构..."

mkdir -p "${SCRIPT_DIR}/output"
mkdir -p "${SCRIPT_DIR}/fonts"
echo "  ✅ output/ (图片输出目录)"
echo "  ✅ fonts/  (字体文件目录)"

# ==================== 4. 设置文件权限 ====================
echo ""
echo "▸ 设置文件权限..."

chmod +x "${SCRIPT_DIR}/cron.sh" 2>/dev/null
chmod +x "${SCRIPT_DIR}/setup.sh" 2>/dev/null
chmod +x "${SCRIPT_DIR}/cli.php" 2>/dev/null
chmod 755 "${SCRIPT_DIR}/output" 2>/dev/null
echo "  ✅ 脚本执行权限已设置"

# ==================== 5. Composer 依赖（可选） ====================
echo ""
echo "▸ 检查 Composer 依赖..."

if [ -f "${SCRIPT_DIR}/composer.json" ]; then
    echo "  找到 composer.json"
    COMPOSER_BIN=$(which composer 2>/dev/null)
    if [ -n "$COMPOSER_BIN" ]; then
        echo "  正在安装依赖..."
        cd "${SCRIPT_DIR}" && $COMPOSER_BIN install --quiet --no-interaction 2>/dev/null
        if [ $? -eq 0 ]; then
            echo "  ✅ PHPMailer 已安装（邮件功能可用）"
        else
            echo "  ⚠ Composer 安装失败，邮件功能将使用兜底方案"
        fi
    else
        echo "  ⚠ Composer 未安装"
        echo "  如需邮件功能: https://getcomposer.org/download/"
        echo "  安装后执行: composer install"
    fi
else
    echo "  ℹ 无 composer.json，跳过"
    echo "  如需邮件功能:"
    echo "    composer init --name moyu/office --no-interaction"
    echo "    composer require phpmailer/phpmailer"
fi

# ==================== 6. 配置文件检查 ====================
echo ""
echo "▸ 检查配置文件..."

CONFIG_FILE="${SCRIPT_DIR}/config.php"
if [ -f "$CONFIG_FILE" ]; then
    echo "  ✅ config.php 存在"

    # 检查 API Key 是否已配置
    if grep -q "YOUR_OPENWEATHERMAP_API_KEY" "$CONFIG_FILE"; then
        echo "  ⚠ 天气 API Key 未配置"
        echo "    请编辑 config.php，将 weather.api_key 替换为你的 OpenWeatherMap API Key"
        echo "    注册地址: https://openweathermap.org/api"
    else
        echo "  ✅ 天气 API Key 已配置"
    fi
else
    echo "  ❌ config.php 不存在！"
fi

# ==================== 完成 ====================
echo ""
echo "  ╔═══════════════════════════════════════╗"
echo "  ║         ✅ 安装完成！                ║"
echo "  ╚═══════════════════════════════════════╝"
echo ""
echo "  快速开始:"
echo "    手动执行:  php cli.php"
echo "    网页访问:  http://localhost/moyu-office/index.php"
echo "    定时任务:  crontab -e"
echo "               30 8 * * * bash ${SCRIPT_DIR}/cron.sh"
echo ""
