<?php
ob_start();
// Đặt tại: DATN/Internal/Staff/HLV/HLV.php
require_once __DIR__ . '/../../auth_check.php';
requireChucVu('Personal Trainer');

$ho_ten        = htmlspecialchars($_SESSION['full_name'] ?? 'Huấn luyện viên');
$ten_dang_nhap = htmlspecialchars($_SESSION['username']  ?? 'hlv');
$initials      = mb_strtoupper(mb_substr($ho_ten, 0, 1));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Gym - Huấn luyện viên</title>
    <link rel="stylesheet" href="HLV.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-shell">

        <!-- ===== SIDEBAR ===== -->
        <aside class="sidebar">
            <div class="sidebar-logo-wrap">
                <div class="logo-hex">
                    <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="30,2 56,16 56,44 30,58 4,44 4,16" fill="none" stroke="#d4a017" stroke-width="2.5"/>
                        <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="#d4a017" font-size="18" font-weight="800" font-family="Barlow Condensed">EG</text>
                    </svg>
                </div>
                <div class="logo-text">
                    <span class="brand-name">ELITE GYM</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-group">
                    <span class="nav-group-label">QUẢN LÝ</span>
                    <ul>
                        <li class="nav-item" data-page="customer.php">
                            <a href="#customer.php">
                                <i class="fas fa-users"></i>
                                <span>Quản lý khách hàng</span>
                            </a>
                        </li>
                        <li class="nav-item" data-page="Schedule_Management.php">
                            <a href="#Schedule_Management.php">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Quản lý lịch tập</span>
                            </a>
                        </li>
                        <li class="nav-item" data-page="facilities.php">
                            <a href="#facilities.php">
                                <i class="fas fa-tools"></i>
                                <span>Cơ sở vật chất</span>
                            </a>
                        </li>
                        <li class="nav-item" data-page="Profile.php">
                            <a href="#Profile.php">
                                <i class="fas fa-user-circle"></i>
                                <span>Thông tin cá nhân</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- User + Logout -->
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?= $initials ?></div>
                    <div class="user-details">
                        <span class="user-name"><?= $ho_ten ?></span>
                        <span class="user-role">HUẤN LUYỆN VIÊN</span>
                    </div>
                </div>
                <a href="../../Index/Login/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Đăng xuất</span>
                </a>
            </div>
        </aside>

        <!-- ===== MAIN ===== -->
        <div class="main-wrap">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="page-icon-wrap" id="page-icon-wrap">
                        <i class="fas fa-users" id="page-icon"></i>
                    </div>
                    <div class="page-title-wrap">
                        <h1 class="page-title" id="page-title">QUẢN LÝ KHÁCH HÀNG</h1>
                        <span class="breadcrumb" id="breadcrumb">Trang chủ / Khách hàng</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="topbar-time" id="topbar-time"></span>
                    <span class="topbar-admin"><?= $ho_ten ?></span>
                    <div class="topbar-avatar"><?= $initials ?></div>
                </div>
            </header>

            <main class="content-area">
                <div class="content-wrapper" id="content-wrapper">
                    <!-- Loaded dynamically -->
                </div>
            </main>
        </div>
    </div>

    <script src="HLV.js"></script>
</body>
</html>
