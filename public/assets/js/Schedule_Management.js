/* ──────────────────────────────────────────────────────────────
   Schedule_Management.js  –  Elite Gym (Bản chuẩn hóa SPA chống trùng)
   ────────────────────────────────────────────────────────────── */

/* ── 1. EARLY ROLE ENFORCEMENT ── */
(function enforceRoleEarly() {
    var role = (typeof window.USER_ROLE !== 'undefined') ? window.USER_ROLE : 'admin';
    if (role === 'trainer') {
        var s = document.createElement('style');
        s.id = 'trainer-role-hide';
        s.textContent = [
            '.view-controls .btn-primary { display:none !important; }',
            '#scheduleModal { display:none !important; }',
            '.cal-add-btn { display:none !important; }'
        ].join('\n');
        (document.head || document.documentElement).appendChild(s);
    }
})();

/* ── 2. BIẾN TOÀN CỤC CẤU HÌNH LỊCH TRÌNH ── */
var _schBase = (window.ELITE_BASE && window.ELITE_BASE !== 'undefined')
    ? window.ELITE_BASE
    : (window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/public').replace('/public/public', '/public');
var API_SCHEDULE = _schBase + '/api/admin/schedule';
var LIMIT = 10;

var currentView = 'calendar'; 
var currentFilterRoom = '';
var currentFilterClass = '';
var currentDate = new Date(); 

var PACKAGE_COLORS = {
    'Basic': { bg: '#e0f2fe', text: '#0369a1', border: '#bae6fd', tag: 'pkg-basic' },
    'VIP': { bg: '#fef3c7', text: '#b45309', border: '#fde68a', tag: 'pkg-vip' },
    'Diamond': { bg: '#f3e8ff', text: '#6b21a8', border: '#e9d5ff', tag: 'pkg-diamond' },
    'Ruby': { bg: '#ffe4e6', text: '#9f1239', border: '#fecdd3', tag: 'pkg-ruby' }
};

/* ── 3. ĐỊNH NGHĨA HÀM PHÂN TRANG ĐỘC QUYỀN (ẨN DANH TUYỆT ĐỐI) ── */
window.renderSchedulePag = function(total, page, totalPages, piId, pcId) {
    var from = total > 0 ? (page - 1) * LIMIT + 1 : 0;
    var to = Math.min(page * LIMIT, total);
    
    var infoEl = document.getElementById(piId);
    if (infoEl) {
        infoEl.textContent = total > 0 ? `Hiển thị ${from}–${to} / ${total}` : 'Không có dữ liệu';
    }
    
    var ctrl = document.getElementById(pcId);
    if (!ctrl) return;
    ctrl.innerHTML = ''; 
    if (totalPages <= 1) return;

    function createPageBtn(content, targetPage, isDisabled, isActive) {
        var btn = document.createElement('button');
        btn.className = 'page-btn' + (isActive ? ' active' : '');
        btn.innerHTML = content;
        if (isDisabled) {
            btn.disabled = true;
        } else {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof window.loadListViewData === 'function') {
                    window.loadListViewData(targetPage);
                }
            });
        }
        return btn;
    }

    ctrl.appendChild(createPageBtn('<i class="fas fa-chevron-left"></i>', page - 1, page === 1, false));

    for (var i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && Math.abs(i - page) > 2 && i !== 1 && i !== totalPages) {
            if (i === 2 || i === totalPages - 1) {
                var dotBtn = document.createElement('button');
                dotBtn.className = 'page-btn';
                dotBtn.disabled = true;
                dotBtn.textContent = '…';
                ctrl.appendChild(dotBtn);
            }
            continue;
        }
        ctrl.appendChild(createPageBtn(i, i, false, i === page));
    }

    ctrl.appendChild(createPageBtn('<i class="fas fa-chevron-right"></i>', page + 1, page === totalPages, false));
};

