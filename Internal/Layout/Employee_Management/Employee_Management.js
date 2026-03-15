// ===== CONFIG =====
const API_URL = 'Employee_Management_Function.php';
let currentPage = 1;
const LIMIT = 15;
let deleteTargetId = null;
let searchTimer = null;

// State for salary modal
let salaryHourlyWage = 0;   // lương/giờ của employees
let salaryTotalHours  = 0;   // tổng giờ làm đã fetch
let salaryBaseCalc = 0;  // = tongGio × luongGio

// ===== DOM READY =====
document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadEmployees();

    const now = new Date();
    document.getElementById('fSalaryMonth').value = now.getMonth() + 1;
    document.getElementById('fSalaryYear').value  = now.getFullYear();

    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { currentPage = 1; loadEmployees(); }, 350);
    });

    document.getElementById('genderFilter').addEventListener('change', () => { currentPage = 1; loadEmployees(); });
    document.getElementById('sortFilter').addEventListener('change', () => { currentPage = 1; loadEmployees(); });

    // Live salary preview when adjustments change
    ['fPhuCap','fThuong','fKhauTru'].forEach(id => {
        document.getElementById(id).addEventListener('input', updateSalaryPreview);
    });
});

// ===== LOAD STATS =====
async function loadStats() {
    try {
        const res = await fetch(`${API_URL}?action=get_stats`);
        const d = await res.json();
        if (!d.success) return;
        document.getElementById('statTotal').textContent     = d.total;
        document.getElementById('statActive').textContent    = d.active;
        document.getElementById('statNewMonth').textContent  = d.new_month;
        document.getElementById('statAvgSalary').textContent = d.avg_salary ? formatMoneyShort(d.avg_salary) : '—';
    } catch(e) { console.error(e); }
}

// ===== LOAD EMPLOYEES TABLE =====
async function loadEmployees() {
    const search = document.getElementById('searchInput').value.trim();
    const gender = document.getElementById('genderFilter').value;
    const sort   = document.getElementById('sortFilter').value;

    setTableLoading(true);

    try {
        const url = `${API_URL}?action=get_employees&page=${currentPage}&limit=${LIMIT}`
            + `&search=${encodeURIComponent(search)}`
            + `&gender=${encodeURIComponent(gender)}`
            + `&sort=${sort}`;

        const res = await fetch(url);
        const d   = await res.json();

        if (!d.success) { showToast('Lỗi tải dữ liệu', 'error'); return; }

        renderTable(d.data);
        renderPagination(d.total, d.totalPages);
        document.getElementById('tableTotal').textContent = `${d.total} employees`;
    } catch(e) {
        showToast('Không thể kết nối server', 'error');
    } finally {
        setTableLoading(false);
    }
}

function setTableLoading(state) {
    if (state) {
        document.getElementById('employeeTbody').innerHTML =
            `<tr><td colspan="9" style="text-align:center;padding:40px;color:rgba(255,255,255,0.3)">
                <i class="fas fa-spinner fa-spin" style="font-size:24px"></i>
             </td></tr>`;
    }
}

