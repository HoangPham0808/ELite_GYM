<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý khách hàng - Elite Gym</title>
    <link rel="stylesheet" href="Customer_Management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="customer-container">


    <!-- STATS STRIP -->
    <div class="stats-strip">
        <div class="strip-card blue">
            <div class="strip-icon"><i class="fas fa-users"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statTotal">—</div>
                <div class="strip-label">Tổng thành viên</div>
            </div>
        </div>
        <div class="strip-card green">
            <div class="strip-icon"><i class="fas fa-user-check"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statActive">—</div>
                <div class="strip-label">Đang có gói tập</div>
            </div>
        </div>
        <div class="strip-card gold">
            <div class="strip-icon"><i class="fas fa-user-plus"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statNewMonth">—</div>
                <div class="strip-label">Mới tháng này</div>
            </div>
        </div>
        <div class="strip-card red">
            <div class="strip-icon"><i class="fas fa-clock"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statExpiring">—</div>
                <div class="strip-label">Sắp hết hạn (7 ngày)</div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Tìm kiếm theo tên, SĐT, email..."autocomplete="off">
        </div>
        <select class="filter-select" id="genderFilter">
            <option value="">Tất cả giới tính</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
        </select>
        <select class="filter-select" id="statusFilter">
            <option value="">Tất cả trạng thái</option>
            <option value="active">Đang hoạt động</option>
            <option value="expiring">Sắp hết hạn</option>
            <option value="expired">Hết hạn</option>
            <option value="none">Chưa có gói</option>
        </select>
        <button class="btn-primary" onclick="openAddModal()">
            <i class="fas fa-user-plus"></i> Thêm khách hàng
        </button>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card">
        <div class="table-header">
            <h3>Danh sách thành viên</h3>
            <span class="table-meta" id="tableTotal">Đang tải...</span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Khách hàng</th>
                        <th>Số điện thoại</th>
                        <th>Giới tính</th>
                        <th>Ngày đăng ký</th>
                        <th>Trạng thái</th>
                        <th>Gói tập hiện tại</th>
                        <th>Hết hạn</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="customerTbody">
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:rgba(255,255,255,0.3)"><i class="fas fa-spinner fa-spin" style="font-size:24px"></i></td></tr>
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <div class="pagination-info" id="paginationInfo">—</div>
            <div class="pagination-controls" id="paginationControls"></div>
        </div>
    </div>

</div>

<!-- ===== MODAL THÊM / SỬA KHÁCH HÀNG ===== -->
<div class="modal-overlay" id="customerModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Thêm khách hàng mới</h3>
            <button class="btn-close" onclick="closeCustomerModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="customerForm">
            <input type="hidden" id="customerId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Họ và tên <span class="required">*</span></label>
                    <input type="text" id="fHoTen" class="form-control" placeholder="Nguyễn Văn A">
                </div>
                <div class="form-group">
                    <label>Ngày sinh</label>
                    <input type="date" id="fNgaySinh" class="form-control">
                </div>
                <div class="form-group">
                    <label>Giới tính</label>
                    <select id="fGioiTinh" class="form-control">
                        <option value="">— Chọn —</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Số điện thoại</label>
                    <input type="tel" id="fSoDienThoai" class="form-control" placeholder="0901234567">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="fEmail" class="form-control" placeholder="example@email.com">
                </div>
                <div class="form-group full">
                    <label>Địa chỉ</label>
                    <input type="text" id="fDiaChi" class="form-control" placeholder="123 Nguyễn Huệ, Q.1, TP.HCM">
                </div>
            </div>

            <!-- PHẦN TÀI KHOẢN -->
            <div class="account-section" id="accountSection">
                <div class="account-section-header">
                    <i class="fas fa-user-lock"></i>
                    <span id="accountSectionTitle">Tài khoản đăng nhập (bắt buộc)</span>
                    <span class="account-required-badge" id="accountRequiredBadge">Bắt buộc</span>
                </div>
                <div class="form-grid" style="margin-top:12px">
                    <div class="form-group">
                        <label>Tên đăng nhập <span class="required" id="usernameRequired">*</span></label>
                        <input type="text" id="fTenDangNhap" class="form-control" placeholder="VD: nguyenvana">
                    </div>
                    <div class="form-group" id="passwordGroup">
                        <label>Mật khẩu <span class="required">*</span></label>
                        <div class="password-input-wrap">
                            <input type="password" id="fMatKhau" class="form-control" placeholder="Nhập mật khẩu">
                            <button type="button" class="btn-toggle-pw" onclick="togglePassword()" tabindex="-1">
                                <i class="fas fa-eye" id="pwEyeIcon"></i>
                            </button>
                        </div>
                        <div class="pw-hint" id="pwHint">Mặc định: <code>elitegym@2025</code> (để trống để dùng mặc định)</div>
                    </div>
                    <div class="form-group" id="editAccountNote" style="display:none">
                        <label style="color:rgba(255,255,255,0.4);font-size:12px">
                            <i class="fas fa-info-circle"></i> Để trống tên đăng nhập = giữ nguyên tài khoản cũ
                        </label>
                    </div>
                </div>
            </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeCustomerModal()">Hủy</button>
            <button class="btn-primary" id="btnSave" onclick="saveCustomer()">
                <i class="fas fa-save"></i> Lưu
            </button>
        </div>
    </div>
</div>

<!-- ===== MODAL XEM CHI TIẾT ===== -->
<div class="modal-overlay" id="detailModal">
    <div class="modal" style="max-width:680px">
        <div class="modal-header">
            <h3><i class="fas fa-id-card" style="color:#d4a017;margin-right:8px"></i>Chi tiết khách hàng</h3>
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
            <h4>Xóa khách hàng?</h4>
            <p>Bạn có chắc muốn xóa <strong id="deleteCustomerName"></strong>?<br>Hành động này không thể hoàn tác.</p>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <button class="btn-secondary" onclick="closeConfirmModal()">Hủy</button>
            <button class="btn-danger" onclick="doDelete()"><i class="fas fa-trash"></i> Xóa</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast-container" id="toastContainer"></div>

<script src="Customer_Management.js"></script>
</body>
</html>
