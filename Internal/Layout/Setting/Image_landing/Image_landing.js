/* ════════════════════════════════════════════════════════════════
   Image_landing.js  —  Trang quản lý ảnh Slideshow
   Vị trí: Internal\Layout\Setting\Image_landing\Image_landing.js
   ════════════════════════════════════════════════════════════════ */

const API = 'Image_landing_function.php';

/* ══ TOAST ═══════════════════════════════════════════════════════ */
function showToast(msg, type = 'info') {
  const t = document.getElementById('toast');
  t.className = `toast ${type}`;
  t.innerHTML = `<i class="fas fa-${type==='success'?'check':type==='error'?'xmark':'info-circle'}"></i> ${msg}`;
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 3000);
}

/* ══ STATS ═══════════════════════════════════════════════════════ */
function updateStats() {
  const cards  = document.querySelectorAll('.img-card');
  const active = [...cards].filter(c => c.dataset.active === '1').length;
  const el_total  = document.getElementById('statTotal');
  const el_active = document.getElementById('statActive');
  const el_hidden = document.getElementById('statHidden');
  if (el_total)  el_total.textContent  = cards.length;
  if (el_active) el_active.textContent = active;
  if (el_hidden) el_hidden.textContent = cards.length - active;
}

/* ══ UPLOAD ══════════════════════════════════════════════════════ */
const fileInput  = document.getElementById('fileInput');
const dropZone   = document.getElementById('dropZone');
const dzPreview  = document.getElementById('dzPreview');
const uploadBtn  = document.getElementById('uploadBtn');
const uploadCount = document.getElementById('uploadCount');
const uploadForm = document.getElementById('uploadForm');

function renderPreviews(files) {
  dzPreview.innerHTML = '';
  if (!files.length) { uploadBtn.disabled = true; uploadCount.textContent = ''; return; }
  uploadBtn.disabled = false;
  uploadCount.textContent = `${files.length} ảnh đã chọn`;
  Array.from(files).slice(0, 10).forEach(f => {
    if (!f.type.startsWith('image/')) return;
    const url = URL.createObjectURL(f);
    const img = Object.assign(document.createElement('img'), {
      src: url, className: 'dz-thumb'
    });
    img.onload = () => URL.revokeObjectURL(url);
    dzPreview.appendChild(img);
  });
  if (files.length > 10) {
    const more = document.createElement('span');
    more.style.cssText = 'color:var(--text-3);font-size:.78rem;align-self:center';
    more.textContent = `+${files.length - 10} ảnh khác`;
    dzPreview.appendChild(more);
  }
}

fileInput?.addEventListener('change', () => renderPreviews(fileInput.files));

dropZone?.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone?.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone?.addEventListener('drop', e => {
  e.preventDefault(); dropZone.classList.remove('drag-over');
  const dt = new DataTransfer();
  [...e.dataTransfer.files].forEach(f => dt.items.add(f));
  fileInput.files = dt.files;
  renderPreviews(fileInput.files);
});

uploadForm?.addEventListener('submit', async e => {
  e.preventDefault();
  if (!fileInput.files.length) return;
  uploadBtn.disabled = true;
  uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang upload...';

  const fd = new FormData();
  fd.append('action', 'upload');
  [...fileInput.files].forEach(f => fd.append('images[]', f));

  try {
    const res  = await fetch(API, { method:'POST', body:fd });
    const data = await res.json();
    if (data.ok) {
      showToast(data.msg, 'success');
      data.uploaded.forEach(img => prependCard(img));
      fileInput.value = '';
      dzPreview.innerHTML = '';
      uploadCount.textContent = '';
      updateStats();
    } else {
      showToast(data.msg || 'Upload thất bại.', 'error');
    }
    if (data.errors?.length) {
      data.errors.forEach(err => showToast(err, 'error'));
    }
  } catch {
    showToast('Lỗi kết nối server.', 'error');
  }

  uploadBtn.disabled = false;
  uploadBtn.innerHTML = '<i class="fas fa-upload"></i> UPLOAD ẢNH';
});

