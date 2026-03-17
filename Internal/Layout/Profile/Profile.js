const API = '/PHP/ELite_GYM/Internal/Layout/Profile/Profile_function.php';


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
        const res = await fetch(`${API}?action=get_profile`);
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

        // Avatar initial
        const initials = (p.full_name || '?').charAt(0).toUpperCase();
        document.getElementById('avatarCircle').textContent = initials;

        // Stats
        if (p.hire_date) {
            document.getElementById('statHireDate').textContent = formatDate(p.hire_date);
        }
        const genderMap = { Male: 'Nam', Female: 'Nữ', Other: 'Khác' };
        document.getElementById('statGender').textContent = genderMap[p.gender] || '–';

    } catch(e) {
        console.error('loadProfile error:', e);
    }
}

function formatDate(str) {
    if (!str) return '–';
    const d = new Date(str);
    if (isNaN(d)) return str;
    return d.toLocaleDateString('vi-VN');
}

// ===== EDIT TOGGLE =====
let editMode = false;

function toggleEdit() {
    editMode = !editMode;
    const fields  = ['inp_name','inp_phone','inp_email','inp_dob','inp_address'];
    const btn     = document.getElementById('editToggleBtn');
    const actions = document.getElementById('formActions');
    const gender  = document.getElementById('inp_gender');

    if (editMode) {
        fields.forEach(id => { document.getElementById(id).removeAttribute('readonly'); });
        gender.removeAttribute('disabled');
        btn.innerHTML = '<i class="fas fa-times"></i> Huỷ';
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
    btn.innerHTML = '<i class="fas fa-pen"></i> Chỉnh sửa';
    btn.classList.remove('active');
    actions.classList.add('hidden');
    loadProfile();
}

// ===== SAVE PROFILE =====
async function saveProfile(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSave');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';

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
            showToast('toast', 'Cập nhật thông tin thành công!', 'success');
            document.getElementById('displayName').textContent = payload.full_name;
            document.getElementById('avatarCircle').textContent = payload.full_name.charAt(0).toUpperCase();
            cancelEdit();
        } else {
            showToast('toast', d.message || 'Có lỗi xảy ra.', 'error');
        }
    } catch(err) {
        showToast('toast', 'Lỗi kết nối máy chủ.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Lưu thay đổi';
    }
}