/* ── 4. THỰC THI LOGIC TẢI DỮ LIỆU DANH SÁCH ── */
window.loadListViewData = async function(page) {
    var searchInput = document.getElementById('listSearch');
    var search = searchInput ? searchInput.value.trim() : '';
    var tbody = document.getElementById('listTbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="8" class="loading-cell"><i class="fas fa-spinner fa-spin"></i> Đang tải danh sách...</td></tr>';

    try {
        var params = new URLSearchParams({
            action: 'get_list_view',
            page: page,
            limit: LIMIT,
            search: search,
            room_id: currentFilterRoom,
            class_type: currentFilterClass
        });

        var res = await fetch(API_SCHEDULE + '?' + params.toString(), { credentials: 'include' });
        var d = await res.json();

        if (d.success && d.data) {
            renderListTable(d.data);
            if (typeof window.renderSchedulePag === 'function') {
                window.renderSchedulePag(d.total, page, d.totalPages, 'listPagInfo', 'listPagCtrl');
            }
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="empty-state text-danger"><i class="fas fa-exclamation-circle"></i> ' + (d.message || 'Lỗi tải danh sách') + '</td></tr>';
        }
    } catch (e) {
        console.error(e);
        tbody.innerHTML = '<tr><td colspan="8" class="empty-state text-danger"><i class="fas fa-exclamation-circle"></i> Lỗi kết nối hệ thống dữ liệu</td></tr>';
    }
};

window.initCalendar = function() {
    renderCalendarSkeleton();
    loadScheduleData();
};

function renderCalendarSkeleton() {
    var calendarView = document.getElementById('calendarView');
    if (!calendarView) return;

    var startOfWeek = getMonday(currentDate);
    var endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6);
    var dateRangeStr = 'Tuần: ' + formatDateDMY(startOfWeek) + ' – ' + formatDateDMY(endOfWeek);
    var viewTitle = document.getElementById('viewTitleText');
    if (viewTitle) viewTitle.textContent = dateRangeStr;

    var html = '<div class="cal-grid">' +
               '<div class="cal-time-col">' +
               '<div class="cal-header-cell empty"></div>' +
               '<div class="cal-time-slots">' +
               '<div class="time-slot-label">Sáng (06:00 - 12:00)</div>' +
               '<div class="time-slot-label">Chiều (12:00 - 18:00)</div>' +
               '<div class="time-slot-label">Tối (18:00 - 22:00)</div>' +
               '</div>' +
               '</div>';

    var daysOfWeek = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ Nhật'];
    
    for (var i = 0; i < 7; i++) {
        var d = new Date(startOfWeek);
        d.setDate(startOfWeek.getDate() + i);
        var isToday = isSameDate(d, new Date()) ? 'today' : '';
        var dateStr = d.toISOString().split('T')[0];

        html += '<div class="cal-day-col ' + isToday + '" data-date="' + dateStr + '">' +
                '<div class="cal-header-cell">' +
                '<span class="day-name">' + daysOfWeek[i] + '</span>' +
                '<span class="day-date">' + d.getDate() + ' thg ' + (d.getMonth() + 1) + '</span>' +
                '</div>' +
                '<div class="cal-slots-container">' +
                '<div class="cal-period-slot morning" data-period="morning" data-date="' + dateStr + '"></div>' +
                '<div class="cal-period-slot afternoon" data-period="afternoon" data-date="' + dateStr + '"></div>' +
                '<div class="cal-period-slot evening" data-period="evening" data-date="' + dateStr + '"></div>' +
                '</div>' +
                '</div>';
    }

    html += '</div>';
    calendarView.innerHTML = html;
}

window.initScheduleManagement = function() {
    window.initCalendar();

    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                window.closeModal(overlay.id);
            }
        });
    });

    var regSearch = document.getElementById('fRegSearch');
    if (regSearch) {
        regSearch.addEventListener('input', debounce(function() {
            searchCustomer(this.value.trim());
        }, 300));
    }
};