/* ══ BUILD CARD ══════════════════════════════════════════════════ */
function buildCard(img) {
  const card = document.createElement('div');
  card.className  = 'img-card' + (img.is_active == 0 ? ' inactive' : '');
  card.dataset.id     = img.image_id;
  card.dataset.active = img.is_active;
  card.dataset.name   = img.image_name;
  card.draggable      = true;

  const isOn        = img.is_active == 1;
  const isProtected = img.image_name === 'Logo_ELITY';

  card.innerHTML = `
    <div class="img-thumb-wrap">
      <img src="${escHtml(img.file_url)}" alt="${escHtml(img.image_name)}" loading="lazy"/>
      <span class="badge-order">${img.sort_order}</span>
      <span class="badge-status ${isOn?'on':'off'}">${isOn?'● HIỆN':'○ ẨN'}</span>
      <span class="drag-handle"><i class="fas fa-grip-dots-vertical"></i></span>
    </div>
    <div class="img-info">
      <div class="img-name-row">
        <span class="img-name" title="${escHtml(img.image_name)}">${escHtml(img.image_name)}</span>
        ${!isProtected
          ? `<button class="btn-rename" title="Đổi tên"><i class="fas fa-pen"></i></button>`
          : `<span class="badge-protected" title="Ảnh hệ thống"><i class="fas fa-lock"></i> Ảnh hệ thống</span>`
        }
      </div>
      <div class="img-meta">
        <span>${img.file_ext || '?'}</span>
        <span>${img.file_size_fmt || (img.file_size > 0 ? Math.round(img.file_size/1024)+' KB' : '?')}</span>
      </div>
    </div>
    <div class="img-url-row" title="Nhấp để sao chép URL">
      <i class="fas fa-link"></i>
      <span>${escHtml(img.file_url)}</span>
    </div>
    <div class="img-actions">
      <button class="btn-replace" title="Thay bằng ảnh mới (xóa ảnh cũ)">
        <i class="fas fa-image"></i> Thay ảnh
      </button>
      ${!isProtected ? `
      <button class="btn-toggle ${isOn?'on':'off'}">
        ${isOn?'<i class="fas fa-eye-slash"></i> Ẩn':'<i class="fas fa-eye"></i> Hiện'}
      </button>
      <button class="btn-del" title="Xóa ảnh"><i class="fas fa-trash-alt"></i></button>
      ` : ''}
    </div>`;

  return card;
}

