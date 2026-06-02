<?php
$root = dirname(__DIR__);
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app/modules', RecursiveDirectoryIterator::SKIP_DOTS)
);

$replace = [
    "require_once '../Database/db.php';" => '',
    "require_once '../../Database/db.php';" => '',
    "requireRole('Admin');" => '',
    "requireRole('Employee');" => '',
    "requireRole(['Admin','NhanVien']);" => '',
    'action="Login_Function.php"' => 'action="<?= url(\'login\') ?>"',
    'href="../Forgot_Password/Forgot_Password.php"' => 'href="<?= url(\'forgot-password\') ?>"',
    'href="../Register/Register.php"' => 'href="<?= url(\'register\') ?>"',
    'href="Schedule/Schedule.php"' => 'href="<?= url(\'schedule\') ?>"',
    'href="Profile/Profile.php"' => 'href="<?= url(\'profile\') ?>"',
    'href="Payment/Payment.php"' => 'href="<?= url(\'payment\') ?>"',
    'href="../Internal/Index/Login/Login.php"' => 'href="<?= url(\'login\') ?>"',
    'href="../Internal/Index/Register/Register.php"' => 'href="<?= url(\'register\') ?>"',
    'href="../Internal/Index/Login/logout.php"' => 'href="<?= url(\'logout\') ?>"',
    'href="../../Internal/Index/Login/Login.php"' => 'href="<?= url(\'login\') ?>"',
    'href="../../Internal/Index/Login/logout.php"' => 'href="<?= url(\'logout\') ?>"',
    'href="index.php"' => 'href="<?= url() ?>"',
    'href="Login.css"' => 'href="<?= asset(\'css/Login.css\') ?>"',
    'href="Register.css"' => 'href="<?= asset(\'css/Register.css\') ?>"',
    'href="Forgot_Password.css"' => 'href="<?= asset(\'css/Forgot_Password.css\') ?>"',
    'href="Landing.css"' => 'href="<?= asset(\'css/landing.css\') ?>"',
    'href="adm.css"' => 'href="<?= asset(\'css/adm.css\') ?>"',
    'src="Landing.js"' => 'src="<?= asset(\'js/landing.js\') ?>"',
    'src="Login.js"' => 'src="<?= asset(\'js/Login.js\') ?>"',
    'src="adm.js"' => 'src="<?= asset(\'js/adm.js\') ?>"',
    '<link rel="stylesheet" href="Profile.css"' => '<link rel="stylesheet" href="<?= asset(\'css/Profile.css\') ?>"',
    '<link rel="stylesheet" href="Payment.css"' => '<link rel="stylesheet" href="<?= asset(\'css/Payment.css\') ?>"',
    '<link rel="stylesheet" href="Schedule.css"' => '<link rel="stylesheet" href="<?= asset(\'css/Schedule.css\') ?>"',
    'src="Profile.js"' => 'src="<?= asset(\'js/Profile.js\') ?>"',
    'src="Payment.js"' => 'src="<?= asset(\'js/Payment.js\') ?>"',
    'src="Schedule.js"' => 'src="<?= asset(\'js/Schedule.js\') ?>"',
    'src="Schedule_function.php"' => '',
    'action="Profile_Function.php"' => 'action="<?= url(\'api/profile\') ?>"',
    'action="Payment_function.php"' => 'action="<?= url(\'api/payment\') ?>"',
    'action="Schedule_function.php"' => 'action="<?= url(\'api/schedule\') ?>"',
    'fetch(\'Schedule_function.php' => 'fetch(\'<?= url(\'api/schedule\') ?>',
    'fetch("Schedule_function.php' => 'fetch("<?= url(\'api/schedule\') ?>',
    'fetch(\'Review/notification_handler.php' => 'fetch(\'<?= url(\'api/notification\') ?>',
    'fetch(\'Review/notification_auto.php' => 'fetch(\'<?= url(\'api/notification/auto\') ?>',
    "'Review/notification_auto.php" => "'<?= url('api/notification/auto') ?>",
    "'Review/notification_handler.php" => "'<?= url('api/notification') ?>",
    '../Index/Login/logout.php' => '<?= url(\'logout\') ?>',
];

$n = 0;
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;
    $c = file_get_contents($file->getPathname());
    $o = $c;
    foreach ($replace as $from => $to) {
        $c = str_replace($from, $to, $c);
    }
    if ($c !== $o) {
        file_put_contents($file->getPathname(), $c);
        $n++;
    }
}
echo "URL patched {$n} files\n";
