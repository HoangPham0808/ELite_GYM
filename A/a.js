/* ══════════════════════════════════════════════════
   a.js — Elite Gym Registration Form
   ══════════════════════════════════════════════════ */
(function () {
  'use strict';

  /* ── QR Code generation ─────────────────────── */
  const qrImg = document.getElementById('qrImg');
  if (typeof QRCode !== 'undefined' && qrImg) {
    const qrContainer = document.createElement('div');
    qrContainer.style.cssText = 'position:absolute;opacity:0;pointer-events:none';
    document.body.appendChild(qrContainer);

    new QRCode(qrContainer, {
      text: FORM_URL,
      width: 176,
      height: 176,
      colorDark: '#000000',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M,
    });

    // Wait for QR render then extract as image
    setTimeout(() => {
      const canvas = qrContainer.querySelector('canvas');
      const img    = qrContainer.querySelector('img');
      if (canvas)    qrImg.src = canvas.toDataURL('image/png');
      else if (img)  qrImg.src = img.src;
      qrContainer.remove();
    }, 200);
  }

  /* ── Copy URL ───────────────────────────────── */
  const copyBtn = document.getElementById('copyBtn');
  copyBtn?.addEventListener('click', () => {
    navigator.clipboard.writeText(FORM_URL).then(() => {
      const orig = copyBtn.innerHTML;
      copyBtn.innerHTML = '<i class="fas fa-check"></i> Đã sao chép!';
      setTimeout(() => copyBtn.innerHTML = orig, 2000);
    });
  });

  /* ── Load admin stats ───────────────────────── */
  async function loadStats() {
    try {
      const res = await fetch('a.php?stats=1');
      const j   = await res.json();
      if (j.ok) {
        document.getElementById('statToday').textContent = j.today ?? '—';
        document.getElementById('statTotal').textContent = j.total ?? '—';
      }
    } catch {}
  }
  loadStats();
  setInterval(loadStats, 30000);

  /* ── Step management ────────────────────────── */
  const steps    = [1, 2, 3];
  let   curStep  = 1;

  function goStep(n) {
    // Hide all panels
    steps.forEach(i => {
      const panel = document.getElementById(`step${i}`);
      const ind   = document.getElementById(`step${i}Ind`);
      if (panel) panel.classList.remove('active');
      if (ind)   { ind.classList.remove('active', 'done'); }
    });

    // Mark done steps
    for (let i = 1; i < n; i++) {
      const ind = document.getElementById(`step${i}Ind`);
      if (ind) ind.classList.add('done');
      // Fill step lines
      const lines = document.querySelectorAll('.step-line');
      if (lines[i-1]) lines[i-1].classList.add('done');
    }

    // Activate current
    const panel = document.getElementById(`step${n}`);
    const ind   = document.getElementById(`step${n}Ind`);
    if (panel) panel.classList.add('active');
    if (ind)   ind.classList.add('active');

    curStep = n;

    // Scroll to top of card on mobile
    const card = document.getElementById('formCard');
    if (card && window.innerWidth < 900) {
      card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  /* ── Validation helpers ─────────────────────── */
  function setErr(id, msg) {
    const el = document.getElementById(id);
    if (el) el.textContent = msg;
    return !!msg;
  }

  function validateStep1() {
    let err = false;
    const name  = document.getElementById('fullName').value.trim();
    const phone = document.getElementById('phone').value.trim();

    err = setErr('errName',  name.length < 2   ? 'Họ tên tối thiểu 2 ký tự.' : '') || err;
    err = setErr('errPhone', !/^[0-9]{9,11}$/.test(phone) ? 'Số điện thoại không hợp lệ (9-11 chữ số).' : '') || err;
    return !err;
  }

  function validateStep2() {
    let err = false;
    err = setErr('errRating', selectedRating === 0 ? 'Vui lòng chọn số sao.' : '') || err;
    const review = document.getElementById('reviewText').value.trim();
    err = setErr('errReview', review.length < 5 ? 'Nhận xét tối thiểu 5 ký tự.' : '') || err;
    return !err;
  }

  /* ── Step 1 → 2 ─────────────────────────────── */
  document.getElementById('nextBtn1')?.addEventListener('click', () => {
    if (validateStep1()) goStep(2);
  });

  /* ── Step 2 → 1 ─────────────────────────────── */
  document.getElementById('backBtn2')?.addEventListener('click', () => goStep(1));

  /* ── Star picker (main rating) ──────────────── */
  let selectedRating = 0;
  const starBtns = document.querySelectorAll('.star-btn');

  function litStars(n) {
    starBtns.forEach(b => b.classList.toggle('lit', +b.dataset.val <= n));
  }

  starBtns.forEach(btn => {
    btn.addEventListener('mouseenter', () => litStars(+btn.dataset.val));
    btn.addEventListener('mouseleave', () => litStars(selectedRating));
    btn.addEventListener('click', () => {
      selectedRating = +btn.dataset.val;
      litStars(selectedRating);
      setErr('errRating', '');
    });
  });

  /* ── Category stars ─────────────────────────── */
  document.querySelectorAll('.cat-stars').forEach(group => {
    let catVal = 0;
    const btns = group.querySelectorAll('.cs-btn');
    btns.forEach(btn => {
      btn.addEventListener('mouseenter', () =>
        btns.forEach(b => b.classList.toggle('lit', +b.dataset.v <= +btn.dataset.v))
      );
      btn.addEventListener('mouseleave', () =>
        btns.forEach(b => b.classList.toggle('lit', +b.dataset.v <= catVal))
      );
      btn.addEventListener('click', () => {
        catVal = +btn.dataset.v;
        btns.forEach(b => b.classList.toggle('lit', +b.dataset.v <= catVal));
      });
    });
  });

  /* ── Char counter ───────────────────────────── */
  const reviewText = document.getElementById('reviewText');
  const charLeft   = document.getElementById('charLeft');
  reviewText?.addEventListener('input', () => {
    charLeft.textContent = 500 - reviewText.value.length;
    if (reviewText.value.trim().length >= 5) setErr('errReview', '');
  });

  /* ── Submit ─────────────────────────────────── */
  document.getElementById('nextBtn2')?.addEventListener('click', async () => {
    if (!validateStep2()) return;

    const overlay = document.getElementById('loadingOverlay');
    overlay.classList.add('show');

    const fd = new FormData();
    fd.append('ajax_action', 'submit');
    fd.append('full_name',   document.getElementById('fullName').value.trim());
    fd.append('phone',       document.getElementById('phone').value.trim());
    fd.append('email',       document.getElementById('email').value.trim());
    fd.append('address',     document.getElementById('address')?.value.trim() || '');
    fd.append('dob',         document.getElementById('dob').value);
    fd.append('gender',      document.querySelector('input[name="gender"]:checked')?.value || 'Other');
    fd.append('rating',      selectedRating);
    fd.append('review',      reviewText.value.trim());

    try {
      const res  = await fetch('a.php', { method: 'POST', body: fd });
      const json = await res.json();

      overlay.classList.remove('show');

      if (json.ok) {
        document.getElementById('successName').textContent = document.getElementById('fullName').value.trim() + '!';
        document.getElementById('successCid').textContent  = 'Mã KH: #' + String(json.customer_id).padStart(5, '0');
        goStep(3);
        loadStats(); // refresh stats
      } else {
        alert('Lỗi: ' + json.msg);
      }
    } catch (e) {
      overlay.classList.remove('show');
      alert('Lỗi kết nối máy chủ. Vui lòng thử lại.');
    }
  });

  /* ── Reset ──────────────────────────────────── */
  document.getElementById('resetBtn')?.addEventListener('click', () => {
    // Reset form values
    document.getElementById('fullName').value   = '';
    document.getElementById('phone').value      = '';
    document.getElementById('email').value      = '';
    document.getElementById('address').value    = '';
    document.getElementById('dob').value        = '';
    reviewText.value = '';
    charLeft.textContent = '500';
    selectedRating = 0;
    litStars(0);

    // Reset gender
    const gOther = document.querySelector('input[name="gender"][value="Other"]');
    if (gOther) gOther.checked = true;

    // Reset cat stars
    document.querySelectorAll('.cs-btn').forEach(b => b.classList.remove('lit'));

    // Reset step lines
    document.querySelectorAll('.step-line').forEach(l => l.classList.remove('done'));

    // Clear errors
    ['errName','errPhone','errRating','errReview'].forEach(id => setErr(id, ''));

    goStep(1);
  });

  /* ── Live input clear errors ────────────────── */
  document.getElementById('fullName')?.addEventListener('input', () => {
    if (document.getElementById('fullName').value.trim().length >= 2) setErr('errName', '');
  });
  document.getElementById('phone')?.addEventListener('input', () => {
    if (/^[0-9]{9,11}$/.test(document.getElementById('phone').value.trim())) setErr('errPhone', '');
  });

})();