async function loadScheduleData() {
    if (currentView === 'list') {
        window.loadListViewData(1);
        return;
    }

    var startOfWeek = getMonday(currentDate);
    var endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6);

    var startStr = startOfWeek.toISOString().split('T')[0] + ' 00:00:00';
    var endStr = endOfWeek.toISOString().split('T')[0] + ' 23:59:59';

    document.querySelectorAll('.cal-period-slot').forEach(function(slot) {
        slot.innerHTML = '<div class="slot-loading"><i class="fas fa-spinner fa-spin"></i></div>';
    });

    try {
        var params = new URLSearchParams({
            action: 'get_schedules',
            start_time: startStr,
            end_time: endStr,
            room_id: currentFilterRoom,
            class_type: currentFilterClass
        });

        var res = await fetch(API_SCHEDULE + '?' + params.toString(), { credentials: 'include' });
        var d = await res.json();
        
        document.querySelectorAll('.cal-period-slot').forEach(function(slot) { slot.innerHTML = ''; });

        if (d.success && d.data) {
            distributeEventsToCalendar(d.data);
        } else {
            window.toast(d.message || 'Không thể tải lịch tập', 'error');
        }
    } catch (e) {
        console.error(e);
        window.toast('Lỗi kết nối API lịch tập', 'error');
    }
}

function distributeEventsToCalendar(events) {
    var role = (typeof window.USER_ROLE !== 'undefined') ? window.USER_ROLE : 'admin';

    events.forEach(function(ev) {
        var start = new Date(ev.start_time);
        var dateStr = start.toISOString().split('T')[0];
        var hour = start.getHours();

        var period = 'morning';
        if (hour >= 12 && hour < 18) period = 'afternoon';
        else if (hour >= 18) period = 'evening';

        var slot = document.querySelector('.cal-period-slot[data-date="' + dateStr + '"][data-period="' + period + '"]');
        if (!slot) return;

        var currentReg = parseInt(ev.current_enrollment || 0);
        var maxReg = parseInt(ev.max_capacity || 0);
        var pkg = ev.package_name || 'Basic';
        var color = PACKAGE_COLORS[pkg] || PACKAGE_COLORS['Basic'];

        var timeStr = formatTimeHHMM(ev.start_time) + ' - ' + formatTimeHHMM(ev.end_time);

        var actionButtons = '';
        if (role === 'admin') {
            actionButtons = '<div class="event-actions">' +
                            '<button onclick="event.stopPropagation(); window.openEditSchedule(' + ev.class_id + ')" title="Sửa lớp"><i class="fas fa-pen"></i></button>' +
                            '<button onclick="event.stopPropagation(); window.deleteScheduleClass(' + ev.class_id + ')" title="Xóa lớp" class="del"><i class="fas fa-trash"></i></button>' +
                            '</div>';
        }

        var cardHtml = '<div class="event-card" style="border-left: 4px solid ' + color.border + ';" onclick="window.openClassDetail(' + ev.class_id + ')">' +
            actionButtons +
            '<div class="event-time"><i class="far fa-clock"></i> ' + timeStr + '</div>' +
            '<div class="event-name">' + escapeHtml(ev.class_name) + '</div>' +
            '<div class="event-meta">' +
            '<span><i class="fas fa-door-open"></i> ' + escapeHtml(ev.room_name) + '</span>' +
            '<span><i class="fas fa-user-tie"></i> ' + escapeHtml(ev.trainer_name || 'Chưa phân công') + '</span>' +
            '</div>' +
            '<div class="event-pkg-row">' +
            '<span class="pkg-tag ' + color.tag + '">' + pkg + '</span>' +
            '<span class="event-cap ' + (currentReg >= maxReg ? 'full' : '') + '">' +
            '<i class="fas fa-users"></i> ' + currentReg + '/' + maxReg +
            '</span>' +
            '</div>' +
            '</div>';
        
        slot.insertAdjacentHTML('beforeend', cardHtml);
    });
}

