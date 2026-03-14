/* ══════════════════════════════════
   PROFILE PAGE — JavaScript
   Elite Gym
   ══════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {
  initTabs();
  initEditInfo();
  initPasswordModal();
  initOtpInputs();
  handleUrlParams();
});

/* ══════════════════════════════════
   TAB SWITCHING
   ══════════════════════════════════ */
function initTabs() {
  window.switchTab = function (name, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    const panel = document.getElementById('tab-' + name);
    if (panel) panel.classList.add('active');
    if (btn)   btn.classList.add('active');
    history.replaceState(null, '', '?tab=' + name);
  };
}

/* ══════════════════════════════════
   EDIT INFO (inline toggle)
   ══════════════════════════════════ */
function initEditInfo() {
  const btnEdit    = document.getElementById('btnEditInfo');
  const btnCancel  = document.getElementById('btnCancelEdit');
  const viewGrid   = document.getElementById('infoViewSection');
  const editSection= document.getElementById('editFormSection');
  const actionsBar = document.getElementById('infoActions');

  if (!btnEdit) return;

  btnEdit.addEventListener('click', () => {
    if (viewGrid)   viewGrid.style.display   = 'none';
    if (actionsBar) actionsBar.style.display = 'none';
    editSection.classList.add('open');
    editSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });

  if (btnCancel) {
    btnCancel.addEventListener('click', () => {
      if (viewGrid)   viewGrid.style.display   = '';
      if (actionsBar) actionsBar.style.display = '';
      editSection.classList.remove('open');
    });
  }

  // Client validation before submit
  const editForm = document.getElementById('editInfoForm');
  if (editForm) {
    editForm.addEventListener('submit', e => {
      const nameVal = editForm.querySelector('[name="full_name"]').value.trim();
      if (!nameVal) {
        e.preventDefault();
        showToast('Họ và tên không được để trống!', 'error');
        return;
      }
      const emailVal = editForm.querySelector('[name="email"]').value.trim();
      if (emailVal && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
        e.preventDefault();
        showToast('Email không hợp lệ!', 'error');
        return;
      }
      const saveBtn = editForm.querySelector('.btn-save');
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';
      }
    });
  }
}

/* ══════════════════════════════════
   CHANGE PASSWORD MODAL
   ══════════════════════════════════ */
function initPasswordModal() {
  const overlay = document.getElementById('modalOverlay');
  const btnOpen = document.getElementById('btnChangePassword');
  const btnClose= document.getElementById('modalClose');

  if (!overlay) return;

  const openModal  = () => { overlay.classList.add('open'); document.body.style.overflow = 'hidden'; };
  const closeModal = () => { overlay.classList.remove('open'); document.body.style.overflow = ''; };

  if (btnOpen)  btnOpen.addEventListener('click', openModal);
  if (btnClose) btnClose.addEventListener('click', closeModal);
  overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

  // Password visibility toggles
  document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.closest('.modal-input-wrap').querySelector('input');
      const show  = input.type === 'password';
      input.type  = show ? 'text' : 'password';
      btn.querySelector('i').className = show ? 'fas fa-eye' : 'fas fa-eye-slash';
    });
  });

  // Strength meter on new_password
  const newPwInput = document.getElementById('new_password');
  if (newPwInput) newPwInput.addEventListener('input', () => updateStrength(newPwInput.value));

  // Step 1 submit
  const step1Form = document.getElementById('step1Form');
  if (step1Form) {
    step1Form.addEventListener('submit', e => {
      if (!validateStep1()) { e.preventDefault(); return; }
      setLoading(step1Form.querySelector('.btn-modal-submit'), true);
    });
  }

  // Step 2 submit — collect OTP digits into hidden field
  const step2Form = document.getElementById('step2Form');
  if (step2Form) {
    step2Form.addEventListener('submit', e => {
      const code = collectOtp();
      if (code.length !== 6) {
        e.preventDefault();
        showToast('Vui lòng nhập đủ 6 chữ số OTP!', 'error');
        return;
      }
      const hidden = document.getElementById('otp_full_hidden');
      if (hidden) hidden.value = code;
      setLoading(step2Form.querySelector('.btn-modal-submit'), true);
    });
  }
}

/* ── Step 1 validation ── */
function validateStep1() {
  clearFieldErrors();
  let ok = true;
  const old_pw  = document.getElementById('old_password')?.value     ?? '';
  const new_pw  = document.getElementById('new_password')?.value     ?? '';
  const conf_pw = document.getElementById('confirm_password')?.value ?? '';

  if (!old_pw)        { showFieldError('err_old_password',   'Vui lòng nhập mật khẩu hiện tại!'); ok = false; }
  if (!new_pw)        { showFieldError('err_new_password',   'Vui lòng nhập mật khẩu mới!');       ok = false; }
  else if (new_pw.length < 6) { showFieldError('err_new_password', 'Mật khẩu tối thiểu 6 ký tự!'); ok = false; }
  if (!conf_pw)       { showFieldError('err_confirm_password','Vui lòng xác nhận mật khẩu mới!'); ok = false; }
  else if (new_pw !== conf_pw) { showFieldError('err_confirm_password', 'Mật khẩu không khớp!'); ok = false; }
  return ok;
}

