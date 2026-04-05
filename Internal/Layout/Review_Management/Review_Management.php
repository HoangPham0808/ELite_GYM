<?php
session_start();
require_once __DIR__ . '/../../../Database/db.php';

/* ── Kiểm tra quyền ── */
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['Admin', 'Employee'])) {
    header('Location: ../../Internal/Index/Login/Login.php');
    exit;
}

/* ── Xử lý AJAX ── */
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_GET['ajax'])) {
    require_once 'Review_Management_function.php';
    exit;
}

$page_title = 'Quản lý Đánh Giá';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= $page_title ?> — Elite Gym</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<link rel="stylesheet" href="Review_Management.css"/>
</head>
<body>

<div class="rm-layout">

  <!-- ── SIDEBAR HEADER ── -->
  <div class="rm-topbar">
    <div class="rm-topbar-left">
      <div class="rm-logo"><span class="rm-logo-bracket">[</span>RM<span class="rm-logo-bracket">]</span></div>
      <div class="rm-page-title">
        <span class="rm-title-eyebrow">Elite Gym &rsaquo; Admin</span>
        <h1>Quản lý Đánh Giá</h1>
      </div>
    </div>
    <div class="rm-topbar-right">
      <span class="rm-user-badge"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
    </div>
  </div>

  <!-- ── STATS ROW ── -->
  <div class="rm-stats-row" id="rmStats">
    <div class="rm-stat-card" id="statTotal">
      <div class="rm-stat-icon"><i class="fas fa-comments"></i></div>
      <div class="rm-stat-body">
        <div class="rm-stat-num" id="sTotal">—</div>
        <div class="rm-stat-label">Tổng đánh giá</div>
      </div>
    </div>
    <div class="rm-stat-card" id="statAvg">
      <div class="rm-stat-icon"><i class="fas fa-star"></i></div>
      <div class="rm-stat-body">
        <div class="rm-stat-num" id="sAvg">—</div>
        <div class="rm-stat-label">Điểm trung bình</div>
      </div>
    </div>
    <div class="rm-stat-card rm-stat-green" id="statReplied">
      <div class="rm-stat-icon"><i class="fas fa-reply-all"></i></div>
      <div class="rm-stat-body">
        <div class="rm-stat-num" id="sReplied">—</div>
        <div class="rm-stat-label">Đã phản hồi</div>
      </div>
    </div>
    <div class="rm-stat-card rm-stat-red" id="statPending">
      <div class="rm-stat-icon"><i class="fas fa-clock"></i></div>
      <div class="rm-stat-body">
        <div class="rm-stat-num" id="sPending">—</div>
        <div class="rm-stat-label">Chưa phản hồi</div>
      </div>
    </div>
    <!-- Rating distribution mini-bar -->
    <div class="rm-stat-card rm-stat-dist">
      <div class="rm-dist-title">Phân bố sao</div>
      <div class="rm-dist-bars" id="sDist"></div>
    </div>
  </div>

  <!-- ── TOOLBAR ── -->
  <div class="rm-toolbar">
    <div class="rm-search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" id="rmSearch" class="rm-search" placeholder="Tìm tên khách hàng, nội dung…"/>
    </div>
    <div class="rm-filters">
      <select id="rmRating" class="rm-select">
        <option value="0">Tất cả sao</option>
        <option value="5">★★★★★ 5 sao</option>
        <option value="4">★★★★☆ 4 sao</option>
        <option value="3">★★★☆☆ 3 sao</option>
        <option value="2">★★☆☆☆ 2 sao</option>
        <option value="1">★☆☆☆☆ 1 sao</option>
      </select>
      <select id="rmReplied" class="rm-select">
        <option value="all">Tất cả trạng thái</option>
        <option value="no">Chưa phản hồi</option>
        <option value="yes">Đã phản hồi</option>
      </select>
      <button class="rm-btn rm-btn-ghost" id="rmRefresh" title="Làm mới">
        <i class="fas fa-rotate-right"></i>
      </button>
    </div>
  </div>

  <!-- ── TABLE ── -->
  <div class="rm-table-wrap">
    <table class="rm-table">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>Khách hàng</th>
          <th style="width:120px">Đánh giá</th>
          <th>Nội dung</th>
          <th>Phản hồi nhân viên</th>
          <th style="width:100px">Ngày</th>
          <th style="width:110px">Thao tác</th>
        </tr>
      </thead>
      <tbody id="rmTbody">
        <tr><td colspan="7" class="rm-loading"><i class="fas fa-spinner fa-spin"></i> Đang tải…</td></tr>
      </tbody>
    </table>
  </div>

  <!-- ── PAGINATION ── -->
  <div class="rm-pagination" id="rmPager"></div>

</div>

<!-- ── REPLY MODAL ── -->
<div class="rm-modal-backdrop" id="rmModalBd">
  <div class="rm-modal" id="rmModal">
    <div class="rm-modal-header">
      <div class="rm-modal-title"><i class="fas fa-reply"></i> Phản hồi đánh giá</div>
      <button class="rm-modal-close" id="rmModalClose"><i class="fas fa-times"></i></button>
    </div>
    <div class="rm-modal-body">
      <div class="rm-modal-review" id="rmModalReview"></div>
      <label class="rm-modal-label">Nội dung phản hồi <span class="rm-required">*</span></label>
      <textarea id="rmReplyTa" class="rm-modal-ta" rows="4" maxlength="300" placeholder="Nhập phản hồi của bạn… (tối thiểu 5 ký tự)"></textarea>
      <div class="rm-modal-chars"><span id="rmReplyChars">300</span> ký tự còn lại</div>
      <div class="rm-modal-msg" id="rmModalMsg"></div>
    </div>
    <div class="rm-modal-footer">
      <button class="rm-btn rm-btn-ghost" id="rmModalCancel">Huỷ</button>
      <button class="rm-btn rm-btn-primary" id="rmModalSave"><i class="fas fa-paper-plane"></i> Lưu phản hồi</button>
    </div>
  </div>
</div>

<!-- ── CONFIRM MODAL ── -->
<div class="rm-modal-backdrop" id="rmConfirmBd">
  <div class="rm-modal rm-modal-sm">
    <div class="rm-modal-header rm-modal-header-danger">
      <div class="rm-modal-title"><i class="fas fa-triangle-exclamation"></i> Xác nhận xóa</div>
    </div>
    <div class="rm-modal-body">
      <p id="rmConfirmMsg" style="color:rgba(255,255,255,.75);font-size:14px;line-height:1.6"></p>
    </div>
    <div class="rm-modal-footer">
      <button class="rm-btn rm-btn-ghost" id="rmConfirmCancel">Huỷ</button>
      <button class="rm-btn rm-btn-danger" id="rmConfirmOk"><i class="fas fa-trash"></i> Xóa</button>
    </div>
  </div>
</div>

<!-- ── TOAST ── -->
<div class="rm-toast" id="rmToast"></div>

<script src="Review_Management.js"></script>
</body>
</html>