// ===== RENDER TABLE =====
function renderTable(employees) {
    const tbody = document.getElementById('employeeTbody');

    if (!employees.length) {
        tbody.innerHTML = `<tr><td colspan="9">
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <p>Không tìm thấy employees nào</p>
            </div>
        </td></tr>`;
        return;
    }

    tbody.innerHTML = employees.map(emp => {
        const isFemale = emp.gender === 'Female';
        const initials = getInitials(emp.full_name);

        const genderBadge = emp.gender === 'Male'
            ? `<span class="gender-badge male"><i class="fas fa-mars"></i> Nam</span>`
            : emp.gender === 'Female'
            ? `<span class="gender-badge female"><i class="fas fa-venus"></i> Nữ</span>`
            : emp.gender === 'Other'
            ? `<span class="gender-badge other"><i class="fas fa-genderless"></i> Khác</span>`
            : `<span style="color:rgba(255,255,255,0.25)">—</span>`;

        const luongGio = emp.hourly_wage && parseFloat(emp.hourly_wage) > 0
            ? `<span style="color:#d4a017;font-weight:600">${formatMoneyShort(emp.hourly_wage)}</span>`
            : `<span style="color:rgba(255,255,255,0.25)">—</span>`;

        const chucVuBadge = emp.position === 'Receptionist'
            ? `<span class="role-badge receptionist"><i class="fas fa-concierge-bell"></i> Receptionist</span>`
            : emp.position === 'Personal Trainer'
            ? `<span class="role-badge trainer"><i class="fas fa-dumbbell"></i> HLV</span>`
            : `<span style="color:rgba(255,255,255,0.25)">—</span>`;

        return `<tr>
            <td>
                <div class="emp-name-cell">
                    <div class="emp-avatar ${isFemale ? 'female' : ''}">${initials}</div>
                    <div>
                        <div class="emp-name-text">${escHtml(emp.full_name)}</div>
                        <div class="emp-id-text">#${emp.employee_id}</div>
                    </div>
                </div>
            </td>
            <td>${chucVuBadge}</td>
            <td>${genderBadge}</td>
            <td style="color:rgba(255,255,255,0.6)">${formatDate(emp.date_of_birth)}</td>
            <td>${emp.phone ? escHtml(emp.phone) : '<span style="color:rgba(255,255,255,0.25)">—</span>'}</td>
            <td style="color:rgba(255,255,255,0.6);font-size:13px">${emp.email ? escHtml(emp.email) : '<span style="color:rgba(255,255,255,0.25)">—</span>'}</td>
            <td style="color:rgba(255,255,255,0.6)">${formatDate(emp.hire_date)}</td>
            <td>${luongGio}</td>
            <td>
                <div class="action-group">
                    <button class="btn-action-sm" title="Xem chi tiết" onclick="openDetail(${emp.employee_id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-action-sm" title="Chỉnh sửa" onclick="openEdit('${escJson(emp)}')">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="btn-action-sm salary" title="Kết toán lương" onclick="openSalaryModal(${emp.employee_id}, '${escHtml(emp.full_name)}', '${emp.gender || ''}')">
                        <i class="fas fa-coins"></i>
                    </button>
                    <button class="btn-action-sm attend" title="Chấm công nhanh" onclick="openQuickAttModal(${emp.employee_id}, '${escHtml(emp.full_name)}', '${emp.gender || ''}')">
                        <i class="fas fa-fingerprint"></i>
                    </button>
                    <button class="btn-action-sm delete" title="Xóa" onclick="confirmDelete(${emp.employee_id}, '${escHtml(emp.full_name)}')">
                        <i class="fas fa-trash"></i>
                    </button>
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
    info.textContent = total > 0 ? `Hiển thị ${from}–${to} trong ${total} employees` : 'Không có dữ liệu';

    let html = `<button class="btn-page" onclick="goPage(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''}>
        <i class="fas fa-chevron-left"></i>
    </button>`;

    const start = Math.max(1, currentPage - 2);
    const end   = Math.min(totalPages, start + 4);
    for (let i = start; i <= end; i++) {
        html += `<button class="btn-page ${i === currentPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
    }

    html += `<button class="btn-page" onclick="goPage(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''}>
        <i class="fas fa-chevron-right"></i>
    </button>`;
    container.innerHTML = html;
}

function goPage(p) {
    if (p < 1) return;
    currentPage = p;
    loadEmployees();
}

// ===== ADD MODAL =====
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Thêm employees mới';
    document.getElementById('employeeId').value       = '';
    document.getElementById('fHoTen').value           = '';
    document.getElementById('fNgaySinh').value        = '';
    document.getElementById('fGioiTinh').value        = '';
    document.getElementById('fChucVu').value          = '';
    document.getElementById('fSoDienThoai').value     = '';
    document.getElementById('fEmail').value           = '';
    document.getElementById('fNgayVaoLam').value      = '';
    document.getElementById('fTenDangNhap').value     = '';
    document.getElementById('fMatKhau').value         = '';
    document.getElementById('fDiaChi').value          = '';
    document.getElementById('fLuongCoBanEmp').value   = '';

    // Chế độ thêm mới: tài khoản bắt buộc
    document.getElementById('accountRequiredBadge').style.display = '';
    document.getElementById('usernameRequired').style.display = '';
    document.getElementById('passwordGroup').style.display = '';
    document.getElementById('editAccountNote').style.display = 'none';
    document.getElementById('pwHint').style.display = '';
    document.getElementById('accountSectionTitle').textContent = 'Tài khoản đăng nhập (bắt buộc)';

    document.getElementById('employeeModal').classList.add('active');
    setTimeout(() => document.getElementById('fHoTen').focus(), 100);
}