function prependCard(img) {
  const grid = document.getElementById('imgGrid');
  const empty = grid.querySelector('.empty-state');
  if (empty) empty.remove();
  grid.prepend(buildCard(img));
  initAllCards();  // bind event cho card mới (bao gồm cả drag)
  rebindDrag();
  updateOrderBadges();
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ══ TOGGLE ══════════════════════════════════════════════════════ */
async function toggleImage(card) {
  const id = card.dataset.id;
  const fd = new FormData();
  fd.append('action', 'toggle');
  fd.append('image_id', id);

  const res  = await fetch(API, { method:'POST', body:fd });
  const data = await res.json();
  if (!data.ok) { showToast('Lỗi.', 'error'); return; }

  const isOn = data.is_active === 1;
  card.dataset.active = data.is_active;
  card.classList.toggle('inactive', !isOn);

  const badge  = card.querySelector('.badge-status');
  const btnTog = card.querySelector('.btn-toggle');

  badge.className  = `badge-status ${isOn?'on':'off'}`;
  badge.textContent = isOn ? '● HIỆN' : '○ ẨN';
  btnTog.className  = `btn-toggle ${isOn?'on':'off'}`;
  btnTog.innerHTML  = isOn
    ? '<i class="fas fa-eye-slash"></i> Ẩn'
    : '<i class="fas fa-eye"></i> Hiện';

  showToast(isOn ? 'Đã bật hiển thị ảnh.' : 'Đã ẩn ảnh.', 'success');
  updateStats();
}

/* ══ DELETE ══════════════════════════════════════════════════════ */
async function deleteImage(card) {
  const name = card.querySelector('.img-name').textContent;
  if (!confirm(`Xóa ảnh "${name}"?\nHành động này không thể hoàn tác.`)) return;

  const fd = new FormData();
  fd.append('action', 'delete');
  fd.append('image_id', card.dataset.id);

  const res  = await fetch(API, { method:'POST', body:fd });
  const data = await res.json();
  if (data.ok) {
    card.style.transition = 'opacity .3s, transform .3s';
    card.style.opacity    = '0';
    card.style.transform  = 'scale(.92)';
    setTimeout(() => {
      card.remove();
      updateStats();
      updateOrderBadges();
      if (!document.querySelectorAll('.img-card').length) showEmptyState();
    }, 300);
    showToast('Đã xóa ảnh.', 'success');
  } else {
    showToast(data.msg || 'Xóa thất bại.', 'error');
  }
}

function showEmptyState() {
  const grid = document.getElementById('imgGrid');
  grid.innerHTML = `
    <div class="empty-state">
      <i class="fas fa-image"></i>
      <p>Chưa có ảnh nào.<br>Hãy upload ảnh đầu tiên!</p>
    </div>`;
}

/* ══ RENAME MODAL ════════════════════════════════════════════════ */
let renameTarget = null;

function openRename(card) {
  renameTarget = card;
  const currentName = card.querySelector('.img-name').textContent;
  document.getElementById('renameInput').value = currentName;
  document.getElementById('renameModal').classList.add('open');
  setTimeout(() => document.getElementById('renameInput').focus(), 100);
}

document.getElementById('renameCancel')?.addEventListener('click', () => {
  document.getElementById('renameModal').classList.remove('open');
  renameTarget = null;
});

document.getElementById('renameConfirm')?.addEventListener('click', async () => {
  const newName = document.getElementById('renameInput').value.trim();
  if (!newName || !renameTarget) return;

  const fd = new FormData();
  fd.append('action', 'rename');
  fd.append('image_id', renameTarget.dataset.id);
  fd.append('image_name', newName);

  const res  = await fetch(API, { method:'POST', body:fd });
  const data = await res.json();
  if (data.ok) {
    renameTarget.querySelector('.img-name').textContent = data.image_name;
    renameTarget.querySelector('.img-thumb-wrap img').alt = data.image_name;
    showToast('Đã đổi tên ảnh.', 'success');
  } else {
    showToast('Đổi tên thất bại.', 'error');
  }
  document.getElementById('renameModal').classList.remove('open');
  renameTarget = null;
});

// Đóng modal khi nhấn overlay
document.getElementById('renameModal')?.addEventListener('click', e => {
  if (e.target === document.getElementById('renameModal')) {
    document.getElementById('renameModal').classList.remove('open');
    renameTarget = null;
  }
});

// Enter để confirm
document.getElementById('renameInput')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') document.getElementById('renameConfirm').click();
  if (e.key === 'Escape') document.getElementById('renameCancel').click();
});

/* ══ REPLACE IMAGE ═══════════════════════════════════════════════ */
let replaceTarget = null;
const replaceFileInput = document.getElementById('replaceFileInput');

replaceFileInput?.addEventListener('change', async () => {
  const file = replaceFileInput.files[0];
  if (!file || !replaceTarget) return;

  const card = replaceTarget;
  const btn  = card.querySelector('.btn-replace');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang thay...'; }

  const fd = new FormData();
  fd.append('action', 'replace');
  fd.append('image_id', card.dataset.id);
  fd.append('image', file);

  try {
    const res  = await fetch(API, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      // Cập nhật ảnh trên card ngay lập tức
      const imgEl = card.querySelector('.img-thumb-wrap img');
      if (imgEl) imgEl.src = data.file_url + '?t=' + Date.now(); // cache-bust

      // Cập nhật URL row
      const urlSpan = card.querySelector('.img-url-row span');
      if (urlSpan) urlSpan.textContent = data.file_url;

      // Cập nhật meta
      const metas = card.querySelectorAll('.img-meta span');
      if (metas[0]) metas[0].textContent = data.file_ext || '';
      if (metas[1]) metas[1].textContent = data.file_size_fmt || '';

      showToast(data.msg, 'success');
    } else {
      showToast(data.msg || 'Thay ảnh thất bại.', 'error');
    }
  } catch {
    showToast('Lỗi kết nối server.', 'error');
  }

  if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-image"></i> Thay ảnh'; }
  replaceFileInput.value = '';
  replaceTarget = null;
});

