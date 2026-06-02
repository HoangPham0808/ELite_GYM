<?php
/**
 * Front controller — mọi request đi qua đây
 * URL: http://localhost/PHP/ELite_GYM/public/
 */
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/core/App.php';
App::run();
