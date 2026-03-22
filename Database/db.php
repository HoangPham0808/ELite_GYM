<?php
// Cấu hình kết nối database
date_default_timezone_set('Asia/Ho_Chi_Minh'); // Múi giờ Việt Nam UTC+7
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "datn";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>