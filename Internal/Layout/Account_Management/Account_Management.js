const API = 'Account_Management_function.php';
const LIMIT = 15;
let nvPage = 1, khPage = 1, logPage = 1;
let statsCache = {};

// ── INIT ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn,.tab-content').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
        });
    });
    // Close modal on bg click
    document.querySelectorAll('.modal-overlay').forEach(o =>
        o.addEventListener('click', e => { if (e.target === o) o.classList.remove('active'); }));

    // Toggle label
    document.getElementById('fAccStatus').addEventListener('change', function() {
        document.getElementById('toggleLabel').textContent = this.checked ? 'Hoạt động' : 'Đã khóa';
    });

    // Filters
    document.getElementById('nvSearch').addEventListener('input',  debounce(() => loadAccounts('staff', 1), 350));
    document.getElementById('nvRole').addEventListener('change',   () => loadAccounts('staff', 1));
    document.getElementById('nvStatus').addEventListener('change', () => loadAccounts('staff', 1));
    document.getElementById('khSearch').addEventListener('input',  debounce(() => loadAccounts('customer', 1), 350));
    document.getElementById('khStatus').addEventListener('change', () => loadAccounts('customer', 1));
    document.getElementById('logSearch').addEventListener('input', debounce(() => loadLoginHistory(1), 350));
    document.getElementById('logResult').addEventListener('change',() => loadLoginHistory(1));
    document.getElementById('logDate').addEventListener('change',  () => loadLoginHistory(1));

    loadStats();
    loadAccounts('staff');
    loadAccounts('customer');
    loadLoginHistory();
});

