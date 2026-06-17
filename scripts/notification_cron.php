<?php
/**
 * notification_cron.php — Chạy tự động qua Task Scheduler / cron
 * Vị trí: ROOT_PATH/scripts/notification_cron.php
 *
 * Windows Task Scheduler:
 *   Program: C:\wamp64\bin\php\php8.x\php.exe
 *   Arguments: C:\wamp64\www\PHP\ELite_GYM\scripts\notification_cron.php
 *   Schedule: Mỗi 30 phút (hoặc theo nhu cầu)
 *
 * Linux cron (nếu deploy lên hosting):
 *   30 * * * * php /var/www/ELite_GYM/scripts/notification_cron.php >> /var/log/elitegym_notif.log 2>&1
 */

// ── Chặn truy cập trực tiếp qua browser ──────────────────
if (PHP_SAPI !== 'cli') {
    // Vẫn cho phép gọi từ localhost để test, nhưng chặn từ ngoài
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        die('Forbidden');
    }
}

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/config/config.php';
require_once ROOT_PATH . '/app/core/Database.php';

// Load services
$svcDir = ROOT_PATH . '/app/services';
if (!is_dir($svcDir)) mkdir($svcDir, 0755, true);
require_once ROOT_PATH . '/app/services/MailService.php';
require_once ROOT_PATH . '/app/services/NotificationService.php';

$db      = Database::getInstance();
$mail    = new MailService();
$service = new NotificationService($db, $mail);

$log = function(string $msg) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $line . PHP_EOL;
    error_log($line, 3, ROOT_PATH . '/storage/logs/notification_cron.log');
};

$log('=== Bắt đầu chạy notification cron ===');

$today = date('Y-m-d');
$now   = date('Y-m-d H:i:s');
$stats = ['processed' => 0, 'in_app' => 0, 'email' => 0, 'errors' => 0];

// ── Lấy tất cả customer có gói đang active ────────────────
$customers = $db->query("
    SELECT DISTINCT c.customer_id, c.full_name, c.email, a.account_id
    FROM customer c
    JOIN account a ON a.account_id = c.account_id
    JOIN membershipregistration mp ON mp.customer_id = c.customer_id
    WHERE mp.status = 'active'
      AND mp.end_date >= CURDATE()
")->fetch_all(MYSQLI_ASSOC);

$log('Tìm thấy ' . count($customers) . ' khách hàng có gói active');

foreach ($customers as $c) {
    $cid    = (int)$c['customer_id'];
    $acc_id = (int)$c['account_id'];
    $name   = $c['full_name'];
    $email  = $c['email'] ?? '';
    $stats['processed']++;

    try {
        $result = $service->runAutoCheckFull($cid, $acc_id, $name, $email, $today, $now);
        $stats['in_app'] += $result['in_app'];
        $stats['email']  += $result['email'];
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $e) $log("  ERROR cid=$cid: $e");
            $stats['errors']++;
        }
    } catch (Throwable $e) {
        $log("  EXCEPTION cid=$cid: " . $e->getMessage());
        $stats['errors']++;
    }
}

// ── Gói sắp hết hạn (7 ngày và 3 ngày) ──────────────────
$expiring = $db->query("
    SELECT c.customer_id, c.full_name, c.email, a.account_id,
           mp.end_date,
           DATEDIFF(mp.end_date, CURDATE()) AS days_left
    FROM membershipregistration mp
    JOIN customer c ON c.customer_id = mp.customer_id
    JOIN account  a ON a.account_id  = c.account_id
    WHERE mp.status = 'active'
      AND DATEDIFF(mp.end_date, CURDATE()) IN (7, 3)
")->fetch_all(MYSQLI_ASSOC);

$log('Gói sắp hết hạn: ' . count($expiring) . ' khách');

foreach ($expiring as $row) {
    $days   = (int)$row['days_left'];
    $type   = "expiring_{$days}d";
    $expiry = date('d/m/Y', strtotime($row['end_date']));
    $msg    = "Gói tập của bạn còn {$days} ngày (hết hạn: {$expiry}). Gia hạn sớm để không gián đoạn!";

    $result = $service->pushExpiring(
        (int)$row['customer_id'],
        (int)$row['account_id'],
        $row['full_name'],
        $row['email'] ?? '',
        $type,
        $msg,
        $expiry,
        $days,
        $today,
        $now
    );
    $stats['in_app'] += $result['in_app'];
    $stats['email']  += $result['email'];
}

$log("=== Hoàn thành: processed={$stats['processed']} in_app={$stats['in_app']} email={$stats['email']} errors={$stats['errors']} ===");

// Trả JSON nếu gọi từ browser (test)
if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode($stats);
}
