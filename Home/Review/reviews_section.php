<?php
/*
 * ═══════════════════════════════════════════════════════════════════
 *  REVIEWS SECTION — Thay thế đoạn <!-- ══ REVIEWS ══ --> trong index.php
 *
 *  CÁCH TÍCH HỢP:
 *  1. Copy file review_handler.php vào cùng thư mục với index.php
 *  2. Thêm dòng sau vào Landing.css (hoặc tạo thẻ <style> trong <head>):
 *       @import url('reviews_section.css');
 *     hoặc copy nội dung reviews_section.css vào cuối Landing.css
 *  3. Thay toàn bộ đoạn <!-- ══ REVIEWS ══ --> ... <?php endif; ?> trong
 *     index.php bằng nội dung file này.
 *
 *  DATABASE:
 *  Chạy lệnh sau 1 lần trong phpMyAdmin để thêm cột phản hồi nhân viên:
 *
 *    ALTER TABLE `Review`
 *        ADD COLUMN IF NOT EXISTS `staff_reply`      VARCHAR(300) NULL DEFAULT NULL,
 *        ADD COLUMN IF NOT EXISTS `staff_reply_by`   VARCHAR(100) NULL DEFAULT NULL,
 *        ADD COLUMN IF NOT EXISTS `staff_replied_at` DATETIME    NULL DEFAULT NULL;
 *
 *  (review_handler.php cũng tự migrate khi được gọi lần đầu)
 * ═══════════════════════════════════════════════════════════════════
 */

// Biến cần dùng từ index.php (đã có sẵn):
// $is_customer, $is_logged_in, $_SESSION['role']
$can_reply = in_array($_SESSION['role'] ?? '', ['Admin', 'Employee']);
?>

<!-- ══ REVIEWS — FULL SECTION ══ -->
<section class="reviews-full" id="reviews">
  <div class="wrap">

    <!-- Header -->
    <div class="sec-head light reveal">
      <div class="eyebrow"><span></span>Đánh giá thành viên</div>
      <h2>Khách hàng <span>nói gì</span> về chúng tôi</h2>
    </div>

    <!-- Overview bar (filled by JS) -->
    <div class="rv-overview reveal" id="rvOverview" style="display:none">
      <div class="rvo-score">
        <div class="rvo-num" id="rvAvg">—</div>
        <div class="rvo-stars" id="rvAvgStars"></div>
        <div class="rvo-total" id="rvTotal">0 đánh giá</div>
      </div>
      <div class="rvo-divider"></div>
      <div class="rvo-bars" id="rvDist"></div>
    </div>

    <!-- Write review button (chỉ hiện với Customer đã đăng nhập) -->
    <?php if ($is_customer): ?>
    <div class="rv-write-btn-wrap reveal">
      <button class="rv-write-btn" id="rvOpenForm">
        <i class="fas fa-pen"></i> Gửi đánh giá của bạn
      </button>
    </div>

    <!-- Write review form -->
    <div class="rv-form-modal" id="rvFormModal">
      <button class="rv-close-btn" id="rvCloseForm" title="Đóng"><i class="fas fa-times"></i></button>
      <div class="rv-form-title"><i class="fas fa-star" style="color:var(--red);margin-right:8px"></i>Viết đánh giá</div>

      <!-- Star picker -->
      <div class="rv-star-row" id="rvStarPicker" role="group" aria-label="Chọn số sao">
        <?php for ($i = 1; $i <= 5; $i++): ?>
        <button class="rv-star-btn" data-val="<?= $i ?>" title="<?= $i ?> sao" type="button">
          <i class="fas fa-star"></i>
        </button>
        <?php endfor; ?>
      </div>

      <!-- Textarea -->
      <textarea class="rv-textarea" id="rvContent"
        placeholder="Chia sẻ trải nghiệm của bạn tại Elite Gym... (tối thiểu 10 ký tự)"
        maxlength="500"></textarea>
      <div class="rv-char-count"><span id="rvCharLeft">500</span> ký tự còn lại</div>
      <div class="rv-hint" id="rvHint"></div>

      <button class="rv-submit-btn" id="rvSubmitBtn" disabled>
        <i class="fas fa-paper-plane"></i> Gửi đánh giá
      </button>
      <div class="rv-form-msg" id="rvFormMsg"></div>
    </div>
    <?php endif; ?>

    <!-- Cards grid -->
    <div class="rv-grid-full" id="rvGrid">
      <!-- Skeletons khi load -->
      <?php for ($i = 0; $i < 3; $i++): ?>
      <div class="rv-skeleton">
        <div class="rv-sk-line short"></div>
        <div class="rv-sk-line medium" style="margin-top:14px"></div>
        <div class="rv-sk-line" style="width:90%"></div>
        <div class="rv-sk-line medium"></div>
      </div>
      <?php endfor; ?>
    </div>

    <!-- Pagination -->
    <div class="rv-pagination" id="rvPager"></div>

  </div>
