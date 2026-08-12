<?php
/**
 * ============================================================
 * 摸鱼办 - 天气数据获取模块
 * ============================================================
 * 功能：通过 OpenWeatherMap API 获取指定城市的实时天气数据。
 *
 * 返回数据包括：温度、体感温度、天气描述、湿度、风速、
 *              最高/最低温度等。
 *
 * API来源：OpenWeatherMap (https://openweathermap.org)
 *   - 免费版每分钟60次调用，完全满足日常需求
 *   - 需要注册获取免费 API Key
 *
 * 依赖：无（仅使用 PHP curl 扩展）
 * ============================================================
 */

class WeatherFetcher
{
    /** @var string API Key */
    private $apiKey;

    /** @var float 纬度 */
    private $latitude;

    /** @var float 经度 */
    private $longitude;

    /** @var string 城市名称 */
    private $cityName;

    /** @var string 温度单位 */
    private $units;

    /** @var string 返回语言 */
    private $lang;

    /**
     * @param array $config 天气配置（来自 config.php 的 weather + city 节）
     */
    public function __construct(array $config, array $cityConfig)
    {
        $this->apiKey    = $config['api_key'];
        $this->latitude  = $cityConfig['latitude'];
        $this->longitude = $cityConfig['longitude'];
        $this->cityName  = $cityConfig['name'];
        $this->units     = $config['units'] ?? 'metric';
        $this->lang      = $config['lang'] ?? 'zh_cn';
    }

    /**
     * 获取当前天气数据
     * @return array|null 天气数据数组，失败返回 null
     */
    public function fetch(): ?array
    {
        $url = sprintf(
            'https://api.openweathermap.org/data/2.5/weather?lat=%s&lon=%s&appid=%s&units=%s&lang=%s',
            $this->latitude,
            $this->longitude,
            $this->apiKey,
            $this->units,
            $this->lang
        );

        $response = $this->httpGet($url);
        if ($response === null) {
            return $this->getFallbackData();
        }

        $data = json_decode($response, true);
        if (!isset($data['main'])) {
            error_log('天气API返回数据异常: ' . $response);
            return $this->getFallbackData();
        }

        return [
            'city'         => $this->cityName,
            'temp'         => round($data['main']['temp']),
            'feels_like'   => round($data['main']['feels_like']),
            'temp_min'     => round($data['main']['temp_min']),
            'temp_max'     => round($data['main']['temp_max']),
            'humidity'     => $data['main']['humidity'],
            'description'  => $data['weather'][0]['description'] ?? '未知',
            'icon'         => $this->getWeatherEmoji($data['weather'][0]['main'] ?? ''),
            'wind_speed'   => round($data['wind']['speed'] ?? 0, 1),
            'pressure'     => $data['main']['pressure'] ?? 0,
            'visibility'   => isset($data['visibility']) ? round($data['visibility'] / 1000, 1) : null,
            'raw'          => $data,
        ];
    }

    /**
     * 将天气状况映射为 Emoji
     * @param string $main 天气状况英文标识
     * @return string Emoji
     */
    private function getWeatherEmoji(string $main): string
    {
        $emojiMap = [
            'Clear'     => '☀️',
            'Clouds'    => '☁️',
            'Rain'      => '🌧️',
            'Drizzle'   => '🌦️',
            'Thunderstorm' => '⛈️',
            'Snow'      => '❄️',
            'Mist'      => '🌫️',
            'Fog'       => '🌫️',
            'Haze'      => '🌫️',
            'Smoke'     => '🌫️',
            'Dust'      => '💨',
            'Tornado'   => '🌪️',
        ];
        return $emojiMap[$main] ?? '🌤️';
    }

    /**
     * 获取兜底数据（API 不可用时使用）
     * @return array
     */
    private function getFallbackData(): array
    {
        return [
            'city'        => $this->cityName,
            'temp'        => '--',
            'feels_like'  => '--',
            'temp_min'    => '--',
            'temp_max'    => '--',
            'humidity'    => '--',
            'description' => '数据获取中...',
            'icon'        => '🌤️',
            'wind_speed'  => '--',
            'pressure'    => '--',
            'visibility'  => null,
            'raw'         => null,
        ];
    }

    /**
     * 发送 HTTP GET 请求
     * @param string $url 请求URL
     * @return string|null 响应内容，失败返回 null
     */
    private function httpGet(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            error_log('PHP curl 扩展未安装，无法获取天气数据');
            return null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'MoYu-Office/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) {
            error_log("天气API请求失败: {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("天气API返回HTTP {$httpCode}");
            return null;
        }

        return $response;
    }
}