function renderListTable(rows) {
    var tbody = document.getElementById('listTbody');
    var role = (typeof window.USER_ROLE !== 'undefined') ? window.USER_ROLE : 'admin';
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state"><i class="fas fa-calendar-times"></i> Không có lịch tập nào khớp với bộ lọc</div></td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(function(r) {
        var pkg = r.package_name || 'Basic';
        var color = PACKAGE_COLORS[pkg] || PACKAGE_COLORS['Basic'];
        var currentReg = parseInt(r.current_enrollment || 0);
        var maxReg = parseInt(r.max_capacity || 0);

        var actionButtons = '<button class="btn-table-action view" onclick="window.openClassDetail(' + r.class_id + ')" title="Xem chi tiết"><i class="fas fa-eye"></i> Chi tiết</button>';
        if (role === 'admin') {
            actionButtons += '<button class="btn-table-action edit" onclick="window.openEditSchedule(' + r.class_id + ')" title="Sửa lớp"><i class="fas fa-pen"></i></button>' +
                             '<button class="btn-table-action del" onclick="window.deleteScheduleClass(' + r.class_id + ')" title="Xóa lớp"><i class="fas fa-trash"></i></button>';
        }

        return '<tr>' +
            '<td><strong>#' + r.class_id + '</strong></td>' +
            '<td><div class="list-class-name">' + escapeHtml(r.class_name) + '</div></td>' +
            '<td><span class="pkg-tag ' + color.tag + '">' + pkg + '</span></td>' +
            '<td><div class="list-room"><i class="fas fa-door-open"></i> ' + escapeHtml(r.room_name) + '</div></td>' +
            '<td><div class="list-trainer"><i class="fas fa-user-tie"></i> ' + escapeHtml(r.trainer_name || 'Chưa có') + '</div></td>' +
            '<td style="font-size:13px;">' +
            '<div class="list-time-row"><i class="far fa-calendar"></i> ' + formatDateDMY(new Date(r.start_time)) + '</div>' +
            '<div class="list-time-row" style="color:red;"><i class="far fa-clock"></i> ' + formatTimeHHMM(r.start_time) + ' - ' + formatTimeHHMM(r.end_time) + '</div>' +
            '</td>' +
            '<td>' +
            '<span class="event-cap ' + (currentReg >= maxReg ? 'full' : '') + '" style="display:inline-flex;padding:4px 8px;border-radius:4px;font-weight:600;">' +
            '<i class="fas fa-users" style="margin-right:4px"></i> ' + currentReg + '/' + maxReg +
            '</span>' +
            '</td>' +
            '<td><div class="table-actions-flex">' + actionButtons + '</div></td>' +
            '</tr>';
    }).join('');
}

window.navigateWeek = function(direction) {
    if (direction === -1) currentDate.setDate(currentDate.getDate() - 7);
    else if (direction === 1) currentDate.setDate(currentDate.getDate() + 7);
    else currentDate = new Date(); 
    window.initCalendar();
};

window.switchView = function(viewType) {
    currentView = viewType;
    document.getElementById('btnViewCal').classList.toggle('active', viewType === 'calendar');
    document.getElementById('btnViewList').classList.toggle('active', viewType === 'list');

    if (viewType === 'calendar') {
        document.getElementById('calendarView').style.display = 'block';
        document.getElementById('listView').style.display = 'none';
        document.getElementById('calNavControls').style.display = 'flex';
        window.initCalendar();
    } else {
        document.getElementById('calendarView').style.display = 'none';
        document.getElementById('listView').style.display = 'block';
        document.getElementById('calNavControls').style.display = 'none';
        var viewTitle = document.getElementById('viewTitleText');
        if (viewTitle) viewTitle.textContent = 'Danh sách toàn bộ lớp học tập';
        
        window.loadListViewData(1);
    }
};

window.filterRoomChanged = function(roomId) { currentFilterRoom = roomId; loadScheduleData(); };
window.filterClassChanged = function(classType) { currentFilterClass = classType; loadScheduleData(); };
window.triggerListSearch = function() { if (currentView === 'list') window.loadListViewData(1); };

