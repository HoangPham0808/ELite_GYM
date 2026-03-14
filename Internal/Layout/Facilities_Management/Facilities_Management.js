const API = 'Facilities_Management_function.php';
const LIMIT = 15;
let devPage = 1, btPage = 1;
let overdueFilter = false;
let categories = [];

// ── INIT ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // ── PHÂN QUYỀN GIAO DIỆN ──────────────────────────────────────
    applyPermissions();

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn,.tab-content').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(o =>
        o.addEventListener('click', e => { if (e.target === o) o.classList.remove('active'); }));

    document.getElementById('devSearch').addEventListener('input', debounce(() => loadDevices(1), 350));
    document.getElementById('devLoai').addEventListener('change',  () => loadDevices(1));
    document.getElementById('devStatus').addEventListener('change', () => loadDevices(1));

    document.getElementById('fBtNgay').value = today();

    loadStats();
    loadCategories();
    loadDevices();
    loadMaintenance();
});

const today    = () => new Date().toISOString().split('T')[0];
const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
const fmtMoney = v => v && v > 0 ? Number(v).toLocaleString('vi-VN') + '₫' : '—';
const fmtDate  = d => (!d || d === '0000-00-00') ? '—' : d.split('-').reverse().join('/');
const esc      = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

// ── PHÂN QUYỀN ────────────────────────────────────────────────────
function applyPermissions() {
    // Receptionist: ẩn tab Loại thiết bị
    if (IS_RECEPT) {
        const catTab = document.querySelector('[data-tab="categories"]');
        if (catTab) catTab.style.display = 'none';
    }
    // Personal Trainer: chỉ thấy tab Bảo trì, ẩn Thiết bị + Loại thiết bị
    if (IS_HLV) {
        const devTab = document.querySelector('[data-tab="devices"]');
        const catTab = document.querySelector('[data-tab="categories"]');
        if (devTab) devTab.style.display = 'none';
        if (catTab) catTab.style.display = 'none';
        document.querySelectorAll('.tab-btn,.tab-content').forEach(el => el.classList.remove('active'));
        const btBtn = document.querySelector('[data-tab="maintenance"]');
        if (btBtn) btBtn.classList.add('active');
        document.getElementById('tab-maintenance').classList.add('active');
    }
}

// Kiểm tra trước khi chọn "Hoàn thành" — phải đủ dữ liệu
function validateCompletedStatus(selectId) {
    const statusEl = document.getElementById(selectId);
    if (statusEl.value !== 'Completed') return true;
    // HLV không có option Completed nên không cần check
    if (IS_HLV) { statusEl.value = 'Scheduled'; return false; }
    const isEdit   = selectId === 'fEditBtStatus';
    const nguoi    = document.getElementById(isEdit ? 'fEditBtNguoi'   : 'fBtNguoi').value.trim();
    const noiDung  = document.getElementById(isEdit ? 'fEditBtNoiDung' : 'fBtNoiDung').value.trim();
    const gia      = document.getElementById(isEdit ? 'fEditBtGia'     : 'fBtGia').value;
    const ngay     = document.getElementById(isEdit ? 'fEditBtNgay'    : 'fBtNgay').value;
    if (!nguoi || !noiDung || !gia || !ngay) {
        toast('Để chọn "Hoàn thành", vui lòng điền đầy đủ: ngày, nội dung, người thực hiện và chi phí!', 'warning');
        statusEl.value = 'In Progress';
        return false;
    }
    return true;
}

function getStatusOptions() {
    if (IS_ADMIN)  return [
        { v: 'Completed',   l: '✅ Hoàn thành' },
        { v: 'In Progress', l: '🔧 Đang xử lý' },
        { v: 'Scheduled',   l: '📅 Lên lịch'   }
    ];
    if (IS_RECEPT) return [
        { v: 'In Progress', l: '🔧 Đang xử lý' },
        { v: 'Completed',   l: '✅ Hoàn thành' }
    ];
    if (IS_HLV)    return [{ v: 'Scheduled', l: '📅 Lên lịch' }];
    return [{ v: 'Scheduled', l: '📅 Lên lịch' }];
}

