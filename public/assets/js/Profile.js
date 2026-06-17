// Tự tính BASE_URL nếu window.ELITE_BASE chưa được set
(function () {
    if (!window.ELITE_BASE) {
        var path = window.location.pathname;
        var pub  = path.indexOf('/public');
        window.ELITE_BASE = pub !== -1
            ? window.location.origin + path.substring(0, pub + 7)
            : window.location.origin;
    }
})();
var API = window.ELITE_BASE + '/api/admin/profile';

// ===== CLOCK =====
function updateClock() {
    const el = document.getElementById('topbar-time');
    if (!el) return;
    const now = new Date();
    el.textContent = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')}`;
}
setInterval(updateClock, 1000);
updateClock();

// ===== TOAST =====
function showToast(id, msg, type = 'success') {
    const el = document.getElementById(id);
    el.className = `toast ${type}`;
    el.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${msg}`;
    clearTimeout(el._timer);
    el._timer = setTimeout(() => { el.className = 'toast hidden'; }, 4000);
}

// ===== LOAD PROFILE =====
async function loadProfile() {
    try {
        const res = await fetch(`${API}?action=get_profile`, { credentials: 'include' });
        const d   = await res.json();
        if (!d.success) return;

        const p = d.data;

        document.getElementById('inp_name').value    = p.full_name    || '';
        document.getElementById('inp_phone').value   = p.phone        || '';
        document.getElementById('inp_email').value   = p.email        || '';
        document.getElementById('inp_dob').value     = p.date_of_birth|| '';
        document.getElementById('inp_address').value = p.address      || '';
        document.getElementById('displayName').textContent = p.full_name || '';

        const sel = document.getElementById('inp_gender');
        for (let opt of sel.options) {
            if (opt.value === p.gender) { opt.selected = true; break; }
        }

        const initials = (p.full_name || '?').charAt(0).toUpperCase();
        document.getElementById('avatarCircle').textContent = initials;

        if (p.hire_date) {
            document.getElementById('statHireDate').textContent = formatDate(p.hire_date);
        }
        const genderMap = { Male: 'Nam', Female: 'Nu', Other: 'Khac' };
        document.getElementById('statGender').textContent = genderMap[p.gender] || '-';

    } catch(e) {
        console.error('loadProfile error:', e);
    }
}

function formatDate(str) {
    if (!str) return '-';
    const d = new Date(str);
    if (isNaN(d)) return str;
    return d.toLocaleDateString('vi-VN');
}

// ===== EDIT TOGGLE =====
var editMode = false;

function toggleEdit() {
    editMode = !editMode;
    const fields  = ['inp_name','inp_phone','inp_email','inp_dob','inp_address'];
    const btn     = document.getElementById('editToggleBtn');
    const actions = document.getElementById('formActions');
    const gender  = document.getElementById('inp_gender');

    if (editMode) {
        fields.forEach(id => { document.getElementById(id).removeAttribute('readonly'); });
        gender.removeAttribute('disabled');
        btn.innerHTML = '<i class="fas fa-times"></i> Huy';
        btn.classList.add('active');
        actions.classList.remove('hidden');
    } else {
        cancelEdit();
    }
}

function cancelEdit() {
    editMode = false;
    const fields = ['inp_name','inp_phone','inp_email','inp_dob','inp_address'];
    const btn    = document.getElementById('editToggleBtn');
    const actions= document.getElementById('formActions');
    fields.forEach(id => { document.getElementById(id).setAttribute('readonly',''); });
    document.getElementById('inp_gender').setAttribute('disabled','');
    btn.innerHTML = '<i class="fas fa-pen"></i> Chinh sua';
    btn.classList.remove('active');
    actions.classList.add('hidden');
    loadProfile();
}

// ===== SAVE PROFILE =====
async function saveProfile(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSave');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Dang luu...';

    const payload = {
        action:        'update_profile',
        full_name:     document.getElementById('inp_name').value.trim(),
        phone:         document.getElementById('inp_phone').value.trim(),
        email:         document.getElementById('inp_email').value.trim(),
        date_of_birth: document.getElementById('inp_dob').value,
        gender:        document.getElementById('inp_gender').value,
        address:       document.getElementById('inp_address').value.trim()
    };

    try {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const d = await res.json();
        if (d.success) {
            showToast('toast', 'Cap nhat thong tin thanh cong!', 'success');
            document.getElementById('displayName').textContent = payload.full_name;
            document.getElementById('avatarCircle').textContent = payload.full_name.charAt(0).toUpperCase();
            cancelEdit();
        } else {
            showToast('toast', d.message || 'Co loi xay ra.', 'error');
        }
    } catch(err) {
        showToast('toast', 'Loi ket noi may chu.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Luu thay doi';
    }
}

// ===== CHANGE PASSWORD =====
async function changePassword(e) {
    e.preventDefault();
    const btn    = document.getElementById('btnChangePw');
    const newPw  = document.getElementById('inp_newpw').value;
    const confPw = document.getElementById('inp_confpw').value;

    if (newPw !== confPw) { showToast('pwToast','Mat khau xac nhan khong khop!','error'); return; }
    if (newPw.length < 6) { showToast('pwToast','Mat khau moi phai co it nhat 6 ky tu!','error'); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Dang cap nhat...';

    try {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'change_password',
                current_password: document.getElementById('inp_curpw').value,
                new_password: newPw
            })
        });
        const d = await res.json();
        if (d.success) {
            showToast('pwToast', 'Doi mat khau thanh cong!', 'success');
            document.getElementById('passwordForm').reset();
            document.getElementById('pwStrengthWrap').style.display = 'none';
        } else {
            showToast('pwToast', d.message || 'Mat khau hien tai khong dung.', 'error');
        }
    } catch(err) {
        showToast('pwToast', 'Loi ket noi may chu.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Cap nhat mat khau';
    }
}

// ===== SHOW/HIDE PASSWORD =====
function togglePw(inputId, btn) {
    const inp = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
    else { inp.type = 'password'; icon.className = 'fas fa-eye'; }
}

// ════════════════════════════════════════════════════════════
// GPS CHECK-IN MODULE
// ════════════════════════════════════════════════════════════

var gpsWatchId  = null;
var userLat     = null;
var userLng     = null;
var gymSettings = { lat: null, lng: null, radius: 100, check: 1, name: 'Elite Gym' };

function haversine(lat1, lng1, lat2, lng2) {
    const R  = 6371000;
    const dL = (lat2 - lat1) * Math.PI / 180;
    const dl = (lng2 - lng1) * Math.PI / 180;
    const a  = Math.sin(dL/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dl/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

async function initGpsCheckin() {
    try {
        const res  = await fetch(`${API}?action=get_gym_settings`, { credentials: 'include' });
        const text = await res.text();
        let d;
        try { d = JSON.parse(text); }
        catch(e) { console.error('GPS settings parse error:', text.substring(0,200)); d = {ok:false}; }
        if (d.ok) {
            gymSettings = { lat: d.lat, lng: d.lng, radius: d.radius, check: d.check, name: d.name };
            const nm = document.getElementById('gpsGymName');
            if (nm) nm.textContent = d.name || 'Elite Gym';
        } else {
            console.warn('get_gym_settings:', d.msg);
            gymSettings.check = 0;
        }
    } catch(e) {
        console.error('initGpsCheckin fetch error:', e);
        gymSettings.check = 0;
    }

    loadTodayStatus();
    loadGpsHistory();
    startGpsWatch();
}

function startGpsWatch() {
    if (!navigator.geolocation) {
        setGpsIndicator('off', 'Trinh duyet khong ho tro GPS');
        if (!gymSettings.lat || gymSettings.check == 0) {
            document.getElementById('btnCheckIn').disabled = false;
        }
        return;
    }
    setGpsIndicator('loading', 'Dang lay vi tri GPS...');

    gpsWatchId = navigator.geolocation.watchPosition(
        pos => {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;
            const acc = Math.round(pos.coords.accuracy);

            if (!gymSettings.lat || gymSettings.check == 0) {
                setGpsIndicator('ok', `GPS san sang (+-${acc}m)`);
                document.getElementById('btnCheckIn').disabled = false;
                return;
            }

            const dist = Math.round(haversine(userLat, userLng, gymSettings.lat, gymSettings.lng));
            const badge = document.getElementById('gpsDistBadge');
            if (badge) { badge.style.display = ''; badge.textContent = `Cach ~${dist}m`; }

            if (acc > 500) {
                setGpsIndicator('ok', `GPS san sang (+-${acc}m) - do chinh xac thap`);
                document.getElementById('btnCheckIn').disabled = false;
                return;
            }

            if (dist <= gymSettings.radius) {
                setGpsIndicator('ok', `Trong pham vi phong tap (+-${acc}m)`);
                document.getElementById('btnCheckIn').disabled = false;
            } else {
                setGpsIndicator('far', `Ngoai pham vi - cach ${dist}m (cho phep ${gymSettings.radius}m)`);
                document.getElementById('btnCheckIn').disabled = true;
            }
        },
        err => {
            const msgs = {
                1: 'GPS bi tu choi - hay cho phep trong cai dat trinh duyet',
                2: 'Khong lay duoc vi tri GPS',
                3: 'Het thoi gian GPS'
            };
            setGpsIndicator('off', msgs[err.code] || 'Loi GPS');
            if (!gymSettings.lat || gymSettings.check == 0) {
                document.getElementById('btnCheckIn').disabled = false;
            }
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
    );
}

function setGpsIndicator(state, text) {
    const ind = document.getElementById('gpsIndicator');
    const txt = document.getElementById('gpsText');
    ind.className   = `gps-indicator gps--${state}`;
    txt.textContent = text;
}

async function loadTodayStatus() {
    try {
        const res  = await fetch(`${API}?action=get_today_status`, { credentials: 'include' });
        const text = await res.text();
        let d;
        try { d = JSON.parse(text); }
        catch(e) { console.error('get_today_status parse error:', text.substring(0,200)); return; }

        if (!d.ok) { console.warn('get_today_status:', d.msg); return; }

        const today   = new Date();
        const boxDate = document.getElementById('boxDate');
        if (boxDate) boxDate.querySelector('.gps-status-val').textContent =
            today.toLocaleDateString('vi-VN', { weekday:'short', day:'2-digit', month:'2-digit' });

        if (d.record) {
            const r = d.record;
            const statusMap = { Present:'Dung gio', Late:'Di muon', Absent:'Vang' };
            const statusBox = document.getElementById('boxStatus');
            if (statusBox) {
                statusBox.querySelector('.gps-status-val').textContent = statusMap[r.status] || r.status;
                if (r.status === 'Present')      statusBox.className = 'gps-status-box status--present';
                else if (r.status === 'Late')    statusBox.className = 'gps-status-box status--late';
                else                             statusBox.className = 'gps-status-box status--absent';
            }

            const boxIn = document.getElementById('boxCheckIn');
            if (boxIn) boxIn.querySelector('.gps-status-val').textContent =
                r.check_in ? r.check_in.slice(0,5) : '—';

            const boxOut = document.getElementById('boxCheckOut');
            if (boxOut) boxOut.querySelector('.gps-status-val').textContent =
                r.check_out ? r.check_out.slice(0,5) : '—';

            const boxH = document.getElementById('boxHours');
            if (boxH && r.check_in && r.check_out) {
                const cin  = r.check_in.split(':');
                const cout = r.check_out.split(':');
                const mins = (parseInt(cout[0])*60+parseInt(cout[1])) - (parseInt(cin[0])*60+parseInt(cin[1]));
                const h = Math.floor(Math.max(0,mins)/60), m = Math.max(0,mins)%60;
                boxH.querySelector('.gps-status-val').textContent = `${h}h${String(m).padStart(2,'0')}`;
            }

            const btnIn  = document.getElementById('btnCheckIn');
            const btnOut = document.getElementById('btnCheckOut');
            if (r.check_in && !r.check_out) {
                if (btnIn)  btnIn.disabled  = true;
                if (btnOut) btnOut.disabled = false;
            } else if (r.check_in && r.check_out) {
                if (btnIn)  btnIn.disabled  = true;
                if (btnOut) btnOut.disabled = true;
            }
        }
    } catch(e) { console.error('loadTodayStatus error:', e); }
}

async function doCheckIn() {
    const btn = document.getElementById('btnCheckIn');
    if (btn) btn.disabled = true;
    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Dang xu ly...';

    try {
        const fd = new FormData();
        fd.append('action', 'checkin');
        if (userLat !== null) { fd.append('lat', userLat); fd.append('lng', userLng); }

        const res  = await fetch(API, {  method:'POST', body: fd, credentials: 'include' });
        const text = await res.text();
        let d;
        try { d = JSON.parse(text); }
        catch(e) {
            console.error('checkin parse error:', text.substring(0,500));
            d = {ok:false, msg:'Loi server - xem Console de biet chi tiet'};
        }

        if (d.ok) {
            showGpsToast('ok', `Check in luc ${d.time} - ${d.status === 'Late' ? 'Muon' : 'Dung gio'}`);
            loadTodayStatus();
            loadGpsHistory();
        } else {
            showGpsToast('err', d.msg || 'Khong the check in');
            if (btn) btn.disabled = false;
        }
    } catch(e) {
        showGpsToast('err', 'Loi ket noi: ' + e.message);
        if (btn) btn.disabled = false;
    } finally {
        if (btn) btn.innerHTML = '<i class="fas fa-map-marker-alt"></i> Check In';
    }
}

async function doCheckOut() {
    const btn = document.getElementById('btnCheckOut');
    if (btn) btn.disabled = true;
    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Dang xu ly...';

    try {
        const fd = new FormData();
        fd.append('action', 'checkout');
        if (userLat !== null) { fd.append('lat', userLat); fd.append('lng', userLng); }

        const res  = await fetch(API, {  method:'POST', body: fd, credentials: 'include' });
        const text = await res.text();
        let d;
        try { d = JSON.parse(text); }
        catch(e) { console.error('checkout parse error:', text.substring(0,200)); d = {ok:false, msg:'Loi server'}; }

        if (d.ok) {
            showGpsToast('ok', `Check out luc ${d.time} - Lam ${d.hours}`);
            loadTodayStatus();
            loadGpsHistory();
        } else {
            showGpsToast('err', d.msg || 'Khong the check out');
            if (btn) btn.disabled = false;
        }
    } catch(e) {
        showGpsToast('err', 'Loi ket noi: ' + e.message);
        if (btn) btn.disabled = false;
    } finally {
        if (btn) btn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Check Out';
    }
}

async function loadGpsHistory() {
    const list = document.getElementById('gpsHistoryList');
    if (!list) return;
    try {
        const res  = await fetch(`${API}?action=get_history&limit=7`, { credentials: 'include' });
        const text = await res.text();
        let d;
        try { d = JSON.parse(text); }
        catch(e) {
            console.error('get_history parse error:', text.substring(0,200));
            list.innerHTML = `<div style="padding:14px;text-align:center;color:var(--tm);font-size:.82rem">Loi tai du lieu</div>`;
            return;
        }

        if (!d.ok || !d.records || !d.records.length) {
            list.innerHTML = `<div style="padding:14px;text-align:center;color:var(--tm);font-size:.82rem">Chua co du lieu cham cong</div>`;
            return;
        }

        const statusMap = { Present:'Dung gio', Late:'Muon', Absent:'Vang' };
        const badgeMap  = { Present:'badge--present', Late:'badge--late', Absent:'badge--absent' };

        list.innerHTML = d.records.map(r => {
            const cin     = r.check_in  ? r.check_in.slice(0,5)  : '—';
            const cout    = r.check_out ? r.check_out.slice(0,5) : '—';
            const dateStr = new Date(r.work_date + 'T00:00:00').toLocaleDateString('vi-VN', { day:'2-digit', month:'2-digit', weekday:'short' });
            return `
            <div class="gps-history-item">
                <span class="gps-hi-date">${dateStr}</span>
                <span class="gps-hi-times">
                    <i class="fas fa-sign-in-alt" style="color:#15803d;font-size:.75rem;margin-right:3px"></i>${cin}
                    &nbsp;&nbsp;
                    <i class="fas fa-sign-out-alt" style="color:#dc2626;font-size:.75rem;margin-right:3px"></i>${cout}
                </span>
                <span class="gps-hi-badge ${badgeMap[r.status]||'badge--absent'}">${statusMap[r.status]||r.status}</span>
            </div>`;
        }).join('');
    } catch(e) {
        console.error('loadGpsHistory error:', e);
        if (list) list.innerHTML = `<div style="padding:14px;text-align:center;color:var(--tm);font-size:.82rem">Loi tai du lieu</div>`;
    }
}

function showGpsToast(type, msg) {
    const el = document.getElementById('gpsToast');
    el.className = `gps-toast gps-toast--${type}`;
    el.innerHTML = `<i class="fas fa-${type==='ok'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.className = 'gps-toast hidden'; }, 5000);
}