window.openClassDetail = async function(classId) {
    var detailBox = document.getElementById('classDetailContent');
    if (!detailBox) return;
    detailBox.innerHTML = '<div class="loading-cell"><i class="fas fa-spinner fa-spin"></i> Đang đọc thông tin lớp...</div>';
    window.openModal('detailModal');

    try {
        var res = await fetch(API_SCHEDULE + '?action=get_class_detail&id=' + classId, { credentials: 'include' });
        var d = await res.json();
        if (!d.success) { detailBox.innerHTML = '<div class="error-msg">' + d.message + '</div>'; return; }

        var cls = d.class;
        var members = d.enrolled_customers || [];
        var pkg = cls.package_name || 'Basic';
        var color = PACKAGE_COLORS[pkg] || PACKAGE_COLORS['Basic'];
        var currentReg = members.length;
        var maxReg = parseInt(cls.max_capacity || 0);

        var html = '<div class="detail-header-block" style="border-left:5px solid ' + color.border + '">' +
            '<div class="detail-title-main">' + escapeHtml(cls.class_name) + '</div>' +
            '<div style="margin-top:8px; display:flex; gap:10px; align-items:center;">' +
            '<span class="pkg-tag ' + color.tag + '">' + pkg + '</span>' +
            '<span style="font-size:13px;"><i class="fas fa-door-open"></i> Phòng: <strong>' + escapeHtml(cls.room_name) + '</strong></span>' +
            '</div>' +
            '</div>' +
            '<div class="detail-info-grid">' +
            '<div class="dig-card"><span class="lbl">Huấn luyện viên</span><span class="val"><i class="fas fa-user-tie"></i> ' + escapeHtml(cls.trainer_name || 'Chưa phân công') + '</span></div>' +
            '<div class="dig-card"><span class="lbl">Ngày tập</span><span class="val"><i class="far fa-calendar"></i> ' + formatDateDMY(new Date(cls.start_time)) + '</span></div>' +
            '<div class="dig-card"><span class="lbl">Thời gian lớp</span><span class="val"><i class="far fa-clock"></i> ' + formatTimeHHMM(cls.start_time) + ' - ' + formatTimeHHMM(cls.end_time) + '</span></div>' +
            '<div class="dig-card"><span class="lbl">Sức chứa phòng</span><span class="val"><i class="fas fa-users"></i> Đã đăng ký ' + currentReg + '/' + maxReg + ' chỗ</span></div>' +
            '</div>' +
            '<div class="detail-section-heading">' +
            '<span><i class="fas fa-list-ol"></i> Danh sách hội viên tham gia (' + currentReg + ')</span>' +
            '<button class="btn-primary btn-sm" onclick="window.openRegistrationModal(' + cls.class_id + ', \'' + pkg + '\')"><i class="fas fa-user-plus"></i> Đăng ký hộ</button>' +
            '</div>';

        if (!members.length) {
            html += '<div class="empty-state" style="padding:20px 0;"><i class="fas fa-folder-open"></i> Chưa có hội viên nào đăng ký lớp này</div>';
        } else {
            html += '<table class="detail-member-table">' +
                    '<thead><tr><th>Hội viên</th><th>Số điện thoại</th><th>Gói đăng ký</th><th>Thời gian</th><th>Hành động</th></tr></thead>' +
                    '<tbody>';
            members.forEach(function(m) {
                html += '<tr>' +
                    '<td><strong>' + escapeHtml(m.full_name) + '</strong><br><span style="font-size:11px;">@' + escapeHtml(m.username) + '</span></td>' +
                    '<td>' + escapeHtml(m.phone || '—') + '</td>' +
                    '<td><span class="pkg-tag ' + (PACKAGE_COLORS[m.package_name]?.tag || 'pkg-basic') + '">' + (m.package_name || 'Basic') + '</span></td>' +
                    '<td style="font-size:12px;">' + (m.registration_time ? formatDateDMY(new Date(m.registration_time)) : '—') + '</td>' +
                    '<td><button class="btn-table-action del" onclick="window.cancelRegistration(' + cls.class_id + ', ' + m.customer_id + ', \'' + escapeHtml(m.full_name) + '\')" title="Hủy đăng ký"><i class="fas fa-user-minus"></i></button></td>' +
                    '</tr>';
            });
            html += '</tbody></table>';
        }

        detailBox.innerHTML = html;
    } catch (e) {
        console.error(e);
        detailBox.innerHTML = '<div class="error-msg">Lỗi đồng bộ chi tiết lớp học</div>';
    }
};

