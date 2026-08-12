<?php
/**
 * ============================================================
 * 摸鱼办 - 农历日期转换模块
 * ============================================================
 * 功能：公历 ↔ 农历互转，生肖、天干地支计算
 *
 * 原理：使用农历数据编码表（1900~2100年），每年用一个
 *       十六进制数编码该年各月大小月和闰月信息。
 *
 * 编码格式（20位）：
 *   [20-23位] 闰月月份（0=无闰月）
 *   [4-15位]  1~12月大小月（1=30天大月，0=29天小月）
 *   [0-3位]   闰月大小（0=29天，1=30天）
 *
 * 数据来源：jjonline/calendar.js 开源项目（经广泛验证）
 * ============================================================
 */

class LunarCalendar
{
    /**
     * 农历数据编码表 (1900 ~ 2100)
     * 每个元素对应一个农历年份的编码信息
     */
    private static $lunarInfo = [
        /* 1900 */ 0x04bd8, 0x04ae0, 0x0a570, 0x054d5, 0x0d260, 0x0d950, 0x16554, 0x056a0, 0x09ad0, 0x055d2,
        /* 1910 */ 0x04ae0, 0x0a5b6, 0x0a4d0, 0x0d250, 0x1d255, 0x0b540, 0x0d6a0, 0x0ada2, 0x095b0, 0x14977,
        /* 1920 */ 0x04970, 0x0a4b0, 0x0b4b5, 0x06a50, 0x06d40, 0x1ab54, 0x02b60, 0x09570, 0x052f2, 0x04970,
        /* 1930 */ 0x06566, 0x0d4a0, 0x0ea50, 0x16a95, 0x05ad0, 0x02b60, 0x186e3, 0x092e0, 0x1c8d7, 0x0c950,
        /* 1940 */ 0x0d4a0, 0x1d8a6, 0x0b550, 0x056a0, 0x1a5b4, 0x025d0, 0x092d0, 0x0d2b2, 0x0a950, 0x0b557,
        /* 1950 */ 0x06ca0, 0x0b550, 0x15355, 0x04da0, 0x0a5b0, 0x14573, 0x052b0, 0x0a9a8, 0x0e950, 0x06aa0,
        /* 1960 */ 0x0aea6, 0x0ab50, 0x04b60, 0x0aae4, 0x0a570, 0x05260, 0x0f263, 0x0d950, 0x05b57, 0x056a0,
        /* 1970 */ 0x096d0, 0x04dd5, 0x04ad0, 0x0a4d0, 0x0d4d4, 0x0d250, 0x0d558, 0x0b540, 0x0b6a0, 0x195a6,
        /* 1980 */ 0x095b0, 0x049b0, 0x0a974, 0x0a4b0, 0x0b27a, 0x06a50, 0x06d40, 0x0af46, 0x0ab60, 0x09570,
        /* 1990 */ 0x04af5, 0x04970, 0x064b0, 0x074a3, 0x0ea50, 0x06b58, 0x05ac0, 0x0ab60, 0x096d5, 0x092e0,
        /* 2000 */ 0x0c960, 0x0d954, 0x0d4a0, 0x0da50, 0x07552, 0x056a0, 0x0abb7, 0x025d0, 0x092d0, 0x0cab5,
        /* 2010 */ 0x0a950, 0x0b4a0, 0x0baa4, 0x0ad50, 0x055d9, 0x04ba0, 0x0a5b0, 0x15176, 0x052b0, 0x0a930,
        /* 2020 */ 0x07954, 0x06aa0, 0x0ad50, 0x05b52, 0x04b60, 0x16956, 0x05550, 0x0d260, 0x0ea65, 0x0d530,
        /* 2030 */ 0x05aa0, 0x076a3, 0x096d0, 0x04afb, 0x04ad0, 0x0a4d0, 0x1d0b6, 0x0d250, 0x0d520, 0x0dd45,
        /* 2040 */ 0x0b5a0, 0x056d0, 0x055b2, 0x049b0, 0x0a577, 0x0a4b0, 0x0aa50, 0x1b255, 0x06d20, 0x0ada0,
        /* 2050 */ 0x14b63, 0x09370, 0x049f8, 0x04970, 0x064b0, 0x168a6, 0x0ea50, 0x06aa0, 0x1a6c4, 0x0aae0,
        /* 2060 */ 0x092e0, 0x0d2e3, 0x0c960, 0x0d557, 0x0d4a0, 0x0da50, 0x05d55, 0x056a0, 0x0a6d0, 0x055d4,
        /* 2070 */ 0x052d0, 0x0a9b8, 0x0a950, 0x0b4a0, 0x0b6a6, 0x0ad50, 0x055a0, 0x0aba4, 0x0a5b0, 0x052b0,
        /* 2080 */ 0x0b273, 0x06930, 0x07337, 0x06aa0, 0x0ad50, 0x14b55, 0x04b60, 0x0a570, 0x054e4, 0x0d160,
        /* 2090 */ 0x0e968, 0x0d520, 0x0daa0, 0x16aa6, 0x056d0, 0x04ae0, 0x0a9d4, 0x0a4d0, 0x0d150, 0x0f252,
        /* 2100 */ 0x0d520,
    ];