function buildStatusSelect(selectId, currentValue) {
    const opts = getStatusOptions();
    const sel  = document.getElementById(selectId);
    // Nếu role không có quyền chọn currentValue, dùng option đầu tiên
    const validValues = opts.map(o => o.v);
    const selected    = validValues.includes(currentValue) ? currentValue : opts[0].v;
    sel.innerHTML = opts.map(o =>
        `<option value="${o.v}" ${o.v === selected ? 'selected' : ''}>${o.l}</option>`
    ).join('');
    sel.onchange = () => validateCompletedStatus(selectId);
}

// ── STATS ─────────────────────────────────────────────────────────
async function loadStats() {
    const d = await apiFetch('get_stats');
    document.getElementById('statTotal').textContent     = d.total       ?? '0';
    document.getElementById('statHoatDong').textContent  = d.hoat_dong   ?? '0';
    document.getElementById('statHong').textContent      = d.hong        ?? '0';
    document.getElementById('statBaoDuong').textContent  = d.bao_duong   ?? '0';
    document.getElementById('statCanBaoTri').textContent = d.can_bao_tri ?? '0';
    const g = parseFloat(d.tong_gia || 0);
    document.getElementById('statTongGia').textContent =
        g >= 1e9 ? (g/1e9).toFixed(1)+'T₫' : g >= 1e6 ? Math.round(g/1e6)+'M₫' : fmtMoney(g);
}

// ── CATEGORIES (EquipmentType) ────────────────────────────────────
// DB fields: type_id, type_name, description, maintenance_interval
async function loadCategories() {
    const d = await apiFetch('get_categories');
    categories = d.data || [];
    const opts = categories.map(c => `<option value="${c.type_id}">${esc(c.type_name)}</option>`).join('');
    document.getElementById('devLoai').innerHTML  = '<option value="">Tất cả loại</option>' + opts;
    document.getElementById('fDevLoai').innerHTML = '<option value="">-- Chọn loại --</option>' + opts;
    renderCatTable(categories);
}

function renderCatTable(rows) {
    const tb = document.getElementById('catTbody');
    if (!rows.length) {
        tb.innerHTML = `<tr><td colspan="5"><div class="empty-state"><i class="fas fa-tags"></i>Chưa có loại nào</div></td></tr>`;
        return;
    }
    tb.innerHTML = rows.map(r => `<tr>
        <td class="id-cell">${r.type_id}</td>
        <td><strong class="col-primary">${esc(r.type_name)}</strong></td>
        <td><span class="days-badge"><i class="fas fa-clock"></i> ${r.maintenance_interval} ngày</span></td>
        <td class="col-muted">${r.description ? esc(r.description) : '—'}</td>
        <td><div class="action-btns">
            <button class="btn-icon" onclick='openCatEdit(${JSON.stringify(r)})' title="Sửa"><i class="fas fa-pen"></i></button>
            <button class="btn-icon delete" onclick="confirmDel('Xóa loại <strong>${esc(r.type_name)}</strong>?', () => deleteCat(${r.type_id}))" title="Xóa"><i class="fas fa-trash"></i></button>
        </div></td>
    </tr>`).join('');
}

function openCatModal() {
    document.getElementById('fCatId').value   = '';
    document.getElementById('fCatTen').value  = '';
    document.getElementById('fCatHan').value  = 180;
    document.getElementById('fCatMoTa').value = '';
    document.getElementById('catModalTitle').innerHTML = '<i class="fas fa-tag" style="color:#d4a017;margin-right:8px"></i>Thêm loại thiết bị';
    openModal('catModal');
}
function openCatEdit(r) {
    // r.type_id, r.type_name, r.maintenance_interval, r.description
    document.getElementById('fCatId').value   = r.type_id;
    document.getElementById('fCatTen').value  = r.type_name            || '';
    document.getElementById('fCatHan').value  = r.maintenance_interval || 180;
    document.getElementById('fCatMoTa').value = r.description          || '';
    document.getElementById('catModalTitle').innerHTML = '<i class="fas fa-pen" style="color:#d4a017;margin-right:8px"></i>Sửa loại thiết bị';
    openModal('catModal');
}
async function saveCat() {
    const id = document.getElementById('fCatId').value;
    const body = fd({
        action: id ? 'update_category' : 'add_category',
        ...(id ? { id } : {}),
        ten_loai:         document.getElementById('fCatTen').value.trim(),
        han_bao_tri_ngay: document.getElementById('fCatHan').value,
        mo_ta:            document.getElementById('fCatMoTa').value.trim()
    });
    const d = await apiPost(body);
    toast(d.message, d.success ? 'success' : 'error');
    if (d.success) { closeModal('catModal'); loadCategories(); }
}
async function deleteCat(id) {
    const d = await apiPost(fd({ action: 'delete_category', id }));
    toast(d.message, d.success ? 'success' : 'error');
    if (d.success) loadCategories();
}

