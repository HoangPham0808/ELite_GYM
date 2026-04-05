<?php
/**
 * a.php — Elite Gym: Customer Registration + Review Form
 * Đặt tại: Home/ (cùng thư mục index.php)
 * Truy cập: http://[YOUR_IP]/PHP/ELite_GYM/Home/a.php
 */
session_start();

// Bypass ngrok browser warning page
header('ngrok-skip-browser-warning: 1');

require_once '../Database/db.php';

/* ══════════════════════════════════════
   STATS (GET)
══════════════════════════════════════ */
if (isset($_GET['stats'])) {
    header('Content-Type: application/json; charset=utf-8');
    $today = date('Y-m-d');
    $t = (int)$conn->query("SELECT COUNT(*) c FROM Customer")->fetch_assoc()['c'];
    $d = (int)$conn->query("SELECT COUNT(*) c FROM Customer WHERE registered_at='$today'")->fetch_assoc()['c'];
    echo json_encode(['ok'=>true,'total'=>$t,'today'=>$d]);
    exit;
}

/* ══════════════════════════════════════
   AJAX HANDLER
══════════════════════════════════════ */
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['ajax_action'];

    /* ── Lấy IP thực của server để tạo QR ── */
    if ($action === 'get_url') {
        $host = $_SERVER['HTTP_HOST'];
        $path = $_SERVER['REQUEST_URI'];
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        echo json_encode(['ok' => true, 'url' => "$proto://$host$path"]);
        exit;
    }

    /* ── Stats ── */
    if ($action === 'stats' || isset($_GET['stats'])) {
        $today = date('Y-m-d');
        $t = (int)$conn->query("SELECT COUNT(*) c FROM Customer")->fetch_assoc()['c'];
        $d = (int)$conn->query("SELECT COUNT(*) c FROM Customer WHERE registered_at='$today'")->fetch_assoc()['c'];
        echo json_encode(['ok'=>true,'total'=>$t,'today'=>$d]);
        exit;
    }

    /* ── Submit form ── */
    if ($action === 'submit') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone     = trim($_POST['phone']     ?? '');
        $email     = trim($_POST['email']     ?? '');
        $dob       = trim($_POST['dob']       ?? '');
        $gender    = $_POST['gender']         ?? 'Other';
        $rating    = (int)($_POST['rating']   ?? 0);
        $review    = trim($_POST['review']    ?? '');
        $address   = trim($_POST['address']   ?? '');

        // Validate
        if (mb_strlen($full_name) < 2)    { echo json_encode(['ok'=>false,'msg'=>'Vui lòng nhập họ tên (tối thiểu 2 ký tự).']); exit; }
        if (!preg_match('/^[0-9]{9,11}$/', $phone)) { echo json_encode(['ok'=>false,'msg'=>'Số điện thoại không hợp lệ (9-11 chữ số).']); exit; }
        if ($rating < 1 || $rating > 5)   { echo json_encode(['ok'=>false,'msg'=>'Vui lòng chọn đánh giá (1-5 sao).']); exit; }
        if (mb_strlen($review) < 5)       { echo json_encode(['ok'=>false,'msg'=>'Nhận xét tối thiểu 5 ký tự.']); exit; }

        try {
            $conn->begin_transaction();

            /* 1. Tạo Account */
            $username = 'guest_' . time() . '_' . rand(100,999);
            $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $stA = $conn->prepare("INSERT INTO Account (username, password, role) VALUES (?, ?, 'Customer')");
            $stA->bind_param('ss', $username, $password);
            $stA->execute();
            $account_id = $conn->insert_id;
            $stA->close();

            /* 2. Tạo Customer */
            $today = date('Y-m-d');
            $dob_val = $dob ?: null;
            $stC = $conn->prepare("INSERT INTO Customer (full_name, date_of_birth, gender, phone, email, registered_at, account_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stC->bind_param('ssssssi', $full_name, $dob_val, $gender, $phone, $email, $today, $account_id);
            $stC->execute();
            $customer_id = $conn->insert_id;
            $stC->close();

            /* 3. Tạo Review */
            $stR = $conn->prepare("INSERT INTO Review (customer_id, content, rating, review_date) VALUES (?, ?, ?, ?)");
            $stR->bind_param('isis', $customer_id, $review, $rating, $today);
            $stR->execute();
            $stR->close();

            $conn->commit();
            echo json_encode(['ok'=>true, 'msg'=>'Đăng ký thành công! Cảm ơn bạn đã tham gia Elite Gym.', 'customer_id'=>$customer_id]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['ok'=>false, 'msg'=>'Lỗi hệ thống: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Action không hợp lệ.']);
    exit;
}
/* ══════════════════════════════════════
   GET server URL for QR (ngrok-aware)
══════════════════════════════════════ */
// Nếu qua ngrok thì dùng X-Forwarded-Host và luôn là https
if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $host  = $_SERVER['HTTP_X_FORWARDED_HOST'];
    $proto = 'https';
} else {
    $host  = $_SERVER['HTTP_HOST'];
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
}
$path     = strtok($_SERVER['REQUEST_URI'], '?'); // bỏ query string
$form_url = "$proto://$host$path";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Đăng ký thành viên — Elite Gym</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="a.css"/>
</head>
<body>

