// ===== CONFIG =====
const API = 'Invoice_Management_function.php';
let currentPage = 1;
const LIMIT = 15;
let deleteId = null;
let invoiceItems = [];        // [{plan_id, plan_name, quantity, unit_price, duration_months}]
let packages = [];            // all packages from DB
let selectedPromo = null;     // {promotion_id, discount_percent, max_discount_amount, promotion_name}
let customerSearchTimer = null;

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('fNgayLap').value = getTodayStr();

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
});

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
        <select class="status-select" onchange="quickUpdateStatus(${id}, this.value)" title="Đổi trạng thái">
            <option value="Paid"      ${s==='Paid'      ?'selected':''}>✅ Đã thanh toán</option>
            <option value="Pending"   ${s==='Pending'   ?'selected':''}>⏳ Chờ thanh toán</option>
            <option value="Cancelled" ${s==='Cancelled' ?'selected':''}>❌ Đã hủy</option>
        </select>
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
        const res = await fetch(`${API}?action=get_stats`);
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
        const res = await fetch(`${API}?${params}`);
        const d = await res.json();
        if (!d.success) throw new Error(d.message);
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
            <td class="created-by-cell">${r.created_by ? `<span class="creator-badge"><i class="fas fa-user-edit"></i> ${esc(r.created_by)}</span>` : '—'}</td>
            <td class="note-by-cell">${r.note ? `<span title="${esc(r.note)}">${esc(r.note.substring(0,30))}${r.note.length > 30 ? '…' : ''}</span>` : '—'}</td>
            <td>${statusBadge(r.status, r.invoice_id)}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon" onclick="openDetail(${r.invoice_id})" title="Xem chi tiết">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${USER_ROLE === 'Admin' ? `<button class="btn-icon delete" onclick="confirmDelete(${r.invoice_id})" title="Xóa"><i class="fas fa-trash"></i></button>` : ''}
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
async function loadPackages() {
    try {
        const res = await fetch(`${API}?action=get_packages`);
        const d = await res.json();
        if (!d.success) return;
        packages = d.data;
        const sel = document.getElementById('fPackage');
        sel.innerHTML = '<option value="">-- Chọn gói tập --</option>' +
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
            const res = await fetch(`${API}?action=get_customers&q=${encodeURIComponent(q)}`);
            const d = await res.json();
            if (!d.success || !d.data.length) { dropdown.style.display = 'none'; return; }

            dropdown.innerHTML = d.data.map(c => `
                <div class="dropdown-item" onclick="selectCustomer(${c.customer_id}, '${esc(c.full_name)}', '${esc(c.phone || '')}', '${esc(c.email || '')}')">
                    <span class="di-name">${esc(c.full_name)}</span>
                    <span class="di-phone">${esc(c.phone || '')}</span>
                </div>`).join('');
            dropdown.style.display = 'block';
        } catch(e) {}
    }, 250);
}

function selectCustomer(id, name, phone, email) {
    document.getElementById('fCustomerId').value = id;
    document.getElementById('fCustomerSearch').value = name;
    document.getElementById('customerDropdown').style.display = 'none';

    const box = document.getElementById('selectedCustomer');
    box.style.display = 'flex';
    box.innerHTML = `
        <div class="sel-cust-avatar">${name.charAt(0).toUpperCase()}</div>
        <div>
            <div class="sel-cust-name">${esc(name)}</div>
            <div class="sel-cust-sub">${esc(phone)} ${email ? '• ' + esc(email) : ''}</div>
        </div>
        <button class="sel-cust-clear" onclick="clearCustomer()"><i class="fas fa-times"></i></button>`;
}

function clearCustomer() {
    document.getElementById('fCustomerId').value = '';
    document.getElementById('fCustomerSearch').value = '';
    document.getElementById('selectedCustomer').style.display = 'none';
}

document.addEventListener('click', e => {
    if (!e.target.closest('.autocomplete-wrap')) {
        document.getElementById('customerDropdown').style.display = 'none';
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
    recalcSummary();
    loadAvailablePromos();
}

function removeItem(idx) {
    invoiceItems.splice(idx, 1);
    renderItems();
    recalcSummary();
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

        document.querySelector('input[name="promoRadio"][value="0"]').checked = true;
        applyPromo(null);
    } catch(e) {}
}

function applyPromo(promoData) {
    selectedPromo = promoData;
    recalcSummary();
}

// ===== RECALC SUMMARY =====
function recalcSummary() {
    const totalGoc = invoiceItems.reduce((s, i) => s + i.unit_price * i.quantity, 0);
    let soGiam = 0;

    if (selectedPromo && totalGoc > 0) {
        soGiam = totalGoc * parseFloat(selectedPromo.discount_percent) / 100;
        if (selectedPromo.max_discount_amount && soGiam > parseFloat(selectedPromo.max_discount_amount)) {
            soGiam = parseFloat(selectedPromo.max_discount_amount);
        }
    }

    const totalSau = Math.max(0, totalGoc - soGiam);

    document.getElementById('summaryGoc').textContent = fmtMoney(totalGoc);
    document.getElementById('summaryTotal').textContent = fmtMoney(totalSau);

    const discRow = document.getElementById('summaryDiscountRow');
    if (soGiam > 0) {
        discRow.style.display = 'flex';
        document.getElementById('summaryDiscountLabel').textContent =
            `Giảm giá (${selectedPromo.discount_percent}%):`;
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
    recalcSummary();
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
            // Tiền mặt: xác nhận thanh toán ngay, không cần mở modal QR
            const payBody = new FormData();
            payBody.append('action', 'confirm_payment');
            payBody.append('id', d.id);
            payBody.append('phuong_thuc', 'Tiền mặt');
            const payRes = await fetch(API, { method: 'POST', body: payBody });
            const payD   = await payRes.json();
            showToast(payD.success ? 'Tạo & xác nhận thanh toán tiền mặt thành công!' : payD.message,
                      payD.success ? 'success' : 'error');
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
                <button class="btn-pay" onclick="openPaymentModal(${inv.invoice_id})">
                    <i class="fas fa-qrcode"></i> Thanh toán ngay
                </button>
            </div>` : inv.status === 'Paid' ? `
            <div class="payment-done-banner">
                <i class="fas fa-check-circle"></i>
                <span>Đã thanh toán thành công</span>
            </div>` : ''}`;
    } catch(e) { showToast('Lỗi tải chi tiết', 'error'); }
}

// ===== PAYMENT QR MODAL =====
let paymentTimer = null;
let paymentCountdown = 300;

async function openPaymentModal(id) {
    document.getElementById('paymentModal').classList.add('active');
    document.getElementById('qrSection').innerHTML =
        '<div class="qr-loading"><i class="fas fa-spinner fa-spin"></i><span>Đang tạo mã QR...</span></div>';
    document.getElementById('payMethod').value = 'Chuyển khoản';
    clearInterval(paymentTimer);

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
                document.getElementById('qrSection').innerHTML =
                    '<div class="qr-error"><i class="fas fa-clock"></i><span>QR hết hạn. Nhấn làm mới để tạo lại.</span></div>';
            }
        }, 1000);
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
