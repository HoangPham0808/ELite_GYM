<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chấm công nhân viên - Elite Gym</title>
    <link rel="stylesheet" href="Employee_attendance_tracking.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="attendance-container">

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
                <div class="strip-value" id="statPresent">—</div>
                <div class="strip-label">Có mặt hôm nay</div>
            </div>
        </div>
        <div class="strip-card red">
            <div class="strip-icon"><i class="fas fa-user-times"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statAbsent">—</div>
                <div class="strip-label">Vắng mặt</div>
            </div>
        </div>
        <div class="strip-card orange">
            <div class="strip-icon"><i class="fas fa-clock"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statLate">—</div>
                <div class="strip-label">Đi muộn</div>
            </div>
        </div>
        <div class="strip-card purple">
            <div class="strip-icon"><i class="fas fa-umbrella-beach"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statLeave">—</div>
                <div class="strip-label">Nghỉ phép</div>
            </div>
        </div>
    </div>

    <!-- DATE BAR -->
    <div class="date-bar">
        <span class="date-bar-label"><i class="fas fa-calendar-day" style="margin-right:6px;color:#d4a017"></i>Ngày chấm công:</span>
        <div class="date-input-wrap">
            <button class="date-nav-btn" onclick="shiftDate(-1)" title="Ngày trước"><i class="fas fa-chevron-left"></i></button>
            <input type="date" id="dateInput">
            <button class="date-nav-btn" onclick="shiftDate(1)" title="Ngày tiếp"><i class="fas fa-chevron-right"></i></button>
        </div>
        <button class="btn-today" onclick="goToday()"><i class="fas fa-crosshairs" style="margin-right:5px"></i>Hôm nay</button>
        <div class="date-bar-right">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Tìm nhân viên...">
            </div>
            <select class="filter-select" id="statusFilter">
                <option value="">Tất cả trạng thái</option>
                <option value="Present">Present</option>
                <option value="Absent">Absent</option>
                <option value="Late">Late</option>
                <option value="Day Off">Day Off</option>
                <option value="not_recorded">Not recorded</option>
            </select>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-clipboard-list" style="color:#d4a017;margin-right:8px;font-size:15px"></i>Bảng chấm công</h3>
            <div class="table-header-right">
                <span class="table-meta" id="tableTotal">Đang tải...</span>
                <button class="btn-primary" onclick="openBulkModal()">
                    <i class="fas fa-layer-group"></i> Chấm công hàng loạt
                </button>
            </div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nhân viên</th>
                        <th>Trạng thái</th>
                        <th>Giờ vào</th>
                        <th>Giờ ra</th>
                        <th>Ghi chú</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="attendanceTbody">
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

<!-- ===== MODAL CHẤM CÔNG ===== -->
<div class="modal-overlay" id="attendanceModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="attModalTitle"><i class="fas fa-fingerprint" style="color:#d4a017;margin-right:8px"></i>Chấm công</h3>
            <button class="btn-close" onclick="closeAttModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="attEmpId">
            <input type="hidden" id="attChamCongId">

            <div class="modal-emp-info" id="attEmpInfo">
                <!-- Rendered by JS -->
            </div>

            <div class="form-grid">
                <div class="form-group full">
                    <label>Trạng thái <span class="required">*</span></label>
                    <select id="fTrangThai" class="form-control" onchange="onStatusChange()">
                        <option value="Present">✅ Present</option>
                        <option value="Late">🕐 Late</option>
                        <option value="Day Off">🏖️ Day Off</option>
                        <option value="Absent">❌ Absent</option>
                    </select>
                </div>
                <div class="form-group" id="gioVaoGroup">
                    <label>Giờ vào</label>
                    <input type="time" id="fGioVao" class="form-control">
                    <div class="time-quick-btns">
                        <button class="btn-time-quick" onclick="setTime('fGioVao','07:00')">07:00</button>
                        <button class="btn-time-quick" onclick="setTime('fGioVao','08:00')">08:00</button>
                        <button class="btn-time-quick" onclick="setTime('fGioVao','09:00')">09:00</button>
                        <button class="btn-time-quick" onclick="setNow('fGioVao')">Bây giờ</button>
                    </div>
                </div>
                <div class="form-group" id="gioRaGroup">
                    <label>Giờ ra</label>
                    <input type="time" id="fGioRa" class="form-control">
                    <div class="time-quick-btns">
                        <button class="btn-time-quick" onclick="setTime('fGioRa','17:00')">17:00</button>
                        <button class="btn-time-quick" onclick="setTime('fGioRa','18:00')">18:00</button>
                        <button class="btn-time-quick" onclick="setTime('fGioRa','19:00')">19:00</button>
                        <button class="btn-time-quick" onclick="setNow('fGioRa')">Bây giờ</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeAttModal()">Hủy</button>
            <button class="btn-primary" onclick="saveAttendance()">
                <i class="fas fa-save"></i> Lưu chấm công
            </button>
        </div>
    </div>
</div>

<!-- ===== MODAL CHẤM CÔNG HÀNG LOẠT ===== -->
<div class="modal-overlay" id="bulkModal">
    <div class="modal" style="max-width:580px">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group" style="color:#d4a017;margin-right:8px"></i>Chấm công hàng loạt</h3>
            <button class="btn-close" onclick="closeBulkModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-grid" style="margin-bottom:16px">
                <div class="form-group">
                    <label>Trạng thái áp dụng <span class="required">*</span></label>
                    <select id="fBulkStatus" class="form-control">
                        <option value="Present">✅ Present</option>
                        <option value="Absent">❌ Absent</option>
                        <option value="Day Off">🏖️ Day Off</option>
                        <option value="Late">🕐 Late</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Giờ vào (tuỳ chọn)</label>
                    <input type="time" id="fBulkGioVao" class="form-control" value="08:00">
                </div>
            </div>

            <div class="bulk-emp-list">
                <div class="bulk-select-all">
                    <input type="checkbox" id="checkAll" onchange="toggleCheckAll(this)">
                    <label for="checkAll" style="cursor:pointer">Chọn tất cả nhân viên chưa chấm công</label>
                </div>
                <div id="bulkEmpItems">
                    <div style="padding:20px;text-align:center;color:rgba(255,255,255,0.3)">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </div>
            </div>
            <div style="font-size:12px;color:rgba(255,255,255,0.35)">
                <i class="fas fa-info-circle" style="margin-right:4px"></i>
                Chỉ hiển thị nhân viên chưa được chấm công ngày đang xem.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeBulkModal()">Hủy</button>
            <button class="btn-primary" onclick="saveBulk()">
                <i class="fas fa-check-double"></i> Chấm công hàng loạt
            </button>
        </div>
    </div>
</div>

<!-- ===== MODAL XÁC NHẬN XÓA ===== -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal confirm-modal">
        <div class="modal-body">
            <div class="confirm-icon"><i class="fas fa-trash-alt"></i></div>
            <h4>Xóa chấm công?</h4>
            <p>Bạn có chắc muốn xóa dữ liệu chấm công của <strong id="deleteAttName"></strong>?<br>Hành động này không thể hoàn tác.</p>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <button class="btn-secondary" onclick="closeConfirmModal()">Hủy</button>
            <button class="btn-danger" onclick="doDelete()"><i class="fas fa-trash"></i> Xóa</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast-container" id="toastContainer"></div>

<script src="Employee_attendance_tracking.js"></script>
</body>
</html>
