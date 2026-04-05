<?php
/* notification_ui.php — Elite Gym
 * Chỉ xuất <script> JS — HTML bell đã có sẵn trong index.php navbar.
 * Include 1 lần duy nhất trước </body>
 */
if (!isset($is_customer) || !$is_customer) return;
?>
<script>
(function () {
  'use strict';

  const HANDLER   = 'Review/notification_handler.php';
  const POLL_MS   = 30000;   /* polling mỗi 30 giây */
  const TOAST_TTL = 5000;    /* toast tự đóng sau 5s */

  /* ── DOM ──────────────────────────────────────────────── */
  const bellBtn   = document.getElementById('notifBellBtn');
  const bellWrap  = document.getElementById('notifBellWrap');
  const panel     = document.getElementById('notifPanel');
  const badge     = document.getElementById('notifBadge');
  const list      = document.getElementById('npList');
  const markAllBtn= document.getElementById('npMarkAll');
  const toastStack= document.getElementById('toastStack');

  if (!bellBtn) return; /* Không phải Customer → dừng */

  /* ── State ────────────────────────────────────────────── */
  let panelOpen    = false;
  let lastUnread   = 0;
  let knownIds     = new Set();   /* IDs đã thấy, tránh toast trùng */
  let firstLoad    = true;

  /* ══════════════════════════════════════════════════════
     BADGE
  ══════════════════════════════════════════════════════ */
  function setBadge(n) {
    badge.textContent = n > 99 ? '99+' : (n || '');
    badge.classList.toggle('show', n > 0);
  }

  function ringBell() {
    bellBtn.classList.remove('ringing');
    void bellBtn.offsetWidth; /* reflow để restart animation */
    bellBtn.classList.add('ringing');
    setTimeout(() => bellBtn.classList.remove('ringing'), 600);
  }

  /* ══════════════════════════════════════════════════════
     TOAST
  ══════════════════════════════════════════════════════ */
  function showToast(title, msg, time) {
    const t = document.createElement('div');
    t.className = 'toast-notif';
    t.innerHTML = `
      <div class="toast-icon"><i class="fas fa-reply"></i></div>
      <div class="toast-body">
        <div class="toast-title">${escHtml(title)}</div>
        <div class="toast-msg">${escHtml(msg)}</div>
        <div class="toast-time">${time || 'Vừa xong'}</div>
      </div>
      <button class="toast-close" title="Đóng"><i class="fas fa-times"></i></button>
      <div class="toast-progress"></div>`;

    toastStack.appendChild(t);

    /* Đóng manual */
    t.querySelector('.toast-close').addEventListener('click', () => dismissToast(t));

    /* Đóng tự động */
    setTimeout(() => dismissToast(t), TOAST_TTL);
  }

  function dismissToast(el) {
    if (!el || el.classList.contains('out')) return;
    el.classList.add('out');
    setTimeout(() => el.remove(), 300);
  }

  /* ══════════════════════════════════════════════════════
     LOAD NOTIFICATIONS vào panel
  ══════════════════════════════════════════════════════ */
  async function loadPanel() {
    list.innerHTML = skeletons(3);
    try {
      const res  = await fetch(`${HANDLER}?action=get_notifications&limit=10`);
      const json = await res.json();
      if (!json.ok) { list.innerHTML = emptyState('Không thể tải thông báo.'); return; }

      const notifs = json.data.notifications;
      const unread = json.data.unread;

      setBadge(unread);

      if (!notifs.length) {
        list.innerHTML = emptyState('Chưa có thông báo nào.');
        return;
      }

      list.innerHTML = notifs.map(n => itemHtml(n)).join('');

      /* Click vào item → đánh dấu đọc */
      list.querySelectorAll('.np-item').forEach(el => {
        el.addEventListener('click', () => markRead(+el.dataset.id, el));
      });

    } catch {
      list.innerHTML = emptyState('Lỗi kết nối.');
    }
  }

  /* ══════════════════════════════════════════════════════
     POLLING — phát hiện thông báo mới → toast
  ══════════════════════════════════════════════════════ */
  async function poll() {
    try {
      const res  = await fetch(`${HANDLER}?action=get_notifications&limit=10`);
      const json = await res.json();
      if (!json.ok) return;

      const notifs = json.data.notifications;
      const unread = json.data.unread;

      /* Cập nhật badge */
      if (unread !== lastUnread) {
        if (unread > lastUnread) ringBell();
        lastUnread = unread;
        setBadge(unread);
      }

      /* Phát hiện thông báo mới → toast */
      notifs.forEach(n => {
        if (!knownIds.has(+n.notif_id)) {
          knownIds.add(+n.notif_id);
          if (!firstLoad && +n.is_read === 0) {
            showToast(n.title, n.message, n.time_fmt);
            ringBell();
          }
        }
      });

      /* Cập nhật panel nếu đang mở */
      if (panelOpen) {
        list.innerHTML = notifs.map(nm => itemHtml(nm)).join('');
        list.querySelectorAll('.np-item').forEach(el => {
          el.addEventListener('click', () => markRead(+el.dataset.id, el));
        });
      }

      firstLoad = false;
    } catch { /* silent */ }
  }

  /* ══════════════════════════════════════════════════════
     MARK READ
  ══════════════════════════════════════════════════════ */
  async function markRead(nid, el) {
    if (!el.classList.contains('unread')) return;
    el.classList.remove('unread');
    el.querySelector('.np-ntitle') && (el.style.opacity = '.7');
    lastUnread = Math.max(0, lastUnread - 1);
    setBadge(lastUnread);

    const fd = new FormData();
    fd.append('action', 'mark_read');
    fd.append('notif_id', nid);
    await fetch(HANDLER, { method:'POST', body: fd }).catch(()=>{});
  }

  async function markAllRead() {
    list.querySelectorAll('.np-item.unread').forEach(el => {
      el.classList.remove('unread'); el.style.opacity = '.7';
    });
    lastUnread = 0; setBadge(0);

    const fd = new FormData();
    fd.append('action', 'mark_all_read');
    await fetch(HANDLER, { method:'POST', body: fd }).catch(()=>{});
  }

  /* ══════════════════════════════════════════════════════
     TOGGLE PANEL
  ══════════════════════════════════════════════════════ */
  bellBtn.addEventListener('click', e => {
    e.stopPropagation();
    panelOpen = !panelOpen;
    panel.classList.toggle('open', panelOpen);
    bellBtn.classList.toggle('active', panelOpen);
    if (panelOpen) loadPanel();
  });

  document.addEventListener('click', e => {
    if (panelOpen && !bellWrap.contains(e.target)) {
      panelOpen = false;
      panel.classList.remove('open');
      bellBtn.classList.remove('active');
    }
  });

  markAllBtn?.addEventListener('click', e => { e.stopPropagation(); markAllRead(); });

  /* ══════════════════════════════════════════════════════
     HELPERS
  ══════════════════════════════════════════════════════ */
  function itemHtml(n) {
    const icon = n.type === 'review_reply' ? 'fa-reply' : 'fa-bell';
    return `
    <div class="np-item${+n.is_read ? '' : ' unread'}" data-id="${n.notif_id}" role="button" tabindex="0">
      <div class="np-icon"><i class="fas ${icon}"></i></div>
      <div class="np-content">
        <div class="np-ntitle">${escHtml(n.title)}</div>
        <div class="np-nmsg">${escHtml(n.message)}</div>
        <div class="np-time">${n.time_fmt}</div>
      </div>
    </div>`;
  }

  function skeletons(n) {
    return Array(n).fill(`
      <div class="np-skeleton">
        <div class="np-sk-circle"></div>
        <div class="np-sk-lines">
          <div class="np-sk-line"></div>
          <div class="np-sk-line s"></div>
        </div>
      </div>`).join('');
  }

  function emptyState(msg) {
    return `<div class="np-empty"><i class="fas fa-bell-slash"></i>${msg}</div>`;
  }

  function escHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, m =>
      ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])
    );
  }

  /* ══════════════════════════════════════════════════════
     BOOT
  ══════════════════════════════════════════════════════ */
  poll();                              /* load ngay lần đầu */
  setInterval(poll, POLL_MS);          /* polling định kỳ   */

})();
</script>