<!-- ── NOISE TEXTURE OVERLAY ── -->
<div class="noise"></div>

<!-- ── BACKGROUND GRID ── -->
<div class="bg-grid"></div>

<!-- ══ ADMIN PANEL (chỉ hiện trên máy tính) ══ -->
<div class="admin-side" id="adminSide">
  <div class="admin-brand">
    <span class="brand-bracket">[</span>EG<span class="brand-bracket">]</span>
    <span class="brand-sub">ELITE GYM</span>
  </div>
  <p class="admin-desc">Quét mã QR để mở form đăng ký trên điện thoại</p>

  <div class="qr-frame" id="qrFrame">
    <div class="qr-corner tl"></div>
    <div class="qr-corner tr"></div>
    <div class="qr-corner bl"></div>
    <div class="qr-corner br"></div>
    <img id="qrImg" src="" alt="QR Code" class="qr-img"/>
    <div class="qr-pulse"></div>
  </div>

  <div class="url-chip" id="urlChip">
    <i class="fas fa-link"></i>
    <span id="urlText"><?= htmlspecialchars($form_url) ?></span>
  </div>

  <button class="copy-btn" id="copyBtn">
    <i class="fas fa-copy"></i> Sao chép link
  </button>

  <div class="admin-stats">
    <div class="astat">
      <span class="astat-n" id="statToday">—</span>
      <span class="astat-l">Hôm nay</span>
    </div>
    <div class="astat-div"></div>
    <div class="astat">
      <span class="astat-n" id="statTotal">—</span>
      <span class="astat-l">Tổng KH</span>
    </div>
  </div>
</div>