/* ══════════════════════════════════
   OTP DIGIT INPUTS
   ══════════════════════════════════ */
function initOtpInputs() {
  const digits = document.querySelectorAll('.otp-digit');
  if (!digits.length) return;

  digits.forEach((inp, idx) => {
    inp.addEventListener('input', e => {
      const val = e.target.value.replace(/\D/g, '');
      e.target.value = val ? val.slice(-1) : '';
      updateFilled(inp);
      if (val && idx < digits.length - 1) digits[idx + 1].focus();
    });

    inp.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !inp.value && idx > 0) {
        digits[idx - 1].value = '';
        updateFilled(digits[idx - 1]);
        digits[idx - 1].focus();
      }
      if (e.key === 'ArrowLeft'  && idx > 0)                digits[idx - 1].focus();
      if (e.key === 'ArrowRight' && idx < digits.length - 1) digits[idx + 1].focus();
    });

    inp.addEventListener('paste', e => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData)
                       .getData('text').replace(/\D/g, '').slice(0, 6);
      [...pasted].forEach((ch, i) => {
        if (digits[i]) { digits[i].value = ch; updateFilled(digits[i]); }
      });
      const next = Math.min(pasted.length, digits.length - 1);
      digits[next].focus();
    });
  });
}

function updateFilled(inp) { inp.classList.toggle('filled', !!inp.value); }

function collectOtp() {
  let code = '';
  document.querySelectorAll('.otp-digit').forEach(d => code += d.value || '');
  return code;
}

/* ══════════════════════════════════
   RESEND OTP (submit form riêng, tránh lỗi form lồng nhau)
   ══════════════════════════════════ */
function resendOtp() {
  const form = document.getElementById('resendOtpForm');
  if (form) form.submit();
}

/* ══════════════════════════════════
   OTP COUNTDOWN
   ══════════════════════════════════ */
let _timerRef = null;

function startOtpTimer(seconds) {
  seconds = seconds ?? 300;
  const display   = document.getElementById('otpCountdown');
  const resendBtn = document.getElementById('resendOtpBtn');
  if (!display) return;

  clearInterval(_timerRef);
  if (resendBtn) resendBtn.disabled = true;

  let left = seconds;
  const render = () => {
    const m = String(Math.floor(left / 60)).padStart(2, '0');
    const s = String(left % 60).padStart(2, '0');
    display.textContent = `${m}:${s}`;
  };
  render();

  _timerRef = setInterval(() => {
    left--;
    render();
    if (left <= 0) {
      clearInterval(_timerRef);
      display.textContent = '00:00';
      if (resendBtn) resendBtn.disabled = false;
    }
  }, 1000);
}

/* ══════════════════════════════════
   PASSWORD STRENGTH
   ══════════════════════════════════ */
function updateStrength(val) {
  const wrap = document.getElementById('strengthWrap');
  if (!wrap) return;
  const fill = wrap.querySelector('.strength-bar-fill');
  const txt  = wrap.querySelector('.strength-text');
  if (!val) { fill.style.width = '0'; txt.textContent = ''; return; }

  let score = 0;
  if (val.length >= 6)          score++;
  if (val.length >= 10)         score++;
  if (/[A-Z]/.test(val))        score++;
  if (/[a-z]/.test(val))        score++;
  if (/[0-9]/.test(val))        score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  if (score <= 2) {
    fill.style.width = '33%'; fill.style.background = '#f87171';
    txt.textContent = 'Yếu'; txt.style.color = '#f87171';
  } else if (score <= 4) {
    fill.style.width = '66%'; fill.style.background = '#f59e0b';
    txt.textContent = 'Trung bình'; txt.style.color = '#f59e0b';
  } else {
    fill.style.width = '100%'; fill.style.background = '#4ade80';
    txt.textContent = 'Mạnh'; txt.style.color = '#4ade80';
  }
}

/* ══════════════════════════════════
   HANDLE URL PARAMS ON PAGE LOAD
   (auto-open modal at correct step,
    show alert, etc.)
   ══════════════════════════════════ */
function handleUrlParams() {
  const p      = new URLSearchParams(window.location.search);
  const pwStep = parseInt(p.get('pw_step') || '0');
  const error  = p.get('error')   || '';
  const success= p.get('success') || '';

  // Show alert
  if (error || success) {
    const msg = getAlertMsg(error || success, !!success);
    if (msg) showInlineAlert(msg.text, msg.type);
  }

  // Open modal at step if redirected back
  if (pwStep === 1 || pwStep === 2) {
    openModalAtStep(pwStep);
    if (pwStep === 2) startOtpTimer();
  }
}

