<?php
ob_start();
session_start();

// ── Auth guard ────────────────────────────────────────────────
if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header("Location: ../Internal/Index/Login/Login.php");
    exit;
}

require_once '../../Database/db.php';

$account_id = (int)$_SESSION['account_id'];
$active_tab = $_GET['tab']     ?? 'info';
$pw_step    = (int)($_GET['pw_step'] ?? 0); // 1 = passwords, 2 = OTP

// ── Customer profile ──────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT c.customer_id, c.full_name, c.date_of_birth, c.gender,
           c.phone, c.email, c.address, c.registered_at, a.username
    FROM Customer c
    JOIN Account a ON a.account_id = c.account_id
    WHERE c.account_id = ? LIMIT 1
");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) { header("Location: ../index.php"); exit; }
$cid = (int)$customer['customer_id'];

// ── Memberships ───────────────────────────────────────────────
$memberships = $conn->query("
    SELECT mr.start_date, mr.end_date, mp.plan_name, mp.duration_months, mp.price
    FROM MembershipRegistration mr
    JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
    WHERE mr.customer_id = $cid ORDER BY mr.end_date DESC
")->fetch_all(MYSQLI_ASSOC);

$active_plan = null;
$today = date('Y-m-d');
foreach ($memberships as $m) { if ($m['end_date'] >= $today) { $active_plan = $m; break; } }

// ── Check-in ──────────────────────────────────────────────────
// GymCheckIn lưu mỗi lượt checkin/checkout thành 1 row riêng
// → ghép cặp: mỗi checkin + checkout gần nhất cùng ngày sau đó
$checkins_raw = $conn->query("
    SELECT
        gin.check_time  AS check_in,
        (SELECT MIN(gout.check_time)
         FROM GymCheckIn gout
         WHERE gout.customer_id = gin.customer_id
           AND gout.type        = 'checkout'
           AND gout.check_time  > gin.check_time
           AND DATE(gout.check_time) = DATE(gin.check_time)
        ) AS check_out
    FROM GymCheckIn gin
    WHERE gin.customer_id = $cid
      AND gin.type = 'checkin'
    ORDER BY gin.check_time DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Đổi tên key để HTML cũ dùng $ci['check_in'] / $ci['check_out'] vẫn chạy đúng
$checkins = $checkins_raw;
$total_checkins = $conn->query("SELECT COUNT(*) AS c FROM GymCheckIn WHERE customer_id=$cid AND type='checkin'")->fetch_assoc()['c'] ?? 0;

// ── Classes ───────────────────────────────────────────────────
$classes = $conn->query("
    SELECT tc.class_name, tc.class_time, e.full_name AS trainer_name
    FROM ClassRegistration cr
    JOIN TrainingClass tc ON tc.class_id = cr.class_id
    LEFT JOIN Employee e ON e.employee_id = tc.trainer_id
    WHERE cr.customer_id = $cid ORDER BY tc.class_time DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── Reviews ───────────────────────────────────────────────────
$reviews = $conn->query("
    SELECT content, rating, review_date FROM Review
    WHERE customer_id = $cid ORDER BY review_date DESC
")->fetch_all(MYSQLI_ASSOC);

// ── All plans ─────────────────────────────────────────────────
$all_plans = $conn->query("
    SELECT plan_id, plan_name, duration_months, price, description
    FROM MembershipPlan ORDER BY price ASC
")->fetch_all(MYSQLI_ASSOC);

// ── Invoices ──────────────────────────────────────────────────
$invoices = $conn->query("
    SELECT i.invoice_id, i.invoice_date, i.final_amount, i.status,
           GROUP_CONCAT(mp.plan_name SEPARATOR ', ') AS plans
    FROM Invoice i
    LEFT JOIN InvoiceDetail id2 ON id2.invoice_id = i.invoice_id
    LEFT JOIN MembershipPlan mp ON mp.plan_id = id2.plan_id
    WHERE i.customer_id = $cid
    GROUP BY i.invoice_id ORDER BY i.invoice_date DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── Plan progress ─────────────────────────────────────────────
$days_left = 0; $plan_pct = 0;
if ($active_plan) {
    $diff      = (new DateTime($today))->diff(new DateTime($active_plan['end_date']));
    $days_left = max(0, (int)$diff->days);
    $total_d   = max(1, (new DateTime($active_plan['start_date']))->diff(new DateTime($active_plan['end_date']))->days);
    $plan_pct  = min(100, round(($total_d - $days_left) / $total_d * 100));
}

$initials  = mb_strtoupper(mb_substr($customer['full_name'], 0, 1));
$full_name = htmlspecialchars($customer['full_name']);

// ── Alert message map ─────────────────────────────────────────
$alert_msg = ''; $alert_type = '';
$msg_map = [
    'info_updated'      => ['success', 'Thông tin cá nhân đã được cập nhật thành công!'],
    'password_changed'  => ['success', 'Mật khẩu đã được đổi thành công!'],
    'otp_sent'          => ['success', 'Mã OTP đã được gửi về email của bạn!'],
    'empty_name'        => ['danger',  'Họ và tên không được để trống!'],
    'invalid_email'     => ['danger',  'Email không hợp lệ!'],
    'db_error'          => ['danger',  'Lỗi hệ thống! Vui lòng thử lại.'],
    'empty_fields'      => ['danger',  'Vui lòng nhập đầy đủ thông tin!'],
    'wrong_old_password'=> ['danger',  'Mật khẩu hiện tại không đúng!'],
    'weak_password'     => ['danger',  'Mật khẩu mới phải có ít nhất 6 ký tự!'],
    'password_mismatch' => ['danger',  'Mật khẩu xác nhận không khớp!'],
    'no_email'          => ['danger',  'Tài khoản chưa có email. Vui lòng cập nhật email trước!'],
    'send_fail'         => ['danger',  'Không thể gửi OTP! Vui lòng thử lại.'],
    'otp_expired'       => ['danger',  'Mã OTP đã hết hạn! Vui lòng gửi lại.'],
    'invalid_otp'       => ['danger',  'Mã OTP không đúng!'],
    'not_found'         => ['danger',  'Không tìm thấy tài khoản!'],
];
$key = $_GET['error'] ?? ($_GET['success'] ?? '');
if ($key && isset($msg_map[$key])) { [$alert_type, $alert_msg] = $msg_map[$key]; }

// ── Masked email for OTP step ─────────────────────────────────
$masked_email = '';
if ($pw_step === 2 && !empty($_SESSION['chpw_email'])) {
    $e = $_SESSION['chpw_email'];
    $pts = explode('@', $e);
    $n = $pts[0]; $d = $pts[1] ?? '';
    $masked_email = substr($n, 0, 2) . str_repeat('*', max(strlen($n) - 2, 3)) . '@' . $d;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Hồ Sơ — <?= $full_name ?> | Elite Gym</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="fontawesome/css/all.min.css"/>
<link rel="stylesheet" href="Profile.css"/>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav class="nav">
  <div class="nav-inner">
    <a href="../index.php" class="nav-logo">
      <svg class="hex-logo" viewBox="0 0 44 44">
        <polygon points="22,2 40,12 40,32 22,42 4,32 4,12" fill="none" stroke="#d4a017" stroke-width="1.8"/>
        <text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" fill="#d4a017" font-size="12" font-weight="800" font-family="Barlow Condensed">EG</text>
      </svg>
      <div class="nav-brand"><span class="nb-main">ELITE</span><span class="nb-sub">GYM</span></div>
    </a>
    <div class="nav-spacer"></div>
    <a href="../index.php" class="nav-back"><i class="fas fa-arrow-left"></i> Trang chủ</a>
    <a href="../Internal/Index/Login/logout.php" class="nav-logout"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
  </div>
</nav>

<!-- ══ MAIN ══ -->
<div class="profile-wrap">

  <!-- ─── SIDEBAR ─── -->
  <aside class="sidebar">
    <div class="profile-card">
      <div class="profile-avatar"><?= $initials ?></div>
      <div class="profile-name"><?= $full_name ?></div>
      <div class="profile-username">@<?= htmlspecialchars($customer['username']) ?></div>
      <?php if ($active_plan): ?>
        <div class="profile-badge"><i class="fas fa-circle" style="font-size:.5rem"></i> Thành viên hoạt động</div>
      <?php else: ?>
        <div class="profile-badge no-plan"><i class="fas fa-circle" style="font-size:.5rem"></i> Chưa có gói tập</div>
      <?php endif; ?>
    </div>

    <?php if ($active_plan): ?>
    <div class="plan-bar">
      <div class="pb-label">Gói hiện tại</div>
      <div class="pb-name"><?= htmlspecialchars($active_plan['plan_name']) ?></div>
      <div class="pb-dates">
        <?= date('d/m/Y', strtotime($active_plan['start_date'])) ?> →
        <?= date('d/m/Y', strtotime($active_plan['end_date'])) ?>
      </div>
      <div class="pb-progress"><div class="pb-fill" style="width:<?= $plan_pct ?>%"></div></div>
      <div style="font-size:.75rem;color:var(--text3);margin-top:6px">Còn <?= $days_left ?> ngày</div>
    </div>
    <?php endif; ?>

    <div class="mini-stats">
      <div class="ms"><div class="ms-n"><?= $total_checkins ?></div><div class="ms-l">Check-in</div></div>
      <div class="ms"><div class="ms-n"><?= count($classes) ?></div><div class="ms-l">Lớp tập</div></div>
      <div class="ms"><div class="ms-n"><?= count($memberships) ?></div><div class="ms-l">Gói đã mua</div></div>
      <div class="ms"><div class="ms-n"><?= count($reviews) ?></div><div class="ms-l">Đánh giá</div></div>
    </div>
  </aside>

  <!-- ─── MAIN CONTENT ─── -->
  <main class="main-content">

    <div class="welcome-banner">
      <div class="wb-icon"><i class="fas fa-dumbbell"></i></div>
      <div class="wb-text">
        <strong>Xin chào, <?= $full_name ?>!</strong>
        <p>Quản lý thông tin, gói tập và lịch sử check-in của bạn tại đây.</p>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs">
      <button class="tab-btn <?= $active_tab==='info'    ?'active':'' ?>" data-tab="info"    onclick="switchTab('info',this)">   <i class="fas fa-user"></i> Thông tin</button>
      <button class="tab-btn <?= $active_tab==='plans'   ?'active':'' ?>" data-tab="plans"   onclick="switchTab('plans',this)">  <i class="fas fa-id-card"></i> Gói tập</button>
      <button class="tab-btn <?= $active_tab==='checkin' ?'active':'' ?>" data-tab="checkin" onclick="switchTab('checkin',this)"><i class="fas fa-calendar-check"></i> Check-in</button>
      <button class="tab-btn <?= $active_tab==='classes' ?'active':'' ?>" data-tab="classes" onclick="switchTab('classes',this)"><i class="fas fa-chalkboard-teacher"></i> Lớp tập</button>
      <button class="tab-btn <?= $active_tab==='invoices'?'active':'' ?>" data-tab="invoices"onclick="switchTab('invoices',this)"><i class="fas fa-receipt"></i> Hóa đơn</button>
    </div>


    <!-- ══════════════════ TAB: INFO ══════════════════ -->
    <div class="tab-panel <?= $active_tab==='info'?'active':'' ?>" id="tab-info">

      <!-- Alert (shown from URL param) -->
      <div id="infoAlert" class="alert alert-<?= $alert_type ?>"
           style="display:<?= $alert_msg ? 'flex' : 'none' ?>">
        <?php if ($alert_msg): ?>
          <i class="fas fa-<?= $alert_type==='success'?'check-circle':'exclamation-triangle' ?>"></i>
          <?= htmlspecialchars($alert_msg) ?>
        <?php endif; ?>
      </div>

      <div class="pcard">
        <div class="pcard-title"><i class="fas fa-user-circle"></i> Thông tin cá nhân</div>

        <!-- VIEW MODE -->
        <div class="info-grid" id="infoViewSection">
          <div class="info-item">
            <div class="ii-label">Họ và tên</div>
            <div class="ii-val"><?= $full_name ?></div>
          </div>
          <div class="info-item">
            <div class="ii-label">Tên đăng nhập</div>
            <div class="ii-val">@<?= htmlspecialchars($customer['username']) ?></div>
          </div>
          <div class="info-item">
            <div class="ii-label">Ngày sinh</div>
            <div class="ii-val <?= $customer['date_of_birth']?'':'empty' ?>">
              <?= $customer['date_of_birth'] ? date('d/m/Y', strtotime($customer['date_of_birth'])) : 'Chưa cập nhật' ?>
            </div>
          </div>
          <div class="info-item">
            <div class="ii-label">Giới tính</div>
            <div class="ii-val <?= $customer['gender']?'':'empty' ?>">
              <?php $gmap=['Male'=>'Nam','Female'=>'Nữ','Other'=>'Khác'];
              echo $customer['gender'] ? ($gmap[$customer['gender']] ?? $customer['gender']) : 'Chưa cập nhật'; ?>
            </div>
          </div>
          <div class="info-item">
            <div class="ii-label">Số điện thoại</div>
            <div class="ii-val <?= $customer['phone']?'':'empty' ?>">
              <?= $customer['phone'] ? htmlspecialchars($customer['phone']) : 'Chưa cập nhật' ?>
            </div>
          </div>
          <div class="info-item">
            <div class="ii-label">Email</div>
            <div class="ii-val <?= $customer['email']?'':'empty' ?>">
              <?= $customer['email'] ? htmlspecialchars($customer['email']) : 'Chưa cập nhật' ?>
            </div>
          </div>
          <div class="info-item" style="grid-column:1/-1">
            <div class="ii-label">Địa chỉ</div>
            <div class="ii-val <?= $customer['address']?'':'empty' ?>">
              <?= $customer['address'] ? htmlspecialchars($customer['address']) : 'Chưa cập nhật' ?>
            </div>
          </div>
          <div class="info-item">
            <div class="ii-label">Ngày đăng ký</div>
            <div class="ii-val">
              <?= $customer['registered_at'] ? date('d/m/Y', strtotime($customer['registered_at'])) : '—' ?>
            </div>
          </div>
        </div><!-- /.info-grid -->

        <!-- QR CODE CARD -->
        <div class="qr-card" id="qrCard">
          <div class="qr-card-left">
            <div class="qr-label"><i class="fas fa-qrcode"></i> Mã QR thành viên</div>
            <div class="qr-member-name"><?= $full_name ?></div>
            <div class="qr-member-id">ID: #<?= str_pad($account_id, 6, '0', STR_PAD_LEFT) ?></div>
            <div class="qr-hint">Xuất trình mã này tại quầy lễ tân để check-in nhanh</div>
          </div>
          <div class="qr-card-right">
            <div class="qr-wrap">
              <div id="qrCanvas"></div>
            </div>
          </div>
        </div>

        <!-- Action buttons (edit & change pw) -->
        <div class="info-actions" id="infoActions">
          <button class="btn-edit" id="btnEditInfo">
            <i class="fas fa-pen"></i> Chỉnh sửa thông tin
          </button>
          <button class="btn-change-pw" id="btnChangePassword">
            <i class="fas fa-lock"></i> Đổi mật khẩu
          </button>
        </div>

        <!-- EDIT FORM (hidden until "Chỉnh sửa" clicked) -->
        <div class="edit-form-section" id="editFormSection">
          <form method="POST" action="Profile_Function.php" id="editInfoForm">
            <input type="hidden" name="action" value="update_info"/>
            <div class="edit-form-grid">
              <div class="form-group">
                <label>Họ và tên <span style="color:var(--red)">*</span></label>
                <input type="text" name="full_name"
                       value="<?= htmlspecialchars($customer['full_name']) ?>"
                       placeholder="Nguyễn Văn A" required/>
              </div>
              <div class="form-group">
                <label>Ngày sinh</label>
                <input type="date" name="date_of_birth"
                       value="<?= htmlspecialchars($customer['date_of_birth'] ?? '') ?>"/>
              </div>
              <div class="form-group">
                <label>Giới tính</label>
                <select name="gender">
                  <option value="">-- Chọn --</option>
                  <option value="Male"   <?= ($customer['gender']==='Male')   ?'selected':'' ?>>Nam</option>
                  <option value="Female" <?= ($customer['gender']==='Female') ?'selected':'' ?>>Nữ</option>
                  <option value="Other"  <?= ($customer['gender']==='Other')  ?'selected':'' ?>>Khác</option>
                </select>
              </div>
              <div class="form-group">
                <label>Số điện thoại</label>
                <input type="tel" name="phone"
                       value="<?= htmlspecialchars($customer['phone'] ?? '') ?>"
                       placeholder="0912 345 678"/>
              </div>
              <div class="form-group full">
                <label>Email</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($customer['email'] ?? '') ?>"
                       placeholder="example@email.com"/>
              </div>
              <div class="form-group full">
                <label>Địa chỉ</label>
                <input type="text" name="address"
                       value="<?= htmlspecialchars($customer['address'] ?? '') ?>"
                       placeholder="123 Đường ABC, Quận 1, TP.HCM"/>
              </div>
            </div>
            <div class="form-actions-row">
              <button type="submit" class="btn-save"><i class="fas fa-check"></i> Lưu thay đổi</button>
              <button type="button" class="btn-cancel" id="btnCancelEdit"><i class="fas fa-times"></i> Hủy</button>
            </div>
          </form>
        </div><!-- /.edit-form-section -->

      </div><!-- /.pcard -->
    </div><!-- /tab-info -->


    <!-- ══════════════════ TAB: PLANS ══════════════════ -->
    <div class="tab-panel <?= $active_tab==='plans'?'active':'' ?>" id="tab-plans">
      <?php if (!empty($memberships)): ?>
      <div class="pcard">
        <div class="pcard-title"><i class="fas fa-history"></i> Lịch sử đăng ký gói tập</div>
        <table class="ptable">
          <thead><tr><th>Gói tập</th><th>Bắt đầu</th><th>Kết thúc</th><th>Trạng thái</th></tr></thead>
          <tbody>
          <?php foreach($memberships as $m): $exp = $m['end_date'] < $today; ?>
            <tr>
              <td class="td-name"><?= htmlspecialchars($m['plan_name']) ?>
                <span style="color:var(--text3);font-size:.8rem">(<?= $m['duration_months'] ?> tháng)</span></td>
              <td><?= date('d/m/Y', strtotime($m['start_date'])) ?></td>
              <td><?= date('d/m/Y', strtotime($m['end_date'])) ?></td>
              <td>
                <?php if(!$exp): ?>
                  <span class="badge badge-green"><i class="fas fa-circle" style="font-size:.4rem"></i> Đang hoạt động</span>
                <?php else: ?>
                  <span class="badge badge-red">Đã hết hạn</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <div class="pcard">
        <div class="pcard-title"><i class="fas fa-tags"></i> Các gói tập hiện có</div>
        <div class="plan-cards">
          <?php foreach($all_plans as $pl): ?>
          <div class="pc">
            <div class="pc-dur"><?= $pl['duration_months'] ?> THÁNG</div>
            <div class="pc-name"><?= htmlspecialchars($pl['plan_name']) ?></div>
            <div class="pc-price"><?= number_format($pl['price'],0,',','.') ?><span> ₫</span></div>
            <?php if($pl['description']): ?><div class="pc-desc"><?= htmlspecialchars($pl['description']) ?></div><?php endif; ?>
            <a href="#" class="pc-btn">Liên hệ đăng ký</a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>


    <!-- ══════════════════ TAB: CHECK-IN ══════════════════ -->
    <div class="tab-panel <?= $active_tab==='checkin'?'active':'' ?>" id="tab-checkin">
      <div class="pcard">
        <div class="pcard-title"><i class="fas fa-calendar-check"></i> Lịch sử check-in (10 gần nhất)</div>
        <?php if(!empty($checkins)): ?>
        <table class="ptable">
          <thead><tr><th>Ngày</th><th>Giờ vào</th><th>Giờ ra</th><th>Thời gian tập</th></tr></thead>
          <tbody>
          <?php foreach($checkins as $ci):
            $in_dt  = new DateTime($ci['check_in']);
            $out_dt = $ci['check_out'] ? new DateTime($ci['check_out']) : null;
            $dur    = $out_dt ? $in_dt->diff($out_dt) : null;
          ?>
            <tr>
              <td class="td-name"><?= $in_dt->format('d/m/Y') ?></td>
              <td><?= $in_dt->format('H:i') ?></td>
              <td><?= $out_dt ? $out_dt->format('H:i') : '<span style="color:var(--text3)">—</span>' ?></td>
              <td><?= $dur ? '<span class="badge badge-blue">'.$dur->h.'g '.$dur->i.'p</span>' : '<span style="color:var(--text3)">—</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state"><i class="fas fa-door-open"></i><p>Bạn chưa có lịch sử check-in nào.</p></div>
        <?php endif; ?>
      </div>
    </div>


    <!-- ══════════════════ TAB: CLASSES ══════════════════ -->
    <div class="tab-panel <?= $active_tab==='classes'?'active':'' ?>" id="tab-classes">
      <div class="pcard">
        <div class="pcard-title"><i class="fas fa-chalkboard-teacher"></i> Lớp tập đã đăng ký</div>
        <?php if(!empty($classes)): ?>
        <table class="ptable">
          <thead><tr><th>Lớp tập</th><th>Huấn luyện viên</th><th>Thời gian</th><th>Trạng thái</th></tr></thead>
          <tbody>
          <?php foreach($classes as $cl):
            $cdt = new DateTime($cl['class_time']);
            $upcoming = $cdt > new DateTime();
          ?>
            <tr>
              <td class="td-name"><?= htmlspecialchars($cl['class_name']) ?></td>
              <td><?= $cl['trainer_name'] ? htmlspecialchars($cl['trainer_name']) : '<span style="color:var(--text3)">—</span>' ?></td>
              <td><?= $cdt->format('d/m/Y H:i') ?></td>
              <td>
                <?= $upcoming
                  ? '<span class="badge badge-gold"><i class="fas fa-clock" style="font-size:.6rem"></i> Sắp diễn ra</span>'
                  : '<span class="badge badge-green">Đã hoàn thành</span>'
                ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state"><i class="fas fa-calendar-times"></i><p>Bạn chưa đăng ký lớp tập nào.</p></div>
        <?php endif; ?>
      </div>
    </div>


    <!-- ══════════════════ TAB: INVOICES ══════════════════ -->
    <div class="tab-panel <?= $active_tab==='invoices'?'active':'' ?>" id="tab-invoices">
      <div class="pcard">
        <div class="pcard-title"><i class="fas fa-receipt"></i> Lịch sử hóa đơn</div>
        <?php if(!empty($invoices)): ?>
        <table class="ptable">
          <thead><tr><th>#</th><th>Ngày</th><th>Gói tập</th><th>Tổng tiền</th><th>Trạng thái</th></tr></thead>
          <tbody>
          <?php foreach($invoices as $inv):
            $smap = ['Paid'=>['badge-green','Đã thanh toán'],'Pending'=>['badge-gold','Chờ thanh toán'],'Cancelled'=>['badge-red','Đã hủy']];
            [$sc,$sl] = $smap[$inv['status']] ?? ['badge-blue',$inv['status']];
          ?>
            <tr>
              <td style="color:var(--text3)">#<?= $inv['invoice_id'] ?></td>
              <td><?= $inv['invoice_date'] ? date('d/m/Y', strtotime($inv['invoice_date'])) : '—' ?></td>
              <td class="td-name"><?= $inv['plans'] ? htmlspecialchars($inv['plans']) : '<span style="color:var(--text3)">—</span>' ?></td>
              <td style="color:var(--gold);font-weight:600"><?= number_format($inv['final_amount'],0,',','.') ?>₫</td>
              <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state"><i class="fas fa-file-invoice"></i><p>Bạn chưa có hóa đơn nào.</p></div>
        <?php endif; ?>
      </div>
    </div>

  </main>
</div><!-- /.profile-wrap -->


<!-- ════════════════════════════════════════════════════════════
     MODAL — ĐỔI MẬT KHẨU
     ════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box">

    <button class="modal-close" id="modalClose" title="Đóng">
      <i class="fas fa-times"></i>
    </button>

    <!-- STEP INDICATOR -->
    <div class="pw-steps">
      <div class="pw-step-item">
        <div class="pw-step-num <?= $pw_step>=1 ? ($pw_step>1?'done':'active') : '' ?>">
          <?= $pw_step > 1 ? '<i class="fas fa-check" style="font-size:.65rem"></i>' : '1' ?>
        </div>
        <div class="pw-step-label <?= $pw_step===1?'active':'' ?>">Mật khẩu</div>
      </div>
      <div class="pw-step-connector <?= $pw_step>=2?'done':'' ?>"></div>
      <div class="pw-step-item">
        <div class="pw-step-num <?= $pw_step===2?'active':'' ?>">2</div>
        <div class="pw-step-label <?= $pw_step===2?'active':'' ?>">Xác nhận OTP</div>
      </div>
    </div>

    <!-- ── STEP 1: Nhập mật khẩu ── -->
    <div id="pwStep1" style="display:<?= $pw_step===2 ? 'none':'block' ?>">
      <div class="modal-title"><i class="fas fa-lock" style="font-size:.9em"></i>&nbsp;Đổi mật khẩu</div>
      <div class="modal-subtitle">Nhập mật khẩu hiện tại và mật khẩu mới để tiếp tục</div>

      <form method="POST" action="Profile_Function.php" id="step1Form">
        <input type="hidden" name="action" value="send_change_pw_otp"/>

        <div class="modal-form-group">
          <label>Mật khẩu hiện tại <span style="color:var(--red)">*</span></label>
          <div class="modal-input-wrap">
            <input type="password" id="old_password" name="old_password"
                   placeholder="Nhập mật khẩu hiện tại" autocomplete="current-password"/>
            <button type="button" class="toggle-pw" tabindex="-1">
              <i class="fas fa-eye-slash"></i>
            </button>
          </div>
          <div class="field-error" id="err_old_password"></div>
        </div>

        <div class="modal-form-group">
          <label>Mật khẩu mới <span style="color:var(--red)">*</span></label>
          <div class="modal-input-wrap">
            <input type="password" id="new_password" name="new_password"
                   placeholder="Tối thiểu 6 ký tự" autocomplete="new-password"/>
            <button type="button" class="toggle-pw" tabindex="-1">
              <i class="fas fa-eye-slash"></i>
            </button>
          </div>
          <div class="strength-row" id="strengthWrap">
            <div class="strength-bar-wrap"><div class="strength-bar-fill"></div></div>
            <span class="strength-text"></span>
          </div>
          <div class="field-error" id="err_new_password"></div>
        </div>

        <div class="modal-form-group">
          <label>Xác nhận mật khẩu mới <span style="color:var(--red)">*</span></label>
          <div class="modal-input-wrap">
            <input type="password" id="confirm_password" name="confirm_password"
                   placeholder="Nhập lại mật khẩu mới" autocomplete="new-password"/>
            <button type="button" class="toggle-pw" tabindex="-1">
              <i class="fas fa-eye-slash"></i>
            </button>
          </div>
          <div class="field-error" id="err_confirm_password"></div>
        </div>

        <button type="submit" class="btn-modal-submit">
          <span class="btn-label"><i class="fas fa-paper-plane"></i>&nbsp; Gửi mã OTP xác nhận</span>
          <span class="spinner"></span>
        </button>
      </form>
    </div><!-- /pwStep1 -->

    <!-- ── STEP 2: Nhập OTP ── -->
    <div id="pwStep2" style="display:<?= $pw_step===2 ? 'block':'none' ?>">
      <div class="modal-title"><i class="fas fa-shield-halved" style="font-size:.9em"></i>&nbsp;Nhập mã OTP</div>
      <div class="modal-subtitle">
        <?php if ($masked_email): ?>
          Mã đã gửi đến <strong style="color:var(--gold)"><?= htmlspecialchars($masked_email) ?></strong>
        <?php else: ?>
          Mã OTP đã được gửi về email của bạn
        <?php endif; ?>
      </div>

      <form method="POST" action="Profile_Function.php" id="step2Form">
        <input type="hidden" name="action"   value="verify_change_pw_otp"/>
        <input type="hidden" name="otp_full" id="otp_full_hidden" value=""/>

        <p class="otp-info">Nhập mã <span>6 chữ số</span> được gửi về email của bạn</p>

        <div class="otp-row">
          <input type="number" class="otp-digit" maxlength="1" min="0" max="9" inputmode="numeric" autocomplete="one-time-code"/>
          <input type="number" class="otp-digit" maxlength="1" min="0" max="9" inputmode="numeric"/>
          <input type="number" class="otp-digit" maxlength="1" min="0" max="9" inputmode="numeric"/>
          <input type="number" class="otp-digit" maxlength="1" min="0" max="9" inputmode="numeric"/>
          <input type="number" class="otp-digit" maxlength="1" min="0" max="9" inputmode="numeric"/>
          <input type="number" class="otp-digit" maxlength="1" min="0" max="9" inputmode="numeric"/>
        </div>
        <div class="field-error" id="err_otp" style="text-align:center"></div>

        <div class="otp-timer-row">
          <span class="otp-timer">Hết hạn sau: <span class="countdown" id="otpCountdown">05:00</span></span>
          <button type="button" class="otp-resend" id="resendOtpBtn" disabled onclick="resendOtp()">
            <i class="fas fa-redo"></i> Gửi lại
          </button>
        </div>

        <button type="submit" class="btn-modal-submit">
          <span class="btn-label"><i class="fas fa-check-double"></i>&nbsp; Xác nhận đổi mật khẩu</span>
          <span class="spinner"></span>
        </button>
      </form>

      <!-- Form resend tách riêng, ẨN - tránh lỗi form lồng nhau -->
      <form method="POST" action="Profile_Function.php" id="resendOtpForm" style="display:none">
        <input type="hidden" name="action" value="resend_change_pw_otp"/>
      </form>
    </div><!-- /pwStep2 -->

  </div><!-- /.modal-box -->
</div><!-- /.modal-overlay -->


<script src="Profile.js"></script>
<!-- ── QRious — cùng thư viện với hệ thống check-in ── -->
<script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
<script>
(function () {
  const qrData  = 'ELITEGYM_CID_<?= $cid ?>';
  const wrap    = document.getElementById('qrCanvas');

  // Tạo canvas thay thế div
  const canvas  = document.createElement('canvas');
  wrap.innerHTML = '';
  wrap.appendChild(canvas);

  new QRious({
    element:    canvas,
    value:      qrData,
    size:       240,          // Lớn hơn → dễ quét hơn
    level:      'H',          // Error correction cao nhất (H > Q > M > L)
    background: '#ffffff',
    foreground: '#000000',
    padding:    12,           // Quiet zone đủ rộng
  });

  // Đảm bảo hiển thị đúng kích thước
  canvas.style.width  = '100%';
  canvas.style.height = 'auto';
  canvas.style.maxWidth = '200px';
  canvas.style.display  = 'block';
  canvas.style.imageRendering = 'pixelated'; // Sắc nét trên màn Retina
})();
</script>
</body>
</html>