    // 天干
    private static $tianGan = ['甲', '乙', '丙', '丁', '戊', '己', '庚', '辛', '壬', '癸'];

    // 地支
    private static $diZhi = ['子', '丑', '寅', '卯', '辰', '巳', '午', '未', '申', '酉', '戌', '亥'];

    // 生肖
    private static $shengXiao = ['鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪'];

    // 农历月份名称
    private static $lunarMonthNames = [
        1 => '正月', 2 => '二月', 3 => '三月', 4 => '四月',
        5 => '五月', 6 => '六月', 7 => '七月', 8 => '八月',
        9 => '九月', 10 => '十月', 11 => '冬月', 12 => '腊月',
    ];

    // 农历日期名称
    private static $lunarDayNames = [
        1  => '初一',  2  => '初二',  3  => '初三',  4  => '初四',  5  => '初五',
        6  => '初六',  7  => '初七',  8  => '初八',  9  => '初九',  10 => '初十',
        11 => '十一', 12 => '十二', 13 => '十三', 14 => '十四', 15 => '十五',
        16 => '十六', 17 => '十七', 18 => '十八', 19 => '十九', 20 => '二十',
        21 => '廿一', 22 => '廿二', 23 => '廿三', 24 => '廿四', 25 => '廿五',
        26 => '廿六', 27 => '廿七', 28 => '廿八', 29 => '廿九', 30 => '三十',
    ];

    // 基准日期：1900年1月31日 = 农历1900年正月初一
    private static $baseYear = 1900;
    private static $baseDate; // 在构造时初始化

    /**
     * 初始化基准日期
     */
    public static function init()
    {
        if (self::$baseDate === null) {
            self::$baseDate = mktime(0, 0, 0, 1, 31, 1900);
        }
    }

    /**
     * 获取某农历年的总天数
     * @param int $year 农历年份
     * @return int 该年总天数
     */
    public static function lunarYearDays($year)
    {
        $sum = 348; // 12个月 × 29天 = 348
        $info = self::$lunarInfo[$year - self::$baseYear];

        // 逐位检查每个月是大月(30)还是小月(29)
        for ($i = 0x8000; $i > 0x8; $i >>= 1) {
            $sum += ($info & $i) ? 1 : 0;
        }

        // 如果有闰月，加上闰月天数
        $sum += self::leapDays($year);

        return $sum;
    }

    /**
     * 获取某农历年闰月的天数
     * @param int $year 农历年份
     * @return int 闰月天数（0=无闰月）
     */
    public static function leapDays($year)
    {
        if (self::leapMonth($year)) {
            $info = self::$lunarInfo[$year - self::$baseYear];
            return ($info & 0x10000) ? 30 : 29;
        }
        return 0;
    }

    /**
     * 获取某农历年闰月的月份
     * @param int $year 农历年份
     * @return int 闰月月份（0=无闰月）
     */
    public static function leapMonth($year)
    {
        $info = self::$lunarInfo[$year - self::$baseYear];
        return $info & 0xf;
    }

    /**
     * 获取某农历年某月的天数
     * @param int $year  农历年份
     * @param int $month 月份（1~12）
     * @return int 该月天数
     */
    public static function monthDays($year, $month)
    {
        $info = self::$lunarInfo[$year - self::$baseYear];
        return ($info & (0x10000 >> $month)) ? 30 : 29;
    }

