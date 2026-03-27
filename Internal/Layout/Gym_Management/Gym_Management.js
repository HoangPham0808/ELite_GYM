const API_URL = 'Gym_Management_function.php';
let currentPage = 1;
const LIMIT = 12;
let currentView = 'grid';
let deleteId = null;
let searchTimer = null;

// ============ INIT ============
document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadGyms();
    loadPackageTypes();

    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { currentPage = 1; loadGyms(); }, 400);
    });

    ['statusFilter','sortFilter'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => {
            currentPage = 1;
            loadGyms();
        });
    });
});

// ============ PACKAGE TYPES ============
let packageTypes = [];

async function loadPackageTypes() {
    try {
        const res  = await fetch(`${API_URL}?action=get_package_types`);
        const data = await res.json();
        if (!data.success) return;
        packageTypes = data.data;
        const sel = document.getElementById('fPackageType');
        sel.innerHTML = '<option value="">— Tất cả loại gói (không giới hạn) —</option>'
            + data.data.map(t =>
                `<option value="${t.type_id}">${escHtml(t.type_name)}</option>`
            ).join('');
    } catch(e) { console.error('loadPackageTypes:', e); }
}

function getPackageTypeBadge(typeId, typeName, typeColor) {
    if (!typeId) return '';
    const color = typeColor || '#6b7280';
    return `<span style="display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:20px;
        font-size:11px;font-weight:700;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);
        color:rgba(255,255,255,.75);margin-top:3px">
        <span style="width:7px;height:7px;border-radius:2px;background:${escHtml(color)};flex-shrink:0"></span>
        ${escHtml(typeName || '')}
    </span>`;
}

// ============ STATS ============
async function loadStats() {
    try {
        const res = await fetch(`${API_URL}?action=get_stats`);
        const d = await res.json();
        if (!d.success) return;
        document.getElementById('statTotal').textContent       = d.total       ?? 0;
        document.getElementById('statActive').textContent      = d.active      ?? 0;
        document.getElementById('statMaintenance').textContent = d.maintenance ?? 0;
        document.getElementById('statCapacity').textContent    = d.capacity    ?? 0;
        document.getElementById('statThietBi').textContent     = d.thiet_bi    ?? 0;
    } catch(e) {}
}

// ============ LOAD LIST ============
async function loadGyms() {
    const search = document.getElementById('searchInput').value.trim();
    const status = document.getElementById('statusFilter').value;
    const sort   = document.getElementById('sortFilter').value;

    const url = `${API_URL}?action=get_gyms&page=${currentPage}&limit=${LIMIT}`
              + `&search=${encodeURIComponent(search)}`
              + `&status=${encodeURIComponent(status)}`
              + `&sort=${encodeURIComponent(sort)}`;

    try {
        const res = await fetch(url);
        const d   = await res.json();
        if (!d.success) return;
        document.getElementById('tableTotal').textContent = `${d.total} phòng tập`;
        renderGrid(d.data);
        renderTable(d.data);
        renderPagination(d.page, d.totalPages, d.total);
    } catch(e) { console.error(e); }
}

// ============ VIEW TOGGLE ============
function setView(v) {
    currentView = v;
    document.getElementById('gridView').style.display = v === 'grid' ? 'grid' : 'none';
    document.getElementById('listView').style.display = v === 'list' ? 'block' : 'none';
    document.getElementById('btnGrid').classList.toggle('active', v === 'grid');
    document.getElementById('btnList').classList.toggle('active', v === 'list');
}

// ============ HELPERS ============

