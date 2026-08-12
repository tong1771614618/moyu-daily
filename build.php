#!/usr/bin/env php
<?php
/**
 * ============================================================
 * 摸鱼办 - 静态页面构建脚本
 * ============================================================
 * 功能：生成静态 index.html，供 Cloudflare Pages 等静态托管使用。
 *       将图片以 base64 内嵌，无需额外图片文件即可展示。
 *
 * 使用方式：
 *   php build.php                # 生成到项目根目录
 *   php build.php --output dist  # 生成到指定目录
 * ============================================================
 */

if (php_sapi_name() !== 'cli') {
    die("此脚本只能通过命令行执行\n");
}

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/LunarCalendar.php';
require_once __DIR__ . '/HolidayCalculator.php';
require_once __DIR__ . '/WeatherFetcher.php';
require_once __DIR__ . '/FortuneGenerator.php';
require_once __DIR__ . '/ImageGenerator.php';
require_once __DIR__ . '/TextGenerator.php';

date_default_timezone_set($config['city']['timezone'] ?? 'Asia/Shanghai');

// ==================== 1. 获取数据 ====================

echo "[build] 获取数据...\n";

$lunarDate   = LunarCalendar::getToday();
$holidayCalc = new HolidayCalculator($config['holidays']);
$countdowns  = $holidayCalc->getCountdowns();

$weatherFetcher = new WeatherFetcher($config['weather'], $config['city']);
$weatherData    = $weatherFetcher->fetch();

$fortuneGen  = new FortuneGenerator($lunarDate);
$fortuneData = $fortuneGen->generate();

$weekdays     = ['日', '一', '二', '三', '四', '五', '六'];
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

// ==================== 2. 生成图片 ====================

echo "[build] 生成图片...\n";
$outputDir = $config['image']['output_dir'] ?? __DIR__ . '/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}
$config['image']['output_dir'] = $outputDir;

$imageGen  = new ImageGenerator($config['image']);
$imagePath = $imageGen->generate($imageData);
echo "[build] 图片: $imagePath\n";

// ==================== 3. 生成文字版 ====================

echo "[build] 生成文字版...\n";
$textGen    = new TextGenerator();
$textContent = $textGen->generate($imageData);

$textPath = rtrim($outputDir, '/') . '/moyu_' . date('Ymd') . '.txt';
file_put_contents($textPath, $textContent);
echo "[build] 文字: $textPath\n";

// ==================== 4. 生成静态 HTML ====================

echo "[build] 生成静态 HTML...\n";

$imageBase64 = base64_encode(file_get_contents($imagePath));
$today       = date('Ymd');
$dateTitle   = date('Y年m月d日') . ' 摸鱼日报';
$pageTitle   = htmlspecialchars($config['text']['title'] . ' · ' . $config['text']['subtitle']);
$footerText  = htmlspecialchars($config['text']['footer']);
$subtitle    = htmlspecialchars($config['text']['subtitle']);
$ts          = time();
$updateTime  = date('Y-m-d H:i:s');
$textEscaped = htmlspecialchars($textContent);

// 确定输出目录
$buildDir = __DIR__;
if (isset($argv) && in_array('--output', $argv)) {
    $idx = array_search('--output', $argv);
    if (isset($argv[$idx + 1])) {
        $buildDir = rtrim($argv[$idx + 1], '/');
        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0755, true);
        }
    }
}

$html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$pageTitle} - {$dateTitle}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: -apple-system, "PingFang SC", "Microsoft YaHei", "Helvetica Neue", sans-serif;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    min-height: 100vh;
    padding: 40px 20px;
    color: #333;
  }

  .container {
    max-width: 520px;
    margin: 0 auto;
  }

  .page-header {
    text-align: center;
    margin-bottom: 32px;
    color: #fff;
  }
  .page-header h1 {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: 2px;
    margin-bottom: 6px;
  }
  .page-header .subtitle {
    font-size: 14px;
    color: rgba(255,255,255,0.6);
  }
  .page-header .date-badge {
    display: inline-block;
    margin-top: 12px;
    padding: 4px 16px;
    background: rgba(255,255,255,0.12);
    border-radius: 20px;
    font-size: 13px;
    color: rgba(255,255,255,0.8);
    backdrop-filter: blur(4px);
  }
  .page-header .update-time {
    display: block;
    margin-top: 8px;
    font-size: 11px;
    color: rgba(255,255,255,0.35);
  }

  .image-card {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    margin-bottom: 24px;
  }
  .image-card img {
    width: 100%;
    display: block;
  }

  .action-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
  }
  .action-bar a, .action-bar button {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px 0;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
  }
  .btn-download {
    background: rgba(255,255,255,0.12);
    color: #fff;
    backdrop-filter: blur(4px);
  }
  .btn-download:hover { background: rgba(255,255,255,0.2); }
  .btn-copy {
    background: rgba(255,255,255,0.12);
    color: #fff;
    backdrop-filter: blur(4px);
  }
  .btn-copy:hover { background: rgba(255,255,255,0.2); }
  .btn-copy.copied { background: #27ae60; }

  .text-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    margin-bottom: 24px;
  }
  .text-card h3 {
    font-size: 15px;
    color: #888;
    margin-bottom: 16px;
    font-weight: 500;
    letter-spacing: 1px;
  }
  .text-card pre {
    font-family: -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif;
    font-size: 14px;
    line-height: 1.8;
    color: #333;
    white-space: pre-wrap;
    word-break: break-all;
    margin: 0;
  }

  .page-footer {
    text-align: center;
    color: rgba(255,255,255,0.35);
    font-size: 12px;
    padding: 20px 0 10px;
  }
</style>
</head>
<body>

<div class="container">

  <div class="page-header">
    <h1>🐟 摸鱼办</h1>
    <div class="subtitle">{$subtitle}</div>
    <div class="date-badge">📅 {$dateTitle}</div>
    <span class="update-time">更新于 {$updateTime}</span>
  </div>

  <div class="image-card">
    <img src="data:image/png;base64,{$imageBase64}" alt="摸鱼日报 {$today}">
  </div>

  <div class="action-bar">
    <a class="btn-download" href="data:image/png;base64,{$imageBase64}" download="moyu_{$today}.png">⬇️ 下载图片</a>
    <button class="btn-copy" id="copyBtn" onclick="copyText()">📋 复制文字</button>
  </div>

  <div class="text-card">
    <h3>📝 微信转发文字版</h3>
    <pre id="textContent">{$textEscaped}</pre>
  </div>

  <div class="page-footer">{$footerText}</div>

</div>

<script>
function copyText() {
  var text = document.getElementById('textContent').innerText;
  navigator.clipboard.writeText(text).then(function() {
    var btn = document.getElementById('copyBtn');
    btn.textContent = '✅ 已复制';
    btn.classList.add('copied');
    setTimeout(function() {
      btn.textContent = '📋 复制文字';
      btn.classList.remove('copied');
    }, 2000);
  });
}
</script>

</body>
</html>
HTML;

$htmlPath = $buildDir . '/index.html';
file_put_contents($htmlPath, $html);

echo "[build] HTML: $htmlPath\n";
echo "[build] 构建完成！\n";
