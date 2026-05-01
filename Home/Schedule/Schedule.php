<?php
ob_start();
session_start();

// Vị trí: Home/Schedule/Schedule.php
if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header("Location: ../../Internal/Index/Login/Login.php");
    exit;
}

require_once '../../Database/db.php';

$customer_name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? '');
$account_id    = (int)$_SESSION['account_id'];

$r = $conn->prepare("SELECT customer_id FROM Customer WHERE account_id = ? LIMIT 1");
$r->bind_param("i", $account_id);
$r->execute();
$cid = (int)($r->get_result()->fetch_assoc()['customer_id'] ?? 0);
$r->close();

$week_offset = (int)($_GET['week'] ?? 0);
$today       = new DateTime();
$week_start  = clone $today;
$week_start->modify('monday this week');
$week_start->modify("{$week_offset} week");
$week_end = clone $week_start;
$week_end->modify('+6 days');
$ws = $week_start->format('Y-m-d');
$we = $week_end->format('Y-m-d 23:59:59');

// ── Lấy PackageType của khách (gói active hiện tại) ──────────
// Ưu tiên gói cao nhất (sort_order lớn nhất) nếu có nhiều gói active
$customer_package = null;
if ($cid) {
    $pkg = $conn->query("
        SELECT pt.type_id, pt.type_name, pt.sort_order, pt.color_code
        FROM MembershipRegistration mr
        JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
        JOIN PackageType pt ON pt.type_id = mp.package_type_id
        WHERE mr.customer_id = $cid
          AND mr.status = 'active'
          AND mr.end_date >= CURDATE()
        ORDER BY pt.sort_order DESC
        LIMIT 1
    ");
    if ($pkg && $pkg->num_rows > 0) {
        $customer_package = $pkg->fetch_assoc();
    }
}
$customer_sort_order = $customer_package ? (int)$customer_package['sort_order'] : 0;

// ── Lịch tuần: chỉ lấy lớp ở phòng có sort_order ≤ gói khách ─
// Nếu chưa có gói → $classes rỗng (UI sẽ ẩn hoàn toàn lịch)
$classes = [];
if ($customer_package) {
    $classes = $conn->query("
        SELECT tc.class_id, tc.class_name, tc.start_time,
               e.full_name AS trainer_name,
               gr.room_name, gr.room_id,
               pt.type_name AS room_package_type,
               pt.sort_order AS room_sort_order,
               pt.color_code AS room_type_color
        FROM TrainingClass tc
        LEFT JOIN Employee e    ON e.employee_id  = tc.trainer_id
        LEFT JOIN GymRoom gr    ON gr.room_id     = tc.room_id
        LEFT JOIN PackageType pt ON pt.type_id    = gr.package_type_id
        WHERE tc.start_time BETWEEN '$ws' AND '$we'
          AND tc.trainer_id IS NOT NULL
          AND (
              pt.sort_order IS NULL
              OR pt.sort_order <= $customer_sort_order
          )
        ORDER BY tc.start_time ASC, COALESCE(pt.sort_order, 0) ASC
    ")->fetch_all(MYSQLI_ASSOC);
}

$my_classes = [];
if ($cid) {
    $res = $conn->query("SELECT cr.class_id FROM ClassRegistration cr WHERE cr.customer_id = $cid");
    while ($row = $res->fetch_assoc()) $my_classes[] = (int)$row['class_id'];
}

// ── Slot đã đăng ký trong tuần này: map start_time → class_id ──
// Dùng để detect trùng giờ (cùng start_time) trong cùng ngày
// Key: 'Y-m-d H:i' (làm tròn theo giờ:phút), value: class_id đã đăng ký
$booked_slots = [];
if ($cid && $customer_package) {
    $bk = $conn->query("
        SELECT tc.start_time, tc.end_time, cr.class_id
        FROM ClassRegistration cr
        JOIN TrainingClass tc ON tc.class_id = cr.class_id
        WHERE cr.customer_id = $cid
          AND tc.start_time BETWEEN '$ws' AND '$we'
    ");
    while ($row = $bk->fetch_assoc()) {
        // key = 'Y-m-d H:i' của slot đã đăng ký
        $slot_key = (new DateTime($row['start_time']))->format('Y-m-d H:i');
        $booked_slots[$slot_key] = (int)$row['class_id'];
    }
}

$by_day = [];
foreach ($classes as $c) {
    $by_day[(new DateTime($c['start_time']))->format('Y-m-d')][] = $c;
}

$all_my = [];
if ($cid) {
    $all_my = $conn->query("
        SELECT tc.class_id, tc.class_name, tc.start_time, tc.end_time,
               e.full_name AS trainer_name,
               gr.room_name,
               pt.type_name AS room_package_type,
               pt.color_code AS room_type_color
        FROM ClassRegistration cr
        JOIN TrainingClass tc  ON tc.class_id     = cr.class_id
        LEFT JOIN Employee e   ON e.employee_id   = tc.trainer_id
        LEFT JOIN GymRoom gr   ON gr.room_id      = tc.room_id
        LEFT JOIN PackageType pt ON pt.type_id    = gr.package_type_id
        WHERE cr.customer_id = $cid
        ORDER BY tc.start_time ASC
    ")->fetch_all(MYSQLI_ASSOC);
}

// ── Lấy tất cả check-in của khách để xác nhận tham dự ────────
$checkin_times = [];
if ($cid) {
    $ci = $conn->query("
        SELECT check_time FROM GymCheckIn
        WHERE customer_id = $cid AND type = 'checkin'
        ORDER BY check_time ASC
    ");
    if ($ci) {
        while ($row = $ci->fetch_assoc()) {
            $checkin_times[] = strtotime($row['check_time']);
        }
    }
}

// Kiểm tra có check-in nằm trong [start_time, end_time] của buổi tập không
function hasCheckinInWindow(array $checkin_times, string $start, ?string $end): bool {
    $s = strtotime($start);
    $e = $end ? strtotime($end) : ($s + 7200);
    foreach ($checkin_times as $ts) {
        if ($ts >= $s && $ts <= $e) return true;
    }
    return false;
}

$now_ts      = time();
$upcoming_my = array_filter($all_my, fn($c) => strtotime($c['start_time']) >= $now_ts);
$past_my     = array_filter($all_my, fn($c) => strtotime($c['start_time']) <  $now_ts);

// ── Lấy logo từ DB ────────────────────────────────────────────
$logo_row = $conn->query("
    SELECT file_url FROM landing_images
    WHERE image_name = 'Logo_ELITY'
    LIMIT 1
")->fetch_assoc();
$logo_url = $logo_row ? htmlspecialchars($logo_row['file_url']) : '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Lịch lớp tập — Elite Gym</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
      crossorigin="anonymous" referrerpolicy="no-referrer" id="fa-cdn" media="print" onload="this.media='all'"/>
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous"/></noscript>
<link rel="stylesheet" href="../Landing.css"/>
<link rel="stylesheet" href="Schedule.css"/>
</head>
<body>

<div class="cursor-glow" id="cursorGlow"></div>

<!-- ══ NAVBAR — giống index.php customer nav ══ -->
<header class="nav scrolled" id="nav">
  <div class="nav-inner">
    <a href="../index.php" class="nav-logo">
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
      <a href="../index.php">Trang chủ</a>
      <a href="../index.php#schedule">Gói tập</a>
      <a href="Schedule.php" class="nav-active">Lớp tập</a>
      <a href="../index.php#checkin">Hướng dẫn</a>
      <a href="../index.php#reviews">Đánh giá</a>
    </nav>
    <div class="nav-actions">
      <div class="nav-user-wrap" id="navUserWrap">
        <button class="nav-user-btn" id="navUserBtn">
          <div class="nav-avatar"><?= mb_strtoupper(mb_substr($customer_name, 0, 1)) ?></div>
          <span class="nav-user-name"><?= $customer_name ?></span>
          <i class="fas fa-chevron-down nav-chevron"></i>
        </button>
        <div class="nav-dropdown" id="navDropdown">
          <a href="../Profile/Profile.php"><i class="fas fa-user-circle"></i> Hồ sơ của tôi</a>
          <a href="../Profile/Profile.php?tab=checkin"><i class="fas fa-calendar-check"></i> Lịch sử check-in</a>
          <a href="../Profile/Profile.php?tab=plans"><i class="fas fa-id-card"></i> Gói tập của tôi</a>
          <div style="border-top:1px solid rgba(255,255,255,.07);margin:4px 0"></div>
          <a href="../../Internal/Index/Login/logout.php" class="nd-logout"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
        </div>
      </div>
    </div>
    <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
  </div>
  <div class="mobile-menu" id="mobileMenu">
    <a href="../index.php">Trang chủ</a>
    <a href="../index.php#schedule">Gói tập</a>
    <a href="Schedule.php">Lớp tập</a>
    <a href="../index.php#checkin">Hướng dẫn</a>
    <a href="../index.php#reviews">Đánh giá</a>
    <a href="../Profile/Profile.php" class="mm-cta"><i class="fas fa-user-circle"></i> Hồ sơ của tôi</a>
    <a href="../../Internal/Index/Login/logout.php" class="mm-login" style="color:#f87171"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
  </div>
</header>

<!-- ══ HERO ══ -->
<section class="sch-hero">
  <div class="sch-hero-bg"><div class="sch-hex-grid"></div><div class="sch-radial"></div></div>
  <div class="wrap sch-hero-inner">
    <div class="sch-hero-text">
      <div class="sch-eyebrow"><span></span>Lịch lớp tập</div>
      <h1 class="sch-title">KHÁM PHÁ <span>LỚP TẬP</span><br>CỦA BẠN</h1>
      <p class="sch-subtitle">Xem lịch các buổi tập, theo dõi lớp đã đăng ký và lên kế hoạch tuần tập luyện hiệu quả.</p>
    </div>
    <div class="sch-hero-stats">
      <div class="sch-stat"><div class="sch-stat-n"><?= count($all_my) ?></div><div class="sch-stat-l">Đã đăng ký</div></div>
      <div class="sch-stat-sep"></div>
      <div class="sch-stat"><div class="sch-stat-n"><?= count($upcoming_my) ?></div><div class="sch-stat-l">Sắp tới</div></div>
      <div class="sch-stat-sep"></div>
      <div class="sch-stat"><div class="sch-stat-n"><?= count($classes) ?></div><div class="sch-stat-l">Tuần này</div></div>
      <?php if ($customer_package): ?>
      <div class="sch-stat-sep"></div>
      <div class="sch-stat">
        <div class="sch-stat-n" style="font-size:1rem;color:<?= htmlspecialchars($customer_package['color_code'] ?? '#cc0000') ?>">
          <i class="fas fa-id-card"></i> <?= htmlspecialchars($customer_package['type_name']) ?>
        </div>
        <div class="sch-stat-l">Gói hiện tại</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ══ TABS ══ -->
<div class="sch-tabs-wrap">
  <div class="wrap">
    <div class="sch-tabs">
      <button class="sch-tab active" data-tab="week"><i class="fas fa-calendar-week"></i> Lịch tuần</button>
      <button class="sch-tab" data-tab="my"><i class="fas fa-bookmark"></i> Lớp của tôi <span class="sch-badge"><?= count($upcoming_my) ?></span></button>
    </div>
  </div>
</div>

<!-- ══ PANEL: LỊCH TUẦN ══ -->
<div class="sch-panel active" id="panel-week">
  <div class="wrap">

  <?php if (!$customer_package): ?>
  <!-- ── Chưa có gói: hiện wall mua gói ── -->
  <div class="sch-locked-wall">
    <div class="sch-locked-icon"><i class="fas fa-lock"></i></div>
    <h2 class="sch-locked-title">Bạn chưa có gói tập</h2>
    <p class="sch-locked-desc">Để xem lịch và đăng ký lớp tập, bạn cần mua một gói tập phù hợp.<br>Mỗi gói sẽ mở khóa các phòng tập và lớp học tương ứng.</p>
    <a href="../index.php#schedule" class="sch-locked-cta">
      <i class="fas fa-shopping-cart"></i> Xem các gói tập
    </a>
    <div class="sch-locked-pkg-list">
      <div class="sch-locked-pkg-item" style="border-color:#6b7280"><span style="color:#6b7280">Basic</span> — Phòng tập chính</div>
      <div class="sch-locked-pkg-item" style="border-color:#3b82f6"><span style="color:#3b82f6">Standard</span> — Phòng tập + lớp nhóm</div>
      <div class="sch-locked-pkg-item" style="border-color:#d4a017"><span style="color:#d4a017">Premium</span> — Toàn bộ phòng &amp; thiết bị</div>
      <div class="sch-locked-pkg-item" style="border-color:#a855f7"><span style="color:#a855f7">VIP</span> — Full access + HLV cá nhân</div>
    </div>
  </div>

  <?php else: ?>
  <!-- ── Có gói: hiện lịch tuần bình thường ── -->
    <div class="sch-week-nav">
      <a href="?week=<?= $week_offset - 1 ?>" class="sch-week-btn"><i class="fas fa-chevron-left"></i></a>
      <div class="sch-week-label">
        <span class="sch-week-range"><?= $week_start->format('d/m') ?> — <?= $week_end->format('d/m/Y') ?></span>
        <?php if ($week_offset === 0): ?>
          <span class="sch-week-tag">Tuần này</span>
        <?php elseif ($week_offset === 1): ?>
          <span class="sch-week-tag" style="background:rgba(96,165,250,.12);color:#60a5fa;border-color:rgba(96,165,250,.3)">Tuần tới</span>
        <?php elseif ($week_offset < 0): ?>
          <span class="sch-week-tag" style="background:rgba(255,255,255,.05);color:rgba(255,255,255,.4);border-color:rgba(255,255,255,.1)">Tuần trước</span>
        <?php endif; ?>
      </div>
      <a href="?week=<?= $week_offset + 1 ?>" class="sch-week-btn"><i class="fas fa-chevron-right"></i></a>
    </div>

    <?php $vi_days = ['Thứ 2','Thứ 3','Thứ 4','Thứ 5','Thứ 6','Thứ 7','CN']; $today_str = (new DateTime())->format('Y-m-d'); ?>
    <div class="sch-week-grid">
      <?php for ($d = 0; $d < 7; $d++):
        $day_dt      = clone $week_start; $day_dt->modify("+{$d} days");
        $day_str     = $day_dt->format('Y-m-d');
        $is_today    = ($day_str === $today_str);
        $day_classes = $by_day[$day_str] ?? [];
      ?>
      <div class="sch-day <?= $is_today ? 'sch-day--today' : '' ?> <?= empty($day_classes) ? 'sch-day--empty' : '' ?>">
        <div class="sch-day-head">
          <span class="sch-day-name"><?= $vi_days[$d] ?></span>
          <span class="sch-day-num <?= $is_today ? 'sch-day-num--today' : '' ?>"><?= $day_dt->format('d') ?></span>
        </div>
        <div class="sch-day-body">
          <?php if (empty($day_classes)): ?>
            <div class="sch-no-class"><i class="fas fa-moon"></i> Nghỉ</div>
          <?php else: foreach ($day_classes as $c):
            $dt         = new DateTime($c['start_time']);
            $slot_key   = $dt->format('Y-m-d H:i');
            $is_mine    = in_array((int)$c['class_id'], $my_classes);
            $is_past    = $dt->getTimestamp() < $now_ts;
            $room_color = $c['room_type_color'] ?? '#6b7280';
            $room_type  = $c['room_package_type'] ?? null;

            // Trùng time slot: cùng giờ đã có lớp khác đã đăng ký
            $conflict_id  = $booked_slots[$slot_key] ?? 0;
            $slot_conflict = $conflict_id && !$is_mine; // trùng giờ, không phải lớp của mình
          ?>
          <div class="sch-class-card
            <?= $is_mine       ? 'sch-class-card--mine'     : '' ?>
            <?= $is_past       ? 'sch-class-card--past'     : '' ?>
            <?= $slot_conflict ? 'sch-class-card--conflict'  : '' ?>"
               data-class-id="<?= $c['class_id'] ?>"
               data-slot-key="<?= htmlspecialchars($slot_key) ?>"
               <?= $slot_conflict ? 'style="display:none"' : '' ?>>

            <?php if ($room_type): ?>
              <div class="sch-card-pkg-stripe" style="background:<?= $room_color ?>"></div>
            <?php endif; ?>

            <div class="sch-card-inner">
              <div class="sch-class-time">
                <i class="fas fa-clock"></i> <?= $dt->format('H:i') ?>
                <?php if (!empty($c['end_time'])): ?>
                  <span class="sch-time-end">– <?= (new DateTime($c['end_time']))->format('H:i') ?></span>
                <?php endif; ?>
              </div>

              <div class="sch-class-name"><?= htmlspecialchars($c['class_name']) ?></div>

              <div class="sch-card-meta">
                <?php if ($c['trainer_name']): ?>
                  <div class="sch-meta-row"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['trainer_name']) ?></div>
                <?php endif; ?>
                <?php if ($c['room_name']): ?>
                  <div class="sch-meta-row"><i class="fas fa-door-open"></i> <?= htmlspecialchars($c['room_name']) ?></div>
                <?php endif; ?>
              </div>

              <?php if ($room_type): ?>
                <div class="sch-class-pkg-tag" style="background:<?= $room_color ?>18;color:<?= $room_color ?>;border-color:<?= $room_color ?>44">
                  <?= htmlspecialchars($room_type) ?>
                </div>
              <?php endif; ?>

              <?php if (!$is_past): ?>
                <?php if ($is_mine): ?>
                  <button class="sch-btn-cancel" data-class-id="<?= $c['class_id'] ?>">
                    <i class="fas fa-times"></i> Hủy
                  </button>
                <?php elseif ($slot_conflict): ?>
                  <div class="sch-btn-conflict"
                       title="Bạn đã đăng ký lớp khác cùng giờ này. Hủy lớp đó trước để đổi sang lớp này.">
                    <i class="fas fa-clock"></i> Trùng giờ
                  </div>
                <?php else: ?>
                  <button class="sch-btn-register" data-class-id="<?= $c['class_id'] ?>">
                    <i class="fas fa-plus"></i> Đăng ký
                  </button>
                <?php endif; ?>
              <?php else: ?>
                <div class="sch-class-badge sch-badge--past"><i class="fas fa-check"></i> Đã qua</div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <?php endfor; ?>
    </div>

    <div class="sch-legend">
      <div class="sch-legend-item"><span class="sch-legend-dot sch-legend-dot--mine"></span> Lớp đã đăng ký</div>
      <div class="sch-legend-item"><span class="sch-legend-dot"></span> Lớp chưa đăng ký</div>
    </div>
  <?php endif; /* end if $customer_package */ ?>
  </div>
</div>

<!-- ══ PANEL: LỚP CỦA TÔI ══ -->
<div class="sch-panel" id="panel-my">
  <div class="wrap">
    <?php if (empty($all_my)): ?>
    <div class="sch-empty">
      <div class="sch-empty-icon"><i class="fas fa-calendar-xmark"></i></div>
      <h3>Chưa có lớp nào</h3>
      <p>Bạn chưa đăng ký lớp tập nào. Hãy xem lịch tuần và đăng ký lớp phù hợp.</p>
      <a href="#" onclick="document.querySelector('[data-tab=week]').click();return false;" class="btn-gold" style="display:inline-flex;margin-top:8px"><i class="fas fa-calendar-week"></i> Xem lịch tuần</a>
    </div>
    <?php else: ?>

    <?php if (!empty($upcoming_my)): ?>
    <div class="sch-list-section">
      <div class="sch-list-head"><i class="fas fa-bolt" style="color:var(--red)"></i> Lớp sắp tới <span class="sch-list-count"><?= count($upcoming_my) ?></span></div>
      <div class="sch-list-grid">
        <?php foreach ($upcoming_my as $c):
          $dt = new DateTime($c['start_time']);
          $days_until = max(0, (int)(($dt->getTimestamp() - $now_ts) / 86400));
        ?>
        <div class="sch-list-card sch-list-card--upcoming">
          <div class="sch-list-card-side"></div>
          <div class="sch-list-card-body">
            <div class="sch-list-card-top">
              <div>
                <div class="sch-list-class-name"><?= htmlspecialchars($c['class_name']) ?></div>
                <?php if ($c['trainer_name']): ?><div class="sch-list-trainer"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['trainer_name']) ?></div><?php endif; ?>
                <?php if (!empty($c['room_name'])): ?><div class="sch-list-trainer"><i class="fas fa-door-open"></i> <?= htmlspecialchars($c['room_name']) ?></div><?php endif; ?>
                <?php if (!empty($c['room_package_type'])): ?>
                  <div class="sch-class-pkg-tag" style="margin-top:4px;background:<?= $c['room_type_color'] ?? '#6b728022' ?>22;color:<?= $c['room_type_color'] ?? '#6b7280' ?>;border-color:<?= $c['room_type_color'] ?? '#6b7280' ?>55">
                    <?= htmlspecialchars($c['room_package_type']) ?>
                  </div>
                <?php endif; ?>
              </div>
              <div class="sch-list-meta">
                <?php if ($days_until === 0): ?><span class="sch-tag sch-tag--today">Hôm nay</span>
                <?php elseif ($days_until === 1): ?><span class="sch-tag sch-tag--soon">Ngày mai</span>
                <?php else: ?><span class="sch-tag">Còn <?= $days_until ?> ngày</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="sch-list-card-foot">
              <div class="sch-list-dt">
                <span><i class="fas fa-calendar"></i> <?= $dt->format('d/m/Y') ?></span>
                <span><i class="fas fa-clock"></i> <?= $dt->format('H:i') ?></span>
              </div>
              <button class="sch-btn-cancel-list" data-class-id="<?= $c['class_id'] ?>">
                <i class="fas fa-times-circle"></i> Hủy đăng ký
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($past_my)): ?>
    <div class="sch-list-section" style="margin-top:40px">
      <div class="sch-list-head" style="opacity:.7"><i class="fas fa-history" style="color:rgba(255,255,255,.4)"></i> Đã qua <span class="sch-list-count" style="background:rgba(255,255,255,.05);color:rgba(255,255,255,.4)"><?= count($past_my) ?></span></div>
      <div class="sch-list-grid">
        <?php foreach (array_reverse($past_my) as $c):
          $dt = new DateTime($c['start_time']);
          $attended = hasCheckinInWindow($checkin_times, $c['start_time'], $c['end_time'] ?? null);
        ?>
        <div class="sch-list-card sch-list-card--past <?= $attended ? 'sch-list-card--attended' : 'sch-list-card--absent' ?>">
          <div class="sch-list-card-side" style="<?= $attended ? 'background:rgba(34,197,94,.7)' : 'background:rgba(239,68,68,.5)' ?>"></div>
          <div class="sch-list-card-body">
            <div class="sch-list-card-top">
              <div>
                <div class="sch-list-class-name"><?= htmlspecialchars($c['class_name']) ?></div>
                <?php if ($c['trainer_name']): ?><div class="sch-list-trainer"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['trainer_name']) ?></div><?php endif; ?>
              </div>
            </div>
            <div class="sch-list-card-foot">
              <div class="sch-list-dt">
                <span><i class="fas fa-calendar"></i> <?= $dt->format('d/m/Y') ?></span>
                <span><i class="fas fa-clock"></i> <?= $dt->format('H:i') ?>
                  <?php if (!empty($c['end_time'])): ?>
                    <span style="color:rgba(255,255,255,.35)">– <?= (new DateTime($c['end_time']))->format('H:i') ?></span>
                  <?php endif; ?>
                </span>
              </div>
              <?php if ($attended): ?>
                <div class="sch-list-reg-badge" style="background:rgba(34,197,94,.1);color:#4ade80;border:1px solid rgba(34,197,94,.3)">
                  <i class="fas fa-circle-check"></i> Đã tham dự
                </div>
              <?php else: ?>
                <div class="sch-list-reg-badge" style="background:rgba(239,68,68,.08);color:#f87171;border:1px solid rgba(239,68,68,.25)">
                  <i class="fas fa-circle-xmark"></i> Vắng mặt
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <div class="sch-contact-cta">
      <i class="fas fa-headset"></i>
      <div>
        <div style="font-weight:700;font-size:.95rem;margin-bottom:4px;color:#fff">Muốn đăng ký thêm lớp?</div>
        <div style="font-size:.83rem;color:rgba(255,255,255,.4)">Liên hệ lễ tân hoặc nhân viên phòng tập để được tư vấn và đặt lịch.</div>
      </div>
    </div>
  </div>
</div>

<!-- ══ FOOTER — giống index.php ══ -->
<footer class="footer">
  <div class="wrap">
    <div class="footer-grid">
      <div class="fg-brand">
        <a href="../index.php" class="nav-logo" style="margin-bottom:14px;display:inline-flex">
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
        <h4>Tài khoản</h4>
        <a href="../Profile/Profile.php">Hồ sơ của tôi</a>
        <a href="../Profile/Profile.php?tab=plans">Gói tập</a>
        <a href="../Profile/Profile.php?tab=checkin">Check-in</a>
        <a href="../../Internal/Index/Login/logout.php" style="color:#f87171">Đăng xuất</a>
      </div>
      <div class="fg-col">
        <h4>Thông tin</h4>
        <a href="../index.php">Trang chủ</a>
        <a href="../index.php#schedule">Gói tập</a>
        <a href="Schedule.php">Lớp tập</a>
        <a href="../index.php#reviews">Đánh giá</a>
        <a href="#">Liên hệ</a>
      </div>
    </div>
    <div class="footer-btm"><span>© 2026 <strong>Elite Gym</strong>. All rights reserved.</span></div>
  </div>
</footer>

<!-- Toast -->
<div class="sch-toast" id="schToast">
  <i class="fas fa-check-circle" id="schToastIcon"></i>
  <span id="schToastMsg"></span>
</div>

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

// Hamburger — giống Landing.js
const hamburger  = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');
if (hamburger && mobileMenu) {
  hamburger.addEventListener('click', () => {
    mobileMenu.classList.toggle('open');
    const spans = hamburger.querySelectorAll('span');
    const isOpen = mobileMenu.classList.contains('open');
    if (isOpen) {
      spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
      spans[1].style.opacity = '0';
      spans[2].style.transform = 'rotate(-45deg) translate(5px, -5px)';
    } else {
      spans.forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
    }
  });
  mobileMenu.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
      mobileMenu.classList.remove('open');
      hamburger.querySelectorAll('span').forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
    });
  });
}
</script>