window.openRegistrationModal = function(classId, packageName) {
    document.getElementById('fRegClassId').value = classId;
    document.getElementById('fRegPkgRequirement').value = packageName;
    document.getElementById('fRegSearch').value = '';
    document.getElementById('khDropdown').innerHTML = '';
    document.getElementById('fRegSelected').style.display = 'none';
    document.getElementById('fRegKhId').value = '';
    window.openModal('regModal');
};

async function searchCustomer(query) {
    var dropdown = document.getElementById('khDropdown');
    if (query.length < 2) { dropdown.innerHTML = ''; return; }
    dropdown.innerHTML = '<div style="padding:8px;"><i class="fas fa-spinner fa-spin"></i> Đang tìm kiếm...</div>';

    try {
        var pkgReq = document.getElementById('fRegPkgRequirement').value;
        var res = await fetch(API_SCHEDULE + '?action=search_customer&q=' + encodeURIComponent(query) + '&package_requirement=' + encodeURIComponent(pkgReq), { credentials: 'include' });
        var d = await res.json();

        if (d.success && d.data && d.data.length > 0) {
            var html = '';
            d.data.forEach(function(c) {
                html += '<div class="kh-option" onclick="window.selectCustomerForReg(' + c.customer_id + ', \'' + escapeHtml(c.full_name) + '\', \'' + escapeHtml(c.phone || '') + '\', \'' + c.package_name + '\')">' +
                        '<div class="kh-opt-name">' + escapeHtml(c.full_name) + ' (' + escapeHtml(c.username) + ')</div>' +
                        '<div class="kh-opt-meta">SĐT: ' + escapeHtml(c.phone || 'Chưa có') + ' — Gói: <span class="pkg-tag ' + (PACKAGE_COLORS[c.package_name]?.tag || 'pkg-basic') + '">' + c.package_name + '</span></div>' +
                        '</div>';
            });
            dropdown.innerHTML = html;
        } else {
            dropdown.innerHTML = '<div style="padding:8px;color:red;"><i class="fas fa-info-circle"></i> Không tìm thấy khách hàng phù hợp hoặc sai gói tập</div>';
        }
    } catch (e) {
        dropdown.innerHTML = '<div style="padding:8px;color:red;">Lỗi tìm kiếm dữ liệu</div>';
    }
}

window.selectCustomerForReg = function(id, name, phone, pkg) {
    document.getElementById('fRegKhId').value = id;
    var box = document.getElementById('fRegSelected');
    box.style.display = 'block';
    box.innerHTML = '<strong>Đang chọn:</strong> ' + escapeHtml(name) + ' (' + (phone || 'Không SĐT') + ') - Gói: <span class="pkg-tag ' + (PACKAGE_COLORS[pkg]?.tag || 'pkg-basic') + '">' + pkg + '</span>' +
                     '<button type="button" class="btn-table-action del" onclick="window.removeSelectedRegCustomer()" style="margin-left:10px;"><i class="fas fa-times"></i> Xóa</button>';
    document.getElementById('khDropdown').innerHTML = '';
};

window.removeSelectedRegCustomer = function() {
    document.getElementById('fRegKhId').value = '';
    document.getElementById('fRegSelected').style.display = 'none';
};

window.saveRegistration = function() {
    var classId = document.getElementById('fRegClassId').value;
    var customerId = document.getElementById('fRegKhId').value;
    if (!customerId) { window.toast('Vui lòng chọn hội viên muốn đăng ký!', 'warning'); return; }

    (async function() {
        try {
            var fd = new FormData();
            fd.append('action', 'register_customer_proxy');
            fd.append('class_id', classId);
            fd.append('customer_id', customerId);

            var res = await fetch(API_SCHEDULE, { method: 'POST', body: fd, credentials: 'include' });
            var d = await res.json();

            window.toast(d.message, d.success ? 'success' : 'error');
            if (d.success) {
                window.closeModal('regModal');
                window.openClassDetail(classId); 
                loadScheduleData();       
            }
        } catch (e) {
            window.toast('Lỗi xử lý đăng ký hộ', 'error');
        }
    })();
};

