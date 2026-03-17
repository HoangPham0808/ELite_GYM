// ===== CONFIG =====
const API_URL = 'Package_Management_function.php';
let currentPage = 1;
const LIMIT = 15;
let deleteTargetId = null;
let searchTimer = null;

// ===== DOM READY =====
document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadPackages();
    loadPackageTypes();

    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { currentPage = 1; loadPackages(); }, 350);
    });

    document.getElementById('durationFilter').addEventListener('change', () => { currentPage = 1; loadPackages(); });
    document.getElementById('sortFilter').addEventListener('change', () => { currentPage = 1; loadPackages(); });
});

// ===== LOAD PACKAGE TYPES =====
let packageTypes = [];

async function loadPackageTypes() {
    try {
        const res  = await fetch(`${API_URL}?action=get_package_types`);
        const data = await res.json();
        if (!data.success) return;
        packageTypes = data.data;
        const sel = document.getElementById('fPackageType');
        sel.innerHTML = '<option value="">— Chọn loại gói —</option>'
            + data.data.map(t =>
                `<option value="${t.type_id}" data-color="${escHtml(t.color_code||'#6b7280')}">${escHtml(t.type_name)}</option>`
            ).join('');
    } catch(e) { console.error('loadPackageTypes:', e); }
}

function getTypeBadgeHtml(typeId) {
    if (!typeId) return '';
    const t = packageTypes.find(x => String(x.type_id) === String(typeId));
    if (!t) return '';
    return `<span class="pkg-type-badge">
        <span class="pkg-type-dot" style="background:${escHtml(t.color_code||'#6b7280')}"></span>
        ${escHtml(t.type_name)}
    </span>`;
}

// ===== LOAD STATS =====
async function loadStats() {
    try {
        const res = await fetch(`${API_URL}?action=get_stats`);
        const d = await res.json();
        if (!d.success) return;
        document.getElementById('statTotal').textContent           = d.total;
        document.getElementById('statActiveSubscribers').textContent = d.active_subscribers.toLocaleString('vi-VN');
        document.getElementById('statPopular').textContent         = d.popular_name || '—';
        document.getElementById('statAvgPrice').textContent        = d.avg_price ? formatMoneyShort(d.avg_price) : '—';
    } catch(e) { console.error(e); }
}

// ===== LOAD PACKAGE TABLE =====
async function loadPackages() {
    const search   = document.getElementById('searchInput').value.trim();
    const duration = document.getElementById('durationFilter').value;
    const sort     = document.getElementById('sortFilter').value;

    setTableLoading(true);

    try {
        const url = `${API_URL}?action=get_packages&page=${currentPage}&limit=${LIMIT}`
            + `&search=${encodeURIComponent(search)}`
            + `&duration=${duration}`
            + `&sort=${sort}`;

        const res = await fetch(url);
        const d   = await res.json();

        if (!d.success) { showToast('Lỗi tải dữ liệu', 'error'); return; }

        renderTable(d.data);
        renderPagination(d.total, d.totalPages);
        document.getElementById('tableTotal').textContent = `${d.total} gói tập`;
    } catch(e) {
        showToast('Không thể kết nối server', 'error');
    } finally {
        setTableLoading(false);
    }
}

function setTableLoading(state) {
    if (state) {
        document.getElementById('packageTbody').innerHTML =
            `<tr><td colspan="7" style="text-align:center;padding:40px;color:rgba(255,255,255,0.3)">
                <i class="fas fa-spinner fa-spin" style="font-size:24px"></i>
             </td></tr>`;
    }
}

