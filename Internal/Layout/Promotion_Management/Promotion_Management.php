<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Khuyến Mãi - Elite Gym</title>
    <link rel="stylesheet" href="Promotion_Management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="promo-container">

    <!-- STATS STRIP -->
    <div class="stats-strip">
        <div class="strip-card pink">
            <div class="strip-icon"><i class="fas fa-tag"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statTotal">—</div>
                <div class="strip-label">Tổng khuyến mãi</div>
            </div>
        </div>
        <div class="strip-card green">
            <div class="strip-icon"><i class="fas fa-check-circle"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statActive">—</div>
                <div class="strip-label">Đang hoạt động</div>
            </div>
        </div>
        <div class="strip-card red">
            <div class="strip-icon"><i class="fas fa-clock"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statExpired">—</div>
                <div class="strip-label">Đã hết hạn</div>
            </div>
        </div>
        <div class="strip-card orange">
            <div class="strip-icon"><i class="fas fa-fire"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statUsed">—</div>
                <div class="strip-label">Lượt đã dùng</div>
            </div>
        </div>
        <div class="strip-card blue">
            <div class="strip-icon"><i class="fas fa-percent"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statAvgDiscount">—</div>
                <div class="strip-label">Giảm giá TB</div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Tìm tên khuyến mãi...">
        </div>
        <select class="filter-select" id="statusFilter">
            <option value="">Tất cả trạng thái</option>
            <option value="Active">Active</option>
            <option value="Expired">Expired</option>
            <option value="Paused">Paused</option>
        </select>
        <select class="filter-select" id="sortFilter">
            <option value="id_desc">Mới nhất</option>
            <option value="id_asc">Cũ nhất</option>
            <option value="discount_desc">% Giảm cao→thấp</option>
            <option value="end_asc">Sắp hết hạn</option>
        </select>
        <button class="btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Thêm khuyến mãi
        </button>
    </div>

    <!-- TABLE HEADER -->
    <div class="view-toggle-bar">
        <h3 class="view-title">
            <i class="fas fa-tag" style="color:#f472b6;margin-right:8px;font-size:15px"></i>
            Danh sách khuyến mãi
        </h3>
        <div class="view-toggle-right">
            <span class="table-meta" id="tableTotal">Đang tải...</span>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Tên khuyến mãi</th>
                        <th>% Giảm</th>
                        <th>Đơn tối thiểu</th>
                        <th>Giảm tối đa</th>
                        <th>Số lượng</th>
                        <th>Đã dùng</th>
                        <th>Ngày bắt đầu</th>
                        <th>Ngày kết thúc</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="promoTbody">
                    <tr>
                        <td colspan="10" class="loading-cell">
                            <i class="fas fa-spinner fa-spin"></i>
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

<!-- ===== MODAL THÊM / SỬA ===== -->
<div class="modal-overlay" id="promoModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-tag" style="color:#f472b6;margin-right:8px"></i>Thêm khuyến mãi mới</h3>
            <button class="btn-close" onclick="closePromoModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="promoId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Tên khuyến mãi <span class="required">*</span></label>
                    <input type="text" id="fTenKM" class="form-control" placeholder="VD: Giảm 20% Hè 2025">
                </div>

                <div class="form-group">
                    <label>% Giảm giá <span class="required">*</span></label>
                    <div class="input-suffix">
                        <input type="number" id="fPhanTram" class="form-control" placeholder="VD: 20" min="0" max="100" step="0.01">
                        <span class="suffix-badge">%</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Trạng thái</label>
                    <select id="fTrangThai" class="form-control">
                        <option value="Active">✅ Active</option>
                        <option value="Paused">⏸️ Paused</option>
                        <option value="Expired">❌ Expired</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Đơn hàng tối thiểu (₫)</label>
                    <div class="input-suffix">
                        <input type="number" id="fDonToiThieu" class="form-control" placeholder="VD: 500000" min="0">
                        <span class="suffix-badge">₫</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Giảm tối đa (₫)</label>
                    <div class="input-suffix">
                        <input type="number" id="fGiamToiDa" class="form-control" placeholder="Để trống = không giới hạn" min="0">
                        <span class="suffix-badge">₫</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Số lượng mã</label>
                    <input type="number" id="fSoLuong" class="form-control" placeholder="Để trống = không giới hạn" min="1">
                </div>

                <div class="form-group">
                    <label>Ngày bắt đầu <span class="required">*</span></label>
                    <input type="date" id="fNgayBD" class="form-control">
                </div>

                <div class="form-group">
                    <label>Ngày kết thúc <span class="required">*</span></label>
                    <input type="date" id="fNgayKT" class="form-control">
                </div>

                <div class="form-group full">
                    <label>Mô tả</label>
                    <textarea id="fMoTa" class="form-control" rows="3" placeholder="Mô tả chi tiết chương trình khuyến mãi..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closePromoModal()">Hủy</button>
            <button class="btn-primary" onclick="savePromo()">
                <i class="fas fa-save"></i> Lưu khuyến mãi
            </button>
        </div>
    </div>
</div>

<!-- ===== MODAL XÁC NHẬN XÓA ===== -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal confirm-modal">
        <div class="modal-body">
            <div class="confirm-icon"><i class="fas fa-trash-alt"></i></div>
            <h4>Xóa khuyến mãi?</h4>
            <p>Bạn có chắc muốn xóa <strong id="deletePromoName"></strong>?<br>Hành động này không thể hoàn tác.</p>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <button class="btn-secondary" onclick="closeConfirmModal()">Hủy</button>
            <button class="btn-danger" onclick="doDelete()"><i class="fas fa-trash"></i> Xóa</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast-container" id="toastContainer"></div>

<script src="Promotion_Management.js"></script>
</body>
</html>
