# 摸鱼办 (MoYu Office)

每日摸鱼日报生成器 —— 自动汇总节假日倒计时、天气、农历和运势，生成精美信息卡片。

## 功能特性

- **节假日倒计时** — 自动计算距离春节、端午、中秋等节日的剩余天数
- **实时天气** — 通过 OpenWeatherMap API 获取当前天气
- **农历日历** — 自动转换农历日期、天干地支、生肖
- **今日运势** — 娱乐性质的摸鱼运势（基于农历日期的伪随机算法）
- **信息卡片** — 以上信息汇总渲染为一张精美 PNG 图片
- **微信转发文字** — 自动生成适合粘贴到微信的纯文本版本，支持一键复制
- **网页展示** — 自带 HTML 页面，浏览器打开即可查看图片和文字版
- **邮件推送** — 支持通过 SMTP 邮件发送日报

## 快速开始

### 1. 环境要求

- PHP 7.4+
- 扩展：`gd`, `curl`, `mbstring`
- 中文字体（TTF 格式）

### 2. 安装

```bash
# 克隆或复制项目到服务器目录
cd moyu-office

# 运行安装向导（自动检测环境）
chmod +x setup.sh
./setup.sh

# 安装邮件依赖（可选）
composer install
```

### 3. 配置

编辑 `config.php`：

```php
// 设置城市
'city' => [
    'name' => '上海',
    'latitude' => 31.2304,
    'longitude' => 121.4737,
],

// 配置天气 API Key（免费注册: https://openweathermap.org/api）
'weather' => [
    'api_key' => '你的API_KEY',
],

// 配置邮件（可选）
'email' => [
    'enabled'  => true,
    'username' => 'your@qq.com',
    'password' => 'SMTP授权码',
    'to'       => ['colleague@example.com'],
],
```

也可以通过环境变量配置敏感信息：

```bash
export MOYU_WEATHER_API_KEY="your_api_key"
export MOYU_SMTP_HOST="smtp.qq.com"
export MOYU_SMTP_USER="your@qq.com"
export MOYU_SMTP_PASS="smtp_auth_code"
```

### 4. 运行

**命令行执行：**

```bash
# 生成图片 + 微信转发文字
php cli.php

# 生成并发送邮件
php cli.php --send

# 只输出文字版，不生成图片
php cli.php --text-only

# 输出文字并自动复制到剪贴板（macOS）
php cli.php --text-only --copy

# 指定输出目录
php cli.php --output /path/to/output

# 查看帮助
php cli.php --help
```

**Web 访问：**

```
http://your-server/moyu-office/index.php            # 查看图片和文字版
http://your-server/moyu-office/index.php?refresh=1  # 强制重新生成数据
```

页面包含：图片展示、一键下载图片、一键复制微信转发文字。

### 5. 定时任务

```bash
# 编辑 crontab
crontab -e

# 每天早上 8:30 自动生成并发送邮件
30 8 * * * /bin/bash /path/to/moyu-office/cron.sh

# 每天中午 12:00 仅生成图片
0 12 * * * php /path/to/moyu-office/cli.php --quiet
```

## 项目结构

```
moyu-office/
├── config.php             # 全局配置（城市、API、邮件等）
├── LunarCalendar.php      # 农历日期转换（公历↔农历、生肖、天干地支）
├── HolidayCalculator.php  # 节假日倒计时计算
├── WeatherFetcher.php     # 天气 API 数据获取
├── FortuneGenerator.php   # 运势生成（伪随机算法）
├── ImageGenerator.php     # PHP GD 图片渲染
├── TextGenerator.php      # 微信转发文字版生成
├── EmailSender.php        # SMTP 邮件发送
├── index.php              # Web 入口（浏览器展示页面）
├── cli.php                # CLI 入口（命令行/定时任务）
├── cron.sh                # cron 定时任务封装脚本
├── setup.sh               # 安装与环境检测脚本
├── composer.json           # Composer 依赖
├── fonts/                 # 中文字体文件
├── output/                # 生成的图片和文字版输出目录
└── README.md
```

## 中文字体

图片中的中文渲染需要 TTF 字体文件，按以下任一方式配置：

1. **macOS** — 系统自带 PingFang 字体，无需额外操作
2. **下载 Noto Sans SC** — 放到 `fonts/` 目录：
   ```bash
   curl -L "https://github.com/google/fonts/raw/main/ofl/notosanssc/NotoSansSC%5Bwght%5D.ttf" \
     -o fonts/NotoSansSC-Regular.ttf
   ```
3. **Linux** — 安装文泉驿字体：
   ```bash
   sudo apt install fonts-wqy-zenhei
   ```

## 常见问题

**Q: 天气数据显示 "数据获取中..."**

A: 请检查 `config.php` 中的 `weather.api_key` 是否正确配置。免费 API Key 在 [OpenWeatherMap](https://openweathermap.org/api) 注册获取。

**Q: 邮件发送失败**

A: 确认已安装 PHPMailer（`composer install`），并正确配置了 SMTP 信息。QQ 邮箱需要使用"授权码"而非登录密码。

**Q: 图片中中文显示为方块**

A: 缺少中文字体，参考上方"中文字体"章节安装。

**Q: 如何添加自定义节日？**

A: 编辑 `config.php` 的 `holidays` 数组，支持公历和农历两种类型：

```php
// 公历节日
['name' => '公司周年庆', 'month' => 6, 'day' => 15, 'type' => 'gregorian', 'emoji' => '🎂'],

// 农历节日
['name' => '腊八节', 'lunar_month' => 12, 'lunar_day' => 8, 'type' => 'lunar', 'emoji' => '🥣'],
```

## 许可证

MIT License
