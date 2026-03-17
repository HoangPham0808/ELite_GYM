<?php
ob_start();
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../../Index/Login/Login.php");
    exit;
}
$admin_name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Elite Gym — Cài đặt hệ thống</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800;900&family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous"/>
<link rel="stylesheet" href="System.css"/>
</head>
<body>

<header class="topbar">
  <div class="tb-logo">
    <svg viewBox="0 0 44 44">
      <polygon points="22,2 40,12 40,32 22,42 4,32 4,12" fill="none" stroke="#d4a017" stroke-width="1.8"/>
      <text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" fill="#d4a017" font-size="12" font-weight="800" font-family="Barlow Condensed">EG</text>
    </svg>
    <span class="tb-brand">ELITE <em>GYM</em></span>
  </div>
  <div class="tb-sep"></div>
  <div class="tb-title tb-nav-trigger" id="navTrigger" onclick="toggleNavDropdown()">
    <i class="fas fa-sliders"></i>
    <span id="navLabel">CÀI ĐẶT HỆ THỐNG</span>
    <i class="fas fa-chevron-down tb-nav-arrow" id="navArrow"></i>
    <div class="tb-nav-dropdown" id="navDropdown">
      <button class="tb-nav-item active" id="navPkg" onclick="switchSection('pkg',event)">
        <span class="tb-nav-dot gold-dot"></span>
        <span>Loại gói tập</span>
      </button>
      <button class="tb-nav-item" id="navEq" onclick="switchSection('eq',event)">
        <span class="tb-nav-dot blue-dot"></span>
        <span>Loại thiết bị</span>
      </button>
    </div>
  </div>
  <div class="tb-right">
    <span class="tb-user"><i class="fas fa-user-shield"></i> <?= $admin_name ?></span>
  </div>
</header>

<div class="page">
  <div class="toast" id="toast"></div>

  <div class="page-header">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
      <div>
        <h1 class="page-title"><i class="fas fa-layer-group" id="pageTitleIcon"></i> <span>Loại gói tập</span></h1>
        <p class="page-desc">Phân loại gói thành viên theo hạng mức</p>
      </div>
      <button class="btn btn--ghost" id="refreshBtn" onclick="refreshCurrent()" title="Làm mới"><i class="fas fa-rotate-right"></i> Làm mới</button>
    </div>
  </div>

  <!-- ══ ACCORDION 1: LOẠI GÓI TẬP ══ -->
  <div class="accordion" id="acc-pkg">
    <div class="accordion-body" id="body-pkg">
      <!-- Form thêm -->
      <div class="acc-form gold-form">
        <div class="form-row">
          <div class="form-group">
            <label>Tên loại gói <span class="req">*</span></label>
            <input type="text" id="inpName" class="form-input" placeholder="VD: Premium, VIP..." maxlength="100"/>
          </div>
          <div class="form-group">
            <label>Mô tả</label>
            <input type="text" id="inpDesc" class="form-input" placeholder="Mô tả ngắn..." maxlength="500"/>
          </div>
          <div class="form-group form-group-color">
            <label>Màu hiển thị</label>
            <div class="color-wrap" onclick="document.getElementById('colorPicker').click()">
              <div class="color-swatch" id="colorSwatch" style="background:#d4a017"></div>
              <span class="color-hex" id="colorHex">#d4a017</span>
              <input type="color" id="colorPicker" value="#d4a017" oninput="syncColor(this.value)"/>
            </div>
          </div>
          <button class="btn btn--gold form-btn" onclick="addPackageType()"><i class="fas fa-plus"></i> Thêm</button>
        </div>
      </div>
      <!-- Bảng -->
      <div class="acc-table-wrap">
        <table class="acc-table">
          <thead><tr>
            <th style="width:50px">#</th>
            <th>Tên loại gói</th>
            <th>Mô tả</th>
            <th style="width:80px">Thứ tự</th>
            <th style="width:90px">Trạng thái</th>
            <th style="width:110px">Thao tác</th>
          </tr></thead>
          <tbody id="tblBody">
            <tr class="loading-row"><td colspan="6"><i class="fas fa-spinner fa-spin"></i></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ══ ACCORDION 2: LOẠI THIẾT BỊ ══ -->
  <div class="accordion section-hidden" id="acc-eq">
    <div class="accordion-body" id="body-eq">
      <!-- Form thêm -->
      <div class="acc-form blue-form">
        <div class="form-row">
          <div class="form-group">
            <label>Tên loại thiết bị <span class="req">*</span></label>
            <input type="text" id="eqInpName" class="form-input" placeholder="VD: Treadmill, Cardio..." maxlength="150"/>
          </div>
          <div class="form-group">
            <label>Mô tả</label>
            <input type="text" id="eqInpDesc" class="form-input" placeholder="Mô tả loại..." maxlength="500"/>
          </div>
          <div class="form-group form-group-sm">
            <label>Hạn bảo trì (ngày)</label>
            <input type="number" id="eqInpInterval" class="form-input" placeholder="180" min="1" value="180"/>
          </div>
          <button class="btn btn--blue form-btn" onclick="addEquipmentType()"><i class="fas fa-plus"></i> Thêm</button>
        </div>
      </div>
      <!-- Bảng -->
      <div class="acc-table-wrap">
        <table class="acc-table">
          <thead><tr>
            <th style="width:50px">#</th>
            <th>Tên loại thiết bị</th>
            <th>Mô tả</th>
            <th style="width:130px">Hạn bảo trì</th>
            <th style="width:90px">Thao tác</th>
          </tr></thead>
          <tbody id="eqTblBody">
            <tr class="loading-row"><td colspan="5"><i class="fas fa-spinner fa-spin"></i></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /page -->
<script src="System.js"></script>
</body>
</html>
