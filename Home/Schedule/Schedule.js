/* ══════════════════════════════════
   CURSOR GLOW
══════════════════════════════════ */
const cursorGlow = document.getElementById('cursorGlow');
if (cursorGlow) {
  document.addEventListener('mousemove', e => {
    cursorGlow.style.left = e.clientX + 'px';
    cursorGlow.style.top  = e.clientY + 'px';
  });
}

/* ══════════════════════════════════
   NAVBAR SCROLL (always scrolled on this page)
══════════════════════════════════ */
const nav = document.getElementById('nav');
if (nav) {
  window.addEventListener('scroll', () => {
    nav.classList.add('scrolled');
  }, { passive: true });
}

/* ══════════════════════════════════
   TABS
══════════════════════════════════ */
const tabs   = document.querySelectorAll('.sch-tab');
const panels = document.querySelectorAll('.sch-panel');

// Kích hoạt tab đúng theo URL hash khi tải trang
const hashTab = window.location.hash === '#my' ? 'my' : 'week';
tabs.forEach(tab     => tab.classList.toggle('active',   tab.dataset.tab === hashTab));
panels.forEach(panel => panel.classList.toggle('active', panel.id === 'panel-' + hashTab));

tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    const targetTab = tab.dataset.tab;

    // Tab "Lớp của tôi": luôn reload để lấy dữ liệu mới nhất từ server
    if (targetTab === 'my') {
      const weekParam = new URLSearchParams(window.location.search).get('week') || '0';
      // _r=timestamp buộc browser không dùng cache
      window.location.replace(`Schedule.php?week=${weekParam}&_r=${Date.now()}#my`);
      return;
    }

    // Tab "Lịch tuần": chuyển tab bình thường, không reload
    tabs.forEach(t => t.classList.remove('active'));
    panels.forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    const target = document.getElementById('panel-' + targetTab);
    if (target) target.classList.add('active');
    history.replaceState(null, '', '#' + targetTab);
  });
});

/* ══════════════════════════════════
   REVEAL ANIMATION
══════════════════════════════════ */
document.querySelectorAll('.sch-list-card, .sch-day').forEach((el, i) => {
  el.style.opacity    = '0';
  el.style.transform  = 'translateY(16px)';
  el.style.transition = `opacity .45s ease ${i * 0.04}s, transform .45s ease ${i * 0.04}s`;

  const obs = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.style.opacity   = '1';
        e.target.style.transform = 'translateY(0)';
        obs.unobserve(e.target);
      }
    });
  }, { threshold: 0.06 });
  obs.observe(el);
});

/* ══════════════════════════════════
   TOAST
══════════════════════════════════ */
function showToast(msg, isError = false) {
  const toast = document.getElementById('schToast');
  const icon  = document.getElementById('schToastIcon');
  const msgEl = document.getElementById('schToastMsg');
  if (!toast) return;
  msgEl.textContent = msg;
  toast.className = 'sch-toast show ' + (isError ? 'toast--error' : 'toast--success');
  icon.className  = isError ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
  clearTimeout(toast._t);
  toast._t = setTimeout(() => { toast.classList.remove('show'); }, 3000);
}

/* ══════════════════════════════════
   ĐĂNG KÝ / HỦY — AJAX
══════════════════════════════════ */
async function doClassAction(classId, action, btn) {
  btn.disabled   = true;
  const origHTML = btn.innerHTML;
  btn.innerHTML  = '<i class="fas fa-spinner fa-spin"></i>';

  try {
    const fd = new FormData();
    fd.append('action',   action);
    fd.append('class_id', classId);

    const res  = await fetch('Schedule_function.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      showToast(data.message, false);

      // ── Cập nhật UI lịch tuần ngay lập tức ──
      const card = btn.closest('.sch-class-card');
      if (card) {
        if (data.action === 'registered') {
          card.classList.add('sch-class-card--mine');
          btn.className      = 'sch-btn-cancel';
          btn.dataset.action = 'cancel';
          btn.innerHTML      = '<i class="fas fa-times"></i> Hủy';
        } else {
          card.classList.remove('sch-class-card--mine');
          btn.className      = 'sch-btn-register';
          btn.dataset.action = 'register';
          btn.innerHTML      = '<i class="fas fa-plus"></i> Đăng ký';
        }
        btn.disabled = false;
      }

      // ── Nút hủy trong tab "Lớp của tôi" → xoá card khỏi DOM ──
      const listCard = btn.closest('.sch-list-card');
      if (listCard && data.action === 'cancelled') {
        listCard.style.transition = 'opacity .3s, transform .3s';
        listCard.style.opacity    = '0';
        listCard.style.transform  = 'translateY(-8px)';
        setTimeout(() => listCard.remove(), 300);
      }

    } else {
      showToast(data.message, true);
      btn.innerHTML = origHTML;
      btn.disabled  = false;
    }
  } catch (err) {
    showToast('Lỗi kết nối, vui lòng thử lại', true);
    btn.innerHTML = origHTML;
    btn.disabled  = false;
  }
}

/* Gắn event listener bằng event delegation */
document.addEventListener('click', e => {
  const btnReg = e.target.closest('.sch-btn-register');
  if (btnReg) { doClassAction(btnReg.dataset.classId, 'register', btnReg); return; }

  const btnCan = e.target.closest('.sch-btn-cancel');
  if (btnCan) { doClassAction(btnCan.dataset.classId, 'cancel', btnCan); return; }

  const btnCanList = e.target.closest('.sch-btn-cancel-list');
  if (btnCanList) { doClassAction(btnCanList.dataset.classId, 'cancel', btnCanList); }
});
