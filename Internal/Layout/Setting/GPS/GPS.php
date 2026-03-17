<?php
/**
 * GPS_.php — Trang cài đặt tọa độ GPS phòng tập cho Admin
 * Vị trí: Internal/Layout/Setting/GPS/GPS_.php
 */
ob_start();
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../../Index/Login/Login.php");
    exit;
}

$admin_name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin');

require_once __DIR__ . '/../../../../Database/db.php';

// Đọc settings hiện tại
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM gym_settings");
if ($res) while ($r = $res->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];

$gym_lat    = $settings['gym_lat']           ?? '21.0285';
$gym_lng    = $settings['gym_lng']           ?? '105.8542';
$gym_radius = $settings['gym_radius_m']      ?? '100';
$gym_name   = htmlspecialchars($settings['gym_location_name'] ?? 'Elite Gym');
$loc_check  = $settings['location_check']    ?? '1';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Elite Gym — Cài đặt GPS chấm công</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800;900&family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
      crossorigin="anonymous" referrerpolicy="no-referrer" id="fa-cdn"/>
<script>
  document.getElementById('fa-cdn').onerror = function(){
    var lnk = document.createElement('link');
    lnk.rel = 'stylesheet';
    lnk.href = '../../../../Home/fontawesome/css/all.min.css';
    document.head.appendChild(lnk);
  };
</script>
<!-- Leaflet map (OSM, không cần API key) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"/>
<link rel="stylesheet" href="GPS.css"/>
</head>
<body>

<!-- ══ TOPBAR ══ -->
<header class="topbar">
  <div class="tb-logo">
    <svg viewBox="0 0 44 44">
      <polygon points="22,2 40,12 40,32 22,42 4,32 4,12" fill="none" stroke="#d4a017" stroke-width="1.8"/>
      <text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" fill="#d4a017" font-size="12" font-weight="800" font-family="Barlow Condensed">EG</text>
    </svg>
    <span class="tb-brand">ELITE <em>GYM</em></span>
  </div>
  <div class="tb-sep"></div>
  <span class="tb-title"><i class="fas fa-satellite-dish"></i> CÀI ĐẶT GPS CHẤM CÔNG</span>
  <div class="tb-right">
    <span class="tb-user"><i class="fas fa-user-shield"></i> <?= $admin_name ?></span>
    <a href="../../Admin/adm.php" class="tb-btn"><i class="fas fa-th-large"></i> Dashboard</a>
  </div>
</header>

