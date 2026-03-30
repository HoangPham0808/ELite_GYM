<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch tập - Elite Gym</title>
    <link rel="stylesheet" href="Schedule_Management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="sch-container">

    <?php
    /* ── ROLE DETECTION ──────────────────────────────────────────
       auth_check.php stores in session:
         role        = 'Admin' | 'Employee' | 'Customer'
         position    = 'Personal Trainer' | 'Receptionist'  (Employee)
         employee_id = int
         account_id  = int

       Strategy — 3 layers:
       1. Check $_SESSION['position'] directly
       2. If empty, query Employee table by employee_id / account_id
       3. Fallback: if no session at all → isAdmin = true (dev mode)
    ─────────────────────────────────────────────────────────── */
    if (session_status() === PHP_SESSION_NONE) session_start();

    $sessionRole     = $_SESSION['role']        ?? '';
    $sessionPosition = $_SESSION['position']    ?? '';
    $sessionEmpId    = (int)($_SESSION['employee_id'] ?? 0);
    $sessionAccId    = (int)($_SESSION['account_id']  ?? 0);

    /* Layer 2: position (and employee_id) not cached in session → query DB */
    if ($sessionRole === 'Employee' && (trim($sessionPosition) === '' || $sessionEmpId === 0)) {
        $dbPath = __DIR__ . '/../../../Database/db.php';
        if (file_exists($dbPath)) {
            require_once $dbPath;
            if (isset($conn) && !$conn->connect_error) {
                $empRow = null;
                if ($sessionEmpId > 0) {
                    $rr = $conn->query("SELECT employee_id, position FROM Employee WHERE employee_id=$sessionEmpId LIMIT 1");
                    if ($rr && $rr->num_rows > 0) $empRow = $rr->fetch_assoc();
                }
                if (!$empRow && $sessionAccId > 0) {
                    $rr = $conn->query("SELECT employee_id, position FROM Employee WHERE account_id=$sessionAccId LIMIT 1");
                    if ($rr && $rr->num_rows > 0) $empRow = $rr->fetch_assoc();
                }
                if ($empRow) {
                    $sessionPosition = $empRow['position'] ?? '';
                    $_SESSION['position']    = $sessionPosition;           // cache position
                    if ($sessionEmpId === 0 && !empty($empRow['employee_id'])) {
                        $sessionEmpId            = (int)$empRow['employee_id'];
                        $_SESSION['employee_id'] = $sessionEmpId;         // cache employee_id
                    }
                }
            }
        }
    }

    $isPersonalTrainer = ($sessionRole === 'Employee'
                          && trim($sessionPosition) === 'Personal Trainer');
    $isAdmin           = !$isPersonalTrainer && ($sessionRole === 'Admin' || $sessionRole === 'Employee');
    if ($sessionRole === '') $isAdmin = true; // dev fallback
    ?>
    <script>
        /* Injected by PHP — USER_ROLE drives all client-side role checks */
        window.USER_ROLE  = '<?= $isAdmin ? "admin" : "trainer" ?>';
        window.TRAINER_ID = <?= $sessionEmpId ?: 0 ?>;
        window._DBG = {
            role:     '<?= htmlspecialchars($sessionRole,     ENT_QUOTES) ?>',
            position: '<?= htmlspecialchars($sessionPosition, ENT_QUOTES) ?>',
            empId:    <?= $sessionEmpId ?>,
            isAdmin:  <?= $isAdmin ? 'true' : 'false' ?>
        };
        console.info('[Schedule] USER_ROLE='+window.USER_ROLE+' | position='+window._DBG.position+' | empId='+window._DBG.empId);
    </script>

    <!-- ROLE BANNER -->
    <?php if ($isAdmin): ?>
    <?php else: ?>
    <div class="role-banner trainer">
        <i class="fas fa-person-running"></i>
        Huấn luyện viên — xem lịch & đăng ký tham gia
    </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-strip">
        <div class="strip-card c-blue">
            <div class="strip-icon"><i class="fas fa-calendar-days"></i></div>
            <div class="strip-info"><div class="strip-value" id="sTotal">—</div><div class="strip-label">Tổng lịch tập</div></div>
        </div>
        <div class="strip-card c-green">
            <div class="strip-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="strip-info"><div class="strip-value" id="sToday">—</div><div class="strip-label">Hôm nay</div></div>
        </div>
        <div class="strip-card c-gold">
            <div class="strip-icon"><i class="fas fa-calendar-week"></i></div>
            <div class="strip-info"><div class="strip-value" id="sWeek">—</div><div class="strip-label">Tuần này</div></div>
        </div>
        <div class="strip-card c-purple">
            <div class="strip-icon"><i class="fas fa-users"></i></div>
            <div class="strip-info"><div class="strip-value" id="sDangKy">—</div><div class="strip-label">Lượt đăng ký</div></div>
        </div>
        <div class="strip-card c-orange">
            <div class="strip-icon"><i class="fas fa-person-running"></i></div>
            <div class="strip-info"><div class="strip-value" id="sHlv">—</div><div class="strip-label">HLV đang dạy</div></div>
        </div>
        <div class="strip-card c-red">
            <div class="strip-icon"><i class="fas fa-clock"></i></div>
            <div class="strip-info"><div class="strip-value" id="sSapDien">—</div><div class="strip-label">Sắp diễn ra (24h)</div></div>
        </div>
    </div>

    <!-- VIEW TOGGLE + CONTROLS -->
    <div class="view-controls">
        <div class="view-toggle">
            <button class="vt-btn active" id="btnCalView" onclick="switchView('calendar')">
                <i class="fas fa-calendar-days"></i> Lịch tuần
            </button>
            <button class="vt-btn" id="btnListView" onclick="switchView('list')">
                <i class="fas fa-list"></i> Danh sách
            </button>
        </div>
        <?php if ($isAdmin): ?>
        <button class="btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Thêm buổi tập
        </button>
        <?php endif; ?>
    </div>

    <!-- ═══ CALENDAR VIEW ═══ -->
    <div id="view-calendar" class="view-section">
        <div class="calendar-nav">
            <button class="cal-nav-btn" onclick="navWeek(-1)"><i class="fas fa-chevron-left"></i></button>
            <div class="cal-week-title" id="calWeekTitle">—</div>
            <button class="cal-nav-btn" onclick="navWeek(1)"><i class="fas fa-chevron-right"></i></button>
            <button class="cal-today-btn" onclick="goToday()">Hôm nay</button>
            <!-- Package type legend -->
            <div class="pkg-legend">
                <span class="pkg-legend-item"><span class="pkg-dot" style="background:var(--pkg-basic)"></span>Basic</span>
                <span class="pkg-legend-item"><span class="pkg-dot" style="background:var(--pkg-standard)"></span>Standard</span>
                <span class="pkg-legend-item"><span class="pkg-dot" style="background:var(--pkg-premium)"></span>Premium</span>
                <span class="pkg-legend-item"><span class="pkg-dot" style="background:var(--pkg-vip)"></span>VIP</span>
                <span class="pkg-legend-item"><span class="pkg-dot" style="background:var(--pkg-student)"></span>Student</span>
            </div>
        </div>
        <div class="calendar-grid-wrap">
            <div class="calendar-grid" id="calendarGrid"></div>
        </div>
    </div>

    <!-- ═══ LIST VIEW ═══ -->
    <div id="view-list" class="view-section" style="display:none">
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="listSearch" placeholder="Tìm tên buổi tập...">
            </div>
            <select class="filter-select" id="listHlv">
                <option value="">Tất cả HLV</option>
            </select>
            <select class="filter-select" id="listRoom">
                <option value="">Tất cả phòng</option>
            </select>
            <div class="date-range">
                <input type="date" class="filter-select" id="listFrom" style="max-width:150px">
                <span style="color:var(--tm);font-size:13px">—</span>
                <input type="date" class="filter-select" id="listTo" style="max-width:150px">
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-list" style="color:var(--gold);margin-right:8px"></i>Danh sách buổi tập</h3>
                <span class="table-meta" id="listMeta">Đang tải...</span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead><tr>
                        <th>#</th>
                        <th>Tên lớp / Buổi tập</th>
                        <th>Thời gian</th>
                        <th>Thứ</th>
                        <th>HLV phụ trách</th>
                        <th>Phòng tập</th>
                        <th>Đăng ký</th>
                        <th>Thao tác</th>
                    </tr></thead>
                    <tbody id="listTbody">
                        <tr><td colspan="8" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <div class="pagination-info" id="listPagInfo">—</div>
                <div class="pagination-controls" id="listPagCtrl"></div>
            </div>
        </div>
    </div>

