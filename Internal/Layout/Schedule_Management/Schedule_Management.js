const API   = 'Schedule_Management_function.php';
const LIMIT = 15;
const DAYS  = ['Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy','Chủ Nhật'];
const DAYS_SHORT = ['T2','T3','T4','T5','T6','T7','CN'];
const MON   = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];

let currentView   = 'calendar';
let currentWeekStart = getThisMonday();
let listPage      = 1;
let trainers      = [];
let currentDetailId = null;

// ── INIT ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal-overlay').forEach(o =>
        o.addEventListener('click', e => { if (e.target===o) o.classList.remove('active'); }));

    // List filters
    document.getElementById('listSearch').addEventListener('input', debounce(() => loadList(1), 350));
    document.getElementById('listHlv').addEventListener('change', () => loadList(1));
    document.getElementById('listFrom').addEventListener('change', () => loadList(1));
    document.getElementById('listTo').addEventListener('change',   () => loadList(1));

    // KH search in reg modal
    document.getElementById('fRegSearch').addEventListener('input', debounce(searchKH, 300));

    loadStats();
    loadTrainers();
    renderCalendar();
});

const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
const esc = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const pad = n => String(n).padStart(2,'0');

function fmtDatetime(s) {
    if (!s||s==='0000-00-00 00:00:00') return '—';
    const d = new Date(s);
    return `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
function fmtTime(s) {
    if (!s) return '—';
    const d = new Date(s);
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
function getThisMonday(offset=0) {
    const d = new Date();
    d.setDate(d.getDate() - ((d.getDay()+6)%7) + offset*7);
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
function getDayOfWeek(dateStr) {
    const d = new Date(dateStr); return (d.getDay()+6)%7; // 0=Mon, 6=Sun
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
    const d = await apiFetch('get_stats');
    document.getElementById('sTotal').textContent   = d.total    ??0;
    document.getElementById('sToday').textContent   = d.today    ??0;
    document.getElementById('sWeek').textContent    = d.this_week??0;
    document.getElementById('sDangKy').textContent  = d.dang_ky  ??0;
    document.getElementById('sHlv').textContent     = d.hlv      ??0;
    document.getElementById('sSapDien').textContent = d.sap_dien ??0;
}

// ── TRAINERS ──────────────────────────────────────────────────────
async function loadTrainers() {
    const d = await apiFetch('get_trainers');
    trainers = d.data || [];
    // Populate dropdowns
    const opts = trainers.map(t => `<option value="${t.employee_id}">${esc(t.full_name)}</option>`).join('');
    document.getElementById('fSchHlv').innerHTML  = '<option value="">— Không chỉ định —</option>' + opts;
    document.getElementById('listHlv').innerHTML  = '<option value="">Tất cả HLV</option>' + opts;
}

// ── CALENDAR ──────────────────────────────────────────────────────
async function renderCalendar() {
    document.getElementById('calWeekTitle').textContent = formatWeekTitle(currentWeekStart);
    const d = await apiFetch(`get_week_schedules&week_start=${currentWeekStart}`);
    const events = d.data || [];
    const today  = new Date().toISOString().slice(0,10);
    const now    = new Date();

    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';

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
        const dateStr    = addDays(currentWeekStart, i);
        const isToday    = dateStr === today;
        const dayEvents  = events.filter(e => e.class_time && e.class_time.slice(0,10) === dateStr);

        const col = document.createElement('div');
        col.className = 'cal-day-col' + (isToday ? ' today' : '');

        dayEvents.forEach(e => {
            const evDate  = new Date(e.class_time);
            const isPast  = evDate < now;
            const isSoon  = !isPast && (evDate - now) < 2*3600*1000;
            const cls     = isPast ? 'past' : isSoon ? 'soon' : 'upcoming';
            const timeCls = isPast ? 'past-time' : isSoon ? 'soon-time' : '';
            const hlvName = e.trainer_name ? esc(e.trainer_name) : '';

            const card = document.createElement('div');
            card.className = `cal-event ${cls}`;
            card.innerHTML = `
                <div class="cal-event-time ${timeCls}">
                    <i class="fas fa-clock" style="font-size:10px"></i>${fmtTime(e.class_time)}
                </div>
                <div class="cal-event-name">${esc(e.class_name)}</div>
                ${hlvName ? `<div class="cal-event-hlv"><i class="fas fa-person-running" style="font-size:10px"></i>${hlvName}</div>` : ''}
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
    currentWeekStart = getThisMonday(
        Math.round((new Date(currentWeekStart) - new Date(getThisMonday())) / (7*86400*1000)) + dir
    );
    // Simple calculation
    const d = new Date(currentWeekStart);
    d.setDate(d.getDate() + dir*7);
    currentWeekStart = d.toISOString().slice(0,10);
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
    const from   = document.getElementById('listFrom').value;
    const to     = document.getElementById('listTo').value;
    const params = new URLSearchParams({ action:'get_schedules', page, limit:LIMIT, search, hlv_id, from, to });

    document.getElementById('listTbody').innerHTML = `<tr><td colspan="7" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>`;
    const d = await apiFetch(params.toString());
    renderListTable(d.data || []);
    renderPag(d.total, page, d.totalPages, 'listPagInfo', 'listPagCtrl', loadList);
    document.getElementById('listMeta').textContent = `${d.total} buổi tập`;
}

