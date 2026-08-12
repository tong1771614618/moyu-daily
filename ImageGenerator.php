<?php
/**
 * ============================================================
 * 摸鱼办 - 图片生成模块
 * ============================================================
 * 功能：将节假日倒计时、天气、农历、运势等数据汇总渲染为
 *       一张精美的信息卡片图片（PNG格式）。
 *
 * 技术要点：
 *   - 使用 PHP GD 库绘制图形和文字
 *   - 渐变色背景 + 圆角卡片 + 阴影效果
 *   - 需要中文字体（TTF格式）支持中文渲染
 *   - 自动检测系统字体或从配置路径加载
 *
 * 布局设计（从上到下）：
 *   1. 标题区：「摸鱼办」标题 + 副标题
 *   2. 日期区：公历日期 + 星期 + 农历信息
 *   3. 天气区：温度、天气描述、湿度风速
 *   4. 倒计时区：未来节假日列表 + 进度条
 *   5. 运势区：运势评级、摸鱼指数、宜忌
 *   6. 底部：提示语 + 页脚
 *
 * 依赖：PHP GD 扩展 (php-gd)
 * ============================================================
 */

class ImageGenerator
{
    /** @var int 图片宽度 */
    private $width;

    /** @var int 图片高度 */
    private $height;

    /** @var resource|GdImage 图片资源 */
    private $image;

    /** @var string 字体文件路径 */
    private $fontPath;

    /** @var string 输出目录 */
    private $outputDir;

    /** @var string 输出文件名 */
    private $outputName;

    // ==================== 颜色定义 ====================
    // 预定义的颜色 RGB 值，在 init() 中分配为 GD 颜色

    /** @var array 背景渐变色（上→下） */
    private $bgColorTop = [102, 126, 234];   // 柔和蓝紫
    private $bgColorBottom = [118, 75, 162];  // 深紫

    /** @var int 卡片背景色 */
    private $cardColor;

    /** @var int 主标题颜色 */
    private $titleColor;

    /** @var int 正文颜色 */
    private $textColor;

    /** @var int 次级文字颜色 */
    private $textSecondaryColor;

    /** @var int 强调色（橙红） */
    private $accentColor;

    /** @var int 分割线颜色 */
    private $lineColor;

    /** @var int 进度条背景色 */
    private $progressBgColor;

    /** @var int 进度条填充色 */
    private $progressColor;

    /**
     * @param array $config 图片配置（来自 config.php 的 image 节）
     */
    public function __construct(array $config)
    {
        $this->width      = $config['width'] ?? 800;
        $this->height     = $config['height'] ?? 1400;
        $this->fontPath   = $config['font_path'] ?? '';
        $this->outputDir  = $config['output_dir'] ?? __DIR__ . '/output';
        $this->outputName = $config['output_name'] ?? 'moyu.png';
    }

    /**
     * 生成完整的信息卡片图片
     *
     * @param array $data 汇总数据，结构如下：
     *   - title:        标题
     *   - subtitle:     副标题
     *   - date_info:    ['gregorian', 'weekday', 'lunar', 'gan_zhi', 'zodiac']
     *   - weather:      天气数据数组
     *   - holidays:     节假日倒计时数组
     *   - fortune:      运势数据数组
     *   - footer:       页脚文字
     * @return string 生成的图片文件完整路径
     * @throws RuntimeException 当字体或GD库不可用时
     */
    public function generate(array $data): string
    {
        // 检查 GD 扩展
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('PHP GD 扩展未安装，请执行: apt install php-gd 或 brew install php');
        }

        // 检测字体
        $this->fontPath = $this->detectFont();

        // 创建图片（初始高度较大，确保足够绘制空间，最终会裁剪到实际高度）
        $this->image = imagecreatetruecolor($this->width, max($this->height, 2000));
        imageantialias($this->image, true);

        // 分配颜色
        $this->allocateColors();

        // 绘制背景
        $this->drawGradientBackground();

        // 当前绘制 Y 坐标指针
        $y = 30;

        // 绘制主卡片
        $y = $this->drawCard($y, function ($x, $y, $w) use ($data) {
            return $this->drawHeader($x, $y, $w, $data);
        });

