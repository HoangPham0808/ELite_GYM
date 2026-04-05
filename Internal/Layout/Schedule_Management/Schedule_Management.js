/* ──────────────────────────────────────────────────────────────
   Schedule_Management.js  –  Elite Gym
   Features:
   - Repeat: none / daily (rest of week) / weekly (same day each month)
   - Role-based UI: admin = full CRUD, trainer = view + register only
   - Package type highlight on calendar events
   - Date suffix appended to class_name for generated occurrences
   ────────────────────────────────────────────────────────────── */

/* ── EARLY ROLE ENFORCEMENT (runs before DOM ready) ─────────────
   Immediately hide admin-only DOM elements injected by PHP before
   the page finishes painting — prevents flash of admin controls.
   This runs synchronously at script-parse time.
─────────────────────────────────────────────────────────────── */
(function enforceRoleEarly() {
    const role = (typeof window.USER_ROLE !== 'undefined') ? window.USER_ROLE : 'admin';
    if (role === 'trainer') {
        /* Inject a <style> that hides admin elements instantly */
        const s = document.createElement('style');
        s.id = 'trainer-role-hide';
        s.textContent = [
            '.view-controls .btn-primary { display:none !important; }',
            '#scheduleModal { display:none !important; }',
            '.cal-add-btn { display:none !important; }'
        ].join('\n');
        (document.head || document.documentElement).appendChild(s);
    }
})();

const API   = 'Schedule_Management_function.php';
const LIMIT = 15;
const DAYS  = ['Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy','Chủ Nhật'];
const DAYS_SHORT = ['T2','T3','T4','T5','T6','T7','CN'];

/* Role injected by PHP via Schedule_Management.php inline <script>.
   Read from window object to avoid const redeclaration errors when
   the outer layout (HLV.js / adm.js) also declares USER_ROLE. */
const IS_ADMIN      = (window.USER_ROLE  !== undefined) ? window.USER_ROLE  === 'admin' : true;
const MY_TRAINER_ID = (window.TRAINER_ID !== undefined) ? (window.TRAINER_ID  || 0)     : 0;

/* Package type → CSS class mapping (matches PackageType.type_name) */
const PKG_CLASS = {
    'Basic'   :'pkg-basic',
    'Standard':'pkg-standard',
    'Premium' :'pkg-premium',
    'VIP'     :'pkg-vip',
    'Student' :'pkg-student',
};

/* ── UTILS — khai báo sớm để mọi hàm bên dưới dùng được ────────── */
const openModal  = id => document.getElementById(id).classList.add('active');
const closeModal = id => document.getElementById(id).classList.remove('active');
const avText     = name => { const p=(name||'?').trim().split(' '); return p.length>=2?(p[0][0]+p[p.length-1][0]).toUpperCase():p[0].slice(0,2).toUpperCase(); };
const fd         = obj => { const f=new FormData(); Object.entries(obj).forEach(([k,v])=>f.append(k,v??'')); return f; };

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
function toast(msg, type='info') {
    const icons={success:'fa-circle-check',error:'fa-circle-xmark',info:'fa-circle-info',warning:'fa-triangle-exclamation'};
    const el=Object.assign(document.createElement('div'),{
        className:`toast ${type}`,
        innerHTML:`<i class="fas ${icons[type]||icons.info}"></i><span>${msg}</span>`
    });
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(()=>{el.style.opacity='0';el.style.transition='opacity .4s';setTimeout(()=>el.remove(),400);},3500);
}
/* ──────────────────────────────────────────────────────────────── */

/* ── DATE HELPERS (khai báo trước let để tránh Temporal Dead Zone) ──
   Dùng inline padStart thay vì const pad để không phụ thuộc vào
   các const được khai báo sau trong file.
──────────────────────────────────────────────────────────────────── */
/* Trả về YYYY-MM-DD theo giờ local, tránh lệch UTC+7 */
function toLocalDateStr(d) {
    const p2 = n => String(n).padStart(2,'0');
    return `${d.getFullYear()}-${p2(d.getMonth()+1)}-${p2(d.getDate())}`;
}
/* Thứ Hai của tuần hiện tại (local) */
function getThisMonday() {
    const d = new Date();
    d.setDate(d.getDate() - ((d.getDay()+6)%7));
    d.setHours(0,0,0,0);
    return toLocalDateStr(d);
}
/* Cộng n ngày vào chuỗi YYYY-MM-DD, trả về YYYY-MM-DD (local) */
function addDays(dateStr, n) {
    const [y,m,day] = dateStr.split('-').map(Number);
    const d = new Date(y, m-1, day);
    d.setDate(d.getDate()+n);
    return toLocalDateStr(d);
}

let currentView      = 'calendar';
let calMode          = 'week';          // 'week' | 'room'
let currentWeekStart = getThisMonday();
let currentRoomDate  = toLocalDateStr(new Date());
let listPage         = 1;
let trainers         = [];
let rooms            = [];
let currentDetailId  = null;

// ── INIT ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    /* ── ROLE-BASED UI ENFORCEMENT ──────────────────────────────────
       Hide ALL admin-only elements when logged in as trainer.
       This is a client-side safety net on top of PHP's server-side guard.
    ─────────────────────────────────────────────────────────────── */
    if (!IS_ADMIN) {
        /* Hide "Thêm buổi tập" button in view-controls */
        document.querySelectorAll('.view-controls .btn-primary').forEach(el => el.style.display = 'none');
        /* Ensure modal is inaccessible */
        const schModal = document.getElementById('scheduleModal');
        if (schModal) schModal.remove();
    }

    document.querySelectorAll('.modal-overlay').forEach(o =>
        o.addEventListener('click', e => { if (e.target===o) o.classList.remove('active'); }));

    document.getElementById('listSearch').addEventListener('input', debounce(() => loadList(1), 350));
    document.getElementById('listHlv').addEventListener('change',  () => loadList(1));
    document.getElementById('listRoom').addEventListener('change', () => loadList(1));
    document.getElementById('listFrom').addEventListener('change', () => loadList(1));
    document.getElementById('listTo').addEventListener('change',   () => loadList(1));
    const regSearchEl = document.getElementById('fRegSearch');
    if (regSearchEl) regSearchEl.addEventListener('input', debounce(searchKH, 300));

    // Repeat option click handler — only exists for admin (scheduleModal present)
    document.querySelectorAll('.repeat-opt').forEach(opt => {
        opt.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            document.querySelectorAll('.repeat-opt').forEach(o => o.classList.remove('active'));
            opt.classList.add('active');
            const radio = opt.querySelector('input[type=radio]');
            if (radio) radio.checked = true;
            updateRepeatNote();
        });
        /* Silence every event on the radio itself so nothing leaks to the framework */
        const radio = opt.querySelector('input[type=radio]');
        if (radio) {
            ['click','change','mousedown','mouseup','focus','blur'].forEach(ev =>
                radio.addEventListener(ev, e => {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                })
            );
        }
    });

    loadStats();
    loadTrainers();
    loadRooms();
    renderCalendar();
});

const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
const esc = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const pad = n => String(n).padStart(2,'0');

/* Bỏ suffix " - DD/MM/YYYY HH:MM..." do buildOccurrences tạo ra khi lặp,
   chỉ giữ baseName để hiển thị gọn trên card lịch */
function stripDateSuffix(name) {
    if (!name) return '';
    return name.replace(/\s*-\s*\d{2}\/\d{2}\/\d{4}.*$/, '').trim();
}