function getTimeBadge(dtStr) {
    if (!dtStr) return '—';
    const dt  = new Date(dtStr);
    const now = new Date();
    const diff= dt - now;
    const time = `${pad(dt.getDate())}/${pad(dt.getMonth()+1)} ${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
    if (diff < 0) return `<span class="time-badge tb-past"><i class="fas fa-check"></i> ${time}</span>`;
    if (diff < 3600000) return `<span class="time-badge tb-now"><i class="fas fa-fire"></i> ${time}</span>`;
    if (diff < 86400000) return `<span class="time-badge tb-soon"><i class="fas fa-clock"></i> ${time}</span>`;
    return `<span class="time-badge tb-future"><i class="fas fa-calendar"></i> ${time}</span>`;
}

function renderListTable(rows) {
    const tb = document.getElementById('listTbody');
    if (!rows.length) { tb.innerHTML = `<tr><td colspan="7"><div class="empty-state"><i class="fas fa-calendar-xmark"></i>Không có buổi tập nào</div></td></tr>`; return; }
    const thus = ['','Thứ 2','Thứ 3','Thứ 4','Thứ 5','Thứ 6','Thứ 7','CN'];
    tb.innerHTML = rows.map(r => {
        const d     = r.class_time ? new Date(r.class_time) : null;
        const thu   = d ? thus[d.getDay()||7] : '—';
        const hlv   = r.trainer_name
            ? `<div class="hlv-cell"><div class="hlv-av">${r.trainer_name.split(' ').pop().slice(0,2).toUpperCase()}</div><span style="font-size:13px;color:var(--ts)">${esc(r.trainer_name)}</span></div>`
            : `<span style="color:var(--tm)">—</span>`;
        return `<tr>
            <td class="id-cell">#${r.class_id}</td>
            <td><span class="col-primary">${esc(r.class_name)}</span></td>
            <td>${getTimeBadge(r.class_time)}</td>
            <td><span class="thu-badge">${thu}</span></td>
            <td>${hlv}</td>
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
    document.getElementById('fSchId').value   = '';
    document.getElementById('fSchTen').value  = '';
    document.getElementById('fSchHlv').value  = '';
    if (defaultDate) {
        document.getElementById('fSchTime').value = defaultDate + 'T08:00';
    } else {
        document.getElementById('fSchTime').value = '';
    }
    document.getElementById('schModalTitle').innerHTML = `<i class="fas fa-calendar-plus" style="color:var(--gold);margin-right:8px"></i>Thêm buổi tập`;
    openModal('scheduleModal');
}
async function openEdit(id) {
    const d = await apiFetch(`get_schedule_detail&id=${id}`);
    if (!d.success) { toast(d.message,'error'); return; }
    const r = d.schedule;
    document.getElementById('fSchId').value   = r.class_id;
    document.getElementById('fSchTen').value  = r.class_name||'';
    document.getElementById('fSchHlv').value  = r.trainer_id||'';
    // Convert datetime to datetime-local format
    const dt = r.class_time ? r.class_time.slice(0,16) : '';
    document.getElementById('fSchTime').value = dt;
    document.getElementById('schModalTitle').innerHTML = `<i class="fas fa-pen" style="color:var(--gold);margin-right:8px"></i>Sửa buổi tập`;
    openModal('scheduleModal');
}
async function saveSchedule() {
    const id   = document.getElementById('fSchId').value;
    const className  = document.getElementById('fSchTen').value.trim();
    const time = document.getElementById('fSchTime').value;
    const hlv  = document.getElementById('fSchHlv').value;
    if (!className||!time) { toast('Vui lòng nhập tên lớp và thời gian','warning'); return; }
    const body = fd({ action: id ? 'update_schedule' : 'add_schedule',
        ...(id ? {id} : {}), class_name:className, class_time:time, trainer_id:hlv });
    const d = await apiPost(body);
    toast(d.message, d.success?'success':'error');
    if (d.success) {
        closeModal('scheduleModal'); loadStats(); renderCalendar();
        if (currentView==='list') loadList();
    }
}
async function deleteSchedule(id) {
    const d = await apiPost(fd({ action:'delete_schedule', id }));
    toast(d.message, d.success?'success':'error');
    if (d.success) { loadStats(); renderCalendar(); if (currentView==='list') loadList(); }
}

