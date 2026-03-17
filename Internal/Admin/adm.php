<?php
ob_start();
// Đặt tại: DATN/Internal/Admin/adm.php
require_once __DIR__ . '/../auth_check.php';
requireRole('Admin');

// Lấy thông tin từ session để hiển thị trên giao diện
$full_name   = htmlspecialchars($_SESSION['full_name']      ?? 'Admin');
$username    = htmlspecialchars($_SESSION['username'] ?? 'admin');
$initials    = mb_strtoupper(mb_substr($full_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Gym - Quản lý Admin</title>
    <link rel="stylesheet" href="adm.css">
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
                    <span class="nav-group-label">TỔNG QUAN</span>
                    <ul>
                        <li class="nav-item" data-page="overview.php">
                            <a href="#overview.php"><i class="fas fa-th-large"></i><span>Tổng quan</span></a>
                        </li>
                    </ul>
                </div>

                <div class="nav-group">
                    <span class="nav-group-label">QUẢN LÝ</span>
                    <ul>
                        <li class="nav-item" data-page="management_invoice.php">
                            <a href="#management_invoice.php"><i class="fas fa-receipt"></i><span>Quản lý hoá đơn</span></a>
                        </li>
                        <li class="nav-item" data-page="management_package.php">
                            <a href="#management_package.php"><i class="fas fa-times-circle"></i><span>Quản lý gói tập</span></a>
                        </li>
                        <li class="nav-item" data-page="promotion.php">
                            <a href="#promotion.php"><i class="fas fa-tag"></i><span>Quản lý khuyến mãi</span></a>
                        </li>
                        <li class="nav-item" data-page="Account_Management.php">
                            <a href="#Account_Management.php"><i class="fas fa-cog"></i><span>Quản lý hệ thống</span></a>
                        </li>
                        <li class="nav-item" data-page="management_branch.php">
                            <a href="#management_branch.php"><i class="fas fa-chess-board"></i><span>Quản lý phòng tập</span></a>
                        </li>
                        <li class="nav-item active" data-page="customer.php">
                            <a href="#customer.php">
                                <i class="fas fa-users"></i><span>Quản lý khách hàng</span>
                                <span class="badge" id="customerBadge" style="display:none"></span>
                            </a>
                        </li>
                        <li class="nav-item" data-page="Schedule_Management.php">
                            <a href="#Schedule_Management.php"><i class="fas fa-calendar-alt"></i><span>Quản lý lịch tập</span></a>
                        </li>
                        <li class="nav-item" data-page="management_staff.php">
                            <a href="#management_staff.php"><i class="fas fa-user-tie"></i><span>Quản lý nhân viên</span></a>
                        </li>
                        <li class="nav-item" data-page="facilities.php">
                            <a href="#facilities.php"><i class="fas fa-tools"></i><span>Cơ sở vật chất</span></a>
                        </li>
                    </ul>
                </div>

                <div class="nav-group">
                    <span class="nav-group-label">BÁO CÁO</span>
                    <ul>
                        <li class="nav-item" data-page="management_statistics.php">
                            <a href="#management_statistics.php"><i class="fas fa-chart-bar"></i><span>Báo cáo thống kê</span></a>
                        </li>
                    </ul>
                </div>

                <!-- ── CÀI ĐẶT (có submenu) ── -->
                <div class="nav-group nav-group-sub" id="groupSetting">
                    <span class="nav-group-label">CÀI ĐẶT</span>
                    <ul>
                        <!-- Nút cha Setting — toggle submenu -->
                        <li class="nav-item nav-has-sub">
                            <a href="#" class="nav-parent">
                                <i class="fas fa-cog"></i>
                                <span>Cài đặt</span>
                                <i class="fas fa-chevron-down nav-arrow"></i>
                            </a>
                            <!-- Submenu -->
                            <ul class="nav-submenu">
                                <li class="nav-item" data-page="landing_image.php">
                                    <a href="#landing_image.php">
                                        <i class="fas fa-images"></i>
                                        <span>Slider</span>
                                    </a>
                                </li>
                                 <li class="nav-item" data-page="GPS.php">
                                    <a href="#GPS.php">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>GPS</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- User + Logout -->
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?= $initials ?></div>
                    <div class="user-details">
                        <span class="user-name"><?= $full_name ?></span>
                        <span class="user-role">ADMIN</span>
                    </div>
                </div>
                <a href="../Index/Login/logout.php" class="logout-btn">
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
                    <span class="topbar-admin"><?= $full_name ?></span>
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

    <script src="adm.js"></script>
</body>
</html>