// ── DEVICES (Equipment) ───────────────────────────────────────────
// DB fields: equipment_id, equipment_name, condition_status, type_id, type_name,
//            purchase_price, purchase_date, last_maintenance_date, description, days_remaining
async function loadDevices(page = devPage) {
    devPage = page;
    const search = document.getElementById('devSearch').value.trim();
    const loai   = document.getElementById('devLoai').value;
    const status = document.getElementById('devStatus').value;
    const alert  = overdueFilter ? 'overdue' : '';
    const params = new URLSearchParams({ action: 'get_devices', page, limit: LIMIT, search, loai, status, alert });
    document.getElementById('devTbody').innerHTML = `<tr><td colspan="9" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>`;
    const d = await apiFetch(params.toString());
    renderDevTable(d.data || []);
    renderPag(d.total, d.page, d.totalPages, 'devPagInfo', 'devPagCtrl', loadDevices);
    document.getElementById('devMeta').textContent = `${d.total} thiết bị`;
}

function renderDevTable(rows) {
    const tb = document.getElementById('devTbody');
    if (!rows.length) {
        tb.innerHTML = `<tr><td colspan="9"><div class="empty-state"><i class="fas fa-dumbbell"></i>Không có thiết bị nào</div></td></tr>`;
        return;
    }
    tb.innerHTML = rows.map(r => {
        // condition_status: 'Hoạt động' | 'Hỏng' | 'Đang bảo dưỡng' | 'Ngừng sử dụng'
        const stCls = r.condition_status === 'Hoạt động'      ? 'st-active'
                    : r.condition_status === 'Hỏng'            ? 'st-broken'
                    : r.condition_status === 'Đang bảo dưỡng'  ? 'st-maintenance' : 'st-stopped';

        const days = parseInt(r.days_remaining ?? 999);
        let hanHtml;
        if (!r.last_maintenance_date && !r.purchase_date) {
            hanHtml = '<span class="day-muted">Chưa có ngày mua</span>';
        } else {
            const fromPurchase = !r.last_maintenance_date && r.purchase_date;
            const suffix = fromPurchase ? ' <span class="day-note">(từ ngày mua)</span>' : '';
            if (days <= 0) {
                hanHtml = `<span class="day-overdue"><i class="fas fa-exclamation-triangle"></i> Quá hạn ${Math.abs(days)} ngày${suffix}</span>`;
            } else if (days <= 30) {
                hanHtml = `<span class="day-warn"><i class="fas fa-clock"></i> Còn ${days} ngày${suffix}</span>`;
            } else {
                hanHtml = `<span class="day-ok"><i class="fas fa-check"></i> Còn ${days} ngày${suffix}</span>`;
            }
        }
        return `<tr>
            <td class="id-cell">#${r.equipment_id}</td>
            <td>
                <div class="col-primary">${esc(r.equipment_name)}</div>
                ${r.description ? `<div class="col-muted" style="font-size:11px;margin-top:2px">${esc(r.description.slice(0,50))}${r.description.length>50?'…':''}</div>` : ''}
            </td>
            <td>${r.type_name ? `<span class="cat-tag">${esc(r.type_name)}</span>` : '<span class="day-muted">—</span>'}</td>
            <td><span class="status-badge ${stCls}">${esc(r.condition_status || '—')}</span></td>
            <td class="money-cell">${fmtMoney(r.purchase_price)}</td>
            <td class="date-cell">${fmtDate(r.purchase_date)}</td>
            <td class="date-cell">${fmtDate(r.last_maintenance_date)}</td>
            <td>${hanHtml}</td>
            <td><div class="action-btns">
                <button class="btn-icon" onclick="openDevDetail(${r.equipment_id})" title="Chi tiết"><i class="fas fa-eye"></i></button>
                ${!IS_HLV ? `<button class="btn-icon" onclick="openDevEdit(${r.equipment_id})" title="Sửa"><i class="fas fa-pen"></i></button>` : ''}
                <button class="btn-icon" onclick="quickBaoTri(${r.equipment_id})" title="Thêm bảo trì nhanh"><i class="fas fa-wrench"></i></button>
                ${IS_ADMIN ? `<button class="btn-icon delete" onclick="confirmDel('Xóa thiết bị <strong>${esc(r.equipment_name)}</strong>?', () => deleteDevice(${r.equipment_id}))" title="Xóa"><i class="fas fa-trash"></i></button>` : ''}
            </div></td>
        </tr>`;
    }).join('');
}

