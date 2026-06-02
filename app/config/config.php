<?php
/**
 * Cấu hình chung — ELite_GYM MVC
 */
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('VENDOR_PATH', ROOT_PATH . '/vendor');

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Tự nhận base URL đầy đủ gồm scheme + host (WAMP: http://localhost/PHP/ELite_GYM/public)
$_scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$_scriptDir = rtrim($_scriptDir, '/');
define('BASE_URL', $_scheme . '://' . $_host . ($_scriptDir === '' ? '' : $_scriptDir));
unset($_scheme, $_host, $_scriptDir);

define('UPLOAD_DIR', PUBLIC_PATH . '/assets/uploads');
define('UPLOAD_URL', BASE_URL . '/assets/uploads');
define('PKG_UPLOAD_DIR', UPLOAD_DIR . '/image_package/');
define('PKG_UPLOAD_URL', UPLOAD_URL . '/image_package/');
define('PANEL_UPLOAD_DIR', UPLOAD_DIR . '/image_panel/');
define('PANEL_UPLOAD_URL', UPLOAD_URL . '/image_panel/');

if (!is_dir(STORAGE_PATH . '/logs')) {
    @mkdir(STORAGE_PATH . '/logs', 0755, true);
}
if (!is_dir(STORAGE_PATH . '/cache')) {
    @mkdir(STORAGE_PATH . '/cache', 0755, true);
}