</section>

<script>
(function () {
  /* ── Config ─────────────────────────────────────────── */
  const HANDLER   = 'Review/review_handler.php';
  const IS_CUST   = <?= $is_customer  ? 'true' : 'false' ?>;
  const CAN_REPLY = <?= $can_reply    ? 'true' : 'false' ?>;

  /* ── DOM refs ───────────────────────────────────────── */
  const grid     = document.getElementById('rvGrid');
  const pager    = document.getElementById('rvPager');
  const overview = document.getElementById('rvOverview');
  const rvAvg    = document.getElementById('rvAvg');
  const rvAvgSt  = document.getElementById('rvAvgStars');
  const rvTotal  = document.getElementById('rvTotal');
  const rvDist   = document.getElementById('rvDist');

  /* ── Write review form ──────────────────────────────── */
  const openBtn   = document.getElementById('rvOpenForm');
  const closeBtn  = document.getElementById('rvCloseForm');
  const formModal = document.getElementById('rvFormModal');
  const starBtns  = document.querySelectorAll('.rv-star-btn');
  const textarea  = document.getElementById('rvContent');
  const charLeft  = document.getElementById('rvCharLeft');
  const submitBtn = document.getElementById('rvSubmitBtn');
  const formMsg   = document.getElementById('rvFormMsg');

  let selectedRating = 0;
  let curPage = 1;

  /* ── Star picker ────────────────────────────────────── */
  if (starBtns.length) {
    starBtns.forEach(btn => {
      btn.addEventListener('mouseenter', () => litStars(+btn.dataset.val));
      btn.addEventListener('mouseleave', () => litStars(selectedRating));
      btn.addEventListener('click', () => {
        selectedRating = +btn.dataset.val;
        litStars(selectedRating);
        validateForm();
      });
    });
  }
  function litStars(n) {
    starBtns.forEach(b => b.classList.toggle('lit', +b.dataset.val <= n));
  }

  /* ── Textarea char count ────────────────────────────── */
  if (textarea) {
    textarea.addEventListener('input', () => {
      const len = textarea.value.length;
      charLeft.textContent = 500 - len;
      validateForm();
    });
  }

  const hint      = document.getElementById('rvHint');

  function validateForm() {
    if (!submitBtn) return;
    const hasRating  = selectedRating > 0;
    const contentLen = (textarea?.value.trim().length ?? 0);
    const hasContent = contentLen >= 10;
    const ok = hasRating && hasContent;
    submitBtn.disabled = !ok;

    if (!hint) return;
    if (!hasRating && contentLen === 0) {
      hint.textContent = '';
    } else if (!hasRating) {
      hint.textContent = '⚠ Vui lòng chọn số sao trước.';
      hint.className = 'rv-hint warn';
    } else if (!hasContent) {
      const need = 10 - contentLen;
      hint.textContent = `⚠ Cần thêm ít nhất ${need} ký tự nữa.`;
      hint.className = 'rv-hint warn';
    } else {
      hint.textContent = '✓ Sẵn sàng gửi!';
      hint.className = 'rv-hint ok';
    }
  }

  /* ── Toggle form ────────────────────────────────────── */
  openBtn?.addEventListener('click', () => {
    formModal?.classList.add('open');
    openBtn.style.display = 'none';
    textarea?.focus();
  });
  closeBtn?.addEventListener('click', closeForm);
  function closeForm() {
    formModal?.classList.remove('open');
    openBtn && (openBtn.style.display = '');
    setFormMsg('', '');
  }

  /* ── Submit review ──────────────────────────────────── */
  submitBtn?.addEventListener('click', async () => {
    if (submitBtn.disabled) return;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi…';

    const fd = new FormData();
    fd.append('action', 'submit_review');
    fd.append('rating', selectedRating);
    fd.append('content', textarea.value.trim());

    try {
      const res = await fetch(HANDLER, { method: 'POST', body: fd });
      const json = await res.json();
      if (json.ok) {
        setFormMsg('✓ ' + (json.data.msg || 'Đánh giá của bạn đã được ghi nhận!'), 'ok');
        setTimeout(() => { closeForm(); loadReviews(1); }, 1800);
        selectedRating = 0; litStars(0);
        textarea.value = ''; charLeft.textContent = '500';
      } else {
        setFormMsg('✗ ' + json.msg, 'err');
        submitBtn.disabled = false;
      }
    } catch {
      setFormMsg('✗ Lỗi kết nối. Vui lòng thử lại.', 'err');
      submitBtn.disabled = false;
    }
    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Gửi đánh giá';
  });

  function setFormMsg(txt, cls) {
    if (!formMsg) return;
    formMsg.textContent = txt;
    formMsg.className = 'rv-form-msg' + (cls ? ' ' + cls : '');
  }

  /* ── Stars renderer ─────────────────────────────────── */
  function starsHtml(n, size = 12) {
    let h = '';
    for (let i = 1; i <= 5; i++) {
      h += `<i class="fa${i <= n ? 's' : 'r'} fa-star" style="font-size:${size}px;color:${i <= n ? '#f59e0b' : 'rgba(255,255,255,.15)'}"></i>`;
    }
    return h;
  }

  /* ── Avatar initials ────────────────────────────────── */
  function initials(name) {
    const parts = (name || '?').trim().split(/\s+/);
    return parts.length >= 2
      ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
      : parts[0].substring(0, 2).toUpperCase();
  }

  /* ── Date formatter ─────────────────────────────────── */
  function fmtDate(str) {
    if (!str) return '';
    const d = new Date(str);
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  /* ── Load reviews ───────────────────────────────────── */
  async function loadReviews(page) {
    curPage = page;
    grid.innerHTML = skeletons(6);

    try {
      const res  = await fetch(`${HANDLER}?action=get_reviews&page=${page}`);
      const json = await res.json();
      if (!json.ok) { grid.innerHTML = emptyState('Không thể tải đánh giá.'); return; }

      const d = json.data;

      /* Overview */
      if (d.total > 0 && overview) {
        rvAvg.textContent   = d.avg;
        rvAvgSt.innerHTML   = starsHtml(Math.round(d.avg), 16);
        rvTotal.textContent = d.total + ' đánh giá';
        rvDist.innerHTML    = buildDist(d.dist, d.total);
        overview.style.display = '';
      }

      /* Cards */
      if (!d.reviews.length) {
        grid.innerHTML = emptyState('Chưa có đánh giá nào. Hãy là người đầu tiên!');
      } else {
        grid.innerHTML = d.reviews.map(rv => cardHtml(rv)).join('');
        if (CAN_REPLY) attachReplyHandlers();
      }

      /* Pagination */
      pager.innerHTML = buildPager(d.pages, d.page);
      pager.querySelectorAll('.rv-pg-btn[data-page]').forEach(btn => {
        btn.addEventListener('click', () => {
          const p = +btn.dataset.page;
          if (p !== curPage) loadReviews(p);
        });
      });

    } catch (e) {
      grid.innerHTML = emptyState('Lỗi kết nối. Vui lòng tải lại trang.');
    }
  }

  /* ── Card HTML ──────────────────────────────────────── */
  function cardHtml(rv) {
    const hasReply = rv.staff_reply && rv.staff_reply.trim();
    const replyBlock = hasReply
      ? `<div class="rv-reply-block">
           <div class="rv-reply-badge"><i class="fas fa-reply"></i> Phản hồi từ Elite Gym</div>
           <div class="rv-reply-text">${escHtml(rv.staff_reply)}</div>
           <div class="rv-reply-who">— ${escHtml(rv.staff_reply_by || 'Nhân viên')} &nbsp;·&nbsp; ${fmtDate(rv.staff_replied_at)}</div>
         </div>`
      : '';

    const staffForm = CAN_REPLY
      ? `<div class="rv-staff-reply-form" data-rid="${rv.review_id}">
           <textarea class="rv-staff-textarea" placeholder="Nhập phản hồi của bạn…" rows="3">${hasReply ? escHtml(rv.staff_reply) : ''}</textarea>
           <button class="rv-staff-send" type="button"><i class="fas fa-reply"></i> ${hasReply ? 'Cập nhật' : 'Phản hồi'}</button>
           <div class="rv-staff-msg"></div>
         </div>`
      : '';

    return `
    <div class="rv-card-full reveal" data-rid="${rv.review_id}">
      <i class="fas fa-quote-right rv-quote-icon"></i>
      <div class="rv-card-header">
        <div class="rv-av-big">${initials(rv.full_name)}</div>
        <div class="rv-card-meta">
          <div class="rv-card-name">${escHtml(rv.full_name)}</div>
          <div class="rv-stars-sm">${starsHtml(+rv.rating, 12)}</div>
          <div class="rv-card-date">${fmtDate(rv.review_date)}</div>
        </div>
      </div>
      <div class="rv-card-body">${escHtml(rv.content)}</div>
      ${replyBlock}
      ${staffForm}
    </div>`;
  }

  /* ── Staff reply handlers ───────────────────────────── */
  function attachReplyHandlers() {
    grid.querySelectorAll('.rv-staff-reply-form').forEach(form => {
      const rid  = +form.dataset.rid;
      const ta   = form.querySelector('.rv-staff-textarea');
      const btn  = form.querySelector('.rv-staff-send');
      const msg  = form.querySelector('.rv-staff-msg');

      btn.addEventListener('click', async () => {
        const reply = ta.value.trim();
        if (reply.length < 5) { showMsg(msg, '✗ Phản hồi quá ngắn.', 'err'); return; }
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const fd = new FormData();
        fd.append('action', 'reply_review');
        fd.append('review_id', rid);
        fd.append('reply', reply);

        try {
          const res  = await fetch(HANDLER, { method: 'POST', body: fd });
          const json = await res.json();
          if (json.ok) {
            showMsg(msg, '✓ ' + json.data.msg, 'ok');
            btn.innerHTML = '<i class="fas fa-reply"></i> Cập nhật';
            /* Update reply block in card */
            const card = form.closest('.rv-card-full');
            let rb = card.querySelector('.rv-reply-block');
            const replyHtml = `<div class="rv-reply-block">
              <div class="rv-reply-badge"><i class="fas fa-reply"></i> Phản hồi từ Elite Gym</div>
              <div class="rv-reply-text">${escHtml(reply)}</div>
              <div class="rv-reply-who">— Nhân viên</div>
            </div>`;
            if (rb) rb.outerHTML = replyHtml;
            else form.insertAdjacentHTML('beforebegin', replyHtml);
          } else {
            showMsg(msg, '✗ ' + json.msg, 'err');
          }
        } catch {
          showMsg(msg, '✗ Lỗi kết nối.', 'err');
        }
        btn.disabled = false;
      });
    });
  }

  function showMsg(el, txt, cls) {
    el.textContent = txt;
    el.className = 'rv-staff-msg ' + cls;
    setTimeout(() => { el.textContent = ''; el.className = 'rv-staff-msg'; }, 3500);
  }

  /* ── Distribution bars ──────────────────────────────── */
  function buildDist(dist, total) {
    const map = {};
    dist.forEach(d => map[d.rating] = +d.cnt);
    let h = '';
    for (let s = 5; s >= 1; s--) {
      const cnt = map[s] || 0;
      const pct = total > 0 ? Math.round(cnt / total * 100) : 0;
      h += `<div class="rvo-bar-row">
        <span class="rvo-bar-label">${s}</span>
        <div class="rvo-bar-track"><div class="rvo-bar-fill" style="width:${pct}%"></div></div>
        <span class="rvo-bar-pct">${pct}%</span>
      </div>`;
    }
    return h;
  }

  /* ── Pagination HTML ────────────────────────────────── */
  function buildPager(pages, cur) {
    if (pages <= 1) return '';
    let h = '';
    if (cur > 1) h += `<button class="rv-pg-btn" data-page="${cur - 1}" title="Trang trước"><i class="fas fa-chevron-left"></i></button>`;
    for (let p = 1; p <= pages; p++) {
      h += `<button class="rv-pg-btn${p === cur ? ' active' : ''}" data-page="${p}">${p}</button>`;
    }
    if (cur < pages) h += `<button class="rv-pg-btn" data-page="${cur + 1}" title="Trang sau"><i class="fas fa-chevron-right"></i></button>`;
    return h;
  }

  /* ── Skeletons ──────────────────────────────────────── */
  function skeletons(n) {
    return Array(n).fill(`<div class="rv-skeleton">
      <div class="rv-sk-line short"></div>
      <div class="rv-sk-line medium" style="margin-top:14px"></div>
      <div class="rv-sk-line" style="width:90%"></div>
      <div class="rv-sk-line medium"></div>
    </div>`).join('');
  }

  /* ── Empty state ────────────────────────────────────── */
  function emptyState(msg) {
    return `<div class="rv-empty"><i class="fas fa-comment-slash"></i>${msg}</div>`;
  }

  /* ── Escape HTML ────────────────────────────────────── */
  function escHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
  }

  /* ── Boot ───────────────────────────────────────────── */
  loadReviews(1);

})();
</script>
<!-- ══ END REVIEWS ══ -->
