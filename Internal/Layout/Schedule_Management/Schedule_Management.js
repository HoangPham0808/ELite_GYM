const API   = 'Schedule_Management_function.php';
const LIMIT = 15;
const DAYS  = ['Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy','Chủ Nhật'];
const DAYS_SHORT = ['T2','T3','T4','T5','T6','T7','CN'];
const MON   = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];

let currentView      = 'calendar';
let currentWeekStart = getThisMonday();
let listPage         = 1;
let trainers         = [];
let rooms            = [];
let currentDetailId  = null;

// ── INIT ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal-overlay').forEach(o =>
        o.addEventListener('click', e => { if (e.target===o) o.classList.remove('active'); }));

    document.getElementById('listSearch').addEventListener('input', debounce(() => loadList(1), 350));
    document.getElementById('listHlv').addEventListener('change', () => loadList(1));
    document.getElementById('listRoom').addEventListener('change', () => loadList(1));
    document.getElementById('listFrom').addEventListener('change', () => loadList(1));
    document.getElementById('listTo').addEventListener('change',   () => loadList(1));
    document.getElementById('fRegSearch').addEventListener('input', debounce(searchKH, 300));

    loadStats();
    loadTrainers();
    loadRooms();
    renderCalendar();
});

const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
const esc = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const pad = n => String(n).padStart(2,'0');

