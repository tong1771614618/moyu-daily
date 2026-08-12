<?php
/**
 * ============================================================
 * 摸鱼办 - 文字版生成模块
 * ============================================================
 * 功能：将摸鱼日报数据格式化为适合微信转发的纯文本消息。
 *
 * 格式特点：
 *   - 使用 Emoji 作为视觉标记（微信可正常显示）
 *   - 用分隔线和空行区分各板块
 *   - 换行使用 \n，兼容微信聊天窗口
 *   - 不包含 Markdown 语法，纯文本直接粘贴
 *
 * 依赖：无
 * ============================================================
 */

class TextGenerator
{
    /** @var string 换行符 */
    private $nl = "\n";

    /**
     * 生成微信转发文本
     *
     * @param array $data 汇总数据，结构同 ImageGenerator：
     *   - date_info:  ['gregorian','weekday','month_name','day_name','gan_zhi','zodiac']
     *   - weather:    天气数据数组
     *   - holidays:   节假日倒计时数组
     *   - fortune:    运势数据数组
     * @return string 格式化后的纯文本
     */
    public function generate(array $data): string
    {
        $lines = [];

        // ==================== 标题 ====================
        $lines[] = '🐟 摸鱼办 · 今日摸鱼指南';
        $lines[] = '';

        // ==================== 日期 ====================
        $lines[] = $this->buildDateLine($data['date_info']);
        $lines[] = '';

        // ==================== 天气 ====================
        $lines[] = $this->buildWeatherBlock($data['weather']);
        $lines[] = '';

        // ==================== 假期倒计时 ====================
        $lines[] = $this->buildHolidayBlock($data['holidays']);
        $lines[] = '';

        // ==================== 今日运势 ====================
        $lines[] = $this->buildFortuneBlock($data['fortune']);
        $lines[] = '';

        // ==================== 页脚 ====================
        $lines[] = '—— 工作再忙，也要适当摸鱼~ ——';

        return implode($this->nl, $lines);
    }

    // ================================================================
    // 各板块构建方法
    // ================================================================

    /**
     * 构建日期行
     */
    private function buildDateLine(array $d): string
    {
        return sprintf(
            '📅 %s%s%s  %s年 · %s年',
            $d['gregorian'] ?? date('Y年m月d日'),
            $this->nl,
            '农历 ' . ($d['month_name'] ?? '') . ($d['day_name'] ?? ''),
            $d['gan_zhi'] ?? '',
            $d['zodiac'] ?? ''
        );
    }

    /**
     * 构建天气板块
     */
    private function buildWeatherBlock(array $w): string
    {
        $lines = [];
        $lines[] = '🌤 天气';

        if (($w['temp'] ?? '--') !== '--') {
            $lines[] = sprintf(
                '   %s %s°C  %s',
                $w['icon'] ?? '',
                $w['temp'],
                $w['description'] ?? ''
            );

            // 详细信息拼接
            $details = [];
            if (isset($w['feels_like']) && $w['feels_like'] !== '--') {
                $details[] = "体感 {$w['feels_like']}°C";
            }
            if (isset($w['temp_min']) && $w['temp_min'] !== '--') {
                $details[] = "{$w['temp_min']}° ~ {$w['temp_max']}°";
            }
            if (isset($w['humidity']) && $w['humidity'] !== '--') {
                $details[] = "湿度 {$w['humidity']}%";
            }
            if (isset($w['wind_speed']) && $w['wind_speed'] !== '--') {
                $details[] = "风速 {$w['wind_speed']}m/s";
            }
            if (!empty($details)) {
                $lines[] = '   ' . implode(' | ', $details);
            }
        } else {
            $lines[] = '   数据获取中...';
        }

        return implode($this->nl, $lines);
    }

    /**
     * 构建假期倒计时板块
     */
    private function buildHolidayBlock(array $holidays): string
    {
        $lines = [];
        $lines[] = '🎉 假期倒计时';

        foreach ($holidays as $h) {
            $emoji = $h['emoji'] ?? '📅';
            $name  = $h['name'];
            // 从 date_str (YYYY-MM-DD) 提取 M月D日
            $dateObj = new \DateTime($h['date_str']);
            $dateLabel = (int)$dateObj->format('n') . '月' . (int)$dateObj->format('j') . '日';
            $daysText  = ($h['days'] == 0) ? '今天！' : $h['days'] . '天';

            $lines[] = sprintf('   %s %s(%s)  %s', $emoji, $name, $dateLabel, $daysText);
        }

        return implode($this->nl, $lines);
    }

    /**
     * 构建运势板块
     */
    private function buildFortuneBlock(array $f): string
    {
        $lines = [];
        $lines[] = '🔮 今日运势';

        $level = $f['fortune_level'] ?? '平';
        $emoji = $f['fortune_emoji'] ?? '';
        $stars = $f['moyu_stars'] ?? '';
        $lines[] = "   运势: {$level} {$emoji}";
        $lines[] = "   摸鱼指数: {$stars}";

        // 宜
        $yi = $f['yi'] ?? [];
        if (!empty($yi)) {
            $lines[] = '   ✅ 宜: ' . implode('、', $yi);
        }

        // 忌
        $ji = $f['ji'] ?? [];
        if (!empty($ji)) {
            $lines[] = '   ❌ 忌: ' . implode('、', $ji);
        }

        // 幸运信息
        $luckyNum   = $f['lucky_number'] ?? '--';
        $luckyColor = $f['lucky_color'] ?? '--';
        $lines[] = "   🍀 幸运数字: {$luckyNum}  幸运色: {$luckyColor}";

        // 提示语
        $tip = $f['tip'] ?? '';
        if ($tip) {
            $lines[] = "   💡 {$tip}";
        }

        return implode($this->nl, $lines);
    }
}