function openModalAtStep(step) {
  const overlay = document.getElementById('modalOverlay');
  if (!overlay) return;
  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';

  const step1 = document.getElementById('pwStep1');
  const step2 = document.getElementById('pwStep2');
  if (step === 2) {
    if (step1) step1.style.display = 'none';
    if (step2) step2.style.display = 'block';
  } else {
    if (step1) step1.style.display = 'block';
    if (step2) step2.style.display = 'none';
  }
  updateStepIndicator(step);
}

function updateStepIndicator(active) {
  document.querySelectorAll('.pw-step-num').forEach((el, i) => {
    const n = i + 1;
    el.classList.toggle('active', n === active);
    el.classList.toggle('done',   n <  active);
    if (n < active) el.innerHTML = '<i class="fas fa-check" style="font-size:.65rem"></i>';
    else el.textContent = String(n);
  });
  document.querySelectorAll('.pw-step-label').forEach((el, i) => {
    el.classList.toggle('active', i + 1 === active);
  });
  document.querySelectorAll('.pw-step-connector').forEach((el, i) => {
    el.classList.toggle('done', i + 1 < active);
  });
}

/* ══════════════════════════════════
   HELPERS
   ══════════════════════════════════ */
function showFieldError(id, msg) {
  const el = document.getElementById(id);
  if (el) el.textContent = msg;
}
function clearFieldErrors() {
  document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
}
function setLoading(btn, on) {
  if (!btn) return;
  btn.disabled = on;
  btn.classList.toggle('loading', on);
}

function showInlineAlert(text, type) {
  const el = document.getElementById('infoAlert');
  if (!el) return;
  const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
  el.className = `alert alert-${type}`;
  el.innerHTML = `<i class="fas fa-${icon}"></i> ${text}`;
  el.style.display = 'flex';
  setTimeout(() => { el.style.display = 'none'; }, 6000);
}

function getAlertMsg(key, isSuccess) {
  const map = {
    // success
    info_updated:       { type: 'success', text: 'Thông tin cá nhân đã được cập nhật thành công!' },
    password_changed:   { type: 'success', text: 'Mật khẩu đã được đổi thành công!' },
    otp_sent:           { type: 'success', text: 'Mã OTP đã được gửi về email của bạn!' },
    // errors
    empty_name:         { type: 'danger',  text: 'Họ và tên không được để trống!' },
    invalid_email:      { type: 'danger',  text: 'Email không hợp lệ!' },
    db_error:           { type: 'danger',  text: 'Lỗi hệ thống! Vui lòng thử lại.' },
    empty_fields:       { type: 'danger',  text: 'Vui lòng nhập đầy đủ thông tin!' },
    wrong_old_password: { type: 'danger',  text: 'Mật khẩu hiện tại không đúng!' },
    weak_password:      { type: 'danger',  text: 'Mật khẩu mới phải có ít nhất 6 ký tự!' },
    password_mismatch:  { type: 'danger',  text: 'Mật khẩu xác nhận không khớp!' },
    no_email:           { type: 'danger',  text: 'Tài khoản chưa có email. Hãy cập nhật email trước!' },
    send_fail:          { type: 'danger',  text: 'Không thể gửi OTP! Vui lòng thử lại.' },
    otp_expired:        { type: 'danger',  text: 'Mã OTP đã hết hạn! Vui lòng gửi lại.' },
    invalid_otp:        { type: 'danger',  text: 'Mã OTP không đúng!' },
    not_found:          { type: 'danger',  text: 'Không tìm thấy tài khoản!' },
  };
  return map[key] || null;
}

function showToast(msg, type = 'info') {
  const c = { success:'rgba(74,222,128,.2)/#4ade80', error:'rgba(248,113,113,.2)/#f87171', info:'rgba(212,160,23,.2)/#d4a017' }[type]?.split('/') || ['rgba(212,160,23,.2)', '#d4a017'];
  const toast = Object.assign(document.createElement('div'), {});
  toast.style.cssText = `
    position:fixed;top:20px;right:20px;z-index:9999;
    padding:12px 20px;border-radius:10px;
    background:${c[0]};border:1px solid ${c[1]};
    color:#e8e8e8;font-size:.875rem;font-weight:600;
    backdrop-filter:blur(12px);box-shadow:0 8px 32px rgba(0,0,0,.5);
    animation:pgToastIn .3s ease forwards;
  `;
  toast.textContent = msg;
  if (!document.getElementById('pgToastStyle')) {
    const s = document.createElement('style');
    s.id = 'pgToastStyle';
    s.textContent = `
      @keyframes pgToastIn  { from{transform:translateX(120%);opacity:0} to{transform:none;opacity:1} }
      @keyframes pgToastOut { to  {transform:translateX(120%);opacity:0} }
    `;
    document.head.appendChild(s);
  }
  document.body.appendChild(toast);
  setTimeout(() => { toast.style.animation='pgToastOut .3s ease forwards'; setTimeout(()=>toast.remove(),300); }, 3500);
}