function parseLocal(s) {
    if (!s || s === '0000-00-00 00:00:00') return null;
    return new Date(s.replace(' ', 'T'));
}
function fmtDatetime(s) {
    const d = parseLocal(s); if (!d) return '—';
    return `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
function fmtTime(s) {
    const d = parseLocal(s); if (!d) return '—';
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
function fmtDateShort(dateStr) {
    const d = new Date(dateStr);
    return `${pad(d.getDate())}/${pad(d.getMonth()+1)}`;
}
function formatWeekTitle(ws) {
    const we = addDays(ws, 6);
    const [y1,m1,d1n] = ws.split('-').map(Number);
    const [y2,m2,d2n] = we.split('-').map(Number);
    if (m1 === m2)
        return `${d1n} – ${d2n} tháng ${m1}, ${y1}`;
    return `${d1n}/${m1} – ${d2n}/${m2}/${y2}`;
}

/* Get package CSS class from room's package_type_name */
function pkgClass(typeName) {
    if (!typeName) return '';
    const k = Object.keys(PKG_CLASS).find(k => typeName.toLowerCase().includes(k.toLowerCase()));
    return k ? PKG_CLASS[k] : '';
}

/* Package badge HTML for table */
function pkgBadge(typeName) {
    const cls = pkgClass(typeName);
    if (!cls) return '';
    const label = typeName || '';
    return `<span class="pkg-badge pb-${cls.replace('pkg-','')}">${esc(label)}</span>`;
}

// ── VIEW SWITCH ───────────────────────────────────────────────────
function switchView(v) {
    currentView = v;
    document.getElementById('view-calendar').style.display = v==='calendar' ? 'block' : 'none';
    document.getElementById('view-room') && (document.getElementById('view-room').style.display = 'none');
    document.getElementById('view-list').style.display     = v==='list'     ? 'block' : 'none';
    document.getElementById('btnCalView').classList.toggle('active', v==='calendar');
    const btnRoom = document.getElementById('btnRoomView');
    if (btnRoom) btnRoom.classList.remove('active');
    document.getElementById('btnListView').classList.toggle('active', v==='list');
    if (v==='list') loadList();
}

// ── CAL MODE: week ↔ room ─────────────────────────────────────────
function switchCalMode(mode) {
    calMode = mode;
    document.getElementById('btnSubWeek').classList.toggle('active', mode==='week');
    document.getElementById('btnSubRoom').classList.toggle('active', mode==='room');
    document.getElementById('weekGridWrap').style.display  = mode==='week' ? 'block' : 'none';
    document.getElementById('roomGridWrap').style.display  = mode==='room' ? 'block' : 'none';
    document.getElementById('pkgLegend').style.display     = mode==='week' ? '' : 'none';
    document.getElementById('roomDayTabs').style.display   = mode==='room' ? '' : 'none';
    if (mode==='week') {
        document.getElementById('calWeekTitle').textContent = formatWeekTitle(currentWeekStart);
        renderCalendar();
    } else {
        renderRoomDayTabs();
        renderRoomColGrid();
    }
}

// Unified nav for both modes
function calNavPrev() { if (calMode==='week') { navWeek(-1); } else { navRoomWeek(-1); } }
function calNavNext() { if (calMode==='week') { navWeek(1);  } else { navRoomWeek(1);  } }
function calGoToday() { if (calMode==='week') { goToday();   } else { goRoomToday();   } }

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
    } catch(e) { console.error('loadStats error:', e); }
}

// ── TRAINERS ──────────────────────────────────────────────────────
async function loadTrainers() {
    try {
        const d = await apiFetch('get_trainers');
        trainers = d.data || [];
        const opts = trainers.map(t => `<option value="${t.employee_id}">${esc(t.full_name)}</option>`).join('');
        if (IS_ADMIN) document.getElementById('fSchHlv').innerHTML = '<option value="">— Không chỉ định —</option>' + opts;
        document.getElementById('listHlv').innerHTML = '<option value="">Tất cả HLV</option>' + opts;
    } catch(e) { console.error('loadTrainers error:', e); }
}

// ── ROOMS ─────────────────────────────────────────────────────────
async function loadRooms() {
    try {
        const d = await apiFetch('get_rooms');
        rooms = d.data || [];
        const opts = rooms.map(r => `<option value="${r.room_id}">${esc(r.room_name)}</option>`).join('');
        if (IS_ADMIN) document.getElementById('fSchRoom').innerHTML = '<option value="">— Không chỉ định —</option>' + opts;
        document.getElementById('listRoom').innerHTML = '<option value="">Tất cả phòng</option>' + opts;
    } catch(e) { console.error('loadRooms error:', e); }
}

// ── CALENDAR ──────────────────────────────────────────────────────
/* ── TIME GRID CONFIG ────────────────────────────────────────────
   Trục hoành = 7 ngày trong tuần, trục dọc = giờ (06:00 → 22:00).
   Mỗi giờ = HOUR_H px. Event được định vị bằng top/height tuyệt đối.
────────────────────────────────────────────────────────────────── */
const TG_START = 6;
const TG_END   = 22;
const TG_HOURS = TG_END - TG_START;
const HOUR_H   = 60; // px / giờ

function timeToOffset(dateStr) {
    const d = parseLocal(dateStr); if (!d) return 0;
    const h = d.getHours() + d.getMinutes() / 60;
    return Math.max(0, (h - TG_START) * HOUR_H);
}
function durationToPx(startStr, endStr) {
    const s = parseLocal(startStr);
    const e = endStr ? parseLocal(endStr) : null;
    if (!s || !e) return HOUR_H;
    return Math.max(HOUR_H * 0.45, (e - s) / 3600000 * HOUR_H);
}

/* ── COLLISION LAYOUT ────────────────────────────────────────────
   Nhận mảng events, trả về mảng mới với _col và _cols để chia cột con
   khi các events trùng giờ trong cùng 1 cột ngày.
   Dùng greedy lane assignment: sắp xếp theo start_time, gán vào lane trống sớm nhất.
─────────────────────────────────────────────────────────────── */
function layoutOverlap(events) {
    if (!events.length) return events;
    const sorted = [...events].sort((a, b) => {
        const sa = parseLocal(a.start_time), sb = parseLocal(b.start_time);
        return (sa ? sa.getTime() : 0) - (sb ? sb.getTime() : 0);
    });
    const lanes = []; // lanes[i] = endMs của event cuối trong lane i
    const tagged = sorted.map(ev => {
        const s = parseLocal(ev.start_time);
        const e = ev.end_time ? parseLocal(ev.end_time) : null;
        const sMs = s ? s.getTime() : 0;
        const eMs = e ? e.getTime() : sMs + 3600000;
        let lane = lanes.findIndex(lEnd => lEnd <= sMs);
        if (lane === -1) lane = lanes.length;
        lanes[lane] = eMs;
        return { ...ev, _lane: lane };
    });
    const totalCols = lanes.length;
    return tagged.map(ev => ({ ...ev, _col: ev._lane, _cols: totalCols }));
}

async function renderCalendar() {
    document.getElementById('calWeekTitle').textContent = formatWeekTitle(currentWeekStart);
    const wrap = document.getElementById('calendarGrid');
    wrap.innerHTML = '';

    let events = [];
    try {
        const d = await apiFetch(`get_week_schedules&week_start=${currentWeekStart}`);
        events = d.data || [];
    } catch(e) { console.error('renderCalendar error:', e); }

    const today  = toLocalDateStr(new Date());
    const now    = new Date();
    const totalH = TG_HOURS * HOUR_H;

    /* ── Tạo layout: header cố định + body cuộn ── */
    const outer = document.createElement('div');
    outer.className = 'tg-outer';

    /* ── Hàng header ngày (sticky top) ── */
    const hdrRow = document.createElement('div');
    hdrRow.className = 'tg-header-row';
    hdrRow.innerHTML = `<div class="tg-corner"></div>`;
    for (let i = 0; i < 7; i++) {
        const dateStr = addDays(currentWeekStart, i);
        const [,, dd] = dateStr.split('-').map(Number);
        const isToday = dateStr === today;
        const cell = document.createElement('div');
        cell.className = 'tg-day-header' + (isToday ? ' tg-day-header-today' : '');
        cell.innerHTML = `<span class="tg-day-name">${DAYS_SHORT[i]}</span><span class="tg-day-num">${dd}</span>`;
        hdrRow.appendChild(cell);
    }
    outer.appendChild(hdrRow);

    /* ── Body: trục giờ bên trái + 7 cột ngày ── */
    const body = document.createElement('div');
    body.className = 'tg-body';

    /* Cột giờ trái */
    const timeCol = document.createElement('div');
    timeCol.className = 'tg-time-col';
    timeCol.style.height = totalH + 'px';
    for (let h = TG_START; h <= TG_END; h++) {
        const tick = document.createElement('div');
        tick.className = 'tg-hour-tick';
        tick.style.top = ((h - TG_START) * HOUR_H) + 'px';
        tick.textContent = `${pad(h)}:00`;
        timeCol.appendChild(tick);
    }
    body.appendChild(timeCol);

    /* 7 cột ngày */
    for (let i = 0; i < 7; i++) {
        const dateStr   = addDays(currentWeekStart, i);
        const isToday   = dateStr === today;
        const dayEvents = events.filter(e => e.start_time && e.start_time.slice(0,10) === dateStr);

        const col = document.createElement('div');
        col.className = 'tg-col' + (isToday ? ' tg-today' : '');
        col.style.height = totalH + 'px';

        /* Lưới ngang theo giờ */
        for (let h = TG_START; h < TG_END; h++) {
            const gl = document.createElement('div');
            gl.className = 'tg-grid-line' + (h % 2 === 0 ? ' tg-grid-even' : '');
            gl.style.top = ((h - TG_START) * HOUR_H) + 'px';
            col.appendChild(gl);
        }

        /* Đường "bây giờ" nếu là hôm nay */
        if (isToday) {
            const nowH = now.getHours() + now.getMinutes() / 60;
            if (nowH >= TG_START && nowH <= TG_END) {
                const nl = document.createElement('div');
                nl.className = 'tg-now-line';
                nl.style.top = ((nowH - TG_START) * HOUR_H) + 'px';
                col.appendChild(nl);
            }
        }

        /* Click vào vùng trống → thêm lịch (admin) */
        if (IS_ADMIN) {
            col.addEventListener('click', ev => {
                if (ev.target === col || ev.target.classList.contains('tg-grid-line') || ev.target.classList.contains('tg-empty'))
                    openAddModal(dateStr);
            });
        }

        /* Nội dung trống */
        if (!dayEvents.length) {
            const emp = document.createElement('div');
            emp.className = 'tg-empty';
            emp.textContent = 'Trống';
            col.appendChild(emp);
        }

        /* Events — dùng layoutOverlap để chia cột con khi trùng giờ */
        const laidOut = layoutOverlap(dayEvents);
        laidOut.forEach(e => {
            const evDate  = parseLocal(e.start_time);
            const evEnd   = e.end_time ? parseLocal(e.end_time) : null;
            const isPast  = evDate < now;
            const isSoon  = !isPast && (evDate - now) < 2*3600*1000;
            const cls     = isPast ? 'past' : isSoon ? 'soon' : 'upcoming';
            const timeCls = isPast ? 'past-time' : isSoon ? 'soon-time' : '';
            const pCls    = pkgClass(e.package_type_name || '');

            const top    = timeToOffset(e.start_time);
            const height = durationToPx(e.start_time, e.end_time);
            const timeLabel = evEnd
                ? `${fmtTime(e.start_time)}–${fmtTime(e.end_time)}`
                : fmtTime(e.start_time);

            const pTagHtml = pCls
                ? `<span class="pkg-tag ${pCls}">${esc(e.package_type_name || '')}</span>` : '';

            const _cardTid = MY_TRAINER_ID || (window._DBG && window._DBG.empId ? window._DBG.empId : 0);
            let trainerCardBtn = '';
            if (!IS_ADMIN && !isPast) {
                if (_cardTid && parseInt(e.trainer_id) === _cardTid)
                    trainerCardBtn = `<button class="cal-trainer-btn cal-trainer-unregister" data-id="${e.class_id}"><i class="fas fa-user-minus"></i> Hủy</button>`;
                else if (!e.trainer_id)
                    trainerCardBtn = `<button class="cal-trainer-btn cal-trainer-register" data-id="${e.class_id}"><i class="fas fa-hand-raised"></i> Đăng ký</button>`;
            }

            /* Tính left/width theo cột con (_col, _cols) */
            const GAP    = 3;  // px gap giữa các card
            const MARGIN = 3;  // px từ mép cột
            const cols   = e._cols || 1;
            const colIdx = e._col  || 0;
            const totalW = 100; // % của cột
            const cardW  = (totalW - MARGIN * 2 - GAP * (cols - 1)) / cols;
            const leftPct = MARGIN + colIdx * (cardW + GAP);

            const card = document.createElement('div');
            const isMeAssigned = !IS_ADMIN && MY_TRAINER_ID && parseInt(e.trainer_id) === MY_TRAINER_ID;
            card.className = `tg-event ${cls}${pCls ? ' '+pCls : ''}${isMeAssigned ? ' trainer-assigned-me' : ''}`;
            card.style.top    = top + 'px';
            card.style.height = height + 'px';
            card.style.left   = leftPct + '%';
            card.style.right  = 'auto';
            card.style.width  = cardW + '%';
            card.innerHTML = `
                <div class="tg-event-time ${timeCls}">${timeLabel}</div>
                <div class="tg-event-name">${esc(stripDateSuffix(e.class_name))}</div>
                ${e.room_name    ? `<div class="tg-event-sub"><i class="fas fa-door-open"></i>${esc(e.room_name)}</div>`    : ''}
                ${e.trainer_name ? `<div class="tg-event-sub"><i class="fas fa-person-running"></i>${esc(e.trainer_name)}</div>` : ''}
                ${pTagHtml}
                ${trainerCardBtn}
                ${e.registration_count > 0 ? `<div class="tg-event-count">${e.registration_count}</div>` : ''}
            `;
            card.onclick = () => openDetail(e.class_id);

            if (trainerCardBtn) {
                const btn = card.querySelector('.cal-trainer-btn');
                if (btn) btn.addEventListener('click', async ev => {
                    ev.stopPropagation();
                    const cid = parseInt(btn.dataset.id);
                    if (btn.classList.contains('cal-trainer-register')) await assignTrainer(cid);
                    else await unassignTrainer(cid);
                });
            }
            col.appendChild(card);
        });

        /* Nút + Thêm nhỏ ở góc trên (admin) */
        if (IS_ADMIN) {
            const addBtn = document.createElement('button');
            addBtn.className = 'tg-add-btn';
            addBtn.title = `Thêm lịch ${dateStr}`;
            addBtn.innerHTML = '<i class="fas fa-plus"></i>';
            addBtn.onclick = ev => { ev.stopPropagation(); openAddModal(dateStr); };
            col.appendChild(addBtn);
        }

        body.appendChild(col);
    }

    outer.appendChild(body);
    wrap.appendChild(outer);
}

function navWeek(dir) {
    const [y,m,day] = currentWeekStart.split('-').map(Number);
    const d = new Date(y, m-1, day);
    d.setDate(d.getDate() + dir * 7);
    currentWeekStart = toLocalDateStr(d);
    renderCalendar();
}
function goToday() { currentWeekStart = getThisMonday(); renderCalendar(); }

// ── ROOM COL GRID (mode = room, inside calendar view) ────────────
function renderRoomDayTabs() {
    const tabs = document.getElementById('roomDayTabs');
    const today = toLocalDateStr(new Date());
    const weekEnd = addDays(currentWeekStart, 6);
    if (currentRoomDate < currentWeekStart || currentRoomDate > weekEnd) {
        currentRoomDate = today >= currentWeekStart && today <= weekEnd ? today : currentWeekStart;
    }
    document.getElementById('calWeekTitle').textContent = formatWeekTitle(currentWeekStart);
    let html = '';
    for (let i = 0; i < 7; i++) {
        const dateStr = addDays(currentWeekStart, i);
        const d = new Date(dateStr);
        const isToday  = dateStr === today;
        const isActive = dateStr === currentRoomDate;
        html += `<button class="room-day-tab${isActive?' rdt-active':''}${isToday?' rdt-today':''}"
            onclick="selectRoomDay('${dateStr}')">
            <span class="rdt-name">${DAYS_SHORT[i]}</span>
            <span class="rdt-num">${d.getDate()}</span>
        </button>`;
    }
    tabs.innerHTML = html;
}

async function renderRoomColGrid() {
    const grid = document.getElementById('roomColGrid');
    grid.innerHTML = `<div class="rcg-loading"><i class="fas fa-spinner fa-spin"></i></div>`;

    let events = [];
    try {
        const d = await apiFetch(`get_week_schedules&week_start=${currentWeekStart}`);
        events = d.data || [];
    } catch(e) { console.error('renderRoomColGrid error:', e); }

    const dayEvents = events.filter(e => e.start_time && e.start_time.slice(0,10) === currentRoomDate);
    const now = new Date();

    if (!rooms.length) {
        grid.innerHTML = `<div class="rcg-loading" style="color:var(--tm);flex-direction:column;gap:10px"><i class="fas fa-door-open" style="font-size:32px;color:var(--gold-dim)"></i>Không có phòng tập</div>`;
        return;
    }

    /* ── Time grid config ── */
    const RCG_START = 6, RCG_END = 22;
    const RCG_HOURS = RCG_END - RCG_START;
    const HOUR_PX   = 64; // px per hour
    const totalH    = RCG_HOURS * HOUR_PX;
    const TIME_COL  = 52; // px — width of time axis
    const COL_MIN   = 180; // px — min width per room
    const numRooms  = rooms.length;

    function tTop(dateStr) {
        const d = parseLocal(dateStr); if (!d) return 0;
        const h = d.getHours() + d.getMinutes() / 60;
        return Math.max(0, (h - RCG_START) * HOUR_PX);
    }
    function tHeight(startStr, endStr) {
        const s = parseLocal(startStr), e = endStr ? parseLocal(endStr) : null;
        if (!s || !e) return HOUR_PX;
        return Math.max(HOUR_PX * 0.5, (e - s) / 3600000 * HOUR_PX);
    }

    /* ── Build outer wrapper ── */
    const outer = document.createElement('div');
    outer.className = 'rcg-outer';

    /* ── Header row ── */
    const hdr = document.createElement('div');
    hdr.className = 'rcg-header-row';
    hdr.style.gridTemplateColumns = `${TIME_COL}px repeat(${numRooms}, minmax(${COL_MIN}px, 1fr))`;

    const corner = document.createElement('div');
    corner.className = 'rcg-time-corner';
    hdr.appendChild(corner);

    rooms.forEach(room => {
        const pCls = pkgClass(room.package_type_name || '');
        const cell = document.createElement('div');
        cell.className = `rcg-room-header${pCls ? ' '+pCls : ''}`;
        cell.innerHTML = `
            <div class="rcg-room-title"><i class="fas fa-door-open"></i>${esc(room.room_name)}</div>
            <div class="rcg-room-meta">
                ${room.capacity ? `<span class="rcg-cap-badge"><i class="fas fa-users"></i>${room.capacity} người</span>` : ''}
                ${room.package_type_name ? `<span class="pkg-tag ${pCls}">${esc(room.package_type_name)}</span>` : ''}
            </div>`;
        hdr.appendChild(cell);
    });
    outer.appendChild(hdr);

    /* ── Body row ── */
    const body = document.createElement('div');
    body.className = 'rcg-body';
    body.style.gridTemplateColumns = `${TIME_COL}px repeat(${numRooms}, minmax(${COL_MIN}px, 1fr))`;

    /* Time axis */
    const timeAxis = document.createElement('div');
    timeAxis.className = 'rcg-time-axis';
    timeAxis.style.height = totalH + 'px';
    for (let h = RCG_START; h <= RCG_END; h++) {
        const tick = document.createElement('div');
        tick.className = 'rcg-tick';
        tick.style.top = ((h - RCG_START) * HOUR_PX) + 'px';
        tick.textContent = `${pad(h)}:00`;
        timeAxis.appendChild(tick);
    }
    body.appendChild(timeAxis);

    /* Room columns */
    rooms.forEach(room => {
        const pCls      = pkgClass(room.package_type_name || '');
        const roomEvs   = dayEvents.filter(e => parseInt(e.room_id) === parseInt(room.room_id));
        const col       = document.createElement('div');
        col.className   = 'rcg-room-col';
        col.style.height = totalH + 'px';

        /* Hour grid lines */
        for (let h = RCG_START; h < RCG_END; h++) {
            const gl = document.createElement('div');
            gl.className = 'rcg-hline' + (h % 2 === 0 ? ' rcg-hline-even' : '');
            gl.style.top = ((h - RCG_START) * HOUR_PX) + 'px';
            col.appendChild(gl);
        }

        /* Now line (all columns) */
        const nowH = now.getHours() + now.getMinutes() / 60;
        if (nowH >= RCG_START && nowH <= RCG_END) {
            const nl = document.createElement('div');
            nl.className = 'rcg-now-line';
            nl.style.top = ((nowH - RCG_START) * HOUR_PX) + 'px';
            col.appendChild(nl);
        }

        /* Empty state */
        if (!roomEvs.length) {
            const emp = document.createElement('div');
            emp.className = 'rcg-col-empty';
            emp.innerHTML = `<i class="fas fa-calendar-xmark"></i><span>Trống</span>`;
            col.appendChild(emp);
        }

        /* Events */
        roomEvs.forEach(e => {
            const evDate  = parseLocal(e.start_time);
            const isPast  = evDate < now;
            const isSoon  = !isPast && (evDate - now) < 2*3600*1000;
            const ePC     = pkgClass(e.package_type_name || '') || pCls;
            const evEnd   = e.end_time ? parseLocal(e.end_time) : null;
            const timeLabel = evEnd
                ? `${fmtTime(e.start_time)} – ${fmtTime(e.end_time)}`
                : fmtTime(e.start_time);
            const cls = isPast ? 'past' : isSoon ? 'soon' : 'upcoming';
            const isMeAssigned = !IS_ADMIN && MY_TRAINER_ID && parseInt(e.trainer_id) === MY_TRAINER_ID;

            const _tid = MY_TRAINER_ID || (window._DBG && window._DBG.empId ? window._DBG.empId : 0);
            let trainerBtn = '';
            if (!IS_ADMIN && !isPast) {
                if (_tid && parseInt(e.trainer_id) === _tid)
                    trainerBtn = `<button class="cal-trainer-btn cal-trainer-unregister" data-id="${e.class_id}"><i class="fas fa-user-minus"></i> Hủy dạy</button>`;
                else if (!e.trainer_id)
                    trainerBtn = `<button class="cal-trainer-btn cal-trainer-register" data-id="${e.class_id}"><i class="fas fa-hand-raised"></i> Đăng ký dạy</button>`;
            }

            const card = document.createElement('div');
            card.className = `rcg-event ${cls}${ePC ? ' '+ePC : ''}${isMeAssigned ? ' trainer-assigned-me' : ''}`;
            card.style.top    = tTop(e.start_time) + 'px';
            card.style.height = tHeight(e.start_time, e.end_time) + 'px';
            card.dataset.id   = e.class_id;
            card.innerHTML = `
                <div class="rcg-ev-time${isPast ? ' past' : ''}">
                    <i class="fas fa-clock" style="font-size:9px"></i>${esc(timeLabel)}
                </div>
                <div class="rcg-ev-name">${esc(e.class_name)}</div>
                ${e.trainer_name ? `<div class="rcg-ev-hlv"><i class="fas fa-person-running"></i>${esc(e.trainer_name)}</div>` : ''}
                ${trainerBtn}
                ${e.registration_count > 0 ? `<div class="rcg-ev-count">${e.registration_count}</div>` : ''}
            `;
            card.addEventListener('click', () => openDetail(parseInt(card.dataset.id)));
            if (trainerBtn) {
                const btn = card.querySelector('.cal-trainer-btn');
                if (btn) btn.addEventListener('click', async ev => {
                    ev.stopPropagation();
                    const cid = parseInt(btn.dataset.id);
                    if (btn.classList.contains('cal-trainer-register')) await assignTrainer(cid);
                    else await unassignTrainer(cid);
                });
            }
            col.appendChild(card);
        });

        /* Add button (admin) */
        if (IS_ADMIN) {
            const addBtn = document.createElement('button');
            addBtn.className = 'rcg-add-btn';
            addBtn.innerHTML = `<i class="fas fa-plus"></i> Thêm lịch`;
            addBtn.onclick = ev => { ev.stopPropagation(); openAddModal(currentRoomDate); };
            col.appendChild(addBtn);
        }

        col.addEventListener('click', ev => {
            if (IS_ADMIN && (ev.target === col || ev.target.classList.contains('rcg-hline')))
                openAddModal(currentRoomDate);
        });

        body.appendChild(col);
    });

    outer.appendChild(body);
    grid.innerHTML = '';
    grid.appendChild(outer);
}

function selectRoomDay(dateStr) {
    currentRoomDate = dateStr;
    renderRoomDayTabs();
    renderRoomColGrid();
}
function navRoomWeek(dir) {
    const [y,m,day] = currentWeekStart.split('-').map(Number);
    const d = new Date(y, m-1, day);
    d.setDate(d.getDate() + dir * 7);
    currentWeekStart = toLocalDateStr(d);
    currentRoomDate  = currentWeekStart;
    renderRoomDayTabs();
    renderRoomColGrid();
}
function goRoomToday() {
    currentWeekStart = getThisMonday();
    currentRoomDate  = toLocalDateStr(new Date());
    renderRoomDayTabs();
    renderRoomColGrid();
}

// ── LIST VIEW ─────────────────────────────────────────────────────
async function loadList(page = listPage) {
    listPage = page;
    const search  = document.getElementById('listSearch').value.trim();
    const hlv_id  = document.getElementById('listHlv').value;
    const room_id = document.getElementById('listRoom').value;
    const from    = document.getElementById('listFrom').value;
    const to      = document.getElementById('listTo').value;
    const params  = new URLSearchParams({ action:'get_schedules', page, limit:LIMIT, search, hlv_id, room_id, from, to });

    document.getElementById('listTbody').innerHTML = `<tr><td colspan="8" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>`;
    try {
        const d = await apiFetch(params.toString());
        renderListTable(d.data || []);
        renderPag(d.total||0, page, d.totalPages||1, 'listPagInfo', 'listPagCtrl', loadList);
        document.getElementById('listMeta').textContent = `${d.total||0} buổi tập`;
    } catch(e) {
        document.getElementById('listTbody').innerHTML = `<tr><td colspan="8"><div class="empty-state"><i class="fas fa-exclamation-circle"></i>Lỗi tải dữ liệu</div></td></tr>`;
    }
}

function getTimeBadge(startStr, endStr) {
    if (!startStr) return '—';
    const dt  = parseLocal(startStr); if (!dt) return '—';
    const now = new Date();
    const diff= dt - now;
    const time = `${pad(dt.getDate())}/${pad(dt.getMonth()+1)} ${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
    const endLabel = endStr ? ` – ${fmtTime(endStr)}` : '';
    if (diff < 0)       return `<span class="time-badge tb-past"><i class="fas fa-check"></i> ${time}${endLabel}</span>`;
    if (diff < 3600000) return `<span class="time-badge tb-now"><i class="fas fa-fire"></i> ${time}${endLabel}</span>`;
    if (diff < 86400000)return `<span class="time-badge tb-soon"><i class="fas fa-clock"></i> ${time}${endLabel}</span>`;
    return `<span class="time-badge tb-future"><i class="fas fa-calendar"></i> ${time}${endLabel}</span>`;
}

function repeatBadge(r) {
    if (r === 'daily')  return `<span class="repeat-badge rep-daily"><i class="fas fa-rotate-right"></i> Hàng ngày</span>`;
    if (r === 'weekly') return `<span class="repeat-badge rep-weekly"><i class="fas fa-calendar-week"></i> Hàng tuần</span>`;
    return `<span class="repeat-badge rep-none">—</span>`;
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

        /* Room + package type badge */
        const pBadge = pkgBadge(r.package_type_name || r.room_package || '');
        const room = r.room_name
            ? `<div style="display:flex;flex-direction:column;gap:3px"><span class="room-badge"><i class="fas fa-door-open"></i>${esc(r.room_name)}</span>${pBadge}</div>`
            : `<span style="color:var(--tm)">—</span>`;

        /* Admin-only action buttons */
        const adminActions = IS_ADMIN ? `
            <button class="btn-icon" onclick="openEdit(${r.class_id})" title="Sửa"><i class="fas fa-pen"></i></button>
            <button class="btn-icon del" onclick="confirmDel('Xóa buổi tập <strong>${esc(r.class_name)}</strong>?',()=>deleteSchedule(${r.class_id}))" title="Xóa"><i class="fas fa-trash"></i></button>
        ` : '';

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
                    ${adminActions}
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── REPEAT HELPERS ────────────────────────────────────────────────
function getRepeatType() {
    const checked = document.querySelector('input[name="repeatType"]:checked');
    return checked ? checked.value : 'none';
}

/* ── Parse datetime-local string into a LOCAL-time Date (avoids UTC shift bug) ──
   new Date("YYYY-MM-DDTHH:MM") may be treated as UTC by some browsers,
   causing getDay() to return the wrong weekday and shifting week boundaries.
   Using the explicit constructor new Date(y,m,d,h,min) always uses local time. */
function parseLocalInput(val) {
    if (!val) return null;
    const [datePart, timePart] = val.split('T');
    const [y, mo, d]  = datePart.split('-').map(Number);
    const [h, mi]     = (timePart || '00:00').split(':').map(Number);
    const dt = new Date(y, mo - 1, d, h, mi, 0, 0);
    return isNaN(dt) ? null : dt;
}

/* Get the Monday (T2) of the week that contains `date` in local calendar */
function getMondayOf(date) {
    const dow = date.getDay();                 // 0=CN,1=T2,...,6=T7
    const daysBack = dow === 0 ? 6 : dow - 1; // how far back to reach Monday
    return new Date(date.getFullYear(), date.getMonth(), date.getDate() - daysBack, 0, 0, 0, 0);
}

function updateRepeatNote() {
    const note = document.getElementById('repeatNote');
    const type = getRepeatType();
    const startVal = document.getElementById('fSchTime').value;
    if (type === 'none' || !startVal) { note.style.display = 'none'; return; }
    const startDate = parseLocalInput(startVal);
    if (!startDate) { note.style.display = 'none'; return; }

    note.style.display = 'block';
    if (type === 'daily') {
        /* Remaining days T2–CN of the selected date's week, AFTER the selected day */
        const monday  = getMondayOf(startDate);
        const weekEnd = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + 6, 23, 59, 59, 999);
        const remaining = [];
        let cur = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate() + 1);
        while (cur <= weekEnd) {
            remaining.push(`${pad(cur.getDate())}/${pad(cur.getMonth()+1)}`);
            cur = new Date(cur.getFullYear(), cur.getMonth(), cur.getDate() + 1);
        }
        if (remaining.length)
            note.innerHTML = `<i class="fas fa-info-circle" style="color:var(--green)"></i> Sẽ tạo thêm buổi cho: ${remaining.join(', ')} (các ngày còn lại trong tuần T2–CN)`;
        else
            note.innerHTML = `<i class="fas fa-info-circle" style="color:var(--tm)"></i> Không còn ngày nào trong tuần để lặp.`;
    } else if (type === 'weekly') {
        /* Same weekday for remaining weeks of the month */
        const dayOfWeek = startDate.getDay();
        const month     = startDate.getMonth();
        const dates     = [];
        let cur2 = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate() + 7);
        while (cur2.getMonth() === month) {
            dates.push(`${pad(cur2.getDate())}/${pad(cur2.getMonth()+1)}`);
            cur2 = new Date(cur2.getFullYear(), cur2.getMonth(), cur2.getDate() + 7);
        }
        if (dates.length)
            note.innerHTML = `<i class="fas fa-info-circle" style="color:var(--blue)"></i> Sẽ lặp vào ${DAYS[(dayOfWeek+6)%7]}: ${dates.join(', ')} (các tuần còn lại trong tháng)`;
        else
            note.innerHTML = `<i class="fas fa-info-circle" style="color:var(--tm)"></i> Không còn tuần nào trong tháng để lặp.`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const startInput = document.getElementById('fSchTime');
    if (startInput) startInput.addEventListener('change', updateRepeatNote);
});

