<?php
/**
 * ============================================================
 * 摸鱼办 - 邮件发送模块
 * ============================================================
 * 功能：将生成的摸鱼日报图片通过 SMTP 邮件发送给指定收件人。
 *
 * 支持两种模式：
 *   1. PHPMailer 模式（推荐）：需要 composer 安装 phpmailer
 *   2. PHP mail() 模式（兜底）：无需额外依赖，但功能有限
 *
 * 邮件内容：HTML格式的摸鱼日报摘要 + 图片附件
 *
 * 依赖：
 *   - 推荐：composer require phpmailer/phpmailer
 *   - 兜底：PHP 内置 mail() 函数
 * ============================================================
 */

class EmailSender
{
    /** @var array 邮件配置 */
    private $config;

    /** @var bool 是否启用 */
    private $enabled;

    public function __construct(array $emailConfig)
    {
        $this->config  = $emailConfig;
        $this->enabled = $emailConfig['enabled'] ?? false;
    }

    /**
     * 发送摸鱼日报邮件
     *
     * @param string $imagePath  生成的图片文件路径
     * @param array  $summary    摘要数据（用于生成HTML邮件正文）
     *   - date:        公历日期
     *   - lunar:       农历信息
     *   - weather:     天气数据
     *   - holidays:    节假日列表
     *   - fortune:     运势数据
     * @return array ['success' => bool, 'message' => string]
     */
    public function send(string $imagePath, array $summary): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'message' => '邮件发送已禁用，请在 config.php 中设置 email.enabled = true'];
        }

        $recipients = $this->config['to'] ?? [];
        if (empty($recipients)) {
            return ['success' => false, 'message' => '收件人列表为空，请在 config.php 中配置 email.to'];
        }

        // 生成 HTML 邮件正文
        $htmlBody = $this->buildHtmlBody($summary);

        // 优先使用 PHPMailer，兜底使用 mail()
        if ($this->isPhpMailerAvailable()) {
            return $this->sendWithPhpMailer($imagePath, $htmlBody, $recipients);
        }

        return $this->sendWithNativeMail($imagePath, $htmlBody, $recipients);
    }

    /**
     * 构建 HTML 邮件正文
     */
    private function buildHtmlBody(array $summary): string
    {
        $date = $summary['date'] ?? date('Y年m月d日');
        $lunar = $summary['lunar'] ?? [];
        $weather = $summary['weather'] ?? [];
        $holidays = $summary['holidays'] ?? [];
        $fortune = $summary['fortune'] ?? [];

        // 农历文字
        $lunarText = sprintf(
            '%s年 %s%s',
            $lunar['full_name'] ?? '',
            $lunar['month_name'] ?? '',
            $lunar['day_name'] ?? ''
        );

        // 天气文字
        $weatherText = '';
        if (!empty($weather['temp']) && $weather['temp'] !== '--') {
            $weatherText = sprintf(
                '%s %s，温度 %s°C，湿度 %s%%',
                $weather['icon'] ?? '',
                $weather['description'] ?? '',
                $weather['temp'],
                $weather['humidity'] ?? '--'
            );
        }

        // 假期列表
        $holidayItems = '';
        $showCount = min(count($holidays), 5);
        for ($i = 0; $i < $showCount; $i++) {
            $h = $holidays[$i];
            $daysLabel = ($h['days'] == 0) ? '🎉 今天！' : "{$h['days']}天";
            $holidayItems .= "<tr><td style='padding:4px 8px;'>{$h['emoji']} {$h['name']}</td>"
                . "<td style='padding:4px 8px;color:#6c757d;'>{$h['date_str']}</td>"
                . "<td style='padding:4px 8px;color:#FF6B35;font-weight:bold;'>{$daysLabel}</td></tr>";
        }

        // 运势
        $fortuneText = sprintf(
            '%s %s · 摸鱼指数 %s',
            $fortune['fortune_emoji'] ?? '',
            $fortune['fortune_level'] ?? '',
            $fortune['moyu_stars'] ?? ''
        );

        // 预计算宜忌和提示（heredoc 内不支持 ?? 运算符）
        $yiText = $this->joinList($fortune['yi'] ?? []);
        $jiText = $this->joinList($fortune['ji'] ?? []);
        $tipText = $fortune['tip'] ?? '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:'Microsoft YaHei','PingFang SC',sans-serif;max-width:600px;margin:0 auto;padding:20px;background:#f5f5f5;">
  <div style="background:linear-gradient(135deg,#667eea,#764ba2);padding:24px;border-radius:16px 16px 0 0;text-align:center;">
    <h1 style="color:white;margin:0;font-size:28px;">摸 鱼 办</h1>
    <p style="color:rgba(255,255,255,0.8);margin:8px 0 0;">{$date} · {$lunarText}</p>
  </div>
  <div style="background:white;padding:20px;">
    <h3 style="color:#667eea;border-bottom:2px solid #667eea;padding-bottom:8px;">天气</h3>
    <p>{$weatherText}</p>
    <h3 style="color:#667eea;border-bottom:2px solid #667eea;padding-bottom:8px;">假期倒计时</h3>
    <table style="width:100%;border-collapse:collapse;">{$holidayItems}</table>
    <h3 style="color:#667eea;border-bottom:2px solid #667eea;padding-bottom:8px;">今日运势</h3>
    <p>{$fortuneText}</p>
    <p style="color:#6c757d;font-size:14px;">宜：{$yiText}</p>
    <p style="color:#6c757d;font-size:14px;">忌：{$jiText}</p>
    <p style="color:#adb5bd;font-size:12px;text-align:center;margin-top:16px;">{$tipText}</p>
  </div>
  <div style="background:#f8f9fa;padding:12px;border-radius:0 0 16px 16px;text-align:center;">
    <p style="color:#adb5bd;font-size:12px;margin:0;">工作再忙，也要适当摸鱼 ~</p>
  </div>
</body>
</html>
HTML;
    }

    /**
     * 使用 PHPMailer 发送（推荐）
     */
    private function sendWithPhpMailer(string $imagePath, string $htmlBody, array $recipients): array
    {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // SMTP 配置
            $mail->isSMTP();
            $mail->Host       = $this->config['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = $this->config['smtp_secure'] ?? 'ssl';
            $mail->Port       = $this->config['smtp_port'] ?? 465;
            $mail->CharSet    = 'UTF-8';

            // 发件人
            $mail->setFrom(
                $this->config['username'],
                $this->config['from_name'] ?? '摸鱼办'
            );

            // 收件人
            foreach ($recipients as $email) {
                $mail->addAddress($email);
            }

            // 添加图片附件（作为内嵌图片）
            if (file_exists($imagePath)) {
                $mail->addAttachment($imagePath, 'moyu_daily.png');
            }

            // 邮件内容
            $mail->isHTML(true);
            $mail->Subject = '🐟 摸鱼日报 - ' . date('Y年m月d日');
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();
            return ['success' => true, 'message' => '邮件发送成功，收件人: ' . implode(', ', $recipients)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => '邮件发送失败: ' . $e->getMessage()];
        }
    }

    /**
     * 使用 PHP 原生 mail() 发送（兜底方案）
     */
    private function sendWithNativeMail(string $imagePath, string $htmlBody, array $recipients): array
    {
        $boundary = md5(uniqid(time()));
        $subject = '=?UTF-8?B?' . base64_encode('🐟 摸鱼日报 - ' . date('Y年m月d日')) . '?=';

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($this->config['from_name'] ?? '摸鱼办') . "?= <{$this->config['username']}>\r\n";

        // HTML 正文部分
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        // 图片附件部分
        if (file_exists($imagePath)) {
            $fileData = file_get_contents($imagePath);
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: image/png; name=\"moyu_daily.png\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"moyu_daily.png\"\r\n\r\n";
            $body .= chunk_split(base64_encode($fileData)) . "\r\n";
        }

        $body .= "--{$boundary}--";

        $to = implode(', ', $recipients);
        $result = @mail($to, $subject, $body, $headers);

        if ($result) {
            return ['success' => true, 'message' => '邮件已通过 mail() 发送（注意：原生 mail() 可能无法直接发SMTP，建议使用 PHPMailer）'];
        }
        return ['success' => false, 'message' => 'mail() 发送失败，请安装 PHPMailer: composer require phpmailer/phpmailer'];
    }

    /**
     * 检查 PHPMailer 是否可用
     */
    private function isPhpMailerAvailable(): bool
    {
        return class_exists('\\PHPMailer\\PHPMailer\\PHPMailer');
    }

    /**
     * 连接数组为逗号分隔字符串
     */
    private function joinList(array $items): string
    {
        return implode('、', $items);
    }
}
