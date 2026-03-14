const API = '/PHP/ELITE_GYM/Internal/Layout/Profile/Profile_function.php';

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

// ===== PASSWORD STRENGTH =====
document.addEventListener('DOMContentLoaded', () => {
    loadProfile();
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
