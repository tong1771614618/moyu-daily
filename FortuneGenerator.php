<?php
/**
 * ============================================================
 * 摸鱼办 - 今日运势生成模块
 * ============================================================
 * 功能：根据当天的农历日期作为随机种子，生成当天固定的
 *       "摸鱼运势"数据。同一天多次调用结果一致。
 *
 * 运势内容包含：
 *   - 综合运势评级（大吉/吉/小吉/平/凶）
 *   - 摸鱼指数（1~5星）
 *   - 宜做的事情列表
 *   - 忌做的事情列表
 *   - 幸运数字
 *   - 幸运颜色
 *   - 一句话运势提示
 *
 * 数据来源：基于农历日期的伪随机算法（娱乐性质）
 * ============================================================
 */

class FortuneGenerator
{
    /** @var int 随机种子（基于农历日期） */
    private $seed;

    /** @var array 宜做事项库 */
    private static $yiList = [
        '带薪上厕所', '摸鱼看新闻', '偷偷刷手机', '茶水间闲聊',
        '假装开会', '网购比价', '整理桌面', '写工作日志',
        '学习新技能', '午饭多吃点', '早退五分钟', '准时下班',
        '和同事吐槽', '看股票行情', '研究午饭吃什么', '做下周计划',
        '清理邮箱', '看看窗外发呆', '喝杯咖啡', '听首好歌',
        '刷个短视频', '看看天气预报', '整理电脑文件', '摸鱼写笔记',
    ];

    /** @var array 忌做事项库 */
    private static $jiList = [
        '主动加班', '和领导对视', '第一个到公司', '接紧急任务',
        '开会坐前排', '回复老板消息', '提出新方案', '自告奋勇',
        '答应别人帮忙', '最后一个走', '打开工作群', '写周报',
        '做PPT', '整理会议纪要', '主动揽活', '回复工作邮件',
    ];

    /** @var array 运势评级 */
    private static $fortuneLevels = [
        ['level' => '大吉', 'emoji' => '🎊', 'weight' => 10],
        ['level' => '吉',   'emoji' => '✨', 'weight' => 25],
        ['level' => '小吉', 'emoji' => '👍', 'weight' => 30],
        ['level' => '平',   'emoji' => '😐', 'weight' => 25],
        ['level' => '凶',   'emoji' => '😱', 'weight' => 10],
    ];

    /** @var array 幸运颜色库 */
    private static $luckyColors = [
        '中国红', '天空蓝', '薄荷绿', '柠檬黄', '薰衣草紫',
        '珊瑚橙', '玫瑰粉', '深海蓝', '抹茶绿', '奶茶色',
        '烟灰色', '樱花粉', '枫叶红', '鹅黄色', '藏青色',
    ];

    /** @var array 运势提示语库 */
    private static $fortuneTips = [
        '今日适合低调摸鱼，切忌高调炫耀',
        '老板今天心情不错，可以放心摸鱼',
        '注意身后，摸鱼时记得切屏',
        '今天是个好日子，适合准时下班',
        '摸鱼有风险，加班需谨慎',
        '今日宜：假装很忙，实则放空',
        '天灵灵地灵灵，今天摸鱼不会停',
        '保持微笑，假装在思考工作',
        '今天的你，是办公室最靓的仔',
        '摸鱼虽好，可不要贪杯哦',
        '今日运势提示：多喝水，多走动',
        '适合在茶水间多待一会儿',
        '午饭时间建议延长30分钟',
        '今日适合整理桌面（假装很忙）',
        '摸鱼一时爽，一直摸鱼一直爽',
        '今天的你距离下一个假期又近了一天',
        '建议打开两个窗口：一个工作，一个摸鱼',
        '今日最佳摸鱼时段：下午2-4点',
        '今天的咖啡特别好喝（不信你试试）',
        '宜：带薪如厕，忌：主动汇报',
    ];

    /**
     * @param array $lunarDate 农历日期数组（来自 LunarCalendar）
     */
    public function __construct(array $lunarDate)
    {
        // 使用农历日期生成固定种子，保证同一天结果一致
        $this->seed = crc32(
            $lunarDate['year'] . '-' .
            $lunarDate['month'] . '-' .
            $lunarDate['day'] . '-' .
            ($lunarDate['is_leap'] ? '1' : '0')
        );
    }

    /**
     * 生成今日运势
     * @return array 运势数据
     */
    public function generate(): array
    {
        // 重置随机种子，保证同一天结果固定
        mt_srand($this->seed);

        // 运势评级（加权随机）
        $fortune = $this->weightedRandom(self::$fortuneLevels);

        // 随机选取宜忌事项
        $yi = $this->randomPick(self::$yiList, 3);
        $ji = $this->randomPick(self::$jiList, 2);

        // 摸鱼指数（1~5星）
        $moyuIndex = mt_rand(1, 5);

        // 幸运数字（1~99）
        $luckyNumber = mt_rand(1, 99);

        // 幸运颜色
        $luckyColor = self::$luckyColors[mt_rand(0, count(self::$luckyColors) - 1)];

        // 运势提示
        $tip = self::$fortuneTips[mt_rand(0, count(self::$fortuneTips) - 1)];

        // 恢复系统随机种子
        mt_srand();

        return [
            'fortune_level' => $fortune['level'],
            'fortune_emoji' => $fortune['emoji'],
            'moyu_index'    => $moyuIndex,
            'moyu_stars'    => str_repeat('★', $moyuIndex) . str_repeat('☆', 5 - $moyuIndex),
            'yi'            => $yi,
            'ji'            => $ji,
            'lucky_number'  => $luckyNumber,
            'lucky_color'   => $luckyColor,
            'tip'           => $tip,
        ];
    }

    /**
     * 加权随机选择
     * @param array $items 带 weight 的选项数组
     * @return array 选中的选项
     */
    private function weightedRandom(array $items): array
    {
        $totalWeight = array_sum(array_column($items, 'weight'));
        $rand = mt_rand(1, $totalWeight);
        $cumulative = 0;

        foreach ($items as $item) {
            $cumulative += $item['weight'];
            if ($rand <= $cumulative) {
                return $item;
            }
        }

        return $items[0];
    }

    /**
     * 从数组中随机选取 N 个不重复元素
     * @param array $list 源数组
     * @param int   $count 选取数量
     * @return array
     */
    private function randomPick(array $list, int $count): array
    {
        $keys = array_rand($list, min($count, count($list)));
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        return array_map(function ($key) use ($list) {
            return $list[$key];
        }, $keys);
    }
}