// ════════════════════════════════════════
// PAYROLL — Bang luong nhan vien
// ════════════════════════════════════════
async function loadPayroll() {
    try {
        const res  = await fetch(`${API}?action=get_payroll&limit=6`, { credentials: 'include' });
        const data = await res.json();
        const wrap = document.getElementById('payrollContent');
        if (!wrap) return;

        if (!data.ok || !data.records || !data.records.length) {
            wrap.innerHTML = `
                <div class="payroll-empty">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Chua co du lieu luong
                </div>`;
            return;
        }

        const fmt = v => Number(v || 0).toLocaleString('vi-VN') + ' d';
        const monthNames = ['','Thang 1','Thang 2','Thang 3','Thang 4','Thang 5','Thang 6',
                            'Thang 7','Thang 8','Thang 9','Thang 10','Thang 11','Thang 12'];

        const latest        = data.records[0];
        const bonusAllowance = Number(latest.allowance || 0) + Number(latest.bonus || 0);

        const summaryHTML = `
            <div class="payroll-summary">
                <div class="payroll-summary-box">
                    <div class="ps-label"><i class="fas fa-money-bill-wave"></i> Luong co ban</div>
                    <div class="ps-val">${fmt(latest.base_salary)}</div>
                </div>
                <div class="payroll-summary-box ps--green">
                    <div class="ps-label"><i class="fas fa-plus-circle"></i> Phu cap + Thuong</div>
                    <div class="ps-val">+${fmt(bonusAllowance)}</div>
                </div>
                <div class="payroll-summary-box ps--red">
                    <div class="ps-label"><i class="fas fa-minus-circle"></i> Khau tru</div>
                    <div class="ps-val">-${fmt(latest.deduction)}</div>
                </div>
                <div class="payroll-summary-box ps--gold">
                    <div class="ps-label"><i class="fas fa-hand-holding-usd"></i> Thuc nhan (thang gan nhat)</div>
                    <div class="ps-val">${fmt(latest.net_salary)}</div>
                </div>
            </div>`;

        const rows = data.records.map(r => `
            <tr>
                <td>
                    <span class="payroll-month-badge">
                        <i class="fas fa-calendar-alt" style="font-size:10px"></i>
                        ${monthNames[r.month]} ${r.year}
                    </span>
                </td>
                <td>${fmt(r.base_salary)}</td>
                <td class="payroll-plus">+${fmt(r.allowance)}</td>
                <td class="payroll-plus">+${fmt(r.bonus)}</td>
                <td class="payroll-minus">-${fmt(r.deduction)}</td>
                <td class="payroll-net">${fmt(r.net_salary)}</td>
            </tr>`).join('');

        wrap.innerHTML = summaryHTML + `
            <div class="payroll-table-wrap">
                <table class="payroll-table">
                    <thead>
                        <tr>
                            <th>Ky luong</th>
                            <th>Luong co ban</th>
                            <th>Phu cap</th>
                            <th>Thuong</th>
                            <th>Khau tru</th>
                            <th>Thuc nhan</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    } catch(e) {
        const wrap = document.getElementById('payrollContent');
        if (wrap) wrap.innerHTML = '<div class="payroll-empty"><i class="fas fa-exclamation-circle"></i> Loi tai du lieu luong</div>';
        console.error('loadPayroll error:', e);
    }
}

// ════════════════════════════════════════
// INIT
// ════════════════════════════════════════
function profileInit() {
    loadProfile();
    initGpsCheckin();
    loadPayroll();

    const newpwEl = document.getElementById('inp_newpw');
    if (!newpwEl) return;
    newpwEl.addEventListener('input', function() {
        const val  = this.value;
        const wrap = document.getElementById('pwStrengthWrap');
        const fill = document.getElementById('pwStrengthFill');
        const text = document.getElementById('pwStrengthText');
        if (!val) { wrap.style.display = 'none'; return; }
        wrap.style.display = 'flex';
        let score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        const levels = [
            { pct:'20%', color:'#ef4444', label:'Rat yeu'    },
            { pct:'40%', color:'#f97316', label:'Yeu'        },
            { pct:'60%', color:'#eab308', label:'Trung binh' },
            { pct:'80%', color:'#22c55e', label:'Manh'       },
            { pct:'100%',color:'#10b981', label:'Rat manh'   }
        ];
        const lv = levels[Math.min(score, 4)];
        fill.style.width      = lv.pct;
        fill.style.background = lv.color;
        text.textContent      = lv.label;
        text.style.color      = lv.color;
    });
}

// Chạy ngay nếu DOM đã ready (script được inject động), hoặc đợi DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', profileInit);
} else {
    profileInit();
}