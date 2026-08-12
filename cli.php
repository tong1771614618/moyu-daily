#!/usr/bin/env php
<?php
/**
 * ============================================================
 * 摸鱼办 - CLI 命令行入口
 * ============================================================
 * 功能：在终端执行，生成摸鱼日报图片，可选发送邮件。
 *       支持手动执行和定时任务（cron）。
 *
 * 使用方式：
 *   php cli.php                  # 生成图片
 *   php cli.php --send           # 生成图片并发送邮件
 *   php cli.php --output /path   # 指定输出目录
 *   php cli.php --help           # 显示帮助信息
 *   php cli.php --quiet          # 静默模式（不输出日志）
 *
 * 定时任务（cron）示例：
 *   # 每天早上8:30自动生成并发送邮件
 *   30 8 * * * /usr/bin/php /path/to/moyu-office/cli.php --send --quiet
 *
 *   # 每天中午12:00生成图片（不发邮件）
 *   0 12 * * * /usr/bin/php /path/to/moyu-office/cli.php --quiet
 * ============================================================
 */

// ==================== 检查 CLI 环境 ====================

if (php_sapi_name() !== 'cli') {
    die("此脚本只能通过命令行执行，请使用: php cli.php\n");
}

// ==================== 解析命令行参数 ====================

$options = getopt('', ['send', 'output:', 'help', 'quiet', 'date:', 'text-only', 'copy']);

// 显示帮助信息
if (isset($options['help'])) {
    echo <<<HELP

  ╔═══════════════════════════════════════╗
  ║         🐟 摸鱼办 CLI 工具           ║
  ╚═══════════════════════════════════════╝

  用法:
    php cli.php [选项]

  选项:
    --send              生成图片后发送邮件
    --output <目录>     指定图片输出目录
    --date <YYYY-MM-DD> 指定日期（默认今天）
    --text-only         只输出文字版，不生成图片
    --copy              自动复制文字版到剪贴板（macOS）
    --quiet             静默模式，不输出日志
    --help              显示此帮助信息

  示例:
    php cli.php                      # 生成今日图片 + 输出文字版
    php cli.php --send               # 生成并发送邮件
    php cli.php --text-only          # 只输出微信转发文字
    php cli.php --text-only --copy   # 输出文字并复制到剪贴板
    php cli.php --output /tmp        # 输出到指定目录

  定时任务:
    crontab -e
    30 8 * * * php /path/to/cli.php --send --quiet

HELP;
    exit(0);
}

$quiet     = isset($options['quiet']);
$sendEmail = isset($options['send']);
$textOnly  = isset($options['text-only']);
$copyText  = isset($options['copy']);
$log       = function ($msg) use ($quiet) {
    if (!$quiet) {
        echo '[' . date('H:i:s') . "] {$msg}\n";
    }
};

// ==================== 加载配置和模块 ====================

$log('🐟 摸鱼办启动...');

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/LunarCalendar.php';
require_once __DIR__ . '/HolidayCalculator.php';
require_once __DIR__ . '/WeatherFetcher.php';
require_once __DIR__ . '/FortuneGenerator.php';
require_once __DIR__ . '/ImageGenerator.php';
require_once __DIR__ . '/EmailSender.php';
require_once __DIR__ . '/TextGenerator.php';

// 加载 PHPMailer（如果已安装）
$phpmailerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($phpmailerAutoload)) {
    require_once $phpmailerAutoload;
}

// 设置时区
date_default_timezone_set($config['city']['timezone'] ?? 'Asia/Shanghai');

// ==================== 处理 --output 参数 ====================

if (isset($options['output'])) {
    $customDir = rtrim($options['output'], '/');
    if (!is_dir($customDir)) {
        mkdir($customDir, 0755, true);
    }
    $config['image']['output_dir'] = $customDir;
}

// ==================== 1. 获取农历日期 ====================

$log('📅 计算农历日期...');
$lunarDate = LunarCalendar::getToday();
$log(sprintf('   农历 %s%s (%s年 %s)',
    $lunarDate['month_name'],
    $lunarDate['day_name'],
    $lunarDate['gan_zhi'],
    $lunarDate['zodiac']
));

// ==================== 2. 计算节假日倒计时 ====================

$log('🎉 计算节假日倒计时...');
$holidayCalc = new HolidayCalculator($config['holidays']);
$countdowns = $holidayCalc->getCountdowns();

foreach (array_slice($countdowns, 0, 3) as $cd) {
    $daysLabel = ($cd['days'] == 0) ? '今天！' : "{$cd['days']}天";
    $log("   {$cd['emoji']} {$cd['name']}: {$daysLabel} ({$cd['date_str']})");
}
if (count($countdowns) > 3) {
    $log('   ... 还有 ' . (count($countdowns) - 3) . ' 个假期');
}

