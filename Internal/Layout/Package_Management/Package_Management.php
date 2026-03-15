<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý gói tập - Elite Gym</title>
    <link rel="stylesheet" href="Package_Management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="package-container">

    <!-- STATS STRIP -->
    <div class="stats-strip">
        <div class="strip-card blue">
            <div class="strip-icon"><i class="fas fa-layer-group"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statTotal">—</div>
                <div class="strip-label">Tổng gói tập</div>
            </div>
        </div>
        <div class="strip-card green">
            <div class="strip-icon"><i class="fas fa-users"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statActiveSubscribers">—</div>
                <div class="strip-label">Đang sử dụng</div>
            </div>
        </div>
        <div class="strip-card gold">
            <div class="strip-icon"><i class="fas fa-fire"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statPopular">—</div>
                <div class="strip-label">Gói phổ biến nhất</div>
            </div>
        </div>
        <div class="strip-card purple">
            <div class="strip-icon"><i class="fas fa-coins"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statAvgPrice">—</div>
                <div class="strip-label">Giá trung bình</div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Tìm kiếm theo tên gói, mô tả...">
        </div>
        <select class="filter-select" id="durationFilter">
            <option value="">Tất cả thời hạn</option>
            <option value="1">1 tháng</option>
            <option value="3">3 tháng</option>
            <option value="6">6 tháng</option>
            <option value="12">12 tháng</option>
        </select>
        <select class="filter-select" id="sortFilter">
            <option value="id_desc">Mới nhất</option>
            <option value="price_asc">Giá tăng dần</option>
            <option value="price_desc">Giá giảm dần</option>
            <option value="duration_asc">Thời hạn tăng dần</option>
            <option value="subscribers_desc">Đăng ký nhiều nhất</option>
        </select>
        <button class="btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Thêm gói tập
        </button>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list" style="color:#d4a017;margin-right:8px;font-size:15px"></i>Danh sách gói tập</h3>
            <div class="table-header-right">
                <span class="table-meta" id="tableTotal">Đang tải...</span>
            </div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Gói tập</th>
                        <th>Thời hạn</th>
                        <th>Giá</th>
                        <th>Mô tả</th>
                        <th>Số người đăng ký</th>
                        <th>Đang hoạt động</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="packageTbody">
                    <tr>
                        <td colspan="7" style="text-align:center;padding:40px;color:rgba(255,255,255,0.3)">
                            <i class="fas fa-spinner fa-spin" style="font-size:24px"></i>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <div class="pagination-info" id="paginationInfo">—</div>
            <div class="pagination-controls" id="paginationControls"></div>
        </div>
    </div>

</div>

<!-- ===== MODAL THÊM / SỬA GÓI TẬP ===== -->
<div class="modal-overlay" id="packageModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Thêm gói tập mới</h3>
            <button class="btn-close" onclick="closePackageModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="packageId">
            <input type="hidden" id="fRemoveImage" value="0">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Tên gói tập <span class="required">*</span></label>
                    <input type="text" id="fTenGoi" class="form-control" placeholder="VD: Gói Cơ Bản 1 Tháng">
                </div>
                <div class="form-group">
                    <label>Thời hạn (tháng) <span class="required">*</span></label>
                    <input type="number" id="fThoiHan" class="form-control" placeholder="VD: 1, 3, 6, 12" min="1" max="60">
                </div>
                <div class="form-group">
                    <label>Giá (VNĐ) <span class="required">*</span></label>
                    <div class="input-prefix-wrap">
                        <span class="prefix">₫</span>
                        <input type="number" id="fGia" class="form-control" placeholder="VD: 500000" min="0">
                    </div>
                </div>
                <div class="form-group full">
                    <label>Mô tả</label>
                    <textarea id="fMoTa" class="form-control" placeholder="Mô tả chi tiết về gói tập, quyền lợi kèm theo..."></textarea>
                </div>

                <!-- ── ẢNH GÓI TẬP ── -->
                <div class="form-group full">
                    <label>Ảnh gói tập</label>
                    <div class="img-upload-zone" id="imgUploadZone" onclick="document.getElementById('fImage').click()">
                        <input type="file" id="fImage" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
                        <!-- Preview ảnh cũ / ảnh mới chọn -->
                        <div id="imgPreviewWrap" style="display:none">
                            <img id="imgPreview" src="" alt="Preview"/>
                            <button type="button" class="img-remove-btn" onclick="removeImage(event)" title="Xóa ảnh">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <!-- Placeholder khi chưa có ảnh -->
                        <div id="imgPlaceholder">
                            <i class="fas fa-image"></i>
                            <span>Nhấp hoặc kéo thả ảnh vào đây</span>
                            <small>JPG · PNG · WEBP · GIF · Tối đa 5 MB</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closePackageModal()">Hủy</button>
            <button class="btn-primary" onclick="savePackage()">
                <i class="fas fa-save"></i> Lưu
            </button>
        </div>
    </div>
</div>

<!-- ===== MODAL XEM CHI TIẾT ===== -->
<div class="modal-overlay" id="detailModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <h3><i class="fas fa-dumbbell" style="color:#d4a017;margin-right:8px"></i>Chi tiết gói tập</h3>
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
            <h4>Xóa gói tập?</h4>
            <p>Bạn có chắc muốn xóa gói <strong id="deletePackageName"></strong>?<br>Hành động này không thể hoàn tác.</p>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <button class="btn-secondary" onclick="closeConfirmModal()">Hủy</button>
            <button class="btn-danger" onclick="doDelete()"><i class="fas fa-trash"></i> Xóa</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast-container" id="toastContainer"></div>

<script src="Package_Management.js"></script>
</body>
</html>