function openReplace(card) {
  replaceTarget = card;
  replaceFileInput.click();
}


let dragSrc = null;

function rebindDrag() {
  document.querySelectorAll('.img-card').forEach(card => {
    card.removeEventListener('dragstart', onDragStart);
    card.removeEventListener('dragend',   onDragEnd);
    card.removeEventListener('dragover',  onDragOver);
    card.removeEventListener('drop',      onDrop);
    card.addEventListener('dragstart', onDragStart);
    card.addEventListener('dragend',   onDragEnd);
    card.addEventListener('dragover',  onDragOver);
    card.addEventListener('drop',      onDrop);
  });
}

function onDragStart(e) {
  dragSrc = this; this.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}
function onDragEnd() {
  this.classList.remove('dragging');
  document.querySelectorAll('.img-card').forEach(c => c.classList.remove('drag-target'));
  updateOrderBadges();
}
function onDragOver(e) {
  e.preventDefault(); e.dataTransfer.dropEffect = 'move';
  if (this !== dragSrc) {
    document.querySelectorAll('.img-card').forEach(c => c.classList.remove('drag-target'));
    this.classList.add('drag-target');
  }
}
function onDrop(e) {
  e.preventDefault();
  if (dragSrc && dragSrc !== this) {
    const grid = document.getElementById('imgGrid');
    const srcI = [...grid.children].indexOf(dragSrc);
    const tgtI = [...grid.children].indexOf(this);
    grid.insertBefore(dragSrc, srcI < tgtI ? this.nextSibling : this);
  }
  document.getElementById('orderStatus').textContent = '⚠️ Thứ tự thay đổi — nhấn "Lưu thứ tự"';
  document.getElementById('orderStatus').style.color = 'var(--yellow)';
}

function updateOrderBadges() {
  document.querySelectorAll('.img-card').forEach((c, i) => {
    const b = c.querySelector('.badge-order');
    if (b) b.textContent = i + 1;
  });
}

/* ══ SAVE ORDER ═════════════════════════════════════════════════ */
document.getElementById('saveOrderBtn')?.addEventListener('click', async () => {
  const ids = [...document.querySelectorAll('.img-card')].map(c => c.dataset.id);
  const fd  = new FormData();
  fd.append('action', 'reorder');
  fd.append('ids', JSON.stringify(ids));

  const res  = await fetch(API, { method:'POST', body:fd });
  const data = await res.json();
  if (data.ok) {
    const s = document.getElementById('orderStatus');
    s.textContent = '✅ Đã lưu thứ tự!';
    s.style.color = 'var(--green)';
    showToast('Đã lưu thứ tự slideshow.', 'success');
  } else {
    showToast('Lỗi lưu thứ tự.', 'error');
  }
});

/* ══ INIT ════════════════════════════════════════════════════════ */

// Bind event cho TẤT CẢ card — cả card PHP render lẫn card JS thêm mới
function initAllCards() {
  document.querySelectorAll('.img-card').forEach(card => {
    if (card.dataset.bound === '1') return;
    card.dataset.bound = '1';

    const btnToggle  = card.querySelector('.btn-toggle');
    const btnDel     = card.querySelector('.btn-del');
    const btnRename  = card.querySelector('.btn-rename');
    const btnReplace = card.querySelector('.btn-replace');
    const urlRow     = card.querySelector('.img-url-row');

    if (btnToggle)  btnToggle.addEventListener('click',  () => toggleImage(card));
    if (btnDel)     btnDel.addEventListener('click',     () => deleteImage(card));
    if (btnRename)  btnRename.addEventListener('click',  () => openRename(card));
    if (btnReplace) btnReplace.addEventListener('click', () => openReplace(card));
    if (urlRow)     urlRow.addEventListener('click', () => {
      const url = urlRow.querySelector('span')?.textContent?.trim();
      if (url) navigator.clipboard.writeText(url).then(() => showToast('Đã sao chép URL!', 'info'));
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initAllCards();   // bind cho card PHP đã render sẵn
  rebindDrag();
  updateStats();
});
