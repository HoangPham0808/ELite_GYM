// ===== CONFIG =====
const API_URL = 'Customer_Management_function.php';
let currentPage = 1;
const LIMIT = 15;
let deleteTargetId = null;
let searchTimer = null;

// ===== DOM READY =====
document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadCustomers();

    // Search debounce
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { currentPage = 1; loadCustomers(); }, 350);
    });

    document.getElementById('genderFilter').addEventListener('change', () => { currentPage = 1; loadCustomers(); });
    document.getElementById('statusFilter').addEventListener('change', () => { currentPage = 1; loadCustomers(); });
});

// ===== LOAD STATS STRIP =====
async function loadStats() {
    try {
        const res = await fetch(`${API_URL}?action=get_stats`);
        const d = await res.json();
        if (!d.success) return;
        document.getElementById('statTotal').textContent   = d.total.toLocaleString('vi-VN');
        document.getElementById('statNewMonth').textContent = '+' + d.new_month;
        document.getElementById('statActive').textContent  = d.active.toLocaleString('vi-VN');
        document.getElementById('statExpiring').textContent = d.expiring;
    } catch(e) { console.error(e); }
}

// ===== LOAD CUSTOMER TABLE =====
async function loadCustomers() {
    const search  = document.getElementById('searchInput').value.trim();
    const gender  = document.getElementById('genderFilter').value;
    const status  = document.getElementById('statusFilter').value;

    setTableLoading(true);

    try {
        const url = `${API_URL}?action=get_customers&page=${currentPage}&limit=${LIMIT}&search=${encodeURIComponent(search)}&gender=${gender}&status=${status}`;
        const res = await fetch(url);
        const d = await res.json();

        if (!d.success) { showToast('Lỗi tải dữ liệu', 'error'); return; }

        renderTable(d.data);
        renderPagination(d.total, d.totalPages);
        document.getElementById('tableTotal').textContent = `${d.total} khách hàng`;
    } catch(e) {
        showToast('Không thể kết nối server', 'error');
    } finally {
        setTableLoading(false);
    }
}

function setTableLoading(state) {
    const tbody = document.getElementById('customerTbody');
    if (state) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:40px;color:rgba(255,255,255,0.3)"><i class="fas fa-spinner fa-spin" style="font-size:24px"></i></td></tr>`;
    }
}