<!-- ══ FORM SIDE ══ -->
<div class="form-side" id="formSide">

  <!-- Mobile brand header -->
  <div class="mobile-brand">
    <span class="brand-bracket">[</span>EG<span class="brand-bracket">]</span>
    <span class="brand-sub">ELITE GYM</span>
  </div>

  <div class="form-card" id="formCard">

    <!-- Step indicator -->
    <div class="step-bar">
      <div class="step-item active" id="step1Ind">
        <div class="step-dot"><span>1</span></div>
        <div class="step-label">Thông tin</div>
      </div>
      <div class="step-line"></div>
      <div class="step-item" id="step2Ind">
        <div class="step-dot"><span>2</span></div>
        <div class="step-label">Đánh giá</div>
      </div>
      <div class="step-line"></div>
      <div class="step-item" id="step3Ind">
        <div class="step-dot"><span>3</span></div>
        <div class="step-label">Hoàn tất</div>
      </div>
    </div>

    <!-- ── STEP 1: Customer Info ── -->
    <div class="step-panel active" id="step1">
      <h2 class="panel-title">Thông tin <span>cá nhân</span></h2>
      <p class="panel-sub">Điền đầy đủ để tạo tài khoản thành viên</p>

      <div class="field-group">
        <label class="field-label">Họ và tên <span class="req">*</span></label>
        <div class="field-wrap">
          <i class="fas fa-user field-icon"></i>
          <input type="text" id="fullName" class="field-input" placeholder="Nguyễn Văn A" maxlength="150"/>
        </div>
        <span class="field-err" id="errName"></span>
      </div>

      <div class="field-row">
        <div class="field-group">
          <label class="field-label">Số điện thoại <span class="req">*</span></label>
          <div class="field-wrap">
            <i class="fas fa-phone field-icon"></i>
            <input type="tel" id="phone" class="field-input" placeholder="0912345678" maxlength="11"/>
          </div>
          <span class="field-err" id="errPhone"></span>
        </div>
        <div class="field-group">
          <label class="field-label">Ngày sinh</label>
          <div class="field-wrap">
            <i class="fas fa-calendar field-icon"></i>
            <input type="date" id="dob" class="field-input"/>
          </div>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label">Email</label>
        <div class="field-wrap">
          <i class="fas fa-envelope field-icon"></i>
          <input type="email" id="email" class="field-input" placeholder="example@email.com" maxlength="150"/>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label">Địa chỉ</label>
        <div class="field-wrap">
          <i class="fas fa-map-marker-alt field-icon"></i>
          <input type="text" id="address" class="field-input" placeholder="Số nhà, đường, phường/xã, quận/huyện..." maxlength="255"/>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label">Giới tính</label>
        <div class="gender-row">
          <label class="gender-opt">
            <input type="radio" name="gender" value="Male"/>
            <span class="gender-btn"><i class="fas fa-mars"></i> Nam</span>
          </label>
          <label class="gender-opt">
            <input type="radio" name="gender" value="Female"/>
            <span class="gender-btn"><i class="fas fa-venus"></i> Nữ</span>
          </label>
          <label class="gender-opt">
            <input type="radio" name="gender" value="Other" checked/>
            <span class="gender-btn"><i class="fas fa-genderless"></i> Khác</span>
          </label>
        </div>
      </div>

      <button class="next-btn" id="nextBtn1">
        Tiếp theo <i class="fas fa-arrow-right"></i>
      </button>
    </div>

    <!-- ── STEP 2: Review ── -->
    <div class="step-panel" id="step2">
      <h2 class="panel-title">Đánh giá <span>Elite Gym</span></h2>
      <p class="panel-sub">Chia sẻ cảm nhận của bạn về phòng tập</p>

      <div class="field-group">
        <label class="field-label">Chất lượng dịch vụ <span class="req">*</span></label>
        <div class="star-picker" id="starPicker">
          <?php for($i=1;$i<=5;$i++): ?>
          <button type="button" class="star-btn" data-val="<?=$i?>">
            <i class="fas fa-star"></i>
          </button>
          <?php endfor; ?>
        </div>
        <div class="star-labels">
          <span>Rất tệ</span><span>Tệ</span><span>Bình thường</span><span>Tốt</span><span>Xuất sắc</span>
        </div>
        <span class="field-err" id="errRating"></span>
      </div>

      <!-- Rating categories -->
      <div class="rating-cats" id="ratingCats">
        <div class="cat-item">
          <span class="cat-label"><i class="fas fa-dumbbell"></i> Thiết bị</span>
          <div class="cat-stars" data-cat="equipment">
            <?php for($i=1;$i<=5;$i++): ?><button type="button" class="cs-btn" data-v="<?=$i?>"><i class="fas fa-star"></i></button><?php endfor; ?>
          </div>
        </div>
        <div class="cat-item">
          <span class="cat-label"><i class="fas fa-broom"></i> Vệ sinh</span>
          <div class="cat-stars" data-cat="clean">
            <?php for($i=1;$i<=5;$i++): ?><button type="button" class="cs-btn" data-v="<?=$i?>"><i class="fas fa-star"></i></button><?php endfor; ?>
          </div>
        </div>
        <div class="cat-item">
          <span class="cat-label"><i class="fas fa-users"></i> Nhân viên</span>
          <div class="cat-stars" data-cat="staff">
            <?php for($i=1;$i<=5;$i++): ?><button type="button" class="cs-btn" data-v="<?=$i?>"><i class="fas fa-star"></i></button><?php endfor; ?>
          </div>
        </div>
        <div class="cat-item">
          <span class="cat-label"><i class="fas fa-dollar-sign"></i> Giá cả</span>
          <div class="cat-stars" data-cat="price">
            <?php for($i=1;$i<=5;$i++): ?><button type="button" class="cs-btn" data-v="<?=$i?>"><i class="fas fa-star"></i></button><?php endfor; ?>
          </div>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label">Nhận xét chi tiết <span class="req">*</span></label>
        <div class="textarea-wrap">
          <textarea id="reviewText" class="field-textarea" rows="4" maxlength="500"
            placeholder="Chia sẻ trải nghiệm của bạn tại Elite Gym…"></textarea>
          <span class="char-count"><span id="charLeft">500</span> ký tự</span>
        </div>
        <span class="field-err" id="errReview"></span>
      </div>

      <div class="btn-row">
        <button class="back-btn" id="backBtn2">
          <i class="fas fa-arrow-left"></i> Quay lại
        </button>
        <button class="next-btn" id="nextBtn2">
          Gửi đăng ký <i class="fas fa-paper-plane"></i>
        </button>
      </div>
    </div>

    <!-- ── STEP 3: Success ── -->
    <div class="step-panel" id="step3">
      <div class="success-wrap">
        <div class="success-icon">
          <svg viewBox="0 0 80 80" fill="none">
            <circle cx="40" cy="40" r="38" stroke="var(--accent)" stroke-width="2" class="check-circle"/>
            <polyline points="22,40 34,52 58,28" stroke="var(--accent)" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" class="check-mark"/>
          </svg>
        </div>
        <h2 class="success-title">Chào mừng<br/><span id="successName">thành viên mới!</span></h2>
        <p class="success-msg">Thông tin của bạn đã được lưu thành công. Nhân viên lễ tân sẽ hỗ trợ bạn hoàn tất thủ tục đăng ký gói tập.</p>
        <div class="success-cid" id="successCid"></div>
        <button class="next-btn" id="resetBtn" style="margin-top:24px">
          <i class="fas fa-plus"></i> Đăng ký mới
        </button>
      </div>
    </div>

  </div><!-- /.form-card -->
</div><!-- /.form-side -->

<!-- Loading overlay -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner">
    <div class="sp-ring"></div>
    <span class="sp-text">ĐANG GỬI</span>
  </div>
</div>

<!-- QR lib -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  const FORM_URL = <?= json_encode($form_url) ?>;
</script>
<script src="a.js"></script>
</body>
</html>
