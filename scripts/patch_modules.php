<?php
/**
 * Patch đường dẫn trong app/modules sau khi copy từ cấu trúc cũ
 */
$root = dirname(__DIR__);
$dirs = [
    $root . '/app/modules',
];

$replacements = [
    // DB includes → đã load qua bootstrap
    "/require_once\s+__DIR__\s*\.\s*'\/\.\.\/\.\.\/\.\.\/Database\/db\.php';\s*/" => '',
    "/require_once\s+__DIR__\s*\.\s*'\/\.\.\/\.\.\/Database\/db\.php';\s*/" => '',
    "/require_once\s+__DIR__\s*\.\s*'\/\.\.\/\.\.\/\.\.\/\.\.\/Database\/db\.php';\s*/" => '',
    "/require_once\s+'\.\.\/\.\.\/\.\.\/Database\/db\.php';\s*/" => '',
    "/require_once\s+'\.\.\/\.\.\/Database\/db\.php';\s*/" => '',
    "/require_once\s+'\.\.\/\.\.\/\.\.\/\.\.\/Database\/db\.php';\s*/" => '',
    "/require_once\s+__DIR__\s*\.\s*'\/\.\.\/auth_check\.php';\s*/" => '',
    "/require_once\s+__DIR__\s*\.\s*'\/\.\.\/\.\.\/auth_check\.php';\s*/" => '',
    "/require_once\s+__DIR__\s*\.\s*'\/\.\.\/\.\.\/\.\.\/Internal\/auth_check\.php';\s*/" => '',

    // Upload paths
    "'C:/wamp64/www/PHP/ELite_GYM/upload/image_package/'" => "PKG_UPLOAD_DIR",
    "'/PHP/ELite_GYM/upload/image_package/'" => "PKG_UPLOAD_URL",
    "'C:/wamp64/www/PHP/ELite_GYM/upload/image_panel/'" => "PANEL_UPLOAD_DIR",
    "'/PHP/ELite_GYM/upload/image_panel/'" => "PANEL_UPLOAD_URL",

    // Redirects cũ → url()
    'header("Location: ../../../Home/index.php")' => 'header("Location: " . url())',
    'header("Location: ../../Admin/adm.php")' => 'header("Location: " . url("admin"))',
    'header("Location: ../../Staff/Receptionist/Receptionist.php")' => 'header("Location: " . url("staff/receptionist"))',
    'header("Location: ../../Staff/HLV/HLV.php")' => 'header("Location: " . url("staff/hlv"))',
    'header("Location: Login.php")' => 'header("Location: " . url("login"))',
    'header("Location: Login.php?' => 'header("Location: " . url("login") . "?',
    "header('Location: Login.php')" => "header('Location: ' . url('login'))",
    'header("Location: ../index.php")' => 'header("Location: " . url())',
    'header("Location: ../Internal/Index/Login/Login.php")' => 'header("Location: " . url("login"))',
    'header("Location: ../../Internal/Index/Login/Login.php")' => 'header("Location: " . url("login"))',

    // PHPMailer
    "require_once '../../../PHPMailer/" => "require_once VENDOR_PATH . '/PHPMailer/",
    "require_once __DIR__ . '/../../../PHPMailer/" => "require_once VENDOR_PATH . '/PHPMailer/",

    // Logo image
    'src="../../Home/ELITY.png"' => 'src="<?= asset(\'images/ELITY.png\') ?>"',
    'href="../../Home/ELITY.png"' => 'href="<?= asset(\'images/ELITY.png\') ?>"',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app/modules', RecursiveDirectoryIterator::SKIP_DOTS)
);

$count = 0;
foreach ($iterator as $file) {
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
    if ($content !== $orig) {
        file_put_contents($file->getPathname(), $content);
        $count++;
    }
}
echo "Patched {$count} files\n";
