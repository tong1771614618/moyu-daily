<?php
/**
 * ============================================================
 * 摸鱼办 - 节假日倒计时计算器
 * ============================================================
 * 功能：根据配置的节日列表，计算距离今天最近的未来节日，
 *       返回倒计时天数并排序。
 *
 * 支持三种节日类型：
 *   - gregorian:  公历固定日期（如元旦 1月1日）
 *   - lunar:      农历日期，自动转换为当年公历（如春节正月初一）
 *   - solar_term: 节气类（如清明），使用近似公式计算
 *
 * 依赖：LunarCalendar.php（用于农历转公历）
 * ============================================================
 */

require_once __DIR__ . '/LunarCalendar.php';

class HolidayCalculator
{
    /** @var array 节日配置列表（来自 config.php） */
    private $holidays;

    /** @var DateTime 当前日期（上海时区） */
    private $today;

    /** @var int 当前公历年份 */
    private $currentYear;

    public function __construct(array $holidayConfig)
    {
        $this->holidays = $holidayConfig;
        $this->today = new DateTime('today', new DateTimeZone('Asia/Shanghai'));
        $this->currentYear = (int)$this->today->format('Y');
    }

    /**
     * 获取所有未来节日的倒计时（已排序）
     * @return array [['name','emoji','date','days','date_str'], ...]
     */
    public function getCountdowns()
    {
        $results = [];

        foreach ($this->holidays as $holiday) {
            $info = $this->resolveHolidayDate($holiday);
            if ($info === null) {
                continue;
            }

            $holidayDate = new DateTime($info['date'], new DateTimeZone('Asia/Shanghai'));
            $interval = $this->today->diff($holidayDate);
            $days = (int)$interval->format('%r%a');

            // 只保留今天及未来的节日（包含今天）
            if ($days < 0) {
                // 如果今年的已过，尝试计算明年的
                $nextYearInfo = $this->resolveHolidayDate($holiday, $this->currentYear + 1);
                if ($nextYearInfo) {
                    $nextDate = new DateTime($nextYearInfo['date'], new DateTimeZone('Asia/Shanghai'));
                    $interval = $this->today->diff($nextDate);
                    $days = (int)$interval->format('%r%a');
                    $info = $nextYearInfo;
                    $holidayDate = $nextDate;
                } else {
                    continue;
                }
            }

            $results[] = [
                'name'     => $holiday['name'],
                'emoji'    => $holiday['emoji'] ?? '📅',
                'date'     => $holidayDate,
                'days'     => $days,
                'date_str' => $holidayDate->format('Y-m-d'),
            ];
        }

        // 按倒计时天数升序排序
        usort($results, function ($a, $b) {
            return $a['days'] - $b['days'];
        });

        // 只保留春节之前的节日（含春节当天）
        $filtered = [];
        foreach ($results as $result) {
            $filtered[] = $result;
            if ($result['name'] === '春节') {
                break;
            }
        }

        return $filtered;
    }

    /**
     * 解析单个节日的公历日期
     * @param array   $holiday 节日配置
     * @param int|null $year   指定年份（默认当前年）
     * @return array|null ['date' => 'YYYY-MM-DD'] 或 null
     */
    private function resolveHolidayDate(array $holiday, $year = null)
    {
        $year = $year ?: $this->currentYear;

        switch ($holiday['type']) {
            case 'gregorian':
                // 公历固定日期
                return $this->resolveGregorian($holiday, $year);

            case 'lunar':
                // 农历日期 → 转换为公历
                return $this->resolveLunar($holiday, $year);

            case 'solar_term':
                // 节气（清明节等）
                return $this->resolveSolarTerm($holiday, $year);

            default:
                return null;
        }
    }

    /**
     * 解析公历固定日期
     */
    private function resolveGregorian(array $holiday, int $year): array
    {
        $month = str_pad($holiday['month'], 2, '0', STR_PAD_LEFT);
        $day = str_pad($holiday['day'], 2, '0', STR_PAD_LEFT);
        return ['date' => "{$year}-{$month}-{$day}"];
    }

    /**
     * 解析农历日期 → 转换为公历
     */
    private function resolveLunar(array $holiday, int $year): ?array
    {
        try {
            $solar = LunarCalendar::lunarToSolar(
                $year,
                $holiday['lunar_month'],
                $holiday['lunar_day']
            );
            $month = str_pad($solar['month'], 2, '0', STR_PAD_LEFT);
            $day = str_pad($solar['day'], 2, '0', STR_PAD_LEFT);
            return ['date' => "{$solar['year']}-{$month}-{$day}"];
        } catch (\Exception $e) {
            error_log("农历转换失败 [{$holiday['name']}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 解析节气日期（清明节近似公式）
     *
     * 清明节日期近似计算公式（适用于2000~2099年）：
     *   日期 = floor(Y * D + C) - L
     *   Y = 年份后两位, D = 0.2422, C = 4.81（20世纪）或 5.52（21世纪）
     *   L = 闰年修正值 = floor(Y / 4)
     */
    private function resolveSolarTerm(array $holiday, int $year): ?array
    {
        // 目前只处理清明节
        if ($holiday['name'] === '清明节') {
            $y = $year % 100; // 年份后两位
            $d = 0.2422;
            // 21世纪清明C值
            $c = ($year >= 2000) ? 4.81 : 5.52;
            $l = (int)($y / 4);

            $day = (int)floor($y * $d + $c) - $l;

            // 特殊年份修正（已知偏差）
            $corrections = [
                2024 => 4, 2025 => 4, 2026 => 5, 2027 => 5,
                2028 => 4, 2029 => 4, 2030 => 5,
            ];
            if (isset($corrections[$year])) {
                $day = $corrections[$year];
            }

            return ['date' => "{$year}-04-" . str_pad($day, 2, '0', STR_PAD_LEFT)];
        }

        return null;
    }

    /**
     * 获取格式化后的倒计时文字描述
     * @param array $countdown 倒计时数据
     * @return string 如 "还有30天" 或 "今天！🎉"
     */
    public static function formatCountdown(array $countdown): string
    {
        if ($countdown['days'] == 0) {
            return '今天！🎉';
        }
        return "还有{$countdown['days']}天";
    }
}
