<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý nhân viên - Elite Gym</title>
    <link rel="stylesheet" href="Employee_Management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="employee-container">

    <!-- STATS STRIP -->
    <div class="stats-strip">
        <div class="strip-card blue">
            <div class="strip-icon"><i class="fas fa-users"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statTotal">—</div>
                <div class="strip-label">Tổng nhân viên</div>
            </div>
        </div>
        <div class="strip-card green">
            <div class="strip-icon"><i class="fas fa-user-check"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statActive">—</div>
                <div class="strip-label">Đang làm việc</div>
            </div>
        </div>
        <div class="strip-card gold">
            <div class="strip-icon"><i class="fas fa-dumbbell"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statNewMonth">—</div>
                <div class="strip-label">Mới tháng này</div>
            </div>
        </div>
        <div class="strip-card purple">
            <div class="strip-icon"><i class="fas fa-coins"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statAvgSalary">—</div>
                <div class="strip-label">Lương TB tháng này</div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Tìm theo tên, email, số điện thoại..." autocomplete="off">
        </div>
        <select class="filter-select" id="genderFilter">
            <option value="">Tất cả giới tính</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
        </select>
        <select class="filter-select" id="sortFilter">
            <option value="id_desc">Mới nhất</option>
            <option value="id_asc">Cũ nhất</option>
            <option value="name_asc">Tên A→Z</option>
            <option value="name_desc">Tên Z→A</option>
            <option value="join_desc">Vào làm gần nhất</option>
        </select>
        <a class="btn-attendance-link" href="Employee_attendance_tracking/Employee_attendance_tracking.php">
            <i class="fas fa-clipboard-check"></i> Trang chấm công
        </a>
        <button class="btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Thêm nhân viên
        </button>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list" style="color:#d4a017;margin-right:8px;font-size:15px"></i>Danh sách nhân viên</h3>
            <div class="table-header-right">
                <span class="table-meta" id="tableTotal">Đang tải...</span>
            </div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nhân viên</th>
                        <th>Chức vụ</th>
                        <th>Giới tính</th>
                        <th>Ngày sinh</th>
                        <th>Số điện thoại</th>
                        <th>Email</th>
                        <th>Ngày vào làm</th>
                        <th>Lương/giờ</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="employeeTbody">
                    <tr>
                        <td colspan="8" style="text-align:center;padding:40px;color:rgba(255,255,255,0.3)">
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

<!-- ===== MODAL THÊM / SỬA NHÂN VIÊN ===== -->
<div class="modal-overlay" id="employeeModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Thêm nhân viên mới</h3>
            <button class="btn-close" onclick="closeEmployeeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="employeeId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Họ và tên <span class="required">*</span></label>
                    <input type="text" id="fHoTen" class="form-control" placeholder="VD: Nguyễn Văn An">
                </div>
                <div class="form-group">
                    <label>Ngày sinh</label>
                    <input type="date" id="fNgaySinh" class="form-control">
                </div>
                <div class="form-group">
                    <label>Giới tính</label>
                    <select id="fGioiTinh" class="form-control">
                        <option value="">-- Chọn --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Chức vụ</label>
                    <select id="fChucVu" class="form-control">
                        <option value="">-- Chọn --</option>
                        <option value="Receptionist">Receptionist</option>
                        <option value="Personal Trainer">Personal Trainer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Số điện thoại</label>
                    <input type="text" id="fSoDienThoai" class="form-control" placeholder="VD: 0901234567">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="fEmail" class="form-control" placeholder="VD: nhanvien@elitegym.vn">
                </div>
                <div class="form-group">
                    <label>Ngày vào làm</label>
                    <input type="date" id="fNgayVaoLam" class="form-control">
                </div>
                <div class="form-group">
                    <label>Lương cơ bản (₫/giờ)</label>
                    <input type="number" id="fLuongCoBanEmp" class="form-control" placeholder="VD: 50000" min="0">
                </div>
                <div class="form-group full">
                    <label>Địa chỉ</label>
                    <input type="text" id="fDiaChi" class="form-control" placeholder="Địa chỉ thường trú">
                </div>
            </div>

            <!-- PHẦN TÀI KHOẢN -->
            <div class="account-section" id="accountSection">
                <div class="account-section-header">
                    <i class="fas fa-user-lock"></i>
                    <span id="accountSectionTitle">Thông tin tài khoản đăng nhập</span>
                    <span class="account-required-badge" id="accountRequiredBadge">Bắt buộc</span>
                </div>
                <div class="form-grid" style="margin-top:12px">
                    <div class="form-group">
                        <label>Tên đăng nhập <span class="required" id="usernameRequired">*</span></label>
                        <input type="text" id="fTenDangNhap" class="form-control" placeholder="VD: nguyenvanan">
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
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeEmployeeModal()">Hủy</button>
            <button class="btn-primary" onclick="saveEmployee()">
                <i class="fas fa-save"></i> Lưu
            </button>
        </div>
    </div>
