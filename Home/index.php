<?php
ob_start();
session_start();

// ── Detect logged-in customer ────────────────────────────────
$is_logged_in  = isset($_SESSION['account_id']);
$is_customer   = $is_logged_in && ($_SESSION['role'] ?? '') === 'Customer';
$customer_name = $is_customer ? htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? '') : '';

require_once '../Database/db.php';

// ── Data always needed ────────────────────────────────────────
$total_members = $conn->query("SELECT COUNT(*) AS c FROM Customer")->fetch_assoc()['c'] ?? 0;
$total_classes = $conn->query("SELECT COUNT(*) AS c FROM TrainingClass")->fetch_assoc()['c'] ?? 0;
$total_plans   = $conn->query("SELECT COUNT(*) AS c FROM MembershipPlan")->fetch_assoc()['c'] ?? 0;

$plans = $conn->query("
    SELECT plan_id, plan_name, duration_months, price, description
    FROM MembershipPlan ORDER BY price ASC LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

$reviews = $conn->query("
    SELECT r.content, r.rating, c.full_name
    FROM Review r JOIN Customer c ON r.customer_id = c.customer_id
    WHERE r.rating >= 4 ORDER BY r.review_date DESC LIMIT 3
")->fetch_all(MYSQLI_ASSOC);
// ── Đọc ảnh slideshow từ DB ──────────────────────────────────────
$slide_imgs = $conn->query("
    SELECT image_id, image_name, file_url
    FROM landing_images
    WHERE is_active = 1
    ORDER BY sort_order ASC, image_id ASC
")->fetch_all(MYSQLI_ASSOC);
// ── Extra data for CUSTOMER view ─────────────────────────────
$cus_active_plan = null;
$cus_checkins    = [];
$cus_total_ci    = 0;
$cus_upcoming    = [];
$cus_days_left   = 0;
$cus_plan_pct    = 0;
$cid             = 0;

if ($is_customer) {
    $account_id = (int)$_SESSION['account_id'];
    $r = $conn->prepare("SELECT customer_id FROM Customer WHERE account_id = ? LIMIT 1");
    $r->bind_param("i", $account_id);
    $r->execute();
    $row = $r->get_result()->fetch_assoc();
    $r->close();
    $cid = (int)($row['customer_id'] ?? 0);

    if ($cid) {
        $today = date('Y-m-d');

        $mems = $conn->query("
            SELECT mr.start_date, mr.end_date, mp.plan_name, mp.duration_months, mp.price
            FROM MembershipRegistration mr
            JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
            WHERE mr.customer_id = $cid ORDER BY mr.end_date DESC
        ")->fetch_all(MYSQLI_ASSOC);
        foreach ($mems as $m) {
            if ($m['end_date'] >= $today) { $cus_active_plan = $m; break; }
        }
        if ($cus_active_plan) {
            $total_days    = max(1, (new DateTime($cus_active_plan['start_date']))->diff(new DateTime($cus_active_plan['end_date']))->days);
            $cus_days_left = max(0, (new DateTime($today))->diff(new DateTime($cus_active_plan['end_date']))->days);
            $cus_plan_pct  = min(100, round(($total_days - $cus_days_left) / $total_days * 100));
        }
        $cus_checkins  = $conn->query("SELECT check_in, check_out FROM CheckInHistory WHERE customer_id=$cid ORDER BY check_in DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
        $cus_total_ci  = $conn->query("SELECT COUNT(*) AS c FROM CheckInHistory WHERE customer_id=$cid")->fetch_assoc()['c'] ?? 0;
        $cus_upcoming  = $conn->query("
            SELECT tc.class_name, tc.class_time, e.full_name AS trainer
            FROM ClassRegistration cr
            JOIN TrainingClass tc ON tc.class_id = cr.class_id
            LEFT JOIN Employee e ON e.employee_id = tc.trainer_id
            WHERE cr.customer_id=$cid AND tc.class_time >= NOW()
            ORDER BY tc.class_time ASC LIMIT 3
        ")->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Elite Gym — <?= $is_customer ? 'Chào mừng, '.$customer_name : 'Phòng Tập Đẳng Cấp' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<!-- Font Awesome 6.5.2 — CDN (không dùng integrity để tránh hash mismatch) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
      crossorigin="anonymous" referrerpolicy="no-referrer" id="fa-cdn"/>
<script>
  // Fallback: nếu CDN lỗi (mất mạng) → load file local
  document.getElementById('fa-cdn').onerror = function(){
    var lnk = document.createElement('link');
    lnk.rel  = 'stylesheet';
    lnk.href = 'fontawesome/css/all.min.css';
    document.head.appendChild(lnk);
  };
</script>
<link rel="stylesheet" href="Landing.css"/>
 <link rel="stylesheet" href="Image_landing_display.css"/>
</head>
<body>

<div class="cursor-glow" id="cursorGlow"></div>

<!-- ══ NAVBAR ══ -->
<header class="nav" id="nav">
  <div class="nav-inner">
    <a href="index.php" class="nav-logo">
      <svg class="hex-logo" viewBox="0 0 44 44">
        <polygon points="22,2 40,12 40,32 22,42 4,32 4,12" fill="none" stroke="#d4a017" stroke-width="1.8"/>
        <text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" fill="#d4a017" font-size="12" font-weight="800" font-family="Barlow Condensed">EG</text>
      </svg>
      <div class="nav-brand"><span class="nb-main">ELITE</span><span class="nb-sub">GYM</span></div>
    </a>
    <nav class="nav-links">
      <?php if ($is_customer): ?>
        <a href="#membership">Gói tập</a>
        <a href="#schedule">Lớp tập</a>
        <a href="#checkin">Hướng dẫn</a>
        <a href="#reviews">Đánh giá</a>
      <?php else: ?>
        <a href="#features">Tính năng</a>
        <a href="#plans">Gói tập</a>
        <a href="#how">Cách hoạt động</a>
        <a href="#reviews">Đánh giá</a>
      <?php endif; ?>
    </nav>
    <div class="nav-actions">
      <?php if ($is_customer): ?>
        <div class="nav-user-wrap" id="navUserWrap">
          <button class="nav-user-btn" id="navUserBtn">
            <div class="nav-avatar"><?= mb_strtoupper(mb_substr($customer_name, 0, 1)) ?></div>
            <span class="nav-user-name"><?= $customer_name ?></span>
            <i class="fas fa-chevron-down nav-chevron"></i>
          </button>
          <div class="nav-dropdown" id="navDropdown">
            <a href="Profile/Profile.php"><i class="fas fa-user-circle"></i> Hồ sơ của tôi</a>
            <a href="Profile/Profile.php?tab=checkin"><i class="fas fa-calendar-check"></i> Lịch sử check-in</a>
            <a href="Profile/Profile.php?tab=plans"><i class="fas fa-id-card"></i> Gói tập của tôi</a>
            <div style="border-top:1px solid rgba(255,255,255,.07);margin:4px 0"></div>
            <a href="../Internal/Index/Login/logout.php" class="nd-logout"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
          </div>
        </div>
      <?php else: ?>
        <a href="../Internal/Index/Login/Login.php"       class="nav-login">Đăng nhập</a>
        <a href="../Internal/Index/Register/Register.php" class="nav-cta"><i class="fas fa-bolt"></i>&nbsp;Tham gia ngay</a>
      <?php endif; ?>
    </div>
    <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
  </div>
  <div class="mobile-menu" id="mobileMenu">
    <?php if ($is_customer): ?>
      <a href="#membership">Gói tập</a>
      <a href="#schedule">Lớp tập</a>
      <a href="#checkin">Hướng dẫn</a>
      <a href="#reviews">Đánh giá</a>
      <a href="Profile/Profile.php" class="mm-cta"><i class="fas fa-user-circle"></i> Hồ sơ của tôi</a>
      <a href="../Internal/Index/Login/logout.php" class="mm-login" style="color:#f87171"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
    <?php else: ?>
      <a href="#features">Tính năng</a>
      <a href="#plans">Gói tập</a>
      <a href="#how">Cách hoạt động</a>
      <a href="#reviews">Đánh giá</a>
      <a href="../Internal/Index/Login/Login.php"       class="mm-login">Đăng nhập</a>
      <a href="../Internal/Index/Register/Register.php" class="mm-cta">Tham gia ngay</a>
    <?php endif; ?>
  </div>
</header>

<!-- ══════════════════════════════════════════
     HERO
     ══════════════════════════════════════════ -->
<?php if ($is_customer): ?>
<!-- ── HERO: CUSTOMER ── -->
<section class="hero" id="home">
  <div class="hero-bg"><div class="hex-grid-bg"></div><div class="radial-top"></div><div class="radial-right"></div></div>
  <div class="hex-deco">
    <svg class="hd hd-1" viewBox="0 0 100 100"><polygon points="50,4 93,27 93,73 50,96 7,73 7,27" fill="none" stroke="rgba(212,160,23,.1)" stroke-width="1.5"/></svg>
    <svg class="hd hd-2" viewBox="0 0 100 100"><polygon points="50,4 93,27 93,73 50,96 7,73 7,27" fill="none" stroke="rgba(212,160,23,.06)" stroke-width="1"/></svg>
    <svg class="hd hd-3" viewBox="0 0 100 100"><polygon points="50,4 93,27 93,73 50,96 7,73 7,27" fill="none" stroke="rgba(212,160,23,.04)" stroke-width="1"/></svg>
  </div>
  <div class="hero-left">
    <div class="hero-kicker">
      <span class="kicker-bar"></span>
      <span>Thành viên Elite Gym</span>
      <span class="kicker-dot">✦</span>
      <span><?= date('d/m/Y') ?></span>
    </div>
    <h1 class="hero-title">
      <span class="ht" style="--d:.05s">CHÀO MỪNG</span>
      <span class="ht gold" style="--d:.18s"><?= mb_strtoupper(explode(' ', trim($customer_name))[count(explode(' ', trim($customer_name)))-1]) ?>,</span>
      <span class="ht" style="--d:.31s">SẴN SÀNG CHƯA?</span>
    </h1>
    <p class="hero-desc">Theo dõi gói tập, lịch lớp và lịch sử check-in của bạn — tất cả trong một nơi duy nhất.</p>
    <div class="hero-btns">
      <a href="Profile/Profile.php" class="btn-gold"><i class="fas fa-user-circle"></i> Xem hồ sơ của tôi</a>
      <a href="#membership" class="btn-ghost-gold">Xem gói tập <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="hero-stats">
      <div class="hs"><div class="hs-n" data-target="<?= $cus_total_ci ?>">0</div><div class="hs-l">Check-in</div></div>
      <div class="hs-sep"></div>
      <div class="hs"><div class="hs-n" data-target="<?= count($cus_upcoming) ?>">0</div><div class="hs-l">Lớp sắp tới</div></div>
      <div class="hs-sep"></div>
      <div class="hs"><div class="hs-n" data-target="<?= $cus_days_left ?>">0</div><div class="hs-l">Ngày còn lại</div></div>
      <div class="hs-sep"></div>
      <div class="hs"><div class="hs-n" data-target="24">0</div><div class="hs-l">Giờ/ngày</div></div>
    </div>
  </div>
  <!-- Customer card -->
  <div class="hero-right">
    <div class="dashboard-card">
      <div class="dc-header">
        <div class="dc-dots"><i></i><i></i><i></i></div>
        <span class="dc-title">HỒ SƠ THÀNH VIÊN</span>
        <?php if ($cus_active_plan): ?>
          <span class="dc-live" style="background:rgba(74,222,128,.1);color:#4ade80;border-color:rgba(74,222,128,.25)"><i class="fas fa-circle" style="font-size:.45rem"></i> ĐANG HOẠT ĐỘNG</span>
        <?php else: ?>
          <span class="dc-live" style="background:rgba(248,113,113,.1);color:#f87171;border-color:rgba(248,113,113,.25)"><i class="fas fa-circle" style="font-size:.45rem"></i> CHƯA CÓ GÓI</span>
        <?php endif; ?>
      </div>
      <div class="dc-body">
        <!-- Identity -->
        <div style="display:flex;align-items:center;gap:12px;padding:4px 0 14px;border-bottom:1px solid rgba(255,255,255,.07)">
          <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#d4a017,#e8b82a);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:1.1rem;color:#0a0a0a;flex-shrink:0">
            <?= mb_strtoupper(mb_substr($customer_name, 0, 1)) ?>
          </div>
          <div>
            <div style="font-weight:700;font-size:.95rem;color:#e8e8e8"><?= $customer_name ?></div>
            <div style="font-size:.75rem;color:#6b7280">Thành viên Elite Gym</div>
          </div>
        </div>
        <!-- Plan progress -->
        <?php if ($cus_active_plan): ?>
        <div style="margin:14px 0 10px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-size:.78rem;font-weight:600;color:#d4a017"><?= htmlspecialchars($cus_active_plan['plan_name']) ?></span>
            <span style="font-size:.72rem;color:#6b7280">Còn <?= $cus_days_left ?> ngày</span>
          </div>
          <div style="height:5px;background:rgba(255,255,255,.08);border-radius:99px;overflow:hidden">
            <div style="width:<?= $cus_plan_pct ?>%;height:100%;background:linear-gradient(90deg,#d4a017,#e8b82a);border-radius:99px"></div>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:5px;font-size:.7rem;color:#4b5563">
            <span><?= date('d/m/Y', strtotime($cus_active_plan['start_date'])) ?></span>
            <span><?= date('d/m/Y', strtotime($cus_active_plan['end_date'])) ?></span>
          </div>
        </div>
        <?php else: ?>
        <div style="margin:14px 0;padding:12px;background:rgba(248,113,113,.06);border:1px solid rgba(248,113,113,.15);border-radius:8px;text-align:center">
          <div style="font-size:.8rem;color:#f87171;font-weight:600">Bạn chưa có gói tập nào</div>
          <a href="#membership" style="font-size:.75rem;color:#d4a017;text-decoration:none;margin-top:4px;display:block">Xem các gói tập →</a>
        </div>
        <?php endif; ?>
        <!-- Recent check-ins -->
        <div class="dc-recent">
          <div class="dcr-head">Check-in gần đây</div>
          <?php if (!empty($cus_checkins)): ?>
            <?php foreach($cus_checkins as $ci): $dt = new DateTime($ci['check_in']); ?>
            <div class="dcr-row">
              <div class="dcr-av" style="background:rgba(96,165,250,.15);color:#60a5fa"><i class="fas fa-door-open" style="font-size:.65rem"></i></div>
              <span><?= $dt->format('d/m/Y') ?></span>
              <span class="dcr-date"><?= $dt->format('H:i') ?></span>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="dcr-empty">Chưa có lịch sử check-in</div>
          <?php endif; ?>
        </div>
        <!-- Upcoming classes -->
        <?php if (!empty($cus_upcoming)): ?>
        <div class="dc-recent" style="margin-top:10px">
          <div class="dcr-head">Lớp tập sắp tới</div>
          <?php foreach($cus_upcoming as $cl): $cdt = new DateTime($cl['class_time']); ?>
          <div class="dcr-row">
            <div class="dcr-av" style="background:rgba(212,160,23,.15);color:#d4a017"><i class="fas fa-dumbbell" style="font-size:.6rem"></i></div>
            <span style="flex:1;font-size:.8rem"><?= htmlspecialchars($cl['class_name']) ?></span>
            <span class="dcr-date"><?= $cdt->format('d/m H:i') ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <a href="#membership" class="scroll-cue"><i class="fas fa-chevron-down"></i></a>
</section>

<?php else: ?>
<!-- ── HERO: GUEST ── -->
<section class="hero" id="home">
  <div class="hero-bg"><div class="hex-grid-bg"></div><div class="radial-top"></div><div class="radial-right"></div></div>
  <div class="hex-deco">
    <svg class="hd hd-1" viewBox="0 0 100 100"><polygon points="50,4 93,27 93,73 50,96 7,73 7,27" fill="none" stroke="rgba(212,160,23,.1)" stroke-width="1.5"/></svg>
    <svg class="hd hd-2" viewBox="0 0 100 100"><polygon points="50,4 93,27 93,73 50,96 7,73 7,27" fill="none" stroke="rgba(212,160,23,.06)" stroke-width="1"/></svg>
    <svg class="hd hd-3" viewBox="0 0 100 100"><polygon points="50,4 93,27 93,73 50,96 7,73 7,27" fill="none" stroke="rgba(212,160,23,.04)" stroke-width="1"/></svg>
  </div>
  <div class="hero-left">
    <div class="hero-kicker">
      <span class="kicker-bar"></span>
      <span>Phòng tập cao cấp</span>
      <span class="kicker-dot">✦</span>
      <span>Elite Standard 2026</span>
    </div>
    <h1 class="hero-title">
      <span class="ht" style="--d:.05s">NÂNG TẦM</span>
      <span class="ht gold" style="--d:.18s">ĐẲNG CẤP</span>
      <span class="ht" style="--d:.31s">LUYỆN TẬP.</span>
    </h1>
    <p class="hero-desc">Phòng gym cao cấp với lớp tập đa dạng, HLV chuyên nghiệp và hệ thống theo dõi thành viên thông minh — trải nghiệm đẳng cấp thật sự.</p>
    <div class="hero-btns">
      <a href="../Internal/Index/Login/Login.php"       class="btn-gold"><i class="fas fa-sign-in-alt"></i> Đăng nhập ngay</a>
      <a href="../Internal/Index/Register/Register.php" class="btn-ghost-gold">Tạo tài khoản miễn phí <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="hero-stats">
      <div class="hs"><div class="hs-n" data-target="<?= $total_members ?>">0</div><div class="hs-l">Thành viên</div></div>
      <div class="hs-sep"></div>
      <div class="hs"><div class="hs-n" data-target="<?= $total_classes ?>">0</div><div class="hs-l">Lớp tập</div></div>
      <div class="hs-sep"></div>
      <div class="hs"><div class="hs-n" data-target="<?= $total_plans ?>">0</div><div class="hs-l">Gói tập</div></div>
      <div class="hs-sep"></div>
      <div class="hs"><div class="hs-n" data-target="24">0</div><div class="hs-l">Giờ/ngày</div></div>
    </div>
  </div>
  <div class="hero-right">
    <div class="dashboard-card">
      <div class="dc-header">
        <div class="dc-dots"><i></i><i></i><i></i></div>
        <span class="dc-title">ELITE GYM</span>
        <span class="dc-live"><i class="fas fa-circle"></i> LIVE</span>
      </div>
      <div class="dc-body">
        <div class="dc-stats">
          <div class="dcs">
            <div class="dcs-icon" style="--c:rgba(212,160,23,.2);--cc:#d4a017"><i class="fas fa-users"></i></div>
            <div><div class="dcs-v"><?= $total_members ?></div><div class="dcs-l">Thành viên</div></div>
          </div>
          <div class="dcs">
            <div class="dcs-icon" style="--c:rgba(59,130,246,.2);--cc:#60a5fa"><i class="fas fa-calendar-check"></i></div>
            <div><div class="dcs-v"><?= $total_classes ?></div><div class="dcs-l">Lớp tập</div></div>
          </div>
        </div>
        <div class="dc-chart">
          <div class="dcc-lbl">Hoạt động hàng tuần</div>
          <div class="dcc-bars">
            <?php
            $bvals = [45,70,55,90,60,80,100];
            $blbls = ['T2','T3','T4','T5','T6','T7','CN'];
            foreach($bvals as $bi=>$bv):
            ?>
            <div class="dcc-col">
              <div class="dcc-bar" style="--h:<?= $bv ?>%"></div>
              <span><?= $blbls[$bi] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="dc-recent">
          <div class="dcr-head">Gói tập nổi bật</div>
          <?php foreach(array_slice($plans,0,3) as $pl): ?>
          <div class="dcr-row">
            <div class="dcr-av" style="background:rgba(212,160,23,.15);color:#d4a017"><i class="fas fa-star" style="font-size:.6rem"></i></div>
            <span><?= htmlspecialchars($pl['plan_name']) ?></span>
            <span class="dcr-date"><?= number_format($pl['price']/1000000,0)?>tr</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <a href="#features" class="scroll-cue"><i class="fas fa-chevron-down"></i></a>
</section>
<?php endif; ?>

<!-- ══ TICKER ══ -->
<div class="ticker-wrap">
  <div class="ticker-track">
    <?php for($i=0;$i<4;$i++): ?>
    <?php if ($is_customer): ?>
    <span>GÓI TẬP CAO CẤP</span><em>✦</em>
    <span>LỚP TẬP ĐA DẠNG</span><em>✦</em>
    <span>CHECK-IN HÀNG NGÀY</span><em>✦</em>
    <span>HLV CHUYÊN NGHIỆP</span><em>✦</em>
    <span>TIẾN BỘ MỖI NGÀY</span><em>✦</em>
    <span>THÀNH VIÊN ELITE</span><em>✦</em>
    <?php else: ?>
    <span>QUẢN LÝ THÀNH VIÊN</span><em>✦</em>
    <span>LỚP TẬP CAO CẤP</span><em>✦</em>
    <span>CHECK-IN THÔNG MINH</span><em>✦</em>
    <span>BÁO CÁO THỐNG KÊ</span><em>✦</em>
    <span>THIẾT BỊ HIỆN ĐẠI</span><em>✦</em>
    <span>HLV CHUYÊN NGHIỆP</span><em>✦</em>
    <?php endif; ?>
    <?php endfor; ?>
  </div>
</div>

<?php if (!empty($slide_imgs)): ?>
<section class="gallery-section" id="gallery">

  <!-- Tiêu đề section vẫn theo .wrap (đồng bộ với các section khác) -->
  <div class="wrap">
    <div class="sec-head reveal">
      <div class="eyebrow"><span></span>Không gian tập luyện</div>
      <h2>Khám phá <span>Elite Gym</span></h2>
    </div>
  </div>

  <!-- Slideshow dùng .gallery-inner: padding 5% hai bên, co giãn theo cửa sổ -->
  <div class="gallery-inner">

    <div class="slideshow-wrap reveal" id="slideshowWrap">

      <!-- Progress bar -->
      <div class="slide-progress">
        <div class="slide-progress-bar" id="slideProgressBar"></div>
      </div>

      <!-- Counter -->
      <div class="slide-counter">
        <span id="slideCur">1</span> / <?= count($slide_imgs) ?>
      </div>

      <!-- Autoplay toggle -->
      <button class="slide-autoplay-btn" id="autoplayBtn" title="Tạm dừng / Phát">
        <i class="fas fa-pause" id="autoplayIcon"></i>
      </button>

      <!-- Slides -->
      <?php foreach($slide_imgs as $si => $img): ?>
      <div class="slide <?= $si === 0 ? 'active' : '' ?>" data-index="<?= $si ?>">
        <img src="<?= htmlspecialchars($img['file_url']) ?>"
             alt="<?= htmlspecialchars($img['image_name']) ?>"
             loading="<?= $si < 2 ? 'eager' : 'lazy' ?>"/>
        <div class="slide-caption">
          <div class="sc-label">
            <i class="fas fa-circle" style="font-size:.4rem"></i> ELITE GYM
          </div>
          <div class="sc-title"><?= htmlspecialchars($img['image_name']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Arrow controls -->
      <button class="slide-prev" id="slidePrev"><i class="fas fa-chevron-left"></i></button>
      <button class="slide-next" id="slideNext"><i class="fas fa-chevron-right"></i></button>

      <!-- Dots -->
      <div class="slide-dots" id="slideDots">
        <?php foreach($slide_imgs as $si => $img): ?>
        <button class="dot <?= $si === 0 ? 'active' : '' ?>" data-i="<?= $si ?>"></button>
        <?php endforeach; ?>
      </div>

    </div><!-- /slideshow-wrap -->

    <!-- Thumbnails -->
    <?php if (count($slide_imgs) > 1): ?>
    <div class="slide-thumbs" id="slideThumbs">
      <?php foreach($slide_imgs as $si => $img): ?>
      <div class="slide-thumb <?= $si === 0 ? 'active' : '' ?>" data-i="<?= $si ?>">
        <img src="<?= htmlspecialchars($img['file_url']) ?>"
             alt="<?= htmlspecialchars($img['image_name']) ?>"
             loading="lazy"/>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div><!-- /gallery-inner -->

</section>
 
<!-- ══ SLIDESHOW JS ══ -->
<script>
(function () {
  const wrap = document.getElementById('slideshowWrap');
  if (!wrap) return;
 
  const slides     = [...wrap.querySelectorAll('.slide')];
  const dots       = [...document.querySelectorAll('#slideDots .dot')];
  const thumbs     = [...document.querySelectorAll('#slideThumbs .slide-thumb')];
  const prevBtn    = document.getElementById('slidePrev');
  const nextBtn    = document.getElementById('slideNext');
  const autoBtn    = document.getElementById('autoplayBtn');
  const autoIcon   = document.getElementById('autoplayIcon');
  const curEl      = document.getElementById('slideCur');
  const progBar    = document.getElementById('slideProgressBar');
 
  const INTERVAL   = 4500;  // ms mỗi slide
  const TRANS      = 900;   // ms CSS transition
 
  let cur     = 0;
  let playing = true;
  let timer   = null;
  let pTimer  = null;
 
  /* ── Chuyển slide ── */
  function goTo(idx) {
    if (idx === cur) return;
    slides[cur].classList.remove('active');
    slides[cur].classList.add('prev');
    dots[cur]?.classList.remove('active');
    thumbs[cur]?.classList.remove('active');
    const old = cur;
    setTimeout(() => slides[old]?.classList.remove('prev'), TRANS);
 
    cur = ((idx % slides.length) + slides.length) % slides.length;
    slides[cur].classList.remove('prev');
    slides[cur].classList.add('active');
    dots[cur]?.classList.add('active');
    thumbs[cur]?.classList.add('active');
    if (curEl) curEl.textContent = cur + 1;
    thumbs[cur]?.scrollIntoView({ behavior:'smooth', block:'nearest', inline:'center' });
    resetProg();
  }
 
  /* ── Progress bar ── */
  function resetProg() {
    if (!progBar) return;
    progBar.style.transition = 'none';
    progBar.style.width = '0%';
    clearTimeout(pTimer);
    if (playing) {
      pTimer = setTimeout(() => {
        progBar.style.transition = `width ${INTERVAL}ms linear`;
        progBar.style.width = '100%';
      }, 50);
    }
  }
 
  /* ── Autoplay ── */
  function play() {
    clearInterval(timer);
    timer   = setInterval(() => goTo(cur + 1), INTERVAL);
    playing = true;
    resetProg();
    if (autoIcon) autoIcon.className = 'fas fa-pause';
    autoBtn?.classList.remove('paused');
  }
  function pause() {
    clearInterval(timer); clearTimeout(pTimer);
    playing = false;
    if (progBar) progBar.style.transition = 'none';
    if (autoIcon) autoIcon.className = 'fas fa-play';
    autoBtn?.classList.add('paused');
  }
 
  /* ── Bind controls ── */
  prevBtn?.addEventListener('click', () => { goTo(cur - 1); if (playing) { pause(); play(); } });
  nextBtn?.addEventListener('click', () => { goTo(cur + 1); if (playing) { pause(); play(); } });
  autoBtn?.addEventListener('click', () => playing ? pause() : play());
 
  dots.forEach(d => d.addEventListener('click', () => { goTo(+d.dataset.i); if (playing) { pause(); play(); } }));
  thumbs.forEach(t => t.addEventListener('click', () => { goTo(+t.dataset.i); if (playing) { pause(); play(); } }));
 
  /* ── Swipe (mobile) ── */
  let tx = null;
  wrap.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, { passive:true });
  wrap.addEventListener('touchend',   e => {
    if (tx === null) return;
    const dx = e.changedTouches[0].clientX - tx;
    if (Math.abs(dx) > 42) goTo(dx < 0 ? cur + 1 : cur - 1);
    tx = null;
    if (playing) { pause(); play(); }
  });
 
  /* ── Keyboard ── */
  document.addEventListener('keydown', e => {
    if (document.activeElement?.tagName === 'INPUT') return;
    if (e.key === 'ArrowLeft')  goTo(cur - 1);
    if (e.key === 'ArrowRight') goTo(cur + 1);
    if (e.key === ' ')          playing ? pause() : play();
  });
 
  /* ── Pause on hover ── */
  wrap.addEventListener('mouseenter', pause);
  wrap.addEventListener('mouseleave', () => { if (!autoBtn?.classList.contains('paused')) play(); });
 
  /* ── Start ── */
  if (slides.length > 1) play();
  else resetProg();
})();
</script>
 
<?php endif; /* end if slide_imgs not empty */ ?>
<!-- ══════════════════════════════════════════
     MAIN BODY SECTIONS
     ══════════════════════════════════════════ -->

<?php if ($is_customer): ?>
<!-- ════ CUSTOMER BODY ════ -->

<!-- Membership status -->
<section class="features" id="membership">
  <div class="wrap">
    <div class="sec-head reveal">
      <div class="eyebrow"><span></span>Thành viên của bạn</div>
      <h2>Tình trạng <span>gói tập</span> hiện tại</h2>
    </div>
    <div class="feat-grid">
      <div class="fc fc-wide reveal">
        <div class="fc-icon" style="--c:rgba(212,160,23,.15);--t:#d4a017"><i class="fas fa-id-card"></i></div>
        <?php if ($cus_active_plan): ?>
          <h3><?= htmlspecialchars($cus_active_plan['plan_name']) ?></h3>
          <p>Gói tập đang hoạt động — còn <strong style="color:#d4a017"><?= $cus_days_left ?> ngày</strong> (hết hạn <?= date('d/m/Y', strtotime($cus_active_plan['end_date'])) ?>).</p>
          <div style="height:6px;background:rgba(255,255,255,.07);border-radius:99px;margin:12px 0 6px;overflow:hidden">
            <div style="width:<?= $cus_plan_pct ?>%;height:100%;background:linear-gradient(90deg,#d4a017,#e8b82a);border-radius:99px"></div>
          </div>
          <div class="fc-chips"><span><?= $cus_active_plan['duration_months'] ?> tháng</span><span><?= number_format($cus_active_plan['price'],0,',','.')?>₫</span><span style="color:#4ade80">Đang hoạt động</span></div>
        <?php else: ?>
          <h3>Chưa có gói tập</h3>
          <p>Bạn chưa đăng ký gói tập nào. Hãy liên hệ lễ tân để được tư vấn và đăng ký.</p>
          <div class="fc-chips"><span>Xem gói bên dưới</span></div>
        <?php endif; ?>
        <a href="Profile/Profile.php?tab=plans" class="fc-arr" style="text-decoration:none"><i class="fas fa-arrow-up-right-from-square"></i></a>
      </div>

      <div class="fc reveal">
        <div class="fc-icon" style="--c:rgba(34,197,94,.15);--t:#4ade80"><i class="fas fa-calendar-check"></i></div>
        <h3>Lịch sử check-in</h3>
        <p>Tổng cộng <strong style="color:#4ade80"><?= $cus_total_ci ?> lần</strong> check-in. Hãy duy trì thói quen tập luyện đều đặn mỗi ngày!</p>
        <div class="fc-chips"><span><?= $cus_total_ci ?> lần tổng cộng</span></div>
        <a href="Profile/Profile.php?tab=checkin" class="fc-arr" style="text-decoration:none"><i class="fas fa-arrow-up-right-from-square"></i></a>
      </div>

      <div class="fc reveal">
        <div class="fc-icon" style="--c:rgba(59,130,246,.15);--t:#60a5fa"><i class="fas fa-chalkboard-teacher"></i></div>
        <h3>Lớp tập sắp tới</h3>
        <?php if (!empty($cus_upcoming)): ?>
          <p>Bạn có <strong style="color:#60a5fa"><?= count($cus_upcoming) ?> lớp</strong> sắp diễn ra. Đừng bỏ lỡ!</p>
          <div class="fc-chips">
            <?php foreach($cus_upcoming as $cl): ?>
              <span><?= htmlspecialchars($cl['class_name']) ?> — <?= (new DateTime($cl['class_time']))->format('d/m H:i') ?></span>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p>Chưa có lớp nào sắp tới. Liên hệ lễ tân để đặt lịch với HLV của bạn.</p>
          <div class="fc-chips"><span>Chưa có lịch</span></div>
        <?php endif; ?>
        <a href="Profile/Profile.php?tab=classes" class="fc-arr" style="text-decoration:none"><i class="fas fa-arrow-up-right-from-square"></i></a>
      </div>

      <div class="fc reveal">
        <div class="fc-icon" style="--c:rgba(168,85,247,.15);--t:#c084fc"><i class="fas fa-user-circle"></i></div>
        <h3>Hồ sơ cá nhân</h3>
        <p>Xem thông tin cá nhân, lịch sử hóa đơn và tất cả hoạt động của bạn tại Elite Gym.</p>
        <div class="fc-chips"><span>Thông tin</span><span>Hóa đơn</span><span>Đánh giá</span></div>
        <a href="Profile/Profile.php" class="fc-arr" style="text-decoration:none"><i class="fas fa-arrow-up-right-from-square"></i></a>
      </div>
    </div>
  </div>
</section>

<!-- Available plans for customers -->
<?php if (!empty($plans)): ?>
<section class="plans" id="schedule">
  <div class="wrap">
    <div class="sec-head reveal">
      <div class="eyebrow"><span></span>Các gói tập</div>
      <h2>Nâng cấp <span>thành viên</span> của bạn</h2>
    </div>
    <div class="plans-grid">
      <?php foreach($plans as $idx => $plan): $hot = $idx === 1; ?>
      <div class="plan <?= $hot ? 'plan-hot' : '' ?> reveal">
        <?php if($hot): ?><div class="plan-badge"><i class="fas fa-star"></i> Phổ biến nhất</div><?php endif; ?>
        <div class="plan-top">
          <div class="plan-dur"><?= $plan['duration_months'] ?> THÁNG</div>
          <div class="plan-name"><?= htmlspecialchars($plan['plan_name']) ?></div>
        </div>
        <div class="plan-price"><?= number_format($plan['price'],0,',','.') ?><span>₫</span></div>
        <?php if($plan['description']): ?><p class="plan-desc"><?= htmlspecialchars($plan['description']) ?></p><?php endif; ?>
        <a href="Profile/Profile.php?tab=plans" class="plan-btn <?= $hot ? 'plan-btn-g' : '' ?>">
          Xem chi tiết <i class="fas fa-arrow-right"></i>
        </a>
        <div class="plan-shimmer"></div>
      </div>
      <?php endforeach; ?>
    </div>
    <p style="text-align:center;margin-top:24px;color:#6b7280;font-size:.875rem">
      <i class="fas fa-info-circle" style="color:#d4a017"></i>
      Để đăng ký hoặc gia hạn gói, vui lòng liên hệ lễ tân hoặc nhân viên phòng tập.
    </p>
  </div>
</section>
<?php endif; ?>

<!-- How to use (customer version) -->
<section class="how" id="checkin">
  <div class="wrap">
    <div class="sec-head reveal">
      <div class="eyebrow"><span></span>Hướng dẫn</div>
      <h2>Tận dụng <span>tối đa</span> thành viên của bạn</h2>
    </div>
    <div class="steps">
      <div class="step reveal">
        <div class="step-hex">
          <svg viewBox="0 0 80 80"><polygon points="40,4 74,22 74,58 40,76 6,58 6,22" fill="rgba(212,160,23,.08)" stroke="#d4a017" stroke-width="1.5"/></svg>
          <span>01</span>
        </div>
        <h3>Check-in mỗi ngày</h3>
        <p>Trình thẻ thành viên tại quầy lễ tân khi vào tập. Hệ thống sẽ tự động ghi nhận thời gian vào và ra.</p>
      </div>
      <div class="step-arr reveal"><i class="fas fa-chevron-right"></i></div>
      <div class="step reveal">
        <div class="step-hex">
          <svg viewBox="0 0 80 80"><polygon points="40,4 74,22 74,58 40,76 6,58 6,22" fill="rgba(212,160,23,.08)" stroke="#d4a017" stroke-width="1.5"/></svg>
          <span>02</span>
        </div>
        <h3>Đặt lịch lớp tập</h3>
        <p>Liên hệ lễ tân để đăng ký các lớp Yoga, HIIT, Zumba và các lớp khác cùng HLV chuyên nghiệp.</p>
      </div>
      <div class="step-arr reveal"><i class="fas fa-chevron-right"></i></div>
      <div class="step reveal">
        <div class="step-hex">
          <svg viewBox="0 0 80 80"><polygon points="40,4 74,22 74,58 40,76 6,58 6,22" fill="rgba(212,160,23,.08)" stroke="#d4a017" stroke-width="1.5"/></svg>
          <span>03</span>
        </div>
        <h3>Theo dõi tiến độ</h3>
        <p>Vào "Hồ sơ của tôi" để xem lịch sử check-in, gói tập hiện tại và hóa đơn bất cứ lúc nào.</p>
      </div>
    </div>
  </div>
</section>

<?php else: ?>
<!-- ════ GUEST BODY ════ -->

<section class="features" id="features">
  <div class="wrap">
    <div class="sec-head reveal">
      <div class="eyebrow"><span></span>Tính năng nổi bật</div>
      <h2>Hệ thống <span>toàn diện</span> cho phòng tập</h2>
    </div>
    <div class="feat-grid">
      <div class="fc fc-wide reveal">
        <div class="fc-icon" style="--c:rgba(212,160,23,.15);--t:#d4a017"><i class="fas fa-users"></i></div>
        <h3>Quản lý thành viên</h3>
        <p>Theo dõi hồ sơ, lịch sử tập luyện, gói đăng ký và hóa đơn từng khách hàng. Tìm kiếm, lọc và xuất báo cáo dễ dàng.</p>
        <div class="fc-chips"><span>Hồ sơ</span><span>Lịch sử</span><span>Hóa đơn</span></div>
        <div class="fc-arr"><i class="fas fa-arrow-up-right-from-square"></i></div>
      </div>
      <div class="fc reveal">
        <div class="fc-icon" style="--c:rgba(59,130,246,.15);--t:#60a5fa"><i class="fas fa-calendar-alt"></i></div>
        <h3>Đặt lịch lớp tập</h3>
        <p>Đăng ký yoga, HIIT, Zumba. Quản lý lịch HLV và số chỗ theo thời gian thực.</p>
        <div class="fc-chips"><span>Lịch tập</span><span>HLV</span></div>
        <div class="fc-arr"><i class="fas fa-arrow-up-right-from-square"></i></div>
      </div>
      <div class="fc reveal">
        <div class="fc-icon" style="--c:rgba(34,197,94,.15);--t:#4ade80"><i class="fas fa-location-dot"></i></div>
        <h3>Check-In thông minh</h3>
        <p>Ghi nhận giờ vào/ra tự động, streak liên tiếp và thống kê tổng giờ tập theo tuần/tháng.</p>
        <div class="fc-chips"><span>Check-in</span><span>Streak</span></div>
        <div class="fc-arr"><i class="fas fa-arrow-up-right-from-square"></i></div>
      </div>
      <div class="fc reveal">
        <div class="fc-icon" style="--c:rgba(236,72,153,.15);--t:#f472b6"><i class="fas fa-tag"></i></div>
        <h3>Khuyến mãi & Ưu đãi</h3>
        <p>Tạo mã giảm giá theo thời gian, số lượt dùng và giá trị đơn hàng tối thiểu.</p>
        <div class="fc-chips"><span>Mã giảm</span><span>Giới hạn</span></div>
        <div class="fc-arr"><i class="fas fa-arrow-up-right-from-square"></i></div>
      </div>
      <div class="fc reveal">
        <div class="fc-icon" style="--c:rgba(168,85,247,.15);--t:#c084fc"><i class="fas fa-screwdriver-wrench"></i></div>
        <h3>Cơ sở vật chất</h3>
        <p>Quản lý thiết bị, lịch bảo trì và phòng tập theo thời gian thực.</p>
        <div class="fc-chips"><span>Thiết bị</span><span>Bảo trì</span></div>
        <div class="fc-arr"><i class="fas fa-arrow-up-right-from-square"></i></div>
      </div>
      <div class="fc fc-wide reveal">
        <div class="fc-icon" style="--c:rgba(234,179,8,.15);--t:#fbbf24"><i class="fas fa-chart-bar"></i></div>
        <h3>Báo cáo & Thống kê</h3>
        <p>Dashboard Admin với doanh thu, check-in, thiết bị và nhân sự. Xuất PDF/Excel theo tháng hoặc tuỳ chọn khoảng thời gian.</p>
        <div class="fc-chips"><span>Dashboard</span><span>Doanh thu</span><span>Nhân sự</span><span>Xuất báo cáo</span></div>
        <div class="fc-arr"><i class="fas fa-arrow-up-right-from-square"></i></div>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($plans)): ?>
<section class="plans" id="plans">
  <div class="wrap">
    <div class="sec-head reveal">
      <div class="eyebrow"><span></span>Gói thành viên</div>
      <h2>Chọn gói <span>phù hợp</span> với bạn</h2>
    </div>
    <div class="plans-grid">
      <?php foreach($plans as $idx => $plan): $hot = $idx === 1; ?>
      <div class="plan <?= $hot ? 'plan-hot' : '' ?> reveal">
        <?php if($hot): ?><div class="plan-badge"><i class="fas fa-star"></i> Phổ biến nhất</div><?php endif; ?>
        <div class="plan-top">
          <div class="plan-dur"><?= $plan['duration_months'] ?> THÁNG</div>
          <div class="plan-name"><?= htmlspecialchars($plan['plan_name']) ?></div>
        </div>
        <div class="plan-price"><?= number_format($plan['price'],0,',','.') ?><span>₫</span></div>
        <?php if($plan['description']): ?><p class="plan-desc"><?= htmlspecialchars($plan['description']) ?></p><?php endif; ?>
        <a href="../Internal/Index/Login/Login.php" class="plan-btn <?= $hot ? 'plan-btn-g' : '' ?>">
          Đăng nhập để đăng ký <i class="fas fa-arrow-right"></i>
        </a>
        <div class="plan-shimmer"></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="how" id="how">
  <div class="wrap">
    <div class="sec-head reveal">
      <div class="eyebrow"><span></span>Bắt đầu ngay</div>
      <h2>Chỉ <span>3 bước</span> đơn giản</h2>
    </div>
    <div class="steps">
      <div class="step reveal">
        <div class="step-hex">
          <svg viewBox="0 0 80 80"><polygon points="40,4 74,22 74,58 40,76 6,58 6,22" fill="rgba(212,160,23,.08)" stroke="#d4a017" stroke-width="1.5"/></svg>
          <span>01</span>
        </div>
        <h3>Tạo tài khoản</h3>
        <p>Đăng ký nhanh, điền thông tin cơ bản và chọn gói thành viên phù hợp trong vài phút.</p>
      </div>
      <div class="step-arr reveal"><i class="fas fa-chevron-right"></i></div>
      <div class="step reveal">
        <div class="step-hex">
          <svg viewBox="0 0 80 80"><polygon points="40,4 74,22 74,58 40,76 6,58 6,22" fill="rgba(212,160,23,.08)" stroke="#d4a017" stroke-width="1.5"/></svg>
          <span>02</span>
        </div>
        <h3>Khám phá & Đặt lịch</h3>
        <p>Xem lịch lớp tập, chọn HLV yêu thích và đăng ký tham gia các buổi phù hợp.</p>
      </div>
      <div class="step-arr reveal"><i class="fas fa-chevron-right"></i></div>
      <div class="step reveal">
        <div class="step-hex">
          <svg viewBox="0 0 80 80"><polygon points="40,4 74,22 74,58 40,76 6,58 6,22" fill="rgba(212,160,23,.08)" stroke="#d4a017" stroke-width="1.5"/></svg>
          <span>03</span>
        </div>
        <h3>Tập luyện & Theo dõi</h3>
        <p>Check-in mỗi ngày, theo dõi streak và nhận thông báo ưu đãi cá nhân hoá.</p>
      </div>
    </div>
  </div>
</section>

<?php endif; /* end guest body */ ?>


<!-- ══ REVIEWS — dùng chung ══ -->
<?php if (!empty($reviews)): ?>
<section class="reviews" id="reviews">
  <div class="wrap">
    <div class="sec-head reveal">
      <div class="eyebrow"><span></span>Đánh giá thành viên</div>
      <h2>Khách hàng <span>nói gì</span> về chúng tôi</h2>
    </div>
    <div class="rv-grid">
      <?php foreach($reviews as $rv): ?>
      <div class="rv-card reveal">
        <div class="rv-stars"><?php for($s=0;$s<$rv['rating'];$s++): ?><i class="fas fa-star"></i><?php endfor; ?></div>
        <p>"<?= htmlspecialchars($rv['content']) ?>"</p>
        <div class="rv-author">
          <div class="rv-av"><?= mb_strtoupper(mb_substr($rv['full_name'],-1)) ?></div>
          <span><?= htmlspecialchars($rv['full_name']) ?></span>
        </div>
        <i class="fas fa-quote-right rv-quote"></i>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ FINAL CTA ══ -->
<section class="cta-final">
  <div class="ctaf-bg">
    <svg class="cf-hex"  viewBox="0 0 500 500"><polygon points="250,10 462,130 462,370 250,490 38,370 38,130" fill="none" stroke="rgba(212,160,23,.05)" stroke-width="2"/></svg>
    <svg class="cf-hex2" viewBox="0 0 500 500"><polygon points="250,10 462,130 462,370 250,490 38,370 38,130" fill="none" stroke="rgba(212,160,23,.03)" stroke-width="1.5"/></svg>
    <div class="cf-glow"></div>
  </div>
  <div class="wrap">
    <div class="ctaf-inner reveal">
      <?php if ($is_customer): ?>
        <div class="ctaf-ey">✦ Chào mừng trở lại, <?= $customer_name ?>!</div>
        <h2>HÃY DUY TRÌ<br><span>THÓI QUEN TẬP LUYỆN</span></h2>
        <p>Mỗi ngày tập luyện là một bước tiến. Xem hồ sơ và theo dõi tiến trình của bạn ngay hôm nay!</p>
        <div class="ctaf-btns">
          <a href="Profile/Profile.php"                          class="btn-gold ctaf-btn"><i class="fas fa-user-circle"></i> Hồ sơ của tôi</a>
          <a href="../Internal/Index/Login/logout.php"   class="btn-ghost-gold ctaf-btn">Đăng xuất <i class="fas fa-sign-out-alt"></i></a>
        </div>
      <?php else: ?>
        <div class="ctaf-ey">✦ Sẵn sàng nâng tầm luyện tập?</div>
        <h2>BẮT ĐẦU HÀNH TRÌNH<br><span>ĐẲNG CẤP HÔM NAY</span></h2>
        <p>Tham gia cùng hàng trăm thành viên đang trải nghiệm phòng tập gym cao cấp nhất.</p>
        <div class="ctaf-btns">
          <a href="../Internal/Index/Login/Login.php"       class="btn-gold ctaf-btn"><i class="fas fa-sign-in-alt"></i> Đăng nhập ngay</a>
          <a href="../Internal/Index/Register/Register.php" class="btn-ghost-gold ctaf-btn">Tạo tài khoản miễn phí <i class="fas fa-arrow-right"></i></a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ══ FOOTER ══ -->
<footer class="footer">
  <div class="wrap">
    <div class="footer-grid">
      <div class="fg-brand">
        <a href="index.php" class="nav-logo" style="margin-bottom:14px;display:inline-flex">
          <svg class="hex-logo" viewBox="0 0 44 44">
            <polygon points="22,2 40,12 40,32 22,42 4,32 4,12" fill="none" stroke="#d4a017" stroke-width="1.8"/>
            <text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" fill="#d4a017" font-size="12" font-weight="800" font-family="Barlow Condensed">EG</text>
          </svg>
          <div class="nav-brand"><span class="nb-main">ELITE</span><span class="nb-sub">GYM</span></div>
        </a>
        <p>Phòng tập thể hình cao cấp — lớp tập đa dạng, HLV chuyên nghiệp, thiết bị hiện đại.</p>
        <div class="socials">
          <a href="#"><i class="fab fa-facebook-f"></i></a>
          <a href="#"><i class="fab fa-instagram"></i></a>
          <a href="#"><i class="fab fa-youtube"></i></a>
        </div>
      </div>
      <div class="fg-col">
        <h4><?= $is_customer ? 'Tài khoản' : 'Hệ thống' ?></h4>
        <?php if ($is_customer): ?>
          <a href="Profile/Profile.php">Hồ sơ của tôi</a>
          <a href="Profile/Profile.php?tab=plans">Gói tập</a>
          <a href="Profile/Profile.php?tab=checkin">Check-in</a>
          <a href="../Internal/Index/Login/logout.php" style="color:#f87171">Đăng xuất</a>
        <?php else: ?>
          <a href="../Internal/Index/Login/Login.php">Đăng nhập</a>
          <a href="../Internal/Index/Register/Register.php">Đăng ký</a>
          <a href="#plans">Gói thành viên</a>
        <?php endif; ?>
      </div>
      <div class="fg-col">
        <h4>Thông tin</h4>
        <?php if ($is_customer): ?>
          <a href="#membership">Gói tập</a>
          <a href="#checkin">Hướng dẫn</a>
        <?php else: ?>
          <a href="#how">Cách hoạt động</a>
          <a href="#features">Tính năng</a>
        <?php endif; ?>
        <a href="#reviews">Đánh giá</a>
        <a href="#">Liên hệ</a>
        <a href="#">Chính sách</a>
      </div>
    </div>
    <div class="footer-btm">
      <span>© 2026 <strong>Elite Gym</strong>. All rights reserved.</span>
    </div>
  </div>
</footer>

<script>
const navUserBtn  = document.getElementById('navUserBtn');
const navDropdown = document.getElementById('navDropdown');
if (navUserBtn && navDropdown) {
  navUserBtn.addEventListener('click', e => {
    e.stopPropagation();
    navDropdown.classList.toggle('open');
    navUserBtn.classList.toggle('active');
  });
  document.addEventListener('click', () => {
    navDropdown.classList.remove('open');
    navUserBtn.classList.remove('active');
  });
}
</script>
<script src="Landing.js"></script>
</body>
</html>
