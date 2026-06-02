<?php
/**
 * notification_ui.php — v4 FINAL (14 class mismatches fixed)
 * Đặt tại: /PHP/ELite_GYM/Landing/Review/notification_ui.php
 * Include từ: Landing/index.php  (base URL = Landing/)
 *
 * Tất cả class/ID đã được đồng bộ 100% với notification.css:
 *   panel     → .open          (không phải np-open)
 *   bell      → .active        (không phải np-active)
 *   badge     → .show          (không phải style.display)
 *   bell ring → .ringing       (không phải has-notif)
 *   item read → .unread        (không phải np-read)
 *   title     → .np-ntitle     (không phải np-item-title)
 *   msg       → .np-nmsg       (không phải np-item-msg)
 *   time      → .np-time       (không phải np-item-time)
 *   toast     → .toast-notif   (không phải eg-toast)
 *   toast out → .out           (không phải remove eg-toast-show)
 *   t.icon    → .toast-icon    (không phải eg-toast-icon)
 *   t.body    → .toast-body    (không phải eg-toast-body)
 *   t.title   → .toast-title   (không phải eg-toast-title)
 *   t.msg     → .toast-msg     (không phải eg-toast-msg)
 *   t.close   → .toast-close   (không phải eg-toast-close)
 */
if (!$is_customer || !$cid) return;
?>
<script>
(function () {
  'use strict';

  const CID         = <?= (int)$cid ?>;
  const AUTO_URL    = '<?= url('api/notification/auto') ?>?customer_id=' + CID;
  const HANDLER_URL = '<?= url('api/notification') ?>';
  const POLL_MS     = 5 * 60 * 1000;

  /* Toast cache reset mỗi ngày → không spam toast khi reload */
  const TODAY       = new Date().toISOString().slice(0, 10);
  const TOAST_KEY   = 'eg_toast_' + CID + '_' + TODAY;

  /* ── Type meta (icon + màu theo loại thông báo) ─────────── */
  const TYPE_META = {
    no_registration: { icon: 'fas fa-calendar-times', color: '#f59e0b' },
    absent:          { icon: 'fas fa-user-slash',      color: '#ef4444' },
    review_reply:    { icon: 'fas fa-reply',           color: '#3b82f6' },
  };
  const getMeta = t => TYPE_META[t] || { icon: 'fas fa-bell', color: 'var(--red)' };

  /* ── DOM refs ───────────────────────────────────────────── */
  const bellBtn  = document.getElementById('notifBellBtn');
  const badge    = document.getElementById('notifBadge');
  const panel    = document.getElementById('notifPanel');
  const npList   = document.getElementById('npList');
  const markAll  = document.getElementById('npMarkAll');
  const bellWrap = document.getElementById('notifBellWrap');
  if (!bellBtn || !panel || !npList) return;

  let notifications = [];

  /* ── localStorage helpers ───────────────────────────────── */
  const getSet  = k => { try { return new Set(JSON.parse(localStorage.getItem(k)||'[]')); } catch { return new Set(); }};
  const saveSet = (k, s) => { try { localStorage.setItem(k, JSON.stringify([...s])); } catch {} };

  /* ── Escape HTML ────────────────────────────────────────── */
  const esc = s => String(s ?? '').replace(/[&<>"']/g,
    m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  /* ── Thời gian tương đối ────────────────────────────────── */
  function timeAgo(str) {
    if (!str) return '';
    /* MySQL trả về '2026-05-02 23:40:00' — cần replace space thành T */
    const d = new Date(str.replace(' ', 'T'));
    const s = Math.floor((Date.now() - d) / 1000);
    if (s < 60)    return 'Vừa xong';
    if (s < 3600)  return Math.floor(s / 60)   + ' phút trước';
    if (s < 86400) return Math.floor(s / 3600)  + ' giờ trước';
    return Math.floor(s / 86400) + ' ngày trước';
  }

  /* ── Badge — dùng class 'show' (khớp notification.css) ──── */
  function updateBadge(count) {
    if (!badge) return;
    if (count > 0) {
      badge.textContent = count > 9 ? '9+' : count;
      badge.classList.add('show');
      /* Rung chuông: class 'ringing' → bellShake animation trong CSS */
      bellBtn.classList.remove('ringing');
      void bellBtn.offsetWidth; // reflow để reset animation
      bellBtn.classList.add('ringing');
      setTimeout(() => bellBtn.classList.remove('ringing'), 600);
    } else {
      badge.classList.remove('show');
    }
  }

  /* ── Render danh sách ───────────────────────────────────── */
  function renderList() {
    npList.innerHTML = '';

    if (!notifications.length) {
      npList.innerHTML = `
        <div class="np-empty">
          <i class="fas fa-check-circle" style="color:#4ade80"></i>
          Không có thông báo nào
          <small style="display:block;margin-top:6px;color:rgba(255,255,255,.2)">
            Bạn đang thực hiện tốt!
          </small>
        </div>`;
      updateBadge(0);
      return;
    }

    let unread = 0;
    notifications.forEach(n => {
      const meta   = getMeta(n.type);
      const isRead = !!+n.is_read;
      if (!isRead) unread++;

      const el = document.createElement('div');
      /* CSS dùng 'unread' (không phải 'np-read') cho item chưa đọc */
      el.className  = 'np-item' + (isRead ? '' : ' unread');
      el.dataset.id = n.notif_id;

      el.innerHTML = `
        <div class="np-icon"
             style="background:${meta.color}22;border:1px solid ${meta.color}44;
                    color:${meta.color};border-radius:8px">
          <i class="${meta.icon}"></i>
        </div>
        <div class="np-content">
          <div class="np-ntitle">${esc(n.title)}</div>
          <div class="np-nmsg">${esc(n.message)}</div>
          <div class="np-time">${n.time_fmt || timeAgo(n.created_at)}</div>
        </div>
      `;

      el.addEventListener('click', () => markOneRead(n.notif_id, el));
      npList.appendChild(el);
    });

    updateBadge(unread);
  }

  /* ── Mark read ──────────────────────────────────────────── */
  async function markOneRead(notif_id, el) {
    if (!el.classList.contains('unread')) return;
    el.classList.remove('unread');
    updateBadge(npList.querySelectorAll('.np-item.unread').length);

    const fd = new FormData();
    fd.append('action',   'mark_read');
    fd.append('notif_id', notif_id);
    fetch(HANDLER_URL, { method:'POST', body:fd, credentials:'same-origin' }).catch(()=>{});
  }

  markAll?.addEventListener('click', async e => {
    e.stopPropagation();
    npList.querySelectorAll('.np-item.unread').forEach(el => el.classList.remove('unread'));
    updateBadge(0);
    const fd = new FormData();
    fd.append('action', 'mark_all_read');
    fetch(HANDLER_URL, { method:'POST', body:fd, credentials:'same-origin' }).catch(()=>{});
    notifications.forEach(n => n.is_read = '1');
  });

  /* ── Push auto (ghi DB) ─────────────────────────────────── */
  async function pushAuto() {
    try { await fetch(AUTO_URL, { credentials:'same-origin' }); }
    catch(e) { console.warn('[EG Notif] pushAuto failed:', e); }
  }

  /* ── Fetch từ handler ───────────────────────────────────── */
  async function fetchNotifs() {
    try {
      const res  = await fetch(HANDLER_URL + '?action=get_notifications', {
        credentials: 'same-origin'
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const json = await res.json();
      if (!json.ok) throw new Error(json.msg || 'handler error');

      notifications = json.data.notifications || [];
      renderList();

      /* Toast: chỉ hiện lần đầu mỗi ngày, reset lúc 00:00 */
      const toasted = getSet(TOAST_KEY);
      let changed   = false;
      notifications.forEach(n => {
        const key = String(n.notif_id);
        if (!+n.is_read && !toasted.has(key)) {
          showToast(n);
          toasted.add(key);
          changed = true;
        }
      });
      if (changed) saveSet(TOAST_KEY, toasted);

    } catch(e) {
      console.warn('[EG Notif] fetchNotifs failed:', e);
      npList.innerHTML = `
        <div class="np-empty" style="color:#f87171">
          <i class="fas fa-exclamation-triangle"></i>
          Không thể tải thông báo
        </div>`;
    }
  }

  /* ── Toast — dùng đúng class của notification.css ──────── */
  function showToast(n) {
    const stack = document.getElementById('toastStack');
    if (!stack) return;
    const meta = getMeta(n.type);
    const el   = document.createElement('div');

    /* CSS: .toast-notif { animation: toastIn ... } */
    el.className = 'toast-notif';
    el.innerHTML = `
      <div class="toast-icon"
           style="background:${meta.color}22;color:${meta.color};border:1px solid ${meta.color}44">
        <i class="${meta.icon}"></i>
      </div>
      <div class="toast-body">
        <div class="toast-title">${esc(n.title)}</div>
        <div class="toast-msg">${esc(
          String(n.message).length > 90
            ? String(n.message).slice(0, 90) + '…'
            : n.message
        )}</div>
        <div class="toast-time">${n.time_fmt || timeAgo(n.created_at)}</div>
      </div>
      <button class="toast-close" title="Đóng"><i class="fas fa-times"></i></button>
      <div class="toast-progress"></div>
    `;
    stack.appendChild(el);

    /* CSS: .toast-notif.out { animation: toastOut ... } */
    const remove = () => {
      el.classList.add('out');
      setTimeout(() => el.remove(), 300);
    };
    const timer = setTimeout(remove, 5500); /* khớp toastProgress 5s */
    el.querySelector('.toast-close').addEventListener('click', () => {
      clearTimeout(timer); remove();
    });
  }

  /* ── Bell panel toggle
       CSS: .notif-panel.open    (notification.css dùng 'open')
            .notif-bell-btn.active (notification.css dùng 'active')
  ─────────────────────────────────────────────────────────── */
  bellBtn.addEventListener('click', e => {
    e.stopPropagation();
    const open = panel.classList.toggle('open');   /* 'open' — khớp CSS */
    bellBtn.classList.toggle('active', open);       /* 'active' — khớp CSS */
    if (open) renderList();
  });

  document.addEventListener('click', e => {
    if (bellWrap && !bellWrap.contains(e.target)) {
      panel.classList.remove('open');
      bellBtn.classList.remove('active');
    }
  });

  /* ── Refresh cycle ──────────────────────────────────────── */
  async function refresh() {
    await pushAuto();
    await fetchNotifs();
  }

  refresh();
  setInterval(refresh, POLL_MS);

})();
</script>