// ===== EDIT MODAL =====
window.openEdit = function(encoded) {
    let emp;
    try { emp = JSON.parse(decodeURIComponent(encoded)); } catch(e) { return; }

    document.getElementById('modalTitle').textContent = 'Chỉnh sửa employees';
    document.getElementById('employeeId').value       = emp.employee_id;
    document.getElementById('fHoTen').value           = emp.full_name || '';
    document.getElementById('fNgaySinh').value        = emp.date_of_birth || '';
    document.getElementById('fGioiTinh').value        = emp.gender || '';
    document.getElementById('fChucVu').value          = emp.position || '';
    document.getElementById('fSoDienThoai').value     = emp.phone || '';
    document.getElementById('fEmail').value           = emp.email || '';
    document.getElementById('fNgayVaoLam').value      = emp.hire_date || '';
    document.getElementById('fTenDangNhap').value     = emp.username || '';
    document.getElementById('fMatKhau').value         = '';
    document.getElementById('fDiaChi').value          = emp.address || '';
    document.getElementById('fLuongCoBanEmp').value   = emp.hourly_wage || '';

    // Chế độ sửa: tài khoản không bắt buộc
    document.getElementById('accountRequiredBadge').style.display = 'none';
    document.getElementById('usernameRequired').style.display = 'none';
    document.getElementById('passwordGroup').style.display = 'none';
    document.getElementById('editAccountNote').style.display = '';
    document.getElementById('accountSectionTitle').textContent = 'Tài khoản đăng nhập';

    document.getElementById('employeeModal').classList.add('active');
    setTimeout(() => document.getElementById('fHoTen').focus(), 100);
};

function closeEmployeeModal() {
    document.getElementById('employeeModal').classList.remove('active');
}

// ===== SAVE EMPLOYEE =====
async function saveEmployee() {
    const id          = document.getElementById('employeeId').value;
    const hoTen       = document.getElementById('fHoTen').value.trim();
    const ngaySinh    = document.getElementById('fNgaySinh').value;
    const gioiTinh    = document.getElementById('fGioiTinh').value;
    const chucVu      = document.getElementById('fChucVu').value;
    const soDienThoai = document.getElementById('fSoDienThoai').value.trim();
    const email       = document.getElementById('fEmail').value.trim();
    const ngayVaoLam  = document.getElementById('fNgayVaoLam').value;
    const tenDangNhap = document.getElementById('fTenDangNhap').value.trim();
    const matKhau     = document.getElementById('fMatKhau').value;
    const diaChi      = document.getElementById('fDiaChi').value.trim();
    const luongCoBan  = document.getElementById('fLuongCoBanEmp').value;

    if (!hoTen) { showToast('Vui lòng nhập họ tên employees', 'warning'); return; }

    // Khi thêm mới: bắt buộc phải có tên đăng nhập
    if (!id) {
        if (!tenDangNhap) {
            showToast('Tên đăng nhập là bắt buộc khi thêm employees mới', 'warning');
            document.getElementById('fTenDangNhap').focus();
            return;
        }
        if (tenDangNhap.length < 3) {
            showToast('Tên đăng nhập phải có ít nhất 3 ký tự', 'warning');
            document.getElementById('fTenDangNhap').focus();
            return;
        }
    }

    const body = new FormData();
    body.append('action', id ? 'update_employee' : 'add_employee');
    if (id) body.append('id', id);
    body.append('full_name', hoTen);
    body.append('date_of_birth', ngaySinh);
    body.append('gender', gioiTinh);
    body.append('position', chucVu);
    body.append('phone', soDienThoai);
    body.append('email', email);
    body.append('hire_date', ngayVaoLam);
    body.append('username', tenDangNhap);
    body.append('password', matKhau);
    body.append('address', diaChi);
    body.append('hourly_wage', luongCoBan || 0);

    try {
        const res  = await fetch(API_URL, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeEmployeeModal();
            loadEmployees();
            loadStats();
        } else {
            showToast(data.message, 'error');
        }
    } catch(e) {
        showToast('Lỗi kết nối server', 'error');
    }
}

