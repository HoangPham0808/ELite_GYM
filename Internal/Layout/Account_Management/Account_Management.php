<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tài khoản - Elite Gym</title>
    <link rel="stylesheet" href="Account_Management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="acc-container">

    <!-- STATS STRIP -->
    <div class="stats-strip">
        <div class="strip-card c-indigo">
            <div class="strip-icon"><i class="fas fa-users"></i></div>
            <div class="strip-info"><div class="strip-value" id="sTotal">—</div><div class="strip-label">Tổng tài khoản</div></div>
        </div>
        <div class="strip-card c-green">
            <div class="strip-icon"><i class="fas fa-circle-check"></i></div>
            <div class="strip-info"><div class="strip-value" id="sActive">—</div><div class="strip-label">Đang hoạt động</div></div>
        </div>
        <div class="strip-card c-gold">
            <div class="strip-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="strip-info"><div class="strip-value" id="sAdmin">—</div><div class="strip-label">Admin</div></div>
        </div>
        <div class="strip-card c-blue">
            <div class="strip-icon"><i class="fas fa-id-badge"></i></div>
            <div class="strip-info"><div class="strip-value" id="sNV">—</div><div class="strip-label">Nhân viên</div></div>
        </div>
        <div class="strip-card c-purple">
            <div class="strip-icon"><i class="fas fa-person"></i></div>
            <div class="strip-info"><div class="strip-value" id="sKH">—</div><div class="strip-label">Khách hàng</div></div>
        </div>
        <div class="strip-card c-teal">
            <div class="strip-icon"><i class="fas fa-right-to-bracket"></i></div>
            <div class="strip-info"><div class="strip-value" id="sTodayLogin">—</div><div class="strip-label">Đăng nhập hôm nay</div></div>
        </div>
    </div>

    <!-- TABS -->
    <div class="tab-bar">
        <button class="tab-btn active" data-tab="staff">
            <i class="fas fa-id-badge"></i>
            <span>Nhân viên & Admin</span>
            <span class="tab-count" id="tcStaff">—</span>
        </button>
        <button class="tab-btn" data-tab="customer">
            <i class="fas fa-person"></i>
            <span>Khách hàng</span>
            <span class="tab-count" id="tcCustomer">—</span>
        </button>
        <button class="tab-btn" data-tab="loginlog">
            <i class="fas fa-clock-rotate-left"></i>
            <span>Lịch sử đăng nhập</span>
        </button>
    </div>

    <!-- ═══ TAB NHÂN VIÊN ═══ -->
    <div class="tab-content active" id="tab-staff">
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="nvSearch" placeholder="Tìm tên đăng nhập, họ tên...">
            </div>
            <select class="filter-select" id="nvRole">
                <option value="">Tất cả vai trò</option>
                <option value="Admin">🛡️ Admin</option>
                <option value="Employee">👤 Nhân viên</option>
            </select>
            <select class="filter-select" id="nvStatus">
                <option value="">Tất cả trạng thái</option>
                <option value="1">✅ Hoạt động</option>
                <option value="0">🔒 Đã khóa</option>
            </select>
            <button class="btn-primary" onclick="openAddModal('staff')">
                <i class="fas fa-plus"></i> Thêm tài khoản
            </button>
        </div>

        <!-- ROLE SUMMARY BARS -->
        <div class="role-summary">
            <div class="role-bar rb-admin">
                <div class="rb-icon"><i class="fas fa-shield-halved"></i></div>
                <div class="rb-info">
                    <span class="rb-label">Admin</span>
                    <span class="rb-desc">Toàn quyền hệ thống</span>
                </div>
                <span class="rb-count" id="rbAdmin">—</span>
            </div>
            <div class="role-bar rb-staff">
                <div class="rb-icon"><i class="fas fa-id-badge"></i></div>
                <div class="rb-info">
                    <span class="rb-label">Nhân viên</span>
                    <span class="rb-desc">Quản lý nghiệp vụ</span>
                </div>
                <span class="rb-count" id="rbNV">—</span>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-id-badge" style="color:var(--gold);margin-right:8px"></i>Danh sách tài khoản nhân viên</h3>
                <span class="table-meta" id="nvMeta">Đang tải...</span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead><tr>
                        <th>Tài khoản</th>
                        <th>Họ tên</th>
                        <th>Liên hệ</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Đăng nhập</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr></thead>
                    <tbody id="nvTbody">
                        <tr><td colspan="8" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <div class="pagination-info" id="nvPagInfo">—</div>
                <div class="pagination-controls" id="nvPagCtrl"></div>
            </div>
        </div>
    </div>

    <!-- ═══ TAB KHÁCH HÀNG ═══ -->
    <div class="tab-content" id="tab-customer">
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="khSearch" placeholder="Tìm tên đăng nhập, họ tên...">
            </div>
            <select class="filter-select" id="khStatus">
                <option value="">Tất cả trạng thái</option>
                <option value="1">✅ Hoạt động</option>
                <option value="0">🔒 Đã khóa</option>
            </select>
            <button class="btn-primary" onclick="openAddModal('customer')">
                <i class="fas fa-plus"></i> Thêm tài khoản KH
            </button>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-person" style="color:var(--gold);margin-right:8px"></i>Danh sách tài khoản khách hàng</h3>
                <span class="table-meta" id="khMeta">Đang tải...</span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead><tr>
                        <th>Tài khoản</th>
                        <th>Họ tên</th>
                        <th>Liên hệ</th>
                        <th>Trạng thái</th>
                        <th>Số lần đăng nhập</th>
                        <th>Đăng nhập gần nhất</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr></thead>
                    <tbody id="khTbody">
                        <tr><td colspan="8" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <div class="pagination-info" id="khPagInfo">—</div>
                <div class="pagination-controls" id="khPagCtrl"></div>
            </div>
        </div>
    </div>

    <!-- ═══ TAB LỊCH SỬ ĐĂNG NHẬP ═══ -->
    <div class="tab-content" id="tab-loginlog">
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="logSearch" placeholder="Tìm theo tên đăng nhập...">
            </div>
            <select class="filter-select" id="logResult">
                <option value="">Tất cả kết quả</option>
                <option value="Success">✅ Thành công</option>
                <option value="Failed">❌ Thất bại</option>
            </select>
            <input type="date" class="filter-select" id="logDate" style="max-width:160px;color:var(--ts)">
            <button class="btn-danger-outline" onclick="confirmDel('Xóa toàn bộ lịch sử đăng nhập quá 30 ngày?',clearOldHistory)">
                <i class="fas fa-trash-can"></i> Xóa cũ (>30 ngày)
            </button>
        </div>

        <!-- MINI STATS ROW -->
        <div class="log-stats-row">
            <div class="log-stat success-stat">
                <i class="fas fa-circle-check"></i>
                <span>Thành công hôm nay: <strong id="logSuccessToday">—</strong></span>
            </div>
            <div class="log-stat fail-stat">
                <i class="fas fa-circle-xmark"></i>
                <span>Thất bại hôm nay: <strong id="logFailToday">—</strong></span>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-clock-rotate-left" style="color:var(--gold);margin-right:8px"></i>Lịch sử đăng nhập</h3>
                <span class="table-meta" id="logMeta">Đang tải...</span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead><tr>
                        <th>Thời gian</th>
                        <th>Tài khoản</th>
                        <th>Họ tên</th>
                        <th>Vai trò</th>
                        <th>IP Address</th>
                        <th>Kết quả</th>
                        <th>Ghi chú</th>
                    </tr></thead>
                    <tbody id="logTbody">
                        <tr><td colspan="7" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <div class="pagination-info" id="logPagInfo">—</div>
                <div class="pagination-controls" id="logPagCtrl"></div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL THÊM / SỬA TÀI KHOẢN ═══ -->