/* Generate list of occurrences based on repeat type.
   Each occurrence is a plain independent session — no repeat_type stored.
   When repeat is active, each session name is:
     "{baseName} - DD/MM/YYYY HH:MM–HH:MM"   (or without –HH:MM if no end time)
   Uses parseLocalInput() + getMondayOf() to guarantee correct T2–CN week boundary
   regardless of browser timezone/UTC parsing quirks.
*/
function buildOccurrences(baseName, startVal, endVal, repeatType) {
    const occurrences = [];
    // Always parse using local-time constructor to avoid UTC-shift week-day bug
    const startDate = parseLocalInput(startVal);
    if (!startDate) return occurrences;

    const toLocal = d =>
        `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;

    const startMs  = startDate.getTime();
    const endDate  = endVal ? parseLocalInput(endVal) : null;
    const duration = endDate ? endDate.getTime() - startMs : null;

    /* Advance a Date by N whole calendar days, keeping same HH:MM */
    const addCalDays = (d, n) =>
        new Date(d.getFullYear(), d.getMonth(), d.getDate() + n, d.getHours(), d.getMinutes(), 0, 0);

    const makeEnd = d => duration
        ? toLocal(new Date(d.getTime() + duration))
        : '';

    /* Suffix: " - DD/MM/YYYY HH:MM–HH:MM" appended to repeated session names */
    const makeSuffix = (d, endLocal) => {
        const dateStr = `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()}`;
        const startHM = `${pad(d.getHours())}:${pad(d.getMinutes())}`;
        if (endLocal) {
            const eDate = parseLocalInput(endLocal);
            if (eDate) {
                const endHM = `${pad(eDate.getHours())}:${pad(eDate.getMinutes())}`;
                return ` - ${dateStr} ${startHM}–${endHM}`;
            }
        }
        return ` - ${dateStr} ${startHM}`;
    };

    if (repeatType === 'none') {
        occurrences.push({ class_name: baseName, start_time: startVal, end_time: endVal || '' });
        return occurrences;
    }

    if (repeatType === 'daily') {
        /* From startDate through Sunday of the same T2–CN week */
        const monday  = getMondayOf(startDate);
        // weekEnd = Sunday (monday + 6 days) at end-of-day
        const weekEnd = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + 6, 23, 59, 59, 999);

        let cur = new Date(startDate);          // first occurrence = selected day
        while (cur <= weekEnd) {
            const endLocal = makeEnd(cur);
            occurrences.push({
                class_name : baseName + makeSuffix(cur, endLocal),
                start_time : toLocal(cur),
                end_time   : endLocal
            });
            cur = addCalDays(cur, 1);           // next calendar day, same HH:MM
        }
        return occurrences;
    }

    if (repeatType === 'weekly') {
        /* Same weekday for each week remaining in the month */
        const month = startDate.getMonth();
        let cur = new Date(startDate);
        while (cur.getMonth() === month) {
            const endLocal = makeEnd(cur);
            occurrences.push({
                class_name : baseName + makeSuffix(cur, endLocal),
                start_time : toLocal(cur),
                end_time   : endLocal
            });
            cur = addCalDays(cur, 7);
        }
        return occurrences;
    }
    return occurrences;
}

// ── ADD / EDIT SCHEDULE ───────────────────────────────────────────
function openAddModal(defaultDate='') {
    if (!IS_ADMIN) return;
    document.getElementById('fSchId').value       = '';
    document.getElementById('fSchTen').value      = '';
    document.getElementById('fSchHlv').value      = '';
    document.getElementById('fSchRoom').value     = '';
    document.getElementById('fSchEndTime').value  = '';
    document.getElementById('fSchTime').value     = defaultDate ? defaultDate + 'T08:00' : '';
    /* Reset repeat — show the section for new sessions */
    document.getElementById('repeatOptions').closest('.form-group').style.display = '';
    document.querySelectorAll('.repeat-opt').forEach(o => o.classList.remove('active'));
    document.querySelector('.repeat-opt[data-val="none"]').classList.add('active');
    document.querySelector('input[name="repeatType"][value="none"]').checked = true;
    document.getElementById('repeatNote').style.display = 'none';
    document.getElementById('schModalTitle').innerHTML =
        `<i class="fas fa-calendar-plus" style="color:var(--gold);margin-right:8px"></i>Thêm buổi tập`;
    openModal('scheduleModal');
}

async function openEdit(id) {
    if (!IS_ADMIN) return;
    try {
        const d = await apiFetch(`get_schedule_detail&id=${id}`);
        if (!d.success) { toast(d.message,'error'); return; }
        const r = d.schedule;
        document.getElementById('fSchId').value       = r.class_id;
        document.getElementById('fSchTen').value      = r.class_name||'';
        document.getElementById('fSchHlv').value      = r.trainer_id||'';
        document.getElementById('fSchRoom').value     = r.room_id||'';
        document.getElementById('fSchTime').value     = r.start_time ? r.start_time.slice(0,16) : '';
        document.getElementById('fSchEndTime').value  = r.end_time   ? r.end_time.slice(0,16)   : '';
        /* On edit: hide repeat section entirely — it applies to new sessions only */
        document.getElementById('repeatOptions').closest('.form-group').style.display = 'none';
        document.getElementById('repeatNote').style.display = 'none';
        /* Reset to none silently */
        document.querySelectorAll('.repeat-opt').forEach(o => o.classList.remove('active'));
        document.querySelector('.repeat-opt[data-val="none"]').classList.add('active');
        document.querySelector('input[name="repeatType"][value="none"]').checked = true;
        document.getElementById('schModalTitle').innerHTML =
            `<i class="fas fa-pen" style="color:var(--gold);margin-right:8px"></i>Sửa buổi tập`;
        closeModal('detailModal');
        openModal('scheduleModal');
    } catch(e) { toast('Lỗi tải dữ liệu','error'); }
}

async function saveSchedule() {
    if (!IS_ADMIN) return;
    const id        = document.getElementById('fSchId').value;
    const className = document.getElementById('fSchTen').value.trim();
    const startTime = document.getElementById('fSchTime').value;
    const endTime   = document.getElementById('fSchEndTime').value;
    const hlv       = document.getElementById('fSchHlv').value;
    const roomId    = document.getElementById('fSchRoom').value;
    const repeatType= getRepeatType();

    if (!className||!startTime) { toast('Vui lòng nhập tên lớp và thời gian bắt đầu','warning'); return; }
    if (!endTime) { toast('Vui lòng nhập giờ kết thúc để tránh trùng lịch','warning'); return; }
    if (new Date(endTime) <= new Date(startTime)) {
        toast('Giờ kết thúc phải lớn hơn giờ bắt đầu', 'warning'); return;
    }

    /* ── EDIT mode: single update ── */
    if (id) {
        const body = fd({
            action:'update_schedule', id,
            class_name: className, start_time: startTime,
            end_time: endTime, trainer_id: hlv, room_id: roomId
        });
        try {
            const d = await apiPost(body);
            toast(d.message, d.success?'success':'error');
            if (d.success) { closeModal('scheduleModal'); loadStats(); renderCalendar(); if (currentView==='list') loadList(); if (calMode==='room') renderRoomColGrid(); }
        } catch(e) { toast('Lỗi kết nối server','error'); }
        return;
    }

    /* ── ADD mode: build occurrences ── */
    const occurrences = buildOccurrences(className, startTime, endTime, repeatType);
    if (!occurrences.length) { toast('Không có buổi nào được tạo','warning'); return; }

    let successCount = 0, failMessages = [];
    for (const occ of occurrences) {
        const body = fd({
            action:'add_schedule',
            class_name : occ.class_name,
            start_time : occ.start_time,
            end_time   : occ.end_time,
            trainer_id : hlv,
            room_id    : roomId
        });
        try {
            const d = await apiPost(body);
            if (d.success) successCount++;
            else failMessages.push(d.message || 'Lỗi không xác định');
        } catch(e) { failMessages.push('Lỗi kết nối server'); }
    }

    if (successCount > 0) {
        const failNote = failMessages.length ? `, ${failMessages.length} lỗi` : '';
        toast(`Đã tạo ${successCount} buổi tập${failNote}`, 'success');
        if (failMessages.length) {
            /* Show first error detail after a short delay */
            setTimeout(() => toast(`Lỗi: ${failMessages[0]}`, 'warning'), 600);
        }
        closeModal('scheduleModal');
        loadStats(); renderCalendar();
        if (currentView==='list') loadList();
        if (calMode==='room') renderRoomColGrid();
    } else {
        /* All failed — show the first error message directly */
        const errMsg = failMessages[0] || 'Lỗi không xác định';
        toast(`Không thể tạo buổi tập: ${errMsg}`, 'error');
    }
}

async function deleteSchedule(id) {
    if (!IS_ADMIN) return;
    try {
        const d = await apiPost(fd({ action:'delete_schedule', id }));
        toast(d.message, d.success?'success':'error');
        if (d.success) { loadStats(); renderCalendar(); if (currentView==='list') loadList(); if (calMode==='room') renderRoomColGrid(); }
    } catch(e) { toast('Lỗi kết nối server','error'); }
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
        const thuLabel  = dt ? DAYS[(dt.getDay()+6)%7] : '—';
        const timeRange = dt
            ? (dtEnd ? `${fmtDatetime(r.start_time)} – ${fmtTime(r.end_time)}` : fmtDatetime(r.start_time))
            : '—';

        /* Package badge in detail */
        const pBadge = pkgBadge(r.package_type_name || r.room_package || '');

        /* Admin-only edit button */
        const editBtn = IS_ADMIN && !isPast
            ? `<button class="btn-icon" onclick="openEdit(${r.class_id})" title="Sửa" style="flex-shrink:0"><i class="fas fa-pen"></i></button>`
            : '';

        /* Trainer: nút đăng ký / hủy dạy trong modal chi tiết
           MY_TRAINER_ID may be 0 if employee_id not in session — also check window._DBG */
        const _tid = MY_TRAINER_ID || (window._DBG && window._DBG.empId ? window._DBG.empId : 0);
        const isMeTrainer = !IS_ADMIN && _tid && parseInt(r.trainer_id) === _tid;
        const isNoTrainer = !IS_ADMIN && !r.trainer_id;
        const isOtherTrainer = !IS_ADMIN && r.trainer_id && !isMeTrainer;
        let trainerActionBtn = '';
        if (!IS_ADMIN && !isPast) {
            if (isMeTrainer) {
                trainerActionBtn = `
                <div class="trainer-action-bar">
                    <div class="trainer-action-status assigned">
                        <i class="fas fa-circle-check"></i> Bạn đang phụ trách buổi này
                    </div>
                    <button class="trainer-action-btn cancel" onclick="unassignTrainer(${r.class_id})">
                        <i class="fas fa-user-minus"></i> Hủy đăng ký dạy
                    </button>
                </div>`;
            } else if (isNoTrainer) {
                trainerActionBtn = `
                <div class="trainer-action-bar">
                    <div class="trainer-action-status vacant">
                        <i class="fas fa-circle-exclamation"></i> Chưa có HLV phụ trách
                    </div>
                    <button class="trainer-action-btn register" onclick="assignTrainer(${r.class_id})">
                        <i class="fas fa-hand-raised"></i> Đăng ký dạy buổi này
                    </button>
                </div>`;
            } else if (isOtherTrainer) {
                trainerActionBtn = `
                <div class="trainer-action-bar">
                    <div class="trainer-action-status taken">
                        <i class="fas fa-user-check"></i> HLV ${esc(r.trainer_name||'')} đang phụ trách
                    </div>
                </div>`;
            }
        }

        /* Add member button — admin only */
        const addMemberBtn = IS_ADMIN
            ? `<button class="btn-add-member" style="width:auto;margin:0;padding:6px 12px;font-size:11px" onclick="openRegModal(${r.class_id})">
                   <i class="fas fa-plus"></i> Thêm
               </button>`
            : '';

        document.getElementById('detailTitle').innerHTML =
            `<i class="fas fa-circle-info" style="color:var(--gold);margin-right:8px"></i>${esc(r.class_name)}`;

        document.getElementById('detailContent').innerHTML = `
            <div class="det-hero">
                <div class="det-icon"><i class="fas fa-dumbbell"></i></div>
                <div style="flex:1">
                    <div class="det-name">${esc(r.class_name)}</div>
                    <div class="det-meta">
                        <span><i class="fas fa-calendar" style="color:var(--gold)"></i>${timeRange}</span>
                        <span><i class="fas fa-calendar-week" style="color:var(--blue)"></i>${thuLabel}</span>
                        ${r.trainer_name ? `<span><i class="fas fa-person-running" style="color:var(--orange)"></i>${esc(r.trainer_name)}</span>` : '<span style="color:var(--tm)"><i class="fas fa-person-running" style="color:var(--tm)"></i>Chưa có HLV</span>'}
                        ${r.room_name ? `<span><i class="fas fa-door-open" style="color:var(--teal)"></i>${esc(r.room_name)}</span>` : ''}
                        ${pBadge ? `<span>${pBadge}</span>` : ''}
                    </div>
                </div>
                ${editBtn}
            </div>
            ${trainerActionBtn ? `<div style="display:flex;justify-content:flex-end;padding:4px 0 8px">${trainerActionBtn}</div>` : ''}
            <div class="sec-title">
                <span class="sec-title-left"><i class="fas fa-users"></i> Người tham gia (${members.length})</span>
                ${addMemberBtn}
            </div>
            <div class="member-list">
                ${members.length ? members.map(m => `
                    <div class="member-row">
                        <div class="member-av">${avText(m.full_name)}</div>
                        <div style="flex:1;min-width:0">
                            <div class="member-name">${esc(m.full_name)}</div>
                            <div class="member-sdt">${m.phone||''}</div>
                        </div>
                        ${IS_ADMIN ? `<button class="btn-icon-sm" onclick="removeReg(${m.class_registration_id},${id})" title="Hủy đăng ký"><i class="fas fa-times"></i></button>` : ''}
                    </div>`).join('')
                : '<div class="no-members"><i class="fas fa-users-slash"></i>Chưa có người đăng ký</div>'}
            </div>`;
    } catch(e) { toast('Lỗi tải chi tiết','error'); }
}

async function removeReg(regId, scheduleId) {
    if (!IS_ADMIN) return;
    try {
        const d = await apiPost(fd({ action:'delete_registration', id:regId }));
        toast(d.message, d.success?'success':'error');
        if (d.success) { loadStats(); renderCalendar(); renderDetail(scheduleId); if (currentView==='list') loadList(); if (calMode==='room') renderRoomColGrid(); }
    } catch(e) { toast('Lỗi kết nối server','error'); }
}

// ── TRAINER SELF-REGISTER AS HLV ─────────────────────────────────
async function assignTrainer(classId) {
    if (IS_ADMIN) return;
    const _tid = MY_TRAINER_ID || (window._DBG && window._DBG.empId ? window._DBG.empId : 0);
    if (!_tid) { toast('Không xác định được ID huấn luyện viên','error'); return; }
    try {
        const d = await apiPost(fd({ action:'assign_trainer', class_id:classId, trainer_id:_tid }));
        toast(d.message, d.success?'success':'error');
        if (d.success) { loadStats(); renderCalendar(); if (currentDetailId) renderDetail(currentDetailId); if (currentView==='list') loadList(); if (calMode==='room') renderRoomColGrid(); }
    } catch(e) { toast('Lỗi kết nối server','error'); }
}

async function unassignTrainer(classId) {
    if (IS_ADMIN) return;
    const _tid = MY_TRAINER_ID || (window._DBG && window._DBG.empId ? window._DBG.empId : 0);
    if (!_tid) { toast('Không xác định được ID huấn luyện viên','error'); return; }
    try {
        const d = await apiPost(fd({ action:'unassign_trainer', class_id:classId, trainer_id:_tid }));
        toast(d.message, d.success?'success':'error');
        if (d.success) { loadStats(); renderCalendar(); if (currentDetailId) renderDetail(currentDetailId); if (currentView==='list') loadList(); if (calMode==='room') renderRoomColGrid(); }
    } catch(e) { toast('Lỗi kết nối server','error'); }
}

// ── REGISTER MEMBER ───────────────────────────────────────────────
function openRegModal(lichId) {
    document.getElementById('fRegLichId').value   = lichId;
    document.getElementById('fRegSearch').value   = '';
    document.getElementById('fRegKhId').value     = '';
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
    } catch(e) { console.error('searchKH error:', e); }
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
    const classId    = document.getElementById('fRegLichId').value;
    const customerId = document.getElementById('fRegKhId').value;
    if (!customerId) { toast('Vui lòng chọn khách hàng','warning'); return; }
    try {
        const d = await apiPost(fd({ action:'add_registration', class_id:classId, customer_id:customerId }));
        toast(d.message, d.success?'success':'error');
        if (d.success) {
            closeModal('regModal');
            if (currentDetailId) renderDetail(currentDetailId);
            loadStats(); renderCalendar(); if (currentView==='list') loadList(); if (calMode==='room') renderRoomColGrid();
        }
    } catch(e) { toast('Lỗi kết nối server','error'); }
}

// (renderRoomView / renderRoomDateTabs / renderRoomGrid removed — superseded by renderRoomDayTabs + renderRoomColGrid)

// ── PAGINATION ────────────────────────────────────────────────────
function renderPag(total, page, totalPages, piId, pcId, fn) {
    const from = total>0 ? (page-1)*LIMIT+1 : 0, to = Math.min(page*LIMIT, total);
    document.getElementById(piId).textContent = total>0 ? `Hiển thị ${from}–${to} / ${total}` : 'Không có dữ liệu';
    const ctrl = document.getElementById(pcId);
    if (totalPages<=1) { ctrl.innerHTML=''; return; }
    // Lưu fn vào map để gọi qua tên, tránh fn.toString() nhúng source code vào onclick
    renderPag._fnMap = renderPag._fnMap || {};
    const fnKey = piId; // piId là unique per table
    renderPag._fnMap[fnKey] = fn;
    const call = (p) => `renderPag._fnMap['${fnKey}'](${p})`;
    let h = `<button class="page-btn" onclick="${call(page-1)}" ${page===1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i=1;i<=totalPages;i++) {
        if (totalPages>7 && Math.abs(i-page)>2 && i!==1 && i!==totalPages) {
            if (i===2||i===totalPages-1) h+=`<button class="page-btn" disabled>…</button>`;
            continue;
        }
        h+=`<button class="page-btn ${i===page?'active':''}" onclick="${call(i)}">${i}</button>`;
    }
    h+=`<button class="page-btn" onclick="${call(page+1)}" ${page===totalPages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
    ctrl.innerHTML=h;
}

// ── CONFIRM ───────────────────────────────────────────────────────
function confirmDel(msg, cb) {
    document.getElementById('confirmMsg').innerHTML = msg;
    document.getElementById('confirmOkBtn').onclick = () => { cb(); closeModal('confirmModal'); };
    openModal('confirmModal');
}