function getStatusInfo(s) {
    s = (s || '').replace(/[\r\n]/g, '').trim();
    if (s === 'Active')      return { cls: 'active',   label: '● Hoạt động' };
    if (s === 'Maintenance') return { cls: 'maintain', label: '⚙ Bảo trì'   };
    if (s === 'Closed')      return { cls: 'closed',   label: '✕ Đóng cửa'  };
    return                          { cls: 'closed',   label: '✕ Đóng cửa'  };
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ============ RENDER GRID ============
function renderGrid(data) {
    const grid = document.getElementById('gridView');
    if (!data.length) {
        grid.innerHTML = `<div class="empty-state">
            <i class="fas fa-chess-board"></i>
            <h4>Không có phòng tập nào</h4>
            <p>Thêm phòng tập đầu tiên để bắt đầu</p>
        </div>`;
        return;
    }
    grid.innerHTML = data.map(r => {
        const st   = getStatusInfo(r.status);
        return `
        <div class="room-card" onclick="openDetail(${r.room_id})">
            <div class="room-card-header">
                <div class="room-card-bg"></div>
                <div class="room-card-icon">🏋️</div>
                <span class="room-status-badge ${st.cls}">${st.label}</span>
            </div>
            <div class="room-card-body">
                <div class="room-card-name">${escHtml(r.room_name)}</div>
                <div class="room-card-type" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                    ${getPackageTypeBadge(r.package_type_id, r.package_type_name, r.package_type_color)}
                </div>
                <div class="room-card-stats">
                    <div class="room-stat-item">
                        <div class="room-stat-value">${r.capacity ?? '—'}</div>
                        <div class="room-stat-label">Sức chứa</div>
                    </div>
                    <div class="room-stat-item">
                        <div class="room-stat-value">${r.area ? r.area + ' m²' : '—'}</div>
                        <div class="room-stat-label">Diện tích</div>
                    </div>
                    <div class="room-stat-item">
                        <div class="room-stat-value">${r.floor != null ? 'Tầng ' + r.floor : '—'}</div>
                        <div class="room-stat-label">Vị trí</div>
                    </div>
                    <div class="room-stat-item">
                        <div class="room-stat-value">${r.open_time ? r.open_time.slice(0,5) : '—'}</div>
                        <div class="room-stat-label">Giờ mở</div>
                    </div>
                    <div class="room-stat-item">
                        <div class="room-stat-value">${r.close_time ? r.close_time.slice(0,5) : '—'}</div>
                        <div class="room-stat-label">Giờ đóng</div>
                    </div>
                </div>
                <div class="room-card-desc">${escHtml(r.description || 'Chưa có mô tả')}</div>
                <div class="room-card-footer" onclick="event.stopPropagation()">
                    <button class="btn-action btn-detail" onclick="openDetail(${r.room_id})">
                        <i class="fas fa-eye"></i> Chi tiết
                    </button>
                    <button class="btn-action btn-edit" onclick="openEditModal(${r.room_id})">
                        <i class="fas fa-pen"></i> Sửa
                    </button>
                    <button class="btn-action btn-delete" onclick="openConfirmDelete(${r.room_id}, '${escHtml(r.room_name)}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>`;
    }).join('');
}

// ============ RENDER TABLE ============
function renderTable(data) {
    const tbody = document.getElementById('gymTbody');
    if (!data.length) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:40px;color:rgba(255,255,255,0.3)">Không có dữ liệu</td></tr>`;
        return;
    }
    tbody.innerHTML = data.map(r => {
        const st    = getStatusInfo(r.status);
        const avcls = 'multipurpose';
        return `<tr>
            <td>
                <div class="room-name-cell">
                    <div class="room-avatar ${avcls}">🏋️</div>
                    <div>
                        <div class="room-name">${escHtml(r.room_name)}</div>
                        <div class="room-floor">${r.floor != null ? "Tầng " + r.floor : "—"}</div>
                    </div>
                </div>
            </td>
            <td>${getPackageTypeBadge(r.package_type_id, r.package_type_name, r.package_type_color)}</td>
            <td>${r.capacity ?? '—'} người</td>
            <td>${r.area ? r.area + ' m²' : '—'}</td>
            <td><span class="status-badge ${st.cls}">${st.label}</span></td>
            <td>${r.so_thiet_bi ?? 0} thiết bị</td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon view"   title="Chi tiết" onclick="openDetail(${r.room_id})"><i class="fas fa-eye"></i></button>
                    <button class="btn-icon edit"   title="Sửa"      onclick="openEditModal(${r.room_id})"><i class="fas fa-pen"></i></button>
                    <button class="btn-icon delete" title="Xóa"      onclick="openConfirmDelete(${r.room_id}, '${escHtml(r.room_name)}')"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ============ PAGINATION ============
function renderPagination(page, totalPages, total) {
    const info = document.getElementById('paginationInfo');
    const ctrl = document.getElementById('paginationControls');
    const from = Math.min((page - 1) * LIMIT + 1, total);
    const to   = Math.min(page * LIMIT, total);
    info.textContent = `Hiển thị ${from}–${to} / ${total} phòng tập`;

    let btns = `<button class="page-btn" onclick="goPage(${page-1})" ${page<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= page-1 && i <= page+1)) {
            btns += `<button class="page-btn ${i===page?'active':''}" onclick="goPage(${i})">${i}</button>`;
        } else if (i === page-2 || i === page+2) {
            btns += `<button class="page-btn" disabled>…</button>`;
        }
    }
    btns += `<button class="page-btn" onclick="goPage(${page+1})" ${page>=totalPages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
    ctrl.innerHTML = btns;
}

function goPage(p) {
    currentPage = p;
    loadGyms();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ============ ADD MODAL ============
function openAddModal() {
    document.getElementById('gymId').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-chess-board" style="color:#fb923c;margin-right:8px"></i>Thêm phòng tập mới';
    ['fTenPhong','fMoTa'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('fPackageType').value = '';
    document.getElementById('fTrangThai').value  = 'Active';
    document.getElementById('fSucChua').value    = '';
    document.getElementById('fDienTich').value   = '';
    document.getElementById('fTang').value       = '';
    document.getElementById('fGioMo').value      = '06:00';
    document.getElementById('gymModal').classList.add('active');
}

function closeGymModal() { document.getElementById('gymModal').classList.remove('active'); }

// ============ EDIT MODAL ============
async function openEditModal(id) {
    try {
        const res = await fetch(`${API_URL}?action=get_detail&id=${id}`);
        const d   = await res.json();
        if (!d.success) { showToast('Không tải được dữ liệu', 'error'); return; }
        const r = d.room;
        document.getElementById('gymId').value      = r.room_id;
        document.getElementById('fTenPhong').value  = r.room_name   || '';
        document.getElementById('fTrangThai').value = (r.status || 'Active').replace(/[\r\n]/g, '').trim();
        document.getElementById('fSucChua').value   = r.capacity    || '';
        document.getElementById('fDienTich').value  = r.area        || '';
        document.getElementById('fTang').value      = r.floor       ?? '';
        document.getElementById('fGioMo').value     = r.open_time   ? r.open_time.slice(0,5)   : '06:00';
        document.getElementById('fGioDong').value   = r.close_time  ? r.close_time.slice(0,5)  : '22:00';
        document.getElementById('fMoTa').value          = r.description || '';
        document.getElementById('fPackageType').value   = r.package_type_id || '';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-pen" style="color:#fb923c;margin-right:8px"></i>Chỉnh sửa phòng tập';
        document.getElementById('gymModal').classList.add('active');
    } catch(e) { showToast('Lỗi kết nối', 'error'); }
}

// ============ SAVE ============
async function saveGym() {
    const id       = document.getElementById('gymId').value;
    const tenPhong = document.getElementById('fTenPhong').value.trim();
    if (!tenPhong) { showToast('Vui lòng nhập tên phòng tập', 'error'); return; }

    const body = new FormData();
    body.append('action',     id ? 'update_gym' : 'add_gym');
    if (id) body.append('id', id);
    body.append('ten_phong',  tenPhong);
    body.append('trang_thai', document.getElementById('fTrangThai').value);
    body.append('suc_chua',   document.getElementById('fSucChua').value);
    body.append('dien_tich',  document.getElementById('fDienTich').value);
    body.append('tang',       document.getElementById('fTang').value);
    body.append('gio_mo',     document.getElementById('fGioMo').value);
    body.append('gio_dong',   document.getElementById('fGioDong').value);
    body.append('mo_ta',         document.getElementById('fMoTa').value.trim());
    body.append('package_type_id', document.getElementById('fPackageType').value);

    try {
        const res = await fetch(API_URL, { method: 'POST', body });
        const d   = await res.json();
        if (d.success) {
            showToast(d.message, 'success');
            closeGymModal();
            loadGyms();
            loadStats();
        } else {
            showToast(d.message || 'Lỗi không xác định', 'error');
        }
    } catch(e) { showToast('Lỗi kết nối server', 'error'); }
}

// ============ DETAIL ============
async function openDetail(id) {
    document.getElementById('detailModal').classList.add('active');
    document.getElementById('detailContent').innerHTML =
        `<div style="text-align:center;padding:40px;color:rgba(255,255,255,0.3)">
            <i class="fas fa-spinner fa-spin" style="font-size:28px"></i>
         </div>`;
    try {
        const res = await fetch(`${API_URL}?action=get_detail&id=${id}`);
        const d   = await res.json();
        if (!d.success) {
            document.getElementById('detailContent').innerHTML = '<p style="color:#f87171;padding:20px">Không tải được dữ liệu</p>';
            return;
        }
        renderDetail(d);
    } catch(e) {
        document.getElementById('detailContent').innerHTML = '<p style="color:#f87171;padding:20px">Lỗi kết nối</p>';
    }
}

function renderDetail(d) {
    const r    = d.room;
    const st   = getStatusInfo(r.status);
    // Equipment: DB fields equipment_name, condition_status
    const thietBiHtml = d.thiet_bi && d.thiet_bi.length
        ? `<div class="thiet-bi-list">` + d.thiet_bi.map(tb => `
            <div class="thiet-bi-item">
                <div class="thiet-bi-icon"><i class="fas fa-dumbbell"></i></div>
                <div>
                    <div class="thiet-bi-name">${escHtml(tb.equipment_name)}</div>
                    <div class="thiet-bi-status">${escHtml(tb.condition_status || '—')}</div>
                </div>
            </div>`).join('') + `</div>`
        : `<p style="color:rgba(255,255,255,0.3);font-size:13px">Chưa có thiết bị nào được liên kết</p>`;

    document.getElementById('detailContent').innerHTML = `
        <div class="detail-room-header">
            <div class="detail-room-icon">🏋️</div>
            <div>
                <div class="detail-room-name">${escHtml(r.room_name)}</div>
                <div class="detail-room-sub"><span class="status-badge ${st.cls}" style="font-size:12px">${st.label}</span> ${r.package_type_id ? getPackageTypeBadge(r.package_type_id, r.package_type_name, r.package_type_color) : ''}</div>
            </div>
        </div>
        <div class="detail-section">
            <div class="detail-section-title">Thông tin chung</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-item-label">Sức chứa</div>
                    <div class="detail-item-value">${r.capacity ? r.capacity + ' người' : '—'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Diện tích</div>
                    <div class="detail-item-value">${r.area ? r.area + ' m²' : '—'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Tầng</div>
                    <div class="detail-item-value">${r.floor != null ? 'Tầng ' + r.floor : '—'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Giờ mở cửa</div>
                    <div class="detail-item-value">${r.open_time ? r.open_time.slice(0,5) : '—'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Giờ đóng cửa</div>
                    <div class="detail-item-value">${r.close_time ? r.close_time.slice(0,5) : '—'}</div>
                </div>
                <div class="detail-item" style="grid-column:1/-1">
                    <div class="detail-item-label">Mô tả</div>
                    <div class="detail-item-value" style="font-weight:400;line-height:1.6;color:rgba(255,255,255,0.65)">${escHtml(r.description || 'Chưa có mô tả')}</div>
                </div>
            </div>
        </div>
        <div class="detail-section">
            <div class="detail-section-title">Thiết bị (${d.thiet_bi ? d.thiet_bi.length : 0})</div>
            ${thietBiHtml}
        </div>`;
}

function closeDetailModal() { document.getElementById('detailModal').classList.remove('active'); }

// ============ DELETE ============
function openConfirmDelete(id, name) {
    deleteId = id;
    document.getElementById('deleteGymName').textContent = name;
    document.getElementById('confirmModal').classList.add('active');
}

function closeConfirmModal() {
    deleteId = null;
    document.getElementById('confirmModal').classList.remove('active');
}

async function doDelete() {
    if (!deleteId) return;
    const body = new FormData();
    body.append('action', 'delete_gym');
    body.append('id', deleteId);
    try {
        const res = await fetch(API_URL, { method: 'POST', body });
        const d   = await res.json();
        if (d.success) {
            showToast(d.message, 'success');
            closeConfirmModal();
            loadGyms();
            loadStats();
        } else {
            showToast(d.message || 'Lỗi không xác định', 'error');
        }
    } catch(e) { showToast('Lỗi kết nối server', 'error'); }
}

// ============ TOAST ============
function showToast(msg, type = 'info') {
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle' };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<i class="fas ${icons[type]}"></i><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.classList.remove('active');
    });
});