        $y += 10;
        $y = $this->drawCard($y, function ($x, $y, $w) use ($data) {
            return $this->drawDateSection($x, $y, $w, $data['date_info']);
        });

        $y += 10;
        $y = $this->drawCard($y, function ($x, $y, $w) use ($data) {
            return $this->drawWeatherSection($x, $y, $w, $data['weather']);
        });

        $y += 10;
        $y = $this->drawCard($y, function ($x, $y, $w) use ($data) {
            return $this->drawHolidaySection($x, $y, $w, $data['holidays']);
        });

        $y += 10;
        $y = $this->drawCard($y, function ($x, $y, $w) use ($data) {
            return $this->drawFortuneSection($x, $y, $w, $data['fortune']);
        });

        // 绘制页脚
        $this->drawFooter($y + 20, $data['footer'] ?? '');

        // 动态裁剪到实际内容高度
        $actualHeight = min($y + 60, $this->height);
        $cropped = imagecrop($this->image, ['x' => 0, 'y' => 0, 'width' => $this->width, 'height' => $actualHeight]);
        if ($cropped !== false) {
            $this->image = $cropped;
        }

        // 保存文件
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
        $filePath = rtrim($this->outputDir, '/') . '/' . $this->outputName;
        imagepng($this->image, $filePath);

