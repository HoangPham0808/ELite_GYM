/* ══════════════════════════════════════════════════
   Review_Management.js — Elite Gym Admin
   ══════════════════════════════════════════════════ */
(function () {
  'use strict';

  const HANDLER = 'Review_Management.php';

  /* ── DOM refs ─────────────────────────────────── */
  const tbody      = document.getElementById('rmTbody');
  const pager      = document.getElementById('rmPager');
  const searchInp  = document.getElementById('rmSearch');
  const ratingSel  = document.getElementById('rmRating');
  const repliedSel = document.getElementById('rmReplied');
  const refreshBtn = document.getElementById('rmRefresh');

  // Stats
  const sTotal   = document.getElementById('sTotal');
  const sAvg     = document.getElementById('sAvg');
  const sReplied = document.getElementById('sReplied');
  const sPending = document.getElementById('sPending');
  const sDist    = document.getElementById('sDist');

  // Reply modal
  const modalBd    = document.getElementById('rmModalBd');
  const modalClose = document.getElementById('rmModalClose');
  const modalCancel= document.getElementById('rmModalCancel');
  const modalSave  = document.getElementById('rmModalSave');
  const modalReview= document.getElementById('rmModalReview');
  const replyTa    = document.getElementById('rmReplyTa');
  const replyChars = document.getElementById('rmReplyChars');
  const modalMsg   = document.getElementById('rmModalMsg');

  // Confirm modal
  const confirmBd     = document.getElementById('rmConfirmBd');
  const confirmMsg    = document.getElementById('rmConfirmMsg');
  const confirmCancel = document.getElementById('rmConfirmCancel');
  const confirmOk     = document.getElementById('rmConfirmOk');

  const toast = document.getElementById('rmToast');

  let curPage       = 1;
  let searchTimer   = null;
  let activeReviewId= null;
  let confirmCb     = null;

  /* ── Fetch wrapper ────────────────────────────── */
  async function apiFetch(url, opts = {}) {
    const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, ...opts });
    const json = await res.json();
    return json;
  }

  /* ── Toast ────────────────────────────────────── */
  let toastTimer;
  function showToast(msg, type = 'ok') {
    toast.textContent = (type === 'ok' ? '✓ ' : '✗ ') + msg;
    toast.className = `rm-toast show ${type}`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
  }

  /* ── Helpers ──────────────────────────────────── */
  function esc(str) {
    return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  function starsHtml(n) {
    let h = '';
    for (let i = 1; i <= 5; i++) {
      h += `<i class="fa${i <= n ? 's' : 'r'} fa-star" style="color:${i <= n ? '#f59e0b' : 'rgba(255,255,255,.12)'}"></i>`;
    }
    return h;
  }

  function initials(name) {
    const parts = (name || '?').trim().split(/\s+/);
    return parts.length >= 2
      ? (parts[0][0] + parts[parts.length-1][0]).toUpperCase()
      : parts[0].substring(0,2).toUpperCase();
  }

  function fmtDate(str) {
    if (!str) return '—';
    const d = new Date(str);
    return d.toLocaleDateString('vi-VN', { day:'2-digit', month:'2-digit', year:'numeric' });
  }

  /* ── Load reviews ─────────────────────────────── */
  async function loadReviews(page = 1) {
    curPage = page;
    tbody.innerHTML = `<tr><td colspan="7" class="rm-loading"><i class="fas fa-spinner fa-spin"></i> Đang tải…</td></tr>`;
    pager.innerHTML = '';

    const params = new URLSearchParams({
      ajax: 1,
      action: 'get_reviews',
      page,
      rating:  ratingSel.value,
      replied: repliedSel.value,
      search:  searchInp.value.trim(),
    });

    try {
      const json = await apiFetch(`${HANDLER}?${params}`);
      if (!json.ok) { tbody.innerHTML = emptyRow('Lỗi tải dữ liệu: ' + json.msg); return; }

      const d = json.data;
      updateStats(d.stats);
      renderRows(d.reviews);
      renderPager(d.pages, d.page);
    } catch (e) {
      tbody.innerHTML = emptyRow('Lỗi kết nối server.');
    }
  }

  /* ── Render stats ─────────────────────────────── */
  function updateStats(s) {
    sTotal.textContent   = s.total;
    sAvg.textContent     = s.avg + ' ★';
    sReplied.textContent = s.replied;
    sPending.textContent = s.pending;

    const map = {};
    s.dist.forEach(d => map[d.rating] = +d.cnt);
    let h = '';
    for (let i = 5; i >= 1; i--) {
      const cnt = map[i] || 0;
      const pct = s.total > 0 ? Math.round(cnt / s.total * 100) : 0;
      h += `<div class="rm-dist-row">
        <span class="rm-dist-lbl">${i}</span>
        <div class="rm-dist-track"><div class="rm-dist-fill" style="width:${pct}%"></div></div>
        <span class="rm-dist-pct">${pct}%</span>
      </div>`;
    }
    sDist.innerHTML = h;
  }

  /* ── Render rows ──────────────────────────────── */
  function renderRows(reviews) {
    if (!reviews.length) {
      tbody.innerHTML = emptyRow('Không có đánh giá nào phù hợp.');
      return;
    }

    tbody.innerHTML = reviews.map((rv, idx) => {
      const hasReply = rv.staff_reply && rv.staff_reply.trim();
      const replyCell = hasReply
        ? `<div class="rm-reply-badge"><i class="fas fa-circle-check"></i> Đã phản hồi</div>
           <div class="rm-reply-text">${esc(rv.staff_reply)}</div>
           <div class="rm-reply-who">— ${esc(rv.staff_reply_by || 'Nhân viên')} · ${fmtDate(rv.staff_replied_at)}</div>`
        : `<span class="rm-pill rm-pill-pending"><i class="fas fa-clock"></i> Chờ phản hồi</span>`;

      return `<tr>
        <td style="color:var(--text3);font-size:12px">${(curPage-1)*10 + idx+1}</td>
        <td>
          <div class="rm-cust">
            <div class="rm-av">${initials(rv.full_name)}</div>
            <div>
              <div class="rm-cust-name">${esc(rv.full_name)}</div>
              <div class="rm-cust-email">${esc(rv.email || '')}</div>
            </div>
          </div>
        </td>
        <td>
          <span class="rm-rating-num">${rv.rating}</span>
          <div class="rm-stars">${starsHtml(+rv.rating)}</div>
        </td>
        <td><div class="rm-content">${esc(rv.content)}</div></td>
        <td>${replyCell}</td>
        <td class="rm-date">${fmtDate(rv.review_date)}</td>
        <td>
          <div class="rm-actions">
            <button class="rm-btn-icon" title="${hasReply ? 'Sửa phản hồi' : 'Phản hồi'}"
              data-action="reply" data-rid="${rv.review_id}"
              data-content="${esc(rv.content)}" data-name="${esc(rv.full_name)}"
              data-rating="${rv.rating}"
              data-reply="${esc(rv.staff_reply || '')}">
              <i class="fas fa-reply"></i>
            </button>
            ${hasReply ? `<button class="rm-btn-icon" title="Xóa phản hồi"
              data-action="del-reply" data-rid="${rv.review_id}">
              <i class="fas fa-eraser"></i>
            </button>` : ''}
            <button class="rm-btn-icon rm-btn-del" title="Xóa đánh giá"
              data-action="del-review" data-rid="${rv.review_id}" data-name="${esc(rv.full_name)}">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  /* ── Pager ────────────────────────────────────── */
  function renderPager(pages, cur) {
    if (pages <= 1) return;
    let h = '';
    if (cur > 1) h += `<button class="rm-pg-btn" data-page="${cur-1}"><i class="fas fa-chevron-left"></i></button>`;
    for (let p = 1; p <= pages; p++) {
      h += `<button class="rm-pg-btn${p===cur?' active':''}" data-page="${p}">${p}</button>`;
    }
    if (cur < pages) h += `<button class="rm-pg-btn" data-page="${cur+1}"><i class="fas fa-chevron-right"></i></button>`;
    pager.innerHTML = h;
    pager.querySelectorAll('.rm-pg-btn[data-page]').forEach(btn =>
      btn.addEventListener('click', () => loadReviews(+btn.dataset.page))
    );
  }

  function emptyRow(msg) {
    return `<tr><td colspan="7" class="rm-empty"><i class="fas fa-comment-slash"></i>${msg}</td></tr>`;
  }

  /* ── Table click delegation ───────────────────── */
  tbody.addEventListener('click', e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    const rid    = +btn.dataset.rid;

    if (action === 'reply') {
      openReplyModal(rid, btn.dataset);
    } else if (action === 'del-reply') {
      openConfirm(
        'Bạn có chắc muốn <strong>xóa phản hồi</strong> này không?',
        () => doDeleteReply(rid)
      );
    } else if (action === 'del-review') {
      openConfirm(
        `Bạn có chắc muốn <strong>xóa toàn bộ đánh giá</strong> của <em>${btn.dataset.name}</em>? Thao tác này không thể hoàn tác.`,
        () => doDeleteReview(rid)
      );
    }
  });

  /* ── Reply modal ──────────────────────────────── */
  function openReplyModal(rid, data) {
    activeReviewId = rid;
    modalReview.innerHTML = `
      <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
        <div style="color:var(--amber);font-size:12px">${starsHtml(+data.rating)}</div>
        <strong style="font-size:13px;color:var(--text)">${esc(data.name)}</strong>
      </div>
      <div style="font-size:13px;color:var(--text2);line-height:1.6">"${esc(data.content)}"</div>`;
    replyTa.value = data.reply || '';
    replyChars.textContent = 300 - replyTa.value.length;
    modalMsg.textContent = '';
    modalMsg.className = 'rm-modal-msg';
    modalBd.classList.add('open');
    setTimeout(() => replyTa.focus(), 50);
  }

  replyTa.addEventListener('input', () => {
    replyChars.textContent = 300 - replyTa.value.length;
  });

  [modalClose, modalCancel].forEach(el => el.addEventListener('click', () => modalBd.classList.remove('open')));
  modalBd.addEventListener('click', e => { if (e.target === modalBd) modalBd.classList.remove('open'); });

  modalSave.addEventListener('click', async () => {
    const reply = replyTa.value.trim();
    if (reply.length < 5) {
      modalMsg.textContent = '✗ Phản hồi tối thiểu 5 ký tự.';
      modalMsg.className = 'rm-modal-msg err';
      return;
    }
    modalSave.disabled = true;
    modalSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu…';

    const fd = new FormData();
    fd.append('action', 'reply');
    fd.append('review_id', activeReviewId);
    fd.append('reply', reply);

    try {
      const json = await apiFetch(HANDLER + '?ajax=1', { method: 'POST', body: fd });
      if (json.ok) {
        showToast('Đã lưu phản hồi.', 'ok');
        modalBd.classList.remove('open');
        loadReviews(curPage);
      } else {
        modalMsg.textContent = '✗ ' + json.msg;
        modalMsg.className = 'rm-modal-msg err';
      }
    } catch {
      modalMsg.textContent = '✗ Lỗi kết nối.';
      modalMsg.className = 'rm-modal-msg err';
    }
    modalSave.disabled = false;
    modalSave.innerHTML = '<i class="fas fa-paper-plane"></i> Lưu phản hồi';
  });

  /* ── Confirm modal ────────────────────────────── */
  function openConfirm(msg, cb) {
    confirmMsg.innerHTML = msg;
    confirmCb = cb;
    confirmBd.classList.add('open');
  }
  confirmCancel.addEventListener('click', () => confirmBd.classList.remove('open'));
  confirmBd.addEventListener('click', e => { if (e.target === confirmBd) confirmBd.classList.remove('open'); });
  confirmOk.addEventListener('click', async () => {
    if (confirmCb) { confirmBd.classList.remove('open'); await confirmCb(); }
  });

  /* ── Delete reply ─────────────────────────────── */
  async function doDeleteReply(rid) {
    const fd = new FormData();
    fd.append('action', 'delete_reply');
    fd.append('review_id', rid);
    try {
      const json = await apiFetch(HANDLER + '?ajax=1', { method: 'POST', body: fd });
      showToast(json.ok ? 'Đã xóa phản hồi.' : json.msg, json.ok ? 'ok' : 'err');
      if (json.ok) loadReviews(curPage);
    } catch { showToast('Lỗi kết nối.', 'err'); }
  }

  /* ── Delete review ────────────────────────────── */
  async function doDeleteReview(rid) {
    const fd = new FormData();
    fd.append('action', 'delete_review');
    fd.append('review_id', rid);
    try {
      const json = await apiFetch(HANDLER + '?ajax=1', { method: 'POST', body: fd });
      showToast(json.ok ? 'Đã xóa đánh giá.' : json.msg, json.ok ? 'ok' : 'err');
      if (json.ok) loadReviews(curPage);
    } catch { showToast('Lỗi kết nối.', 'err'); }
  }

  /* ── Filters / search ─────────────────────────── */
  searchInp.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadReviews(1), 400);
  });
  ratingSel.addEventListener('change',  () => loadReviews(1));
  repliedSel.addEventListener('change', () => loadReviews(1));
  refreshBtn.addEventListener('click',  () => loadReviews(curPage));

  /* ── Boot ─────────────────────────────────────── */
  loadReviews(1);

})();