    /**
     * 公历转农历
     * @param int $year  公历年
     * @param int $month 公历月
     * @param int $day   公历日
     * @return array ['year','month','day','is_leap','month_name','day_name','zodiac','gan_zhi']
     */
    public static function solarToLunar($year, $month, $day)
    {
        self::init();

        // 计算目标日期与基准日期的天数差
        $targetDate = mktime(0, 0, 0, $month, $day, $year);
        $offset = (int)(($targetDate - self::$baseDate) / 86400);

        // 计算农历年份
        $lunarYear = self::$baseYear;
        $daysInYear = 0;
        for ($i = self::$baseYear; $i < 2101 && $offset > 0; $i++) {
            $daysInYear = self::lunarYearDays($i);
            $offset -= $daysInYear;
            $lunarYear++;
        }
        if ($offset < 0) {
            $offset += $daysInYear;
            $lunarYear--;
        }

        // 计算闰月信息
        $leap = self::leapMonth($lunarYear);
        $isLeap = false;

        // 计算农历月份和日期
        $lunarMonth = 0;
        $lunarDay = 0;

        for ($i = 1; $i <= 12; $i++) {
            // 当前月天数
            $daysInMonth = self::monthDays($lunarYear, $i);

            // 先清除上一轮迭代的闰月标记（必须在进入闰月检查之前执行）
            if ($isLeap && $i == ($leap + 1)) {
                $isLeap = false;
            }

            // 再检查是否进入闰月（当 i 到达闰月+1 且尚未处理闰月时，回退 i 处理闰月）
            if ($leap > 0 && $i == ($leap + 1) && !$isLeap) {
                --$i;
                $isLeap = true;
                $daysInMonth = self::leapDays($lunarYear);
            }

            if ($offset < $daysInMonth) {
                $lunarMonth = $i;
                $lunarDay = $offset + 1;
                break;
            }
            $offset -= $daysInMonth;
        }

        // 如果循环结束还没找到，说明是最后一天
        if ($lunarMonth == 0) {
            $lunarMonth = 12;
            $lunarDay = $offset + 1;
        }

        // 构建月份名称（含闰月标记）
        $isActuallyLeap = ($leap > 0 && $lunarMonth == $leap && $isLeap);
        $monthName = ($isActuallyLeap ? '闰' : '') . self::$lunarMonthNames[$lunarMonth];
        $dayName = self::$lunarDayNames[$lunarDay] ?? (string)$lunarDay;

        return [
            'year'       => $lunarYear,
            'month'      => $lunarMonth,
            'day'        => $lunarDay,
            'is_leap'    => $isActuallyLeap,
            'month_name' => $monthName,
            'day_name'   => $dayName,
            'zodiac'     => self::getZodiac($lunarYear),
            'gan_zhi'    => self::getGanZhi($lunarYear),
            'full_name'  => self::getGanZhi($lunarYear) . '年（' . self::getZodiac($lunarYear) . '年）',
        ];
    }

    /**
     * 农历转公历
     * @param int  $year    农历年
     * @param int  $month   农历月
     * @param int  $day     农历日
     * @param bool $isLeap  是否闰月
     * @return array ['year','month','day'] 公历日期
     */
    public static function lunarToSolar($year, $month, $day, $isLeap = false)
    {
        self::init();

        // 累计从基准年到目标年的总天数
        $offset = 0;
        for ($i = self::$baseYear; $i < $year; $i++) {
            $offset += self::lunarYearDays($i);
        }

        // 累计目标年内到目标月之前的天数
        $leap = self::leapMonth($year);
        for ($i = 1; $i < $month; $i++) {
            $offset += self::monthDays($year, $i);
            // 如果闰月在这个月之前，加上闰月天数
            if ($i == $leap) {
                $offset += self::leapDays($year);
            }
        }

        // 如果目标月就是闰月且标记为闰月，还要加上正常月天数
        if ($isLeap && $month == $leap) {
            $offset += self::monthDays($year, $month);
        }

        // 加上日期偏移
        $offset += $day - 1;

        // 从基准日期推算公历
        $targetDate = self::$baseDate + $offset * 86400;

        return [
            'year'  => (int)date('Y', $targetDate),
            'month' => (int)date('n', $targetDate),
            'day'   => (int)date('j', $targetDate),
        ];
    }

    /**
     * 获取今天的农历信息
     * @return array 农历信息数组
     */
    public static function getToday()
    {
        $now = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
        return self::solarToLunar(
            (int)$now->format('Y'),
            (int)$now->format('n'),
            (int)$now->format('j')
        );
    }

    /**
     * 获取生肖
     * @param int $lunarYear 农历年份
     * @return string 生肖名称
     */
    public static function getZodiac($lunarYear)
    {
        return self::$shengXiao[($lunarYear - 4) % 12];
    }

    /**
     * 获取天干地支纪年
     * @param int $lunarYear 农历年份
     * @return string 如 "丙午"
     */
    public static function getGanZhi($lunarYear)
    {
        $ganIndex = ($lunarYear - 4) % 10;
        $zhiIndex = ($lunarYear - 4) % 12;
        return self::$tianGan[$ganIndex] . self::$diZhi[$zhiIndex];
    }

    /**
     * 获取农历月份的显示名称
     * @param int $month 月份
     * @return string
     */
    public static function getMonthName($month)
    {
        return self::$lunarMonthNames[$month] ?? (string)$month . '月';
    }

    /**
     * 获取农历日期的显示名称
     * @param int $day 日期
     * @return string
     */
    public static function getDayName($day)
    {
        return self::$lunarDayNames[$day] ?? (string)$day;
    }
}
