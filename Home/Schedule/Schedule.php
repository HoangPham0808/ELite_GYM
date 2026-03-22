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

$classes = $conn->query("
    SELECT tc.class_id, tc.class_name, tc.class_time,
           e.full_name AS trainer_name
    FROM TrainingClass tc
    LEFT JOIN Employee e ON e.employee_id = tc.trainer_id
    WHERE tc.class_time BETWEEN '$ws' AND '$we'
    ORDER BY tc.class_time ASC
")->fetch_all(MYSQLI_ASSOC);

$my_classes = [];
if ($cid) {
    $res = $conn->query("SELECT cr.class_id FROM ClassRegistration cr WHERE cr.customer_id = $cid");
    while ($row = $res->fetch_assoc()) $my_classes[] = (int)$row['class_id'];
}

$by_day = [];
foreach ($classes as $c) {
    $by_day[(new DateTime($c['class_time']))->format('Y-m-d')][] = $c;
}

$all_my = [];
if ($cid) {
    $all_my = $conn->query("
        SELECT tc.class_id, tc.class_name, tc.class_time, e.full_name AS trainer_name
        FROM ClassRegistration cr
        JOIN TrainingClass tc ON tc.class_id = cr.class_id
        LEFT JOIN Employee e ON e.employee_id = tc.trainer_id
        WHERE cr.customer_id = $cid
        ORDER BY tc.class_time ASC
    ")->fetch_all(MYSQLI_ASSOC);
}

$now_ts      = time();
$upcoming_my = array_filter($all_my, fn($c) => strtotime($c['class_time']) >= $now_ts);
$past_my     = array_filter($all_my, fn($c) => strtotime($c['class_time']) <  $now_ts);
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
      <svg class="hex-logo" viewBox="0 0 44 44">
        <polygon points="22,2 40,12 40,32 22,42 4,32 4,12" fill="none" stroke="#cc0000" stroke-width="1.8"/>
        <text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" fill="#cc0000" font-size="12" font-weight="800" font-family="Barlow Condensed">EG</text>
      </svg>
      <div class="nav-brand"><span class="nb-main">ELITE</span><span class="nb-sub">GYM</span></div>
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
        $day_dt = clone $week_start; $day_dt->modify("+{$d} days");
        $day_str = $day_dt->format('Y-m-d');
        $is_today = ($day_str === $today_str);
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
            $dt = new DateTime($c['class_time']);
            $is_mine = in_array((int)$c['class_id'], $my_classes);
            $is_past = $dt->getTimestamp() < $now_ts;
          ?>
          <div class="sch-class-card <?= $is_mine ? 'sch-class-card--mine' : '' ?> <?= $is_past ? 'sch-class-card--past' : '' ?>"
               data-class-id="<?= $c['class_id'] ?>">
            <div class="sch-class-time"><i class="fas fa-clock"></i> <?= $dt->format('H:i') ?></div>
            <div class="sch-class-name"><?= htmlspecialchars($c['class_name']) ?></div>
            <?php if ($c['trainer_name']): ?><div class="sch-class-trainer"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['trainer_name']) ?></div><?php endif; ?>
            <?php if (!$is_past): ?>
              <?php if ($is_mine): ?>
                <button class="sch-btn-cancel" data-class-id="<?= $c['class_id'] ?>">
                  <i class="fas fa-times"></i> Hủy
                </button>
              <?php else: ?>
                <button class="sch-btn-register" data-class-id="<?= $c['class_id'] ?>">
                  <i class="fas fa-plus"></i> Đăng ký
                </button>
              <?php endif; ?>
            <?php else: ?>
              <div class="sch-class-badge sch-badge--past"><i class="fas fa-check"></i> Đã qua</div>
            <?php endif; ?>
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
          $dt = new DateTime($c['class_time']);
          $days_until = max(0, (int)(($dt->getTimestamp() - $now_ts) / 86400));
        ?>
        <div class="sch-list-card sch-list-card--upcoming">
          <div class="sch-list-card-side"></div>
          <div class="sch-list-card-body">
            <div class="sch-list-card-top">
              <div>
                <div class="sch-list-class-name"><?= htmlspecialchars($c['class_name']) ?></div>
                <?php if ($c['trainer_name']): ?><div class="sch-list-trainer"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['trainer_name']) ?></div><?php endif; ?>
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
          $dt = new DateTime($c['class_time']);
        ?>
        <div class="sch-list-card sch-list-card--past">
          <div class="sch-list-card-side"></div>
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
                <span><i class="fas fa-clock"></i> <?= $dt->format('H:i') ?></span>
              </div>
              <div class="sch-list-reg-badge" style="background:rgba(255,255,255,.05);color:rgba(255,255,255,.35);border:1px solid rgba(255,255,255,.08)"><i class="fas fa-check"></i> Đã hoàn thành</div>
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
          <svg class="hex-logo" viewBox="0 0 44 44">
            <polygon points="22,2 40,12 40,32 22,42 4,32 4,12" fill="none" stroke="#cc0000" stroke-width="1.8"/>
            <text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" fill="#cc0000" font-size="12" font-weight="800" font-family="Barlow Condensed">EG</text>
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
<script src="Schedule.js"></script>
</body>
</html>