window.cancelRegistration = function(classId, customerId, customerName) {
    confirmDel('Hủy quyền tham gia lớp của hội viên <strong>' + customerName + '</strong>?', async function() {
        try {
            var fd = new FormData();
            fd.append('action', 'cancel_customer_proxy');
            fd.append('class_id', classId);
            fd.append('customer_id', customerId);

            var res = await fetch(API_SCHEDULE, { method: 'POST', body: fd, credentials: 'include' });
            var d = await res.json();

            window.toast(d.message, d.success ? 'success' : 'error');
            if (d.success) {
                window.openClassDetail(classId);
                loadScheduleData();
            }
        } catch (e) {
            window.toast('Lỗi xử lý hủy lớp hội viên', 'error');
        }
    });
};

window.openAddScheduleModal = function() {
    document.getElementById('fClassId').value = '';
    document.getElementById('fClassName').value = '';
    document.getElementById('fRoomId').value = '';
    document.getElementById('fTrainerId').value = '';
    document.getElementById('fPackageName').value = 'Basic';
    document.getElementById('fDate').value = '';
    document.getElementById('fStartTime').value = '';
    document.getElementById('fDuration').value = '60';
    document.getElementById('fMaxCapacity').value = '20';
    document.getElementById('fRepeatType').value = 'none';
    document.getElementById('fRepeatWeeksBlock').style.display = 'none';
    document.getElementById('fRepeatWeeks').value = '1';
    document.getElementById('scheduleModalTitle').innerHTML = '<i class="fas fa-calendar-plus"></i> Thêm lớp học tập mới';
    window.openModal('scheduleModal');
};

window.openEditSchedule = async function(classId) {
    window.openModal('scheduleModal');
    document.getElementById('scheduleModalTitle').innerHTML = '<i class="fas fa-calendar-check"></i> Cập nhật thông tin lớp';
    document.getElementById('fRepeatType').value = 'none';
    document.getElementById('fRepeatWeeksBlock').style.display = 'none';

    try {
        var res = await fetch(API_SCHEDULE + '?action=get_class_detail&id=' + classId, { credentials: 'include' });
        var d = await res.json();
        if (!d.success) { window.toast(d.message, 'error'); window.closeModal('scheduleModal'); return; }

        var cls = d.class;
        var start = new Date(cls.start_time);
        var end = new Date(cls.end_time);
        var durationMin = Math.round((end - start) / 60000);

        document.getElementById('fClassId').value = cls.class_id;
        document.getElementById('fClassName').value = cls.class_name;
        document.getElementById('fRoomId').value = cls.room_id;
        document.getElementById('fTrainerId').value = cls.trainer_id || '';
        document.getElementById('fPackageName').value = cls.package_name || 'Basic';
        document.getElementById('fDate').value = start.toISOString().split('T')[0];
        document.getElementById('fStartTime').value = start.toTimeString().slice(0, 5);
        document.getElementById('fDuration').value = durationMin;
        document.getElementById('fMaxCapacity').value = cls.max_capacity;
    } catch (e) {
        window.toast('Lỗi nạp thông tin lớp chỉnh sửa', 'error');
        window.closeModal('scheduleModal');
    }
};

window.handleRepeatTypeChange = function(val) {
    document.getElementById('fRepeatWeeksBlock').style.display = (val !== 'none') ? 'block' : 'none';
};

