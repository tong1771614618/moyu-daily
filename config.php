<?php
/**
 * ============================================================
 * 摸鱼办 - 全局配置文件
 * ============================================================
 * 在此文件中配置天气API、邮件服务、城市信息等参数。
 * 所有模块均从此文件读取配置，修改后全局生效。
 */

return [

    // ==================== 城市与位置配置 ====================
    'city' => [
        'name'      => '青岛',           // 显示名称（中文）
        'latitude'  => 36.0671,          // 纬度
        'longitude' => 120.3826,         // 经度
        'timezone'  => 'Asia/Shanghai',  // 时区
    ],

    // ==================== 天气 API 配置 ====================
    // 使用 OpenWeatherMap 免费API
    // 注册地址: https://openweathermap.org/api （注册后获取免费 API Key）
    'weather' => [
        'api_key' => getenv('MOYU_WEATHER_API_KEY') ?: '70268a1c67db315a80ffbb61430f9ede',
        'units'   => 'metric',  // metric=摄氏度, imperial=华氏度
        'lang'    => 'zh_cn',   // 返回中文天气描述
    ],

    // ==================== 节假日配置 ====================
    // 公历固定节日 + 农历节日（系统自动转换公历日期）
    // 可在此处增减节日，格式保持一致即可
    'holidays' => [
        // --- 公历固定节日 ---
        ['name' => '元旦',   'month' => 1,  'day' => 1,  'type' => 'gregorian', 'emoji' => '🎆'],
        ['name' => '劳动节', 'month' => 5,  'day' => 1,  'type' => 'gregorian', 'emoji' => '💪'],
        ['name' => '国庆节', 'month' => 10, 'day' => 1,  'type' => 'gregorian', 'emoji' => '🇨🇳'],

        // --- 农历法定假日（自动转换为当年公历日期）---
        ['name' => '春节',     'lunar_month' => 1,  'lunar_day' => 1,  'type' => 'lunar', 'emoji' => '🧧'],
        ['name' => '清明节',   'lunar_month' => 0,  'lunar_day' => 0,  'type' => 'solar_term', 'emoji' => '🌿'],
        ['name' => '端午节',   'lunar_month' => 5,  'lunar_day' => 5,  'type' => 'lunar', 'emoji' => '🐉'],
        ['name' => '中秋节',   'lunar_month' => 8,  'lunar_day' => 15, 'type' => 'lunar', 'emoji' => '🥮'],
    ],

    // ==================== 邮件配置 ====================
    // 使用 PHPMailer 通过 SMTP 发送邮件
    // 需要先通过 composer require phpmailer/phpmailer 安装
    'email' => [
        'enabled'    => false,                                      // 是否启用邮件发送
        'smtp_host'  => getenv('MOYU_SMTP_HOST') ?: 'smtp.qq.com',
        'smtp_port'  => 465,                                        // SSL端口465, TLS端口587
        'smtp_secure'=> 'ssl',                                      // ssl 或 tls
        'username'   => getenv('MOYU_SMTP_USER') ?: '',             // 发件人邮箱
        'password'   => getenv('MOYU_SMTP_PASS') ?: '',             // SMTP授权码（非登录密码）
        'from_name'  => '摸鱼办',                                    // 发件人名称
        'to'         => [],                                         // 收件人列表 ['a@x.com', 'b@x.com']
    ],

    // ==================== 图片生成配置 ====================
    'image' => [
        'width'  => 800,
        'height' => 1200,
        'output_dir'  => __DIR__ . '/output',
        'output_name' => 'moyu_' . date('Ymd') . '.png',
        'font_path'   => __DIR__ . '/fonts/NotoSansSC-Regular.ttf',
    ],

    // ==================== 界面文字配置 ====================
    'text' => [
        'title'    => '摸 鱼 办',
        'subtitle' => '今日摸鱼指南',
        'footer'   => '工作再忙，也要适当摸鱼哦 ~',
    ],

];