// ===== CHANGE PASSWORD =====
async function changePassword(e) {
    e.preventDefault();
    const btn    = document.getElementById('btnChangePw');
    const newPw  = document.getElementById('inp_newpw').value;
    const confPw = document.getElementById('inp_confpw').value;

    if (newPw !== confPw) { showToast('pwToast','Mật khẩu xác nhận không khớp!','error'); return; }
    if (newPw.length < 6) { showToast('pwToast','Mật khẩu mới phải có ít nhất 6 ký tự!','error'); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang cập nhật...';

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
            showToast('pwToast', 'Đổi mật khẩu thành công!', 'success');
            document.getElementById('passwordForm').reset();
            document.getElementById('pwStrengthWrap').style.display = 'none';
        } else {
            showToast('pwToast', d.message || 'Mật khẩu hiện tại không đúng.', 'error');
        }
    } catch(err) {
        showToast('pwToast', 'Lỗi kết nối máy chủ.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Cập nhật mật khẩu';
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
// GPS CHECK-IN MODULE — gọi Profile_function.php
// ════════════════════════════════════════════════════════════

let gpsWatchId   = null;
let userLat      = null;
let userLng      = null;
let gymSettings  = { lat: null, lng: null, radius: 100, check: 1, name: 'Elite Gym' };

// ── Haversine distance (metres) ──
function haversine(lat1, lng1, lat2, lng2) {
    const R  = 6371000;
    const dL = (lat2 - lat1) * Math.PI / 180;
    const dl = (lng2 - lng1) * Math.PI / 180;
    const a  = Math.sin(dL/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dl/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// ── Load gym settings + today status ──
async function initGpsCheckin() {
    // 1. Lấy settings từ GPS_function.php
    try {
        const res  = await fetch(`${API}?action=get_gym_settings`);
        const text = await res.text();
        let d;
        try { d = JSON.parse(text); }
        catch(e) { console.error('GPS settings parse error, raw:', text.substring(0,200)); d = {ok:false}; }
        if (d.ok) {
            gymSettings = { lat: d.lat, lng: d.lng, radius: d.radius, check: d.check, name: d.name };
            const nm = document.getElementById('gpsGymName');
            if (nm) nm.textContent = d.name || 'Elite Gym';
        } else {
            console.warn('get_gym_settings:', d.msg);
            // Vẫn cho phép checkin nếu không lấy được settings
            gymSettings.check = 0;
        }
    } catch(e) {
        console.error('initGpsCheckin fetch error:', e);
        gymSettings.check = 0; // fallback: không kiểm tra vị trí
    }

    // 2. Lấy trạng thái chấm công hôm nay
    loadTodayStatus();

    // 3. Load lịch sử
    loadGpsHistory();

    // 4. Bắt đầu theo dõi GPS
    startGpsWatch();
}

// ── Start GPS watch ──
function startGpsWatch() {
    if (!navigator.geolocation) {
        setGpsIndicator('off', 'Trình duyệt không hỗ trợ GPS');
        // Nếu không có GPS và không cần check → vẫn cho phép checkin
        if (!gymSettings.lat || gymSettings.check == 0) {
            document.getElementById('btnCheckIn').disabled = false;
        }
        return;
    }
    setGpsIndicator('loading', 'Đang lấy vị trí GPS...');

    gpsWatchId = navigator.geolocation.watchPosition(
        pos => {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;
            const acc = Math.round(pos.coords.accuracy);

            // Không bật kiểm tra vị trí → cho phép checkin tự do
            if (!gymSettings.lat || gymSettings.check == 0) {
                setGpsIndicator('ok', `GPS sẵn sàng (±${acc}m)`);
                document.getElementById('btnCheckIn').disabled = false;
                return;
            }

            // Accuracy quá thấp (> 2000m = dùng IP, không đáng tin)
            // Vẫn hiển thị cảnh báo nhưng KHÔNG chặn checkin hoàn toàn
            // vì đây là localhost — để admin bật/tắt kiểm tra qua location_check
            const dist = Math.round(haversine(userLat, userLng, gymSettings.lat, gymSettings.lng));
            const badge = document.getElementById('gpsDistBadge');
            if (badge) { badge.style.display = ''; badge.textContent = `Cách ~${dist}m`; }

            if (acc > 500) {
                // GPS kém chính xác (IP-based) — thông báo nhưng vẫn cho phép nếu check=1
                // Admin nên tắt location_check khi nhân viên làm việc remote
                setGpsIndicator('ok', `GPS sẵn sàng (±${acc}m) — độ chính xác thấp`);
                document.getElementById('btnCheckIn').disabled = false;
                return;
            }

            if (dist <= gymSettings.radius) {
                setGpsIndicator('ok', `Trong phạm vi phòng tập (±${acc}m)`);
                document.getElementById('btnCheckIn').disabled = false;
            } else {
                setGpsIndicator('far', `Ngoài phạm vi — cách ${dist}m (cho phép ${gymSettings.radius}m)`);
                document.getElementById('btnCheckIn').disabled = true;
            }
        },
        err => {
            const msgs = {
                1: 'GPS bị từ chối — hãy cho phép trong cài đặt trình duyệt',
                2: 'Không lấy được vị trí GPS',
                3: 'Hết thời gian GPS'
            };
            setGpsIndicator('off', msgs[err.code] || 'Lỗi GPS');
            // Nếu GPS lỗi và không cần check vị trí → vẫn cho checkin
            if (!gymSettings.lat || gymSettings.check == 0) {
                document.getElementById('btnCheckIn').disabled = false;
            }
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
    );
}

function setGpsIndicator(state, text) {
    const ind  = document.getElementById('gpsIndicator');
    const dot  = document.getElementById('gpsDot');
    const txt  = document.getElementById('gpsText');
    ind.className  = `gps-indicator gps--${state}`;
    txt.textContent = text;
}

// ── Load today status ──
async function loadTodayStatus() {
    try {
        const res  = await fetch(`${API}?action=get_today_status`);
        const text = await res.text();
        let d;
        try { d = JSON.parse(text); }
        catch(e) { console.error('get_today_status parse error:', text.substring(0,200)); return; }

        if (!d.ok) { console.warn('get_today_status:', d.msg); return; }

        const today = new Date();
        const boxDate = document.getElementById('boxDate');
        if (boxDate) boxDate.querySelector('.gps-status-val').textContent =
            today.toLocaleDateString('vi-VN', { weekday:'short', day:'2-digit', month:'2-digit' });

        if (d.record) {
            const r = d.record;
            const statusMap = { Present:'Đúng giờ', Late:'Đi muộn', Absent:'Vắng' };
            const statusBox = document.getElementById('boxStatus');
            if (statusBox) {
                statusBox.querySelector('.gps-status-val').textContent = statusMap[r.status] || r.status;
                if (r.status === 'Present') statusBox.className = 'gps-status-box status--present';
                else if (r.status === 'Late') statusBox.className = 'gps-status-box status--late';
                else statusBox.className = 'gps-status-box status--absent';
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

// ── Check In ──
async function doCheckIn() {
    const btn = document.getElementById('btnCheckIn');
    if (btn) btn.disabled = true;
    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';

    try {
        const fd = new FormData();
        fd.append('action', 'checkin');
        if (userLat !== null) { fd.append('lat', userLat); fd.append('lng', userLng); }

        const res  = await fetch(API, { method:'POST', body: fd });
        const text = await res.text();
        let d;
        try { d = JSON.parse(text); } catch(e) {
            console.error('checkin parse error, raw response:', text.substring(0,500));
            d = {ok:false, msg:'Lỗi server — xem Console để biết chi tiết'};
        }

        if (d.ok) {
            showGpsToast('ok', `✓ Check in lúc ${d.time} — ${d.status === 'Late' ? '⚠ Muộn' : 'Đúng giờ'}`);
            loadTodayStatus();
            loadGpsHistory();
        } else {
            showGpsToast('err', d.msg || 'Không thể check in');
            if (btn) btn.disabled = false;
        }
    } catch(e) {
        showGpsToast('err', 'Lỗi kết nối: ' + e.message);
        if (btn) btn.disabled = false;
    } finally {
        if (btn) btn.innerHTML = '<i class="fas fa-map-marker-alt"></i> Check In';
    }
}

// ── Check Out ──
async function doCheckOut() {
    const btn = document.getElementById('btnCheckOut');
    if (btn) btn.disabled = true;
    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';

    try {
        const fd = new FormData();
        fd.append('action', 'checkout');
        if (userLat !== null) { fd.append('lat', userLat); fd.append('lng', userLng); }

        const res  = await fetch(API, { method:'POST', body: fd });
        const text = await res.text();
        let d;
        try { d = JSON.parse(text); } catch(e) { console.error('checkout parse error:', text.substring(0,200)); d = {ok:false, msg:'Lỗi server'}; }

        if (d.ok) {
            showGpsToast('ok', `✓ Check out lúc ${d.time} — Làm ${d.hours}`);
            loadTodayStatus();
            loadGpsHistory();
        } else {
            showGpsToast('err', d.msg || 'Không thể check out');
            if (btn) btn.disabled = false;
        }
    } catch(e) {
        showGpsToast('err', 'Lỗi kết nối: ' + e.message);
        if (btn) btn.disabled = false;
    } finally {
        if (btn) btn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Check Out';
    }
}

// ── Load history ──
async function loadGpsHistory() {
    const list = document.getElementById('gpsHistoryList');
    if (!list) return;
    try {
        const res  = await fetch(`${API}?action=get_history&limit=7`);
        const text = await res.text();
        let d;
        try { d = JSON.parse(text); }
        catch(e) {
            console.error('get_history parse error:', text.substring(0,200));
            list.innerHTML = `<div style="padding:14px;text-align:center;color:rgba(255,255,255,.3);font-size:.82rem">Lỗi tải dữ liệu</div>`;
            return;
        }

        if (!d.ok || !d.records || !d.records.length) {
            list.innerHTML = `<div style="padding:14px;text-align:center;color:rgba(255,255,255,.3);font-size:.82rem">Chưa có dữ liệu chấm công</div>`;
            return;
        }

        const statusMap = { Present:'Đúng giờ', Late:'Muộn', Absent:'Vắng' };
        const badgeMap  = { Present:'badge--present', Late:'badge--late', Absent:'badge--absent' };

        list.innerHTML = d.records.map(r => {
            const cin      = r.check_in  ? r.check_in.slice(0,5)  : '—';
            const cout     = r.check_out ? r.check_out.slice(0,5) : '—';
            const dateStr  = new Date(r.work_date + 'T00:00:00').toLocaleDateString('vi-VN', { day:'2-digit', month:'2-digit', weekday:'short' });
            return `
            <div class="gps-history-item">
                <span class="gps-hi-date">${dateStr}</span>
                <span class="gps-hi-times">
                    <i class="fas fa-sign-in-alt" style="color:#4ade80;font-size:.75rem;margin-right:3px"></i>${cin}
                    &nbsp;&nbsp;
                    <i class="fas fa-sign-out-alt" style="color:#f87171;font-size:.75rem;margin-right:3px"></i>${cout}
                </span>
                <span class="gps-hi-badge ${badgeMap[r.status]||'badge--absent'}">${statusMap[r.status]||r.status}</span>
            </div>`;
        }).join('');
    } catch(e) {
        console.error('loadGpsHistory error:', e);
        if (list) list.innerHTML = `<div style="padding:14px;text-align:center;color:rgba(255,255,255,.3);font-size:.82rem">Lỗi tải dữ liệu</div>`;
    }
}

// ── GPS Toast (inside card) ──
function showGpsToast(type, msg) {
    const el = document.getElementById('gpsToast');
    el.className = `gps-toast gps-toast--${type}`;
    el.innerHTML = `<i class="fas fa-${type==='ok'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.className = 'gps-toast hidden'; }, 5000);
}

// ===== PASSWORD STRENGTH =====
document.addEventListener('DOMContentLoaded', () => {
    loadProfile();
    initGpsCheckin();
    document.getElementById('inp_newpw').addEventListener('input', function() {
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
            { pct:'20%', color:'#ef4444', label:'Rất yếu' },
            { pct:'40%', color:'#f97316', label:'Yếu'     },
            { pct:'60%', color:'#eab308', label:'Trung bình' },
            { pct:'80%', color:'#22c55e', label:'Mạnh'    },
            { pct:'100%',color:'#10b981', label:'Rất mạnh' }
        ];
        const lv = levels[Math.min(score, 4)];
        fill.style.width = lv.pct;
        fill.style.background = lv.color;
        text.textContent = lv.label;
        text.style.color = lv.color;
    });
});