window.saveScheduleClass = async function() {
    var id = document.getElementById('fClassId').value;
    var name = document.getElementById('fClassName').value.trim();
    var roomId = document.getElementById('fRoomId').value;
    var date = document.getElementById('fDate').value;
    var startTime = document.getElementById('fStartTime').value;

    if (!name || !roomId || !date || !startTime) {
        window.toast('Vui lòng hoàn thiện toàn bộ trường bắt buộc (*)', 'warning');
        return;
    }

    var fd = new FormData();
    fd.append('action', id ? 'update_class' : 'add_class');
    if (id) fd.append('class_id', id);
    fd.append('class_name', name);
    fd.append('room_id', roomId);
    fd.append('trainer_id', document.getElementById('fTrainerId').value);
    fd.append('package_name', document.getElementById('fPackageName').value);
    fd.append('date', date);
    fd.append('start_time_hhmm', startTime);
    fd.append('duration_minutes', document.getElementById('fDuration').value);
    fd.append('max_capacity', document.getElementById('fMaxCapacity').value);
    
    if (!id) {
        fd.append('repeat_type', document.getElementById('fRepeatType').value);
        fd.append('repeat_weeks', document.getElementById('fRepeatWeeks').value);
    }

    try {
        var res = await fetch(API_SCHEDULE, { method: 'POST', body: fd, credentials: 'include' });
        var d = await res.json();
        window.toast(d.message, d.success ? 'success' : 'error');
        if (d.success) { window.closeModal('scheduleModal'); loadScheduleData(); }
    } catch (e) {
        window.toast('Lỗi hệ thống khi lưu lịch tập', 'error');
    }
};

window.deleteScheduleClass = function(classId) {
    confirmDel('Xóa lớp học tập này? Mọi thông tin đăng ký của khách hàng liên quan sẽ bị hủy bỏ.', async function() {
        try {
            var fd = new FormData();
            fd.append('action', 'delete_class');
            fd.append('class_id', classId);
            var res = await fetch(API_SCHEDULE, { method: 'POST', body: fd, credentials: 'include' });
            var d = await res.json();
            window.toast(d.message, d.success ? 'success' : 'error');
            if (d.success) loadScheduleData();
        } catch (e) {
            window.toast('Lỗi kết nối khi xóa lớp', 'error');
        }
    });
};

/* ── HELPERS TIME & FORMAT ── */
function getMonday(d) {
    var date = new Date(d);
    var day = date.getDay();
    var diff = date.getDate() - day + (day === 0 ? -6 : 1); 
    return new Date(date.setDate(diff));
}
var isSameDate = function(d1, d2) { return d1.getFullYear() === d2.getFullYear() && d1.getMonth() === d2.getMonth() && d1.getDate() === d2.getDate(); };
var formatDateDMY = function(date) { return date.getDate().toString().padStart(2, '0') + '/' + (date.getMonth() + 1).toString().padStart(2, '0') + '/' + date.getFullYear(); };
function formatTimeHHMM(timeStr) {
    if (!timeStr) return '--:--';
    var d = new Date(timeStr);
    return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
}
var escapeHtml = function(s) { return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
function debounce(fn, ms) { var t; return function(...a) { clearTimeout(t); t = setTimeout(function() { fn(...a); }, ms); }; }

function confirmDel(msg, cb) {
    var confirmMsg = document.getElementById('confirmMsg');
    if (confirmMsg) confirmMsg.innerHTML = msg;
    window.currentConfirmCallback = cb;
    var okBtn = document.getElementById('confirmOkBtn');
    if (okBtn) {
        okBtn.onclick = function() {
            if (window.currentConfirmCallback) window.currentConfirmCallback();
            window.closeModal('confirmModal');
        };
    }
    window.openModal('confirmModal');
}
window.openModal = function(id) { var el = document.getElementById(id); if(el) el.classList.add('active'); };
window.closeModal = function(id) { var el = document.getElementById(id); if(el) el.classList.remove('active'); };

window.toast = function(msg, type = 'info') {
    var icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info', warning: 'fa-triangle-exclamation' };
    var container = document.getElementById('toastContainer') || document.body;
    var el = Object.assign(document.createElement('div'), {
        className: 'toast ' + type,
        innerHTML: '<i class="fas ' + (icons[type] || icons.info) + '"></i><span>' + msg + '</span>'
    });
    container.appendChild(el);
    setTimeout(function() { el.style.opacity = '0'; el.style.transition = 'opacity .4s'; setTimeout(function() { el.remove(); }, 400); }, 3500);
};

// Khởi chạy hệ thống độc lập
window.initScheduleManagement();