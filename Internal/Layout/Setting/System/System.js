// System.js — PackageType + EquipmentType
const API = '/PHP/ELite_GYM/Internal/Layout/Setting/System/System_function.php';

let editingId = null, eqEditingId = null;
let currentSection = 'pkg'; // default

document.addEventListener('DOMContentLoaded', () => {
    loadList();
    loadEqList();
    // Close dropdown on outside click
    document.addEventListener('click', e => {
        if (!e.target.closest('#navTrigger')) closeNavDropdown();
    });
});

// ══ NAV DROPDOWN ══════════════════════════
function toggleNavDropdown() {
    const trigger = document.getElementById('navTrigger');
    trigger.classList.toggle('open');
}
function closeNavDropdown() {
    document.getElementById('navTrigger').classList.remove('open');
}
function switchSection(key, e) {
    e && e.stopPropagation();
    currentSection = key;
    closeNavDropdown();

    // Toggle accordion visibility
    document.getElementById('acc-pkg').classList.toggle('section-hidden', key !== 'pkg');
    document.getElementById('acc-eq').classList.toggle('section-hidden', key !== 'eq');

    // Update nav item highlight
    document.getElementById('navPkg').classList.toggle('active', key === 'pkg');
    document.getElementById('navEq').classList.toggle('active', key === 'eq');

    // Update topbar label
    const labels = { pkg: 'Loại gói tập', eq: 'Loại thiết bị' };
    document.getElementById('navLabel').textContent = labels[key];

    // Update page header
    const titleEl = document.querySelector('.page-title span');
    const descEl = document.querySelector('.page-desc');
    const iconEl = document.getElementById('pageTitleIcon');
    if (key === 'pkg') {
        if (titleEl) titleEl.textContent = 'Loại gói tập';
        if (descEl) descEl.textContent = 'Phân loại gói thành viên theo hạng mức';
        if (iconEl) iconEl.className = 'fas fa-layer-group';
    } else {
        if (titleEl) titleEl.textContent = 'Loại thiết bị';
        if (descEl) descEl.textContent = 'Phân loại thiết bị phòng gym';
        if (iconEl) iconEl.className = 'fas fa-tags';
    }
}

function refreshCurrent() {
    if (currentSection === 'pkg') loadList();
    else loadEqList();
}

// ══ TOAST ════════════════════════════════
function showToast(type, msg) {
    const el = document.getElementById('toast');
    el.className = `toast toast--${type} show`;
    el.innerHTML = `<i class="fas fa-${type==='ok'?'check-circle':'times-circle'}"></i> ${msg}`;
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 4000);
}

// ══ UTILS ════════════════════════════════
const escHtml = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const escAttr = s => String(s??'').replace(/'/g,"\\'").replace(/"/g,'&quot;');
function syncColor(val) {
    document.getElementById('colorSwatch').style.background = val;
    document.getElementById('colorHex').textContent = val;
}
function syncEditColor(id, val) {
    document.getElementById(`edit-swatch-${id}`).style.background = val;
    document.getElementById(`edit-hex-${id}`).textContent = val;
}