// ===== RENDER TABLE =====
function renderTable(customers) {
    const tbody = document.getElementById('customerTbody');
    if (!customers.length) {
        tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state"><i class="fas fa-users-slash"></i><p>Không tìm thấy khách hàng nào</p></div></td></tr>`;
        return;
    }

    tbody.innerHTML = customers.map(c => {
        const initials = c.full_name.split(' ').map(w => w[0]).slice(-2).join('').toUpperCase();
        const genderBadge = c.gender === 'Male' 
            ? `<span class="badge badge-male"><i class="fas fa-mars"></i> Nam</span>`
            : c.gender === 'Female'
            ? `<span class="badge badge-female"><i class="fas fa-venus"></i> Nữ</span>`
            : `<span class="badge badge-other">Khác</span>`;

        const statusMap = {
            active:   `<span class="badge badge-active"><i class="fas fa-circle" style="font-size:8px"></i> Active</span>`,
            expiring: `<span class="badge badge-expiring"><i class="fas fa-exclamation-circle"></i> Sắp hết hạn</span>`,
            expired:  `<span class="badge badge-expired"><i class="fas fa-times-circle"></i> Expired</span>`,
            none:     `<span class="badge badge-none">Chưa có gói</span>`
        };

        const statusBadge = statusMap[c.pkg_status] || statusMap.none;
        const pkgEnd = c.pkg_end ? formatDate(c.pkg_end) : '—';
        const ngayDK = c.registered_at ? formatDate(c.registered_at) : '—';
        const phone  = c.phone || '—';

        return `<tr>
            <td>
                <div class="customer-info">
                    <div class="customer-avatar">${initials}</div>
                    <div>
                        <div class="customer-name">${escHtml(c.full_name)}</div>
                        <div class="customer-email">${escHtml(c.email || '—')}</div>
                    </div>
                </div>
            </td>
            <td>${phone}</td>
            <td>${genderBadge}</td>
            <td>${ngayDK}</td>
            <td>${statusBadge}</td>
            <td>${c.pkg_name ? escHtml(c.pkg_name) : '—'}</td>
            <td>${pkgEnd}</td>
            <td>
                <div class="action-group">
                    <button class="btn-action" title="Xem chi tiết" onclick="openDetail(${c.customer_id})"><i class="fas fa-eye"></i></button>
                    <button class="btn-action" title="Chỉnh sửa" onclick="openEdit(${c.customer_id}, '${escJson(c)}')"><i class="fas fa-pen"></i></button>
                    <button class="btn-action delete" title="Xóa" onclick="confirmDelete(${c.customer_id}, '${escHtml(c.full_name)}')"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ===== PAGINATION =====
function renderPagination(total, totalPages) {
    const container = document.getElementById('paginationControls');
    const info = document.getElementById('paginationInfo');
    const from = Math.min((currentPage - 1) * LIMIT + 1, total);
    const to   = Math.min(currentPage * LIMIT, total);
    info.textContent = `Hiển thị ${from}–${to} trong ${total.toLocaleString('vi-VN')} khách hàng`;

    let html = `<button class="btn-page" onclick="goPage(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;

    // Page number buttons (show at most 5)
    const start = Math.max(1, currentPage - 2);
    const end   = Math.min(totalPages, start + 4);
    for (let i = start; i <= end; i++) {
        html += `<button class="btn-page ${i === currentPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
    }

    html += `<button class="btn-page" onclick="goPage(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
    container.innerHTML = html;
}

function goPage(p) {
    if (p < 1) return;
    currentPage = p;
    loadCustomers();
}

// ===== ADD MODAL =====
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Thêm khách hàng mới';
    document.getElementById('customerId').value = '';
    document.getElementById('fHoTen').value        = '';
    document.getElementById('fNgaySinh').value     = '';
    document.getElementById('fGioiTinh').value     = '';
    document.getElementById('fSoDienThoai').value  = '';
    document.getElementById('fEmail').value        = '';
    document.getElementById('fDiaChi').value       = '';
    document.getElementById('fTenDangNhap').value  = '';
    document.getElementById('fMatKhau').value      = '';

    // Chế độ thêm mới: tài khoản bắt buộc
    document.getElementById('accountRequiredBadge').style.display = '';
    document.getElementById('usernameRequired').style.display = '';
    document.getElementById('passwordGroup').style.display = '';
    document.getElementById('editAccountNote').style.display = 'none';
    document.getElementById('pwHint').style.display = '';
    document.getElementById('accountSectionTitle').textContent = 'Tài khoản đăng nhập (bắt buộc)';

    document.getElementById('customerModal').classList.add('active');
}

// ===== EDIT MODAL =====
function openEdit(id, customer) {
    document.getElementById('modalTitle').textContent = 'Chỉnh sửa thông tin';
    document.getElementById('customerId').value    = id;
    document.getElementById('fHoTen').value        = customer.full_name || '';
    document.getElementById('fNgaySinh').value     = customer.date_of_birth || '';
    document.getElementById('fGioiTinh').value     = customer.gender || '';
    document.getElementById('fSoDienThoai').value  = customer.phone || '';
    document.getElementById('fEmail').value        = customer.email || '';
    document.getElementById('fDiaChi').value       = customer.address || '';
    document.getElementById('customerModal').classList.add('active');
}

function closeCustomerModal() {
    document.getElementById('customerModal').classList.remove('active');
}

// ===== SAVE CUSTOMER =====
async function saveCustomer() {
    const id      = document.getElementById('customerId').value;
    const full_name  = document.getElementById('fHoTen').value.trim();
    const username = document.getElementById('fTenDangNhap').value.trim();
    const password = document.getElementById('fMatKhau').value;

    if (!full_name) { showToast('Please enter full name', 'warning'); return; }

    // Khi thêm mới: bắt buộc phải có tên đăng nhập
    if (!id) {
        if (!username) {
            showToast('Username is required when adding a new customer', 'warning');
            document.getElementById('fTenDangNhap').focus();
            return;
        }
        if (username.length < 3) {
            showToast('Username must be at least 3 characters', 'warning');
            document.getElementById('fTenDangNhap').focus();
            return;
        }
    }

    const body = new FormData();
    body.append('action', id ? 'update_customer' : 'add_customer');
    if (id) body.append('id', id);
    body.append('full_name',        full_name);
    body.append('date_of_birth',     document.getElementById('fNgaySinh').value);
    body.append('gender',     document.getElementById('fGioiTinh').value);
    body.append('phone', document.getElementById('fSoDienThoai').value.trim());
    body.append('email',         document.getElementById('fEmail').value.trim());
    body.append('address',       document.getElementById('fDiaChi').value.trim());
    body.append('username', username);
    body.append('password',      password);

    const btn = document.getElementById('btnSave');
    btn.disabled = true; btn.textContent = 'Đang lưu...';

    try {
        const res  = await fetch(API_URL, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeCustomerModal();
            loadCustomers();
            loadStats();
        } else {
            showToast(data.message, 'error');
        }
    } catch(e) {
        showToast('Lỗi kết nối', 'error');
    } finally {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Lưu';
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

// ===== DELETE =====
function confirmDelete(id, name) {
    deleteTargetId = id;
    document.getElementById('deleteCustomerName').textContent = name;
    document.getElementById('confirmModal').classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('active');
    deleteTargetId = null;
}

async function doDelete() {
    if (!deleteTargetId) return;
    const body = new FormData();
    body.append('action', 'delete_customer');
    body.append('id', deleteTargetId);

    try {
        const res  = await fetch(API_URL, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeConfirmModal();
            loadCustomers();
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
    document.getElementById('detailContent').innerHTML = `<div style="text-align:center;padding:40px;color:rgba(255,255,255,0.4)"><i class="fas fa-spinner fa-spin" style="font-size:28px"></i></div>`;

    try {
        const res = await fetch(`${API_URL}?action=get_detail&id=${id}`);
        const d = await res.json();
        if (!d.success) { document.getElementById('detailContent').innerHTML = '<p style="color:#f87171;padding:20px">Lỗi tải dữ liệu</p>'; return; }
        renderDetail(d);
    } catch(e) {
        document.getElementById('detailContent').innerHTML = '<p style="color:#f87171;padding:20px">Lỗi kết nối</p>';
    }
}

function renderDetail(d) {
    const c = d.customer;
    const genderLabel = c.gender === 'Male' ? 'Nam' : c.gender === 'Female' ? 'Nữ' : 'Khác';

    const packagesHtml = d.packages.length
        ? `<table class="pkg-table">
            <thead><tr><th>Plan</th><th>Start Date</th><th>End Date</th><th>Status</th></tr></thead>
            <tbody>${d.packages.map(p => {
                const now = new Date();
                const end = new Date(p.end_date);
                let badge;
                const diffDays = Math.ceil((end - now) / 86400000);
                if (diffDays < 0) badge = `<span class="badge badge-expired">Expired</span>`;
                else if (diffDays <= 7) badge = `<span class="badge badge-expiring">${diffDays} ngày</span>`;
                else badge = `<span class="badge badge-active">Active</span>`;
                return `<tr><td><strong>${escHtml(p.plan_name)}</strong></td><td>${formatDate(p.start_date)}</td><td>${formatDate(p.end_date)}</td><td>${badge}</td></tr>`;
            }).join('')}</tbody>
           </table>`
        : `<p style="color:rgba(255,255,255,0.35);font-size:13px;padding:8px 0">No membership plans registered</p>`;

    const invoicesHtml = d.invoices.length
        ? `<table class="pkg-table">
            <thead><tr><th>#ID</th><th>Ngày lập</th><th>Plan</th><th>Tổng tiền</th></tr></thead>
            <tbody>${d.invoices.map(i => `<tr>
                <td>#${i.invoice_id}</td>
                <td>${formatDate(i.invoice_date)}</td>
                <td>${escHtml(i.plan_names || '—')}</td>
                <td style="color:#d4a017;font-weight:600">${formatMoney(i.final_amount)}</td>
            </tr>`).join('')}</tbody>
           </table>`
        : `<p style="color:rgba(255,255,255,0.35);font-size:13px;padding:8px 0">No invoices</p>`;

    document.getElementById('detailContent').innerHTML = `
        <div class="detail-section">
            <h4><i class="fas fa-user"></i> Personal Info</h4>
            <div class="detail-grid">
                <div class="detail-item"><label>Full Name</label><span>${escHtml(c.full_name)}</span></div>
                <div class="detail-item"><label>Gender</label><span>${genderLabel || '—'}</span></div>
                <div class="detail-item"><label>Date of Birth</label><span>${c.date_of_birth ? formatDate(c.date_of_birth) : '—'}</span></div>
                <div class="detail-item"><label>Registered</label><span>${c.registered_at ? formatDate(c.registered_at) : '—'}</span></div>
                <div class="detail-item"><label>Phone</label><span>${c.phone || '—'}</span></div>
                <div class="detail-item"><label>Email</label><span>${c.email || '—'}</span></div>
                <div class="detail-item" style="grid-column:1/-1"><label>Address</label><span>${c.address || '—'}</span></div>
            </div>
        </div>

        <div class="detail-section">
            <h4><i class="fas fa-dumbbell"></i> Check-in History</h4>
            <div class="detail-grid">
                <div class="detail-item"><label>Total Check-ins</label><span>${d.checkin.total_checkin || 0} lần</span></div>
                <div class="detail-item"><label>Last Check-in</label><span>${d.checkin.last_checkin ? formatDateTime(d.checkin.last_checkin) : '—'}</span></div>
            </div>
        </div>

        <div class="detail-section">
            <h4><i class="fas fa-id-card"></i> Membership Plans</h4>
            ${packagesHtml}
        </div>

        <div class="detail-section">
            <h4><i class="fas fa-file-invoice"></i> Invoice History</h4>
            ${invoicesHtml}
        </div>
    `;
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('active');
}

// ===== TOAST =====
function showToast(message, type = 'success') {
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle' };
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.success}"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.4s'; setTimeout(() => toast.remove(), 400); }, 3000);
}

// ===== HELPERS =====
function formatDate(str) {
    if (!str) return '—';
    const d = new Date(str);
    return `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()}`;
}

function formatDateTime(str) {
    if (!str) return '—';
    const d = new Date(str);
    return `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')} ${formatDate(str)}`;
}

function formatMoney(val) {
    if (!val) return '0 VNĐ';
    return Number(val).toLocaleString('vi-VN') + ' VNĐ';
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escJson(obj) {
    return encodeURIComponent(JSON.stringify(obj));
}

// Override openEdit to decode JSON from attribute
window.openEdit = function(id, encoded) {
    let customer;
    try { customer = JSON.parse(decodeURIComponent(encoded)); } catch(e) { return; }
    document.getElementById('modalTitle').textContent = 'Chỉnh sửa thông tin';
    document.getElementById('customerId').value    = id;
    document.getElementById('fHoTen').value        = customer.full_name || '';
    document.getElementById('fNgaySinh').value     = customer.date_of_birth || '';
    document.getElementById('fGioiTinh').value     = customer.gender || '';
    document.getElementById('fSoDienThoai').value  = customer.phone || '';
    document.getElementById('fEmail').value        = customer.email || '';
    document.getElementById('fDiaChi').value       = customer.address || '';
    document.getElementById('fTenDangNhap').value  = customer.username || '';
    document.getElementById('fMatKhau').value      = '';

    // Chế độ sửa: tài khoản không bắt buộc
    document.getElementById('accountRequiredBadge').style.display = 'none';
    document.getElementById('usernameRequired').style.display = 'none';
    document.getElementById('passwordGroup').style.display = 'none';
    document.getElementById('editAccountNote').style.display = '';
    document.getElementById('accountSectionTitle').textContent = 'Tài khoản đăng nhập';

    document.getElementById('customerModal').classList.add('active');
};

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});
