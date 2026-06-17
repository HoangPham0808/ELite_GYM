<?php
/**
 * MailService.php
 * Vị trí: app/services/MailService.php
 */

class MailService
{
    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    private string $from;
    private string $fromName;

    public function __construct()
    {
        $cfg = file_exists(APP_PATH . '/config/mail.php')
             ? require APP_PATH . '/config/mail.php'
             : [];
        $this->host     = $cfg['host']      ?? 'smtp.gmail.com';
        $this->port     = (int)($cfg['port'] ?? 587);
        $this->username = $cfg['username']  ?? '';
        $this->password = $cfg['password']  ?? '';
        $this->from     = $cfg['from']      ?? $this->username;
        $this->fromName = $cfg['from_name'] ?? 'Elite Fitness Gym';
    }

    // ── Gửi OTP quên mật khẩu ─────────────────────────────────
    public function sendForgotPasswordOtp(string $toEmail, string $otp): bool
    {
        return $this->send($toEmail,
            '[Elite Fitness] - Mã OTP Đặt Lại Mật Khẩu',
            $this->buildOtpTemplate($otp, 'Đặt lại mật khẩu',
                'Chúng tôi nhận được yêu cầu đặt lại mật khẩu. Sử dụng mã OTP bên dưới:',
                'Nếu bạn không gửi yêu cầu này, hãy bỏ qua email này.'),
            "Mã OTP của bạn là: {$otp} (có hiệu lực 5 phút)"
        );
    }

    // ── Gửi OTP đổi mật khẩu ──────────────────────────────────
    public function sendChangePasswordOtp(string $toEmail, string $otp): bool
    {
        return $this->send($toEmail,
            '[Elite Fitness] - Mã OTP Đổi Mật Khẩu',
            $this->buildOtpTemplate($otp, 'Xác nhận đổi mật khẩu',
                'Chúng tôi nhận được yêu cầu đổi mật khẩu. Sử dụng mã OTP bên dưới để xác nhận.',
                'Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email này.'),
            "Mã OTP đổi mật khẩu: {$otp} (hiệu lực 5 phút)"
        );
    }

    // ── Gửi thông báo tự động ─────────────────────────────────
    // Gọi từ NotificationService::sendEmail()
    public function sendNotification(
        string $toEmail,
        string $customerName,
        string $title,
        string $message,
        string $type           // 'no_registration' | 'absent' | 'review_reply'
    ): bool {
        [$icon, $color, $subject] = match($type) {
            'no_registration' => ['📅', '#f59e0b', '[Elite Gym] Nhắc nhở: Chưa đăng ký ca tập'],
            'absent'          => ['⚠️', '#ef4444', '[Elite Gym] Thông báo: Vắng ca tập'],
            'review_reply'    => ['💬', '#3b82f6', '[Elite Gym] Phản hồi đánh giá của bạn'],
            default           => ['🔔', '#cc0000', '[Elite Gym] Thông báo mới'],
        };

        $escapedMsg  = nl2br(htmlspecialchars($message));
        $escapedName = htmlspecialchars($customerName);
        $escapedTitle= htmlspecialchars($title);
        $year        = date('Y');

        $body = "
        <html>
        <body style='font-family:Arial,sans-serif;background:#0a0a0a;color:#fff;padding:30px;margin:0;'>
          <div style='max-width:520px;margin:auto;background:#111111;border-radius:4px;overflow:hidden;border:1px solid rgba(204,0,0,.25);'>

            <!-- Header -->
            <div style='background:#cc0000;padding:18px 32px;display:flex;align-items:center;gap:12px;'>
              <span style='font-family:Arial,sans-serif;font-size:1.5rem;font-weight:900;color:#fff;letter-spacing:2px;text-transform:uppercase;'>ELITE GYM</span>
              <span style='color:rgba(255,255,255,.6);font-size:.8rem;margin-left:auto;text-transform:uppercase;letter-spacing:1px;'>Thông báo</span>
            </div>

            <!-- Body -->
            <div style='padding:28px 32px;'>
              <p style='color:rgba(255,255,255,.6);margin:0 0 6px;font-size:.85rem;'>Xin chào,</p>
              <p style='color:#fff;font-size:1rem;font-weight:700;margin:0 0 20px;'>{$escapedName}</p>

              <!-- Notification card -->
              <div style='background:rgba(255,255,255,.04);border-left:3px solid {$color};border-radius:2px;padding:16px 20px;margin-bottom:20px;'>
                <div style='font-size:.85rem;color:{$color};font-weight:700;margin-bottom:8px;'>
                  {$icon} {$escapedTitle}
                </div>
                <div style='color:rgba(255,255,255,.75);font-size:.9rem;line-height:1.6;'>
                  {$escapedMsg}
                </div>
              </div>

              <div style='text-align:center;margin:24px 0 8px;'>
                <a href='http://localhost/PHP/ELite_GYM/public/'
                   style='background:#cc0000;color:#fff;text-decoration:none;padding:12px 32px;border-radius:3px;font-weight:700;font-size:.9rem;letter-spacing:1px;display:inline-block;'>
                  Vào trang Elite Gym
                </a>
              </div>
            </div>

            <!-- Footer -->
            <div style='padding:16px 32px;border-top:1px solid rgba(255,255,255,.08);'>
              <p style='color:rgba(255,255,255,.3);font-size:.75rem;margin:0;text-align:center;'>
                © {$year} Elite Fitness Gym. Nếu bạn không muốn nhận email này, liên hệ lễ tân.
              </p>
            </div>
          </div>
        </body></html>";

        return $this->send($toEmail, $subject, $body, strip_tags($message));
    }

