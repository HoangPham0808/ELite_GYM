// ===== CONFIG =====
var API = window.ELITE_BASE + '/api/admin/invoice';
var currentPage = 1;
var LIMIT = 15;
var deleteId = null;
var invoiceItems = [];        // [{plan_id, plan_name, quantity, unit_price, duration_months}]
var packages = [];            // all packages from DB
var selectedPromo = null;     // {promotion_id, discount_percent, max_discount_amount, promotion_name}
var customerSearchTimer = null;

// ===== INIT =====
function initInvoiceModule() {
    const fNgayLap = document.getElementById('fNgayLap');
    if (!fNgayLap) return;
    fNgayLap.value = getTodayStr();

    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(window._searchTimer);
        window._searchTimer = setTimeout(() => loadInvoices(1), 350);
    });
    document.getElementById('sortFilter').addEventListener('change', () => loadInvoices(1));
    document.getElementById('monthFilter').addEventListener('change', () => loadInvoices(1));
    document.getElementById('statusFilter').addEventListener('change', () => loadInvoices(1));

    loadStats();
    loadInvoices(1);
    loadPackages();
}

// SPA: DOMContentLoaded không fire lại khi navigate — chạy ngay nếu DOM đã sẵn sàng
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initInvoiceModule);
} else {
    initInvoiceModule();
}

function getTodayStr() {
    return new Date().toISOString().split('T')[0];
}

function fmtMoney(v) {
    if (!v && v !== 0) return '—';
    return Number(v).toLocaleString('vi-VN') + '₫';
}

function fmtDate(d) {
    if (!d || d === '0000-00-00') return '—';
    const [y, m, day] = d.split('-');
    return `${day}/${m}/${y}`;
}

function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// DB stores English ENUM: 'Paid', 'Pending', 'Cancelled'
// UI displays Vietnamese labels
function statusBadge(s, id) {
    const map = {
        'Paid':      ['status-paid',    'Đã thanh toán'],
        'Pending':   ['status-pending', 'Chờ thanh toán'],
        'Cancelled': ['status-cancel',  'Đã hủy'],
    };
    const [cls, label] = map[s] || ['status-pending', s || 'Chờ thanh toán'];
    return `<div class="status-wrap">
        <span class="inv-status ${cls}">${label}</span>
    </div>`;
}

async function quickUpdateStatus(id, status) {
    const body = new FormData();
    body.append('action', 'update_status');
    body.append('id', id);
    body.append('status', status);
    try {
        const res = await fetch(API, { method: 'POST', body });
        const d = await res.json();
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) { loadInvoices(currentPage); loadStats(); }
    } catch(e) { showToast('Lỗi kết nối', 'error'); }
}

// ===== STATS =====
async function loadStats() {
    try {
           const res = await fetch(`${API}?action=get_stats`, { credentials: 'same-origin' });
           if (!res.ok) { console.error('loadStats HTTP', res.status); return; }
           const d = await res.json();
        if (!d.success) return;
        document.getElementById('statTotal').textContent   = d.total ?? '0';
        document.getElementById('statRevenue').textContent = d.revenue_month >= 1e6
            ? (d.revenue_month / 1e6).toFixed(1) + 'M₫'
            : fmtMoney(d.revenue_month);
        document.getElementById('statToday').textContent   = d.today_count ?? '0';
        document.getElementById('statPromo').textContent   = d.promo_used ?? '0';
        document.getElementById('statAvg').textContent     = d.avg_value >= 1e6
            ? (d.avg_value / 1e6).toFixed(1) + 'M₫'
            : fmtMoney(d.avg_value);
    } catch(e) {}
}

// ===== LOAD TABLE =====
async function loadInvoices(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value.trim();
    const sort   = document.getElementById('sortFilter').value;
    const month  = document.getElementById('monthFilter').value;
    const status = document.getElementById('statusFilter').value;
    const params = new URLSearchParams({ action: 'get_invoices', page, limit: LIMIT, search, sort, month, status });

    document.getElementById('invoiceTbody').innerHTML =
        `<tr><td colspan="10" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>`;

        try {
            const res = await fetch(`${API}?${params}`, { credentials: 'same-origin' });
            if (!res.ok) {
                const bodyText = await res.text();
                console.error('Invoice list HTTP error', res.status, bodyText);
                throw new Error('HTTP ' + res.status);
            }
            let d;
            try { d = await res.json(); } catch (je) {
                const txt = await res.text().catch(() => '');
                console.error('Invalid JSON from invoice API', je, txt);
                throw je;
            }
            if (!d.success) {
                console.error('Invoice API returned error', d);
                throw new Error(d.message || 'Unknown');
            }
        renderTable(d.data);
        renderPagination(d.total, d.page, d.totalPages);
        document.getElementById('tableTotal').textContent = `${d.total} hóa đơn`;
    } catch(e) {
        document.getElementById('invoiceTbody').innerHTML =
            `<tr><td colspan="10" class="loading-cell" style="color:#f87171">
                <i class="fas fa-exclamation-triangle"></i> Lỗi tải dữ liệu
             </td></tr>`;
    }
}