function toggleOverdue() {
    overdueFilter = !overdueFilter;
    document.getElementById('btnOverdue').classList.toggle('active', overdueFilter);
    loadDevices(1);
}

function openDeviceModal() {
    ['fDevId','fDevTen','fDevGia','fDevNgayMua','fDevNgayBao','fDevMoTa'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('fDevLoai').value   = '';
    document.getElementById('fDevStatus').value = 'Hoạt động';
    document.getElementById('devModalTitle').innerHTML = '<i class="fas fa-dumbbell" style="color:#d4a017;margin-right:8px"></i>Thêm thiết bị';
    openModal('deviceModal');
}
async function openDevEdit(id) {
    const d = await apiFetch(`get_device_detail&id=${id}`);
    if (!d.success) { toast(d.message, 'error'); return; }
    const r = d.device;
    // r uses DB fields: equipment_id, equipment_name, type_id, condition_status,
    //                   purchase_price, purchase_date, last_maintenance_date, description
    document.getElementById('fDevId').value      = r.equipment_id;
    document.getElementById('fDevTen').value     = r.equipment_name         || '';
    document.getElementById('fDevLoai').value    = r.type_id                || '';
    document.getElementById('fDevStatus').value  = r.condition_status       || 'Hoạt động';
    document.getElementById('fDevGia').value     = r.purchase_price         || '';
    document.getElementById('fDevNgayMua').value = r.purchase_date          || '';
    document.getElementById('fDevNgayBao').value = r.last_maintenance_date  || '';
    document.getElementById('fDevMoTa').value    = r.description            || '';
    document.getElementById('devModalTitle').innerHTML = '<i class="fas fa-pen" style="color:#d4a017;margin-right:8px"></i>Sửa thiết bị';
    openModal('deviceModal');
}
async function saveDevice() {
    const id = document.getElementById('fDevId').value;
    const body = fd({
        action:           id ? 'update_device' : 'add_device',
        ...(id ? { id } : {}),
        ten_thiet_bi:     document.getElementById('fDevTen').value.trim(),
        loai_id:          document.getElementById('fDevLoai').value,
        tinh_trang:       document.getElementById('fDevStatus').value,
        gia_mua:          document.getElementById('fDevGia').value,
        ngay_mua:         document.getElementById('fDevNgayMua').value,
        ngay_bao_tri_gan: document.getElementById('fDevNgayBao').value,
        mo_ta:            document.getElementById('fDevMoTa').value.trim()
    });
    const d = await apiPost(body);
    toast(d.message, d.success ? 'success' : 'error');
    if (d.success) { closeModal('deviceModal'); loadDevices(); loadStats(); }
}
async function deleteDevice(id) {
    const d = await apiPost(fd({ action: 'delete_device', id }));
    toast(d.message, d.success ? 'success' : 'error');
    if (d.success) { loadDevices(); loadStats(); }
}
async function openDevDetail(id) {
    document.getElementById('detailContent').innerHTML = '<div class="loading-cell"><i class="fas fa-spinner fa-spin"></i></div>';
    openModal('detailModal');
    const d = await apiFetch(`get_device_detail&id=${id}`);
    if (!d.success) { toast(d.message, 'error'); return; }
    const r = d.device, hist = d.history;
    const days = parseInt(r.days_remaining ?? 999);

    let hanHtml;
    if (!r.last_maintenance_date && !r.purchase_date) {
        hanHtml = '<span class="day-muted">Chưa có ngày mua</span>';
    } else {
        const fromPurchase = !r.last_maintenance_date && r.purchase_date;
        const suffix = fromPurchase ? ' <span class="day-note">(tính từ ngày mua)</span>' : '';
        if (days <= 0) {
            hanHtml = `<span class="day-overdue"><i class="fas fa-exclamation-triangle"></i> Quá hạn ${Math.abs(days)} ngày${suffix}</span>`;
        } else if (days <= 30) {
            hanHtml = `<span class="day-warn"><i class="fas fa-clock"></i> Còn ${days} ngày${suffix}</span>`;
        } else {
            hanHtml = `<span class="day-ok"><i class="fas fa-check"></i> Còn ${days} ngày${suffix}</span>`;
        }
    }
    const stCls = r.condition_status === 'Hoạt động' ? 'st-active'
                : r.condition_status === 'Hỏng'       ? 'st-broken' : 'st-maintenance';

    // EquipmentMaintenance status ENUM: 'Completed' | 'In Progress' | 'Scheduled'
    const stLabel = s => s === 'Completed' ? 'Hoàn thành' : s === 'In Progress' ? 'Đang xử lý' : s === 'Scheduled' ? 'Lên lịch' : (s || '—');
    const stBadge = s => s === 'Completed' ? 'bt-done' : s === 'In Progress' ? 'bt-proc' : 'bt-sched';

    document.getElementById('detailContent').innerHTML = `
        <div class="detail-hero">
            <div class="detail-icon"><i class="fas fa-dumbbell"></i></div>
            <div>
                <div class="detail-name">${esc(r.equipment_name)}</div>
                <div class="detail-sub">${r.type_name ? esc(r.type_name) : 'Chưa phân loại'}</div>
            </div>
            <span class="status-badge ${stCls}" style="margin-left:auto">${esc(r.condition_status || '—')}</span>
        </div>
        <div class="detail-grid">
            <div class="di-block"><div class="di-label">Giá mua</div><div class="di-val">${fmtMoney(r.purchase_price)}</div></div>
            <div class="di-block"><div class="di-label">Ngày mua</div><div class="di-val">${fmtDate(r.purchase_date)}</div></div>
            <div class="di-block"><div class="di-label">Bảo trì gần nhất</div><div class="di-val">${fmtDate(r.last_maintenance_date)}</div></div>
            <div class="di-block"><div class="di-label">Hạn bảo trì</div><div class="di-val">${hanHtml}</div></div>
            ${r.description ? `<div class="di-block full"><div class="di-label">Ghi chú</div><div class="di-val">${esc(r.description)}</div></div>` : ''}
        </div>
        ${hist.length ? `
        <div class="detail-sec-title"><i class="fas fa-history"></i> Lịch sử bảo trì gần đây</div>
        <table class="detail-hist">
            <thead><tr><th>Ngày</th><th>Nội dung</th><th>Chi phí</th><th>Người TH</th><th>TT</th></tr></thead>
            <tbody>${hist.map(h => `<tr>
                <td class="date-cell">${fmtDate(h.maintenance_date)}</td>
                <td style="font-size:13px">${h.description ? esc(h.description) : '—'}</td>
                <td class="money-cell">${h.cost > 0 ? fmtMoney(h.cost) : '—'}</td>
                <td style="font-size:13px">${h.performed_by ? esc(h.performed_by) : '—'}</td>
                <td><span class="bt-badge ${stBadge(h.status)}">${stLabel(h.status)}</span></td>
            </tr>`).join('')}</tbody>
        </table>` : '<div class="empty-state" style="padding:20px"><i class="fas fa-history"></i>Chưa có lịch sử</div>'}`;
}

// ── MAINTENANCE (EquipmentMaintenance) ────────────────────────────
// DB fields: maintenance_id, equipment_id, equipment_name, maintenance_date,
//            description, cost, performed_by, status (Completed|In Progress|Scheduled)
async function loadMaintenance(page = btPage) {
    btPage = page;
    const params = new URLSearchParams({ action: 'get_maintenance', page, limit: LIMIT });
    document.getElementById('btTbody').innerHTML = `<tr><td colspan="8" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>`;
    const d = await apiFetch(params.toString());
    renderBtTable(d.data || []);
    renderPag(d.total, d.page, d.totalPages, 'btPagInfo', 'btPagCtrl', loadMaintenance);
    document.getElementById('btMeta').textContent = `${d.total} lượt bảo trì`;
}
function renderBtTable(rows) {
    const tb = document.getElementById('btTbody');
    if (!rows.length) {
        tb.innerHTML = `<tr><td colspan="8"><div class="empty-state"><i class="fas fa-wrench"></i>Chưa có lịch sử bảo trì</div></td></tr>`;
        return;
    }
    // status ENUM: 'Completed' | 'In Progress' | 'Scheduled'
    const stLabel = s => s === 'Completed' ? 'Hoàn thành' : s === 'In Progress' ? 'Đang xử lý' : s === 'Scheduled' ? 'Lên lịch' : (s || '—');
    const stBadge = s => s === 'Completed' ? 'bt-done' : s === 'In Progress' ? 'bt-proc' : 'bt-sched';

    tb.innerHTML = rows.map(r => `<tr>
        <td class="id-cell">#${r.maintenance_id}</td>
        <td><strong class="col-primary">${esc(r.equipment_name)}</strong></td>
        <td class="date-cell">${fmtDate(r.maintenance_date)}</td>
        <td class="col-muted" style="max-width:180px;font-size:13px">${r.description ? esc(r.description.slice(0,60)) + (r.description.length > 60 ? '…' : '') : '—'}</td>
        <td style="font-size:13px">${r.performed_by ? esc(r.performed_by) : '—'}</td>
        <td class="money-cell">${parseFloat(r.cost || 0) > 0 ? fmtMoney(r.cost) : '—'}</td>
        <td><span class="bt-badge ${stBadge(r.status)}">${stLabel(r.status)}</span></td>
        <td><div class="action-btns">
            <button class="btn-icon" onclick="openEditMaintenance(${r.maintenance_id})" title="Chỉnh sửa"><i class="fas fa-pen"></i></button>
            ${IS_ADMIN ? `<button class="btn-icon delete" onclick="confirmDel('Xóa bản ghi bảo trì này?', () => deleteMaintenance(${r.maintenance_id}))"><i class="fas fa-trash"></i></button>` : ''}
        </div></td>
    </tr>`).join('');
}

async function openMaintenanceModal(preDevId = null) {
    document.getElementById('fBtNgay').value     = today();
    document.getElementById('fBtGia').value      = '';
    document.getElementById('fBtNguoi').value    = '';
    document.getElementById('fBtNoiDung').value  = '';
    // Set trạng thái theo role
    const defaultStatus = IS_RECEPT ? 'In Progress' : 'Scheduled';
    buildStatusSelect('fBtStatus', defaultStatus);
    const d = await apiFetch('get_all_devices_list');
    document.getElementById('fBtDev').innerHTML = '<option value="">-- Chọn thiết bị --</option>'
        + (d.data || []).map(x => `<option value="${x.equipment_id}" ${preDevId == x.equipment_id ? 'selected' : ''}>${esc(x.equipment_name)}</option>`).join('');
    openModal('maintenanceModal');
}
async function quickBaoTri(id) { await openMaintenanceModal(id); }
async function saveMaintenance() {
    if (!validateCompletedStatus('fBtStatus')) return;
    const body = fd({
        action:           'add_maintenance',
        thiet_bi_id:      document.getElementById('fBtDev').value,
        ngay_bao_tri:     document.getElementById('fBtNgay').value,
        trang_thai:       document.getElementById('fBtStatus').value,   // sends 'Completed'/'In Progress'/'Scheduled'
        gia_bao_tri:      document.getElementById('fBtGia').value,
        nguoi_thuc_hien:  document.getElementById('fBtNguoi').value.trim(),
        noi_dung:         document.getElementById('fBtNoiDung').value.trim()
    });
    const d = await apiPost(body);
    toast(d.message, d.success ? 'success' : 'error');
    if (d.success) { closeModal('maintenanceModal'); loadMaintenance(); loadDevices(); loadStats(); }
}
async function openEditMaintenance(id) {
    const d = await apiFetch(`get_maintenance_detail&id=${id}`);
    if (!d.success) { toast('Không tải được dữ liệu', 'error'); return; }
    const r = d.data;
    document.getElementById('fEditBtId').value       = r.maintenance_id;
    document.getElementById('fEditBtDevName').value  = r.equipment_name || '';
    document.getElementById('fEditBtNgay').value     = r.maintenance_date || today();
    document.getElementById('fEditBtGia').value      = r.cost || '';
    document.getElementById('fEditBtNguoi').value    = r.performed_by || '';
    document.getElementById('fEditBtNoiDung').value  = r.description || '';
    buildStatusSelect('fEditBtStatus', r.status || 'Scheduled');
    openModal('editMaintenanceModal');
}
async function saveEditMaintenance() {
    if (!validateCompletedStatus('fEditBtStatus')) return;
    const id = document.getElementById('fEditBtId').value;
    if (!id) { toast('ID không hợp lệ', 'error'); return; }
    const body = fd({
        action:           'update_maintenance',
        id,
        ngay_bao_tri:     document.getElementById('fEditBtNgay').value,
        trang_thai:       document.getElementById('fEditBtStatus').value,
        gia_bao_tri:      document.getElementById('fEditBtGia').value,
        nguoi_thuc_hien:  document.getElementById('fEditBtNguoi').value.trim(),
        noi_dung:         document.getElementById('fEditBtNoiDung').value.trim()
    });
    const d = await apiPost(body);
    toast(d.message, d.success ? 'success' : 'error');
    if (d.success) { closeModal('editMaintenanceModal'); loadMaintenance(); loadDevices(); loadStats(); }
}
async function deleteMaintenance(id) {
    const d = await apiPost(fd({ action: 'delete_maintenance', id }));
    toast(d.message, d.success ? 'success' : 'error');
    if (d.success) { loadMaintenance(); loadDevices(); loadStats(); }
}

// ── PAGINATION ────────────────────────────────────────────────────
function renderPag(total, page, totalPages, infoId, ctrlId, fn) {
    const from = (page - 1) * LIMIT + 1, to = Math.min(page * LIMIT, total);
    document.getElementById(infoId).textContent = total > 0 ? `Hiển thị ${from}–${to} / ${total}` : 'Không có dữ liệu';
    const ctrl = document.getElementById(ctrlId);
    if (totalPages <= 1) { ctrl.innerHTML = ''; return; }
    let h = `<button class="page-btn" onclick="${fn.name}(${page-1})" ${page===1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && Math.abs(i - page) > 2 && i !== 1 && i !== totalPages) {
            if (i === 2 || i === totalPages - 1) h += `<button class="page-btn" disabled>…</button>`; continue;
        }
        h += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="${fn.name}(${i})">${i}</button>`;
    }
    h += `<button class="page-btn" onclick="${fn.name}(${page+1})" ${page===totalPages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
    ctrl.innerHTML = h;
}

// ── CONFIRM / MODAL ───────────────────────────────────────────────
function confirmDel(msg, cb) {
    document.getElementById('confirmMsg').innerHTML = msg + '<br><small style="color:var(--tm)">Hành động này không thể hoàn tác.</small>';
    document.getElementById('confirmOkBtn').onclick = () => { cb(); closeModal('confirmModal'); };
    openModal('confirmModal');
}
const openModal  = id => document.getElementById(id).classList.add('active');
const closeModal = id => document.getElementById(id).classList.remove('active');

// ── API HELPERS ───────────────────────────────────────────────────
async function apiFetch(actionOrParams) {
    let url;
    if (actionOrParams.startsWith('action=') || actionOrParams.includes('&action=')) {
        url = `${API}?${actionOrParams}`;
    } else {
        url = `${API}?action=${actionOrParams}`;
    }
    const r = await fetch(url);
    return r.json();
}
async function apiPost(body) { const r = await fetch(API, { method: 'POST', body }); return r.json(); }
const fd = obj => { const f = new FormData(); Object.entries(obj).forEach(([k,v]) => f.append(k, v)); return f; };

// ── TOAST ─────────────────────────────────────────────────────────
function toast(msg, type = 'info') {
    const icons = { success:'fa-check-circle', error:'fa-exclamation-circle', info:'fa-info-circle', warning:'fa-exclamation-triangle' };
    const el = Object.assign(document.createElement('div'), {
        className: `toast ${type}`,
        innerHTML: `<i class="fas ${icons[type]}"></i><span>${msg}</span>`
    });
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => el.remove(), 3800);
}
