// ===== CONFIG =====
const API_URL = 'Employee_attendance_tracking_function.php';
let currentPage = 1;
const LIMIT = 20;
let deleteTargetId = null;
let searchTimer = null;
let currentDate = getTodayStr();

// ===== DOM READY =====
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('dateInput').value = currentDate;
    loadStats();
    loadAttendance();

    document.getElementById('dateInput').addEventListener('change', (e) => {
        currentDate = e.target.value;
        currentPage = 1;
        loadStats();
        loadAttendance();
    });

    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { currentPage = 1; loadAttendance(); }, 300);
    });

    document.getElementById('statusFilter').addEventListener('change', () => { currentPage = 1; loadAttendance(); });
});

// ===== LOAD STATS =====
async function loadStats() {
    try {
        const res = await fetch(`${API_URL}?action=get_stats&date=${currentDate}`);
        const d = await res.json();
        if (!d.success) return;
        document.getElementById('statTotal').textContent   = d.total;
        document.getElementById('statPresent').textContent = d.present;
        document.getElementById('statAbsent').textContent  = d.absent;
        document.getElementById('statLate').textContent    = d.late;
        document.getElementById('statLeave').textContent   = d.leave;
    } catch(e) { console.error(e); }
}

// ===== LOAD ATTENDANCE TABLE =====
async function loadAttendance() {
    const search = document.getElementById('searchInput').value.trim();
    const status = document.getElementById('statusFilter').value;

    setTableLoading(true);
    try {
        const url = `${API_URL}?action=get_attendance`
            + `&date=${currentDate}`
            + `&page=${currentPage}&limit=${LIMIT}`
            + `&search=${encodeURIComponent(search)}`
            + `&status=${encodeURIComponent(status)}`;

        const res = await fetch(url);
        const d   = await res.json();
        if (!d.success) { showToast('Loi tai du lieu', 'error'); setTableLoading(false); return; }
        renderTable(d.data);
        renderPagination(d.total, d.totalPages);
        document.getElementById('tableTotal').textContent = `${d.total} nhan vien`;
    } catch(e) {
        showToast('Khong the ket noi server', 'error');
        setTableLoading(false); // FIX 2: clear spinner on network error
    }
}

// FIX 2: setTableLoading(false) now properly clears the spinner
function setTableLoading(state) {
    if (state) {
        document.getElementById('attendanceTbody').innerHTML =
            `<tr><td colspan="7" style="text-align:center;padding:40px;color:rgba(255,255,255,0.3)">
                <i class="fas fa-spinner fa-spin" style="font-size:24px"></i>
             </td></tr>`;
    } else {
        const tbody = document.getElementById('attendanceTbody');
        if (tbody.querySelector('.fa-spinner')) {
            tbody.innerHTML = `<tr><td colspan="7">
                <div class="empty-state">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Khong the tai du lieu. Vui long thu lai.</p>
                </div>
            </td></tr>`;
        }
    }
}