// ===== RENDER TABLE =====
function renderTable(rows) {
    const tbody = document.getElementById('invoiceTbody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="10">
            <div class="empty-state"><i class="fas fa-file-invoice"></i>Chưa có hóa đơn nào</div>
        </td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(r => {
        const hasPromo = r.promotion_id;
        const discountHtml = hasPromo
            ? `<span class="promo-badge"><i class="fas fa-tag"></i>${r.discount_percent}% — ${esc(r.promotion_name)}</span>`
            : `<span class="no-promo">—</span>`;

        const soGiam = parseFloat(r.discount_amount || 0);
        const discountCell = soGiam > 0
            ? `<span class="discount-cell">- ${fmtMoney(soGiam)}</span>`
            : `<span style="color:var(--text-muted)">—</span>`;

        return `<tr>
            <td class="id-cell">#${r.invoice_id}</td>
            <td>
                <div class="customer-name">${esc(r.ten_khach)}</div>
                <div class="customer-phone">${esc(r.phone || '')}</div>
            </td>
            <td class="date-cell">${fmtDate(r.invoice_date)}</td>
            <td class="money-cell">${fmtMoney(r.original_amount)}</td>
            <td>${discountCell}</td>
            <td class="money-cell total">${fmtMoney(r.final_amount)}</td>
            <td>${discountHtml}</td>
            <td class="note-by-cell">${r.note ? `<span title="${esc(r.note)}">${esc(r.note.substring(0,30))}${r.note.length > 30 ? '…' : ''}</span>` : '—'}</td>
            <td>${statusBadge(r.status, r.invoice_id)}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon" onclick="openDetail(${r.invoice_id})" title="Xem chi tiết">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-icon" onclick="printInvoice(${r.invoice_id})" title="In hóa đơn">
                        <i class="fas fa-print"></i>
                    </button>
                    ${window.window.USER_ROLE === 'Admin' ? `<button class="btn-icon delete" onclick="confirmDelete(${r.invoice_id})" title="Xóa"><i class="fas fa-trash"></i></button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ===== PAGINATION =====
function renderPagination(total, page, totalPages) {
    const info = document.getElementById('paginationInfo');
    const ctrl = document.getElementById('paginationControls');
    const from = (page - 1) * LIMIT + 1;
    const to   = Math.min(page * LIMIT, total);
    info.textContent = total > 0 ? `Hiển thị ${from}–${to} / ${total}` : 'Không có dữ liệu';

    if (totalPages <= 1) { ctrl.innerHTML = ''; return; }

    let html = `<button class="page-btn" onclick="loadInvoices(${page-1})" ${page===1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && Math.abs(i - page) > 2 && i !== 1 && i !== totalPages) {
            if (i === 2 || i === totalPages - 1) html += `<button class="page-btn" disabled>…</button>`;
            continue;
        }
        html += `<button class="page-btn ${i===page?'active':''}" onclick="loadInvoices(${i})">${i}</button>`;
    }
    html += `<button class="page-btn" onclick="loadInvoices(${page+1})" ${page===totalPages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
    ctrl.innerHTML = html;
}

// ===== LOAD PACKAGES =====
async function loadPackages(packageTypeId) {
    try {
            const url = packageTypeId
                ? `${API}?action=get_packages&package_type_id=${packageTypeId}`
                : `${API}?action=get_packages`;
            const res = await fetch(url, { credentials: 'same-origin' });
            const d = await res.json();
        if (!d.success) return;
        packages = d.data;
        const sel = document.getElementById('fPackage');

        if (packageTypeId && d.data.length === 0) {
            sel.innerHTML = '<option value="">-- Không có gói phù hợp --</option>';
            return;
        }

        const filterNote = packageTypeId && window._activePackageTypeName
            ? ` [${window._activePackageTypeName}]`
            : '';

        sel.innerHTML = `<option value="">-- Chọn gói tập${filterNote} --</option>` +
            packages.map(p => `<option value="${p.plan_id}" data-gia="${p.price}" data-ten="${esc(p.plan_name)}" data-thang="${p.duration_months}">
                ${esc(p.plan_name)} (${p.duration_months} tháng) — ${fmtMoney(p.price)}
            </option>`).join('');
    } catch(e) {}
}

function onPackageChange() {
    const sel = document.getElementById('fPackage');
    const opt = sel.options[sel.selectedIndex];
    const preview = document.getElementById('fPricePreview');
    if (!sel.value) { preview.style.display = 'none'; return; }
    const gia = parseFloat(opt.dataset.gia);
    const qty = parseInt(document.getElementById('fQty').value) || 1;
    preview.style.display = 'block';
    preview.innerHTML = `<i class="fas fa-info-circle"></i> Đơn giá: ${fmtMoney(gia)} × ${qty} = <strong>${fmtMoney(gia * qty)}</strong>`;
}

// ===== CUSTOMER AUTOCOMPLETE =====
async function searchCustomers(q) {
    clearTimeout(customerSearchTimer);
    const dropdown = document.getElementById('customerDropdown');
    if (!q || q.length < 2) { dropdown.style.display = 'none'; return; }

    customerSearchTimer = setTimeout(async () => {
            try {
                const res = await fetch(`${API}?action=get_customers&q=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
                const d = await res.json();
            if (!d.success || !d.data.length) { dropdown.style.display = 'none'; return; }

            dropdown.innerHTML = d.data.map(c => {
                const pkBadge = c.active_package_type_name
                    ? `<span style="font-size:10px;background:rgba(212,160,23,0.2);color:#d4a017;padding:1px 6px;border-radius:10px;margin-left:4px">${esc(c.active_package_type_name)}</span>`
                    : '';
                return `<div class="dropdown-item" onclick="selectCustomer(${c.customer_id}, '${esc(c.full_name)}', '${esc(c.phone || '')}', '${esc(c.email || '')}', ${c.active_package_type_id || 'null'}, '${esc(c.active_package_type_name || '')}')">
                    <span class="di-name">${esc(c.full_name)}${pkBadge}</span>
                    <span class="di-phone">${esc(c.phone || '')}</span>
                </div>`;
            }).join('');
            dropdown.style.display = 'block';
        } catch(e) {}
    }, 250);
}

function selectCustomer(id, name, phone, email, activePackageTypeId, activePackageTypeName) {
    document.getElementById('fCustomerId').value = id;
    document.getElementById('fCustomerSearch').value = name;
    document.getElementById('customerDropdown').style.display = 'none';

    // Lưu package_type hiện tại để lọc gói tập
    window._activePackageTypeId   = activePackageTypeId   || null;
    window._activePackageTypeName = activePackageTypeName || null;

    const box = document.getElementById('selectedCustomer');
    box.style.display = 'flex';

    const pkBadge = activePackageTypeName
        ? `<span style="font-size:11px;background:rgba(212,160,23,0.15);color:#d4a017;padding:2px 8px;border-radius:10px;margin-left:6px;border:1px solid rgba(212,160,23,0.3)">
               <i class="fas fa-layer-group" style="font-size:9px;margin-right:3px"></i>Đang dùng: ${esc(activePackageTypeName)}
           </span>`
        : '';

    box.innerHTML = `
        <div class="sel-cust-avatar">${name.charAt(0).toUpperCase()}</div>
        <div style="flex:1">
            <div class="sel-cust-name">${esc(name)}${pkBadge}</div>
            <div class="sel-cust-sub">${esc(phone)} ${email ? '• ' + esc(email) : ''}</div>
        </div>
        <button class="sel-cust-clear" onclick="clearCustomer()"><i class="fas fa-times"></i></button>`;

    // Reload packages với filter
    loadPackages(activePackageTypeId);
}

function clearCustomer() {
    document.getElementById('fCustomerId').value = '';
    document.getElementById('fCustomerSearch').value = '';
    document.getElementById('selectedCustomer').style.display = 'none';
    window._activePackageTypeId   = null;
    window._activePackageTypeName = null;
    loadPackages(null); // reset về tất cả gói
}

document.addEventListener('click', e => {
    if (!e.target.closest('.autocomplete-wrap')) {
        const dd = document.getElementById('customerDropdown');
        if (dd) dd.style.display = 'none';
    }
});

// ===== ADD ITEM =====
function addItem() {
    const sel = document.getElementById('fPackage');
    const qty = parseInt(document.getElementById('fQty').value) || 1;
    if (!sel.value) { showToast('Vui lòng chọn gói tập', 'error'); return; }

    const opt            = sel.options[sel.selectedIndex];
    const plan_id        = parseInt(sel.value);
    const unit_price     = parseFloat(opt.dataset.gia);
    const plan_name      = opt.dataset.ten;
    const duration_months = opt.dataset.thang;

    const existing = invoiceItems.findIndex(i => i.plan_id === plan_id);
    if (existing >= 0) {
        invoiceItems[existing].quantity += qty;
    } else {
        invoiceItems.push({ plan_id, plan_name, duration_months, quantity: qty, unit_price });
    }

    sel.value = '';
    document.getElementById('fQty').value = 1;
    document.getElementById('fPricePreview').style.display = 'none';

    renderItems();
    recalcSummary(); // async, fire and forget
    loadAvailablePromos();
}

function removeItem(idx) {
    invoiceItems.splice(idx, 1);
    renderItems();
    recalcSummary(); // async, fire and forget
    loadAvailablePromos();
}

function renderItems() {
    const tbody = document.getElementById('itemsTbody');
    if (!invoiceItems.length) {
        tbody.innerHTML = `<tr id="emptyItemsRow"><td colspan="5" class="empty-items"><i class="fas fa-shopping-cart"></i> Chưa có sản phẩm</td></tr>`;
        return;
    }
    tbody.innerHTML = invoiceItems.map((item, idx) => `
        <tr>
            <td>
                <div class="item-name">${esc(item.plan_name)}</div>
                <div class="item-sub">${item.duration_months} tháng</div>
            </td>
            <td class="qty-cell">${item.quantity}</td>
            <td class="money-cell">${fmtMoney(item.unit_price)}</td>
            <td class="money-cell"><strong>${fmtMoney(item.unit_price * item.quantity)}</strong></td>
            <td><button class="btn-remove-item" onclick="removeItem(${idx})"><i class="fas fa-times"></i></button></td>
        </tr>`).join('');
}

// ===== PROMO =====
async function loadAvailablePromos() {
    const totalGoc = invoiceItems.reduce((s, i) => s + i.unit_price * i.quantity, 0);
    const promoList = document.getElementById('promoList');
    const promoPlaceholder = document.getElementById('promoPlaceholder');

    if (totalGoc === 0) {
        promoList.style.display = 'none';
        promoPlaceholder.style.display = 'flex';
        selectedPromo = null;
        return;
    }

    try {
        const res = await fetch(`${API}?action=get_promotions&total=${totalGoc}`);
        const d = await res.json();

        if (!d.success || !d.data.length) {
            promoList.style.display = 'none';
            promoPlaceholder.style.display = 'flex';
            promoPlaceholder.innerHTML = `<i class="fas fa-info-circle"></i> Không có khuyến mãi nào áp dụng được cho đơn hàng này`;
            selectedPromo = null;
            applyPromo(null);
            return;
        }

        promoPlaceholder.style.display = 'none';
        promoList.style.display = 'block';

        document.getElementById('promoItems').innerHTML = d.data.map(p => {
            const discountAmt = Math.min(
                totalGoc * p.discount_percent / 100,
                p.max_discount_amount ? parseFloat(p.max_discount_amount) : Infinity
            );
            const usageHtml = p.max_usage
                ? `<span class="promo-usage">${p.usage_count}/${p.max_usage} lượt</span>`
                : '';
            return `<div class="promo-option">
                <label>
                    <input type="radio" name="promoRadio" value="${p.promotion_id}"
                           onchange="applyPromo(${JSON.stringify(p).replace(/"/g, '&quot;')})">
                    <div class="promo-info">
                        <span class="promo-name">${esc(p.promotion_name)}</span>
                        <span class="promo-pct">${p.discount_percent}% OFF</span>
                        ${usageHtml}
                        <span class="promo-save">Tiết kiệm: <strong>${fmtMoney(discountAmt)}</strong></span>
                        ${p.max_discount_amount ? `<span class="promo-cap">Giảm tối đa: ${fmtMoney(p.max_discount_amount)}</span>` : ''}
                    </div>
                </label>
            </div>`;
        }).join('');

        // Bỏ chọn tất cả radio trước khi reset
        document.querySelectorAll('input[name="promoRadio"]').forEach(r => r.checked = false);
        applyPromo(null);
    } catch(e) {}
}

function applyPromo(promoData) {
    selectedPromo = promoData;
    recalcSummary(); // async, fire and forget
}

// ===== RECALC SUMMARY =====
var _upgradeCredit = 0; // cache credit từ server

async function recalcSummary() {
    const totalGoc = invoiceItems.reduce((s, i) => s + i.unit_price * i.quantity, 0);
    const customerId = document.getElementById('fCustomerId').value;

    // Lấy package_type_id cao nhất trong giỏ hàng
    let maxTypeId = 0;
    for (const item of invoiceItems) {
        const pkg = packages.find(p => p.plan_id == item.plan_id);
        if (pkg && pkg.package_type_id) {
            // So sánh bằng cách dùng sort_order từ _allPackageTypes nếu có
            if (parseInt(pkg.package_type_id) > maxTypeId) {
                maxTypeId = parseInt(pkg.package_type_id);
            }
        }
    }

    // Lấy upgrade credit nếu có khách + gói
    if (customerId && maxTypeId > 0 && totalGoc > 0) {
        try {
            const r = await fetch(`${API}?action=get_upgrade_credit&customer_id=${customerId}&package_type_id=${maxTypeId}`);
            const d = await r.json();
            _upgradeCredit = (d.success && d.is_upgrade) ? parseFloat(d.credit) : 0;
        } catch(e) { _upgradeCredit = 0; }
    } else {
        _upgradeCredit = 0;
    }

    let soGiam = _upgradeCredit;

    if (selectedPromo && totalGoc > 0) {
        let promoDisc = totalGoc * parseFloat(selectedPromo.discount_percent) / 100;
        if (selectedPromo.max_discount_amount && promoDisc > parseFloat(selectedPromo.max_discount_amount)) {
            promoDisc = parseFloat(selectedPromo.max_discount_amount);
        }
        soGiam += promoDisc;
    }

    const totalSau = Math.max(0, totalGoc - soGiam);

    document.getElementById('summaryGoc').textContent = fmtMoney(totalGoc);
    document.getElementById('summaryTotal').textContent = fmtMoney(totalSau);

    const discRow = document.getElementById('summaryDiscountRow');
    if (soGiam > 0) {
        discRow.style.display = 'flex';
        let labelParts = [];
        if (_upgradeCredit > 0) labelParts.push(`Hoàn tiền nâng cấp: -${fmtMoney(_upgradeCredit)}`);
        if (selectedPromo) labelParts.push(`KM ${selectedPromo.discount_percent}%: -${fmtMoney(soGiam - _upgradeCredit)}`);
        document.getElementById('summaryDiscountLabel').textContent = labelParts.join(' | ') || 'Giảm giá:';
        document.getElementById('summaryDiscount').textContent = `- ${fmtMoney(soGiam)}`;
    } else {
        discRow.style.display = 'none';
    }
}

// ===== OPEN/CLOSE ADD MODAL =====
function openAddModal() {
    invoiceItems = [];
    selectedPromo = null;
    clearCustomer();
    document.getElementById('fNgayLap').value = getTodayStr();
    document.getElementById('fGhiChu').value = '';
    document.getElementById('fPackage').value = '';
    document.getElementById('fQty').value = 1;
    document.getElementById('fPricePreview').style.display = 'none';
    document.getElementById('promoList').style.display = 'none';
    document.getElementById('promoPlaceholder').style.display = 'flex';
    document.getElementById('promoPlaceholder').innerHTML =
        '<i class="fas fa-info-circle"></i> Thêm sản phẩm trước để xem khuyến mãi áp dụng được';
    renderItems();
    recalcSummary(); // async, fire and forget
    document.getElementById('invoiceModal').classList.add('active');
}

function closeInvoiceModal() {
    document.getElementById('invoiceModal').classList.remove('active');
}

// ===== SAVE INVOICE =====
async function saveInvoice() {
    const customerId  = document.getElementById('fCustomerId').value;
    const invoiceDate = document.getElementById('fNgayLap').value;
    const note        = document.getElementById('fGhiChu').value.trim();

    if (!customerId) { showToast('Vui lòng chọn khách hàng', 'error'); return; }
    if (!invoiceItems.length) { showToast('Vui lòng thêm ít nhất 1 gói tập', 'error'); return; }

    const body = new FormData();
    body.append('action', 'add_invoice');
    body.append('customer_id', customerId);
    body.append('invoice_date', invoiceDate);
    body.append('note', note);
    body.append('status', document.getElementById('fTrangThaiHD').value);
    if (selectedPromo) body.append('promotion_id', selectedPromo.promotion_id);
    body.append('items', JSON.stringify(invoiceItems.map(i => ({
        plan_id:    i.plan_id,
        quantity:   i.quantity,
        unit_price: i.unit_price
    }))));

    try {
        const res = await fetch(API, { method: 'POST', body });
        const d = await res.json();
        if (!d.success) { showToast(d.message, 'error'); return; }

        const chosenStatus = document.getElementById('fTrangThaiHD').value;
        closeInvoiceModal();

        if (chosenStatus === 'Paid') {
            showToast('Tạo & xác nhận thanh toán tiền mặt thành công!', 'success');
        } else {
            // Chờ thanh toán: mở modal QR/thanh toán ngay
            showToast('Tạo hóa đơn thành công! Chuyển sang thanh toán...', 'success');
            setTimeout(() => openPaymentModal(d.id), 500);
        }

        loadInvoices(currentPage);
        loadStats();
    } catch(e) { showToast('Lỗi kết nối', 'error'); }
}

// ===== DETAIL MODAL =====
async function openDetail(id) {
    document.getElementById('detailContent').innerHTML =
        '<div class="loading-cell"><i class="fas fa-spinner fa-spin"></i></div>';
    document.getElementById('detailModal').classList.add('active');

    try {
        const res = await fetch(`${API}?action=get_detail&id=${id}`);
        const d = await res.json();
        if (!d.success) { showToast(d.message, 'error'); closeDetailModal(); return; }

        const inv   = d.invoice;
        const items = d.items;

        const promoHtml = inv.promotion_id
            ? `<span class="promo-badge"><i class="fas fa-tag"></i>${inv.discount_percent}% — ${esc(inv.promotion_name)}
               ${inv.max_discount_amount ? `(tối đa ${fmtMoney(inv.max_discount_amount)})` : ''}</span>`
            : '<span style="color:var(--text-muted)">Không áp dụng</span>';

        document.getElementById('detailContent').innerHTML = `
            <div class="detail-header-row">
                <div class="detail-info-block">
                    <div class="detail-label">Hóa đơn</div>
                    <div class="detail-val big">#${inv.invoice_id}</div>
                </div>
                <div class="detail-info-block">
                    <div class="detail-label">Khách hàng</div>
                    <div class="detail-val">${esc(inv.ten_khach)}</div>
                    <div class="detail-sub">${esc(inv.phone || '')} ${inv.email ? '• ' + esc(inv.email) : ''}</div>
                </div>
                <div class="detail-info-block">
                    <div class="detail-label">Ngày lập</div>
                    <div class="detail-val">${fmtDate(inv.invoice_date)}</div>
                </div>
                <div class="detail-info-block">
                    <div class="detail-label">Khuyến mãi</div>
                    <div class="detail-val">${promoHtml}</div>
                </div>
            </div>

            <table class="detail-items-table">
                <thead>
                    <tr><th>Gói tập</th><th>Thời hạn</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr>
                </thead>
                <tbody>
                    ${items.map(i => `
                    <tr>
                        <td>${esc(i.plan_name)}</td>
                        <td>${i.duration_months} tháng</td>
                        <td>${i.quantity}</td>
                        <td>${fmtMoney(i.unit_price)}</td>
                        <td><strong>${fmtMoney(i.subtotal)}</strong></td>
                    </tr>`).join('')}
                </tbody>
            </table>

            <div class="detail-summary">
                <div class="detail-sum-row"><span>Tổng tiền gốc:</span><span>${fmtMoney(inv.original_amount)}</span></div>
                ${parseFloat(inv.discount_amount) > 0
                    ? `<div class="detail-sum-row discount"><span>Đã giảm:</span><span class="discount-value">- ${fmtMoney(inv.discount_amount)}</span></div>`
                    : ''}
                <div class="detail-sum-row total"><span>Thực thu:</span><span class="total-value">${fmtMoney(inv.final_amount)}</span></div>
                ${inv.note ? `<div class="detail-note"><i class="fas fa-sticky-note"></i> ${esc(inv.note)}</div>` : ''}
            </div>
            ${inv.status === 'Pending' ? `
            <div class="payment-cta">
                <div class="payment-cta-info">
                    <i class="fas fa-clock" style="color:#fbbf24"></i>
                    <span>Hóa đơn <strong>chưa thanh toán</strong> — Số tiền: <strong style="color:#d4a017">${fmtMoney(inv.final_amount)}</strong></span>
                </div>
                <div style="display:flex;gap:8px">
                    ${window.window.USER_ROLE === 'Admin' ? `<button class="btn-secondary" onclick="openEditInvoiceModal(${inv.invoice_id}, '${inv.invoice_date}', '${esc(inv.note||'')}', '${inv.status}')">
                        <i class="fas fa-pen"></i> Sửa
                    </button>` : ''}
                    <button class="btn-secondary" onclick="printInvoice(${inv.invoice_id})" title="In hóa đơn">
                        <i class="fas fa-print"></i> In
                    </button>
                    <button class="btn-pay" onclick="openPaymentModal(${inv.invoice_id})">
                        <i class="fas fa-qrcode"></i> Thanh toán ngay
                    </button>
                </div>
            </div>` : inv.status === 'Paid' ? `
            <div class="payment-done-banner" style="display:flex;justify-content:space-between;align-items:center">
                <div><i class="fas fa-check-circle"></i> <span>Đã thanh toán thành công</span></div>
                <div style="display:flex;gap:8px">
                    <button class="btn-export" onclick="printInvoice(${inv.invoice_id})">
                        <i class="fas fa-print"></i> In hóa đơn
                    </button>
                    ${window.window.USER_ROLE === 'Admin' ? `<button class="btn-secondary" onclick="openEditInvoiceModal(${inv.invoice_id}, '${inv.invoice_date}', '${esc(inv.note||'')}', '${inv.status}')">
                        <i class="fas fa-pen"></i> Sửa
                    </button>` : ''}
                </div>
            </div>` : `
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
                <button class="btn-secondary" onclick="printInvoice(${inv.invoice_id})">
                    <i class="fas fa-print"></i> In hóa đơn
                </button>
                ${window.window.USER_ROLE === 'Admin' ? `<button class="btn-secondary" onclick="openEditInvoiceModal(${inv.invoice_id}, '${inv.invoice_date}', '${esc(inv.note||'')}', '${inv.status}')">
                    <i class="fas fa-pen"></i> Sửa hóa đơn
                </button>` : ''}
            </div>`}`
            ;
            
    } catch(e) { showToast('Lỗi tải chi tiết', 'error'); }
}

// ===== EDIT INVOICE MODAL (Admin only) =====
function openEditInvoiceModal(id, date, note, status) {
    document.getElementById('editInvId').value     = id;
    document.getElementById('editInvDate').value   = date;
    document.getElementById('editInvNote').value   = note;
    document.getElementById('editInvStatus').value = status;
    document.getElementById('editInvoiceModal').classList.add('active');
}

function closeEditInvoiceModal() {
    document.getElementById('editInvoiceModal').classList.remove('active');
}

async function saveEditInvoice() {
    const id     = document.getElementById('editInvId').value;
    const date   = document.getElementById('editInvDate').value;
    const note   = document.getElementById('editInvNote').value.trim();
    const status = document.getElementById('editInvStatus').value;

    const body = new FormData();
    body.append('action',       'update_invoice');
    body.append('id',           id);
    body.append('invoice_date', date);
    body.append('note',         note);
    body.append('status',       status);

    try {
        const res = await fetch(API, { method: 'POST', body });
        const d   = await res.json();
        if (d.success) {
            showToast(d.message, 'success');
            closeEditInvoiceModal();
            closeDetailModal();
            loadInvoices(currentPage);
            loadStats();
        } else {
            showToast(d.message, 'error');
        }
    } catch(e) { showToast('Lỗi kết nối', 'error'); }
}

// ===== PAYMENT QR MODAL =====
var paymentTimer = null;
var paymentCountdown = 300;
var pollTimer = null; // auto-polling kiểm tra thanh toán

async function openPaymentModal(id) {
    document.getElementById('paymentModal').classList.add('active');
    document.getElementById('qrSection').innerHTML =
        '<div class="qr-loading"><i class="fas fa-spinner fa-spin"></i><span>Đang tạo mã QR...</span></div>';
    document.getElementById('payMethod').value = 'Chuyển khoản';
    clearInterval(paymentTimer);
    clearInterval(pollTimer);

    try {
        const res = await fetch(`${API}?action=get_payment_info&id=${id}`);
        const d = await res.json();
        if (!d.success) { showToast(d.message, 'error'); return; }

        const { invoice: inv, qr_url, amount, bank, description } = d;

        document.getElementById('payInvoiceId').textContent  = `#${inv.invoice_id}`;
        document.getElementById('payCustomer').textContent   = inv.ten_khach;
        document.getElementById('payAmount').textContent     = fmtMoney(amount);
        document.getElementById('payBankName').textContent   = bank.bank_id;
        document.getElementById('payAccNo').textContent      = bank.account_no;
        document.getElementById('payAccName').textContent    = bank.account_name;
        document.getElementById('payDesc').textContent       = description;
        document.getElementById('payConfirmId').value        = inv.invoice_id;

        document.getElementById('qrSection').innerHTML = `
            <div class="qr-wrap">
                <img src="${qr_url}" alt="QR Thanh toán" class="qr-img" id="qrImage"
                     onerror="this.parentNode.innerHTML='<div class=\'qr-error\'><i class=\'fas fa-exclamation-triangle\'></i><span>Không tải được QR. Kiểm tra kết nối mạng.</span></div>'">
                <div class="qr-amount-badge">${fmtMoney(amount)}</div>
            </div>`;

        paymentCountdown = 300;
        updateCountdown();
        paymentTimer = setInterval(() => {
            paymentCountdown--;
            updateCountdown();
            if (paymentCountdown <= 0) {
                clearInterval(paymentTimer);
                clearInterval(pollTimer);
                document.getElementById('qrSection').innerHTML =
                    '<div class="qr-error"><i class="fas fa-clock"></i><span>QR hết hạn. Nhấn làm mới để tạo lại.</span></div>';
            }
        }, 1000);

        // ===== AUTO POLLING: kiểm tra mỗi 5 giây =====
        pollTimer = setInterval(async () => {
            try {
                const pr = await fetch(`${API}?action=check_payment_status&id=${inv.invoice_id}`);
                const pd = await pr.json();
                if (pd.status === 'Paid') {
                    clearInterval(pollTimer);
                    clearInterval(paymentTimer);
                    // Hiện banner thành công trong QR section
                    document.getElementById('qrSection').innerHTML = `
                        <div style="display:flex;flex-direction:column;align-items:center;gap:12px;padding:32px 16px;">
                            <i class="fas fa-check-circle" style="font-size:56px;color:#22c55e"></i>
                            <span style="font-size:18px;font-weight:700;color:#22c55e">Thanh toán thành công!</span>
                            <span style="color:var(--text-muted);font-size:14px">${fmtMoney(amount)} đã được xác nhận</span>
                        </div>`;
                    showToast('✅ Thanh toán tự động xác nhận thành công!', 'success');
                    setTimeout(() => {
                        closePaymentModal();
                        closeDetailModal();
                        loadInvoices(currentPage);
                        loadStats();
                    }, 2000);
                }
            } catch(e) { /* bỏ qua lỗi mạng tạm thời */ }
        }, 5000);

    } catch(e) { showToast('Lỗi tạo QR', 'error'); }
}

function updateCountdown() {
    const m = Math.floor(paymentCountdown / 60).toString().padStart(2,'0');
    const s = (paymentCountdown % 60).toString().padStart(2,'0');
    const el = document.getElementById('payCountdown');
    if (el) el.textContent = `${m}:${s}`;
    if (paymentCountdown <= 60 && el) el.style.color = '#f87171';
    else if (el) el.style.color = '';
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.remove('active');
    clearInterval(paymentTimer);
    clearInterval(pollTimer);
}

function refreshQR() {
    const id = document.getElementById('payConfirmId').value;
    if (id) openPaymentModal(parseInt(id));
}

async function confirmPayment() {
    const id     = document.getElementById('payConfirmId').value;
    const method = document.getElementById('payMethod').value;
    if (!id) return;

    const body = new FormData();
    body.append('action', 'confirm_payment');
    body.append('id', id);
    body.append('phuong_thuc', method);

    try {
        const res = await fetch(API, { method: 'POST', body });
        const d = await res.json();
        if (d.success) {
            showToast(d.message, 'success');
            closePaymentModal();
            closeDetailModal();
            loadInvoices(currentPage);
            loadStats();
        } else {
            showToast(d.message, 'error');
        }
    } catch(e) { showToast('Lỗi kết nối', 'error'); }
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('active');
}

// ===== DELETE =====
function confirmDelete(id) {
    deleteId = id;
    document.getElementById('deleteInvoiceName').textContent = `#${id}`;
    document.getElementById('confirmModal').classList.add('active');
}
function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('active');
    deleteId = null;
}
async function doDelete() {
    if (!deleteId) return;
    const body = new FormData();
    body.append('action', 'delete_invoice');
    body.append('id', deleteId);
    try {
        const res = await fetch(API, { method: 'POST', body });
        const d = await res.json();
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) { loadInvoices(currentPage); loadStats(); }
    } catch(e) { showToast('Lỗi kết nối', 'error'); }
    closeConfirmModal();
}

document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => {
        if (e.target === o) o.classList.remove('active');
    });
});