<div class="page">

  <!-- ══ TOAST ══ -->
  <div class="toast" id="toast"></div>

  <div class="page-grid">

    <!-- ══════════════════════════════════════
         [LEFT] — Form cài đặt
    ══════════════════════════════════════ -->
    <div class="left-col">

      <!-- Trạng thái hệ thống -->
      <div class="card">
        <div class="card-head">
          <div class="card-icon"><i class="fas fa-toggle-on"></i></div>
          <h2>TRẠNG THÁI HỆ THỐNG</h2>
        </div>
        <div class="toggle-row">
          <div class="toggle-info">
            <div class="toggle-title">Kiểm tra vị trí khi chấm công</div>
            <div class="toggle-sub">Khi bật, nhân viên chỉ được chấm công trong phạm vi đã cài đặt</div>
          </div>
          <label class="toggle-switch">
            <input type="checkbox" id="toggleLocCheck" <?= $loc_check == '1' ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>

      <!-- Thông tin phòng tập -->
      <div class="card">
        <div class="card-head">
          <div class="card-icon"><i class="fas fa-building"></i></div>
          <h2>THÔNG TIN PHÒNG TẬP</h2>
        </div>
        <div class="form-group">
          <label><i class="fas fa-tag"></i> Tên phòng tập</label>
          <input type="text" id="inpGymName" class="form-input"
                 value="<?= $gym_name ?>"
                 placeholder="VD: Elite Gym — 123 Đường ABC, Hà Nội"
                 maxlength="200"/>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><i class="fas fa-ruler-combined"></i> Bán kính cho phép (mét)</label>
            <input type="number" id="inpRadius" class="form-input"
                   value="<?= (int)$gym_radius ?>" min="50" max="1000" step="10"/>
            <div class="form-hint">Tối thiểu 50m — Khuyến nghị 100m</div>
          </div>
          <div class="form-group">
            <label><i class="fas fa-crosshairs"></i> Tọa độ hiện tại</label>
            <div class="coord-display">
              <span class="coord-val" id="dispLat"><?= $gym_lat ?></span>
              <span class="coord-sep">,</span>
              <span class="coord-val" id="dispLng"><?= $gym_lng ?></span>
            </div>
          </div>
        </div>
        <input type="hidden" id="inpLat" value="<?= $gym_lat ?>"/>
        <input type="hidden" id="inpLng" value="<?= $gym_lng ?>"/>
      </div>

      <!-- Cách đặt tọa độ -->
      <div class="card">
        <div class="card-head">
          <div class="card-icon"><i class="fas fa-map-pin"></i></div>
          <h2>ĐẶT VỊ TRÍ PHÒNG TẬP</h2>
        </div>
        <p class="card-desc">Chọn một trong các cách sau để xác định tọa độ chính xác:</p>

        <div class="method-btns">
          <button class="method-btn method-btn--primary" onclick="useMyLocation()">
            <i class="fas fa-location-crosshairs"></i>
            <div>
              <strong>Dùng vị trí hiện tại</strong>
              <small>Lấy tọa độ GPS của thiết bị admin</small>
            </div>
          </button>
          <button class="method-btn" onclick="focusMap()">
            <i class="fas fa-map-marker-alt"></i>
            <div>
              <strong>Nhấp trên bản đồ</strong>
              <small>Kéo hoặc nhấp vào điểm trên bản đồ</small>
            </div>
          </button>
          <button class="method-btn" onclick="openManualInput()">
            <i class="fas fa-keyboard"></i>
            <div>
              <strong>Nhập tọa độ thủ công</strong>
              <small>Dán tọa độ từ Google Maps</small>
            </div>
          </button>
        </div>

        <!-- Manual input (ẩn mặc định) -->
        <div class="manual-row hidden" id="manualRow">
          <div class="form-row">
            <div class="form-group">
              <label><i class="fas fa-arrows-up-down"></i> Vĩ độ (Latitude)</label>
              <input type="text" id="manLat" class="form-input" placeholder="VD: 21.0285"/>
            </div>
            <div class="form-group">
              <label><i class="fas fa-arrows-left-right"></i> Kinh độ (Longitude)</label>
              <input type="text" id="manLng" class="form-input" placeholder="VD: 105.8542"/>
            </div>
          </div>
          <button class="btn-apply-manual" onclick="applyManual()">
            <i class="fas fa-check"></i> Áp dụng tọa độ
          </button>
        </div>

        <!-- GPS loading indicator -->
        <div class="gps-loading hidden" id="gpsLoading">
          <i class="fas fa-spinner fa-spin"></i> Đang lấy vị trí GPS...
        </div>
      </div>

      <!-- Lưu cài đặt -->
      <button class="btn-save-all" id="btnSave" onclick="saveSettings()">
        <i class="fas fa-save"></i> LƯU CÀI ĐẶT GPS
      </button>

      <!-- Hướng dẫn -->
      <div class="guide-card">
        <div class="guide-head"><i class="fas fa-circle-info"></i> Hướng dẫn sử dụng</div>
        <ol class="guide-list">
          <li>Đến phòng tập, mở trang này trên thiết bị của bạn</li>
          <li>Nhấn <strong>"Dùng vị trí hiện tại"</strong> để lấy tọa độ chính xác</li>
          <li>Điều chỉnh bán kính (100m là mặc định phù hợp)</li>
          <li>Nhấn <strong>"Lưu cài đặt GPS"</strong></li>
          <li>Nhân viên sẽ chỉ chấm công được trong phạm vi đã cài</li>
        </ol>
        <div class="guide-note">
          <i class="fas fa-lightbulb"></i>
          Tắt "Kiểm tra vị trí" nếu muốn nhân viên chấm công từ xa (không cần GPS)
        </div>
      </div>

    </div><!-- /left-col -->

    <!-- ══════════════════════════════════════
         [RIGHT] — Bản đồ
    ══════════════════════════════════════ -->
    <div class="right-col">
      <div class="card card-map">
        <div class="card-head">
          <div class="card-icon"><i class="fas fa-map"></i></div>
          <h2>BẢN ĐỒ VỊ TRÍ PHÒNG TẬP</h2>
          <span class="map-hint">Nhấp vào bản đồ để đặt vị trí</span>
        </div>

        <!-- Radius info bar -->
        <div class="radius-bar" id="radiusBar">
          <i class="fas fa-circle-dot" style="color:var(--gold)"></i>
          Bán kính: <strong id="radiusDisplay"><?= (int)$gym_radius ?>m</strong>
          &nbsp;·&nbsp;
          <i class="fas fa-map-marker-alt" style="color:#f87171"></i>
          <span id="coordDisplay"><?= $gym_lat ?>, <?= $gym_lng ?></span>
        </div>

        <!-- Map search + locate controls -->
        <div class="map-controls">
          <div class="map-search-wrap">
            <i class="fas fa-search map-search-icon"></i>
            <input type="text" id="mapSearchInput"
                   class="map-search-input"
                   placeholder="Tìm địa điểm... (VD: Hoàn Kiếm, Hà Nội)"
                   autocomplete="off"/>
            <button class="map-search-btn" onclick="searchLocation()" title="Tìm kiếm">
              <i class="fas fa-arrow-right"></i>
            </button>
          </div>
          <button class="map-locate-btn" onclick="locateOnMap()" title="Vị trí hiện tại của tôi">
            <i class="fas fa-location-crosshairs"></i>
          </button>
        </div>

        <!-- Search results dropdown -->
        <div class="map-search-results hidden" id="searchResults"></div>

        <!-- Map container -->
        <div id="map"></div>

        <!-- Map legend -->
        <div class="map-legend">
          <div class="legend-item">
            <span class="legend-dot legend-dot--gym"></span> Phòng tập (tâm)
          </div>
          <div class="legend-item">
            <span class="legend-dot legend-dot--radius"></span> Vùng cho phép chấm công
          </div>
        </div>
      </div>

      <!-- Stats hiện tại -->
      <div class="card card-stats">
        <div class="card-head">
          <div class="card-icon"><i class="fas fa-chart-bar"></i></div>
          <h2>THỐNG KÊ CHẤM CÔNG HÔM NAY</h2>
        </div>
        <div class="stats-grid" id="statsGrid">
          <div class="stat-box stat-box--blue">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-val" id="sTotalEmp">—</div>
            <div class="stat-lbl">Tổng nhân viên</div>
          </div>
          <div class="stat-box stat-box--green">
            <div class="stat-icon"><i class="fas fa-user-check"></i></div>
            <div class="stat-val" id="sPresent">—</div>
            <div class="stat-lbl">Đã chấm công</div>
          </div>
          <div class="stat-box stat-box--red">
            <div class="stat-icon"><i class="fas fa-user-xmark"></i></div>
            <div class="stat-val" id="sAbsent">—</div>
            <div class="stat-lbl">Chưa chấm</div>
          </div>
          <div class="stat-box stat-box--gold">
            <div class="stat-icon"><i class="fas fa-location-dot"></i></div>
            <div class="stat-val" id="sGpsCount">—</div>
            <div class="stat-lbl">Có dữ liệu GPS</div>
          </div>
        </div>
      </div>
    </div><!-- /right-col -->

  </div><!-- /page-grid -->
</div><!-- /page -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="GPS.js"></script>
<script>
  // Truyền dữ liệu PHP sang JS
  const GPS_INIT = {
    lat:    <?= floatval($gym_lat) ?>,
    lng:    <?= floatval($gym_lng) ?>,
    radius: <?= intval($gym_radius) ?>,
    name:   <?= json_encode($gym_name) ?>,
    check:  <?= intval($loc_check) ?>
  };
</script>
</body>
</html>