</div><!-- end container -->

<!-- ═══ MODAL THÊM / SỬA BUỔI TẬP (Admin only) ═══ -->
<div class="modal-overlay" id="scheduleModal">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <h3 id="schModalTitle">
                <i class="fas fa-calendar-plus" style="color:var(--gold);margin-right:8px"></i>Thêm buổi tập
            </h3>
            <button class="btn-close" onclick="closeModal('scheduleModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="fSchId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Tên lớp / Buổi tập <span class="req">*</span></label>
                    <input type="text" id="fSchTen" class="form-control" placeholder="VD: Yoga buổi sáng, Kickboxing...">
                </div>
                <div class="form-group">
                    <label>Giờ bắt đầu <span class="req">*</span></label>
                    <input type="datetime-local" id="fSchTime" class="form-control">
                </div>
                <div class="form-group">
                    <label>Giờ kết thúc</label>
                    <input type="datetime-local" id="fSchEndTime" class="form-control">
                </div>
                <div class="form-group full">
                    <label>Lặp lại</label>
                    <div class="repeat-options" id="repeatOptions">
                        <div class="repeat-opt active" data-val="none">
                            <input type="radio" name="repeatType" value="none" checked>
                            <span class="repeat-opt-icon"><i class="fas fa-minus-circle"></i></span>
                            <span class="repeat-opt-label">Không<br>lặp lại</span>
                        </div>
                        <div class="repeat-opt" data-val="daily">
                            <input type="radio" name="repeatType" value="daily">
                            <span class="repeat-opt-icon"><i class="fas fa-rotate-right"></i></span>
                            <span class="repeat-opt-label">Hàng<br>ngày</span>
                        </div>
                        <div class="repeat-opt" data-val="weekly">
                            <input type="radio" name="repeatType" value="weekly">
                            <span class="repeat-opt-icon"><i class="fas fa-calendar-week"></i></span>
                            <span class="repeat-opt-label">Hàng<br>tuần</span>
                        </div>
                    </div>
                    <div id="repeatNote" style="font-size:11px;color:var(--tm);margin-top:6px;display:none"></div>
                </div>
                <div class="form-group full">
                    <label>Huấn luyện viên</label>
                    <select id="fSchHlv" class="form-control">
                        <option value="">— Không chỉ định —</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Phòng tập</label>
                    <select id="fSchRoom" class="form-control">
                        <option value="">— Không chỉ định —</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('scheduleModal')">Hủy</button>
            <button class="btn-primary" onclick="saveSchedule()"><i class="fas fa-save"></i> Lưu</button>
        </div>
    </div>