// Parse chuỗi MySQL "YYYY-MM-DD HH:MM:SS" thành Date local (không bị lệch UTC)
function parseLocal(s) {
    if (!s || s === '0000-00-00 00:00:00') return null;
    return new Date(s.replace(' ', 'T'));
}
function fmtDatetime(s) {
    const d = parseLocal(s);
    if (!d) return '—';
    return `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
function fmtTime(s) {
    const d = parseLocal(s);
    if (!d) return '—';
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
function getThisMonday() {
    const d = new Date();
    d.setDate(d.getDate() - ((d.getDay()+6)%7));
    d.setHours(0,0,0,0);
    return d.toISOString().slice(0,10);
}
function addDays(dateStr, n) {
    const d = new Date(dateStr); d.setDate(d.getDate()+n);
    return d.toISOString().slice(0,10);
}
function formatWeekTitle(ws) {
    const we = addDays(ws, 6);
    const d1 = new Date(ws), d2 = new Date(we);
    if (d1.getMonth() === d2.getMonth())
        return `${d1.getDate()} – ${d2.getDate()} tháng ${d1.getMonth()+1}, ${d1.getFullYear()}`;
    return `${d1.getDate()}/${d1.getMonth()+1} – ${d2.getDate()}/${d2.getMonth()+1}/${d2.getFullYear()}`;
}

// ── VIEW SWITCH ───────────────────────────────────────────────────
function switchView(v) {
    currentView = v;
    document.getElementById('view-calendar').style.display = v==='calendar' ? 'block' : 'none';
    document.getElementById('view-list').style.display     = v==='list'     ? 'block' : 'none';
    document.getElementById('btnCalView').classList.toggle('active', v==='calendar');
    document.getElementById('btnListView').classList.toggle('active', v==='list');
    if (v==='list') loadList();
}

// ── STATS ─────────────────────────────────────────────────────────
async function loadStats() {
    try {
        const d = await apiFetch('get_stats');
        if (!d.success) return;
        document.getElementById('sTotal').textContent   = d.total    ??0;
        document.getElementById('sToday').textContent   = d.today    ??0;
        document.getElementById('sWeek').textContent    = d.this_week??0;
        document.getElementById('sDangKy').textContent  = d.dang_ky  ??0;
        document.getElementById('sHlv').textContent     = d.hlv      ??0;
        document.getElementById('sSapDien').textContent = d.sap_dien ??0;
    } catch(e) {
        console.error('loadStats error:', e);
    }
}

// ── TRAINERS ──────────────────────────────────────────────────────
async function loadTrainers() {
    try {
        const d = await apiFetch('get_trainers');
        trainers = d.data || [];
        const opts = trainers.map(t => `<option value="${t.employee_id}">${esc(t.full_name)}</option>`).join('');
        document.getElementById('fSchHlv').innerHTML = '<option value="">— Không chỉ định —</option>' + opts;
        document.getElementById('listHlv').innerHTML = '<option value="">Tất cả HLV</option>' + opts;
    } catch(e) {
        console.error('loadTrainers error:', e);
    }
}

// ── ROOMS ─────────────────────────────────────────────────────────
async function loadRooms() {
    try {
        const d = await apiFetch('get_rooms');
        rooms = d.data || [];
        const opts = rooms.map(r => `<option value="${r.room_id}">${esc(r.room_name)}</option>`).join('');
        document.getElementById('fSchRoom').innerHTML = '<option value="">— Không chỉ định —</option>' + opts;
        document.getElementById('listRoom').innerHTML = '<option value="">Tất cả phòng</option>' + opts;
    } catch(e) {
        console.error('loadRooms error:', e);
    }
}

// ── CALENDAR ──────────────────────────────────────────────────────
async function renderCalendar() {
    document.getElementById('calWeekTitle').textContent = formatWeekTitle(currentWeekStart);
    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';

    let events = [];
    try {
        const d = await apiFetch(`get_week_schedules&week_start=${currentWeekStart}`);
        events = d.data || [];
    } catch(e) {
        console.error('renderCalendar error:', e);
    }

    const today = new Date().toISOString().slice(0,10);
    const now   = new Date();

    // Headers
    for (let i=0; i<7; i++) {
        const dateStr = addDays(currentWeekStart, i);
        const dateObj = new Date(dateStr);
        const isToday = dateStr === today;
        const hdr = document.createElement('div');
        hdr.className = 'cal-day-header' + (isToday ? ' today' : '');
        hdr.innerHTML = `<div class="cal-day-name">${DAYS_SHORT[i]}</div>
            <div class="cal-day-date">${dateObj.getDate()}</div>`;
        grid.appendChild(hdr);
    }

    // Event columns
    for (let i=0; i<7; i++) {
        const dateStr   = addDays(currentWeekStart, i);
        const isToday   = dateStr === today;
        // Filter by start_time
        const dayEvents = events.filter(e => e.start_time && e.start_time.slice(0,10) === dateStr);

        const col = document.createElement('div');
        col.className = 'cal-day-col' + (isToday ? ' today' : '');

        dayEvents.forEach(e => {
            const evDate  = parseLocal(e.start_time);
            const evEnd   = e.end_time ? parseLocal(e.end_time) : null;
            const isPast  = evDate < now;
            const isSoon  = !isPast && (evDate - now) < 2*3600*1000;
            const cls     = isPast ? 'past' : isSoon ? 'soon' : 'upcoming';
            const timeCls = isPast ? 'past-time' : isSoon ? 'soon-time' : '';
            const hlvName = e.trainer_name ? esc(e.trainer_name) : '';
            const roomName = e.room_name ? esc(e.room_name) : '';
            const timeLabel = evEnd
                ? `${fmtTime(e.start_time)} – ${fmtTime(e.end_time)}`
                : fmtTime(e.start_time);

            const card = document.createElement('div');
            card.className = `cal-event ${cls}`;
            card.innerHTML = `
                <div class="cal-event-time ${timeCls}">
                    <i class="fas fa-clock" style="font-size:10px"></i>${timeLabel}
                </div>
                <div class="cal-event-name">${esc(e.class_name)}</div>
                ${hlvName ? `<div class="cal-event-hlv"><i class="fas fa-person-running" style="font-size:10px"></i>${hlvName}</div>` : ''}
                ${roomName ? `<div class="cal-event-hlv" style="color:var(--teal)"><i class="fas fa-door-open" style="font-size:10px"></i>${roomName}</div>` : ''}
                ${e.registration_count > 0 ? `<div class="cal-event-count">${e.registration_count}</div>` : ''}
            `;
            card.onclick = () => openDetail(e.class_id);
            col.appendChild(card);
        });

        if (!dayEvents.length) {
            const emp = document.createElement('div');
            emp.className = 'cal-empty';
            emp.textContent = 'Trống';
            col.appendChild(emp);
        }

        // Add button
        const addBtn = document.createElement('button');
        addBtn.className = 'cal-add-btn';
        addBtn.innerHTML = '<i class="fas fa-plus"></i> Thêm';
        addBtn.onclick = () => openAddModal(dateStr);
        col.appendChild(addBtn);
        grid.appendChild(col);
    }
}

function navWeek(dir) {
    const d = new Date(currentWeekStart);
    d.setDate(d.getDate() + dir * 7);
    currentWeekStart = d.toISOString().slice(0, 10);
    renderCalendar();
}
function goToday() {
    currentWeekStart = getThisMonday();
    renderCalendar();
}

// ── LIST VIEW ─────────────────────────────────────────────────────
async function loadList(page = listPage) {
    listPage = page;
    const search = document.getElementById('listSearch').value.trim();
    const hlv_id = document.getElementById('listHlv').value;
    const room_id = document.getElementById('listRoom').value;
    const from   = document.getElementById('listFrom').value;
    const to     = document.getElementById('listTo').value;
    const params = new URLSearchParams({ action:'get_schedules', page, limit:LIMIT, search, hlv_id, room_id, from, to });

    document.getElementById('listTbody').innerHTML = `<tr><td colspan="7" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>`;
    try {
        const d = await apiFetch(params.toString());
        renderListTable(d.data || []);
        renderPag(d.total||0, page, d.totalPages||1, 'listPagInfo', 'listPagCtrl', loadList);
        document.getElementById('listMeta').textContent = `${d.total||0} buổi tập`;
    } catch(e) {
        document.getElementById('listTbody').innerHTML = `<tr><td colspan="7"><div class="empty-state"><i class="fas fa-exclamation-circle"></i>Lỗi tải dữ liệu</div></td></tr>`;
    }
}

function getTimeBadge(startStr, endStr) {
    if (!startStr) return '—';
    const dt  = parseLocal(startStr);
    if (!dt) return '—';
    const now = new Date();
    const diff= dt - now;
    const time = `${pad(dt.getDate())}/${pad(dt.getMonth()+1)} ${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
    const endLabel = endStr ? ` – ${fmtTime(endStr)}` : '';
    if (diff < 0) return `<span class="time-badge tb-past"><i class="fas fa-check"></i> ${time}${endLabel}</span>`;
    if (diff < 3600000) return `<span class="time-badge tb-now"><i class="fas fa-fire"></i> ${time}${endLabel}</span>`;
    if (diff < 86400000) return `<span class="time-badge tb-soon"><i class="fas fa-clock"></i> ${time}${endLabel}</span>`;
    return `<span class="time-badge tb-future"><i class="fas fa-calendar"></i> ${time}${endLabel}</span>`;
}

function renderListTable(rows) {
    const tb = document.getElementById('listTbody');
    if (!rows.length) {
        tb.innerHTML = `<tr><td colspan="8"><div class="empty-state"><i class="fas fa-calendar-xmark"></i>Không có buổi tập nào</div></td></tr>`;
        return;
    }
    const thus = ['','Thứ 2','Thứ 3','Thứ 4','Thứ 5','Thứ 6','Thứ 7','CN'];
    tb.innerHTML = rows.map(r => {
        const d   = r.start_time ? parseLocal(r.start_time) : null;
        const thu = d ? thus[d.getDay()||7] : '—';
        const hlv = r.trainer_name
            ? `<div class="hlv-cell"><div class="hlv-av">${r.trainer_name.split(' ').pop().slice(0,2).toUpperCase()}</div><span style="font-size:13px;color:var(--ts)">${esc(r.trainer_name)}</span></div>`
            : `<span style="color:var(--tm)">—</span>`;
        const room = r.room_name
            ? `<span class="room-badge"><i class="fas fa-door-open"></i>${esc(r.room_name)}</span>`
            : `<span style="color:var(--tm)">—</span>`;
        return `<tr>
            <td class="id-cell">#${r.class_id}</td>
            <td><span class="col-primary">${esc(r.class_name)}</span></td>
            <td>${getTimeBadge(r.start_time, r.end_time)}</td>
            <td><span class="thu-badge">${thu}</span></td>
            <td>${hlv}</td>
            <td>${room}</td>
            <td><span class="reg-count"><i class="fas fa-users"></i>${r.registration_count||0} người</span></td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon" onclick="openDetail(${r.class_id})" title="Chi tiết & Đăng ký"><i class="fas fa-eye"></i></button>
                    <button class="btn-icon" onclick="openEdit(${r.class_id})" title="Sửa"><i class="fas fa-pen"></i></button>
                    <button class="btn-icon del" onclick="confirmDel('Xóa buổi tập <strong>${esc(r.class_name)}</strong>?',()=>deleteSchedule(${r.class_id}))" title="Xóa"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── ADD / EDIT SCHEDULE ───────────────────────────────────────────
function openAddModal(defaultDate='') {
    document.getElementById('fSchId').value    = '';
    document.getElementById('fSchTen').value   = '';
    document.getElementById('fSchHlv').value   = '';
    document.getElementById('fSchRoom').value  = '';
    document.getElementById('fSchEndTime').value = '';
    if (defaultDate) {
        document.getElementById('fSchTime').value = defaultDate + 'T08:00';
    } else {
        document.getElementById('fSchTime').value = '';
    }
    document.getElementById('schModalTitle').innerHTML = `<i class="fas fa-calendar-plus" style="color:var(--gold);margin-right:8px"></i>Thêm buổi tập`;
    openModal('scheduleModal');
}
async function openEdit(id) {
    try {
        const d = await apiFetch(`get_schedule_detail&id=${id}`);
        if (!d.success) { toast(d.message,'error'); return; }
        const r = d.schedule;
        document.getElementById('fSchId').value      = r.class_id;
        document.getElementById('fSchTen').value     = r.class_name||'';
        document.getElementById('fSchHlv').value     = r.trainer_id||'';
        document.getElementById('fSchRoom').value    = r.room_id||'';
        document.getElementById('fSchTime').value    = r.start_time ? r.start_time.slice(0,16) : '';
        document.getElementById('fSchEndTime').value = r.end_time   ? r.end_time.slice(0,16)   : '';
        document.getElementById('schModalTitle').innerHTML = `<i class="fas fa-pen" style="color:var(--gold);margin-right:8px"></i>Sửa buổi tập`;
        openModal('scheduleModal');
    } catch(e) {
        toast('Lỗi tải dữ liệu','error');
    }
}
async function saveSchedule() {
    const id        = document.getElementById('fSchId').value;
    const className = document.getElementById('fSchTen').value.trim();
    const startTime = document.getElementById('fSchTime').value;
    const endTime   = document.getElementById('fSchEndTime').value;
    const hlv       = document.getElementById('fSchHlv').value;
    const roomId    = document.getElementById('fSchRoom').value;
    if (!className||!startTime) { toast('Vui lòng nhập tên lớp và thời gian bắt đầu','warning'); return; }
    const body = fd({
        action: id ? 'update_schedule' : 'add_schedule',
        ...(id ? {id} : {}),
        class_name: className,
        start_time: startTime,
        end_time:   endTime,
        trainer_id: hlv,
        room_id:    roomId
    });
    try {
        const d = await apiPost(body);
        toast(d.message, d.success?'success':'error');
        if (d.success) {
            closeModal('scheduleModal'); loadStats(); renderCalendar();
            if (currentView==='list') loadList();
        }
    } catch(e) {
        toast('Lỗi kết nối server','error');
    }
}
async function deleteSchedule(id) {
    try {
        const d = await apiPost(fd({ action:'delete_schedule', id }));
        toast(d.message, d.success?'success':'error');
        if (d.success) { loadStats(); renderCalendar(); if (currentView==='list') loadList(); }
    } catch(e) {
        toast('Lỗi kết nối server','error');
    }
}

// ── DETAIL MODAL ──────────────────────────────────────────────────
async function openDetail(id) {
    currentDetailId = id;
    document.getElementById('detailContent').innerHTML = '<div class="loading-cell"><i class="fas fa-spinner fa-spin"></i></div>';
    openModal('detailModal');
    renderDetail(id);
}
async function renderDetail(id) {
    try {
        const d = await apiFetch(`get_schedule_detail&id=${id}`);
        if (!d.success) { toast(d.message,'error'); return; }
        const r = d.schedule, members = d.members;
        const dt    = r.start_time ? parseLocal(r.start_time) : null;
        const dtEnd = r.end_time   ? parseLocal(r.end_time)   : null;
        const isPast = dt && dt < new Date();
        const thuLabel = dt ? DAYS[(dt.getDay()+6)%7] : '—';
        const timeRange = dt
            ? (dtEnd ? `${fmtDatetime(r.start_time)} – ${fmtTime(r.end_time)}` : fmtDatetime(r.start_time))
            : '—';

        document.getElementById('detailTitle').innerHTML = `<i class="fas fa-circle-info" style="color:var(--gold);margin-right:8px"></i>${esc(r.class_name)}`;
        document.getElementById('detailContent').innerHTML = `
            <div class="det-hero">
                <div class="det-icon"><i class="fas fa-dumbbell"></i></div>
                <div style="flex:1">
                    <div class="det-name">${esc(r.class_name)}</div>
                    <div class="det-meta">
                        <span><i class="fas fa-calendar" style="color:var(--gold)"></i>${timeRange}</span>
                        <span><i class="fas fa-calendar-week" style="color:var(--blue)"></i>${thuLabel}</span>
                        ${r.trainer_name ? `<span><i class="fas fa-person-running" style="color:var(--orange)"></i>${esc(r.trainer_name)}</span>` : ''}
                        ${r.room_name ? `<span><i class="fas fa-door-open" style="color:var(--teal)"></i>${esc(r.room_name)}</span>` : ''}
                    </div>
                </div>
                ${!isPast ? `<button class="btn-icon" onclick="openEdit(${r.class_id})" title="Sửa" style="flex-shrink:0"><i class="fas fa-pen"></i></button>` : ''}
            </div>
            <div class="sec-title">
                <span class="sec-title-left"><i class="fas fa-users"></i> Người tham gia (${members.length})</span>
                <button class="btn-add-member" style="width:auto;margin:0;padding:6px 12px;font-size:11px" onclick="openRegModal(${r.class_id})">
                    <i class="fas fa-plus"></i> Thêm
                </button>
            </div>
            <div class="member-list">
                ${members.length ? members.map(m => `
                    <div class="member-row">
                        <div class="member-av">${avText(m.full_name)}</div>
                        <div style="flex:1;min-width:0">
                            <div class="member-name">${esc(m.full_name)}</div>
                            <div class="member-sdt">${m.phone||''}</div>
                        </div>
                        <button class="btn-icon-sm" onclick="removeReg(${m.class_registration_id},${id})" title="Hủy đăng ký"><i class="fas fa-times"></i></button>
                    </div>`).join('')
                : '<div class="no-members"><i class="fas fa-users-slash"></i>Chưa có người đăng ký</div>'}
            </div>`;
    } catch(e) {
        toast('Lỗi tải chi tiết','error');
    }
}
async function removeReg(regId, scheduleId) {
    try {
        const d = await apiPost(fd({ action:'delete_registration', id:regId }));
        toast(d.message, d.success?'success':'error');
        if (d.success) { loadStats(); renderCalendar(); renderDetail(scheduleId); if (currentView==='list') loadList(); }
    } catch(e) {
        toast('Lỗi kết nối server','error');
    }
}

// ── REGISTER MEMBER ───────────────────────────────────────────────
function openRegModal(lichId) {
    document.getElementById('fRegLichId').value  = lichId;
    document.getElementById('fRegSearch').value  = '';
    document.getElementById('fRegKhId').value    = '';
    document.getElementById('khDropdown').innerHTML = '';
    document.getElementById('khDropdown').classList.remove('show');
    document.getElementById('fRegSelected').style.display = 'none';
    openModal('regModal');
}
async function searchKH() {
    const q = document.getElementById('fRegSearch').value.trim();
    if (!q) { document.getElementById('khDropdown').classList.remove('show'); return; }
    try {
        const d = await apiFetch(`get_customers&search=${encodeURIComponent(q)}`);
        const dd = document.getElementById('khDropdown');
        if (!d.data?.length) { dd.innerHTML = '<div class="kh-item" style="color:var(--tm)">Không tìm thấy</div>'; dd.classList.add('show'); return; }
        dd.innerHTML = d.data.map(k => `
            <div class="kh-item" onclick="selectKH(${k.customer_id},'${esc(k.full_name)}','${esc(k.phone||'')}')">
                <div class="kh-item-name">${esc(k.full_name)}</div>
                <div class="kh-item-sdt">${k.phone||''}</div>
            </div>`).join('');
        dd.classList.add('show');
    } catch(e) {
        console.error('searchKH error:', e);
    }
}
function selectKH(id, name, sdt) {
    document.getElementById('fRegKhId').value   = id;
    document.getElementById('fRegSearch').value = name;
    document.getElementById('khDropdown').classList.remove('show');
    const sel = document.getElementById('fRegSelected');
    sel.style.display = 'flex';
    sel.innerHTML = `<i class="fas fa-circle-check"></i><span style="font-weight:600;font-size:13px">${esc(name)}</span><span style="font-size:12px;color:var(--tm);margin-left:4px">${esc(sdt)}</span>`;
}
async function saveRegistration() {
    const classId   = document.getElementById('fRegLichId').value;
    const customerId= document.getElementById('fRegKhId').value;
    if (!customerId) { toast('Vui lòng chọn khách hàng','warning'); return; }
    try {
        const d = await apiPost(fd({ action:'add_registration', class_id:classId, customer_id:customerId }));
        toast(d.message, d.success?'success':'error');
        if (d.success) {
            closeModal('regModal');
            if (currentDetailId) renderDetail(currentDetailId);
            loadStats(); renderCalendar(); if (currentView==='list') loadList();
        }
    } catch(e) {
        toast('Lỗi kết nối server','error');
    }
}

// ── PAGINATION ────────────────────────────────────────────────────
function renderPag(total, page, totalPages, piId, pcId, fn) {
    const from = total>0 ? (page-1)*LIMIT+1 : 0, to = Math.min(page*LIMIT, total);
    document.getElementById(piId).textContent = total>0 ? `Hiển thị ${from}–${to} / ${total}` : 'Không có dữ liệu';
    const ctrl = document.getElementById(pcId);
    if (totalPages<=1) { ctrl.innerHTML=''; return; }
    let h = `<button class="page-btn" onclick="(${fn.toString()})(${page-1})" ${page===1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i=1;i<=totalPages;i++) {
        if (totalPages>7 && Math.abs(i-page)>2 && i!==1 && i!==totalPages) {
            if (i===2||i===totalPages-1) h+=`<button class="page-btn" disabled>…</button>`;
            continue;
        }
        h+=`<button class="page-btn ${i===page?'active':''}" onclick="(${fn.toString()})(${i})">${i}</button>`;
    }
    h+=`<button class="page-btn" onclick="(${fn.toString()})(${page+1})" ${page===totalPages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
    ctrl.innerHTML=h;
}

// ── CONFIRM ───────────────────────────────────────────────────────
function confirmDel(msg, cb) {
    document.getElementById('confirmMsg').innerHTML = msg;
    document.getElementById('confirmOkBtn').onclick = () => { cb(); closeModal('confirmModal'); };
    openModal('confirmModal');
}

// ── UTILS ─────────────────────────────────────────────────────────
const openModal  = id => document.getElementById(id).classList.add('active');
const closeModal = id => document.getElementById(id).classList.remove('active');
const avText     = name => { const p=(name||'?').trim().split(' '); return p.length>=2?(p[0][0]+p[p.length-1][0]).toUpperCase():p[0].slice(0,2).toUpperCase(); };

async function apiFetch(actionOrParams) {
    let url;
    if (actionOrParams.startsWith('action=')) url = `${API}?${actionOrParams}`;
    else url = `${API}?action=${actionOrParams}`;
    const r = await fetch(url);
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    return r.json();
}
async function apiPost(body) {
    const r = await fetch(API, { method:'POST', body });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    return r.json();
}
const fd = obj => { const f=new FormData(); Object.entries(obj).forEach(([k,v])=>f.append(k,v??'')); return f; };

function toast(msg, type='info') {
    const icons={success:'fa-circle-check',error:'fa-circle-xmark',info:'fa-circle-info',warning:'fa-triangle-exclamation'};
    const el=Object.assign(document.createElement('div'),{
        className:`toast ${type}`,
        innerHTML:`<i class="fas ${icons[type]||icons.info}"></i><span>${msg}</span>`
    });
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(()=>{el.style.opacity='0';el.style.transition='opacity .4s';setTimeout(()=>el.remove(),400);},3500);
}
