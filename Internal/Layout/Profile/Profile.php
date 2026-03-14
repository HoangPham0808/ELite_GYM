<?php
ob_start();
// Đặt tại: ELITE_GYM/Internal/Layout/Profile/Profile.php
require_once __DIR__ . '/../../auth_check.php';
requireRole('Employee'); // Dành cho HLV và Receptionist

$ho_ten   = htmlspecialchars($_SESSION['ho_ten']   ?? ($_SESSION['full_name'] ?? 'Nhân viên'));
$initials = mb_strtoupper(mb_substr($ho_ten, 0, 1));
$chuc_vu  = $_SESSION['position'] ?? '';
$chuc_vu_display = match($chuc_vu) {
    'Personal Trainer' => 'HUẤN LUYỆN VIÊN',
    'Receptionist'     => 'LỄ TÂN',
    default            => strtoupper($chuc_vu)
};
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Gym – Hồ sơ cá nhân</title>
    <link rel="stylesheet" href="Profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<div class="pg-bg">
    <div class="pg-noise"></div>
    <div class="pg-glow"></div>
</div>

<div class="page-wrap">

    <!-- ===== MAIN ===== -->
    <main class="main-content">

        <!-- Page heading -->
        <div class="page-heading">
            <div class="heading-left">
                <div class="heading-icon"><i class="fas fa-user-circle"></i></div>
                <div>
                    <h1 class="heading-title">HỒ SƠ CÁ NHÂN</h1>
                    <p class="heading-sub">Quản lý thông tin tài khoản của bạn</p>
                </div>
            </div>
        </div>

        <!-- Cards grid -->
        <div class="cards-grid">

            <!-- ===== AVATAR CARD ===== -->
            <div class="card card-avatar">
                <div class="avatar-ring">
                    <div class="avatar-circle" id="avatarCircle"><?= $initials ?></div>
                </div>
                <h2 class="avatar-name" id="displayName"><?= $ho_ten ?></h2>
                <span class="avatar-badge">
                    <i class="fas fa-<?= $chuc_vu === 'Personal Trainer' ? 'dumbbell' : 'concierge-bell' ?>"></i>
                    <?= $chuc_vu_display ?>
                </span>

                <div class="avatar-stats">
                    <div class="stat-item">
                        <span class="stat-val" id="statHireDate">–</span>
                        <span class="stat-label">Ngày vào làm</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-val" id="statGender">–</span>
                        <span class="stat-label">Giới tính</span>
                    </div>
                </div>
            </div>

            <!-- ===== INFO CARD ===== -->
            <div class="card card-info">
                <div class="card-header">
                    <div class="card-header-icon"><i class="fas fa-id-card"></i></div>
                    <h3 class="card-title">Thông tin cá nhân</h3>
                    <button class="edit-toggle-btn" id="editToggleBtn" onclick="toggleEdit()">
                        <i class="fas fa-pen"></i> Chỉnh sửa
                    </button>
                </div>

                <div id="toast" class="toast hidden"></div>

                <form id="profileForm" class="profile-form" onsubmit="saveProfile(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Họ và tên</label>
                            <input type="text" name="full_name" id="inp_name" class="form-input" readonly>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Số điện thoại</label>
                            <input type="text" name="phone" id="inp_phone" class="form-input" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="email" id="inp_email" class="form-input" readonly>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-birthday-cake"></i> Ngày sinh</label>
                            <input type="date" name="date_of_birth" id="inp_dob" class="form-input" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-venus-mars"></i> Giới tính</label>
                            <select name="gender" id="inp_gender" class="form-input" disabled>
                                <option value="">– Chọn –</option>
                                <option value="Male">Nam</option>
                                <option value="Female">Nữ</option>
                                <option value="Other">Khác</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-briefcase"></i> Chức vụ</label>
                            <input type="text" id="inp_position" class="form-input" readonly disabled
                                   value="<?= htmlspecialchars($chuc_vu) ?>">
                        </div>
                    </div>
                    <div class="form-row form-row-full">
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Địa chỉ</label>
                            <input type="text" name="address" id="inp_address" class="form-input" readonly>
                        </div>
                    </div>

                    <div class="form-actions hidden" id="formActions">
                        <button type="button" class="btn-cancel" onclick="cancelEdit()">
                            <i class="fas fa-times"></i> Huỷ
                        </button>
                        <button type="submit" class="btn-save" id="btnSave">
                            <i class="fas fa-check"></i> Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>

            <!-- ===== PASSWORD CARD ===== -->
            <div class="card card-password">
                <div class="card-header">
                    <div class="card-header-icon card-header-icon--red"><i class="fas fa-lock"></i></div>
                    <h3 class="card-title">Đổi mật khẩu</h3>
                </div>

                <div id="pwToast" class="toast hidden"></div>

                <form id="passwordForm" class="profile-form" onsubmit="changePassword(event)">
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Mật khẩu hiện tại</label>
                        <div class="pw-wrap">
                            <input type="password" name="current_password" id="inp_curpw" class="form-input" placeholder="Nhập mật khẩu hiện tại" required>
                            <button type="button" class="pw-eye" onclick="togglePw('inp_curpw',this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Mật khẩu mới</label>
                        <div class="pw-wrap">
                            <input type="password" name="new_password" id="inp_newpw" class="form-input" placeholder="Tối thiểu 6 ký tự" required minlength="6">
                            <button type="button" class="pw-eye" onclick="togglePw('inp_newpw',this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-shield-alt"></i> Xác nhận mật khẩu mới</label>
                        <div class="pw-wrap">
                            <input type="password" name="confirm_password" id="inp_confpw" class="form-input" placeholder="Nhập lại mật khẩu mới" required>
                            <button type="button" class="pw-eye" onclick="togglePw('inp_confpw',this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>

                    <div class="pw-strength-wrap" id="pwStrengthWrap" style="display:none">
                        <span class="pw-strength-label">Độ mạnh:</span>
                        <div class="pw-strength-bar">
                            <div class="pw-strength-fill" id="pwStrengthFill"></div>
                        </div>
                        <span class="pw-strength-text" id="pwStrengthText"></span>
                    </div>

                    <button type="submit" class="btn-save btn-save--full" id="btnChangePw">
                        <i class="fas fa-sync-alt"></i> Cập nhật mật khẩu
                    </button>
                </form>
            </div>

        </div>
    </main>
</div>

<script src="Profile.js"></script>
</body>
</html>