// ===== TOAST =====
function showToast(msg, type = 'info') {
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle' };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => el.remove(), 4000);
}
async function printInvoice(id) {
    showToast('Đang chuẩn bị hóa đơn...', 'info');
    try {
        const [detailRes, payRes] = await Promise.all([
            fetch(`${API}?action=get_detail&id=${id}`),
            fetch(`${API}?action=get_payment_info&id=${id}`)
        ]);
        const d   = await detailRes.json();
        const pay = await payRes.json();

        if (!d.success) { showToast('Không tải được dữ liệu', 'error'); return; }

        const inv   = d.invoice;
        const items = d.items;

        const statusMap = {
            'Paid':      ['paid',    'ĐÃ THANH TOÁN'],
            'Pending':   ['pending', 'CHỜ THANH TOÁN'],
            'Cancelled': ['cancel',  'ĐÃ HỦY'],
        };
        const [sCls, sLabel] = statusMap[inv.status] || ['pending', inv.status];

        const itemRows = items.map(i => `
            <tr>
                <td>${esc(i.plan_name)}</td>
                <td class="text-center">${i.duration_months} tháng</td>
                <td class="text-center">${i.quantity}</td>
                <td class="text-right">${fmtMoney(i.unit_price)}</td>
                <td class="text-right"><strong>${fmtMoney(i.subtotal)}</strong></td>
            </tr>`).join('');

        const discountRow = parseFloat(inv.discount_amount) > 0
            ? `<div class="inv-print-total-row">
                   <span>Giảm giá</span>
                   <span style="color:#15803d">− ${fmtMoney(inv.discount_amount)}</span>
               </div>` : '';

        const promoRow = inv.promotion_name
            ? `<div class="inv-print-total-row">
                   <span>Khuyến mãi</span>
                   <span>${esc(inv.promotion_name)} (${inv.discount_percent}%)</span>
               </div>` : '';

        const noteHtml = inv.note
            ? `<div class="inv-print-note"><i class="fas fa-sticky-note"></i><span>Ghi chú: ${esc(inv.note)}</span></div>`
            : '';

        // ── QR + Thông tin chuyển khoản ──
        let qrBlockHtml = '';
        if (inv.status === 'Pending' && pay.success) {
            const b = pay.bank || {};
            qrBlockHtml = `
            <div class="inv-print-qr-block">
                <div class="inv-print-qr-left">
                    <div class="inv-print-qr-title">
                        <i class="fas fa-qrcode"></i> Quét mã để thanh toán
                    </div>
                    <img id="printQrImg" src="${pay.qr_url}" alt="QR thanh toán" class="inv-print-qr-img">
                    <div class="inv-print-qr-amount">${fmtMoney(pay.amount)}</div>
                </div>
                <div class="inv-print-qr-right">
                    <h4 class="inv-print-qr-right-title">Thông tin chuyển khoản</h4>
                    <div class="inv-print-qr-row">
                        <span class="inv-print-qr-label">Ngân hàng</span>
                        <strong>${esc(b.bank_id || '—')}</strong>
                    </div>
                    <div class="inv-print-qr-row">
                        <span class="inv-print-qr-label">Số tài khoản</span>
                        <strong>${esc(b.account_no || '—')}</strong>
                    </div>
                    <div class="inv-print-qr-row">
                        <span class="inv-print-qr-label">Chủ tài khoản</span>
                        <strong>${esc(b.account_name || '—')}</strong>
                    </div>
                    <div class="inv-print-qr-row">
                        <span class="inv-print-qr-label">Nội dung CK</span>
                        <strong>${esc(pay.description || '—')}</strong>
                    </div>
                    <div class="inv-print-qr-row inv-print-qr-row--total">
                        <span class="inv-print-qr-label">Số tiền</span>
                        <strong class="inv-print-qr-pay-amount">${fmtMoney(pay.amount)}</strong>
                    </div>
                </div>
            </div>`;
        }

        const html = `
            <div class="inv-print-header">
                <div class="inv-print-logo">
                    ELITE<span>GYM</span>
                    <small>Trung tâm thể dục thể hình Elite</small>
                </div>
                <div class="inv-print-meta">
                    <h2>HÓA ĐƠN #${inv.invoice_id}</h2>
                    <p>Ngày lập: ${fmtDate(inv.invoice_date)}</p>
                    <p>Người tạo: ${esc(inv.created_by || '—')}</p>
                    <span class="inv-status-print ${sCls}">${sLabel}</span>
                </div>
            </div>

            <div class="inv-print-parties">
                <div class="inv-print-party">
                    <h4>Đơn vị cung cấp</h4>
                    <strong>Elite Gym</strong>
                    <p>📍 Địa chỉ: Elite Gym Center</p>
                    <p>📞 Hotline: 1900 xxxx</p>
                    <p>✉️ Email: info@elitegym.vn</p>
                </div>
                <div class="inv-print-party">
                    <h4>Khách hàng</h4>
                    <strong>${esc(inv.ten_khach)}</strong>
                    <p>📞 ${esc(inv.phone || '—')}</p>
                    ${inv.email ? `<p>✉️ ${esc(inv.email)}</p>` : ''}
                </div>
            </div>

            <table class="inv-print-table">
                <thead>
                    <tr>
                        <th>Gói tập</th>
                        <th class="text-center">Thời hạn</th>
                        <th class="text-center">SL</th>
                        <th class="text-right">Đơn giá</th>
                        <th class="text-right">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>${itemRows}</tbody>
            </table>

            ${noteHtml}

            <div class="inv-print-totals">
                <div class="inv-print-total-row">
                    <span>Tổng tiền gốc</span>
                    <span>${fmtMoney(inv.original_amount)}</span>
                </div>
                ${promoRow}
                ${discountRow}
                <div class="inv-print-total-row grand">
                    <span>THỰC THU</span>
                    <span>${fmtMoney(inv.final_amount)}</span>
                </div>
            </div>

            ${qrBlockHtml}

            <div class="inv-print-footer">
                <div class="sig-block">
                    <p>Khách hàng ký tên</p>
                    <strong>${esc(inv.ten_khach)}</strong>
                </div>
                <div style="text-align:center;font-size:12px;color:#9ca3af;flex:1">
                    <p>Cảm ơn quý khách đã sử dụng dịch vụ!</p>
                    <p style="margin-top:4px">In lúc: ${new Date().toLocaleString('vi-VN')}</p>
                </div>
                <div class="sig-block">
                    <p>Nhân viên lập phiếu</p>
                    <strong>${esc(inv.created_by || '—')}</strong>
                </div>
            </div>`;

        const area = document.getElementById('printInvoiceArea');
        area.innerHTML = html;
        area.style.display = 'block';

        // ── Chờ ảnh QR load xong mới print ──
        if (inv.status === 'Pending' && pay.success) {
            const qrImg = document.getElementById('printQrImg');
            await new Promise(resolve => {
                if (qrImg.complete) { resolve(); return; }
                qrImg.onload  = resolve;
                qrImg.onerror = resolve; // in dù ảnh lỗi
                setTimeout(resolve, 5000); // timeout 5s
            });
        }

        window.print();
        area.style.display = 'none';
    } catch(e) { showToast('Lỗi xuất hóa đơn', 'error'); }
}
// ===== XUẤT CSV DANH SÁCH =====
async function exportCSV() {
    showToast('Đang xuất dữ liệu...', 'info');
    const search = document.getElementById('searchInput').value.trim();
    const month  = document.getElementById('monthFilter').value;
    const status = document.getElementById('statusFilter').value;
    // Lấy toàn bộ (limit lớn)
    const params = new URLSearchParams({ action: 'get_invoices', page: 1, limit: 9999, search, month, status, sort: 'id_desc' });

    try {
        const res = await fetch(`${API}?${params}`);
        const d = await res.json();
        if (!d.success || !d.data.length) { showToast('Không có dữ liệu để xuất', 'warning'); return; }

        const statusViMap = { 'Paid': 'Đã thanh toán', 'Pending': 'Chờ thanh toán', 'Cancelled': 'Đã hủy' };

        const headers = ['Mã HĐ','Khách hàng','SĐT','Ngày lập','Tiền gốc','Giảm giá','Thực thu','Khuyến mãi','Người tạo','Ghi chú','Trạng thái'];
        const rows = d.data.map(r => [
            `#${r.invoice_id}`,
            r.ten_khach,
            r.phone || '',
            fmtDate(r.invoice_date),
            r.original_amount,
            r.discount_amount || 0,
            r.final_amount,
            r.promotion_name ? `${r.promotion_name} (${r.discount_percent}%)` : '',
            r.created_by || '',
            r.note || '',
            statusViMap[r.status] || r.status,
        ]);

        const csvContent = '\uFEFF' + [headers, ...rows]
            .map(row => row.map(v => `"${String(v).replace(/"/g,'""')}"`).join(','))
            .join('\r\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        const dateStr = new Date().toISOString().slice(0,10);
        a.href = url;
        a.download = `hoa_don_elitegym_${dateStr}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        showToast(`Đã xuất ${d.data.length} hóa đơn ra CSV`, 'success');
    } catch(e) { showToast('Lỗi xuất CSV', 'error'); }
}