</div>

<!-- ===== MODAL XEM CHI TIẾT ===== -->
<div class="modal-overlay" id="detailModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <h3><i class="fas fa-id-card" style="color:#d4a017;margin-right:8px"></i>Thông tin nhân viên</h3>
            <button class="btn-close" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="detailContent">
            <!-- Rendered by JS -->
        </div>
    </div>
</div>

<!-- ===== MODAL KẾT TOÁN LƯƠNG ===== -->
<div class="modal-overlay" id="salaryModal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <h3 id="salaryModalTitle">
                <i class="fas fa-calculator" style="color:#d4a017;margin-right:8px"></i>Kết toán lương
            </h3>
            <button class="btn-close" onclick="closeSalaryModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="salaryEmpId">

            <!-- Employee info banner -->
            <div class="modal-emp-info" id="salaryEmpInfo"></div>

            <!-- BƯỚC 1: Chọn tháng/năm + nút tính -->
            <div class="salary-step">
                <div class="salary-step-label">
                    <span class="step-badge">1</span> Chọn tháng kết toán
                </div>
                <div class="form-grid" style="margin-top:10px">
                    <div class="form-group">
                        <label>Tháng <span class="required">*</span></label>
                        <select id="fSalaryMonth" class="form-control">
                            <option value="1">Tháng 1</option><option value="2">Tháng 2</option>
                            <option value="3">Tháng 3</option><option value="4">Tháng 4</option>
                            <option value="5">Tháng 5</option><option value="6">Tháng 6</option>
                            <option value="7">Tháng 7</option><option value="8">Tháng 8</option>
                            <option value="9">Tháng 9</option><option value="10">Tháng 10</option>
                            <option value="11">Tháng 11</option><option value="12">Tháng 12</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Năm <span class="required">*</span></label>
                        <input type="number" id="fSalaryYear" class="form-control" min="2000" max="2100">
                    </div>
                </div>
                <button class="btn-calc" onclick="fetchHoursWorked()">
                    <i class="fas fa-calculator"></i> Tính giờ làm từ chấm công
                </button>
            </div>

            <!-- BƯỚC 2: Kết quả tính công (hiện sau khi fetch) -->
            <div class="salary-step" id="salaryStepResult" style="display:none">
                <div class="salary-step-label">
                    <span class="step-badge">2</span> Kết quả chấm công tháng này
                </div>
                <div class="hours-summary">
                    <div class="hours-card">
                        <div class="hours-value" id="rsNgayCham">—</div>
                        <div class="hours-label">Ngày có mặt</div>
                    </div>
                    <div class="hours-card">
                        <div class="hours-value" id="rsTongGio">—</div>
                        <div class="hours-label">Tổng giờ làm</div>
                    </div>
                    <div class="hours-card gold">
                        <div class="hours-value" id="rsLuongGio">—</div>
                        <div class="hours-label">Lương/giờ</div>
                    </div>
                    <div class="hours-card green">
                        <div class="hours-value" id="rsLuongTinh">—</div>
                        <div class="hours-label">Lương tính được</div>
                    </div>
                </div>
                <div class="hours-note" id="hoursNote"></div>
            </div>

            <!-- BƯỚC 3: Điều chỉnh + thực lĩnh -->
            <div class="salary-step" id="salaryStepAdjust" style="display:none">
                <div class="salary-step-label">
                    <span class="step-badge">3</span> Điều chỉnh và kết toán
                </div>
                <div class="form-grid" style="margin-top:10px">
                    <div class="form-group">
                        <label>Phụ cấp (₫)</label>
                        <input type="number" id="fPhuCap" class="form-control" placeholder="0" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Thưởng (₫)</label>
                        <input type="number" id="fThuong" class="form-control" placeholder="0" min="0" value="0">
                    </div>
                    <div class="form-group full">
                        <label>Khấu trừ (₫)</label>
                        <input type="number" id="fKhauTru" class="form-control" placeholder="0" min="0" value="0">
                    </div>
                </div>

                <!-- Công thức hiển thị -->
                <div class="salary-formula">
                    <div class="formula-row">
                        <span class="formula-label">Lương tính được</span>
                        <span class="formula-value" id="fmLuongTinh">0 ₫</span>
                    </div>
                    <div class="formula-row plus">
                        <span class="formula-label"><i class="fas fa-plus-circle"></i> Phụ cấp</span>
                        <span class="formula-value" id="fmPhuCap">0 ₫</span>
                    </div>
                    <div class="formula-row plus">
                        <span class="formula-label"><i class="fas fa-plus-circle"></i> Thưởng</span>
                        <span class="formula-value" id="fmThuong">0 ₫</span>
                    </div>
                    <div class="formula-row minus">
                        <span class="formula-label"><i class="fas fa-minus-circle"></i> Khấu trừ</span>
                        <span class="formula-value" id="fmKhauTru">0 ₫</span>
                    </div>
                    <div class="formula-divider"></div>
                    <div class="formula-row total">
                        <span class="formula-label"><i class="fas fa-wallet"></i> Thực lĩnh</span>
                        <span class="formula-value" id="salaryPreview">0 ₫</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeSalaryModal()">Hủy</button>
            <button class="btn-primary" id="btnKetToan" onclick="saveSalary()" style="display:none">
                <i class="fas fa-check-circle"></i> Kết toán lương
            </button>
        </div>
    </div>