// ==================== 3. 获取天气数据 ====================

$log('🌤 获取天气数据...');
$weatherFetcher = new WeatherFetcher($config['weather'], $config['city']);
$weatherData = $weatherFetcher->fetch();

if ($weatherData['temp'] !== '--') {
    $log(sprintf('   %s %s，%s°C，湿度 %s%%',
        $weatherData['icon'],
        $weatherData['description'],
        $weatherData['temp'],
        $weatherData['humidity']
    ));
} else {
    $log('   ⚠ 天气数据获取失败，使用兜底数据');
}

// ==================== 4. 生成运势 ====================

$log('🔮 生成今日运势...');
$fortuneGen = new FortuneGenerator($lunarDate);
$fortuneData = $fortuneGen->generate();

$log(sprintf('   运势: %s %s | 摸鱼指数: %s',
    $fortuneData['fortune_level'],
    $fortuneData['fortune_emoji'],
    $fortuneData['moyu_stars']
));

// ==================== 5. 组装数据 & 生成图片（--text-only 时跳过图片） ====================

$weekdays = ['日', '一', '二', '三', '四', '五', '六'];
$weekdayIndex = (int)date('w');

$imageData = [
    'title'    => $config['text']['title'],
    'subtitle' => $config['text']['subtitle'],
    'date_info' => [
        'gregorian'  => date('Y年m月d日') . ' 星期' . $weekdays[$weekdayIndex],
        'weekday'    => '星期' . $weekdays[$weekdayIndex],
        'month_name' => $lunarDate['month_name'],
        'day_name'   => $lunarDate['day_name'],
        'gan_zhi'    => $lunarDate['gan_zhi'],
        'zodiac'     => $lunarDate['zodiac'],
    ],
    'weather'  => $weatherData,
    'holidays' => $countdowns,
    'fortune'  => $fortuneData,
    'footer'   => $config['text']['footer'],
];

$imagePath = null;

if (!$textOnly) {
    $log('🎨 生成信息卡片...');
    try {
        $imageGen = new ImageGenerator($config['image']);
        $imagePath = $imageGen->generate($imageData);
        $log("   ✅ 图片已保存: {$imagePath}");
    } catch (RuntimeException $e) {
        fwrite(STDERR, "   ❌ 图片生成失败: " . $e->getMessage() . "\n");
        exit(1);
    }
}

// ==================== 6. 生成文字版 ====================

$log('📝 生成微信转发文字...');
$textGen = new TextGenerator();
$textOutput = $textGen->generate($imageData);

// 同时保存文字版到文件
$textDir = $config['image']['output_dir'] ?? __DIR__ . '/output';
if (!is_dir($textDir)) {
    mkdir($textDir, 0755, true);
}
$textPath = rtrim($textDir, '/') . '/moyu_' . date('Ymd') . '.txt';
file_put_contents($textPath, $textOutput);
$log("   ✅ 文字已保存: {$textPath}");

// ==================== 7. 发送邮件（可选） ====================

if ($sendEmail && $imagePath) {
    $log('📧 发送邮件...');
    $emailSender = new EmailSender($config['email']);
    $emailResult = $emailSender->send($imagePath, [
        'date'     => date('Y年m月d日'),
        'lunar'    => $lunarDate,
        'weather'  => $weatherData,
        'holidays' => $countdowns,
        'fortune'  => $fortuneData,
    ]);

    if ($emailResult['success']) {
        $log('   ✅ ' . $emailResult['message']);
    } else {
        $log('   ⚠ ' . $emailResult['message']);
    }
}

// ==================== 完成 ====================

$log('');
if ($imagePath) {
    $log('🐟 摸鱼办任务完成！');
    $log('   图片: ' . $imagePath);
} else {
    $log('🐟 摸鱼办任务完成！');
}
$log('   文字: ' . $textPath);
$log('   ' . $fortuneData['tip']);

// 输出文字版到标准输出
echo "\n";
echo $textOutput;
echo "\n";

// 复制到剪贴板（macOS 使用 pbcopy）
if ($copyText) {
    $descriptors = [['pipe', 'r']];
    $process = proc_open('pbcopy', $descriptors, $pipes);
    if (is_resource($process)) {
        fwrite($pipes[0], $textOutput);
        fclose($pipes[0]);
        proc_close($process);
        echo "\n✅ 已复制到剪贴板，可直接粘贴到微信\n";
    } else {
        echo "\n⚠ 复制到剪贴板失败（仅支持 macOS）\n";
    }
}

// 静默模式只返回图片路径（方便脚本调用）
if ($quiet) {
    echo $imagePath ? $imagePath : $textPath;
    echo "\n";
}

exit(0);