// ===== RENDER TABLE =====
function renderTable(packages) {
    const tbody = document.getElementById('packageTbody');

    if (!packages.length) {
        tbody.innerHTML = `<tr><td colspan="7">
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>Không tìm thấy gói tập nào</p>
            </div>
        </td></tr>`;
        return;
    }

    tbody.innerHTML = packages.map(p => {
        const durationLabel = formatDuration(p.duration_months);
        const totalSubs  = parseInt(p.total_subscribers) || 0;
        const activeSubs = parseInt(p.active_subscribers) || 0;
        const moTa = p.description
            ? escHtml(p.description).substring(0, 60) + (p.description.length > 60 ? '...' : '')
            : '<span style="color:rgba(255,255,255,0.25)">—</span>';
        const isHot = totalSubs >= 10;

        // Ảnh thumbnail
        const imgHtml = p.image_url
            ? `<img src="${escHtml(p.image_url)}" class="pkg-thumb" alt="${escHtml(p.plan_name)}"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
            : '';
        const avatarHtml = p.image_url
            ? `${imgHtml}<div class="pkg-avatar" style="display:none"><i class="fas fa-dumbbell"></i></div>`
            : `<div class="pkg-avatar"><i class="fas fa-dumbbell"></i></div>`;

        return `<tr>
            <td>
                <div class="pkg-name-cell">
                    ${avatarHtml}
                    <div>
                        <div class="pkg-name-text">${escHtml(p.plan_name)}</div>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:2px">
                            <span class="pkg-id-text">#${p.plan_id}</span>
                            ${getTypeBadgeHtml(p.package_type_id)}
                        </div>
                    </div>
                </div>
            </td>
            <td>
                <span class="duration-badge">
                    <i class="fas fa-calendar-alt"></i> ${durationLabel}
                </span>
            </td>
            <td><span class="price-text">${formatMoney(p.price)}</span></td>
            <td style="max-width:220px;font-size:13px;color:rgba(255,255,255,0.55)">${moTa}</td>
            <td>
                <span class="subscriber-count ${isHot ? 'hot' : ''}">
                    ${isHot ? '<i class="fas fa-fire" style="color:#f97316"></i>' : '<i class="fas fa-user"></i>'}
                    ${totalSubs} người
                </span>
            </td>
            <td>
                <span class="subscriber-count" style="color:#34d399">
                    <i class="fas fa-circle" style="font-size:8px"></i> ${activeSubs} đang dùng
                </span>
            </td>
            <td>
                <div class="action-group">
                    <button class="btn-action-sm" title="Xem chi tiết" onclick="openDetail(${p.plan_id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-action-sm" title="Chỉnh sửa" onclick="openEdit('${escJson(p)}')">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="btn-action-sm delete" title="Xóa" onclick="confirmDelete(${p.plan_id}, '${escHtml(p.plan_name)}')">
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
    info.textContent = total > 0 ? `Hiển thị ${from}–${to} trong ${total} gói tập` : 'Không có dữ liệu';

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
    loadPackages();
}

// ===== ADD MODAL =====
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Thêm gói tập mới';
    document.getElementById('packageId').value  = '';
    document.getElementById('fTenGoi').value    = '';
    document.getElementById('fThoiHan').value   = '';
    document.getElementById('fGia').value       = '';
    document.getElementById('fMoTa').value      = '';
    resetImagePreview();
    document.getElementById('fPackageType').value = '';
    document.getElementById('packageModal').classList.add('active');
    setTimeout(() => document.getElementById('fTenGoi').focus(), 100);
}

// ===== EDIT MODAL =====
window.openEdit = function(encoded) {
    let pkg;
    try { pkg = JSON.parse(decodeURIComponent(encoded)); } catch(e) { return; }

    document.getElementById('modalTitle').textContent = 'Chỉnh sửa gói tập';
    document.getElementById('packageId').value  = pkg.plan_id;
    document.getElementById('fTenGoi').value    = pkg.plan_name || '';
    document.getElementById('fThoiHan').value   = pkg.duration_months || '';
    document.getElementById('fGia').value       = pkg.price || '';
    document.getElementById('fMoTa').value           = pkg.description || '';
    document.getElementById('fPackageType').value     = pkg.package_type_id || '';

    // Load ảnh cũ vào preview
    if (pkg.image_url) {
        setImagePreview(pkg.image_url);
    } else {
        resetImagePreview();
    }

    document.getElementById('packageModal').classList.add('active');
    setTimeout(() => document.getElementById('fTenGoi').focus(), 100);
};

function closePackageModal() {
    document.getElementById('packageModal').classList.remove('active');
}

// ===== SAVE PACKAGE =====
async function savePackage() {
    const id          = document.getElementById('packageId').value;
    const planName    = document.getElementById('fTenGoi').value.trim();
    const duration    = document.getElementById('fThoiHan').value;
    const price       = document.getElementById('fGia').value;
    const description = document.getElementById('fMoTa').value.trim();
    const removeImg   = document.getElementById('fRemoveImage').value;
    const imageFile   = document.getElementById('fImage').files[0];

    if (!planName)                             { showToast('Vui lòng nhập tên gói tập', 'warning'); return; }
    if (!duration || parseInt(duration) < 1)  { showToast('Thời hạn phải lớn hơn 0', 'warning'); return; }
    if (!price || parseFloat(price) < 0)      { showToast('Vui lòng nhập giá hợp lệ', 'warning'); return; }

    const isEdit = !!id;
    const body = new FormData();
    body.append('action', isEdit ? 'update_package' : 'add_package');
    if (isEdit) body.append('id', id);
    body.append('plan_name', planName);
    body.append('duration_months', duration);
    body.append('price', price);
    body.append('description', description);
    const packageTypeId = document.getElementById('fPackageType').value;
    body.append('package_type_id', packageTypeId);
    body.append('remove_image', removeImg);
    if (imageFile) body.append('image_package', imageFile);

    const btn = document.querySelector('#packageModal .btn-primary');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';

    try {
        const res  = await fetch(API_URL, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closePackageModal();
            loadPackages();
            loadStats();
        } else {
            showToast(data.message || 'Có lỗi xảy ra', 'error');
        }
    } catch(e) {
        showToast('Lỗi kết nối server', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Lưu';
    }
}

// ===== DELETE =====
function confirmDelete(id, name) {
    deleteTargetId = id;
    document.getElementById('deletePackageName').textContent = name;
    document.getElementById('confirmModal').classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('active');
    deleteTargetId = null;
}

async function doDelete() {
    if (!deleteTargetId) return;
    const body = new FormData();
    body.append('action', 'delete_package');
    body.append('id', deleteTargetId);

    try {
        const res  = await fetch(API_URL, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeConfirmModal();
            loadPackages();
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
    const p = d.package;
    const pricePerMonth = p.duration_months > 0 ? (p.price / p.duration_months) : 0;

    const subscribersHtml = d.subscribers.length
        ? `<table class="sub-table">
            <thead><tr><th>Khách hàng</th><th>SĐT</th><th>Ngày bắt đầu</th><th>Ngày kết thúc</th><th>Trạng thái</th></tr></thead>
            <tbody>${d.subscribers.map(s => {
                const now  = new Date();
                const end  = new Date(s.end_date);
                const diff = Math.ceil((end - now) / 86400000);
                let badge;
                if (diff < 0)       badge = `<span style="color:#f87171;font-size:12px">Hết hạn</span>`;
                else if (diff <= 7) badge = `<span style="color:#fbbf24;font-size:12px">Còn ${diff} ngày</span>`;
                else                badge = `<span style="color:#34d399;font-size:12px">Đang hoạt động</span>`;
                return `<tr>
                    <td><strong>${escHtml(s.full_name)}</strong></td>
                    <td>${s.phone || '—'}</td>
                    <td>${formatDate(s.start_date)}</td>
                    <td>${formatDate(s.end_date)}</td>
                    <td>${badge}</td>
                </tr>`;
            }).join('')}</tbody>
           </table>`
        : `<p style="color:rgba(255,255,255,0.35);font-size:13px;padding:8px 0">Chưa có khách hàng nào đăng ký gói này</p>`;

    document.getElementById('detailContent').innerHTML = `
        <div class="detail-pkg-header">
            ${p.image_url
                ? `<img src="${escHtml(p.image_url)}" class="detail-pkg-img" alt="${escHtml(p.plan_name)}"
                        onerror="this.style.display='none'">`
                : `<div class="detail-pkg-icon"><i class="fas fa-dumbbell"></i></div>`
            }
            <div>
                <div class="detail-pkg-name">${escHtml(p.plan_name)}</div>
                <div class="detail-pkg-id">Plan ID: #${p.plan_id}</div>
                <div class="detail-pkg-price">${formatMoney(p.price)}</div>
            </div>
        </div>

        <div class="detail-section">
            <h4><i class="fas fa-info-circle"></i> Thông tin gói</h4>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Thời hạn</label>
                    <span>${formatDuration(p.duration_months)}</span>
                </div>
                <div class="detail-item">
                    <label>Giá / tháng</label>
                    <span style="color:#d4a017">${formatMoney(pricePerMonth)}</span>
                </div>
                <div class="detail-item">
                    <label>Tổng người đã đăng ký</label>
                    <span>${d.total_subscribers} người</span>
                </div>
                <div class="detail-item">
                    <label>Đang sử dụng</label>
                    <span style="color:#34d399">${d.active_subscribers} người</span>
                </div>
                <div class="detail-item">
                    <label>Doanh thu tổng</label>
                    <span style="color:#d4a017;font-weight:700">${formatMoney(d.total_revenue)}</span>
                </div>
                <div class="detail-item">
                    <label>Lần đăng ký gần nhất</label>
                    <span>${d.last_registered ? formatDate(d.last_registered) : '—'}</span>
                </div>
            </div>
        </div>

        ${p.description ? `
        <div class="detail-section">
            <h4><i class="fas fa-align-left"></i> Mô tả</h4>
            <div class="desc-box">${escHtml(p.description)}</div>
        </div>` : ''}

        <div class="detail-section">
            <h4><i class="fas fa-users"></i> Danh sách đăng ký (${d.total_subscribers} người)</h4>
            ${subscribersHtml}
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
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.4s';
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

// ===== HELPERS =====
function formatDate(str) {
    if (!str) return '—';
    const d = new Date(str);
    return `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()}`;
}

function formatMoney(val) {
    if (!val && val !== 0) return '0 ₫';
    return Number(val).toLocaleString('vi-VN') + ' ₫';
}

function formatMoneyShort(val) {
    const n = Number(val);
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0','') + 'tr ₫';
    if (n >= 1_000)     return (n / 1_000).toFixed(0) + 'k ₫';
    return n + ' ₫';
}

function formatDuration(months) {
    const m = parseInt(months);
    if (m === 12) return '1 năm';
    if (m === 24) return '2 năm';
    return m + ' tháng';
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

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

// Enter to save
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});

// ===== IMAGE PREVIEW HELPERS =====
function setImagePreview(url) {
    document.getElementById('imgPreview').src = url;
    document.getElementById('imgPreviewWrap').style.display = 'flex';
    document.getElementById('imgPlaceholder').style.display = 'none';
    document.getElementById('fRemoveImage').value = '0';
}

function resetImagePreview() {
    document.getElementById('fImage').value = '';
    document.getElementById('imgPreview').src = '';
    document.getElementById('imgPreviewWrap').style.display = 'none';
    document.getElementById('imgPlaceholder').style.display = 'flex';
    document.getElementById('fRemoveImage').value = '0';
}

function removeImage(e) {
    e.stopPropagation();
    document.getElementById('fRemoveImage').value = '1';
    resetImagePreview();
}

// File input change → preview
document.addEventListener('DOMContentLoaded', () => {
    const fImage = document.getElementById('fImage');
    if (fImage) {
        fImage.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) {
                showToast('Ảnh vượt quá 5 MB', 'error');
                this.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = e => setImagePreview(e.target.result);
            reader.readAsDataURL(file);
        });
    }

    // Drag & drop vào zone
    const zone = document.getElementById('imgUploadZone');
    if (zone) {
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            document.getElementById('fImage').files = dt.files;
            document.getElementById('fImage').dispatchEvent(new Event('change'));
        });
    }
});
