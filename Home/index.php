
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

// ── Lấy $cid sớm để dùng cho filter plans ────────────────────
$cid           = 0;
$cur_sort      = 0;
$cur_type_name = '';
if ($is_customer) {
    $account_id = (int)$_SESSION['account_id'];
    $rCid = $conn->prepare("SELECT customer_id FROM Customer WHERE account_id = ? LIMIT 1");
    $rCid->bind_param("i", $account_id);
    $rCid->execute();
    $rowCid = $rCid->get_result()->fetch_assoc();
    $rCid->close();
    $cid = (int)($rowCid['customer_id'] ?? 0);

    if ($cid) {
        $today_tmp = date('Y-m-d');
        // Lấy packagetype active cao nhất của khách
        $curRes = $conn->query("
            SELECT pt.sort_order, pt.type_name
            FROM MembershipRegistration mr
            JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
            LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
            WHERE mr.customer_id = $cid
              AND mr.status      = 'active'
              AND mr.end_date   >= '$today_tmp'
            ORDER BY pt.sort_order DESC
            LIMIT 1
        ");
        $curPkg = $curRes ? $curRes->fetch_assoc() : null;
        if ($curPkg) {
            $cur_sort      = intval($curPkg['sort_order']);
            $cur_type_name = $curPkg['type_name'] ?? '';
        }
    }
}

// ── Plans: lọc theo sort_order của packagetype đang dùng ─────
// Guest / chưa có gói → tất cả
// Basic(1)    → tất cả        (sort_order >= 1 = tất cả)
// Standard(2) → Standard+     (sort_order >= 2)
// Premium(3)  → Premium only  (sort_order >= 3)
if ($cur_sort > 0) {
    $plans = $conn->query("
        SELECT mp.plan_id, mp.plan_name, mp.duration_months, mp.price,
               mp.description, mp.image_url,
               pt.type_name, pt.color_code, pt.sort_order AS type_order
        FROM MembershipPlan mp
        LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
        WHERE pt.sort_order >= $cur_sort
        ORDER BY pt.sort_order ASC, mp.price ASC
    ")->fetch_all(MYSQLI_ASSOC);
} else {
    $plans = $conn->query("
        SELECT mp.plan_id, mp.plan_name, mp.duration_months, mp.price,
               mp.description, mp.image_url,
               pt.type_name, pt.color_code, pt.sort_order AS type_order
        FROM MembershipPlan mp
        LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
        ORDER BY pt.sort_order ASC, mp.price ASC
    ")->fetch_all(MYSQLI_ASSOC);
}

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

// ── Lấy logo từ DB (ảnh tên Logo_ELITY) ─────────────────────────
$logo_row = $conn->query("
    SELECT file_url FROM landing_images
    WHERE image_name = 'Logo_ELITY'
    LIMIT 1
")->fetch_assoc();
$logo_url = $logo_row ? htmlspecialchars($logo_row['file_url']) : '';

// First image for hero background
$hero_img = !empty($slide_imgs) ? htmlspecialchars($slide_imgs[0]['file_url']) : '';

// ── Extra data for CUSTOMER view ─────────────────────────────
$cus_active_plan = null;
$cus_checkins    = [];
$cus_total_ci    = 0;
$cus_upcoming    = [];
$cus_days_left   = 0;
$cus_plan_pct    = 0;
$cus_first_start = null;

if ($is_customer && $cid) {
    $today = date('Y-m-d');

    $mems = $conn->query("
        SELECT mr.start_date, mr.end_date, mp.plan_name, mp.duration_months, mp.price,
               mp.package_type_id, pt.type_name
        FROM MembershipRegistration mr
        JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
        LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
        WHERE mr.customer_id = $cid
          AND mr.status = 'active'
        ORDER BY mr.end_date DESC
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($mems as $m) {
        if ($m['end_date'] >= $today) { $cus_active_plan = $m; break; }
    }
    if ($cus_active_plan) {
        // Tìm start_date sớm nhất của chuỗi gói cùng PackageType
        $chain_type      = $cus_active_plan['type_name'] ?? '';
        $cus_first_start = $cus_active_plan['start_date'];
        foreach ($mems as $m) {
            if (($m['type_name'] ?? '') === $chain_type && $m['start_date'] < $cus_first_start) {
                $cus_first_start = $m['start_date'];
            }
        }
        $total_days    = max(1, (new DateTime($cus_first_start))->diff(new DateTime($cus_active_plan['end_date']))->days);
        $cus_days_left = max(0, (new DateTime($today))->diff(new DateTime($cus_active_plan['end_date']))->days);
        $elapsed       = (new DateTime($cus_first_start))->diff(new DateTime($today))->days;
        $cus_plan_pct  = min(100, round($elapsed / $total_days * 100));
    }
        // Lấy 5 lần check-in gần nhất từ GymCheckIn, ghép check_out tương ứng
        $cus_checkins = $conn->query("
            SELECT
                ci.check_time  AS check_in,
                co.check_time  AS check_out
            FROM GymCheckIn ci
            LEFT JOIN GymCheckIn co
                ON  co.customer_id = ci.customer_id
                AND co.type        = 'checkout'
                AND co.check_time  = (
                    SELECT MIN(x.check_time)
                    FROM GymCheckIn x
                    WHERE x.customer_id = ci.customer_id
                      AND x.type        = 'checkout'
                      AND x.check_time  > ci.check_time
                )
            WHERE ci.customer_id = $cid
              AND ci.type        = 'checkin'
            ORDER BY ci.check_time DESC
            LIMIT 5
        ")->fetch_all(MYSQLI_ASSOC);
        $cus_total_ci  = $conn->query("SELECT COUNT(*) AS c FROM GymCheckIn WHERE customer_id=$cid AND type='checkin'")->fetch_assoc()['c'] ?? 0;
        $cus_upcoming  = $conn->query("
            SELECT tc.class_name, tc.start_time, e.full_name AS trainer
            FROM ClassRegistration cr
            JOIN TrainingClass tc ON tc.class_id = cr.class_id
            LEFT JOIN Employee e ON e.employee_id = tc.trainer_id
            WHERE cr.customer_id=$cid AND tc.start_time >= NOW()
            ORDER BY tc.start_time ASC LIMIT 3
        ")->fetch_all(MYSQLI_ASSOC);
}
// ── Plan card helpers (shared between guest & customer) ──────────
$fallback_icons  = ['fas fa-fire','fas fa-bolt','fas fa-crown','fas fa-gem'];
$fallback_colors = ['#cc0000','#ff6b00','#cc0000','#9c27b0'];
$fallback_bgs    = [
    'linear-gradient(135deg,#1a0a0a,#2a1010)',
    'linear-gradient(135deg,#1a1000,#2a1a00)',
    'linear-gradient(135deg,#0a0a1a,#10102a)',
    'linear-gradient(135deg,#1a001a,#2a002a)',
];
$type_icon_map = [
    'Basic'    => 'fas fa-fire',
    'Standard' => 'fas fa-bolt',
    'Premium'  => 'fas fa-crown',
    'VIP'      => 'fas fa-gem',
    'Student'  => 'fas fa-graduation-cap',
];
?>
<html lang="vi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Elite Gym — <?= $is_customer ? 'Chào mừng, '.$customer_name : 'Phòng Tập Đẳng Cấp' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<?php if ($hero_img): ?>
<link rel="preload" as="image" href="<?= $hero_img ?>"/>
<?php endif; ?>
<link rel="stylesheet" href="Review/reviews_section.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>
<link rel="stylesheet" href="Landing.css"/>
<link rel="stylesheet" href="Review/notification.css"/>
</head>
<body>

<div class="cursor-glow" id="cursorGlow"></div>

<!-- ══ NAVBAR ══ -->
<header class="nav" id="nav">
  <div class="nav-inner">
    <a href="index.php" class="nav-logo">
      <?php if ($logo_url): ?>
        <img src="<?= $logo_url ?>" alt="Elite Gym Logo" class="nav-logo-img"/>
      <?php else: ?>
        <svg class="hex-logo" viewBox="0 0 44 44">
          <polygon points="22,2 40,12 40,32 22,42 4,32 4,12" fill="none" stroke="#cc0000" stroke-width="1.8"/>
          <text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" fill="#cc0000" font-size="12" font-weight="800" font-family="Barlow Condensed">EG</text>
        </svg>
        <div class="nav-brand"><span class="nb-main">ELITE</span><span class="nb-sub">GYM</span></div>
      <?php endif; ?>
    </a>
    <nav class="nav-links">
      <?php if ($is_customer): ?>
        <a href="#home">Trang chủ</a>
        <a href="#schedule">Gói tập</a>
        <a href="Schedule/Schedule.php">Lớp tập</a>
        <a href="#checkin">Hướng dẫn</a>
        <a href="#reviews">Đánh giá</a>
      <?php else: ?>
        <a href="#home" class="active">Trang chủ</a>
        <a href="#features">Tính năng</a>
        <a href="#plans">Gói tập</a>
        <a href="#gallery">Không gian</a>
        <a href="#how">Bắt đầu</a>
        <a href="#reviews">Đánh giá</a>
      <?php endif; ?>
    </nav>
    <div class="nav-actions">
      <?php if ($is_customer): ?>
        <!-- ▼▼▼ BELL NOTIFICATION ▼▼▼ -->
        <div class="notif-bell-wrap" id="notifBellWrap">
          <button class="notif-bell-btn" id="notifBellBtn" title="Thông báo" aria-label="Thông báo">
            <i class="fas fa-bell"></i>
            <span class="notif-badge" id="notifBadge"></span>
          </button>
          <div class="notif-panel" id="notifPanel" role="dialog" aria-label="Thông báo">
            <div class="np-header">
              <div class="np-title"><i class="fas fa-bell"></i> Thông báo</div>
              <button class="np-mark-all" id="npMarkAll">Đánh dấu tất cả đã đọc</button>
            </div>
            <div class="np-list" id="npList">
              <div class="np-skeleton"><div class="np-sk-circle"></div><div class="np-sk-lines"><div class="np-sk-line"></div><div class="np-sk-line s"></div></div></div>
              <div class="np-skeleton"><div class="np-sk-circle"></div><div class="np-sk-lines"><div class="np-sk-line"></div><div class="np-sk-line s"></div></div></div>
            </div>
            <div class="np-footer"><a href="#reviews">Xem tất cả đánh giá của bạn</a></div>
          </div>
        </div>
        <!-- ▲▲▲ END BELL ▲▲▲ -->
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
      <a href="#schedule">Gói tập</a>
      <a href="Schedule/Schedule.php">Lịch tập</a>
      <a href="#checkin">Hướng dẫn</a>
      <a href="#reviews">Đánh giá</a>
      <a href="Profile/Profile.php" class="mm-cta"><i class="fas fa-user-circle"></i> Hồ sơ của tôi</a>
      <a href="../Internal/Index/Login/logout.php" class="mm-login" style="color:#f87171"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
    <?php else: ?>
      <a href="#features">Tính năng</a>
      <a href="#plans">Gói tập</a>
      <a href="#gallery">Không gian</a>
      <a href="#how">Bắt đầu</a>
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
<section class="hero-customer" id="home">
  <div class="wrap">
    <div class="hero-left">
      <div class="hero-kicker">
        <span class="kicker-bar"></span>
        <span>Thành viên Elite Gym</span>
        <span class="kicker-dot">✦</span>
        <span><?= date('d/m/Y') ?></span>
      </div>
      <h1 class="hero-title-cus">
        CHÀO MỪNG<br>
        <span><?= mb_strtoupper(explode(' ', trim($customer_name))[count(explode(' ', trim($customer_name)))-1]) ?>,</span><br>
        SẴN SÀNG CHƯA?
      </h1>
      <p class="hero-desc">Theo dõi gói tập, lịch lớp và lịch sử check-in — tất cả trong một nơi duy nhất.</p>
      <div class="hero-btns-cus">
        <a href="Profile/Profile.php" class="btn-gold"><i class="fas fa-user-circle"></i> Xem hồ sơ</a>
        <a href="#schedule" class="btn-ghost-gold">Xem gói tập <i class="fas fa-arrow-right"></i></a>
      </div>
      <div class="hero-stats-inline">
        <div class="hs"><div class="hs-n" data-target="<?= $cus_total_ci ?>">0</div><div class="hs-l">Check-in</div></div>
        <div class="hs-sep"></div>
        <div class="hs"><div class="hs-n" data-target="<?= count($cus_upcoming) ?>">0</div><div class="hs-l">Lớp sắp tới</div></div>
        <div class="hs-sep"></div>
        <div class="hs"><div class="hs-n" data-target="<?= $cus_days_left ?>">0</div><div class="hs-l">Ngày còn lại</div></div>
        <div class="hs-sep"></div>
        <div class="hs"><div class="hs-n" data-target="24">0</div><div class="hs-l">Giờ/ngày</div></div>
      </div>
    </div>
    <!-- Dashboard card -->
    <div class="hero-right">
      <div class="dashboard-card">
        <div class="dc-header">
          <div class="dc-dots"><i></i><i></i><i></i></div>
          <span class="dc-title">HỒ SƠ THÀNH VIÊN</span>
          <?php if ($cus_active_plan): ?>
            <span class="dc-live" style="background:rgba(34,197,94,.1);color:#4ade80;border:1px solid rgba(34,197,94,.2);padding:2px 8px;border-radius:2px;font-size:9px;letter-spacing:1px"><i class="fas fa-circle" style="font-size:.4rem"></i> ĐANG HOẠT ĐỘNG</span>
          <?php else: ?>
            <span class="dc-live" style="background:rgba(248,113,113,.1);color:#f87171;border:1px solid rgba(248,113,113,.2);padding:2px 8px;border-radius:2px;font-size:9px;letter-spacing:1px"><i class="fas fa-circle" style="font-size:.4rem"></i> CHƯA CÓ GÓI</span>
          <?php endif; ?>
        </div>
        <div class="dc-body">
          <!-- Identity -->
          <div style="display:flex;align-items:center;gap:12px;padding:4px 0 12px;border-bottom:1px solid rgba(255,255,255,.06)">
            <div style="width:40px;height:40px;border-radius:3px;background:var(--red);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:1rem;color:#fff;flex-shrink:0">
              <?= mb_strtoupper(mb_substr($customer_name, 0, 1)) ?>
            </div>
            <div>
              <div style="font-weight:700;font-size:.9rem;color:#e8e8e8"><?= $customer_name ?></div>
              <div style="font-size:.72rem;color:rgba(255,255,255,.3)">Thành viên Elite Gym</div>
            </div>
          </div>
          <!-- Plan progress -->
          <?php if ($cus_active_plan): ?>
          <div style="margin:12px 0 8px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <span style="font-size:.76rem;font-weight:600;color:var(--red)"><?= htmlspecialchars($cus_active_plan['plan_name']) ?></span>
              <span style="font-size:.7rem;color:rgba(255,255,255,.3)">Còn <?= $cus_days_left ?> ngày</span>
            </div>
            <div style="height:4px;background:rgba(255,255,255,.07);border-radius:99px;overflow:hidden">
              <div style="width:<?= $cus_plan_pct ?>%;height:100%;background:var(--red);border-radius:99px"></div>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:4px;font-size:.68rem;color:rgba(255,255,255,.25)">
              <span><?= date('d/m/Y', strtotime($cus_first_start ?? $cus_active_plan['start_date'])) ?></span>
              <span><?= date('d/m/Y', strtotime($cus_active_plan['end_date'])) ?></span>
            </div>
          </div>
          <?php else: ?>
          <div style="margin:12px 0;padding:10px;background:rgba(248,113,113,.06);border:1px solid rgba(248,113,113,.12);border-radius:3px;text-align:center">
            <div style="font-size:.8rem;color:#f87171;font-weight:600">Bạn chưa có gói tập nào</div>
            <a href="#schedule" style="font-size:.72rem;color:var(--red);text-decoration:none;margin-top:4px;display:block">Xem các gói tập →</a>
          </div>
          <?php endif; ?>
          <!-- Recent check-ins -->
          <div class="dc-recent">
            <div class="dcr-head">Check-in gần đây</div>
            <?php if (!empty($cus_checkins)): ?>
              <?php foreach($cus_checkins as $ci): $dt = new DateTime($ci['check_in']); ?>
              <div class="dcr-row">
                <div class="dcr-av"><i class="fas fa-door-open" style="font-size:.6rem"></i></div>
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
          <div class="dc-recent">
            <div class="dcr-head">Lớp tập sắp tới</div>
            <?php foreach($cus_upcoming as $cl): $cdt = new DateTime($cl['start_time']); ?>
            <div class="dcr-row">
              <div class="dcr-av"><i class="fas fa-dumbbell" style="font-size:.58rem"></i></div>
              <span style="flex:1;font-size:.78rem"><?= htmlspecialchars($cl['class_name']) ?></span>
              <span class="dcr-date"><?= $cdt->format('d/m H:i') ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php else: ?>
<!-- ── HERO: GUEST — FULL SCREEN ── -->
<section class="hero-fullscreen" id="home">
  <?php if ($hero_img): ?>
    <div class="hero-bg-img" style="background-image:url('<?= $hero_img ?>')"></div>
  <?php else: ?>
    <div class="hero-bg-img no-image"></div>
  <?php endif; ?>
  <div class="hero-overlay"></div>

  <div class="hero-content">
    <div class="hero-eyebrow">ELITE GYM — PHÒNG TẬP CAO CẤP</div>
    <h1 class="hero-title">
      KEEP YOUR<br><span>BODY</span> BURNING
    </h1>
    <div class="hero-btns">
      <a href="#plans" class="btn-hero-solid"><i class="fas fa-shopping-cart"></i> Xem gói tập</a>
    </div>
  </div>

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

<!-- ══════════════════════════════════════════
     PLANS — PRODUCT CARD STYLE (GUEST ONLY)
     ══════════════════════════════════════════ -->
<?php if (!empty($plans) && !$is_customer): ?>
<!-- Shop bar -->
<div class="shop-bar">
  <div class="shop-bar-inner">
    <span class="shop-bar-count">Hiển thị 1–<?= count($plans) ?> trong <?= $total_plans ?> gói tập</span>
    <div class="shop-bar-right"></div>
  </div>
</div>

<section class="shop-section" id="plans">
  <div class="shop-grid" id="plansGrid">
    <?php foreach($plans as $idx => $plan):
      // PackageType data
      $pt_name  = $plan['type_name']   ?? '';
      $pt_color = $plan['color_code']  ?? $fallback_colors[$idx % 4];
      $icon     = $type_icon_map[$pt_name] ?? $fallback_icons[$idx % 4];
      $bgcss    = $fallback_bgs[$idx % 4];
      // Highlight: Premium & VIP are "hot"
      $is_hot   = in_array($pt_name, ['Premium','VIP']);
      $old_price = round($plan['price'] * 1.2 / 1000000, 0) * 1000000;
    ?>
    <div class="product-card <?= $is_hot ? 'plan-hot' : '' ?> reveal"
         data-price="<?= $plan['price'] ?>"
         style="--pt-color:<?= htmlspecialchars($pt_color) ?>;<?= $is_hot ? 'border-color:'.htmlspecialchars($pt_color).';box-shadow:0 0 0 1px '.htmlspecialchars($pt_color).'40,0 8px 32px rgba(0,0,0,.2)' : '' ?>">
      <!-- Image area -->
      <div class="product-img-wrap">
        <?php if ($is_hot): ?>
          <span class="hot-badge" style="background:<?= htmlspecialchars($pt_color) ?>">
            <i class="fas fa-star"></i> <?= htmlspecialchars($pt_name ?: 'PHỔ BIẾN') ?>
          </span>
        <?php elseif ($pt_name): ?>
          <span class="type-badge" style="background:<?= htmlspecialchars($pt_color) ?>">
            <?= htmlspecialchars($pt_name) ?>
          </span>
        <?php endif; ?>
        <span class="sale-badge">SALE!</span>

        <?php if (!empty($plan['image_url'])): ?>
          <!-- Real image from DB -->
          <img src="<?= htmlspecialchars($plan['image_url']) ?>"
               alt="<?= htmlspecialchars($plan['plan_name']) ?>"
               class="product-real-img" loading="lazy"/>
          <!-- Overlay gradient + icon -->
          <div class="product-img-overlay" style="background:linear-gradient(to top,rgba(0,0,0,.7) 0%,rgba(0,0,0,.1) 60%)"></div>
        <?php else: ?>
          <!-- Placeholder when no image -->
          <div class="product-img-placeholder" style="background:<?= $bgcss ?>">
            <i class="<?= $icon ?>" style="font-size:52px;color:<?= htmlspecialchars($pt_color) ?>;opacity:.75"></i>
            <span style="font-family:'Barlow Condensed',sans-serif;font-size:10px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,.25);text-transform:uppercase"><?= $plan['duration_months'] ?> Tháng</span>
          </div>
        <?php endif; ?>

        <!-- Duration chip always shown at bottom of image -->
        <div class="product-img-dur" style="border-color:<?= htmlspecialchars($pt_color) ?>40;color:<?= htmlspecialchars($pt_color) ?>">
          <?= $plan['duration_months'] ?> THÁNG
        </div>
      </div>

      <!-- Info -->
      <div class="product-body">
        <div class="product-duration" style="color:<?= htmlspecialchars($pt_color) ?>"><?= $plan['duration_months'] ?> THÁNG</div>
        <div class="product-name"><?= htmlspecialchars($plan['plan_name']) ?></div>
        <?php if($plan['description']): ?>
          <div class="product-desc"><?= htmlspecialchars($plan['description']) ?></div>
        <?php endif; ?>
        <!-- PackageType highlight chip -->
        <?php if ($pt_name): ?>
        <div class="product-type-chip" style="border-color:<?= htmlspecialchars($pt_color) ?>40;color:<?= htmlspecialchars($pt_color) ?>">
          <i class="<?= $icon ?>" style="font-size:.7rem"></i>
          <?= htmlspecialchars($pt_name) ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Price + CTA -->
      <div class="product-footer" style="<?= $is_hot ? 'border-top:2px solid '.htmlspecialchars($pt_color).'60' : '' ?>">
        <div class="product-price-box">
          <div class="product-price-old"><?= number_format($old_price,0,',','.') ?>₫</div>
          <div class="product-price-new" style="color:<?= $is_hot ? htmlspecialchars($pt_color) : 'var(--red)' ?>">
            <?= number_format($plan['price'],0,',','.') ?><sub>₫</sub>
          </div>
        </div>
        <a href="../Internal/Index/Login/Login.php"
           class="product-add-btn"
           style="<?= $is_hot ? 'background:'.htmlspecialchars($pt_color).';color:#fff' : '' ?>">
          <i class="fas fa-sign-in-alt"></i>&nbsp;ĐĂNG KÝ
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if (!$is_customer): ?>
  <div style="text-align:center;margin-top:28px;color:#888;font-size:13px;padding:0 40px">
    <i class="fas fa-info-circle" style="color:var(--red)"></i>
    <a href="../Internal/Index/Login/Login.php" style="color:var(--red);font-weight:600">Đăng nhập</a> để đăng ký gói tập ngay hôm nay!
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<!-- ══ GALLERY / SLIDESHOW ══ -->
<?php if (!empty($slide_imgs)): ?>
<section class="gallery-section" id="gallery">
  <div class="wrap">
    <div class="sec-head reveal">
      <div class="eyebrow"><span></span>Không gian tập luyện</div>
      <h2>Khám phá <span>Elite Gym</span></h2>
    </div>
  </div>
  <div class="gallery-inner">
    <?php $visible_imgs = array_values(array_filter($slide_imgs, fn($img) => $img['image_name'] !== 'Logo_ELITY')); ?>
    <div class="slideshow-wrap reveal" id="slideshowWrap">
      <div class="slide-progress"><div class="slide-progress-bar" id="slideProgressBar"></div></div>
      <div class="slide-counter"><span id="slideCur">1</span> / <?= count($visible_imgs) ?></div>
      <button class="slide-autoplay-btn" id="autoplayBtn"><i class="fas fa-pause" id="autoplayIcon"></i></button>

      <?php foreach($visible_imgs as $si => $img): ?>
      <div class="slide <?= $si === 0 ? 'active' : '' ?>" data-index="<?= $si ?>">
        <img src="<?= htmlspecialchars($img['file_url']) ?>" alt="<?= htmlspecialchars($img['image_name']) ?>" loading="<?= $si < 2 ? 'eager' : 'lazy' ?>"/>
        <div class="slide-caption">
          <div class="sc-label"><i class="fas fa-circle" style="font-size:.4rem"></i> ELITE GYM</div>
          <div class="sc-title"><?= htmlspecialchars($img['image_name']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>

      <button class="slide-prev" id="slidePrev"><i class="fas fa-chevron-left"></i></button>
      <button class="slide-next" id="slideNext"><i class="fas fa-chevron-right"></i></button>
      <div class="slide-dots" id="slideDots">
        <?php foreach($visible_imgs as $si => $img): ?>
        <button class="dot <?= $si === 0 ? 'active' : '' ?>" data-i="<?= $si ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if (count($visible_imgs) > 1): ?>
    <div class="slide-thumbs" id="slideThumbs">
      <?php foreach($visible_imgs as $si => $img): ?>
      <div class="slide-thumb <?= $si === 0 ? 'active' : '' ?>" data-i="<?= $si ?>">
        <img src="<?= htmlspecialchars($img['file_url']) ?>" alt="<?= htmlspecialchars($img['image_name']) ?>" loading="lazy"/>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<script>
(function () {
  const wrap    = document.getElementById('slideshowWrap');
  if (!wrap) return;
  const slides  = [...wrap.querySelectorAll('.slide')];
  const dots    = [...document.querySelectorAll('#slideDots .dot')];
  const thumbs  = [...document.querySelectorAll('#slideThumbs .slide-thumb')];
  const prevBtn = document.getElementById('slidePrev');
  const nextBtn = document.getElementById('slideNext');
  const autoBtn = document.getElementById('autoplayBtn');
  const autoIcon= document.getElementById('autoplayIcon');
  const curEl   = document.getElementById('slideCur');
  const progBar = document.getElementById('slideProgressBar');
  const INTERVAL = 4500, TRANS = 900;
  let cur = 0, playing = true, timer = null, pTimer = null;
  function goTo(idx) {
    if (idx === cur) return;
    slides[cur].classList.remove('active'); slides[cur].classList.add('prev');
    dots[cur]?.classList.remove('active'); thumbs[cur]?.classList.remove('active');
    const old = cur;
    setTimeout(() => slides[old]?.classList.remove('prev'), TRANS);
    cur = ((idx % slides.length) + slides.length) % slides.length;
    slides[cur].classList.remove('prev'); slides[cur].classList.add('active');
    dots[cur]?.classList.add('active'); thumbs[cur]?.classList.add('active');
    if (curEl) curEl.textContent = cur + 1;
    const tw = document.getElementById('slideThumbs');
    if (tw && thumbs[cur]) tw.scrollTo({ left: thumbs[cur].offsetLeft - tw.offsetWidth/2 + thumbs[cur].offsetWidth/2, behavior:'smooth' });
    resetProg();
  }
  function resetProg() {
    if (!progBar) return;
    progBar.style.transition = 'none'; progBar.style.width = '0%';
    clearTimeout(pTimer);
    if (playing) pTimer = setTimeout(() => { progBar.style.transition = `width ${INTERVAL}ms linear`; progBar.style.width = '100%'; }, 50);
  }
  function play() { clearInterval(timer); timer = setInterval(() => goTo(cur+1), INTERVAL); playing = true; resetProg(); if (autoIcon) autoIcon.className = 'fas fa-pause'; autoBtn?.classList.remove('paused'); }
  function pause() { clearInterval(timer); clearTimeout(pTimer); playing = false; if (progBar) progBar.style.transition = 'none'; if (autoIcon) autoIcon.className = 'fas fa-play'; autoBtn?.classList.add('paused'); }
  prevBtn?.addEventListener('click', () => { goTo(cur-1); if (playing) { pause(); play(); } });
  nextBtn?.addEventListener('click', () => { goTo(cur+1); if (playing) { pause(); play(); } });
  autoBtn?.addEventListener('click', () => playing ? pause() : play());
  dots.forEach(d => d.addEventListener('click', () => { goTo(+d.dataset.i); if (playing) { pause(); play(); } }));
  thumbs.forEach(t => t.addEventListener('click', () => { goTo(+t.dataset.i); if (playing) { pause(); play(); } }));
  let tx = null;
  wrap.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, { passive:true });
  wrap.addEventListener('touchend', e => { if (tx === null) return; const dx = e.changedTouches[0].clientX - tx; if (Math.abs(dx) > 42) goTo(dx < 0 ? cur+1 : cur-1); tx = null; if (playing) { pause(); play(); } });
  document.addEventListener('keydown', e => { if (document.activeElement?.tagName === 'INPUT') return; if (e.key==='ArrowLeft') goTo(cur-1); if (e.key==='ArrowRight') goTo(cur+1); if (e.key===' ') playing ? pause() : play(); });
  wrap.addEventListener('mouseenter', pause);
  wrap.addEventListener('mouseleave', () => { if (!autoBtn?.classList.contains('paused')) play(); });
  if (slides.length > 1) play(); else resetProg();
})();
</script>
<?php endif; ?>

<!-- ══════════════════════════════════════════
     BODY SECTIONS
     ══════════════════════════════════════════ -->
<?php if ($is_customer): ?>
<!-- ════ CUSTOMER BODY ════ -->
<section class="features" id="membership">
  <div class="wrap">
    <div class="sec-head light reveal">
      <div class="eyebrow"><span></span>Thành viên của bạn</div>
      <h2>Tình trạng <span>gói tập</span> hiện tại</h2>
    </div>
    <div class="feat-grid">
      <div class="fc fc-wide reveal">
        <div class="fc-icon" style="--c:rgba(204,0,0,.15);--t:var(--red)"><i class="fas fa-id-card"></i></div>
        <?php if ($cus_active_plan): ?>
          <h3><?= htmlspecialchars($cus_active_plan['plan_name']) ?></h3>
          <p>Gói tập đang hoạt động — còn <strong style="color:var(--red)"><?= $cus_days_left ?> ngày</strong> (hết hạn <?= date('d/m/Y', strtotime($cus_active_plan['end_date'])) ?>).</p>
          <div style="height:5px;background:rgba(255,255,255,.07);border-radius:99px;margin:12px 0 6px;overflow:hidden">
            <div style="width:<?= $cus_plan_pct ?>%;height:100%;background:var(--red);border-radius:99px"></div>
          </div>
          <div class="fc-chips"><span><?= $cus_active_plan['duration_months'] ?> tháng</span><span><?= number_format($cus_active_plan['price'],0,',','.')?>₫</span><span style="color:#4ade80">Đang hoạt động</span></div>
        <?php else: ?>
          <h3>Chưa có gói tập</h3>
          <p>Bạn chưa đăng ký gói tập nào. Hãy liên hệ lễ tân để được tư vấn.</p>
          <div class="fc-chips"><span>Xem gói bên dưới</span></div>
        <?php endif; ?>
        <a href="Profile/Profile.php?tab=plans" class="fc-arr" style="text-decoration:none"><i class="fas fa-arrow-up-right-from-square"></i></a>
      </div>
      <div class="fc reveal">
        <div class="fc-icon" style="--c:rgba(34,197,94,.15);--t:#4ade80"><i class="fas fa-calendar-check"></i></div>
        <h3>Lịch sử check-in</h3>
        <p>Tổng cộng <strong style="color:#4ade80"><?= $cus_total_ci ?> lần</strong> check-in. Hãy duy trì thói quen mỗi ngày!</p>
        <div class="fc-chips"><span><?= $cus_total_ci ?> lần tổng cộng</span></div>
        <a href="Profile/Profile.php?tab=checkin" class="fc-arr" style="text-decoration:none"><i class="fas fa-arrow-up-right-from-square"></i></a>
      </div>
      <div class="fc reveal">
        <div class="fc-icon" style="--c:rgba(59,130,246,.15);--t:#60a5fa"><i class="fas fa-chalkboard-teacher"></i></div>
        <h3>Lớp tập sắp tới</h3>
        <?php if (!empty($cus_upcoming)): ?>
          <p>Bạn có <strong style="color:#60a5fa"><?= count($cus_upcoming) ?> lớp</strong> sắp diễn ra. Đừng bỏ lỡ!</p>
          <div class="fc-chips"><?php foreach($cus_upcoming as $cl): ?><span><?= htmlspecialchars($cl['class_name']) ?> — <?= (new DateTime($cl['start_time']))->format('d/m H:i') ?></span><?php endforeach; ?></div>
        <?php else: ?>
          <p>Chưa có lớp nào sắp tới. Liên hệ lễ tân để đặt lịch.</p>
          <div class="fc-chips"><span>Chưa có lịch</span></div>
        <?php endif; ?>
        <a href="Profile/Profile.php?tab=classes" class="fc-arr" style="text-decoration:none"><i class="fas fa-arrow-up-right-from-square"></i></a>
      </div>
      <div class="fc reveal">
        <div class="fc-icon" style="--c:rgba(168,85,247,.15);--t:#c084fc"><i class="fas fa-user-circle"></i></div>
        <h3>Hồ sơ cá nhân</h3>
        <p>Xem thông tin cá nhân, lịch sử hóa đơn và tất cả hoạt động của bạn.</p>
        <div class="fc-chips"><span>Thông tin</span><span>Hóa đơn</span><span>Đánh giá</span></div>
        <a href="Profile/Profile.php" class="fc-arr" style="text-decoration:none"><i class="fas fa-arrow-up-right-from-square"></i></a>
      </div>
    </div>
  </div>
</section>

<!-- Plans for customer -->
<?php if (!empty($plans)): ?>
<div class="shop-bar" style="background:#1a1a1a;border-bottom:1px solid rgba(255,255,255,.07)">
  <div class="shop-bar-inner">
    <span class="shop-bar-count" style="color:rgba(255,255,255,.4)">
        Hiển thị 1–<?= count($plans) ?> trong <?= $total_plans ?> gói tập
        <?php if ($cur_type_name): ?>
            &nbsp;·&nbsp;<span style="color:#4ade80;font-weight:600"><i class="fas fa-filter" style="font-size:.65rem"></i> Từ <?= htmlspecialchars($cur_type_name) ?> trở lên</span>
        <?php endif; ?>
    </span>
    <div class="shop-bar-right"></div>
  </div>
</div>
<section class="shop-section" id="schedule" style="background:#1a1a1a;padding-bottom:70px">
  <div class="shop-grid" id="plansGrid">
    <?php foreach($plans as $idx => $plan):
      $pt_name  = $plan['type_name']   ?? '';
      $pt_color = $plan['color_code']  ?? $fallback_colors[$idx % 4];
      $icon     = $type_icon_map[$pt_name] ?? $fallback_icons[$idx % 4];
      $bgcss    = $fallback_bgs[$idx % 4];
      $is_hot   = in_array($pt_name, ['Premium','VIP']);
      $old_price = round($plan['price'] * 1.2 / 1000000, 0) * 1000000;
    ?>
    <div class="product-card <?= $is_hot ? 'plan-hot' : '' ?> reveal"
         data-price="<?= $plan['price'] ?>"
         style="border-color:rgba(255,255,255,.1);<?= $is_hot ? 'border-color:'.htmlspecialchars($pt_color).';box-shadow:0 0 0 1px '.htmlspecialchars($pt_color).'40' : '' ?>">
      <div class="product-img-wrap">
        <?php if ($is_hot): ?>
          <span class="hot-badge" style="background:<?= htmlspecialchars($pt_color) ?>">
            <i class="fas fa-star"></i> <?= htmlspecialchars($pt_name ?: 'PHỔ BIẾN') ?>
          </span>
        <?php elseif ($pt_name): ?>
          <span class="type-badge" style="background:<?= htmlspecialchars($pt_color) ?>">
            <?= htmlspecialchars($pt_name) ?>
          </span>
        <?php endif; ?>
        <span class="sale-badge">SALE!</span>

        <?php if (!empty($plan['image_url'])): ?>
          <img src="<?= htmlspecialchars($plan['image_url']) ?>" alt="<?= htmlspecialchars($plan['plan_name']) ?>" class="product-real-img" loading="lazy"/>
          <div class="product-img-overlay" style="background:linear-gradient(to top,rgba(0,0,0,.7) 0%,rgba(0,0,0,.1) 60%)"></div>
        <?php else: ?>
          <div class="product-img-placeholder" style="background:<?= $bgcss ?>">
            <i class="<?= $icon ?>" style="font-size:48px;color:<?= htmlspecialchars($pt_color) ?>;opacity:.75"></i>
            <span style="font-family:'Barlow Condensed',sans-serif;font-size:10px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,.2);text-transform:uppercase"><?= $plan['duration_months'] ?> Tháng</span>
          </div>
        <?php endif; ?>
        <div class="product-img-dur" style="border-color:<?= htmlspecialchars($pt_color) ?>40;color:<?= htmlspecialchars($pt_color) ?>">
          <?= $plan['duration_months'] ?> THÁNG
        </div>
      </div>
      <div class="product-body" style="background:#222">
        <div class="product-duration" style="color:<?= htmlspecialchars($pt_color) ?>"><?= $plan['duration_months'] ?> THÁNG</div>
        <div class="product-name" style="color:#fff"><?= htmlspecialchars($plan['plan_name']) ?></div>
        <?php if($plan['description']): ?><div class="product-desc" style="color:rgba(255,255,255,.45)"><?= htmlspecialchars($plan['description']) ?></div><?php endif; ?>
        <?php if ($pt_name): ?>
        <div class="product-type-chip" style="border-color:<?= htmlspecialchars($pt_color) ?>40;color:<?= htmlspecialchars($pt_color) ?>">
          <i class="<?= $icon ?>" style="font-size:.7rem"></i> <?= htmlspecialchars($pt_name) ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="product-footer" style="border-top-color:rgba(255,255,255,.08);<?= $is_hot ? 'border-top:2px solid '.htmlspecialchars($pt_color).'60' : '' ?>">
        <div class="product-price-box">
          <div class="product-price-old"><?= number_format($old_price,0,',','.') ?>₫</div>
          <div class="product-price-new" style="color:<?= $is_hot ? htmlspecialchars($pt_color) : 'var(--red)' ?>"><?= number_format($plan['price'],0,',','.') ?><sub>₫</sub></div>
        </div>
        <a href="Payment/Payment.php" class="product-add-btn" style="<?= $is_hot ? 'background:'.htmlspecialchars($pt_color).';color:#fff' : '' ?>">
          <i class="fas fa-plus"></i>&nbsp;ĐĂNG KÝ
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- How to use (customer) -->
<section class="how" id="checkin">
  <div class="wrap">
    <div class="sec-head reveal">
      <div class="eyebrow"><span></span>Hướng dẫn</div>
      <h2>Tận dụng <span>tối đa</span> thành viên của bạn</h2>
    </div>
    <div class="steps">
      <div class="step reveal">
        <div class="step-hex"><svg viewBox="0 0 80 80"><polygon points="40,4 74,22 74,58 40,76 6,58 6,22" fill="rgba(204,0,0,.08)" stroke="var(--red)" stroke-width="1.5"/></svg><span>01</span></div>
        <h3>Check-in mỗi ngày</h3>
        <p>Trình thẻ thành viên tại quầy lễ tân khi vào tập. Hệ thống tự động ghi nhận thời gian.</p>
      </div>
      <div class="step-arr reveal"><i class="fas fa-chevron-right"></i></div>
      <div class="step reveal">
        <div class="step-hex"><svg viewBox="0 0 80 80"><polygon points="40,4 74,22 74,58 40,76 6,58 6,22" fill="rgba(204,0,0,.08)" stroke="var(--red)" stroke-width="1.5"/></svg><span>02</span></div>
        <h3>Đặt lịch lớp tập</h3>
        <p>Liên hệ lễ tân để đăng ký Yoga, HIIT, Zumba cùng HLV chuyên nghiệp.</p>
      </div>
      <div class="step-arr reveal"><i class="fas fa-chevron-right"></i></div>
      <div class="step reveal">
        <div class="step-hex"><svg viewBox="0 0 80 80"><polygon points="40,4 74,22 74,58 40,76 6,58 6,22" fill="rgba(204,0,0,.08)" stroke="var(--red)" stroke-width="1.5"/></svg><span>03</span></div>
        <h3>Theo dõi tiến độ</h3>
        <p>Vào "Hồ sơ của tôi" để xem lịch sử check-in, gói tập và hóa đơn bất cứ lúc nào.</p>
      </div>
    </div>
  </div>
</section>

<?php endif; ?>

<!-- ══ REVIEWS ══ -->
<?php include 'Review/reviews_section.php'; ?>

<!-- ══ FINAL CTA ══ -->
<section class="cta-final">
  <div class="ctaf-bg"><div class="cf-stripe"></div><div class="cf-glow"></div></div>
  <div class="wrap">
    <div class="ctaf-inner reveal">
      <?php if ($is_customer): ?>
        <div class="ctaf-ey">✦ Chào mừng trở lại, <?= $customer_name ?>!</div>
        <h2>HÃY DUY TRÌ<br><span>THÓI QUEN TẬP LUYỆN</span></h2>
        <p>Mỗi ngày tập luyện là một bước tiến. Xem hồ sơ và theo dõi tiến trình ngay hôm nay!</p>
        <div class="ctaf-btns">
          <a href="Profile/Profile.php" class="btn-gold ctaf-btn"><i class="fas fa-user-circle"></i> Hồ sơ của tôi</a>
          <a href="../Internal/Index/Login/logout.php" class="btn-ghost-gold ctaf-btn">Đăng xuất <i class="fas fa-sign-out-alt"></i></a>
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
          <?php if ($logo_url): ?>
            <img src="<?= $logo_url ?>" alt="Elite Gym Logo" class="nav-logo-img"/>
          <?php else: ?>
            <svg class="hex-logo" viewBox="0 0 44 44">
              <polygon points="22,2 40,12 40,32 22,42 4,32 4,12" fill="none" stroke="#cc0000" stroke-width="1.8"/>
              <text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" fill="#cc0000" font-size="12" font-weight="800" font-family="Barlow Condensed">EG</text>
            </svg>
            <div class="nav-brand"><span class="nb-main">ELITE</span><span class="nb-sub">GYM</span></div>
          <?php endif; ?>
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
          <a href="#schedule">Gói tập</a>
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
// Nav dropdown
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

// Plan sort
function sortPlans(val) {
  const grid  = document.getElementById('plansGrid');
  if (!grid) return;
  const cards = [...grid.querySelectorAll('.product-card')];
  cards.sort((a,b) => {
    if (val === 'price-asc')  return +a.dataset.price - +b.dataset.price;
    if (val === 'price-desc') return +b.dataset.price - +a.dataset.price;
    return 0;
  });
  cards.forEach(c => grid.appendChild(c));
}
</script>
<script src="Landing.js" defer></script>
 
<?php if ($is_customer): ?>
<!-- Toast container + Notification JS -->
<div class="toast-stack" id="toastStack"></div>
<?php include 'Review/notification_ui.php'; ?>
<?php endif; ?>
 
</body>
</html>