const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const fmtDT = s => {
    if (!s || s === '0000-00-00 00:00:00') return '—';
    const d = new Date(s);
    return `${d.getDate().toString().padStart(2,'0')}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getFullYear()} ${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
};
const fmtDate = s => (!s||s==='0000-00-00') ? '—' : s.split('-').reverse().join('/');

// ── STATS ─────────────────────────────────────────────────────────
async function loadStats() {
    const d = await apiFetch('get_stats');
    statsCache = d;
    document.getElementById('sTotal').textContent      = d.total       ?? 0;
    document.getElementById('sActive').textContent     = d.active      ?? 0;
    document.getElementById('sAdmin').textContent      = d.admin       ?? 0;
    document.getElementById('sNV').textContent         = d.employee   ?? 0;
    document.getElementById('sKH').textContent         = d.customer  ?? 0;
    document.getElementById('sTodayLogin').textContent = d.today_login ?? 0;
    document.getElementById('rbAdmin').textContent     = d.admin       ?? 0;
    document.getElementById('rbNV').textContent        = d.employee   ?? 0;
    document.getElementById('logSuccessToday').textContent = d.today_login ?? 0;
    document.getElementById('logFailToday').textContent    = d.fail_today  ?? 0;
}

// ── LOAD ACCOUNTS ─────────────────────────────────────────────────
async function loadAccounts(type, page) {
    const isStaff = type === 'staff';
    if (isStaff) { if (page !== undefined) nvPage = page; }
    else         { if (page !== undefined) khPage = page; }

    const pg     = isStaff ? nvPage : khPage;
    const search = document.getElementById(isStaff ? 'nvSearch' : 'khSearch').value.trim();
    const status = document.getElementById(isStaff ? 'nvStatus' : 'khStatus').value;
    const role   = isStaff ? (document.getElementById('nvRole').value || 'Admin,NhanVien') : 'Customer';

    // Build individual role param for staff (allow filter within Admin/NV)
    let roleParam = '';
    if (isStaff) {
        const sel = document.getElementById('nvRole').value;
        roleParam = sel || ''; // empty = both; we filter in PHP by checking for staff roles
    } else {
        roleParam = 'Customer';
    }

    const tbId   = isStaff ? 'nvTbody'  : 'khTbody';
    const metaId = isStaff ? 'nvMeta'   : 'khMeta';
    const piId   = isStaff ? 'nvPagInfo': 'khPagInfo';
    const pcId   = isStaff ? 'nvPagCtrl': 'khPagCtrl';
    const cols   = isStaff ? 8 : 8;

    document.getElementById(tbId).innerHTML = `<tr><td colspan="${cols}" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>`;

    const params = new URLSearchParams({ action:'get_accounts', page:pg, limit:LIMIT, search, role:roleParam, status });
    // For staff tab without specific role filter, we need Admin+NhanVien
    // We handle this by passing empty role and filtering in display
    const d = await apiFetch(params.toString());
    const rows = (d.data || []).filter(r => isStaff
        ? (r.role === 'Admin' || r.role === 'Employee')
        : r.role === 'Customer');

    renderAccountTable(rows, type, tbId);
    renderPag(d.total, pg, d.totalPages, piId, pcId, p => loadAccounts(type, p));
    document.getElementById(metaId).textContent = `${rows.length} / ${d.total} tài khoản`;
    document.getElementById(isStaff ? 'tcStaff' : 'tcCustomer').textContent = d.total;
}

function avatarCls(role) {
    return role === 'Admin' ? 'av-admin' : role === 'Employee' ? 'av-staff' : 'av-customer';
}
function roleBadge(role) {
    if (role === 'Admin')    return `<span class="role-badge rb-tag-admin"><i class="fas fa-shield-halved"></i> Admin</span>`;
    if (role === 'Employee') return `<span class="role-badge rb-tag-staff"><i class="fas fa-id-badge"></i> Nhân viên</span>`;
    return `<span class="role-badge rb-tag-customer"><i class="fas fa-person"></i> Khách hàng</span>`;
}
function avatarText(name, user) {
    const src = name || user || '?';
    const parts = src.trim().split(' ');
    return parts.length >= 2 ? (parts[0][0] + parts[parts.length-1][0]).toUpperCase() : src.slice(0,2).toUpperCase();
}

function renderAccountTable(rows, type, tbId) {
    const tb = document.getElementById(tbId);
    const isStaff = type === 'staff';
    if (!rows.length) {
        tb.innerHTML = `<tr><td colspan="8"><div class="empty-state"><i class="fas fa-users-slash"></i>Không có tài khoản nào</div></td></tr>`;
        return;
    }
    tb.innerHTML = rows.map(r => {
        const avTxt = avatarText(r.full_name, r.username);
        const avCls = avatarCls(r.role);
        const stBadge = r.is_active == 1
            ? `<span class="status-badge sb-active">Hoạt động</span>`
            : `<span class="status-badge sb-locked">Đã khóa</span>`;
        const lockIcon = r.is_active == 1 ? 'fa-lock' : 'fa-lock-open';
        const lockTitle = r.is_active == 1 ? 'Khóa tài khoản' : 'Mở khóa';
        const contact = r.email || r.phone
            ? `<div style="font-size:12px;color:var(--tm)">${r.email ? esc(r.email) : esc(r.phone||'')}</div>`
            : `<span style="color:var(--tm);font-size:12px">—</span>`;
        const loginInfo = `<span class="login-count"><i class="fas fa-right-to-bracket"></i> ${r.login_count||0} lần</span>`;

        if (isStaff) return `<tr>
            <td>
                <div class="user-cell">
                    <div class="avatar ${avCls}">${esc(avTxt)}</div>
                    <div>
                        <div class="user-name">${esc(r.username)}</div>
                        <div class="user-id">#${r.account_id}</div>
                    </div>
                </div>
            </td>
            <td><div style="font-size:13px;font-weight:600;color:var(--tp)">${r.full_name ? esc(r.full_name) : '<span style="color:var(--tm)">—</span>'}</div></td>
            <td>${contact}</td>
            <td>${roleBadge(r.role)}</td>
            <td>${stBadge}</td>
            <td>${loginInfo}</td>
            <td style="font-size:12px;color:var(--tm)">${fmtDate(r.created_at ? r.created_at.split(' ')[0] : '')}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon" onclick="openDetail(${r.account_id})" title="Chi tiết"><i class="fas fa-eye"></i></button>
                    <button class="btn-icon" onclick="openEdit(${r.account_id})" title="Sửa"><i class="fas fa-pen"></i></button>
                    <button class="btn-icon" onclick="openResetPass(${r.account_id},'${esc(r.username)}')" title="Đặt lại mật khẩu"><i class="fas fa-key"></i></button>
                    <button class="btn-icon lock-btn" onclick="toggleStatus(${r.account_id},'${esc(r.username)}')" title="${lockTitle}"><i class="fas ${lockIcon}"></i></button>
                    <button class="btn-icon del" onclick="confirmDel('Xóa tài khoản <strong>${esc(r.username)}</strong>?', () => deleteAccount(${r.account_id}))" title="Xóa"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`;

        // Customer row
        return `<tr>
            <td>
                <div class="user-cell">
                    <div class="avatar ${avCls}">${esc(avTxt)}</div>
                    <div>
                        <div class="user-name">${esc(r.username)}</div>
                        <div class="user-id">#${r.account_id}</div>
                    </div>
                </div>
            </td>
            <td><div style="font-size:13px;font-weight:600;color:var(--tp)">${r.full_name ? esc(r.full_name) : '<span style="color:var(--tm)">—</span>'}</div></td>
            <td>${contact}</td>
            <td>${stBadge}</td>
            <td>${loginInfo}</td>
            <td style="font-size:12px;color:var(--tm)">${r.last_login ? fmtDT(r.last_login) : '—'}</td>
            <td style="font-size:12px;color:var(--tm)">${fmtDate(r.created_at ? r.created_at.split(' ')[0] : '')}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon" onclick="openDetail(${r.account_id})" title="Chi tiết"><i class="fas fa-eye"></i></button>
                    <button class="btn-icon" onclick="openEdit(${r.account_id})" title="Sửa"><i class="fas fa-pen"></i></button>
                    <button class="btn-icon" onclick="openResetPass(${r.account_id},'${esc(r.username)}')" title="Đặt lại mật khẩu"><i class="fas fa-key"></i></button>
                    <button class="btn-icon lock-btn" onclick="toggleStatus(${r.account_id},'${esc(r.username)}')" title="${lockTitle}"><i class="fas ${lockIcon}"></i></button>
                    <button class="btn-icon del" onclick="confirmDel('Xóa tài khoản <strong>${esc(r.username)}</strong>?', () => deleteAccount(${r.account_id}))" title="Xóa"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── ACCOUNT CRUD ──────────────────────────────────────────────────
function openAddModal(type) {
    document.getElementById('fAccId').value = '';
    document.getElementById('fAccUser').value = '';
    document.getElementById('fAccPass').value = '';
    document.getElementById('fAccStatus').checked = true;
    document.getElementById('toggleLabel').textContent = 'Hoạt động';
    document.getElementById('fAccUser').disabled = false;
    document.getElementById('passHint').style.display = 'none';
    document.getElementById('fPassLabel').innerHTML = 'Mật khẩu <span class="req">*</span>';
    document.getElementById('fAccRole').value = type === 'customer' ? 'Customer' : 'Employee';
    document.getElementById('accModalTitle').innerHTML = `<i class="fas fa-user-plus" style="color:var(--gold);margin-right:8px"></i>Thêm tài khoản`;
    openModal('accountModal');
}
async function openEdit(id) {
    const d = await apiFetch(`get_account_detail&id=${id}`);
    if (!d.success) { toast(d.message,'error'); return; }
    const r = d.account;
    document.getElementById('fAccId').value            = r.account_id;
    document.getElementById('fAccUser').value          = r.username;
    document.getElementById('fAccUser').disabled       = true;
    document.getElementById('fAccPass').value          = '';
    document.getElementById('fAccRole').value          = r.role;
    document.getElementById('fAccStatus').checked      = r.is_active == 1;
    document.getElementById('toggleLabel').textContent = r.is_active == 1 ? 'Hoạt động' : 'Đã khóa';
    document.getElementById('passHint').style.display  = 'block';
    document.getElementById('fPassLabel').innerHTML    = 'Mật khẩu mới';
    document.getElementById('accModalTitle').innerHTML = `<i class="fas fa-pen" style="color:var(--gold);margin-right:8px"></i>Sửa tài khoản`;
    openModal('accountModal');
}
async function saveAccount() {
    const id     = document.getElementById('fAccId').value;
    const user   = document.getElementById('fAccUser').value.trim();
    const pass   = document.getElementById('fAccPass').value.trim();
    const role   = document.getElementById('fAccRole').value;
    const status = document.getElementById('fAccStatus').checked ? 1 : 0;
    const body   = fd({ action: id ? 'update_account' : 'add_account',
        ...(id ? {id} : {username: user}),
        password: pass, role: role, is_active: status
    });
    const d = await apiPost(body);
    toast(d.message, d.success ? 'success' : 'error');
    if (d.success) { closeModal('accountModal'); loadStats(); loadAccounts('staff'); loadAccounts('customer'); }
}
async function deleteAccount(id) {
    const d = await apiPost(fd({ action:'delete_account', id }));
    toast(d.message, d.success ? 'success' : 'error');
    if (d.success) { loadStats(); loadAccounts('staff'); loadAccounts('customer'); }
}
async function toggleStatus(id, username) {
    confirmDel(`${document.querySelector(`button[onclick*="toggleStatus(${id}"] i`).classList.contains('fa-lock') ? 'Khóa' : 'Mở khóa'} tài khoản <strong>${esc(username)}</strong>?`, async () => {
        const d = await apiPost(fd({ action:'toggle_status', id }));
        toast(d.message, d.success ? 'success' : 'error');
        if (d.success) { loadStats(); loadAccounts('staff'); loadAccounts('customer'); }
    });
}

// ── DETAIL MODAL ──────────────────────────────────────────────────
async function openDetail(id) {
    document.getElementById('detailContent').innerHTML = '<div class="loading-cell"><i class="fas fa-spinner fa-spin"></i></div>';
    openModal('detailModal');
    const d = await apiFetch(`get_account_detail&id=${id}`);
    if (!d.success) { toast(d.message,'error'); return; }
    const r = d.account, hist = d.history;
    const avTxt = avatarText(r.full_name, r.username);
    const avCls = avatarCls(r.role);
    const stBadge = r.is_active == 1
        ? `<span class="status-badge sb-active">Hoạt động</span>`
        : `<span class="status-badge sb-locked">Đã khóa</span>`;
    document.getElementById('detailContent').innerHTML = `
        <div class="acc-detail-hero">
            <div class="acc-detail-avatar ${avCls}">${esc(avTxt)}</div>
            <div>
                <div class="acc-detail-name">${r.full_name ? esc(r.full_name) : esc(r.username)}</div>
                <div class="acc-detail-user">
                    <i class="fas fa-at" style="color:var(--tm);font-size:11px"></i>
                    ${esc(r.username)}
                    &nbsp;${roleBadge(r.role)}
                </div>
            </div>
            <div style="margin-left:auto">${stBadge}</div>
        </div>
        <div class="acc-detail-grid">
            <div class="di-block"><div class="di-label">Email</div><div class="di-val">${r.email ? esc(r.email) : '—'}</div></div>
            <div class="di-block"><div class="di-label">Số điện thoại</div><div class="di-val">${r.phone ? esc(r.phone) : '—'}</div></div>
            <div class="di-block"><div class="di-label">Ngày tạo</div><div class="di-val">${fmtDT(r.created_at)}</div></div>
            <div class="di-block"><div class="di-label">Đăng nhập gần nhất</div><div class="di-val">${fmtDT(r.last_login)}</div></div>
        </div>
        <div class="detail-sec-title"><i class="fas fa-clock-rotate-left"></i> Lịch sử đăng nhập gần đây</div>
        ${hist.length ? `<table class="detail-hist">
            <thead><tr><th>Thời gian</th><th>IP</th><th>Kết quả</th><th>Ghi chú</th></tr></thead>
            <tbody>${hist.map(h => `<tr>
                <td>${fmtDT(h.login_time)}</td>
                <td style="font-size:11px;font-family:monospace;color:var(--tm)">${h.ip_address||'—'}</td>
                <td><span class="log-badge ${h.result==='Success'?'lb-ok':'lb-fail'}">${esc(h.result)}</span></td>
                <td style="font-size:12px;color:var(--tm)">${h.note ? esc(h.note) : '—'}</td>
            </tr>`).join('')}</tbody>
        </table>` : '<div class="empty-state" style="padding:24px"><i class="fas fa-clock-rotate-left"></i>Chưa có lịch sử đăng nhập</div>'}`;
}

// ── RESET PASSWORD ────────────────────────────────────────────────
function openResetPass(id, username) {
    document.getElementById('fRpId').value   = id;
    document.getElementById('fRpPass').value = '';
    document.getElementById('fRpUser').textContent = username;
    openModal('resetPassModal');
}
async function doResetPass() {
    const id   = document.getElementById('fRpId').value;
    const pass = document.getElementById('fRpPass').value.trim();
    const d = await apiPost(fd({ action:'reset_password', id, password:pass }));
    toast(d.message, d.success ? 'success' : 'error');
    if (d.success) closeModal('resetPassModal');
}

// ── LOGIN HISTORY ─────────────────────────────────────────────────
async function loadLoginHistory(page = logPage) {
    logPage = page;
    const search = document.getElementById('logSearch').value.trim();
    const result = document.getElementById('logResult').value;
    const date   = document.getElementById('logDate').value;
    const params = new URLSearchParams({ action:'get_login_history', page, limit:20, search, result, date });
    document.getElementById('logTbody').innerHTML = `<tr><td colspan="7" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td></tr>`;
    const d = await apiFetch(params.toString());
    renderLogTable(d.data || []);
    renderPag(d.total, page, d.totalPages, 'logPagInfo', 'logPagCtrl', loadLoginHistory);
    document.getElementById('logMeta').textContent = `${d.total} bản ghi`;
}
function renderLogTable(rows) {
    const tb = document.getElementById('logTbody');
    if (!rows.length) { tb.innerHTML = `<tr><td colspan="7"><div class="empty-state"><i class="fas fa-clock-rotate-left"></i>Không có dữ liệu</div></td></tr>`; return; }
    tb.innerHTML = rows.map(r => `<tr>
        <td style="font-size:12px;white-space:nowrap;color:var(--ts)">${fmtDT(r.login_time)}</td>
        <td>
            <div class="user-cell">
                <div class="avatar ${avatarCls(r.role)}" style="width:28px;height:28px;font-size:11px">${avatarText(r.full_name,r.username)}</div>
                <div class="user-name" style="font-size:13px">${esc(r.username)}</div>
            </div>
        </td>
        <td style="font-size:13px;color:var(--ts)">${r.full_name ? esc(r.full_name) : '<span style="color:var(--tm)">—</span>'}</td>
        <td>${roleBadge(r.role)}</td>
        <td style="font-size:11px;font-family:monospace;color:var(--tm)">${r.ip_address||'—'}</td>
        <td><span class="log-badge ${r.result==='Success'?'lb-ok':'lb-fail'}">${esc(r.result)}</span></td>
        <td style="font-size:12px;color:var(--tm)">${r.note ? esc(r.note) : '—'}</td>
    </tr>`).join('');
}
async function clearOldHistory() {
    const d = await apiPost(fd({ action:'clear_login_history' }));
    toast(d.message, d.success ? 'success' : 'error');
    if (d.success) { loadLoginHistory(1); loadStats(); }
}

// ── PAGINATION ────────────────────────────────────────────────────
function renderPag(total, page, totalPages, piId, pcId, fn) {
    const from = total > 0 ? (page-1)*LIMIT+1 : 0, to = Math.min(page*LIMIT, total);
    document.getElementById(piId).textContent = total > 0 ? `Hiển thị ${from}–${to} / ${total}` : 'Không có dữ liệu';
    const ctrl = document.getElementById(pcId);
    if (totalPages <= 1) { ctrl.innerHTML = ''; return; }
    let h = `<button class="page-btn" onclick="(${fn.toString()})(${page-1})" ${page===1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && Math.abs(i-page) > 2 && i !== 1 && i !== totalPages) {
            if (i === 2 || i === totalPages-1) h += `<button class="page-btn" disabled>…</button>`;
            continue;
        }
        h += `<button class="page-btn ${i===page?'active':''}" onclick="(${fn.toString()})(${i})">${i}</button>`;
    }
    h += `<button class="page-btn" onclick="(${fn.toString()})(${page+1})" ${page===totalPages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
    ctrl.innerHTML = h;
}

// ── CONFIRM ───────────────────────────────────────────────────────
function confirmDel(msg, cb) {
    document.getElementById('confirmMsg').innerHTML = msg;
    document.getElementById('confirmOkBtn').onclick = () => { cb(); closeModal('confirmModal'); };
    openModal('confirmModal');
}

// ── UTILS ─────────────────────────────────────────────────────────
const openModal  = id => document.getElementById(id).classList.add('active');
const closeModal = id => document.getElementById(id).classList.remove('active');

function togglePass(inputId, btn) {
    const inp = document.getElementById(inputId);
    const isPass = inp.type === 'password';
    inp.type = isPass ? 'text' : 'password';
    btn.querySelector('i').className = `fas ${isPass ? 'fa-eye-slash' : 'fa-eye'}`;
}

async function apiFetch(actionOrParams) {
    let url;
    if (actionOrParams.startsWith('action=')) {
        url = `${API}?${actionOrParams}`;
    } else if (actionOrParams.includes('=')) {
        url = `${API}?action=${actionOrParams}`;
    } else {
        url = `${API}?action=${actionOrParams}`;
    }
    const r = await fetch(url); return r.json();
}
async function apiPost(body) { const r = await fetch(API, { method:'POST', body }); return r.json(); }
const fd = obj => { const f = new FormData(); Object.entries(obj).forEach(([k,v]) => f.append(k, v ?? '')); return f; };

function toast(msg, type = 'info') {
    const icons = { success:'fa-circle-check', error:'fa-circle-xmark', info:'fa-circle-info', warning:'fa-triangle-exclamation' };
    const el = Object.assign(document.createElement('div'), {
        className: `toast ${type}`,
        innerHTML: `<i class="fas ${icons[type]||icons.info}"></i><span>${msg}</span>`
    });
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .4s'; setTimeout(()=>el.remove(),400); }, 3500);
}
