<?php
/**
 * Chuyển app/modules → app/views + app/api theo cấu trúc MVC mục tiêu.
 */
$root = dirname(__DIR__);
$app  = $root . '/app';

$viewMap = [
    'modules/auth/Login/Login.php' => 'views/auth/login.php',
    'modules/auth/Register/Register.php' => 'views/auth/register.php',
    'modules/auth/Forgot_Password/Forgot_Password.php' => 'views/auth/forgot_password.php',
    'modules/home/index.php' => 'views/home/index.php',
    'modules/home/Profile/Profile.php' => 'views/home/profile.php',
    'modules/home/Payment/Payment.php' => 'views/home/payment.php',
    'modules/home/Schedule/Schedule.php' => 'views/home/schedule.php',
    'modules/home/Review/reviews_section.php' => 'views/home/partials/reviews_section.php',
    'modules/home/Review/notification_ui.php' => 'views/home/partials/notification_ui.php',
    'modules/admin_shell/adm.php' => 'views/admin/dashboard.php',
    'modules/admin/overview/overview.php' => 'views/admin/overview.php',
    'modules/admin/Account_Management/Account_Management.php' => 'views/admin/account_management.php',
    'modules/admin/Customer_Management/Customer_Management.php' => 'views/admin/customer_management.php',
    'modules/admin/Employee_Management/Employee_Management.php' => 'views/admin/employee_management.php',
    'modules/admin/Employee_Management/Employee_attendance_tracking/Employee_attendance_tracking.php' => 'views/admin/employee_attendance.php',
    'modules/admin/Package_Management/Package_Management.php' => 'views/admin/package_management.php',
    'modules/admin/Invoice_Management/Invoice_Management.php' => 'views/admin/invoice_management.php',
    'modules/admin/Promotion_Management/Promotion_Management.php' => 'views/admin/promotion_management.php',
    'modules/admin/Management_statistics/management_statistics.php' => 'views/admin/statistics.php',
    'modules/admin/Review_Management/Review_Management.php' => 'views/admin/review_management.php',
    'modules/admin/Schedule_Management/Schedule_Management.php' => 'views/admin/schedule_management.php',
    'modules/admin/Facilities_Management/Facilities_Management.php' => 'views/admin/facilities_management.php',
    'modules/admin/Gym_Management/Gym_Management.php' => 'views/admin/gym_management.php',
    'modules/admin/Setting/System/System.php' => 'views/admin/setting_system.php',
    'modules/admin/Setting/Image_landing/Image_landing.php' => 'views/admin/setting_landing.php',
    'modules/admin/Setting/GPS/GPS.php' => 'views/admin/setting_gps.php',
    'modules/admin/Profile/Profile.php' => 'views/admin/profile.php',
    'modules/staff/HLV/HLV.php' => 'views/staff/hlv.php',
    'modules/staff/Receptionist/Receptionist.php' => 'views/staff/receptionist.php',
];

$apiMap = [
    'modules/auth/Login/Login_Function.php' => 'api/auth/login.php',
    'modules/auth/Register/Register_Function.php' => 'api/auth/register.php',
    'modules/auth/Forgot_Password/Forgot_Password_Function.php' => 'api/auth/forgot_password.php',
    'modules/admin_shell/payment_webhook.php' => 'api/payment_webhook.php',
    'modules/home/Schedule/ai_proxy.php' => 'api/schedule_ai_proxy.php',
    'modules/admin/Customer_Management/QZ_Check/get_ngrok_url.php' => 'api/qr_ngrok.php',
    'modules/admin/Customer_Management/QZ_Check/qr_bridge.php' => 'api/qr_bridge.php',
    'modules/admin/Customer_Management/QZ_Check/qr_phone_scanner.php' => 'api/qr_phone_scanner.php',
];

function copyMapped(array $map, string $app): int
{
    $n = 0;
    foreach ($map as $from => $to) {
        $src = $app . '/' . $from;
        $dst = $app . '/' . $to;
        if (!is_file($src)) {
            echo "SKIP (missing): {$from}\n";
            continue;
        }
        $dir = dirname($dst);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        copy($src, $dst);
        $n++;
    }
    return $n;
}

$viewsCopied = copyMapped($viewMap, $app);

$apiDir = $app . '/api';
if (!is_dir($apiDir)) {
    mkdir($apiDir, 0755, true);
}
$apiCopied = copyMapped($apiMap, $app);

$apiFromModules = glob($app . '/modules/api/*.php') ?: [];
foreach ($apiFromModules as $src) {
    $name = basename($src);
    $dst = $apiDir . '/' . $name;
    copy($src, $dst);
    $apiCopied++;
}

$store = $app . '/modules/admin/Customer_Management/QZ_Check/qr_bridge_store.json';
if (is_file($store)) {
    copy($store, $apiDir . '/qr_bridge_store.json');
}

$patchDirs = [$app . '/views', $app . '/api'];
$replacements = [
    "/require_once\s+__DIR__\s*\.\s*'\/\.\.\/\.\.\/\.\.\/Database\/db\.php';\s*/" => '',
    "/require_once\s+__DIR__\s*\.\s*'\/\.\.\/\.\.\/Database\/db\.php';\s*/" => '',
    "/require_once\s+__DIR__\s*\.\s*'\/\.\.\/\.\.\/\.\.\/\.\.\/Database\/db\.php';\s*/" => '',
    "require_once '../../PHPMailer/" => "require_once VENDOR_PATH . '/PHPMailer/",
    "require_once '../../../PHPMailer/" => "require_once VENDOR_PATH . '/PHPMailer/",
    "require_once __DIR__ . '/../../../PHPMailer/" => "require_once VENDOR_PATH . '/PHPMailer/",
    "include 'Review/reviews_section.php'" => "include __DIR__ . '/partials/reviews_section.php'",
    "include 'Review/notification_ui.php'" => "include __DIR__ . '/partials/notification_ui.php'",
    "const HANDLER   = 'Review/review_handler.php'" => "const HANDLER   = (window.ELITE_BASE || '') + '/api/review'",
    "const HANDLER = 'Review/review_handler.php'" => "const HANDLER = (window.ELITE_BASE || '') + '/api/review'",
    'href="Customer_Management.css"' => 'href="<?= asset(\'css/Customer_Management.css\') ?>"',
    'href="QZ_Check/QR_CheckIn.css"' => 'href="<?= asset(\'css/QR_CheckIn.css\') ?>"',
    'src="QZ_Check/QR_CheckIn.js"' => 'src="<?= asset(\'js/QR_CheckIn.js\') ?>"',
];

$assetPatterns = [
    '/href="([A-Za-z0-9_]+\.css)"/' => "href=\"<?= asset('css/$1') ?>\"",
    '/src="([A-Za-z0-9_]+\.js)"/' => "src=\"<?= asset('js/$1') ?>\"",
];

$patched = 0;
foreach ($patchDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        $orig = $content;
        foreach ($replacements as $from => $to) {
            if ($from[0] === '/') {
                $content = preg_replace($from, $to, $content);
            } else {
                $content = str_replace($from, $to, $content);
            }
        }
        foreach ($assetPatterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        $content = preg_replace_callback('/src="([A-Za-z0-9_\/]+\.js)"/', static function ($m) {
            $name = basename($m[1]);
            return 'src="<?= asset(\'js/' . $name . '\') ?>"';
        }, $content);
        if ($content !== $orig) {
            file_put_contents($file->getPathname(), $content);
            $patched++;
        }
    }
}

echo "Views copied: {$viewsCopied}\n";
echo "API files: {$apiCopied}\n";
echo "Patched PHP: {$patched}\n";
