const API = 'Promotion_Management_function.php';

let currentPage = 1;
let deleteId = null;
const LIMIT = 15;

// ============ FORMAT HELPERS ============
function fmtMoney(v) {
    if (v === null || v === '' || v == 0) return '<span class="money-sub">—</span>';
    return `<span class="money-value">${Number(v).toLocaleString('vi-VN')}₫</span>`;
}

function fmtDate(d) {
    if (!d || d === '0000-00-00' || d === '') return '<span style="color:var(--text-muted)">—</span>';
    const [y, m, day] = d.split('-');
    if (y === '0000' || !y || !m || !day) return '<span style="color:var(--text-muted)">—</span>';
    return `${day}/${m}/${y}`;
}

function statusBadge(s) {
    const map = {
        'Active': ['status-active', 'Active'],
        'Expired':   ['status-expired', 'Expired'],
        'Paused':  ['status-paused', 'Paused'],
    };
    const [cls, label] = map[s] || ['status-paused', s];
    return `<span class="status-badge ${cls}">${label}</span>`;
}

// ============ STATS ============
async function loadStats() {
    try {
        const res = await fetch(`${API}?action=get_stats`);
        const d = await res.json();
        if (!d.success) return;
        document.getElementById('statTotal').textContent        = d.total ?? '0';
        document.getElementById('statActive').textContent       = d.active ?? '0';
        document.getElementById('statExpired').textContent      = d.expired ?? '0';
        document.getElementById('statUsed').textContent         = d.total_used ?? '0';
        document.getElementById('statAvgDiscount').textContent  = d.avg_discount ? d.avg_discount + '%' : '0%';
    } catch(e) { /* silent */ }
}

// ============ LOAD TABLE ============
async function loadPromos(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value.trim();
    const status = document.getElementById('statusFilter').value;
    const sort   = document.getElementById('sortFilter').value;

    const params = new URLSearchParams({
        action: 'get_promos', page, limit: LIMIT,
        search, status, sort
    });

    document.getElementById('promoTbody').innerHTML =
        `<tr><td colspan="10" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>`;

    try {
        const res = await fetch(`${API}?${params}`);
        const d = await res.json();
        if (!d.success) throw new Error(d.message);

        renderTable(d.data);
        renderPagination(d.total, d.page, d.totalPages);
        document.getElementById('tableTotal').textContent =
            `${d.total} khuyến mãi`;
    } catch(e) {
        document.getElementById('promoTbody').innerHTML =
            `<tr><td colspan="10" class="loading-cell" style="color:#f87171">
                <i class="fas fa-exclamation-triangle"></i> Lỗi tải dữ liệu
             </td></tr>`;
    }
}

// ============ RENDER TABLE ============
function renderTable(rows) {
    const tbody = document.getElementById('promoTbody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="10">
            <div class="empty-state">
                <i class="fas fa-tag"></i>
                Chưa có khuyến mãi nào
            </div></td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(r => {
        const sl = r.max_usage ? parseInt(r.max_usage) : null;
        const sd = parseInt(r.usage_count || 0);
        const pct = sl ? Math.min(100, Math.round(sd / sl * 100)) : 0;

        let qtyHtml;
        if (sl) {
            qtyHtml = `
                <div class="qty-wrap">
                    <div class="qty-total">${sl}</div>
                    <div class="qty-used">Đã dùng: ${sd}</div>
                    <div class="progress-wrap">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width:${pct}%"></div>
                        </div>
                    </div>
                </div>`;
        } else {
            qtyHtml = `<span class="qty-unlimited"><i class="fas fa-infinity"></i> Không giới hạn</span>`;
        }

        return `<tr>
            <td>
                <div class="promo-name">${esc(r.promotion_name)}</div>
                ${r.description ? `<div class="promo-desc">${esc(r.description.substring(0,60))}${r.description.length > 60 ? '…' : ''}</div>` : ''}
            </td>
            <td><span class="discount-badge"><i class="fas fa-percent" style="font-size:11px"></i>${r.discount_percent}%</span></td>
            <td>${fmtMoney(r.min_order_value)}</td>
            <td>${fmtMoney(r.max_discount_amount)}</td>
            <td>${qtyHtml}</td>
            <td style="text-align:center">
                <span style="font-family:'Barlow Condensed';font-size:17px;font-weight:700;color:var(--orange)">${sd}</span>
            </td>
            <td class="date-cell">${fmtDate(r.start_date)}</td>
            <td class="date-cell">${fmtDate(r.end_date)}</td>
            <td>${statusBadge(r.status || 'Active')}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon edit" onclick="openEditModal(${r.promotion_id})" title="Sửa khuyến mãi">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="btn-icon delete" onclick="confirmDelete(${r.promotion_id}, '${esc(r.promotion_name)}')" title="Xóa khuyến mãi">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ============ PAGINATION ============
function renderPagination(total, page, totalPages) {
    const info = document.getElementById('paginationInfo');
    const ctrl = document.getElementById('paginationControls');

    const from = (page - 1) * LIMIT + 1;
    const to   = Math.min(page * LIMIT, total);
    info.textContent = total > 0 ? `Hiển thị ${from}–${to} / ${total}` : 'Không có dữ liệu';

    if (totalPages <= 1) { ctrl.innerHTML = ''; return; }

    let html = `<button class="page-btn" onclick="loadPromos(${page - 1})" ${page === 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i></button>`;

    for (let i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && Math.abs(i - page) > 2 && i !== 1 && i !== totalPages) {
            if (i === 2 || i === totalPages - 1) html += `<button class="page-btn" disabled>…</button>`;
            continue;
        }
        html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="loadPromos(${i})">${i}</button>`;
    }

    html += `<button class="page-btn" onclick="loadPromos(${page + 1})" ${page === totalPages ? 'disabled' : ''}>
                 <i class="fas fa-chevron-right"></i></button>`;
    ctrl.innerHTML = html;
}

// ============ DEBOUNCE SEARCH ============
let searchTimer;
document.getElementById('searchInput').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadPromos(1), 350);
});
document.getElementById('statusFilter').addEventListener('change', () => loadPromos(1));
document.getElementById('sortFilter').addEventListener('change',   () => loadPromos(1));