</div>

<!-- ═══ MODAL CHI TIẾT + ĐĂNG KÝ ═══ -->
<div class="modal-overlay" id="detailModal">
    <div class="modal" style="max-width:640px">
        <div class="modal-header">
            <h3 id="detailTitle">
                <i class="fas fa-circle-info" style="color:var(--gold);margin-right:8px"></i>Chi tiết buổi tập
            </h3>
            <button class="btn-close" onclick="closeModal('detailModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="detailContent">
            <div class="loading-cell"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </div>
</div>

<!-- ═══ MODAL ĐĂNG KÝ THÀNH VIÊN ═══ -->
<div class="modal-overlay" id="regModal">
    <div class="modal" style="max-width:440px">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus" style="color:var(--gold);margin-right:8px"></i>Thêm người tham gia</h3>
            <button class="btn-close" onclick="closeModal('regModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="fRegLichId">
            <div class="form-group full">
                <label>Tìm khách hàng <span class="req">*</span></label>
                <input type="text" id="fRegSearch" class="form-control" placeholder="Nhập tên hoặc SĐT..." autocomplete="off">
                <div class="kh-dropdown" id="khDropdown"></div>
            </div>
            <div id="fRegSelected" class="selected-kh" style="display:none"></div>
            <input type="hidden" id="fRegKhId">
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('regModal')">Hủy</button>
            <button class="btn-primary" onclick="saveRegistration()"><i class="fas fa-check"></i> Đăng ký</button>
        </div>
    </div>
</div>

<!-- ═══ MODAL XÁC NHẬN XÓA ═══ -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal confirm-modal">
        <div class="modal-body">
            <div class="confirm-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <h4>Xác nhận xóa?</h4>
            <p id="confirmMsg">Hành động này không thể hoàn tác.</p>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <button class="btn-secondary" onclick="closeModal('confirmModal')">Hủy</button>
            <button class="btn-danger" id="confirmOkBtn"><i class="fas fa-trash"></i> Xóa</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="Schedule_Management.js"></script>
</body>
</html>
