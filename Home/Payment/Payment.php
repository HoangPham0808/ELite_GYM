<?php
ob_start();
session_start();

if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header("Location: ../Internal/Index/Login/Login.php");
    exit;
}

require_once '../../Database/db.php';

$account_id    = (int)$_SESSION['account_id'];
$customer_name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? '');
$today         = date('Y-m-d');

$r = $conn->prepare("SELECT customer_id, email FROM Customer WHERE account_id = ? LIMIT 1");
$r->bind_param("i", $account_id);
$r->execute();
$cus = $r->get_result()->fetch_assoc();
$r->close();

if (!$cus) { header("Location: ../index.php"); exit; }
$cid = (int)$cus['customer_id'];

// Gói đang active của khách
$activeRes = $conn->query("
    SELECT mr.end_date, mp.plan_name, mp.price,
           pt.type_name, pt.color_code, pt.sort_order
    FROM MembershipRegistration mr
    JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
    LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
    WHERE mr.customer_id = $cid
      AND mr.status      = 'active'
      AND mr.end_date   >= '$today'
    ORDER BY pt.sort_order DESC
    LIMIT 1
");
$active_plan = $activeRes ? $activeRes->fetch_assoc() : null;
$days_left = 0;
if ($active_plan) {
    $days_left = max(0, (new DateTime($today))->diff(new DateTime($active_plan['end_date']))->days);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Mua Gói Tập — Elite Gym</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous"/>
<link rel="stylesheet" href="Payment.css"/>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<header class="pay-nav" id="payNav">
  <div class="pay-nav-inner">
    <a href="../index.php" class="nav-logo">
      <svg viewBox="0 0 44 44" width="34" height="34">
        <polygon points="22,2 40,12 40,32 22,42 4,32 4,12" fill="none" stroke="#cc0000" stroke-width="1.8"/>
        <text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" fill="#cc0000" font-size="12" font-weight="800" font-family="Barlow Condensed">EG</text>
      </svg>
      <div style="display:flex;flex-direction:column;line-height:1.1">
        <span style="font-family:'Barlow Condensed',sans-serif;font-size:20px;font-weight:900;color:#fff;letter-spacing:1px">ELITE</span>
        <span style="font-family:'Barlow Condensed',sans-serif;font-size:9px;font-weight:600;color:rgba(255,255,255,.4);letter-spacing:3px">GYM</span>
      </div>
    </a>
    <div style="margin-left:auto;display:flex;align-items:center;gap:16px">
      <?php if ($active_plan): ?>
      <div class="nav-plan-badge">
        <i class="fas fa-circle" style="font-size:.4rem;color:#4ade80"></i>
        <span style="font-size:.8rem;color:rgba(255,255,255,.6)"><?= htmlspecialchars($active_plan['type_name'] ?? $active_plan['plan_name']) ?></span>
        <span style="font-size:.75rem;color:rgba(255,255,255,.35)">· còn <?= $days_left ?> ngày</span>
      </div>
      <?php endif; ?>
      <a href="../index.php" class="nav-back-btn"><i class="fas fa-arrow-left"></i> Trang chủ</a>
    </div>
  </div>
</header>

<main class="pay-main">

  <!-- PAGE HEADER -->
  <section class="pay-hero">
    <div class="pay-hero-bg"></div>
    <div class="pay-wrap">
      <div class="pay-eyebrow"><span></span>ĐĂNG KÝ THÀNH VIÊN<span></span></div>
      <h1 class="pay-hero-title">CHỌN <span>GÓI TẬP</span><br>PHÙ HỢP</h1>
      <?php if ($active_plan): ?>
      <div class="pay-current-plan">
        <i class="fas fa-check-circle" style="color:#4ade80"></i>
        Đang dùng: <strong style="color:<?= htmlspecialchars($active_plan['color_code'] ?? '#d4a017') ?>"><?= htmlspecialchars($active_plan['plan_name']) ?></strong>
        — hết hạn <?= date('d/m/Y', strtotime($active_plan['end_date'])) ?>
      </div>
      <?php else: ?>
      <div class="pay-current-plan no-plan">
        <i class="fas fa-info-circle" style="color:#fbbf24"></i>
        Bạn chưa có gói tập nào. Đăng ký ngay để bắt đầu!
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- PLANS GRID -->
  <section class="pay-plans-section">
    <div class="pay-wrap">
      <div class="plans-filter-bar">
        <span class="plans-count" id="plansCount">Đang tải...</span>
        <div class="plans-sort">
          <select id="planSort" onchange="sortPlans(this.value)">
            <option value="default">Mặc định</option>
            <option value="price-asc">Giá tăng dần</option>
            <option value="price-desc">Giá giảm dần</option>
            <option value="duration-asc">Thời gian tăng</option>
          </select>
        </div>
      </div>

      <div class="plans-grid" id="plansGrid">
        <div class="plans-loading"><i class="fas fa-spinner fa-spin"></i> Đang tải gói tập...</div>
      </div>
    </div>
  </section>

</main>

<!-- ══════════════════════════════════════════════════
     MODAL: CHECKOUT  (2-column redesign)
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="checkoutModal">
  <div class="modal-box checkout-box">
    <button class="modal-x" onclick="closeCheckout()"><i class="fas fa-times"></i></button>

    <!-- Header -->
    <div class="checkout-header">
      <div class="checkout-title">
        <i class="fas fa-shopping-cart"></i> Xác nhận đơn hàng
      </div>
    </div>

    <!-- 2-Column body -->
    <div class="checkout-body">

      <!-- LEFT COL: plan info + upgrade notice -->
      <div class="checkout-col-left">

        <!-- Plan card (filled by JS) -->
        <div class="checkout-plan-info" id="checkoutPlanInfo">
          <!-- filled by JS -->
        </div>

        <!-- Upgrade notice -->
        <div class="checkout-upgrade-notice" id="upgradeNotice" style="display:none">
          <i class="fas fa-arrow-circle-up"></i>
          <div>
            <strong>Nâng cấp gói!</strong>
            <span id="upgradeText">Tiền hoàn lại từ gói cũ sẽ được khấu trừ.</span>
          </div>
        </div>

        <!-- Số lượng -->
        <div class="checkout-qty-row">
          <label>Số tháng đăng ký:</label>
          <div class="qty-ctrl">
            <button type="button" onclick="changeQty(-1)"><i class="fas fa-minus"></i></button>
            <span id="qtyDisplay">1</span>
            <button type="button" onclick="changeQty(1)"><i class="fas fa-plus"></i></button>
          </div>
        </div>

        <!-- Khuyến mãi -->
        <div class="checkout-promo-section">
          <div class="promo-toggle" onclick="togglePromo()">
            <i class="fas fa-tag"></i> Mã khuyến mãi
            <i class="fas fa-chevron-down" id="promoChevron"></i>
          </div>
          <div class="promo-list" id="promoList" style="display:none">
            <div class="promo-loading" id="promoLoading"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
            <div id="promoItems"></div>
            <div class="promo-none" id="promoNone" style="display:none">
              <i class="fas fa-info-circle"></i> Không có khuyến mãi phù hợp
            </div>
          </div>
        </div>

      </div><!-- /left col -->

      <!-- RIGHT COL: summary + pay button -->
      <div class="checkout-col-right">

        <!-- Summary -->
        <div class="checkout-summary" id="checkoutSummary">
          <div class="checkout-summary-title">Chi tiết thanh toán</div>

          <div class="summary-row">
            <span>Giá gốc</span>
            <span id="sumOriginal">—</span>
          </div>
          <div class="summary-row upgrade-row" id="sumUpgradeRow" style="display:none">
            <span><i class="fas fa-arrow-up" style="font-size:.65rem;color:#4ade80;margin-right:4px"></i>Hoàn tiền nâng cấp</span>
            <span id="sumUpgrade" style="color:#4ade80">—</span>
          </div>
          <div class="summary-row promo-row" id="sumPromoRow" style="display:none">
            <span><i class="fas fa-tag" style="font-size:.65rem;color:#fbbf24;margin-right:4px"></i>Khuyến mãi</span>
            <span id="sumPromo" style="color:#fbbf24">—</span>
          </div>

          <div class="summary-divider"></div>

          <div class="summary-row total-row">
            <span>Thực thanh toán</span>
            <span id="sumTotal" class="sum-total-val">—</span>
          </div>
        </div>

        <!-- Pay buttons -->
        <div class="checkout-actions">
          <button class="btn-pay-transfer" onclick="submitOrder('transfer')">
            <i class="fas fa-qrcode"></i> Chuyển khoản QR
          </button>
        </div>

      </div><!-- /right col -->
    </div><!-- /checkout-body -->

    <!-- Footer note -->
    <div class="checkout-footer-note">
      <i class="fas fa-shield-alt"></i>
      Thanh toán được bảo mật. Gói tập kích hoạt ngay sau khi xác nhận.
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL: QR PAYMENT
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="qrModal">
  <div class="modal-box qr-box">
    <button class="modal-x" onclick="closeQR()"><i class="fas fa-times"></i></button>

    <div class="qr-header">
      <div class="qr-title"><i class="fas fa-qrcode"></i> Thanh toán chuyển khoản</div>
      <div class="qr-subtitle" id="qrInvoiceId"></div>
    </div>

    <div class="qr-body">
      <div class="qr-img-wrap">
        <div id="qrImgArea">
          <div class="qr-loading-spin"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
        <div class="qr-amount-badge" id="qrAmountBadge"></div>
      </div>

      <div class="qr-info-panel">
        <div class="qr-bank-row">
          <span class="qr-bank-label">Ngân hàng</span>
          <span id="qrBankName" class="qr-bank-val"></span>
        </div>
        <div class="qr-bank-row">
          <span class="qr-bank-label">Số tài khoản</span>
          <span id="qrAccNo" class="qr-bank-val mono"></span>
        </div>
        <div class="qr-bank-row">
          <span class="qr-bank-label">Chủ tài khoản</span>
          <span id="qrAccName" class="qr-bank-val"></span>
        </div>
        <div class="qr-bank-row">
          <span class="qr-bank-label">Số tiền</span>
          <span id="qrAmount" class="qr-bank-val gold"></span>
        </div>
        <div class="qr-bank-row">
          <span class="qr-bank-label">Nội dung CK</span>
          <span id="qrDesc" class="qr-bank-val mono"></span>
        </div>

        <div class="qr-timer-row">
          <i class="fas fa-clock"></i>
          <span>Hết hạn sau: <strong id="qrCountdown" class="qr-countdown">05:00</strong></span>
        </div>

        <div class="qr-status-row" id="qrStatusRow">
          <i class="fas fa-circle-notch fa-spin"></i> Đang chờ thanh toán...
        </div>
      </div>
    </div>

    <div class="qr-actions">
      <button class="btn-qr-confirm" onclick="confirmPayment()">
        <i class="fas fa-check"></i> Đã chuyển khoản xong
      </button>
      <button class="btn-qr-refresh" onclick="refreshQR()">
        <i class="fas fa-redo"></i> Làm mới QR
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL: SUCCESS
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="successModal">
  <div class="modal-box success-box">
    <div class="success-icon"><i class="fas fa-check-circle"></i></div>
    <div class="success-title">Thanh toán thành công!</div>
    <div class="success-msg" id="successMsg">Gói tập của bạn đã được kích hoạt.</div>
    <div class="success-actions">
      <a href="../Profile/Profile.php?tab=plans" class="btn-success-profile">
        <i class="fas fa-id-card"></i> Xem gói tập của tôi
      </a>
      <a href="../index.php" class="btn-success-home">
        <i class="fas fa-home"></i> Về trang chủ
      </a>
    </div>
  </div>
</div>

<!-- Toast container -->
<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none"></div>

<script src="Payment.js" defer></script>
<style>
@keyframes toastIn { from{opacity:0;transform:translateX(20px)} to{opacity:1;transform:translateX(0)} }
</style>
</body>
</html>