// ===== RENDER TABLE =====
function renderTable(rows) {
    const tbody = document.getElementById('attendanceTbody');

    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="7">
            <div class="empty-state">
                <i class="fas fa-clipboard-check"></i>
                <p>Khong tim thay nhan vien nao</p>
            </div>
        </td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(r => {
        const isFemale = r.gender === 'Female';
        const initials = getInitials(r.full_name);
        const hasChamCong = !!r.attendance_id;

        // Status badge
        let badge = `<span class="status-badge pending"><i class="fas fa-minus-circle"></i> Not recorded</span>`;
        if (hasChamCong) {
            const map = {
                'Present':  `<span class="status-badge present"><i class="fas fa-check-circle"></i> Present</span>`,
                'Absent':   `<span class="status-badge absent"><i class="fas fa-times-circle"></i> Absent</span>`,
                'Late':     `<span class="status-badge late"><i class="fas fa-clock"></i> Late</span>`,
                'Day Off':  `<span class="status-badge leave"><i class="fas fa-umbrella-beach"></i> Day Off</span>`,
                'On Leave': `<span class="status-badge leave"><i class="fas fa-umbrella-beach"></i> On Leave</span>`,
            };
            badge = map[r.status] || badge;
        }

        // Times
        const gioVao = r.check_in
            ? `<span class="time-cell"><i class="fas fa-sign-in-alt"></i> ${r.check_in.substring(0,5)}</span>`
            : `<span class="time-dash">&#8212;</span>`;
        const gioRa = r.check_out
            ? `<span class="time-cell"><i class="fas fa-sign-out-alt"></i> ${r.check_out.substring(0,5)}</span>`
            : `<span class="time-dash">&#8212;</span>`;

        // Hours
        let hoursBadge = `<span class="hours-badge none">&#8212;</span>`;
        if (r.check_in && r.check_out) {
            const mins = calcMinutes(r.check_in, r.check_out);
            if (mins > 0) {
                const h = Math.floor(mins / 60), m = mins % 60;
                const label = m > 0 ? `${h}h${String(m).padStart(2,'0')}` : `${h}h`;
                hoursBadge = `<span class="hours-badge ${mins >= 480 ? 'good' : 'short'}">${label}</span>`;
            }
        }

        // Action buttons
        const editBtn = `<button class="btn-action-sm" title="${hasChamCong ? 'Sua cham cong' : 'Cham cong'}"
            onclick="openAttModal(${r.employee_id}, '${escHtml(r.full_name)}', '${r.gender || ''}', '${hasChamCong ? r.attendance_id : ''}', ${hasChamCong ? `'${r.status}','${r.check_in||''}','${r.check_out||''}'` : "null,null,null"})">
            <i class="fas ${hasChamCong ? 'fa-pen' : 'fa-fingerprint'}"></i>
        </button>`;

        const delBtn = hasChamCong
            ? `<button class="btn-action-sm delete" title="Xoa cham cong"
                onclick="confirmDelete(${r.attendance_id}, '${escHtml(r.full_name)}')">
                <i class="fas fa-trash"></i>
            </button>`
            : '';

        // FIX 3: support both 'note' and 'notes' column names
        const noteText = r.note || r.notes || '';

        return `<tr>
            <td>
                <div class="emp-name-cell">
                    <div class="emp-avatar ${isFemale ? 'female' : ''}">${initials}</div>
                    <div>
                        <div class="emp-name-text">${escHtml(r.full_name)}</div>
                        <div class="emp-id-text">#${r.employee_id}</div>
                    </div>
                </div>
            </td>
            <td>${badge}</td>
            <td>${gioVao}</td>
            <td>${gioRa}</td>
            <td>${hoursBadge}</td>
            <td style="font-size:12px;color:rgba(255,255,255,0.4);max-width:160px">
                ${noteText ? escHtml(noteText) : '&#8212;'}
            </td>
            <td>
                <div class="action-group">
                    ${editBtn}
                    ${delBtn}
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ===== PAGINATION =====
function renderPagination(total, totalPages) {
    const info = document.getElementById('paginationInfo');
    const container = document.getElementById('paginationControls');
    const from = Math.min((currentPage - 1) * LIMIT + 1, total);
    const to   = Math.min(currentPage * LIMIT, total);
    info.textContent = total > 0 ? `Hien thi ${from}-${to} trong ${total}` : 'Khong co du lieu';

    let html = `<button class="btn-page" onclick="goPage(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''}>
        <i class="fas fa-chevron-left"></i></button>`;
    const start = Math.max(1, currentPage - 2);
    const end   = Math.min(totalPages, start + 4);
    for (let i = start; i <= end; i++) {
        html += `<button class="btn-page ${i === currentPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
    }
    html += `<button class="btn-page" onclick="goPage(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''}>
        <i class="fas fa-chevron-right"></i></button>`;
    container.innerHTML = html;
}

function goPage(p) {
    if (p < 1) return;
    currentPage = p;
    loadAttendance();
}

// ===== ATTENDANCE MODAL =====
// FIX 1: All occurrences of undefined `attendanceId` replaced with `chamCongId`
function openAttModal(empId, empName, gioiTinh, chamCongId, trangThai, gioVao, gioRa) {
    document.getElementById('attEmpId').value      = empId;
    document.getElementById('attChamCongId').value = chamCongId || ''; // FIX 1

    const isFemale = gioiTinh === 'Female';
    const initials  = getInitials(empName);
    const isEdit    = !!chamCongId; // FIX 1

    document.getElementById('attModalTitle').innerHTML =
        `<i class="fas fa-fingerprint" style="color:#d4a017;margin-right:8px"></i>${isEdit ? 'Sua cham cong' : 'Cham cong'} - ${escHtml(empName)}`;

    document.getElementById('attEmpInfo').innerHTML = `
        <div class="modal-emp-avatar ${isFemale ? 'female' : ''}">${initials}</div>
        <div>
            <div class="modal-emp-name">${escHtml(empName)}</div>
            <div class="modal-emp-sub">Ngay: ${formatDateVN(currentDate)}</div>
        </div>`;

    document.getElementById('fTrangThai').value = trangThai || 'Present';
    document.getElementById('fGioVao').value    = gioVao ? gioVao.substring(0,5) : '';
    document.getElementById('fGioRa').value     = gioRa  ? gioRa.substring(0,5)  : '';

    onStatusChange();
    document.getElementById('attendanceModal').classList.add('active');
}

function closeAttModal() {
    document.getElementById('attendanceModal').classList.remove('active');
}

function onStatusChange() {
    const status = document.getElementById('fTrangThai').value;
    const showTime = (status === 'Present' || status === 'Late');
    document.getElementById('gioVaoGroup').style.display = showTime ? '' : 'none';
    document.getElementById('gioRaGroup').style.display  = showTime ? '' : 'none';
}

function setTime(fieldId, val) {
    document.getElementById(fieldId).value = val;
}

function setNow(fieldId) {
    const now = new Date();
    const hh  = String(now.getHours()).padStart(2,'0');
    const mm  = String(now.getMinutes()).padStart(2,'0');
    document.getElementById(fieldId).value = `${hh}:${mm}`;
}

async function saveAttendance() {
    const empId        = document.getElementById('attEmpId').value;
    const attendanceId = document.getElementById('attChamCongId').value;
    const trangThai    = document.getElementById('fTrangThai').value;
    const gioVao       = document.getElementById('fGioVao').value;
    const gioRa        = document.getElementById('fGioRa').value;

    const body = new FormData();
    body.append('action', attendanceId ? 'update_attendance' : 'add_attendance');
    body.append('employee_id', empId);
    if (attendanceId) body.append('attendance_id', attendanceId);
    body.append('work_date', currentDate);
    body.append('status', trangThai);
    body.append('check_in', gioVao);
    body.append('check_out', gioRa);

    try {
        const res  = await fetch(API_URL, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeAttModal();
            loadAttendance();
            loadStats();
        } else {
            showToast(data.message, 'error');
        }
    } catch(e) {
        showToast('Loi ket noi server', 'error');
    }
}

// ===== BULK MODAL =====
async function openBulkModal() {
    document.getElementById('bulkModal').classList.add('active');
    document.getElementById('bulkEmpItems').innerHTML =
        `<div style="padding:20px;text-align:center;color:rgba(255,255,255,0.3)"><i class="fas fa-spinner fa-spin"></i></div>`;
    document.getElementById('checkAll').checked = false;

    try {
        const res = await fetch(`${API_URL}?action=get_unchecked&date=${currentDate}`);
        const d   = await res.json();
        if (!d.success || !d.data.length) {
            document.getElementById('bulkEmpItems').innerHTML =
                `<div style="padding:20px;text-align:center;color:rgba(255,255,255,0.35);font-size:13px">
                    <i class="fas fa-check-circle" style="color:#34d399;margin-right:6px"></i>
                    Tat ca nhan vien da duoc cham cong hom nay
                </div>`;
            return;
        }
        document.getElementById('bulkEmpItems').innerHTML = d.data.map(emp => `
            <div class="bulk-emp-item" onclick="toggleCheck(${emp.employee_id})">
                <input type="checkbox" class="bulk-check" id="bulk_${emp.employee_id}" value="${emp.employee_id}">
                <div class="emp-avatar ${emp.gender === 'Female' ? 'female' : ''}" style="width:32px;height:32px;font-size:13px">
                    ${getInitials(emp.full_name)}
                </div>
                <div>
                    <div style="font-size:13px;font-weight:600;color:#fff">${escHtml(emp.full_name)}</div>
                    <div style="font-size:11px;color:rgba(255,255,255,0.35)">#${emp.employee_id}</div>
                </div>
            </div>`).join('');
    } catch(e) {
        document.getElementById('bulkEmpItems').innerHTML =
            `<div style="padding:20px;text-align:center;color:#f87171;font-size:13px">Loi tai du lieu</div>`;
    }
}

function toggleCheck(empId) {
    const cb = document.getElementById(`bulk_${empId}`);
    if (cb) cb.checked = !cb.checked;
}

function toggleCheckAll(masterCb) {
    document.querySelectorAll('.bulk-check').forEach(cb => cb.checked = masterCb.checked);
}

function closeBulkModal() {
    document.getElementById('bulkModal').classList.remove('active');
}

async function saveBulk() {
    const status  = document.getElementById('fBulkStatus').value;
    const gioVao  = document.getElementById('fBulkGioVao').value;
    const checked = [...document.querySelectorAll('.bulk-check:checked')].map(cb => cb.value);

    if (!checked.length) { showToast('Vui long chon it nhat mot nhan vien', 'warning'); return; }

    const body = new FormData();
    body.append('action', 'bulk_attendance');
    body.append('work_date', currentDate);
    body.append('status', status);
    body.append('check_in', gioVao);
    body.append('employee_ids', JSON.stringify(checked));

    try {
        const res  = await fetch(API_URL, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeBulkModal();
            loadAttendance();
            loadStats();
        } else {
            showToast(data.message, 'error');
        }
    } catch(e) {
        showToast('Loi ket noi server', 'error');
    }
}

// ===== DELETE =====
function confirmDelete(attendanceId, name) {
    deleteTargetId = attendanceId;
    document.getElementById('deleteAttName').textContent = name;
    document.getElementById('confirmModal').classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('active');
    deleteTargetId = null;
}

async function doDelete() {
    if (!deleteTargetId) return;
    const body = new FormData();
    body.append('action', 'delete_attendance');
    body.append('attendance_id', deleteTargetId);

    try {
        const res  = await fetch(API_URL, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeConfirmModal();
            loadAttendance();
            loadStats();
        } else {
            showToast(data.message, 'error');
            closeConfirmModal();
        }
    } catch(e) {
        showToast('Loi ket noi', 'error');
    }
}

// ===== DATE NAV =====
function shiftDate(delta) {
    const d = new Date(currentDate);
    d.setDate(d.getDate() + delta);
    currentDate = d.toISOString().split('T')[0];
    document.getElementById('dateInput').value = currentDate;
    currentPage = 1;
    loadStats();
    loadAttendance();
}

function goToday() {
    currentDate = getTodayStr();
    document.getElementById('dateInput').value = currentDate;
    currentPage = 1;
    loadStats();
    loadAttendance();
}

// ===== TOAST =====
function showToast(message, type = 'success') {
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle' };
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.success}"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.4s';
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

// ===== HELPERS =====
function getTodayStr() {
    return new Date().toISOString().split('T')[0];
}

function formatDateVN(str) {
    if (!str) return '';
    const [y, m, d] = str.split('-');
    return `${d}/${m}/${y}`;
}

function calcMinutes(start, end) {
    if (!start || !end) return 0;
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    return (eh * 60 + em) - (sh * 60 + sm);
}

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(' ');
    return parts[parts.length - 1].charAt(0).toUpperCase();
}

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
        .replace(/'/g,'&#039;');
}

// Close modal on overlay click + ESC
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active');
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
});