</div>

<!-- ===== MODAL XÁC NHẬN XÓA ===== -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal confirm-modal">
        <div class="modal-body">
            <div class="confirm-icon"><i class="fas fa-trash-alt"></i></div>
            <h4>Xóa nhân viên?</h4>
            <p>Bạn có chắc muốn xóa nhân viên <strong id="deleteEmployeeName"></strong>?<br>Hành động này không thể hoàn tác.</p>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <button class="btn-secondary" onclick="closeConfirmModal()">Hủy</button>
            <button class="btn-danger" onclick="doDelete()"><i class="fas fa-trash"></i> Xóa</button>
        </div>
    </div>
</div>

<!-- ===== MODAL CHẤM CÔNG NHANH ===== -->
<div class="modal-overlay" id="quickAttModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3 id="quickAttTitle">
                <i class="fas fa-fingerprint" style="color:#d4a017;margin-right:8px"></i>Chấm công nhanh
            </h3>
            <button class="btn-close" onclick="closeQuickAttModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="qAttEmpId">
            <div class="modal-emp-info" id="qAttEmpInfo"></div>
            <div class="form-grid">
                <div class="form-group full">
                    <label>Ngày chấm công <span class="required">*</span></label>
                    <input type="date" id="qAttDate" class="form-control">
                </div>
                <div class="form-group full">
                    <label>Trạng thái <span class="required">*</span></label>
                    <select id="qAttStatus" class="form-control" onchange="qOnStatusChange()">
                        <option value="Present">✅ Present</option>
                        <option value="Late">🕐 Late</option>
                        <option value="Day Off">🏖️ Day Off</option>
                        <option value="Absent">❌ Absent</option>
                    </select>
                </div>
                <div class="form-group" id="qGioVaoGroup">
                    <label>Giờ vào</label>
                    <input type="time" id="qAttGioVao" class="form-control" value="08:00">
                </div>
                <div class="form-group" id="qGioRaGroup">
                    <label>Giờ ra</label>
                    <input type="time" id="qAttGioRa" class="form-control">
                </div>
            </div>
            <div class="att-info-note">
                <i class="fas fa-info-circle" style="color:#d4a017"></i>
                Để chấm công hàng loạt hoặc xem lịch sử, vào
                <a href="Employee_attendance_tracking/Employee_attendance_tracking.php" style="color:#d4a017;font-weight:600;text-decoration:none">
                    trang Chấm công <i class="fas fa-arrow-right" style="font-size:11px"></i>
                </a>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeQuickAttModal()">Hủy</button>
            <button class="btn-primary" onclick="saveQuickAtt()">
                <i class="fas fa-save"></i> Lưu chấm công
            </button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast-container" id="toastContainer"></div>

<script src="Employee_Management.js"></script>
</body>
</html>