// ── DETAIL MODAL ──────────────────────────────────────────────────
async function openDetail(id) {
    currentDetailId = id;
    document.getElementById('detailContent').innerHTML = '<div class="loading-cell"><i class="fas fa-spinner fa-spin"></i></div>';
    openModal('detailModal');
    renderDetail(id);
}
async function renderDetail(id) {
    const d = await apiFetch(`get_schedule_detail&id=${id}`);
    if (!d.success) { toast(d.message,'error'); return; }
    const r = d.schedule, members = d.members;
    const dt = r.class_time ? new Date(r.class_time) : null;
    const isPast = dt && dt < new Date();
    const thusMap = ['CN','T2','T3','T4','T5','T6','T7'];
    const thuLabel = dt ? DAYS[(dt.getDay()+6)%7] : '—';
    document.getElementById('detailTitle').innerHTML = `<i class="fas fa-circle-info" style="color:var(--gold);margin-right:8px"></i>${esc(r.class_name)}`;
    document.getElementById('detailContent').innerHTML = `
        <div class="det-hero">
            <div class="det-icon"><i class="fas fa-dumbbell"></i></div>
            <div style="flex:1">
                <div class="det-name">${esc(r.class_name)}</div>
                <div class="det-meta">
                    <span><i class="fas fa-calendar" style="color:var(--gold)"></i>${fmtDatetime(r.class_time)}</span>
                    <span><i class="fas fa-calendar-week" style="color:var(--blue)"></i>${thuLabel}</span>
                    ${r.trainer_name ? `<span><i class="fas fa-person-running" style="color:var(--orange)"></i>${esc(r.trainer_name)}</span>` : ''}
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
}
async function removeReg(regId, scheduleId) {
    const d = await apiPost(fd({ action:'delete_registration', id:regId }));
    toast(d.message, d.success?'success':'error');
    if (d.success) { loadStats(); renderCalendar(); renderDetail(scheduleId); if (currentView==='list') loadList(); }
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
    const d = await apiFetch(`get_customers&search=${encodeURIComponent(q)}`);
    const dd = document.getElementById('khDropdown');
    if (!d.data?.length) { dd.innerHTML = '<div class="kh-item" style="color:var(--tm)">Không tìm thấy</div>'; dd.classList.add('show'); return; }
    dd.innerHTML = d.data.map(k => `
        <div class="kh-item" onclick="selectKH(${k.customer_id},'${esc(k.full_name)}','${esc(k.phone||'')}')">
            <div class="kh-item-name">${esc(k.full_name)}</div>
            <div class="kh-item-sdt">${k.phone||''}</div>
        </div>`).join('');
    dd.classList.add('show');
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
    const classId  = document.getElementById('fRegLichId').value;
    const customerId= document.getElementById('fRegKhId').value;
    if (!customerId) { toast('Vui lòng chọn khách hàng','warning'); return; }
    const d = await apiPost(fd({ action:'add_registration', class_id:classId, customer_id:customerId }));
    toast(d.message, d.success?'success':'error');
    if (d.success) {
        closeModal('regModal');
        if (currentDetailId) renderDetail(currentDetailId);
        loadStats(); renderCalendar(); if (currentView==='list') loadList();
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
    else if (actionOrParams.includes('='))     url = `${API}?action=${actionOrParams}`;
    else                                        url = `${API}?action=${actionOrParams}`;
    const r = await fetch(url); return r.json();
}
async function apiPost(body) { const r = await fetch(API,{method:'POST',body}); return r.json(); }
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