// ============ MODAL ADD ============
function openAddModal() {
    document.getElementById('modalTitle').innerHTML =
        '<i class="fas fa-tag" style="color:#f472b6;margin-right:8px"></i>Thêm khuyến mãi mới';
    clearForm();
    document.getElementById('promoModal').classList.add('open');
}

function clearForm() {
    ['promoId','fTenKM','fPhanTram','fDonToiThieu','fGiamToiDa','fSoLuong','fNgayBD','fNgayKT','fMoTa']
        .forEach(id => { document.getElementById(id).value = ''; });
    document.getElementById('fTrangThai').value = 'Active';
}

function closePromoModal() {
    document.getElementById('promoModal').classList.remove('open');
}

// ============ MODAL EDIT ============
async function openEditModal(id) {
    try {
        const res = await fetch(`${API}?action=get_detail&id=${id}`);
        const d = await res.json();
        if (!d.success) { showToast(d.message, 'error'); return; }
        const r = d.promo;

        document.getElementById('modalTitle').innerHTML =
            '<i class="fas fa-pen" style="color:#f472b6;margin-right:8px"></i>Chỉnh sửa khuyến mãi';
        document.getElementById('promoId').value         = r.promotion_id;
        document.getElementById('fTenKM').value          = r.promotion_name || '';
        document.getElementById('fPhanTram').value       = r.discount_percent || '';
        document.getElementById('fTrangThai').value      = r.status || 'Active';
        document.getElementById('fDonToiThieu').value    = r.min_order_value || '';
        document.getElementById('fGiamToiDa').value      = r.max_discount_amount || '';
        document.getElementById('fSoLuong').value        = r.max_usage || '';
        document.getElementById('fNgayBD').value         = r.start_date || '';
        document.getElementById('fNgayKT').value         = r.end_date || '';
        document.getElementById('fMoTa').value           = r.description || '';

        document.getElementById('promoModal').classList.add('open');
    } catch(e) {
        showToast('Lỗi tải dữ liệu', 'error');
    }
}

// ============ SAVE ============
async function savePromo() {
    const id         = document.getElementById('promoId').value;
    const promotionName      = document.getElementById('fTenKM').value.trim();
    const discountPct   = document.getElementById('fPhanTram').value;
    const status  = document.getElementById('fTrangThai').value;
    const minOrderValue= document.getElementById('fDonToiThieu').value;
    const maxDiscount  = document.getElementById('fGiamToiDa').value;
    const maxUsage    = document.getElementById('fSoLuong').value;
    const startDate     = document.getElementById('fNgayBD').value;
    const endDate     = document.getElementById('fNgayKT').value;
    const description       = document.getElementById('fMoTa').value.trim();

    if (!promotionName)    { showToast('Vui lòng nhập tên khuyến mãi', 'error'); return; }
    if (!discountPct) { showToast('Vui lòng nhập % giảm giá', 'error'); return; }
    if (!startDate || !endDate) { showToast('Vui lòng chọn ngày bắt đầu và kết thúc', 'error'); return; }
    if (startDate > endDate)   { showToast('Ngày kết thúc phải sau ngày bắt đầu', 'error'); return; }

    const body = new FormData();
    body.append('action', id ? 'update_promo' : 'add_promo');
    if (id) body.append('id', id);
    body.append('promotion_name', promotionName);
    body.append('discount_percent', discountPct);
    body.append('status', status);
    body.append('min_order_value', minOrderValue || 0);
    body.append('max_discount_amount', maxDiscount);
    body.append('max_usage', maxUsage);
    body.append('start_date', startDate);
    body.append('end_date', endDate);
    body.append('description', description);

    try {
        const res = await fetch(API, { method: 'POST', body });
        const d = await res.json();
        if (d.success) {
            showToast(d.message, 'success');
            closePromoModal();
            loadPromos(currentPage);
            loadStats();
        } else {
            showToast(d.message, 'error');
        }
    } catch(e) { showToast('Lỗi kết nối', 'error'); }
}

// ============ DELETE ============
function confirmDelete(id, name) {
    deleteId = id;
    document.getElementById('deletePromoName').textContent = name;
    document.getElementById('confirmModal').classList.add('open');
}
function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('open');
    deleteId = null;
}
async function doDelete() {
    if (!deleteId) return;
    const body = new FormData();
    body.append('action', 'delete_promo');
    body.append('id', deleteId);
    try {
        const res = await fetch(API, { method: 'POST', body });
        const d = await res.json();
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) { loadPromos(currentPage); loadStats(); }
    } catch(e) { showToast('Lỗi kết nối', 'error'); }
    closeConfirmModal();
}

// ============ CLOSE MODALS ON OVERLAY CLICK ============
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});

// ============ TOAST ============
function showToast(msg, type = 'info') {
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<i class="fas ${icons[type]}"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

// ============ INIT ============
loadStats();
loadPromos(1);
