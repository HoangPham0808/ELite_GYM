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
    loadRooms();
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
    // Personal Trainer: thấy cả 2 tab, nhưng tab Thiết bị chỉ hiện thiết bị phòng hôm nay
    // (không ẩn tab devices nữa)
    if (IS_HLV) {
        // Giữ tab devices active theo mặc định (không thay đổi)
        // Banner sẽ được cập nhật sau khi loadDevices() chạy
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
}


// ── ROOMS (GymRoom) ───────────────────────────────────────────────
let rooms = [];

async function loadRooms() {
    try {
        const d = await apiFetch('get_rooms');
        if (!d.success) { console.warn('get_rooms failed:', d.message); return; }
        rooms = d.data || [];
    } catch(e) { console.error('loadRooms error:', e); }
}

function _populateRoomSelect(selectedId) {
    const sel = document.getElementById('fDevRoom');
    if (!sel) return;
    const opts = rooms.map(r =>
        `<option value="${r.room_id}">${esc(r.room_name)}</option>`
    ).join('');
    sel.innerHTML = '<option value="">-- Chưa phân phòng --</option>' + opts;
    if (selectedId !== null && selectedId !== undefined && selectedId !== '') {
        sel.value = String(selectedId);
    }
}

// ── DEVICES (Equipment) ───────────────────────────────────────────
// DB fields: equipment_id, equipment_name, condition_status, type_id, type_name,
//            purchase_price, purchase_date, last_maintenance_date, description, days_remaining
async function loadDevices(page = devPage) {
    devPage = page;

    // ── Personal Trainer: chỉ xem thiết bị phòng mình dạy hôm nay ──
    if (IS_HLV) {
        await loadTrainerRoomDevices(page);
        return;
    }

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

// ── Tải thiết bị theo phòng mà HLV đăng ký dạy hôm nay ──────────
async function loadTrainerRoomDevices(page = 1) {
    document.getElementById('devTbody').innerHTML = `<tr><td colspan="10" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>`;

    const params = new URLSearchParams({
        action:     'get_trainer_room_devices',
        trainer_id: USER_EMP_ID,
        page,
        limit:      LIMIT
    });
    const d = await apiFetch(params.toString());

    const banner    = document.getElementById('hlvRoomBanner');
    const bannerTxt = document.getElementById('hlvRoomBannerText');

    if (!d.success) {
        if (banner) { banner.style.display = 'flex'; bannerTxt.textContent = 'Không thể tải dữ liệu phòng tập.'; }
        document.getElementById('devTbody').innerHTML = `<tr><td colspan="10" class="empty-cell">Không thể tải dữ liệu.</td></tr>`;
        document.getElementById('devMeta').textContent = '—';
        return;
    }

    if (banner) {
        banner.style.display = 'flex';
        if (d.room_name) {
            bannerTxt.innerHTML = `<strong>Hôm nay bạn dạy tại:</strong> <span style="color:var(--tm,#d4a017);font-weight:700">${esc(d.room_name)}</span> — Hiển thị ${d.total} thiết bị trong phòng`;
        } else {
            bannerTxt.innerHTML = `<span style="color:#f87171">${d.message || 'Hôm nay bạn chưa đăng ký buổi dạy nào.'}</span>`;
        }
    }

    renderDevTable(d.data || []);
    renderPag(d.total, d.page, d.totalPages, 'devPagInfo', 'devPagCtrl', loadTrainerRoomDevices);
    document.getElementById('devMeta').textContent = d.room_name
        ? `${d.total} thiết bị — ${esc(d.room_name)}`
        : '0 thiết bị';
}

function renderDevTable(rows) {
    const tb = document.getElementById('devTbody');
    if (!rows.length) {
        tb.innerHTML = `<tr><td colspan="10"><div class="empty-state"><i class="fas fa-dumbbell"></i>Không có thiết bị nào</div></td></tr>`;
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
        const roomHtml = r.room_name
            ? `<span class="room-tag"><i class="fas fa-door-open"></i> ${esc(r.room_name)}</span>`
            : '<span class="day-muted">—</span>';
        return `<tr>
            <td class="id-cell">#${r.equipment_id}</td>
            <td>
                <div class="col-primary">${esc(r.equipment_name)}</div>
                ${r.description ? `<div class="col-muted" style="font-size:11px;margin-top:2px">${esc(r.description.slice(0,50))}${r.description.length>50?'…':''}</div>` : ''}
            </td>
            <td>${r.type_name ? `<span class="cat-tag">${esc(r.type_name)}</span>` : '<span class="day-muted">—</span>'}</td>
            <td>${roomHtml}</td>
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
    _populateRoomSelect('');
    document.getElementById('devModalTitle').innerHTML = '<i class="fas fa-dumbbell" style="color:#d4a017;margin-right:8px"></i>Thêm thiết bị';
    openModal('deviceModal');
}
async function openDevEdit(id) {
    const d = await apiFetch(`get_device_detail&id=${id}`);
    if (!d.success) { toast(d.message, 'error'); return; }
    const r = d.device;
    // Đảm bảo rooms đã load trước khi populate dropdown
    if (rooms.length === 0) await loadRooms();
    document.getElementById('fDevId').value      = r.equipment_id;
    document.getElementById('fDevTen').value     = r.equipment_name         || '';
    document.getElementById('fDevLoai').value    = r.type_id                || '';
    document.getElementById('fDevStatus').value  = r.condition_status       || 'Hoạt động';
    document.getElementById('fDevGia').value     = r.purchase_price         || '';
    document.getElementById('fDevNgayMua').value = r.purchase_date          || '';
    document.getElementById('fDevNgayBao').value = r.last_maintenance_date  || '';
    document.getElementById('fDevMoTa').value    = r.description            || '';
    _populateRoomSelect(r.room_id || '');
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
        mo_ta:            document.getElementById('fDevMoTa').value.trim(),
        phong_tap_id:     document.getElementById('fDevRoom').value
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

// ── IMPORT EXCEL ──────────────────────────────────────────────────
let importData = []; // parsed rows ready to submit

function openImportModal() {
    clearImport();
    document.getElementById('importResult').style.display = 'none';
    document.getElementById('importProgress').style.display = 'none';
    const submitBtn = document.getElementById('importSubmitBtn');
    submitBtn.disabled = true; submitBtn.style.opacity = '.4'; submitBtn.style.cursor = 'not-allowed';
    openModal('importModal');
    // Drag & drop
    const zone = document.getElementById('importDropzone');
    zone.ondragover  = e => { e.preventDefault(); zone.classList.add('drag-over'); };
    zone.ondragleave = () => zone.classList.remove('drag-over');
    zone.ondrop      = e => { e.preventDefault(); zone.classList.remove('drag-over'); handleImportFile(e.dataTransfer.files[0]); };
}

function handleImportFile(file) {
    if (!file) return;
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['xlsx','xls','csv'].includes(ext)) { toast('Chỉ chấp nhận .xlsx, .xls, .csv', 'error'); return; }
    if (file.size > 5 * 1024 * 1024) { toast('File quá lớn (tối đa 5MB)', 'error'); return; }

    const reader = new FileReader();
    reader.onload = e => {
        try {
            const wb = XLSX.read(e.target.result, { type: 'array', cellDates: true });
            const ws = wb.Sheets[wb.SheetNames[0]];
            const json = XLSX.utils.sheet_to_json(ws, { defval: '' });
            if (!json.length) { toast('File không có dữ liệu', 'error'); return; }
            showImportPreview(file.name, json);
        } catch(err) {
            toast('Không đọc được file: ' + err.message, 'error');
        }
    };
    reader.readAsArrayBuffer(file);
}

function showImportPreview(filename, json) {
    // Validate & normalize
    const errors = [];
    const validStatuses = ['Hoạt động','Hỏng','Đang bảo dưỡng','Ngừng sử dụng'];
    const dateRe = /^\d{4}-\d{2}-\d{2}$/;

    // Chuyển Excel serial / Date object / string → YYYY-MM-DD, trả '' nếu không nhận ra
    const toYMD = (v) => {
        if (v === null || v === undefined || v === '') return '';
        if (v instanceof Date) {
            return v.getFullYear() + '-'
                 + String(v.getMonth() + 1).padStart(2, '0') + '-'
                 + String(v.getDate()).padStart(2, '0');
        }
        if (typeof v === 'number' && v > 1000) {
            // Excel serial date
            const d = new Date(Date.UTC(1899, 11, 30) + v * 86400000);
            return d.getUTCFullYear() + '-'
                 + String(d.getUTCMonth() + 1).padStart(2, '0') + '-'
                 + String(d.getUTCDate()).padStart(2, '0');
        }
        const s = String(v).trim();
        if (dateRe.test(s)) return s;
        return ''; // không nhận ra → PHP sẽ tự điền ngày hôm nay
    };

    importData = json.map((row, i) => {
        const r = {};
        Object.keys(row).forEach(k => {
            const key = k.trim();
            const val = row[k];
            if (key === 'purchase_date' || key === 'last_maintenance_date') {
                r[key] = toYMD(val);
            } else {
                r[key] = (val === null || val === undefined) ? '' : String(val).trim();
            }
        });

        if (!r['equipment_name']) { errors.push(`Dòng ${i+2}: thiếu <strong>equipment_name</strong>`); }
        if (r['condition_status'] && !validStatuses.includes(r['condition_status'])) {
            errors.push(`Dòng ${i+2}: condition_status "<em>${esc(r['condition_status'])}</em>" không hợp lệ`);
        }
        return r;
    });

    // Show preview table (first 5 rows)
    const cols = Object.keys(json[0]).map(k => k.trim());
    const previewRows = importData.slice(0, 5);
    document.getElementById('importPreviewHead').innerHTML =
        `<tr style="background:rgba(212,160,23,.08)">${cols.map(c => `<th style="padding:8px 10px;font-size:10px;color:var(--tm);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">${esc(c)}</th>`).join('')}</tr>`;
    document.getElementById('importPreviewBody').innerHTML = previewRows.map(row =>
        `<tr>${cols.map(c => `<td style="padding:7px 10px;border-bottom:1px solid rgba(255,255,255,.04);color:var(--ts)">${esc(row[c]||'')}</td>`).join('')}</tr>`
    ).join('') + (importData.length > 5 ? `<tr><td colspan="${cols.length}" style="padding:8px 10px;color:var(--tm);font-size:11px;text-align:center">... và ${importData.length - 5} dòng nữa</td></tr>` : '');

    document.getElementById('importFileLabel').innerHTML = `<i class="fas fa-file-excel" style="color:#22c55e"></i> ${esc(filename)}`;
    document.getElementById('importRowCount').textContent = `${importData.length} thiết bị`;
    document.getElementById('importPreview').style.display = 'block';
    document.getElementById('importDropzone').style.display = 'none';

    const errBox = document.getElementById('importErrors');
    if (errors.length) {
        errBox.style.display = 'block';
        errBox.innerHTML = `<div class="import-err-title"><i class="fas fa-exclamation-triangle"></i> ${errors.length} lỗi phát hiện</div>`
            + errors.slice(0,10).map(e => `<div class="import-err-item">• ${e}</div>`).join('')
            + (errors.length > 10 ? `<div class="import-err-item" style="color:var(--tm)">... và ${errors.length-10} lỗi khác</div>` : '');
        // Disable submit if critical errors
        const criticalErrors = errors.filter(e => e.includes('equipment_name'));
        if (criticalErrors.length) return;
    } else {
        errBox.style.display = 'none';
    }

    const submitBtn = document.getElementById('importSubmitBtn');
    submitBtn.disabled = false; submitBtn.style.opacity = '1'; submitBtn.style.cursor = 'pointer';
}

function clearImport() {
    importData = [];
    document.getElementById('importPreview').style.display = 'none';
    document.getElementById('importDropzone').style.display = 'block';
    document.getElementById('importFileInput').value = '';
    const submitBtn = document.getElementById('importSubmitBtn');
    submitBtn.disabled = true; submitBtn.style.opacity = '.4'; submitBtn.style.cursor = 'not-allowed';
}

async function submitImport() {
    if (!importData.length) return;
    const submitBtn  = document.getElementById('importSubmitBtn');
    const progressEl = document.getElementById('importProgress');
    const barEl      = document.getElementById('importProgressBar');
    const labelEl    = document.getElementById('importProgressLabel');
    const resultEl   = document.getElementById('importResult');

    submitBtn.disabled = true;
    progressEl.style.display = 'block';
    resultEl.style.display   = 'none';

    let success = 0, failed = 0, failedNames = [];
    const total = importData.length;

    for (let i = 0; i < total; i++) {
        const row = importData[i];
        const pct = Math.round(((i + 1) / total) * 100);
        barEl.style.width  = pct + '%';
        labelEl.textContent = `Đang nhập ${i+1}/${total}...`;

        const body = fd({
            action:           'add_device',
            ten_thiet_bi:     row['equipment_name']          || '',
            loai_id:          '',   // resolved server-side by type_name
            type_name_import: row['type_name']               || '',
            room_name_import: row['room_name']               || '',
            tinh_trang:       row['condition_status']        || 'Hoạt động',
            gia_mua:          row['purchase_price']          || '',
            ngay_mua:         row['purchase_date']           || '',
            ngay_bao_tri_gan: row['last_maintenance_date']   || '',
            mo_ta:            row['description']             || '',
            phong_tap_id:     ''
        });

        try {
            const d = await apiPost(body);
            if (d.success) { success++; }
            else           { failed++; failedNames.push(esc(row['equipment_name'] || `dòng ${i+2}`)); }
        } catch {
            failed++; failedNames.push(`dòng ${i+2}`);
        }
    }

    barEl.style.width  = '100%';
    labelEl.textContent = 'Hoàn tất!';
    setTimeout(() => { progressEl.style.display = 'none'; }, 600);

    resultEl.style.display = 'block';
    resultEl.innerHTML = `
        <div class="import-result-row">
            <span class="import-result-ok"><i class="fas fa-check-circle"></i> ${success} thiết bị nhập thành công</span>
            ${failed ? `<span class="import-result-err"><i class="fas fa-times-circle"></i> ${failed} thất bại</span>` : ''}
        </div>
        ${failedNames.length ? `<div class="import-result-fails">Thất bại: ${failedNames.slice(0,5).join(', ')}${failedNames.length>5?'...':''}</div>` : ''}
    `;

    if (success > 0) {
        toast(`Đã nhập ${success}/${total} thiết bị thành công!`, 'success');
        loadDevices(); loadStats();
    }
}

function downloadTemplate() {
    const headers = ['equipment_name','type_name','condition_status','room_name','purchase_price','purchase_date','last_maintenance_date','description'];
    const example = ['Máy chạy bộ Technogym','Treadmill','Hoạt động','Phòng Cardio','25000000','2024-01-15','2025-06-01','Nhập từ Excel mẫu'];
    const csv = [headers.join(','), example.join(',')].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: 'import_thiet_bi_mau.csv' });
    a.click();
}
