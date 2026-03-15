<?php
/**
 * Image_landing.php — Trang quản lý ảnh Slideshow trang chủ
 * Vị trí: C:\wamp64\www\PHP\ELite_GYM\Internal\Layout\Setting\Image_landing\Image_landing.php
 */
ob_start();
session_start();

// ── Bảo vệ: chỉ Admin ────────────────────────────────────────────
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../../Index/Login/Login.php");
    exit;
}

$admin_name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin');

// ── Kết nối DB ────────────────────────────────────────────────────
require_once __DIR__ . '/../../../../Database/db.php';

// ── Đọc danh sách ảnh ────────────────────────────────────────────
$images = $conn->query("
    SELECT image_id, image_name, file_name, file_url,
           file_size, file_ext, sort_order, is_active,
           DATE_FORMAT(uploaded_at,'%d/%m/%Y %H:%i') AS uploaded_at
    FROM landing_images
    ORDER BY sort_order ASC, image_id ASC
")->fetch_all(MYSQLI_ASSOC);

$total  = count($images);
$active = array_sum(array_column($images, 'is_active'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Elite Gym — Quản lý ảnh Slideshow</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800;900&family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
      crossorigin="anonymous" referrerpolicy="no-referrer"/>
<link rel="stylesheet" href="Image_landing.css"/>
</head>
<body>

<!-- ══════════════════════════════════════════
     TOPBAR
══════════════════════════════════════════ -->
<header class="topbar">
  <div class="tb-logo">
    <svg viewBox="0 0 44 44">
      <polygon points="22,2 40,12 40,32 22,42 4,32 4,12"
               fill="none" stroke="#d4a017" stroke-width="1.8"/>
      <text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle"
            fill="#d4a017" font-size="12" font-weight="800"
            font-family="Barlow Condensed">EG</text>
    </svg>
    <span class="tb-brand">ELITE <em>GYM</em></span>
  </div>

  <div class="tb-sep"></div>
  <span class="tb-title">
    <i class="fas fa-images"></i> QUẢN LÝ ẢNH SLIDESHOW
  </span>

  <div class="tb-right">
    <span style="font-size:.8rem;color:var(--text-3)">
      <i class="fas fa-user-shield" style="color:var(--gold);margin-right:5px"></i>
      <?= $admin_name ?>
    </span>
    <a href="../../Admin/adm.php"         class="tb-btn">
      <i class="fas fa-th-large"></i> Dashboard
    </a>
    <a href="../../../../Home/index.php"  class="tb-btn">
      <i class="fas fa-home"></i> Trang chủ
    </a>
  </div>
</header>

<!-- ══════════════════════════════════════════
     PAGE CONTENT
══════════════════════════════════════════ -->
<div class="page">

  <!-- ════════════════════════════════════════
       [1] THÔNG TIN THƯ MỤC
  ════════════════════════════════════════ -->
  <div class="card">
    <div class="card-head">
      <div class="card-head-icon"><i class="fas fa-folder-open"></i></div>
      <h2>CẤU HÌNH THƯ MỤC LƯU ẢNH</h2>
    </div>
    <div class="path-row">
      <div class="path-box">
        <strong><i class="fas fa-hdd"></i> &nbsp;Đường dẫn vật lý (Server)</strong>
        C:/wamp64/www/PHP/ELite_GYM/upload/image_panel/
      </div>
      <div class="path-box">
        <strong><i class="fas fa-globe"></i> &nbsp;URL hiển thị trình duyệt</strong>
        /PHP/ELite_GYM/upload/image_panel/
      </div>
    </div>
  </div>

  <!-- ════════════════════════════════════════
       [2] UPLOAD ẢNH MỚI
  ════════════════════════════════════════ -->
  <div class="card">
    <div class="card-head">
      <div class="card-head-icon"><i class="fas fa-cloud-upload-alt"></i></div>
      <h2>THÊM ẢNH MỚI VÀO SLIDESHOW</h2>
      <span class="card-head-meta">
        JPG &middot; PNG &middot; WEBP &middot; GIF &nbsp;&bull;&nbsp;
        Tối đa 5 MB/ảnh &nbsp;&bull;&nbsp;
        Có thể chọn nhiều ảnh cùng lúc
      </span>
    </div>

    <form id="uploadForm" enctype="multipart/form-data">
      <!-- Vùng kéo thả -->
      <div class="drop-zone" id="dropZone">
        <input type="file" id="fileInput" name="images[]"
               multiple accept="image/jpeg,image/png,image/webp,image/gif"/>
        <div class="dz-icon"><i class="fas fa-images"></i></div>
        <div class="dz-title">Kéo &amp; thả ảnh vào đây — hoặc nhấp để chọn file</div>
        <div class="dz-sub">
          Giữ
          <kbd style="background:rgba(255,255,255,.08);border:1px solid var(--border);
                      padding:1px 7px;border-radius:4px;font-size:.74rem">Ctrl</kbd>
          để chọn nhiều ảnh một lúc
        </div>
        <div class="dz-tags">
          <span class="dz-tag">JPG</span>
          <span class="dz-tag">PNG</span>
          <span class="dz-tag">WEBP</span>
          <span class="dz-tag">GIF</span>
          <span class="dz-tag">MAX 5 MB</span>
        </div>
        <!-- Preview ảnh chọn -->
        <div class="dz-preview" id="dzPreview"></div>
      </div>

      <div class="upload-submit">
        <button type="submit" class="btn-upload" id="uploadBtn" disabled>
          <i class="fas fa-upload"></i> UPLOAD ẢNH
        </button>
        <span class="upload-count" id="uploadCount"></span>
      </div>
    </form>
  </div>

  <!-- ════════════════════════════════════════
       [3] DANH SÁCH ẢNH + SỬA / XÓA
  ════════════════════════════════════════ -->
  <div class="card">
    <div class="card-head">
      <div class="card-head-icon"><i class="fas fa-th"></i></div>
      <h2>DANH SÁCH ẢNH TRONG SLIDESHOW</h2>
      <span class="card-head-meta" id="galleryMeta"><?= $total ?> ảnh</span>
    </div>

    <!-- Thống kê nhanh -->
    <div class="stats-row">
      <div class="stat-chip">
        <i class="fas fa-images"></i>
        <span>Tổng:</span>
        <strong id="statTotal"><?= $total ?></strong>
      </div>
      <div class="stat-chip">
        <i class="fas fa-eye" style="color:var(--green)"></i>
        <span>Hiển thị:</span>
        <strong id="statActive" style="color:var(--green)"><?= $active ?></strong>
      </div>
      <div class="stat-chip">
        <i class="fas fa-eye-slash" style="color:var(--red)"></i>
        <span>Đang ẩn:</span>
        <strong id="statHidden" style="color:var(--red)"><?= $total - $active ?></strong>
      </div>
      <div class="stat-chip" style="margin-left:auto">
        <i class="fas fa-arrows-alt"></i>
        <span>Kéo thả để <strong>thay đổi thứ tự</strong> slideshow</span>
      </div>
    </div>

    <!-- Toolbar lưu thứ tự -->
    <div class="gallery-toolbar">
      <button class="btn-save-order" id="saveOrderBtn">
        <i class="fas fa-save"></i> LƯU THỨ TỰ SLIDESHOW
      </button>
      <span class="order-hint" id="orderStatus">
        Kéo thả các ảnh rồi nhấn "Lưu thứ tự" để áp dụng
      </span>
    </div>

    <!-- GRID ẢNH -->
    <div class="img-grid" id="imgGrid">

      <?php if (empty($images)): ?>
      <!-- Empty state -->
      <div class="empty-state">
        <i class="fas fa-image"></i>
        <p>Chưa có ảnh nào trong slideshow.<br>Upload ảnh đầu tiên ở phần trên!</p>
      </div>

      <?php else: ?>
      <?php foreach ($images as $img):
        $isOn  = (bool)$img['is_active'];
        $fsize = ($img['file_size'] > 0) ? round($img['file_size'] / 1024) . ' KB' : '?';
        $fext  = strtoupper($img['file_ext'] ?? '?');
        // Placeholder SVG khi ảnh không load được
        $placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' "
                     . "width='160' height='100'%3E%3Crect fill='%23111' width='100%25' height='100%25'/%3E"
                     . "%3Ctext x='50%25' y='52%25' fill='%23444' font-size='11' "
                     . "text-anchor='middle' dominant-baseline='middle'%3EKhông tải được%3C/text%3E%3C/svg%3E";
      ?>

      <div class="img-card <?= $isOn ? '' : 'inactive' ?>"
           draggable="true"
           data-id="<?= (int)$img['image_id'] ?>"
           data-active="<?= (int)$img['is_active'] ?>">

        <!-- ── Thumbnail ── -->
        <div class="img-thumb-wrap">
          <img src="<?= htmlspecialchars($img['file_url']) ?>"
               alt="<?= htmlspecialchars($img['image_name']) ?>"
               loading="lazy"
               onerror="this.src='<?= $placeholder ?>'"/>

          <!-- Số thứ tự -->
          <span class="badge-order"><?= (int)$img['sort_order'] ?></span>

          <!-- Trạng thái hiển thị -->
          <span class="badge-status <?= $isOn ? 'on' : 'off' ?>">
            <?= $isOn ? '● HIỆN' : '○ ẨN' ?>
          </span>

          <!-- Drag handle -->
          <span class="drag-handle" title="Giữ để kéo sắp xếp">
            <i class="fas fa-grip-dots-vertical"></i>
          </span>
        </div>

        <!-- ── Tên ảnh + nút đổi tên ── -->
        <div class="img-info">
          <div class="img-name-row">
            <span class="img-name"
                  title="<?= htmlspecialchars($img['image_name']) ?>">
              <?= htmlspecialchars($img['image_name']) ?>
            </span>
            <button class="btn-rename"
                    title="Sửa tên hiển thị của ảnh này">
              <i class="fas fa-pen"></i>
            </button>
          </div>
          <div class="img-meta">
            <span><?= $fext ?></span>
            <span><?= $fsize ?></span>
          </div>
        </div>

        <!-- ── URL (nhấp để copy) ── -->
        <div class="img-url-row"
             title="Nhấp để sao chép đường dẫn URL">
          <i class="fas fa-link"></i>
          <span><?= htmlspecialchars($img['file_url']) ?></span>
        </div>

        <!-- ── Nút Ẩn/Hiện & Xóa ── -->
        <div class="img-actions">
          <button class="btn-toggle <?= $isOn ? 'on' : 'off' ?>">
            <?php if ($isOn): ?>
              <i class="fas fa-eye-slash"></i> Ẩn khỏi slideshow
            <?php else: ?>
              <i class="fas fa-eye"></i> Hiện trên slideshow
            <?php endif; ?>
          </button>
          <button class="btn-del" title="Xóa ảnh vĩnh viễn">
            <i class="fas fa-trash-alt"></i>
          </button>
        </div>

      </div><!-- /img-card -->
      <?php endforeach; ?>
      <?php endif; ?>

    </div><!-- /img-grid -->
  </div><!-- /card gallery -->

</div><!-- /page -->

<!-- ══════════════════════════════════════════
     MODAL ĐỔI TÊN ẢNH
══════════════════════════════════════════ -->
<div class="modal-overlay" id="renameModal">
  <div class="modal">
    <h3><i class="fas fa-pen"></i> ĐỔI TÊN HIỂN THỊ</h3>
    <input type="text" id="renameInput"
           placeholder="Nhập tên mới..."
           maxlength="120" autocomplete="off"/>
    <div class="modal-btns">
      <button class="btn-cancel" id="renameCancel">
        <i class="fas fa-times"></i> Hủy
      </button>
      <button class="btn-confirm" id="renameConfirm">
        <i class="fas fa-check"></i> Lưu tên
      </button>
    </div>
  </div>
</div>

<!-- ══ TOAST ══ -->
<div class="toast" id="toast"></div>

<!-- ══ JS ══ -->
<script src="Image_landing.js"></script>
</body>
</html>