        return $filePath;
    }

    // ================================================================
    // 内部绘制方法
    // ================================================================

    /**
     * 分配 GD 颜色资源
     */
    private function allocateColors(): void
    {
        $this->cardColor           = imagecolorallocate($this->image, 255, 255, 255);
        $this->titleColor          = imagecolorallocate($this->image, 33, 37, 41);
        $this->textColor           = imagecolorallocate($this->image, 52, 58, 64);
        $this->textSecondaryColor  = imagecolorallocate($this->image, 108, 117, 125);
        $this->accentColor         = imagecolorallocate($this->image, 255, 107, 53);
        $this->lineColor           = imagecolorallocate($this->image, 233, 236, 239);
        $this->progressBgColor     = imagecolorallocate($this->image, 233, 236, 239);
        $this->progressColor       = imagecolorallocate($this->image, 99, 132, 255);
    }

    /**
     * 绘制渐变背景
     */
    private function drawGradientBackground(): void
    {
        $bandHeight = 3; // 每个色带3像素高，兼顾效果和性能
        $steps = (int)ceil($this->height / $bandHeight);

        for ($i = 0; $i < $steps; $i++) {
            $ratio = $i / $steps;
            $r = (int)($this->bgColorTop[0] + ($this->bgColorBottom[0] - $this->bgColorTop[0]) * $ratio);
            $g = (int)($this->bgColorTop[1] + ($this->bgColorBottom[1] - $this->bgColorTop[1]) * $ratio);
            $b = (int)($this->bgColorTop[2] + ($this->bgColorBottom[2] - $this->bgColorTop[2]) * $ratio);

            $color = imagecolorallocate($this->image, $r, $g, $b);
            imagefilledrectangle(
                $this->image,
                0, $i * $bandHeight,
                $this->width, ($i + 1) * $bandHeight,
                $color
            );
        }
    }

    /**
     * 绘制白色圆角卡片（含简单阴影）
     *
     * 采用两阶段绘制：先在临时图片上测量内容高度，再在正式图片上绘制，
     * 避免重复绘制导致的文字抗锯齿叠加残影。
     *
     * @param int      $y        卡片顶部Y坐标
     * @param callable $callback 卡片内容绘制回调 fn($x, $y, $width) => $newY
     * @return int 卡片底部Y坐标
     */
    private function drawCard(int $y, callable $callback): int
    {
        $padding = 30;        // 卡片左右边距
        $innerPad = 25;       // 卡片内边距
        $radius = 16;         // 圆角半径

        $cardX = $padding;
        $cardW = $this->width - $padding * 2;
        $contentX = $cardX + $innerPad;
        $contentY = $y + $innerPad;
        $contentW = $cardW - $innerPad * 2;

        // 第一遍：在临时图片上测量内容实际高度
        $tempImg = imagecreatetruecolor($this->width, 800);
        $tempFontColor = imagecolorallocate($tempImg, 0, 0, 0);
        $savedImage = $this->image;
        $this->image = $tempImg;
        $measureEndY = $callback($contentX, $contentY, $contentW);
        $this->image = $savedImage;

        // 计算实际卡片高度
        $cardH = ($measureEndY - $y) + $innerPad;

        // 第二遍：在正式图片上绘制阴影 + 卡片背景 + 内容
        $shadowColor = imagecolorallocatealpha($this->image, 0, 0, 0, 100);
        $this->drawRoundedRect($cardX + 2, $y + 3, $cardW, $cardH, $radius, $shadowColor);
        $this->drawRoundedRect($cardX, $y, $cardW, $cardH, $radius, $this->cardColor);
        $callback($contentX, $contentY, $contentW);

        return $y + $cardH;
    }

    /**
     * 绘制圆角矩形
     */
    private function drawRoundedRect(int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        // 四个圆角
        imagefilledarc($this->image, $x + $r, $y + $r, $r * 2, $r * 2, 180, 270, $color, IMG_ARC_PIE);
        imagefilledarc($this->image, $x + $w - $r, $y + $r, $r * 2, $r * 2, 270, 360, $color, IMG_ARC_PIE);
        imagefilledarc($this->image, $x + $r, $y + $h - $r, $r * 2, $r * 2, 90, 180, $color, IMG_ARC_PIE);
        imagefilledarc($this->image, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, 0, 90, $color, IMG_ARC_PIE);

        // 填充主体区域
        imagefilledrectangle($this->image, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($this->image, $x, $y + $r, $x + $w, $y + $h - $r, $color);
    }

    /**
     * 绘制标题区
     */
    private function drawHeader(int $x, int $y, int $w, array $data): int
    {
        // 标题
        $title = $data['title'] ?? '摸鱼办';
        $this->drawText($title, $x, $y + 8, 38, $this->titleColor, true);

        // 副标题（右对齐）
        $subtitle = $data['subtitle'] ?? '今日摸鱼指南';
        $this->drawTextRight($subtitle, $x + $w, $y + 14, 16, $this->textSecondaryColor);

        return $y + 50;
    }

    /**
     * 绘制日期区域
     */
    private function drawDateSection(int $x, int $y, int $w, array $dateInfo): int
    {
        // 公历日期（大字，已包含星期）
        $gregorian = $dateInfo['gregorian'] ?? date('Y年m月d日');
        $this->drawText($gregorian, $x, $y + 4, 28, $this->titleColor, true);

        // 分割线
        $lineY = $y + 48;
        imageline($this->image, $x, $lineY, $x + $w, $lineY, $this->lineColor);

        // 农历信息
        $lunarText = sprintf(
            '农历 %s%s',
            $dateInfo['month_name'] ?? '',
            $dateInfo['day_name'] ?? ''
        );
        $this->drawText($lunarText, $x, $lineY + 16, 18, $this->textColor);

        // 干支纪年 + 生肖
        $ganZhiText = sprintf(
            '%s年 · %s年',
            $dateInfo['gan_zhi'] ?? '',
            $dateInfo['zodiac'] ?? ''
        );
        $this->drawText($ganZhiText, $x, $lineY + 44, 16, $this->textSecondaryColor);

        return $lineY + 62;
    }

    /**
     * 绘制天气区域
     */
    private function drawWeatherSection(int $x, int $y, int $w, array $weather): int
    {
        // 区域标题
        $this->drawText('天气', $x, $y + 2, 20, $this->accentColor, true);

        // 天气图标 + 温度
        $icon = $weather['icon'] ?? '🌤';
        $temp = ($weather['temp'] !== '--') ? $weather['temp'] . '°C' : '--°C';
        $this->drawText($temp, $x + 60, $y, 28, $this->titleColor, true);

        // 天气描述
        $desc = $weather['description'] ?? '';
        $this->drawText($desc, $x + 160, $y + 8, 16, $this->textSecondaryColor);

        // 详细信息行
        $detailY = $y + 44;
        $details = [];
        if (isset($weather['feels_like']) && $weather['feels_like'] !== '--') {
            $details[] = "体感 {$weather['feels_like']}°C";
        }
        if (isset($weather['temp_min']) && $weather['temp_min'] !== '--') {
            $details[] = "{$weather['temp_min']}° ~ {$weather['temp_max']}°";
        }
        if (isset($weather['humidity']) && $weather['humidity'] !== '--') {
            $details[] = "湿度 {$weather['humidity']}%";
        }
        if (isset($weather['wind_speed']) && $weather['wind_speed'] !== '--') {
            $details[] = "风速 {$weather['wind_speed']}m/s";
        }

        $detailText = implode('  |  ', $details);
        $this->drawText($detailText, $x, $detailY, 14, $this->textSecondaryColor);

        return $detailY + 28;
    }

    /**
     * 绘制节假日倒计时区域
     */
    private function drawHolidaySection(int $x, int $y, int $w, array $holidays): int
    {
        // 区域标题
        $this->drawText('假期倒计时', $x, $y + 2, 20, $this->accentColor, true);
        $y += 36;

        // 最多显示 8 个节假日
        $showCount = min(count($holidays), 8);
        $rowHeight = 44;

        // 找到最大天数值，用于进度条比例
        $maxDays = 1;
        for ($i = 0; $i < $showCount; $i++) {
            $maxDays = max($maxDays, $holidays[$i]['days']);
        }

        for ($i = 0; $i < $showCount; $i++) {
            $h = $holidays[$i];
            $rowY = $y + $i * $rowHeight;

            // 彩色圆点指示器（替代无法渲染的 Emoji）
            $dotX = $x + 6;
            $dotY = $rowY + 12;
            $this->drawHolidayDot($h['name'], $dotX, $dotY, 5);

            // 节日名称 + 括号内的具体日期
            $dateObj = new \DateTime($h['date_str']);
            $dateLabel = (int)$dateObj->format('n') . '月' . (int)$dateObj->format('j') . '日';
            $this->drawText($h['name'] . ' (' . $dateLabel . ')', $x + 20, $rowY, 17, $this->textColor);

            // 倒计时天数（右对齐）
            $daysText = ($h['days'] == 0) ? '今天！' : "{$h['days']}天";
            $this->drawTextRight($daysText, $x + $w, $rowY, 17, $this->accentColor);

            // 进度条
            $barY = $rowY + 26;
            $barX = $x;
            $barW = $w;
            $barH = 8;

            // 背景条
            $this->drawRoundedRect($barX, $barY, $barW, $barH, 4, $this->progressBgColor);

            // 填充条（已过去的比例）
            if ($maxDays > 0 && $h['days'] < $maxDays) {
                $fillRatio = 1 - ($h['days'] / $maxDays);
                $fillW = max(8, (int)($barW * $fillRatio));
                $this->drawRoundedRect($barX, $barY, $fillW, $barH, 4, $this->progressColor);
            } elseif ($h['days'] == 0) {
                $this->drawRoundedRect($barX, $barY, $barW, $barH, 4, $this->progressColor);
            }
        }

        return $y + $showCount * $rowHeight + 4;
    }

    /**
     * 绘制运势区域
     */
    private function drawFortuneSection(int $x, int $y, int $w, array $fortune): int
    {
        // 区域标题
        $this->drawText('今日运势', $x, $y + 2, 20, $this->accentColor, true);
        $y += 36;

        // 运势评级 + 摸鱼指数
        $level = $fortune['fortune_level'] ?? '平';
        $emoji = $fortune['fortune_emoji'] ?? '😐';
        $this->drawText("运势: {$level} {$emoji}", $x, $y, 18, $this->titleColor, true);

        $moyuStars = $fortune['moyu_stars'] ?? '★★★☆☆';
        $this->drawText("摸鱼指数: {$moyuStars}", $x, $y + 30, 16, $this->textColor);

        // 分割线
        $lineY = $y + 58;
        imageline($this->image, $x, $lineY, $x + $w, $lineY, $this->lineColor);

        // 宜
        $yiY = $lineY + 16;
        $yiLabel = imagecolorallocate($this->image, 40, 167, 69); // 绿色
        $this->drawText('宜', $x, $yiY, 17, $yiLabel, true);
        $yiText = implode('、', $fortune['yi'] ?? []);
        $this->drawText($yiText, $x + 30, $yiY, 15, $this->textColor);

        // 忌
        $jiY = $yiY + 28;
        $jiLabel = imagecolorallocate($this->image, 220, 53, 69); // 红色
        $this->drawText('忌', $x, $jiY, 17, $jiLabel, true);
        $jiText = implode('、', $fortune['ji'] ?? []);
        $this->drawText($jiText, $x + 30, $jiY, 15, $this->textColor);

        // 幸运信息
        $luckyY = $jiY + 34;
        $luckyText = sprintf(
            '幸运数字: %s    幸运色: %s',
            $fortune['lucky_number'] ?? '--',
            $fortune['lucky_color'] ?? '--'
        );
        $this->drawText($luckyText, $x, $luckyY, 14, $this->textSecondaryColor);

        // 运势提示（可能较长，自动换行）
        $tipY = $luckyY + 28;
        $tip = $fortune['tip'] ?? '';
        if ($tip) {
            $this->drawWrappedText(
                '💡 ' . $tip,
                $x, $tipY, $w, 15, $this->textSecondaryColor, 22
            );
            $tipY += 28; // 至少一行高度
        }

        return $tipY + 8;
    }

    /**
     * 绘制页脚文字
     */
    private function drawFooter(int $y, string $text): void
    {
        if (empty($text)) {
            return;
        }
        // 居中绘制
        $this->drawTextCenter($text, $this->width / 2, $y, 14, imagecolorallocatealpha($this->image, 255, 255, 255, 40));
    }

    // ================================================================
    // 文字绘制辅助方法
    // ================================================================

    /**
     * 绘制左对齐文字
     */
    private function drawText(string $text, int $x, int $y, int $size, int $color, bool $bold = false): void
    {
        $text = $this->stripEmoji($text);
        if ($text === '') return;
        $fontSize = $bold ? $size : $size;
        // imagettftext 的 Y 是基线位置，需要加上字体大小
        $baseline = $y + $size + 4;
        @imagettftext($this->image, $fontSize, 0, $x, $baseline, $color, $this->fontPath, $text);
    }

    /**
     * 绘制右对齐文字
     */
    private function drawTextRight(string $text, int $rightX, int $y, int $size, int $color): void
    {
        $bbox = @imagettfbbox($size, 0, $this->fontPath, $text);
        if ($bbox) {
            $textWidth = $bbox[2] - $bbox[0];
            $this->drawText($text, $rightX - $textWidth, $y, $size, $color);
        }
    }

    /**
     * 绘制居中文字
     */
    private function drawTextCenter(string $text, int $centerX, int $y, int $size, int $color): void
    {
        $bbox = @imagettfbbox($size, 0, $this->fontPath, $text);
        if ($bbox) {
            $textWidth = $bbox[2] - $bbox[0];
            $this->drawText($text, (int)($centerX - $textWidth / 2), $y, $size, $color);
        }
    }

    /**
     * 绘制自动换行文字
     */
    private function drawWrappedText(string $text, int $x, int $y, int $maxWidth, int $size, int $color, int $lineHeight = 22): void
    {
        $text = $this->stripEmoji($text);
        if ($text === '') return;
        $chars = mb_str_split($text);
        $line = '';
        $currentY = $y;

        foreach ($chars as $char) {
            $testLine = $line . $char;
            $bbox = @imagettfbbox($size, 0, $this->fontPath, $testLine);
            $lineWidth = $bbox ? ($bbox[2] - $bbox[0]) : 0;

            if ($lineWidth > $maxWidth && $line !== '') {
                $this->drawText($line, $x, $currentY, $size, $color);
                $currentY += $lineHeight;
                $line = $char;
            } else {
                $line = $testLine;
            }
        }

        if ($line !== '') {
            $this->drawText($line, $x, $currentY, $size, $color);
        }
    }

    /**
     * 检测中文字体文件
     * @return string 可用的字体文件路径
     * @throws RuntimeException 当找不到任何可用中文字体时
     */
    private function detectFont(): string
    {
        // 1. 优先使用配置的字体路径
        if (!empty($this->fontPath) && file_exists($this->fontPath)) {
            return $this->fontPath;
        }

        // 2. 检查项目 fonts 目录
        $projectFont = __DIR__ . '/fonts/NotoSansSC-Regular.ttf';
        if (file_exists($projectFont)) {
            return $projectFont;
        }

        // 3. 检查常见系统中文字体路径
        $systemFonts = [
            // macOS
            '/System/Library/Fonts/PingFang.ttc',
            '/System/Library/Fonts/STHeiti Light.ttc',
            '/System/Library/Fonts/Supplemental/Arial Unicode.ttf',
            '/Library/Fonts/Arial Unicode.ttf',
            '/System/Library/Fonts/Hiragino Sans GB.ttc',
            // Linux (Ubuntu/Debian)
            '/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc',
            '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/truetype/droid/DroidSansFallbackFull.ttf',
            // Windows (WSL)
            '/mnt/c/Windows/Fonts/msyh.ttc',
            '/mnt/c/Windows/Fonts/simsun.ttc',
        ];

        foreach ($systemFonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }

        // 4. 找不到字体，给出提示
        throw new RuntimeException(
            "未找到中文字体文件！请按以下方式之一安装：\n" .
            "  1. 下载 Noto Sans SC 字体并放到 fonts/ 目录\n" .
            "     https://fonts.google.com/noto/specimen/Noto+Sans+SC\n" .
            "  2. macOS 通常自带 PingFang 字体，无需额外操作\n" .
            "  3. Linux: sudo apt install fonts-wqy-zenhei\n" .
            "  4. 在 config.php 中指定 image.font_path"
        );
    }

    // ================================================================
    // Emoji 处理方法（PHP GD 无法渲染彩色 Emoji）
    // ================================================================

    /**
     * 移除文本中的 Emoji 字符（PHP GD 的 TTF 渲染不支持彩色 Emoji）
     * @param string $text 含 Emoji 的文本
     * @return string 清除 Emoji 后的文本
     */
    private function stripEmoji(string $text): string
    {
        // 移除 Emoji 和其他非 BMP 符号字符（U+1F000 ~ U+1FFFF）
        $text = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $text);
        // 移除常见 Emoji 符号（保留 ★☆✦✧ 等排版用符号 U+2605~U+260C）
        $text = preg_replace('/[\x{2600}-\x{2604}\x{260D}-\x{27BF}]/u', '', $text);
        // 移除 Emoji 修饰符（肤色、变体选择器等）
        $text = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', $text);
        $text = preg_replace('/[\x{200D}]/u', '', $text); // ZWJ
        return trim($text);
    }

    /**
     * 根据节日名称绘制彩色圆点指示器（替代 Emoji）
     * @param string $holidayName 节日名称
     * @param int    $cx          圆心 X
     * @param int    $cy          圆心 Y
     * @param int    $radius      圆半径
     */
    private function drawHolidayDot(string $holidayName, int $cx, int $cy, int $radius = 6): void
    {
        $dotColors = [
            '元旦'   => [255, 215, 0],    // 金色
            '春节'   => [220, 20, 60],    // 中国红
            '元宵节' => [255, 140, 0],    // 灯笼橙
            '清明节' => [34, 139, 34],    // 森林绿
            '劳动节' => [65, 105, 225],   // 皇家蓝
            '端午节' => [0, 128, 128],    // 青色
            '七夕节' => [255, 105, 180],  // 热粉
            '中秋节' => [255, 165, 0],    // 橙色
            '重阳节' => [218, 165, 32],   // 金色菊
            '国庆节' => [220, 20, 60],    // 中国红
        ];
        $rgb = $dotColors[$holidayName] ?? [99, 132, 255];
        $color = imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledellipse($this->image, $cx, $cy, $radius * 2, $radius * 2, $color);
    }
}
