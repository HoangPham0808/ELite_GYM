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

/* ── USER_EMPLOYEE_ID alias (view dùng TRAINER_ID) ── */
if (typeof window.USER_EMPLOYEE_ID === 'undefined') {
    window.USER_EMPLOYEE_ID = (typeof window.TRAINER_ID !== 'undefined') ? window.TRAINER_ID : 0;
}

/* ── 2. BIẾN TOÀN CỤC CẤU HÌNH LỊCH TRÌNH ── */
var _schBase = (window.ELITE_BASE && window.ELITE_BASE !== 'undefined')
    ? window.ELITE_BASE
    : (window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/public').replace('/public/public', '/public');
var API_SCHEDULE = _schBase + '/api/admin/schedule';
var LIMIT = 10;

async function fetchJson(url, options = {}) {
    var res = await fetch(url, options);
    var text = await res.text();
    if (!res.ok) {
        console.error('API request failed:', url, res.status, res.statusText, text);
        throw new Error('API request failed with status ' + res.status);
    }
    try {
        return JSON.parse(text);
    } catch (err) {
        console.error('Invalid JSON response from', url, 'status', res.status, 'body:', text);
        throw err;
    }
}

var currentView = 'calendar'; 
var currentFilterRoom = '';
var currentFilterClass = '';
var currentDate = new Date(); 

var PACKAGE_COLORS = {
    'Basic':    { bg: '#e0f2fe', text: '#0369a1', border: '#7dd3fc', tag: 'pkg-basic'    },
    'Standard': { bg: '#dcfce7', text: '#166534', border: '#86efac', tag: 'pkg-standard' },
    'Premium':  { bg: '#fef3c7', text: '#b45309', border: '#fcd34d', tag: 'pkg-premium'  },
    'VIP':      { bg: '#f3e8ff', text: '#6b21a8', border: '#c084fc', tag: 'pkg-vip'      },
    'Student':  { bg: '#ecfdf5', text: '#065f46', border: '#6ee7b7', tag: 'pkg-student'  },
    'Gold':     { bg: '#fffbeb', text: '#92400e', border: '#fde68a', tag: 'pkg-gold'     },
    'Diamond':  { bg: '#ede9fe', text: '#4c1d95', border: '#a78bfa', tag: 'pkg-diamond'  },
    'Ruby':     { bg: '#ffe4e6', text: '#9f1239', border: '#fda4af', tag: 'pkg-ruby'     }
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
    var hlvId  = (document.getElementById('listHlv')  || {}).value || '';
    var roomId = (document.getElementById('listRoom') || {}).value || '';
    var fromDt = (document.getElementById('listFrom') || {}).value || '';
    var toDt   = (document.getElementById('listTo')   || {}).value || '';
    var tbody = document.getElementById('listTbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="8" class="loading-cell"><i class="fas fa-spinner fa-spin"></i> Đang tải danh sách...</td></tr>';

    try {
        var params = new URLSearchParams({
            action: 'get_list_view',
            page: page,
            limit: LIMIT,
            search: search,
            hlv_id: hlvId,
            room_id: roomId,
            from: fromDt,
            to: toDt
        });

        var d = await fetchJson(API_SCHEDULE + '?' + params.toString(), { credentials: 'include' });

        if (d.success && d.data) {
            renderListTable(d.data);
            var metaEl = document.getElementById('listMeta');
            if (metaEl) metaEl.textContent = '';
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

/* ── TIMELINE CONFIG ── */
var TG_START_HOUR = 6;   // 06:00
var TG_END_HOUR   = 23;  // 23:00
var TG_HOUR_PX    = 60;  // pixels per hour

function _localDateStr(d) {
    // Returns YYYY-MM-DD in LOCAL time (not UTC) to avoid timezone mismatch
    var pad = function(n){ return String(n).padStart(2,'0'); };
    return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());
}

function renderCalendarSkeleton() {
    var calendarGrid = document.getElementById('calendarGrid');
    if (!calendarGrid) return;

    var startOfWeek = getMonday(currentDate);
    var endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6);

    // Update week title
    var viewTitle = document.getElementById('calWeekTitle');
    var startDay = startOfWeek.getDate();
    var endDay   = endOfWeek.getDate();
    var month    = startOfWeek.getMonth() + 1;
    if (viewTitle) viewTitle.textContent =
        startDay + ' – ' + endDay + ' tháng ' + month + ', ' + startOfWeek.getFullYear();

    var shortDays = ['T2','T3','T4','T5','T6','T7','CN'];
    var totalHours = TG_END_HOUR - TG_START_HOUR;
    var bodyH = totalHours * TG_HOUR_PX;

    // ── Header row ──
    var headerHtml = '<div class="tg-header-row"><div class="tg-corner"></div>';
    for (var i = 0; i < 7; i++) {
        var d = new Date(startOfWeek);
        d.setDate(startOfWeek.getDate() + i);
        var isToday = isSameDate(d, new Date()) ? ' tg-day-header-today' : '';
        var dateStr = _localDateStr(d);
        headerHtml +=
            '<div class="tg-day-header' + isToday + '" data-date="' + dateStr + '">' +
            '<span class="tg-day-name">' + shortDays[i] + '</span>' +
            '<span class="tg-day-num">' + d.getDate() + '</span>' +
            '</div>';
    }
    headerHtml += '</div>';

    // ── Body ──
    var bodyHtml = '<div class="tg-body" style="height:' + (bodyH + 2) + 'px">';
    // Time column
    bodyHtml += '<div class="tg-time-col" style="height:' + bodyH + 'px">';
    for (var h = TG_START_HOUR; h <= TG_END_HOUR; h++) {
        var topPx = (h - TG_START_HOUR) * TG_HOUR_PX;
        bodyHtml += '<div class="tg-hour-tick" style="top:' + topPx + 'px">' +
            String(h).padStart(2,'0') + ':00</div>';
    }
    bodyHtml += '</div>';
    // Day columns
    for (var i = 0; i < 7; i++) {
        var d = new Date(startOfWeek);
        d.setDate(startOfWeek.getDate() + i);
        var isTodayCls = isSameDate(d, new Date()) ? ' tg-today' : '';
        var dateStr = _localDateStr(d);
        bodyHtml += '<div class="tg-col' + isTodayCls + '" data-date="' + dateStr +
                    '" style="height:' + bodyH + 'px">';
        for (var h = 0; h < totalHours; h++) {
            var cls = (h % 2 === 0) ? ' tg-grid-even' : '';
            bodyHtml += '<div class="tg-grid-line' + cls + '" style="top:' + (h * TG_HOUR_PX) + 'px"></div>';
        }
        bodyHtml += '</div>';
    }
    bodyHtml += '</div>'; // tg-body

    calendarGrid.innerHTML = '<div class="tg-outer">' + headerHtml + bodyHtml + '</div>';
    _drawNowLine();
    _scrollCalendarToNow();
}

function _scrollCalendarToNow() {
    // The scrollable container is .tg-body (max-height:520px overflow-y:auto)
    var body = document.querySelector('.tg-body');
    if (!body) return;
    var now = new Date();
    var h = now.getHours() + now.getMinutes() / 60;
    var targetH = (h >= TG_START_HOUR && h <= TG_END_HOUR) ? Math.max(TG_START_HOUR, h - 1) : 8;
    body.scrollTop = (targetH - TG_START_HOUR) * TG_HOUR_PX;
}


async function loadStats() {
    try {
        var d = await fetchJson(API_SCHEDULE + '?action=get_stats', { credentials: 'include' });
        if (!d.success) return;
        var map = {
            sTotal: d.total, sToday: d.today, sWeek: d.week,
            sDangKy: d.registered, sHlv: d.trainers, sSapDien: d.upcoming
        };
        Object.keys(map).forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.textContent = map[id] ?? '—';
        });
    } catch(e) { console.warn('Stats load failed', e); }
}

window.initScheduleManagement = function() {
    window.initCalendar();
    loadStats();
    _populateListFilters();  // populate + wire list view filter dropdowns
    _populateModalSelects(); // populate trainer & room dropdowns in add/edit modal

    // Wire repeat-opt click
    document.querySelectorAll('.repeat-opt').forEach(function(opt) {
        opt.addEventListener('click', function() {
            var val = opt.dataset.val;
            document.querySelectorAll('.repeat-opt').forEach(function(o) {
                o.classList.toggle('active', o.dataset.val === val);
            });
            var radio = opt.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
            window.handleRepeatTypeChange(val);
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                window.closeModal(overlay.id);
            }
        });
    });

};