<!-- ══ AI WORKOUT PLANNER MODAL ══ -->
<div class="ai-modal-overlay" id="aiPlannerOverlay">
  <div class="ai-modal" id="aiPlannerModal">

    <button class="ai-modal-close" id="aiModalClose" title="Đóng">
      <i class="fas fa-times"></i>
    </button>

    <!-- HEADER CLASS INFO -->
    <div class="ai-modal-header">
      <div class="ai-modal-pkg-stripe" id="aiPkgStripe"></div>
      <div class="ai-modal-header-body">
        <span class="ai-modal-label">Thông tin buổi tập</span>
        <h2 class="ai-modal-title" id="aiClassName">—</h2>
        <div class="ai-modal-meta">
          <span id="aiClassTime"><i class="fas fa-clock"></i> —</span>
          <span id="aiClassTrainer"><i class="fas fa-user-tie"></i> —</span>
          <span id="aiClassRoom"><i class="fas fa-door-open"></i> —</span>
        </div>
        <div id="aiPkgTag" class="ai-pkg-tag" style="display:none"></div>
      </div>
    </div>

    <!-- BODY: 2 col -->
    <div class="ai-modal-body">

      <!-- LEFT: BMI form -->
      <div class="ai-modal-col ai-modal-col--form">
        <p class="ai-section-label"><i class="fas fa-person"></i> Thông số cơ thể</p>

        <div class="ai-form-row">
          <div class="ai-form-group">
            <label>Chiều cao (cm)</label>
            <input type="number" id="aiHeight" placeholder="170" min="100" max="250">
          </div>
          <div class="ai-form-group">
            <label>Cân nặng (kg)</label>
            <input type="number" id="aiWeight" placeholder="65" min="30" max="200">
          </div>
        </div>
        <div class="ai-form-row">
          <div class="ai-form-group">
            <label>Tuổi</label>
            <input type="number" id="aiAge" placeholder="25" min="10" max="100">
          </div>
          <div class="ai-form-group">
            <label>Giới tính</label>
            <select id="aiGender">
              <option value="male">Nam</option>
              <option value="female">Nữ</option>
            </select>
          </div>
        </div>
        <div class="ai-form-group">
          <label>Mục tiêu</label>
          <select id="aiGoal">
            <option value="lose_fat">🔥 Giảm mỡ</option>
            <option value="build_muscle">💪 Tăng cơ</option>
            <option value="endurance">🏃 Tăng sức bền</option>
            <option value="maintain">⚖️ Duy trì thể hình</option>
          </select>
        </div>

        <!-- BMI result chips -->
        <div class="ai-bmi-chips" id="aiBmiChips" style="display:none">
          <div class="ai-bmi-chip ai-bmi-chip--gold">
            <span class="ai-chip-val" id="aiBmiVal">—</span>
            <span class="ai-chip-lbl">BMI</span>
          </div>
          <div class="ai-bmi-chip">
            <span class="ai-chip-val ai-chip-val--sm" id="aiBmiCat">—</span>
            <span class="ai-chip-lbl">Phân loại</span>
          </div>
          <div class="ai-bmi-chip ai-bmi-chip--gold">
            <span class="ai-chip-val" id="aiBurnTarget">—</span>
            <span class="ai-chip-lbl">Calo cần đốt</span>
          </div>
        </div>

        <button class="ai-gen-btn" id="aiGenBtn" disabled>
          <i class="fas fa-bolt"></i> Tạo lịch tập AI
        </button>
      </div>

      <!-- RIGHT: AI output -->
      <div class="ai-modal-col ai-modal-col--output">
        <p class="ai-section-label"><i class="fas fa-robot"></i> Lịch tập được tạo bởi AI</p>
        <div class="ai-output-box" id="aiOutputBox">
          <div class="ai-output-empty">
            <i class="fas fa-dumbbell"></i>
            <p>Nhập thông số cơ thể và nhấn<br><strong>Tạo lịch tập AI</strong> để bắt đầu</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<script>
  window.CURRENT_ACCOUNT_ID = <?= (int)$_SESSION['account_id'] ?>;
</script>
<script src="ai_stream.js"></script>
<script src="Schedule.js"></script>
</body>
</html>