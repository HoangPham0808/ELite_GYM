<?php
chdir('c:/wamp64/www/PHP/ELite_GYM/Internal/Layout/Review_Management');
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_GET['ajax'] = 1;
$_GET['action'] = 'get_reviews';
require 'Review_Management_function.php';