async function _populateModalSelects() {
    try {
        var dT = await fetchJson(API_SCHEDULE + '?action=get_trainers', { credentials: 'include' });
        var sel = document.getElementById('fSchHlv');
        if (sel && dT.success && dT.data) {
            dT.data.forEach(function(t) {
                var opt = document.createElement('option');
                opt.value = t.employee_id;
                opt.textContent = t.full_name;
                sel.appendChild(opt);
            });
        }
    } catch(e) { console.warn('Could not load trainers for modal', e); }

    try {
        var dR = await fetchJson(API_SCHEDULE + '?action=get_rooms', { credentials: 'include' });
        var sel2 = document.getElementById('fSchRoom');
        if (sel2 && dR.success && dR.data) {
            dR.data.forEach(function(r) {
                var opt = document.createElement('option');
                opt.value = r.room_id;
                opt.textContent = r.room_name;
                sel2.appendChild(opt);
            });
        }
    } catch(e) { console.warn('Could not load rooms for modal', e); }
}

async function _populateListFilters() {
    // Populate HLV dropdown
    try {
        var dT = await fetchJson(API_SCHEDULE + '?action=get_trainers', { credentials: 'include' });
        var hlvSel = document.getElementById('listHlv');
        if (hlvSel && dT.success && dT.data) {
            dT.data.forEach(function(t) {
                var opt = document.createElement('option');
                opt.value = t.employee_id;
                opt.textContent = t.full_name;
                hlvSel.appendChild(opt);
            });
        }
    } catch(e) { console.warn('Could not load trainers for filter', e); }

    // Populate room dropdown
    try {
        var dR = await fetchJson(API_SCHEDULE + '?action=get_rooms', { credentials: 'include' });
        var roomSel = document.getElementById('listRoom');
        if (roomSel && dR.success && dR.data) {
            dR.data.forEach(function(r) {
                var opt = document.createElement('option');
                opt.value = r.room_id;
                opt.textContent = r.room_name;
                roomSel.appendChild(opt);
            });
        }
    } catch(e) { console.warn('Could not load rooms for filter', e); }

    // Wire filter change events
    var hlvEl   = document.getElementById('listHlv');
    var roomEl  = document.getElementById('listRoom');
    var fromEl  = document.getElementById('listFrom');
    var toEl    = document.getElementById('listTo');
    var srchEl  = document.getElementById('listSearch');

    function reloadList() { if (currentView === 'list') window.loadListViewData(1); }

    if (hlvEl)  hlvEl.addEventListener('change',  reloadList);
    if (roomEl) roomEl.addEventListener('change', reloadList);
    if (fromEl) fromEl.addEventListener('change', reloadList);
    if (toEl)   toEl.addEventListener('change',   reloadList);
    if (srchEl) srchEl.addEventListener('input',  debounce(reloadList, 300));
}

