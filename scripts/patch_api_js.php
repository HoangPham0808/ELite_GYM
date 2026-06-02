<?php
/** Patch API URL trong JS modules */
$root = dirname(__DIR__);
$map = [
    'Customer_Management_function.php' => "window.ELITE_BASE + '/api/admin/customer'",
    'Customer_Management_Function.php' => "window.ELITE_BASE + '/api/admin/customer'",
    'Employee_Management_function.php' => "window.ELITE_BASE + '/api/admin/employee'",
    'Employee_Management_Function.php' => "window.ELITE_BASE + '/api/admin/employee'",
    'Employee_attendance_tracking_function.php' => "window.ELITE_BASE + '/api/admin/employee-attendance'",
    'Facilities_Management_function.php' => "window.ELITE_BASE + '/api/admin/facility'",
    'Gym_Management_function.php' => "window.ELITE_BASE + '/api/admin/gym'",
    'Invoice_Management_function.php' => "window.ELITE_BASE + '/api/admin/invoice'",
    'Package_Management_function.php' => "window.ELITE_BASE + '/api/admin/package'",
    'Promotion_Management_function.php' => "window.ELITE_BASE + '/api/admin/promotion'",
    'Schedule_Management_function.php' => "window.ELITE_BASE + '/api/admin/schedule'",
    'management_statistics_function.php' => "window.ELITE_BASE + '/api/admin/statistics'",
    'Account_Management_function.php' => "window.ELITE_BASE + '/api/admin/account'",
    'System_function.php' => "window.ELITE_BASE + '/api/admin/setting/system'",
    'Image_landing_function.php' => "window.ELITE_BASE + '/api/admin/setting/landing'",
    'GPS_function.php' => "window.ELITE_BASE + '/api/admin/setting/gps'",
    'Review_Management_function.php' => "window.ELITE_BASE + '/api/admin/review'",
    'QR_CheckIn_function.php' => "window.ELITE_BASE + '/api/admin/qr'",
    'QZ_Check/QR_CheckIn_function.php' => "window.ELITE_BASE + '/api/admin/qr'",
    'Schedule_function.php' => "window.ELITE_BASE + '/api/schedule'",
    'Payment_function.php' => "window.ELITE_BASE + '/api/payment'",
    'Profile_function.php' => "window.ELITE_BASE + '/api/admin/profile'",
    'Profile_Function.php' => "window.ELITE_BASE + '/api/profile'",
    "'ai_proxy.php'" => "window.ELITE_BASE + '/api/schedule/ai'",
    "'Schedule_function.php'" => "window.ELITE_BASE + '/api/schedule'",
    '/PHP/ELite_GYM/Internal/Layout/Profile/Profile_function.php' => "window.ELITE_BASE + '/api/admin/profile'",
    '/PHP/ELite_GYM/Internal/Layout/Setting/System/System_function.php' => "window.ELITE_BASE + '/api/admin/setting/system'",
    '/PHP/ELite_GYM/Internal/Layout/Customer_Management/Customer_Management_function.php' => "window.ELITE_BASE + '/api/admin/customer'",
    "Employee_attendance_tracking/Employee_attendance_tracking_function.php" => "window.ELITE_BASE + '/api/admin/employee-attendance'",
];

$dirs = [$root . '/app/modules', $root . '/public/assets/js'];
$n = 0;
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'js') continue;
        $c = file_get_contents($file->getPathname());
        $o = $c;
        foreach ($map as $from => $to) {
            $c = str_replace($from, $to, $c);
        }
        if ($c !== $o) {
            file_put_contents($file->getPathname(), $c);
            $n++;
        }
    }
}
echo "Patched {$n} JS files\n";