// ══ PACKAGE TYPE ══════════════════════════
async function loadList() {
    const tbody = document.getElementById('tblBody');
    tbody.innerHTML = `<tr class="loading-row"><td colspan="6"><i class="fas fa-spinner fa-spin"></i></td></tr>`;
    editingId = null;
    try {
        const data = await (await fetch(`${API}?action=list`)).json();
        if (!data.ok || !data.data.length) {
            tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><i class="fas fa-layer-group"></i>Chưa có loại gói nào</div></td></tr>`;
        } else {
            tbody.innerHTML = data.data.map(renderRow).join('');
        }
    } catch(e) {
        tbody.innerHTML = `<tr class="loading-row"><td colspan="6" style="color:var(--red)"><i class="fas fa-exclamation-circle"></i> Lỗi tải</td></tr>`;
    }
}

function renderRow(r) {
    const active = parseInt(r.is_active), color = escHtml(r.color_code||'#6b7280');
    return `<tr id="row-${r.type_id}">
        <td class="td-id">${r.sort_order}</td>
        <td><div class="td-name"><div class="type-dot" style="background:${color}"></div>${escHtml(r.type_name)}</div>
            ${r.description?`<div class="td-muted" style="padding-left:17px;margin-top:1px">${escHtml(r.description)}</div>`:''}
        </td>
        <td class="td-muted">${escHtml(r.description||'—')}</td>
        <td class="td-id">${r.sort_order}</td>
        <td><span class="badge badge--${active?'on':'off'}">${active?'● Bật':'○ Tắt'}</span></td>
        <td><div class="td-actions">
            <button class="btn btn--ghost btn--sm" onclick="startEdit(${r.type_id})" title="Sửa"><i class="fas fa-pen"></i></button>
            <button class="btn btn--ghost btn--sm" onclick="toggleType(${r.type_id})" title="${active?'Tắt':'Bật'}">
                <i class="fas fa-power-off" style="color:${active?'var(--green)':'var(--red)'}"></i>
            </button>
            <button class="btn btn--danger btn--sm" onclick="deleteType(${r.type_id},'${escAttr(r.type_name)}')"><i class="fas fa-trash"></i></button>
        </div></td>
    </tr>`;
}

function renderEditRow(r) {
    const color = escHtml(r.color_code||'#6b7280');
    return `<tr id="row-${r.type_id}" style="background:rgba(212,160,23,.04)">
        <td class="td-id">${r.sort_order}</td>
        <td colspan="2"><div style="display:flex;flex-direction:column;gap:6px">
            <input class="edit-input" id="edit-name-${r.type_id}" value="${escHtml(r.type_name)}" placeholder="Tên loại gói"/>
            <input class="edit-input" id="edit-desc-${r.type_id}" value="${escHtml(r.description||'')}" placeholder="Mô tả"/>
        </div></td>
        <td><input class="edit-input" id="edit-order-${r.type_id}" type="number" value="${r.sort_order}" min="0" style="width:60px"/></td>
        <td><div class="edit-color-wrap" onclick="document.getElementById('edit-cp-${r.type_id}').click()">
            <div class="edit-swatch" id="edit-swatch-${r.type_id}" style="background:${color}"></div>
            <span class="edit-hex" id="edit-hex-${r.type_id}">${color}</span>
            <input type="color" id="edit-cp-${r.type_id}" value="${color}" oninput="syncEditColor(${r.type_id},this.value)"/>
        </div></td>
        <td><div class="td-actions">
            <button class="btn btn--gold btn--sm" onclick="saveEdit(${r.type_id})"><i class="fas fa-check"></i></button>
            <button class="btn btn--ghost btn--sm" onclick="cancelEdit(${r.type_id},${JSON.stringify(r)})"><i class="fas fa-times"></i></button>
        </div></td>
    </tr>`;
}

async function addPackageType() {
    const name=document.getElementById('inpName').value.trim(), desc=document.getElementById('inpDesc').value.trim(), color=document.getElementById('colorPicker').value;
    if (!name) { showToast('err','Vui lòng nhập tên loại gói!'); return; }
    const fd=new FormData(); fd.append('action','add'); fd.append('type_name',name); fd.append('description',desc); fd.append('color_code',color);
    try { const d=await(await fetch(API,{method:'POST',body:fd})).json(); if(d.ok){showToast('ok',d.msg);document.getElementById('inpName').value='';document.getElementById('inpDesc').value='';syncColor('#d4a017');document.getElementById('colorPicker').value='#d4a017';loadList();}else showToast('err',d.msg||'Thêm thất bại!'); } catch(e){showToast('err','Lỗi kết nối!');}
}
async function startEdit(id) {
    if (editingId&&editingId!==id) document.getElementById(`row-${editingId}`)?.remove();
    editingId=id;
    try { const data=await(await fetch(`${API}?action=list`)).json(); const row=data.data.find(r=>parseInt(r.type_id)===id); if(!row)return; document.getElementById(`row-${id}`).outerHTML=renderEditRow(row); } catch(e){showToast('err','Lỗi!');}
}
function cancelEdit(id,original){editingId=null;document.getElementById(`row-${id}`).outerHTML=renderRow(original);}
async function saveEdit(id) {
    const name=document.getElementById(`edit-name-${id}`)?.value.trim(), desc=document.getElementById(`edit-desc-${id}`)?.value.trim(), color=document.getElementById(`edit-cp-${id}`)?.value, order=document.getElementById(`edit-order-${id}`)?.value||0;
    if (!name){showToast('err','Tên không được trống!');return;}
    const fd=new FormData(); fd.append('action','update'); fd.append('type_id',id); fd.append('type_name',name); fd.append('description',desc||''); fd.append('color_code',color||'#6b7280'); fd.append('sort_order',order);
    try { const d=await(await fetch(API,{method:'POST',body:fd})).json(); if(d.ok){showToast('ok',d.msg);editingId=null;loadList();}else showToast('err',d.msg||'Thất bại!'); } catch(e){showToast('err','Lỗi!');}
}
async function toggleType(id){const fd=new FormData();fd.append('action','toggle');fd.append('type_id',id);try{const d=await(await fetch(API,{method:'POST',body:fd})).json();if(d.ok){showToast('ok',d.msg);loadList();}else showToast('err',d.msg);}catch(e){showToast('err','Lỗi!');}}
async function deleteType(id,name){if(!confirm(`Xác nhận xóa loại gói "${name}"?`))return;const fd=new FormData();fd.append('action','delete');fd.append('type_id',id);try{const d=await(await fetch(API,{method:'POST',body:fd})).json();if(d.ok){showToast('ok',d.msg);loadList();}else showToast('err',d.msg);}catch(e){showToast('err','Lỗi!');}}

// ══ EQUIPMENT TYPE ════════════════════════
async function loadEqList() {
    const tbody = document.getElementById('eqTblBody');
    tbody.innerHTML = `<tr class="loading-row"><td colspan="5"><i class="fas fa-spinner fa-spin"></i></td></tr>`;
    eqEditingId = null;
    try {
        const data = await (await fetch(`${API}?action=eq_list`)).json();
        if (!data.ok || !data.data.length) {
            tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state"><i class="fas fa-tags"></i>Chưa có loại thiết bị nào</div></td></tr>`;
        } else {
            tbody.innerHTML = data.data.map(renderEqRow).join('');
        }
    } catch(e) {
        tbody.innerHTML = `<tr class="loading-row"><td colspan="5" style="color:var(--red)"><i class="fas fa-exclamation-circle"></i> Lỗi tải</td></tr>`;
    }
}

