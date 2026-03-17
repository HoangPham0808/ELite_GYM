<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý phòng tập - Elite Gym</title>
    <link rel="stylesheet" href="Gym_Management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="gym-container">

    <!-- STATS STRIP -->
    <div class="stats-strip">
        <div class="strip-card orange">
            <div class="strip-icon"><i class="fas fa-chess-board"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statTotal">—</div>
                <div class="strip-label">Tổng phòng tập</div>
            </div>
        </div>
        <div class="strip-card green">
            <div class="strip-icon"><i class="fas fa-door-open"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statActive">—</div>
                <div class="strip-label">Đang hoạt động</div>
            </div>
        </div>
        <div class="strip-card red">
            <div class="strip-icon"><i class="fas fa-tools"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statMaintenance">—</div>
                <div class="strip-label">Đang bảo trì</div>
            </div>
        </div>
        <div class="strip-card blue">
            <div class="strip-icon"><i class="fas fa-users"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statCapacity">—</div>
                <div class="strip-label">Tổng sức chứa</div>
            </div>
        </div>
        <div class="strip-card purple">
            <div class="strip-icon"><i class="fas fa-dumbbell"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statThietBi">—</div>
                <div class="strip-label">Thiết bị đang dùng</div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Tìm theo tên phòng, loại phòng...">
        </div>
        <select class="filter-select" id="statusFilter">
            <option value="">Tất cả trạng thái</option>
            <option value="Hoạt động">Hoạt động</option>
            <option value="Bảo trì">Bảo trì</option>
            <option value="Đóng cửa">Đóng cửa</option>
        </select>

        <select class="filter-select" id="sortFilter">
            <option value="id_desc">Mới nhất</option>
            <option value="id_asc">Cũ nhất</option>
            <option value="name_asc">Tên A→Z</option>
            <option value="cap_desc">Sức chứa cao→thấp</option>
        </select>
        <button class="btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Thêm phòng tập
        </button>
    </div>

    <!-- GRID VIEW + TABLE TOGGLE -->
    <div class="view-toggle-bar">
        <h3 class="view-title">
            <i class="fas fa-chess-board" style="color:#fb923c;margin-right:8px;font-size:15px"></i>
            Danh sách phòng tập
        </h3>
        <div class="view-toggle-right">
            <span class="table-meta" id="tableTotal">Đang tải...</span>
            <div class="view-btns">
                <button class="view-btn active" id="btnGrid" onclick="setView('grid')" title="Dạng lưới">
                    <i class="fas fa-th-large"></i>
                </button>
                <button class="view-btn" id="btnList" onclick="setView('list')" title="Dạng danh sách">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- GRID VIEW -->
    <div id="gridView" class="room-grid">
        <div class="loading-placeholder">
            <i class="fas fa-spinner fa-spin"></i>
        </div>
    </div>

    <!-- LIST VIEW -->
    <div id="listView" class="table-card" style="display:none">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Phòng tập</th>
                        <th>Sức chứa</th>
                        <th>Diện tích</th>
                        <th>Trạng thái</th>
                        <th>Thiết bị</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="gymTbody">
                    <tr>
                        <td colspan="6" style="text-align:center;padding:40px;color:rgba(255,255,255,0.3)">
                            <i class="fas fa-spinner fa-spin" style="font-size:24px"></i>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PAGINATION -->
    <div class="pagination">
        <div class="pagination-info" id="paginationInfo">—</div>
        <div class="pagination-controls" id="paginationControls"></div>
    </div>

</div>

<!-- ===== MODAL THÊM / SỬA PHÒNG TẬP ===== -->
<div class="modal-overlay" id="gymModal">
    <div class="modal" style="max-width:640px">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-chess-board" style="color:#fb923c;margin-right:8px"></i>Thêm phòng tập mới</h3>
            <button class="btn-close" onclick="closeGymModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="gymId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Tên phòng tập <span class="required">*</span></label>
                    <input type="text" id="fTenPhong" class="form-control" placeholder="VD: Phòng Yoga A1">
                </div>
                <div class="form-group full">
                    <label>Loại gói tập yêu cầu</label>
                    <select id="fPackageType" class="form-control">
                        <option value="">— Tất cả loại gói (không giới hạn) —</option>
                    </select>
                    <small style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;display:block">Chọn loại gói tập tối thiểu cần có để vào phòng này</small>
                </div>

                <div class="form-group">
                    <label>Trạng thái</label>
                    <select id="fTrangThai" class="form-control">
                        <option value="Hoạt động">✅ Hoạt động</option>
                        <option value="Bảo trì">🔧 Bảo trì</option>
                        <option value="Đóng cửa">🚫 Đóng cửa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sức chứa (người)</label>
                    <input type="number" id="fSucChua" class="form-control" placeholder="VD: 30" min="1">
                </div>
                <div class="form-group">
                    <label>Diện tích (m²)</label>
                    <input type="number" id="fDienTich" class="form-control" placeholder="VD: 150" min="1">
                </div>
                <div class="form-group">
                    <label>Tầng</label>
                    <input type="number" id="fTang" class="form-control" placeholder="VD: 1" min="0">
                </div>
                <div class="form-group">
                    <label>Giờ mở cửa</label>
                    <input type="time" id="fGioMo" class="form-control" value="06:00">
                </div>
                <div class="form-group full">
                    <label>Mô tả</label>
                    <textarea id="fMoTa" class="form-control" rows="3" placeholder="Mô tả tiện ích, trang thiết bị của phòng..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeGymModal()">Hủy</button>
            <button class="btn-primary" onclick="saveGym()">
                <i class="fas fa-save"></i> Lưu phòng tập
            </button>
        </div>
    </div>
</div>

<!-- ===== MODAL XEM CHI TIẾT ===== -->
<div class="modal-overlay" id="detailModal">
    <div class="modal" style="max-width:720px">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle" style="color:#fb923c;margin-right:8px"></i>Chi tiết phòng tập</h3>
            <button class="btn-close" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="detailContent">
            <!-- Rendered by JS -->
        </div>
    </div>
</div>

<!-- ===== MODAL XÁC NHẬN XÓA ===== -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal confirm-modal">
        <div class="modal-body">
            <div class="confirm-icon"><i class="fas fa-trash-alt"></i></div>
            <h4>Xóa phòng tập?</h4>
            <p>Bạn có chắc muốn xóa phòng <strong id="deleteGymName"></strong>?<br>Hành động này không thể hoàn tác.</p>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <button class="btn-secondary" onclick="closeConfirmModal()">Hủy</button>
            <button class="btn-danger" onclick="doDelete()"><i class="fas fa-trash"></i> Xóa</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast-container" id="toastContainer"></div>

<script src="Gym_Management.js"></script>
</body>
</html>
