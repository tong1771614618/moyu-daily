<?php
/**
 * ============================================================
 * 摸鱼办 - Web 展示页面
 * ============================================================
 * 功能：浏览器访问时，展示今日摸鱼日报图片和微信转发文字。
 *
 * 访问方式：
 *   http://your-server/moyu-office/index.php
 *   http://your-server/moyu-office/index.php?refresh=1  (强制重新生成)
 *
 * 流程：
 *   1. 检查今日图片是否已存在
 *   2. 若不存在或强制刷新，调用数据模块重新生成
 *   3. 输出 HTML 页面展示图片和文字版
 * ============================================================
 */

// ==================== 1. 加载配置和模块 ====================

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/LunarCalendar.php';
require_once __DIR__ . '/HolidayCalculator.php';
require_once __DIR__ . '/WeatherFetcher.php';
require_once __DIR__ . '/FortuneGenerator.php';
require_once __DIR__ . '/ImageGenerator.php';
require_once __DIR__ . '/TextGenerator.php';
require_once __DIR__ . '/EmailSender.php';

// 加载 PHPMailer（如果已安装）
$phpmailerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($phpmailerAutoload)) {
    require_once $phpmailerAutoload;
}

// 设置时区
date_default_timezone_set($config['city']['timezone'] ?? 'Asia/Shanghai');

// ==================== 2. 确定今日输出文件路径 ====================

$outputDir = $config['image']['output_dir'] ?? __DIR__ . '/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$today       = date('Ymd');
$imagePath   = rtrim($outputDir, '/') . '/moyu_' . $today . '.png';
$textPath    = rtrim($outputDir, '/') . '/moyu_' . $today . '.txt';
$needRefresh = !file_exists($imagePath) || !file_exists($textPath)
               || isset($_GET['refresh']);

// ==================== 3. 按需生成数据 ====================

if ($needRefresh) {
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

    // 生成图片
    try {
        $imageGen  = new ImageGenerator($config['image']);
        $imagePath = $imageGen->generate($imageData);
    } catch (RuntimeException $e) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<h2>图片生成失败</h2><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        exit(1);
    }

    // 生成文字版
    $textGen    = new TextGenerator();
    $textOutput = $textGen->generate($imageData);
    file_put_contents($textPath, $textOutput);
}

// ==================== 4. 读取已生成的文件 ====================

$imageBase64 = base64_encode(file_get_contents($imagePath));
$textContent = file_get_contents($textPath);

// 格式化日期标题
$dateTitle = date('Y年m月d日') . ' 摸鱼日报';

// ==================== 5. 输出 HTML 页面 ====================

$pageTitle   = htmlspecialchars($config['text']['title'] . ' · ' . $config['text']['subtitle']);
$footerText  = htmlspecialchars($config['text']['footer']);
$cityName    = htmlspecialchars($config['city']['name'] ?? '');
$refreshLink = '?refresh=1';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?> - <?= htmlspecialchars($dateTitle) ?></title>
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

  /* 页面头部 */
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

  /* 图片卡片 */
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

  /* 操作栏 */
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
  .btn-refresh {
    background: rgba(255,255,255,0.12);
    color: #fff;
    backdrop-filter: blur(4px);
  }
  .btn-refresh:hover { background: rgba(255,255,255,0.2); }
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

  /* 文字版卡片 */
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

  /* 页脚 */
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

  <!-- 头部 -->
  <div class="page-header">
    <h1>🐟 摸鱼办</h1>
    <div class="subtitle"><?= htmlspecialchars($config['text']['subtitle']) ?></div>
    <div class="date-badge">📅 <?= htmlspecialchars($dateTitle) ?></div>
  </div>

  <!-- 图片 -->
  <div class="image-card">
    <img src="data:image/png;base64,<?= $imageBase64 ?>" alt="摸鱼日报 <?= htmlspecialchars($today) ?>">
  </div>

  <!-- 操作栏 -->
  <div class="action-bar">
    <a class="btn-refresh" href="<?= $refreshLink ?>">🔄 刷新</a>
    <a class="btn-download" href="data:image/png;base64,<?= $imageBase64 ?>" download="moyu_<?= $today ?>.png">⬇️ 下载</a>
    <button class="btn-copy" id="copyBtn" onclick="copyText()">📋 复制文字</button>
  </div>

  <!-- 文字版 -->
  <div class="text-card">
    <h3>📝 微信转发文字版</h3>
    <pre id="textContent"><?= htmlspecialchars($textContent) ?></pre>
  </div>

  <!-- 页脚 -->
  <div class="page-footer"><?= $footerText ?></div>

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