function renderEqRow(r) {
    return `<tr id="eq-row-${r.type_id}">
        <td class="td-id">${r.type_id}</td>
        <td><div class="td-name">${escHtml(r.type_name)}</div></td>
        <td class="td-muted">${escHtml(r.description||'—')}</td>
        <td><span class="interval-badge"><i class="fas fa-clock" style="font-size:.65rem"></i>${r.maintenance_interval} ngày</span></td>
        <td><div class="td-actions">
            <button class="btn btn--ghost btn--sm" onclick="startEqEdit(${r.type_id})" title="Sửa"><i class="fas fa-pen"></i></button>
            <button class="btn btn--danger btn--sm" onclick="deleteEqType(${r.type_id},'${escAttr(r.type_name)}')"><i class="fas fa-trash"></i></button>
        </div></td>
    </tr>`;
}
function renderEqEditRow(r) {
    return `<tr id="eq-row-${r.type_id}" style="background:rgba(96,165,250,.04)">
        <td class="td-id">${r.type_id}</td>
        <td><input class="edit-input eq-edit-input" id="eq-edit-name-${r.type_id}" value="${escHtml(r.type_name)}" placeholder="Tên loại thiết bị"/></td>
        <td><input class="edit-input eq-edit-input" id="eq-edit-desc-${r.type_id}" value="${escHtml(r.description||'')}" placeholder="Mô tả"/></td>
        <td><div style="display:flex;align-items:center;gap:6px"><input class="edit-input eq-edit-input" id="eq-edit-int-${r.type_id}" type="number" value="${r.maintenance_interval}" min="1" style="width:72px"/> <span style="font-size:.78rem;color:var(--text-3)">ngày</span></div></td>
        <td><div class="td-actions">
            <button class="btn btn--gold btn--sm" onclick="saveEqEdit(${r.type_id})"><i class="fas fa-check"></i></button>
            <button class="btn btn--ghost btn--sm" onclick="cancelEqEdit(${r.type_id},${JSON.stringify(r)})"><i class="fas fa-times"></i></button>
        </div></td>
    </tr>`;
}

async function addEquipmentType() {
    const name=document.getElementById('eqInpName').value.trim(), desc=document.getElementById('eqInpDesc').value.trim(), interval=document.getElementById('eqInpInterval').value;
    if (!name){showToast('err','Vui lòng nhập tên loại thiết bị!');return;}
    const fd=new FormData();fd.append('action','eq_add');fd.append('type_name',name);fd.append('description',desc);fd.append('maintenance_interval',interval||180);
    try{const d=await(await fetch(API,{method:'POST',body:fd})).json();if(d.ok){showToast('ok',d.msg);document.getElementById('eqInpName').value='';document.getElementById('eqInpDesc').value='';document.getElementById('eqInpInterval').value='180';loadEqList();}else showToast('err',d.msg||'Thêm thất bại!');}catch(e){showToast('err','Lỗi kết nối!');}
}
async function startEqEdit(id){if(eqEditingId&&eqEditingId!==id)document.getElementById(`eq-row-${eqEditingId}`)?.remove();eqEditingId=id;try{const data=await(await fetch(`${API}?action=eq_list`)).json();const row=data.data.find(r=>parseInt(r.type_id)===id);if(!row)return;document.getElementById(`eq-row-${id}`).outerHTML=renderEqEditRow(row);}catch(e){showToast('err','Lỗi!');}}
function cancelEqEdit(id,original){eqEditingId=null;document.getElementById(`eq-row-${id}`).outerHTML=renderEqRow(original);}
async function saveEqEdit(id){const name=document.getElementById(`eq-edit-name-${id}`)?.value.trim(),desc=document.getElementById(`eq-edit-desc-${id}`)?.value.trim(),interval=document.getElementById(`eq-edit-int-${id}`)?.value;if(!name){showToast('err','Tên không được trống!');return;}const fd=new FormData();fd.append('action','eq_update');fd.append('type_id',id);fd.append('type_name',name);fd.append('description',desc||'');fd.append('maintenance_interval',interval||180);try{const d=await(await fetch(API,{method:'POST',body:fd})).json();if(d.ok){showToast('ok',d.msg);eqEditingId=null;loadEqList();}else showToast('err',d.msg||'Thất bại!');}catch(e){showToast('err','Lỗi!');}}
async function deleteEqType(id,name){if(!confirm(`Xác nhận xóa loại thiết bị "${name}"?`))return;const fd=new FormData();fd.append('action','eq_delete');fd.append('type_id',id);try{const d=await(await fetch(API,{method:'POST',body:fd})).json();if(d.ok){showToast('ok',d.msg);loadEqList();}else showToast('err',d.msg);}catch(e){showToast('err','Lỗi!');}}