// ===== TOGGLE PASSWORD VISIBILITY =====
function togglePassword() {
    const input = document.getElementById('fMatKhau');
    const icon  = document.getElementById('pwEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// ===== CONFIRM DELETE =====
function confirmDelete(id, name) {
    deleteTargetId = id;
    document.getElementById('deleteEmployeeName').textContent = name;
    document.getElementById('confirmModal').classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('active');
    deleteTargetId = null;
}

async function doDelete() {
    if (!deleteTargetId) return;
    const body = new FormData();
    body.append('action', 'delete_employee');
    body.append('id', deleteTargetId);

    try {
        const res  = await fetch(API_URL, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeConfirmModal();
            loadEmployees();
            loadStats();
        } else {
            showToast(data.message, 'error');
            closeConfirmModal();
        }
    } catch(e) {
        showToast('Lỗi kết nối', 'error');
    }
}

// ===== DETAIL MODAL =====
async function openDetail(id) {
    document.getElementById('detailModal').classList.add('active');
    document.getElementById('detailContent').innerHTML =
        `<div style="text-align:center;padding:40px;color:rgba(255,255,255,0.4)">
            <i class="fas fa-spinner fa-spin" style="font-size:28px"></i>
         </div>`;

    try {
        const res = await fetch(`${API_URL}?action=get_detail&id=${id}`);
        const d   = await res.json();
        if (!d.success) {
            document.getElementById('detailContent').innerHTML = '<p style="color:#f87171;padding:20px">Lỗi tải dữ liệu</p>';
            return;
        }
        renderDetail(d);
    } catch(e) {
        document.getElementById('detailContent').innerHTML = '<p style="color:#f87171;padding:20px">Lỗi kết nối</p>';
    }
}

function renderDetail(d) {
    const emp = d.employee;
    const isFemale = emp.gender === 'Female';
    const initials  = getInitials(emp.full_name);
    const gioiTinhLabel = emp.gender === 'Male' ? 'Nam' : emp.gender === 'Female' ? 'Nữ' : emp.gender === 'Other' ? 'Khác' : '—';
    const chucVuLabel   = emp.position || '—';
    const chucVuColor   = emp.position === 'Personal Trainer' ? '#d4a017' : emp.position === 'Receptionist' ? '#60a5fa' : 'rgba(255,255,255,0.6)';

    const salaryHtml = d.salaries && d.salaries.length
        ? `<table class="salary-table">
            <thead><tr><th>Month</th><th>Hours</th><th>Base</th><th>Allowance</th><th>Bonus</th><th>Deduction</th><th>Net Salary</th></tr></thead>
            <tbody>${d.salaries.map(s => `<tr>
                <td>${s.month}/${s.year}</td>
                <td style="color:rgba(255,255,255,0.6)">${s.total_hours ? parseFloat(s.total_hours).toFixed(1) + 'h' : '—'}</td>
                <td>${formatMoneyShort(s.base_salary)}</td>
                <td>${formatMoneyShort(s.allowance)}</td>
                <td style="color:#34d399">${formatMoneyShort(s.bonus)}</td>
                <td style="color:#f87171">${formatMoneyShort(s.deduction)}</td>
                <td style="color:#d4a017;font-weight:700">${formatMoney(s.net_salary)}</td>
            </tr>`).join('')}</tbody>
           </table>`
        : `<p style="color:rgba(255,255,255,0.35);font-size:13px;padding:8px 0">No payroll records found</p>`;

    document.getElementById('detailContent').innerHTML = `
        <div class="detail-emp-header">
            <div class="detail-emp-avatar ${isFemale ? 'female' : ''}">${initials}</div>
            <div>
                <div class="detail-emp-name">${escHtml(emp.full_name)}</div>
                <div class="detail-emp-id">Employee ID: #${emp.employee_id}</div>
                ${emp.username ? `<div style="font-size:13px;color:rgba(255,255,255,0.45)"><i class="fas fa-user-circle" style="margin-right:5px"></i>${escHtml(emp.username)}</div>` : ''}
            </div>
        </div>

        <div class="detail-section">
            <h4><i class="fas fa-info-circle"></i> Personal Info</h4>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Position</label>
                    <span style="color:${chucVuColor};font-weight:600">${chucVuLabel}</span>
                </div>
                <div class="detail-item">
                    <label>Gender</label>
                    <span>${gioiTinhLabel}</span>
                </div>
                <div class="detail-item">
                    <label>Date of Birth</label>
                    <span>${formatDate(emp.date_of_birth)}</span>
                </div>
                <div class="detail-item">
                    <label>Phone</label>
                    <span>${emp.phone || '—'}</span>
                </div>
                <div class="detail-item">
                    <label>Email</label>
                    <span>${emp.email ? escHtml(emp.email) : '—'}</span>
                </div>
                <div class="detail-item">
                    <label>Hire Date</label>
                    <span>${formatDate(emp.hire_date)}</span>
                </div>
                <div class="detail-item">
                    <label>Seniority</label>
                    <span>${calcSeniority(emp.hire_date)}</span>
                </div>
                <div class="detail-item">
                    <label>Hourly Wage</label>
                    <span style="color:#d4a017;font-weight:600">${emp.hourly_wage && parseFloat(emp.hourly_wage) > 0 ? formatMoney(emp.hourly_wage) + '/giờ' : '—'}</span>
                </div>
                <div class="detail-item" style="grid-column:1/-1">
                    <label>Address</label>
                    <span>${emp.address ? escHtml(emp.address) : '—'}</span>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h4><i class="fas fa-coins"></i> Payroll (${d.salaries ? d.salaries.length : 0} most recent months)</h4>
            ${salaryHtml}
        </div>
    `;
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('active');
}

// ===== SALARY MODAL =====
function openSalaryModal(empId, empName, gioiTinh) {
    // Reset state
    salaryHourlyWage   = 0;
    salaryTotalHours    = 0;
    salaryBaseCalc  = 0;

    document.getElementById('salaryEmpId').value = empId;

    const isFemale = gioiTinh === 'Female';
    const initials  = getInitials(empName);
    document.getElementById('salaryEmpInfo').innerHTML = `
        <div class="modal-emp-avatar ${isFemale ? 'female' : ''}">${initials}</div>
        <div>
            <div class="modal-emp-name">${escHtml(empName)}</div>
            <div class="modal-emp-sub">Employee ID: #${empId}</div>
        </div>`;

    document.getElementById('salaryModalTitle').innerHTML =
        `<i class="fas fa-calculator" style="color:#d4a017;margin-right:8px"></i>Kết toán lương — ${escHtml(empName)}`;

    // Default tháng/năm
    const now = new Date();
    document.getElementById('fSalaryMonth').value = now.getMonth() + 1;
    document.getElementById('fSalaryYear').value  = now.getFullYear();

    // Reset phụ cấp / thưởng / khấu trừ
    document.getElementById('fPhuCap').value  = '0';
    document.getElementById('fThuong').value  = '0';
    document.getElementById('fKhauTru').value = '0';

    // Hide step 2 & 3 and kết toán button
    document.getElementById('salaryStepResult').style.display  = 'none';
    document.getElementById('salaryStepAdjust').style.display  = 'none';
    document.getElementById('btnKetToan').style.display        = 'none';

    document.getElementById('salaryModal').classList.add('active');
}

function closeSalaryModal() {
    document.getElementById('salaryModal').classList.remove('active');
}

// ===== FETCH HOURS WORKED =====
async function fetchHoursWorked() {
    const empId = document.getElementById('salaryEmpId').value;
    const month = document.getElementById('fSalaryMonth').value;
    const year   = document.getElementById('fSalaryYear').value;

    const btn = document.querySelector('.btn-calc');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang tính...';

    try {
        const res = await fetch(`${API_URL}?action=get_hours_worked&employee_id=${empId}&month=${month}&year=${year}`);
        const d   = await res.json();

        if (!d.success) {
            showToast(d.message || 'Lỗi tính giờ', 'error');
            return;
        }

        salaryHourlyWage  = d.hourly_wage || 0;
        salaryTotalHours   = d.total_hours  || 0;
        salaryBaseCalc = salaryHourlyWage * salaryTotalHours;

        // Hiển thị bước 2
        document.getElementById('rsNgayCham').textContent  = d.days_present + ' days';
        document.getElementById('rsTongGio').textContent   = salaryTotalHours.toFixed(1) + ' hours';
        document.getElementById('rsLuongGio').textContent  = salaryHourlyWage > 0 ? formatMoneyShort(salaryHourlyWage) + '/h' : '—';
        document.getElementById('rsLuongTinh').textContent = formatMoneyShort(salaryBaseCalc);

        // Ghi chú nếu thiếu giờ vào/ra
        const missing = d.days_present - d.days_with_time;
        const noteEl = document.getElementById('hoursNote');
        if (missing > 0) {
            noteEl.innerHTML = `<i class="fas fa-exclamation-triangle" style="color:#facc15"></i> 
                ${missing} ngày chưa có giờ vào/ra nên không tính được giờ. 
                Vào <a href="Employee_attendance_tracking/Employee_attendance_tracking.php" style="color:#d4a017">trang chấm công</a> để bổ sung.`;
            noteEl.style.display = 'block';
        } else {
            noteEl.style.display = 'none';
        }

        if (salaryHourlyWage === 0) {
            noteEl.innerHTML = `<i class="fas fa-info-circle" style="color:#60a5fa"></i> 
                Nhân viên chưa có lương/giờ. Vui lòng cập nhật lương cơ bản trong thông tin employees.`;
            noteEl.style.display = 'block';
        }

        document.getElementById('salaryStepResult').style.display = '';
        document.getElementById('salaryStepAdjust').style.display = '';
        document.getElementById('btnKetToan').style.display       = '';

        updateSalaryPreview();

    } catch(e) {
        showToast('Lỗi kết nối server', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-calculator"></i> Tính giờ làm từ chấm công';
    }
}

// ===== UPDATE SALARY PREVIEW =====
function updateSalaryPreview() {
    const allowance  = parseFloat(document.getElementById('fPhuCap').value)  || 0;
    const bonus  = parseFloat(document.getElementById('fThuong').value)  || 0;
    const deduction  = parseFloat(document.getElementById('fKhauTru').value) || 0;
    const total = Math.max(0, salaryBaseCalc + allowance + bonus - deduction);

    document.getElementById('fmLuongTinh').textContent = formatMoney(salaryBaseCalc);
    document.getElementById('fmPhuCap').textContent    = formatMoney(allowance);
    document.getElementById('fmThuong').textContent    = formatMoney(bonus);
    document.getElementById('fmKhauTru').textContent   = formatMoney(deduction);
    document.getElementById('salaryPreview').textContent = formatMoney(total);
}

// ===== SAVE SALARY =====
async function saveSalary() {
    const empId  = document.getElementById('salaryEmpId').value;
    const month  = document.getElementById('fSalaryMonth').value;
    const year    = document.getElementById('fSalaryYear').value;
    const allowance = parseFloat(document.getElementById('fPhuCap').value)  || 0;
    const bonus = parseFloat(document.getElementById('fThuong').value)  || 0;
    const deduction= parseFloat(document.getElementById('fKhauTru').value) || 0;
    const netSalary = Math.max(0, salaryBaseCalc + allowance + bonus - deduction);

    if (salaryHourlyWage === 0 && salaryTotalHours === 0) {
        showToast('Vui lòng tính giờ làm trước', 'warning');
        return;
    }

    const body = new FormData();
    body.append('action', 'save_salary');
    body.append('employee_id', empId);
    body.append('month', month);
    body.append('year', year);
    body.append('base_salary', salaryBaseCalc);   // lương tính được (giờ × đơn giá)
    body.append('allowance', allowance);
    body.append('bonus', bonus);
    body.append('deduction', deduction);
    body.append('net_salary', netSalary);
    body.append('total_hours', salaryTotalHours);

    try {
        const res  = await fetch(API_URL, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeSalaryModal();
            loadStats();
        } else {
            showToast(data.message, 'error');
        }
    } catch(e) {
        showToast('Lỗi kết nối server', 'error');
    }
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
function formatDate(str) {
    if (!str || str === '0000-00-00') return '—';
    const d = new Date(str);
    if (isNaN(d)) return '—';
    return `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()}`;
}

function formatMoney(val) {
    if (!val && val !== 0) return '0 ₫';
    return Number(val).toLocaleString('vi-VN') + ' ₫';
}

function formatMoneyShort(val) {
    const n = Number(val);
    if (!n) return '0 ₫';
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0','') + 'tr ₫';
    if (n >= 1_000)     return (n / 1_000).toFixed(0) + 'k ₫';
    return n + ' ₫';
}

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(' ');
    return parts[parts.length - 1].charAt(0).toUpperCase();
}

function calcSeniority(dateStr) {
    if (!dateStr || dateStr === '0000-00-00') return '—';
    const start = new Date(dateStr);
    if (isNaN(start)) return '—';
    const now   = new Date();
    const months = (now.getFullYear() - start.getFullYear()) * 12 + (now.getMonth() - start.getMonth());
    if (months < 1)  return 'Dưới 1 tháng';
    if (months < 12) return `${months} tháng`;
    const years = Math.floor(months / 12);
    const rem   = months % 12;
    return rem > 0 ? `${years} năm ${rem} tháng` : `${years} năm`;
}

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#039;');
}

function escJson(obj) {
    return encodeURIComponent(JSON.stringify(obj));
}

// ===== QUICK ATTENDANCE MODAL =====
const ATT_API = 'Employee_attendance_tracking/Employee_attendance_tracking_function.php';

function openQuickAttModal(empId, empName, gioiTinh) {
    document.getElementById('qAttEmpId').value = empId;

    const isFemale = gioiTinh === 'Female';
    const initials  = getInitials(empName);
    const today     = new Date().toISOString().split('T')[0];

    document.getElementById('quickAttTitle').innerHTML =
        `<i class="fas fa-fingerprint" style="color:#d4a017;margin-right:8px"></i>Chấm công — ${escHtml(empName)}`;

    document.getElementById('qAttEmpInfo').innerHTML = `
        <div class="modal-emp-avatar ${isFemale ? 'female' : ''}">${initials}</div>
        <div>
            <div class="modal-emp-name">${escHtml(empName)}</div>
            <div class="modal-emp-sub">Employee ID: #${empId}</div>
        </div>`;

    document.getElementById('qAttDate').value   = today;
    document.getElementById('qAttStatus').value = 'Present';
    document.getElementById('qAttGioVao').value = '08:00';
    document.getElementById('qAttGioRa').value  = '';
    qOnStatusChange();
    document.getElementById('quickAttModal').classList.add('active');
}

function closeQuickAttModal() {
    document.getElementById('quickAttModal').classList.remove('active');
}

function qOnStatusChange() {
    const status = document.getElementById('qAttStatus').value;
    const show = (status === 'Present' || status === 'Late');
    document.getElementById('qGioVaoGroup').style.display = show ? '' : 'none';
    document.getElementById('qGioRaGroup').style.display  = show ? '' : 'none';
}

async function saveQuickAtt() {
    const empId     = document.getElementById('qAttEmpId').value;
    const ngay      = document.getElementById('qAttDate').value;
    const trangThai = document.getElementById('qAttStatus').value;
    const gioVao    = document.getElementById('qAttGioVao').value;
    const gioRa     = document.getElementById('qAttGioRa').value;

    if (!ngay) { showToast('Vui lòng chọn ngày', 'warning'); return; }

    const body = new FormData();
    body.append('action', 'add_attendance');
    body.append('employee_id', empId);
    body.append('work_date', ngay);
    body.append('status', trangThai);
    body.append('check_in', gioVao);
    body.append('check_out', gioRa);

    try {
        const res  = await fetch(ATT_API, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeQuickAttModal();
        } else {
            showToast(data.message, 'error');
        }
    } catch(e) {
        showToast('Lỗi kết nối server', 'error');
    }
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

// ESC to close
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});