<div class="modal-overlay" id="accountModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3 id="accModalTitle">
                <i class="fas fa-user-plus" style="color:var(--gold);margin-right:8px"></i>Thêm tài khoản
            </h3>
            <button class="btn-close" onclick="closeModal('accountModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="fAccId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Tên đăng nhập <span class="req">*</span></label>
                    <input type="text" id="fAccUser" class="form-control" placeholder="VD: nguyen.van.a">
                </div>
                <div class="form-group full">
                    <label id="fPassLabel">Mật khẩu <span class="req">*</span></label>
                    <div class="pass-wrap">
                        <input type="password" id="fAccPass" class="form-control" placeholder="Tối thiểu 6 ký tự">
                        <button type="button" class="pass-eye" onclick="togglePass('fAccPass',this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="form-hint" id="passHint" style="display:none">Để trống nếu không đổi mật khẩu</small>
                </div>
                <div class="form-group">
                    <label>Vai trò</label>
                    <select id="fAccRole" class="form-control">
                        <option value="Employee">👤 Nhân viên</option>
                        <option value="Admin">🛡️ Admin</option>
                        <option value="Customer">🙋 Khách hàng</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trạng thái</label>
                    <div class="toggle-row">
                        <label class="toggle-switch">
                            <input type="checkbox" id="fAccStatus" checked>
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label" id="toggleLabel">Hoạt động</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('accountModal')">Hủy</button>
            <button class="btn-primary" onclick="saveAccount()"><i class="fas fa-save"></i> Lưu</button>
        </div>
    </div>
</div>

<!-- ═══ MODAL CHI TIẾT TÀI KHOẢN ═══ -->
<div class="modal-overlay" id="detailModal">
    <div class="modal" style="max-width:640px">
        <div class="modal-header">
            <h3><i class="fas fa-circle-info" style="color:var(--gold);margin-right:8px"></i>Chi tiết tài khoản</h3>
            <button class="btn-close" onclick="closeModal('detailModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="detailContent">
            <div class="loading-cell"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </div>
</div>

<!-- ═══ MODAL ĐẶT LẠI MẬT KHẨU ═══ -->
<div class="modal-overlay" id="resetPassModal">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <h3><i class="fas fa-key" style="color:var(--gold);margin-right:8px"></i>Đặt lại mật khẩu</h3>
            <button class="btn-close" onclick="closeModal('resetPassModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="fRpId">
            <p class="rp-info">Đặt mật khẩu mới cho: <strong id="fRpUser" style="color:var(--gold)"></strong></p>
            <div class="form-group full" style="margin-top:14px">
                <label>Mật khẩu mới <span class="req">*</span></label>
                <div class="pass-wrap">
                    <input type="password" id="fRpPass" class="form-control" placeholder="Tối thiểu 6 ký tự">
                    <button type="button" class="pass-eye" onclick="togglePass('fRpPass',this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('resetPassModal')">Hủy</button>
            <button class="btn-primary" onclick="doResetPass()"><i class="fas fa-key"></i> Đặt lại</button>
        </div>
    </div>
</div>

<!-- ═══ MODAL XÁC NHẬN ═══ -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal confirm-modal">
        <div class="modal-body">
            <div class="confirm-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <h4 id="confirmTitle">Xác nhận?</h4>
            <p id="confirmMsg">Hành động này không thể hoàn tác.</p>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <button class="btn-secondary" onclick="closeModal('confirmModal')">Hủy</button>
            <button class="btn-danger" id="confirmOkBtn"><i class="fas fa-check"></i> Xác nhận</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="Account_Management.js"></script>
</body>
</html>
