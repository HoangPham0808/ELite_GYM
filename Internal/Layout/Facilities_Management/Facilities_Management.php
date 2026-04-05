<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_role       = $_SESSION['role']        ?? '';
$_position   = $_SESSION['position']   ?? '';
$_emp_id     = (int)($_SESSION['employee_id'] ?? 0);
$is_admin    = ($_role === 'Admin');
$is_recept   = ($_role === 'Employee' && $_position === 'Receptionist');
$is_hlv      = ($_role === 'Employee' && $_position === 'Personal Trainer');
// Encode for JS
$js_role     = json_encode($_role);
$js_position = json_encode($_position);
$js_emp_id   = json_encode($_emp_id);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cơ sở vật chất - Elite Gym</title>
    <link rel="stylesheet" href="Facilities_Management.css">
    <style>
        .hlv-room-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(212, 160, 23, 0.1);
            border: 1px solid rgba(212, 160, 23, 0.35);
            border-radius: 8px;
            padding: 10px 16px;
            margin-bottom: 14px;
            font-size: 14px;
            color: var(--ts, #e2e8f0);
        }
        .hlv-room-banner i { color: #d4a017; font-size: 16px; flex-shrink: 0; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="fac-container">
    <?php if ($is_admin): ?>
    <div class="stats-strip">
        <div class="strip-card blue">  <div class="strip-icon"><i class="fas fa-dumbbell"></i></div>      <div class="strip-info"><div class="strip-value" id="statTotal">—</div>    <div class="strip-label">Tổng thiết bị</div></div></div>
        <div class="strip-card green"> <div class="strip-icon"><i class="fas fa-check-circle"></i></div>   <div class="strip-info"><div class="strip-value" id="statHoatDong">—</div> <div class="strip-label">Hoạt động</div></div></div>
        <div class="strip-card red">   <div class="strip-icon"><i class="fas fa-times-circle"></i></div>   <div class="strip-info"><div class="strip-value" id="statHong">—</div>      <div class="strip-label">Hỏng</div></div></div>
        <div class="strip-card orange"><div class="strip-icon"><i class="fas fa-tools"></i></div>           <div class="strip-info"><div class="strip-value" id="statBaoDuong">—</div> <div class="strip-label">Đang bảo dưỡng</div></div></div>
        <div class="strip-card yellow"><div class="strip-icon"><i class="fas fa-exclamation-triangle"></i></div><div class="strip-info"><div class="strip-value" id="statCanBaoTri">—</div><div class="strip-label">Cần bảo trì</div></div></div>
        <div class="strip-card purple"><div class="strip-icon"><i class="fas fa-coins"></i></div>           <div class="strip-info"><div class="strip-value" id="statTongGia">—</div>  <div class="strip-label">Tổng tài sản</div></div></div>
    </div>
    <?php endif; ?>
    <div class="tab-bar">
        <button class="tab-btn active" data-tab="devices"><i class="fas fa-dumbbell"></i> Thiết bị</button>
        <button class="tab-btn" data-tab="maintenance"><i class="fas fa-wrench"></i> Bảo trì</button>

    </div>
    <div class="tab-content active" id="tab-devices">
        <?php if ($is_hlv): ?>
        <div class="hlv-room-banner" id="hlvRoomBanner" style="display:none">
            <i class="fas fa-door-open"></i>
            <span id="hlvRoomBannerText">Đang tải thông tin phòng tập hôm nay...</span>
        </div>
        <?php endif; ?>
        <div class="filter-bar">
            <div class="search-box"><i class="fas fa-search"></i><input type="text" id="devSearch" placeholder="Tìm tên thiết bị..."></div>
            <select class="filter-select" id="devLoai"><option value="">Tất cả loại</option></select>
            <select class="filter-select" id="devStatus">
                <option value="">Tất cả trạng thái</option>
                <option>Hoạt động</option><option>Hỏng</option>
                <option>Đang bảo dưỡng</option><option>Ngừng sử dụng</option>
            </select>
            <button class="btn-alert" id="btnOverdue" onclick="toggleOverdue()">
                <i class="fas fa-exclamation-triangle"></i> Cần bảo trì
            </button>
            <?php if ($is_admin): ?>
            <button class="btn-import" onclick="openImportModal()"><i class="fas fa-file-excel"></i> Import Excel</button>
            <button class="btn-primary" onclick="openDeviceModal()"><i class="fas fa-plus"></i> Thêm thiết bị</button>
            <?php endif; ?>
        </div>
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-dumbbell" style="color:#d4a017;margin-right:8px"></i>Danh sách thiết bị</h3>
                <span class="table-meta" id="devMeta">Đang tải...</span>
            </div>
            <div class="table-wrapper"><table>
                <thead><tr>
                    <th>#</th><th>Tên thiết bị</th><th>Loại</th><th>Phòng tập</th><th>Trạng thái</th>
                    <th>Giá mua</th><th>Ngày mua</th><th>Bảo trì gần nhất</th><th>Hạn bảo trì</th><th>Thao tác</th>
                </tr></thead>
                <tbody id="devTbody"><tr><td colspan="10" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
            </table></div>
            <div class="pagination"><div class="pagination-info" id="devPagInfo">—</div><div class="pagination-controls" id="devPagCtrl"></div></div>
        </div>
    </div>
    <div class="tab-content" id="tab-maintenance">
        <div class="filter-bar">
            <div style="flex:1"></div>
            <?php if ($is_admin || $is_recept || $is_hlv): ?>
            <button class="btn-primary" onclick="openMaintenanceModal()"><i class="fas fa-plus"></i> Thêm bảo trì</button>
            <?php endif; ?>
        </div>
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-wrench" style="color:#d4a017;margin-right:8px"></i>Lịch sử bảo trì</h3>
                <span class="table-meta" id="btMeta">—</span>
            </div>
            <div class="table-wrapper"><table>
                <thead><tr>
                    <th>#</th><th>Thiết bị</th><th>Ngày bảo trì</th><th>Nội dung</th>
                    <th>Người thực hiện</th><th>Chi phí</th><th>Trạng thái</th><th>Thao tác</th>
                </tr></thead>
                <tbody id="btTbody"><tr><td colspan="8" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
            </table></div>
            <div class="pagination"><div class="pagination-info" id="btPagInfo">—</div><div class="pagination-controls" id="btPagCtrl"></div></div>
        </div>
    </div>

</div>

<!-- MODAL THIẾT BỊ -->
<div class="modal-overlay" id="deviceModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="devModalTitle"><i class="fas fa-dumbbell" style="color:#d4a017;margin-right:8px"></i>Thêm thiết bị</h3>
            <button class="btn-close" onclick="closeModal('deviceModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="fDevId">
            <div class="form-grid">
                <div class="form-group full"><label>Tên thiết bị <span class="req">*</span></label><input type="text" id="fDevTen" class="form-control" placeholder="VD: Máy chạy bộ Technogym T80"></div>
                <div class="form-group"><label>Loại thiết bị</label><select id="fDevLoai" class="form-control"><option value="">-- Chọn loại --</option></select></div>
                <div class="form-group"><label>Trạng thái</label>
                    <select id="fDevStatus" class="form-control">
                        <option value="Hoạt động">✅ Hoạt động</option>
                        <option value="Hỏng">❌ Hỏng</option>
                        <option value="Đang bảo dưỡng">🔧 Đang bảo dưỡng</option>
                        <option value="Ngừng sử dụng">⛔ Ngừng sử dụng</option>
                    </select>
                </div>
                <div class="form-group"><label>Giá mua (₫)</label><input type="number" id="fDevGia" class="form-control" placeholder="VD: 15000000" min="0"></div>
                <div class="form-group"><label>Ngày mua</label><input type="date" id="fDevNgayMua" class="form-control"></div>
                <div class="form-group"><label>Ngày bảo trì gần nhất</label><input type="date" id="fDevNgayBao" class="form-control"></div>
                <div class="form-group"><label>Phòng tập</label><select id="fDevRoom" class="form-control"><option value="">-- Chưa phân phòng --</option></select></div>
                <div class="form-group full"><label>Mô tả</label><textarea id="fDevMoTa" class="form-control" rows="2" placeholder="Ghi chú..."></textarea></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('deviceModal')">Hủy</button>
            <button class="btn-primary" onclick="saveDevice()"><i class="fas fa-save"></i> Lưu</button>
        </div>
    </div>
</div>

<!-- MODAL BẢO TRÌ -->
<div class="modal-overlay" id="maintenanceModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-wrench" style="color:#d4a017;margin-right:8px"></i>Thêm bảo trì</h3>
            <button class="btn-close" onclick="closeModal('maintenanceModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full"><label>Thiết bị <span class="req">*</span></label><select id="fBtDev" class="form-control"><option value="">-- Chọn thiết bị --</option></select></div>
                <div class="form-group"><label>Ngày bảo trì <span class="req">*</span></label><input type="date" id="fBtNgay" class="form-control"></div>
                <div class="form-group"><label>Trạng thái</label>
                    <select id="fBtStatus" class="form-control">
                        <option value="Completed">✅ Hoàn thành</option>
                        <option value="In Progress">🔧 Đang xử lý</option>
                        <option value="Scheduled">📅 Lên lịch</option>
                    </select>
                </div>
                <div class="form-group"><label>Chi phí bảo trì (₫)</label><input type="number" id="fBtGia" class="form-control" placeholder="0" min="0"></div>
                <div class="form-group"><label>Người / đơn vị thực hiện</label><input type="text" id="fBtNguoi" class="form-control" placeholder="VD: Kỹ thuật viên Tuấn"></div>
                <div class="form-group full"><label>Nội dung bảo trì</label><textarea id="fBtNoiDung" class="form-control" rows="3" placeholder="Mô tả công việc..."></textarea></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('maintenanceModal')">Hủy</button>
            <button class="btn-primary" onclick="saveMaintenance()"><i class="fas fa-save"></i> Lưu</button>
        </div>
    </div>
</div>

<!-- MODAL CHI TIẾT -->
<div class="modal-overlay" id="detailModal">
    <div class="modal" style="max-width:640px">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle" style="color:#d4a017;margin-right:8px"></i>Chi tiết thiết bị</h3>
            <button class="btn-close" onclick="closeModal('detailModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="detailContent"><div class="loading-cell"><i class="fas fa-spinner fa-spin"></i></div></div>
    </div>
</div>

<!-- MODAL CHỈNH SỬA BẢO TRÌ -->
<div class="modal-overlay" id="editMaintenanceModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-pen" style="color:#d4a017;margin-right:8px"></i>Chỉnh sửa bảo trì</h3>
            <button class="btn-close" onclick="closeModal('editMaintenanceModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="fEditBtId">
            <div class="form-grid">
                <div class="form-group full"><label>Thiết bị</label><input type="text" id="fEditBtDevName" class="form-control" disabled style="opacity:.6"></div>
                <div class="form-group"><label>Ngày bảo trì <span class="req">*</span></label><input type="date" id="fEditBtNgay" class="form-control"></div>
                <div class="form-group"><label>Trạng thái</label>
                    <select id="fEditBtStatus" class="form-control">
                        <option value="Completed">✅ Hoàn thành</option>
                        <option value="In Progress">🔧 Đang xử lý</option>
                        <option value="Scheduled">📅 Lên lịch</option>
                    </select>
                </div>
                <div class="form-group"><label>Chi phí bảo trì (₫)</label><input type="number" id="fEditBtGia" class="form-control" placeholder="0" min="0"></div>
                <div class="form-group"><label>Người / đơn vị thực hiện</label><input type="text" id="fEditBtNguoi" class="form-control" placeholder="VD: Kỹ thuật viên Tuấn"></div>
                <div class="form-group full"><label>Nội dung bảo trì</label><textarea id="fEditBtNoiDung" class="form-control" rows="3" placeholder="Mô tả công việc..."></textarea></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('editMaintenanceModal')">Hủy</button>
            <button class="btn-primary" onclick="saveEditMaintenance()"><i class="fas fa-save"></i> Lưu</button>
        </div>
    </div>
</div>

<!-- MODAL XÁC NHẬN XÓA -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal confirm-modal">
        <div class="modal-body">
            <div class="confirm-icon"><i class="fas fa-trash-alt"></i></div>
            <h4>Xác nhận xóa?</h4>
            <p id="confirmMsg">Hành động này không thể hoàn tác.</p>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <button class="btn-secondary" onclick="closeModal('confirmModal')">Hủy</button>
            <button class="btn-danger" id="confirmOkBtn"><i class="fas fa-trash"></i> Xóa</button>
        </div>
    </div>
</div>

<!-- MODAL IMPORT EXCEL -->
<div class="modal-overlay" id="importModal">
    <div class="modal" style="max-width:660px">
        <div class="modal-header">
            <h3><i class="fas fa-file-excel" style="color:#22c55e;margin-right:8px"></i>Import thiết bị từ Excel</h3>
            <button class="btn-close" onclick="closeModal('importModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <!-- Hướng dẫn -->
            <div class="import-guide">
                <div class="import-guide-title"><i class="fas fa-info-circle"></i> Định dạng file Excel (.xlsx / .xls / .csv)</div>
                <div class="import-cols-wrap">
                    <div class="import-col-item req-col"><span class="col-badge req-badge">BẮT BUỘC</span><strong>equipment_name</strong><span>Tên thiết bị</span></div>
                    <div class="import-col-item"><span class="col-badge">Tuỳ chọn</span><strong>type_name</strong><span>Loại (khớp tên loại)</span></div>
                    <div class="import-col-item"><span class="col-badge">Tuỳ chọn</span><strong>condition_status</strong><span>Hoạt động / Hỏng / Đang bảo dưỡng</span></div>
                    <div class="import-col-item"><span class="col-badge">Tuỳ chọn</span><strong>room_name</strong><span>Tên phòng (khớp tên phòng)</span></div>
                    <div class="import-col-item"><span class="col-badge">Tuỳ chọn</span><strong>purchase_price</strong><span>Giá mua (số)</span></div>
                    <div class="import-col-item"><span class="col-badge">Tuỳ chọn</span><strong>purchase_date</strong><span>Ngày mua (YYYY-MM-DD)</span></div>
                    <div class="import-col-item"><span class="col-badge">Tuỳ chọn</span><strong>last_maintenance_date</strong><span>Ngày bảo trì gần nhất</span></div>
                    <div class="import-col-item"><span class="col-badge">Tuỳ chọn</span><strong>description</strong><span>Mô tả</span></div>
                </div>
                <a class="import-template-link" onclick="downloadTemplate()"><i class="fas fa-download"></i> Tải file mẫu (.csv)</a>
            </div>
            <!-- Upload zone -->
            <div class="import-dropzone" id="importDropzone" onclick="document.getElementById('importFileInput').click()">
                <input type="file" id="importFileInput" accept=".xlsx,.xls,.csv" style="display:none" onchange="handleImportFile(this.files[0])">
                <div class="import-drop-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                <div class="import-drop-text">Kéo thả file vào đây hoặc <span class="import-drop-link">chọn file</span></div>
                <div class="import-drop-hint">.xlsx · .xls · .csv · tối đa 5MB</div>
            </div>
            <!-- Preview -->
            <div id="importPreview" style="display:none">
                <div class="import-preview-header">
                    <span id="importFileLabel" class="import-file-label"></span>
                    <span id="importRowCount" class="import-row-count"></span>
                    <button class="import-clear-btn" onclick="clearImport()"><i class="fas fa-times"></i> Xóa</button>
                </div>
                <div class="table-wrapper" style="max-height:220px;overflow-y:auto;border-radius:8px;border:1px solid var(--border)">
                    <table style="font-size:12px;min-width:100%">
                        <thead id="importPreviewHead"></thead>
                        <tbody id="importPreviewBody"></tbody>
                    </table>
                </div>
                <!-- Validate errors -->
                <div id="importErrors" style="display:none" class="import-errors"></div>
            </div>
            <!-- Progress -->
            <div id="importProgress" style="display:none" class="import-progress-wrap">
                <div class="import-progress-bar-track"><div class="import-progress-bar" id="importProgressBar"></div></div>
                <div class="import-progress-label" id="importProgressLabel">Đang xử lý...</div>
            </div>
            <!-- Result -->
            <div id="importResult" style="display:none" class="import-result"></div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('importModal')">Đóng</button>
            <button class="btn-primary" id="importSubmitBtn" onclick="submitImport()" disabled style="opacity:.4;cursor:not-allowed">
                <i class="fas fa-upload"></i> Nhập dữ liệu
            </button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script>
    const USER_ROLE     = <?= $js_role ?>;
    const USER_POSITION = <?= $js_position ?>;
    const USER_EMP_ID   = <?= $js_emp_id ?>;
    const IS_ADMIN      = <?= $is_admin  ? 'true' : 'false' ?>;
    const IS_RECEPT     = <?= $is_recept ? 'true' : 'false' ?>;
    const IS_HLV        = <?= $is_hlv    ? 'true' : 'false' ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="Facilities_Management.js"></script>
</body>
</html>