    // ── Core send ──────────────────────────────────────────────
    private function send(string $to, string $subject, string $htmlBody, string $altBody): bool
    {
        // Load PHPMailer nếu chưa có — thử nhiều vị trí phổ biến
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $base = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
            $candidates = [
                // ✅ Đường dẫn thực tế trong project
                $base . '/vendor/PHPMailer',
                // Các path phổ biến khác
                __DIR__ . '/PHPMailer',
                dirname(__DIR__) . '/PHPMailer',
                $base . '/vendor/phpmailer/phpmailer/src',
                $base . '/PHPMailer',
                $base . '/app/PHPMailer',
            ];

            $loaded = false;
            foreach ($candidates as $dir) {
                if (file_exists($dir . '/PHPMailer.php')) {
                    require_once $dir . '/Exception.php';
                    require_once $dir . '/PHPMailer.php';
                    require_once $dir . '/SMTP.php';
                    $loaded = true;
                    break;
                }
            }

            if (!$loaded) {
                error_log('[EliteGym] MailService: Không tìm thấy PHPMailer. Kiểm tra đường dẫn trong app/services/MailService.php');
                return false;
            }
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // Debug — ghi SMTP log ra file
            $mail->SMTPDebug  = 2;
            $logFile = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2))
                     . '/storage/logs/smtp_debug.log';
            $mail->Debugoutput = function(string $str, int $level) use ($logFile): void {
                error_log('[' . date('H:i:s') . '] ' . trim($str) . "\n", 3, $logFile);
            };

            $mail->isSMTP();
            $mail->Host       = $this->host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->username;
            $mail->Password   = str_replace(' ', '', $this->password); // bỏ space App Password
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $this->port;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($this->from, $this->fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $altBody;
            $mail->send();
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log('[EliteGym] MailService::send() FAILED to=' . $to . ' err=' . $mail->ErrorInfo . "\n", 3,
                (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/storage/logs/smtp_debug.log');
            return false;
        }
    }

    // ── OTP template dùng chung ────────────────────────────────
    private function buildOtpTemplate(string $otp, string $subtitle, string $intro, string $footer): string
    {
        $year = date('Y');
        return "
        <html>
        <body style='font-family:Arial,sans-serif;background:#0a0a0a;color:#fff;padding:30px;margin:0;'>
          <div style='max-width:480px;margin:auto;background:#111111;border-radius:4px;padding:0;overflow:hidden;border:1px solid rgba(204,0,0,.3);'>
            <div style='background:#cc0000;padding:20px 32px;'>
              <h2 style='color:#fff;font-size:1.6rem;margin:0;font-weight:900;letter-spacing:2px;text-transform:uppercase;'>ELITE GYM</h2>
              <p style='color:rgba(255,255,255,.75);margin:4px 0 0;font-size:.85rem;letter-spacing:1px;text-transform:uppercase;'>{$subtitle}</p>
            </div>
            <div style='padding:32px;'>
              <p style='color:rgba(255,255,255,.85);margin:0 0 24px;line-height:1.6;'>{$intro}</p>
              <div style='text-align:center;margin:28px 0;'>
                <div style='display:inline-block;background:rgba(204,0,0,.1);border:2px solid #cc0000;border-radius:4px;padding:18px 44px;'>
                  <span style='font-size:2.6rem;font-weight:900;letter-spacing:14px;color:#fff;font-family:monospace;'>{$otp}</span>
                </div>
              </div>
              <div style='background:rgba(204,0,0,.08);border-left:3px solid #cc0000;border-radius:2px;padding:12px 16px;margin-bottom:24px;'>
                <p style='color:#ff6666;font-weight:700;margin:0;font-size:.875rem;'>⏱ Mã có hiệu lực trong <strong>5 phút</strong>. Không chia sẻ mã này cho bất kỳ ai.</p>
              </div>
              <hr style='border:none;border-top:1px solid rgba(255,255,255,.08);margin:0 0 20px;'>
              <p style='color:rgba(255,255,255,.35);font-size:.78rem;margin:0;'>{$footer}<br>© {$year} Elite Fitness Gym.</p>
            </div>
          </div>
        </body></html>";
    }
}