async function loadScheduleData() {
    if (currentView === 'list') {
        window.loadListViewData(1);
        return;
    }

    var startOfWeek = getMonday(currentDate);
    var startStr = _localDateStr(startOfWeek);

    document.querySelectorAll('.tg-col').forEach(function(col) {
        col.querySelectorAll('.tg-event').forEach(function(ev) { ev.remove(); });
    });

    try {
        var params = new URLSearchParams({
            action: 'get_week_schedules',
            week_start: startStr,
            room_id: currentFilterRoom,
            class_type: currentFilterClass
        });

        var d = await fetchJson(API_SCHEDULE + '?' + params.toString(), { credentials: 'include' });

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
    var now  = new Date();

    events.forEach(function(ev) {
        // Replace space separator with 'T' for cross-browser ISO 8601 parsing
        var start = new Date((ev.start_time || '').replace(' ', 'T'));
        var end   = new Date((ev.end_time   || ev.start_time || '').replace(' ', 'T'));
        // Use LOCAL date string to match column data-date (avoid UTC timezone shift)
        var dateStr = _localDateStr(start);

        var col = document.querySelector('.tg-col[data-date="' + dateStr + '"]');
        if (!col) return;

        var startH = start.getHours() + start.getMinutes() / 60;
        var endH   = end.getHours()   + end.getMinutes()   / 60;

        // Clamp to grid range
        if (endH <= TG_START_HOUR || startH >= TG_END_HOUR) return;
        startH = Math.max(startH, TG_START_HOUR);
        endH   = Math.min(endH,   TG_END_HOUR);

        var topPx    = (startH - TG_START_HOUR) * TG_HOUR_PX;
        var heightPx = Math.max((endH - startH) * TG_HOUR_PX - 2, 24);

        var pkg      = ev.package_name || 'Basic';
        var pkgCls   = 'pkg-' + pkg.toLowerCase();
        var isPast   = end < now ? ' past' : '';
        var isSoon   = (!isPast && (start - now) < 30 * 60 * 1000) ? ' soon' : '';
        var timeCls  = isPast ? ' past-time' : (isSoon ? ' soon-time' : '');

        var currentReg = parseInt(ev.current_enrollment || 0);
        var maxReg     = parseInt(ev.max_capacity || 0);
        var timeStr    = formatTimeHHMM(ev.start_time) + '-' + formatTimeHHMM(ev.end_time);

        var editBtns = '';
        if (role === 'admin') {
            editBtns = '<button class="tg-event-edit" onclick="event.stopPropagation();window.openEditSchedule(' + ev.class_id + ')" title="Sửa"><i class="fas fa-pen"></i></button>' +
                       '<button class="tg-event-del" onclick="event.stopPropagation();window.deleteScheduleClass(' + ev.class_id + ')" title="Xóa"><i class="fas fa-trash"></i></button>';
        }

        var capHtml = '';
        if (heightPx >= 40) {
            capHtml = '<div class="tg-event-sub"><i class="fas fa-users"></i>' +
                currentReg + '/' + maxReg + ' &nbsp;<i class="fas fa-door-open"></i>' +
                escapeHtml(ev.room_name || '') + '</div>';
        }
        var trainerHtml = '';
        if (heightPx >= 56 && ev.trainer_name) {
            trainerHtml = '<div class="tg-event-sub"><i class="fas fa-user-tie"></i>' +
                escapeHtml(ev.trainer_name) + '</div>';
        }

        var cardHtml =
            '<div class="tg-event ' + pkgCls + isPast + isSoon + '" ' +
            'style="top:' + topPx + 'px;height:' + heightPx + 'px" ' +
            'onclick="window.openClassDetail(' + ev.class_id + ')">' +
            '<div class="tg-event-time' + timeCls + '">' + timeStr + '</div>' +
            '<div class="tg-event-name">' + escapeHtml(ev.class_name) + '</div>' +
            capHtml + trainerHtml +
            '<span class="tg-event-count">' + pkg + '</span>' +
            '' +
            '</div>';

        col.insertAdjacentHTML('beforeend', cardHtml);
    });

    // After placing events: if current time is outside event range, scroll to earliest event
    var allEvents = document.querySelectorAll('.tg-event');
    if (allEvents.length > 0) {
        var minTop = Infinity;
        allEvents.forEach(function(ev) { minTop = Math.min(minTop, parseInt(ev.style.top) || 0); });
        var wrap = document.querySelector('.tg-body');
        if (wrap) {
            var now = new Date();
            var h = now.getHours() + now.getMinutes() / 60;
            // Only override scroll if current time not in event range
            if (h < TG_START_HOUR || h > TG_END_HOUR || minTop > wrap.clientHeight) {
                wrap.scrollTop = Math.max(0, minTop - 40);
            }
        }
    }
}

function _drawNowLine() {
    document.querySelectorAll('.tg-now-line').forEach(function(el) { el.remove(); });
    var now = new Date();
    var todayStr = _localDateStr(now);
    var col = document.querySelector('.tg-col[data-date="' + todayStr + '"]');
    if (!col) return;
    var h = now.getHours() + now.getMinutes() / 60;
    if (h < TG_START_HOUR || h > TG_END_HOUR) return;
    var top = (h - TG_START_HOUR) * TG_HOUR_PX;
    var line = document.createElement('div');
    line.className = 'tg-now-line';
    line.style.top = top + 'px';
    col.appendChild(line);
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
            '<div class="list-time-row"><i class="far fa-calendar"></i> ' + formatDateDMY(new Date((r.start_time || '').replace(' ', 'T'))) + '</div>' +
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
    document.getElementById('btnCalView').classList.toggle('active', viewType === 'calendar');
    document.getElementById('btnListView').classList.toggle('active', viewType === 'list');

    if (viewType === 'calendar') {
        document.getElementById('view-calendar').style.display = 'block';
        document.getElementById('view-list').style.display = 'none';
        document.getElementById('calNav').style.display = 'flex';
        window.initCalendar();
    } else {
        document.getElementById('view-calendar').style.display = 'none';
        document.getElementById('view-list').style.display = 'block';
        document.getElementById('calNav').style.display = 'none';
        var viewTitle = document.getElementById('calWeekTitle');
        if (viewTitle) viewTitle.textContent = 'Danh sách toàn bộ lớp học tập';
        
        window.loadListViewData(1);
    }
};

window.switchCalMode = function(mode) {
    var btnWeek  = document.getElementById('btnSubWeek');
    var btnRoom  = document.getElementById('btnSubRoom');
    var weekWrap = document.getElementById('weekGridWrap');
    var roomWrap = document.getElementById('roomGridWrap');
    var roomTabs = document.getElementById('roomDayTabs');
    if (!weekWrap || !roomWrap) return;

    var weekMode = (mode === 'week');
    if (btnWeek) btnWeek.classList.toggle('active', weekMode);
    if (btnRoom) btnRoom.classList.toggle('active', !weekMode);
    weekWrap.style.display = weekMode ? 'block' : 'none';
    roomWrap.style.display = weekMode ? 'none' : 'block';
    if (roomTabs) roomTabs.style.display = weekMode ? 'none' : 'flex';

    if (!weekMode) {
        if (roomTabs) _buildRoomDayTabs(roomTabs);
        loadRoomViewData();
    } else {
        loadScheduleData();
    }
};

function _buildRoomDayTabs(container) {
    var startOfWeek = getMonday(currentDate);
    var dayNames = ['T2','T3','T4','T5','T6','T7','CN'];
    var html = '';
    for (var i = 0; i < 7; i++) {
        var d = new Date(startOfWeek); d.setDate(startOfWeek.getDate() + i);
        var isToday = isSameDate(d, new Date()) ? ' active' : '';
        var dateStr = _localDateStr(d);
        html += '<button class="room-tab' + isToday + '" data-date="' + dateStr +
                '" onclick="window.selectRoomDay(this)">' + dayNames[i] + ' ' + d.getDate() + '</button>';
    }
    container.innerHTML = html;
}

window.selectRoomDay = function(btn) {
    document.querySelectorAll('#roomDayTabs .room-tab').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    loadRoomViewData();
};

async function loadRoomViewData() {
    var grid = document.getElementById('roomColGrid');
    if (!grid) return;
    grid.innerHTML = '<div class="rcg-loading"><i class="fas fa-spinner fa-spin"></i> Đang tải lịch theo phòng...</div>';

    var activeTab = document.querySelector('#roomDayTabs .room-tab.active');
    var selectedDate = activeTab ? activeTab.dataset.date : _localDateStr(currentDate);

    try {
        var d = await fetchJson(
            API_SCHEDULE + '?action=get_day_schedules&date=' + selectedDate +
            '&room_id=' + encodeURIComponent(currentFilterRoom || ''),
            { credentials: 'include' }
        );
        if (d.success && Array.isArray(d.data)) {
            _renderRoomGrid(grid, d.data);
        } else {
            grid.innerHTML = '<div class="rcg-loading" style="color:var(--tm)"><i class="fas fa-calendar-xmark"></i> Không có lịch tập ngày này</div>';
        }
    } catch(e) {
        console.error(e);
        grid.innerHTML = '<div class="rcg-loading" style="color:red"><i class="fas fa-exclamation-circle"></i> Lỗi tải dữ liệu</div>';
    }
}

function _renderRoomGrid(container, events) {
    if (!events.length) {
        container.innerHTML = '<div class="rcg-loading" style="color:var(--tm)"><i class="fas fa-calendar-xmark"></i> Không có lịch tập ngày này</div>';
        return;
    }
    // Group by room
    var rooms = {}, roomOrder = [];
    events.forEach(function(ev) {
        var key = ev.room_id || 'none';
        if (!rooms[key]) { rooms[key] = { id: ev.room_id, name: ev.room_name || '?', pkg: ev.package_name || 'Basic', events: [] }; roomOrder.push(key); }
        rooms[key].events.push(ev);
    });

    var totalH = TG_END_HOUR - TG_START_HOUR;
    var bodyH = totalH * TG_HOUR_PX;
    var cols = roomOrder.map(function(k) { return rooms[k]; });

    var headerHtml = '<div class="rcg-header-row"><div class="rcg-time-corner"></div>';
    cols.forEach(function(r) {
        var pkgCls = (PACKAGE_COLORS[r.pkg] || PACKAGE_COLORS['Basic']).tag;
        headerHtml += '<div class="rcg-room-header ' + pkgCls + '">' +
            '<div class="rcg-room-title"><i class="fas fa-door-open"></i> ' + escapeHtml(r.name) + '</div>' +
            '<div class="rcg-room-meta"><span class="rcg-cap-badge"><i class="fas fa-calendar-check"></i> ' + r.events.length + ' lớp</span></div></div>';
    });
    headerHtml += '</div>';

    var bodyHtml = '<div class="rcg-body" style="position:relative;height:' + bodyH + 'px;overflow-y:auto;display:grid;grid-template-columns:48px repeat(' + cols.length + ',1fr)">';
    var timeCol = '<div class="tg-time-col" style="height:' + bodyH + 'px">';
    for (var h = TG_START_HOUR; h <= TG_END_HOUR; h++) {
        timeCol += '<div class="tg-hour-tick" style="top:' + ((h - TG_START_HOUR) * TG_HOUR_PX) + 'px">' + String(h).padStart(2,'0') + ':00</div>';
    }
    bodyHtml += timeCol + '</div>';

    cols.forEach(function(r) {
        var col = '<div class="tg-col" style="height:' + bodyH + 'px">';
        for (var h = 0; h < totalH; h++) col += '<div class="tg-grid-line' + (h%2===0?' tg-grid-even':'') + '" style="top:' + (h*TG_HOUR_PX) + 'px"></div>';
        r.events.forEach(function(ev) {
            var st = new Date((ev.start_time||'').replace(' ','T'));
            var en = new Date((ev.end_time||ev.start_time||'').replace(' ','T'));
            var sH = st.getHours() + st.getMinutes()/60;
            var eH = en.getHours() + en.getMinutes()/60;
            if (eH <= TG_START_HOUR || sH >= TG_END_HOUR) return;
            sH = Math.max(sH, TG_START_HOUR); eH = Math.min(eH, TG_END_HOUR);
            var top = (sH - TG_START_HOUR) * TG_HOUR_PX;
            var height = Math.max((eH - sH) * TG_HOUR_PX - 4, 18);
            var color = PACKAGE_COLORS[ev.package_name] || PACKAGE_COLORS['Basic'];
            col += '<div class="tg-event" style="top:' + top + 'px;height:' + height + 'px;background:' + color.bg + ';border-left-color:' + color.border + ';color:' + color.text + '" onclick="window.openClassDetail(' + ev.class_id + ')">' +
                '<div class="tg-ev-time">' + formatTimeHHMM(ev.start_time) + '–' + formatTimeHHMM(ev.end_time) + ' <span class="pkg-tag ' + color.tag + '" style="font-size:9px">' + (ev.package_name||'Basic') + '</span></div>' +
                '<div class="tg-ev-name">' + escapeHtml(ev.class_name || r.name) + '</div>' +
                '<div class="tg-ev-meta"><i class="fas fa-users"></i> ' + (ev.current_enrollment||0) + '/' + (ev.max_capacity||30) + '</div></div>';
        });
        bodyHtml += col + '</div>';
    });
    bodyHtml += '</div>';

    container.innerHTML = '<div class="rcg-outer">' + headerHtml + bodyHtml + '</div>';
    var body = container.querySelector('.rcg-body');
    if (body) {
        var now = new Date();
        var nowH = now.getHours();
        var targetH = Math.min(Math.max(nowH - 1, TG_START_HOUR), TG_END_HOUR);
        body.scrollTop = (targetH - TG_START_HOUR) * TG_HOUR_PX;
    }
}

window.calNavPrev = function() { window.navigateWeek(-1); };
window.calNavNext = function() { window.navigateWeek(1); };
window.calGoToday = function() { window.navigateWeek(0); };
window.openAddModal = function() { window.openAddScheduleModal(); };
window.saveSchedule = function() { window.saveScheduleClass(); };

window.filterRoomChanged = function(roomId) { currentFilterRoom = roomId; loadScheduleData(); };
window.filterClassChanged = function(classType) { currentFilterClass = classType; loadScheduleData(); };
window.triggerListSearch = function() { if (currentView === 'list') window.loadListViewData(1); };

window.openClassDetail = async function(classId) {
    var detailBox = document.getElementById('detailContent');
    if (!detailBox) return;
    detailBox.innerHTML = '<div class="loading-cell"><i class="fas fa-spinner fa-spin"></i> Đang đọc thông tin lớp...</div>';
    window.openModal('detailModal');

    try {
        var res = await fetch(API_SCHEDULE + '?action=get_class_detail&id=' + classId, { credentials: 'include' });
        var d = await res.json();
        if (!d.success) { detailBox.innerHTML = '<div class="error-msg">' + (d.message || 'Không tìm thấy lớp học') + '</div>'; return; }

        var cls = d['class'];  // 'class' is a reserved word — use bracket notation
        if (!cls) { detailBox.innerHTML = '<div class="error-msg">Dữ liệu lớp học không hợp lệ</div>'; return; }
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
            '<div class="dig-card"><span class="lbl">Ngày tập</span><span class="val"><i class="far fa-calendar"></i> ' + formatDateDMY(new Date((cls.start_time || '').replace(' ', 'T'))) + '</span></div>' +
            '<div class="dig-card"><span class="lbl">Thời gian lớp</span><span class="val"><i class="far fa-clock"></i> ' + formatTimeHHMM(cls.start_time) + (cls.end_time ? ' - ' + formatTimeHHMM(cls.end_time) : '') + '</span></div>' +
            '<div class="dig-card"><span class="lbl">Sức chứa phòng</span><span class="val"><i class="fas fa-users"></i> Đã đăng ký ' + currentReg + '/' + maxReg + ' chỗ</span></div>' +
            '</div>' +
            '</div>';

        // Nút action theo role
        var _role = (typeof window.USER_ROLE !== 'undefined') ? window.USER_ROLE : 'admin';
        var _myEmpId = (typeof window.USER_EMPLOYEE_ID !== 'undefined') ? parseInt(window.USER_EMPLOYEE_ID) : 0;
        var _isMyClass = cls.trainer_id && parseInt(cls.trainer_id) === _myEmpId;
        var _sectionBtn = '';
        if (_role === 'trainer') {
            if (_isMyClass) {
                _sectionBtn = '<button class="btn-sm" style="background:#ef4444;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;" onclick="window.unassignTrainerSelf(' + cls.class_id + ')"><i class="fas fa-user-minus"></i> Hủy đăng ký dạy</button>';
            } else {
                _sectionBtn = '<button class="btn-primary btn-sm" onclick="window.assignTrainerSelf(' + cls.class_id + ')"><i class="fas fa-chalkboard-teacher"></i> Đăng ký dạy buổi này</button>';
            }
        } else {
            _sectionBtn = '<button class="btn-primary btn-sm" onclick="window.openRegistrationModal(' + cls.class_id + ', \'' + pkg + '\')"><i class="fas fa-user-plus"></i> Đăng ký hộ</button>';
        }
        html += '<div class="detail-section-heading">' +
            '<span><i class="fas fa-list-ol"></i> Danh sách hội viên tham gia (' + currentReg + ')</span>' +
            _sectionBtn +
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
                    '<td style="font-size:12px;">' + (m.registration_time ? formatDateDMY(new Date((m.registration_time || '').replace(' ', 'T'))) : '—') + '</td>' +
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
    document.getElementById('fRegLichId').value = classId;
    document.getElementById('fRegLichId').dataset.pkg = packageName || '';
    document.getElementById('fRegPhone').value = '';
    document.getElementById('fRegFoundBox').style.display = 'none';
    document.getElementById('fRegFoundBox').innerHTML = '';
    document.getElementById('fRegKhId').value = '';
    document.getElementById('fRegErrMsg').style.display = 'none';
    window.openModal('regModal');
    setTimeout(function() {
        var el = document.getElementById('fRegPhone');
        if (el) el.focus();
    }, 200);
};

/* Lookup customer by exact phone number */
window.lookupCustomerByPhone = async function() {
    var phone = (document.getElementById('fRegPhone').value || '').trim();
    var errEl = document.getElementById('fRegErrMsg');
    var foundBox = document.getElementById('fRegFoundBox');

    errEl.style.display = 'none';
    foundBox.style.display = 'none';
    foundBox.innerHTML = '';
    document.getElementById('fRegKhId').value = '';

    if (!phone) {
        errEl.textContent = 'Vui lòng nhập số điện thoại.';
        errEl.style.display = 'block';
        return;
    }

    foundBox.innerHTML = '<div style="padding:10px;color:var(--tm)"><i class="fas fa-spinner fa-spin"></i> Đang tra cứu...</div>';
    foundBox.style.display = 'block';

    try {
        var res = await fetch(API_SCHEDULE + '?action=search_customer&q=' + encodeURIComponent(phone), { credentials: 'include' });
        var d = await res.json();

        if (!d.success || !d.data || d.data.length === 0) {
            foundBox.innerHTML = '';
            foundBox.style.display = 'none';
            errEl.textContent = 'Không tìm thấy khách hàng với SĐT "' + phone + '". Kiểm tra lại số điện thoại.';
            errEl.style.display = 'block';
            return;
        }

        // Show matched customers (usually 1)
        var html = '<div style="font-size:11px;color:var(--tm);margin-bottom:6px">Chọn khách hàng:</div>';
        d.data.forEach(function(c) {
            var pkg = c.package_name || 'Basic';
            var pkgColor = PACKAGE_COLORS[pkg] || PACKAGE_COLORS['Basic'];
            html += '<div class="kh-option" style="cursor:pointer;border:2px solid transparent;border-radius:8px;padding:10px;margin-bottom:6px;background:var(--surface-2)" ' +
                'onclick="window.confirmRegCustomer(' + c.customer_id + ', \'' + escapeHtml(c.full_name) + '\', \'' + escapeHtml(c.phone || phone) + '\', \'' + escapeHtml(pkg) + '\')">' +
                '<div style="font-weight:600;font-size:14px;color:var(--tp)">' + escapeHtml(c.full_name) + '</div>' +
                '<div style="font-size:12px;color:var(--tm);margin-top:3px">' +
                '📞 ' + escapeHtml(c.phone || phone) +
                ' &nbsp;|&nbsp; Gói: <span class="pkg-tag ' + pkgColor.tag + '">' + escapeHtml(pkg) + '</span>' +
                '</div></div>';
        });
        foundBox.innerHTML = html;

        // Auto-select if only 1 result
        if (d.data.length === 1) {
            var c = d.data[0];
            window.confirmRegCustomer(c.customer_id, c.full_name, c.phone || phone, c.package_name || 'Basic');
        }
    } catch(e) {
        console.error(e);
        foundBox.innerHTML = '';
        foundBox.style.display = 'none';
        errEl.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
        errEl.style.display = 'block';
    }
};

window.confirmRegCustomer = function(id, name, phone, pkg) {
    document.getElementById('fRegKhId').value = id;
    var pkgColor = PACKAGE_COLORS[pkg] || PACKAGE_COLORS['Basic'];
    document.getElementById('fRegFoundBox').innerHTML =
        '<div style="background:var(--green-bg,#f0fdf4);border:1.5px solid #86efac;border-radius:8px;padding:12px;display:flex;align-items:center;gap:10px">' +
        '<i class="fas fa-circle-check" style="color:#22c55e;font-size:18px;flex-shrink:0"></i>' +
        '<div><div style="font-weight:600;color:var(--tp)">' + escapeHtml(name) + '</div>' +
        '<div style="font-size:12px;color:var(--tm)">📞 ' + escapeHtml(phone) +
        ' &nbsp;|&nbsp; <span class="pkg-tag ' + pkgColor.tag + '">' + escapeHtml(pkg) + '</span></div></div>' +
        '<button type="button" onclick="window.clearRegCustomer()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--tm);font-size:16px" title="Chọn lại"><i class="fas fa-times"></i></button>' +
        '</div>';
    document.getElementById('fRegErrMsg').style.display = 'none';
};

window.clearRegCustomer = function() {
    document.getElementById('fRegKhId').value = '';
    document.getElementById('fRegFoundBox').innerHTML = '';
    document.getElementById('fRegFoundBox').style.display = 'none';
    document.getElementById('fRegPhone').value = '';
    document.getElementById('fRegPhone').focus();
};

window.saveRegistration = function() {
    var classId    = document.getElementById('fRegLichId').value;
    var customerId = document.getElementById('fRegKhId').value;

    if (!customerId) {
        // If phone entered but no customer selected yet, trigger lookup first
        var phone = (document.getElementById('fRegPhone').value || '').trim();
        if (phone) {
            window.lookupCustomerByPhone();
        } else {
            window.toast('Vui lòng nhập SĐT và tìm khách hàng trước!', 'warning');
        }
        return;
    }

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
    document.getElementById('fSchId').value = '';
    document.getElementById('fSchTen').value = '';
    document.getElementById('fSchRoom').value = '';
    document.getElementById('fSchHlv').value = '';
    document.getElementById('fSchTime').value = '';
    document.getElementById('fSchEndTime').value = '';
    // reset repeat
    var opts = document.querySelectorAll('.repeat-opt');
    opts.forEach(function(o) { o.classList.toggle('active', o.dataset.val === 'none'); });
    var radios = document.querySelectorAll('input[name="repeatType"]');
    radios.forEach(function(r) { r.checked = r.value === 'none'; });
    var rNote = document.getElementById('repeatNote');
    if (rNote) rNote.style.display = 'none';
    window.openModal('scheduleModal');
};

window.openEditSchedule = async function(classId) {
    window.openModal('scheduleModal');
    // edit mode — no repeat options needed

    try {
        var res = await fetch(API_SCHEDULE + '?action=get_class_detail&id=' + classId, { credentials: 'include' });
        var d = await res.json();
        if (!d.success) { window.toast(d.message || 'Không tải được dữ liệu lớp', 'error'); window.closeModal('scheduleModal'); return; }

        var cls = d['class'];  // bracket notation — 'class' is reserved in JS
        if (!cls) { window.toast('Dữ liệu lớp học không hợp lệ', 'error'); window.closeModal('scheduleModal'); return; }
        var start = new Date((cls.start_time || '').replace(' ', 'T'));

        function toLocalDT(dt) {
            if (!dt || isNaN(dt.getTime())) return '';
            var pad = function(n){ return String(n).padStart(2,'0'); };
            return dt.getFullYear() + '-' + pad(dt.getMonth()+1) + '-' + pad(dt.getDate()) +
                   'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
        }

        document.getElementById('fSchId').value       = cls.class_id;
        document.getElementById('fSchTen').value      = cls.class_name;
        document.getElementById('fSchRoom').value     = cls.room_id || '';
        document.getElementById('fSchHlv').value      = cls.trainer_id || '';
        document.getElementById('fSchTime').value     = toLocalDT(start);
        document.getElementById('fSchEndTime').value  = cls.end_time ? toLocalDT(new Date((cls.end_time || '').replace(' ', 'T'))) : '';
        var maxCapEl = document.getElementById('fSchMaxCap');
        if (maxCapEl) maxCapEl.value = cls.max_capacity || '';
    } catch (e) {
        window.toast('Lỗi nạp thông tin lớp chỉnh sửa', 'error');
        window.closeModal('scheduleModal');
    }
};

window.handleRepeatTypeChange = function(val) {
    var note = document.getElementById('repeatNote');
    if (note) {
        note.style.display = val !== 'none' ? 'block' : 'none';
        note.textContent = val === 'daily' ? 'Sẽ tạo lịch hằng ngày cho đến cuối tháng' :
                           val === 'weekly' ? 'Sẽ tạo lịch hằng tuần trong 4 tuần tới' : '';
    }
};

window.saveScheduleClass = async function() {
    var id        = document.getElementById('fSchId').value;
    var name      = document.getElementById('fSchTen').value.trim();
    var roomId    = document.getElementById('fSchRoom').value;
    var startTime = document.getElementById('fSchTime').value;
    var endTime   = document.getElementById('fSchEndTime').value;

    if (!name || !startTime) {
        window.toast('Vui lòng hoàn thiện toàn bộ trường bắt buộc (*)', 'warning');
        return;
    }

    // Convert datetime-local "YYYY-MM-DDTHH:mm" → "YYYY-MM-DD HH:mm:00"
    var toDbDT = function(v) { return v ? v.replace('T', ' ') + ':00' : ''; };

    var repeatType = '';
    var checkedRadio = document.querySelector('input[name="repeatType"]:checked');
    if (checkedRadio) repeatType = checkedRadio.value;

    var fd = new FormData();
    fd.append('action', id ? 'update_class' : 'add_class');
    if (id) fd.append('id', id);
    fd.append('class_name', name);
    fd.append('room_id', roomId);
    fd.append('trainer_id', document.getElementById('fSchHlv').value);
    fd.append('start_time', toDbDT(startTime));
    fd.append('end_time',   toDbDT(endTime));
    var maxCapEl = document.getElementById('fSchMaxCap');
    if (maxCapEl) fd.append('max_capacity', maxCapEl.value);
    if (!id) fd.append('repeat_type', repeatType);

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
            fd.append('id', classId);
            var res = await fetch(API_SCHEDULE, { method: 'POST', body: fd, credentials: 'include' });
            if (!res.ok) { window.toast('Lỗi server khi xóa (HTTP ' + res.status + ')', 'error'); return; }
            var text = await res.text();
            var d;
            try { d = JSON.parse(text); } catch(e) { window.toast('Server trả dữ liệu không hợp lệ', 'error'); console.error(text); return; }
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
    var d = new Date(timeStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return '--:--';
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
/* ══ HLV: Tự đăng ký / hủy dạy buổi tập ══════════════════════════ */
window.assignTrainerSelf = async function(classId) {
    if (!confirm('Xác nhận đăng ký dạy buổi tập này?')) return;
    var myId = (typeof window.USER_EMPLOYEE_ID !== 'undefined') ? parseInt(window.USER_EMPLOYEE_ID) : 0;
    if (!myId) { alert('Không tìm thấy thông tin huấn luyện viên.'); return; }
    var fd = new FormData();
    fd.append('action', 'assign_trainer');
    fd.append('class_id', classId);
    fd.append('trainer_id', myId);
    try {
        var res = await fetch(API_SCHEDULE, { method: 'POST', body: fd, credentials: 'include' });
        var d = await res.json();
        if (d.success) {
            window.closeModal('detailModal');
            if (typeof window.showToastNotification === 'function')
                window.showToastNotification('Đã đăng ký dạy buổi tập thành công!', 'success');
            loadScheduleData();
        } else {
            alert(d.message || 'Đăng ký thất bại.');
        }
    } catch(e) { alert('Lỗi kết nối.'); }
};

window.unassignTrainerSelf = async function(classId) {
    if (!confirm('Xác nhận hủy đăng ký dạy buổi tập này?')) return;
    var myId = (typeof window.USER_EMPLOYEE_ID !== 'undefined') ? parseInt(window.USER_EMPLOYEE_ID) : 0;
    if (!myId) { alert('Không tìm thấy thông tin huấn luyện viên.'); return; }
    var fd = new FormData();
    fd.append('action', 'unassign_trainer');
    fd.append('class_id', classId);
    fd.append('trainer_id', myId);
    try {
        var res = await fetch(API_SCHEDULE, { method: 'POST', body: fd, credentials: 'include' });
        var d = await res.json();
        if (d.success) {
            window.closeModal('detailModal');
            if (typeof window.showToastNotification === 'function')
                window.showToastNotification('Đã hủy đăng ký dạy buổi tập.', 'info');
            loadScheduleData();
        } else {
            alert(d.message || 'Hủy thất bại.');
        }
    } catch(e) { alert('Lỗi kết nối.'); }
};
