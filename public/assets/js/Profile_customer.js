// ════════════════════════════════════════
// CUSTOMER PROFILE — JS
// ════════════════════════════════════════
(function () {

// ── Tab switching ─────────────────────────────────────────────
window.switchTab = function (tab, btn) {
    document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
    var panel = document.getElementById('tab-' + tab);
    if (panel) panel.classList.add('active');
    if (btn)   btn.classList.add('active');
    // Update URL param without reload
    try {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        url.searchParams.delete('pw_step');
        history.replaceState(null, '', url.toString());
    } catch(e) {}
};

// ── Edit info toggle ──────────────────────────────────────────
var btnEdit   = document.getElementById('btnEditInfo');
var btnCancel = document.getElementById('btnCancelEdit');
var editSec   = document.getElementById('editFormSection');
var infoView  = document.getElementById('infoViewSection');
var infoActs  = document.getElementById('infoActions');

if (btnEdit) {
    btnEdit.addEventListener('click', function () {
        if (editSec)  editSec.classList.add('open');
        if (infoView) infoView.style.display = 'none';
        if (infoActs) infoActs.style.display = 'none';
    });
}
if (btnCancel) {
    btnCancel.addEventListener('click', function () {
        if (editSec)  editSec.classList.remove('open');
        if (infoView) infoView.style.display = '';
        if (infoActs) infoActs.style.display = '';
    });
}

// ── Modal đổi mật khẩu ───────────────────────────────────────
var overlay    = document.getElementById('modalOverlay');
var modalClose = document.getElementById('modalClose');
var btnChangePw = document.getElementById('btnChangePassword');

function openModal() { if (overlay) overlay.classList.add('open'); }
function closeModal() { if (overlay) overlay.classList.remove('open'); }

if (btnChangePw) btnChangePw.addEventListener('click', openModal);
if (modalClose)  modalClose.addEventListener('click', closeModal);
if (overlay) {
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
});

// Auto-open modal nếu có pw_step trong URL
var urlParams = new URLSearchParams(window.location.search);
var pwStep = urlParams.get('pw_step');
if (pwStep === '1' || pwStep === '2') { openModal(); }

// ── Toggle show/hide password ─────────────────────────────────
document.querySelectorAll('.toggle-pw').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var inp  = this.closest('.modal-input-wrap').querySelector('input');
        var icon = this.querySelector('i');
        if (!inp) return;
        if (inp.type === 'password') {
            inp.type = 'text';
            if (icon) icon.className = 'fas fa-eye-slash';
        } else {
            inp.type = 'password';
            if (icon) icon.className = 'fas fa-eye';
        }
    });
});

// ── Strength bar ──────────────────────────────────────────────
var newPwInput = document.getElementById('new_password');
if (newPwInput) {
    newPwInput.addEventListener('input', function () {
        var val  = this.value;
        var wrap = document.getElementById('strengthWrap');
        var fill = wrap ? wrap.querySelector('.strength-bar-fill') : null;
        var text = wrap ? wrap.querySelector('.strength-text')     : null;
        if (!wrap) return;
        if (!val) { wrap.style.display = 'none'; return; }
        wrap.style.display = 'flex';
        var score = 0;
        if (val.length >= 6)          score++;
        if (val.length >= 10)         score++;
        if (/[A-Z]/.test(val))        score++;
        if (/[0-9]/.test(val))        score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        var levels = [
            { pct: '20%',  color: '#ef4444', label: 'Rất yếu'    },
            { pct: '40%',  color: '#f97316', label: 'Yếu'        },
            { pct: '60%',  color: '#eab308', label: 'Trung bình'  },
            { pct: '80%',  color: '#22c55e', label: 'Mạnh'       },
            { pct: '100%', color: '#10b981', label: 'Rất mạnh'   }
        ];
        var lv = levels[Math.min(score, 4)];
        if (fill) { fill.style.width = lv.pct; fill.style.background = lv.color; }
        if (text) { text.textContent = lv.label; text.style.color = lv.color; }
    });
}

// ── Validate step 1 ───────────────────────────────────────────
var step1Form = document.getElementById('step1Form');
if (step1Form) {
    step1Form.addEventListener('submit', function (e) {
        ['old_password','new_password','confirm_password'].forEach(function (id) {
            var el = document.getElementById('err_' + id);
            if (el) el.textContent = '';
        });
        var oldPw  = (document.getElementById('old_password').value  || '').trim();
        var newPw  = (document.getElementById('new_password').value  || '').trim();
        var confPw = (document.getElementById('confirm_password').value || '').trim();
        var ok = true;
        if (!oldPw) { var el = document.getElementById('err_old_password'); if (el) el.textContent = 'Vui lòng nhập mật khẩu hiện tại.'; ok = false; }
        if (newPw.length < 6) { var el = document.getElementById('err_new_password'); if (el) el.textContent = 'Mật khẩu mới phải có ít nhất 6 ký tự.'; ok = false; }
        if (newPw && confPw && newPw !== confPw) { var el = document.getElementById('err_confirm_password'); if (el) el.textContent = 'Mật khẩu xác nhận không khớp.'; ok = false; }
        if (!ok) { e.preventDefault(); return; }
        var btn = step1Form.querySelector('button[type=submit]');
        var lbl = btn ? btn.querySelector('.btn-label') : null;
        var spn = btn ? btn.querySelector('.spinner')   : null;
        if (btn) btn.disabled = true;
        if (lbl) lbl.style.display = 'none';
        if (spn) spn.style.display = 'inline-block';
    });
}

// ── OTP digit input ───────────────────────────────────────────
var otpDigits = document.querySelectorAll('.otp-digit');
if (otpDigits.length) {
    function syncOtp() {
        var val = Array.from(otpDigits).map(function (i) { return i.value; }).join('');
        var h = document.getElementById('otp_full_hidden');
        if (h) h.value = val;
    }
    otpDigits.forEach(function (inp, idx) {
        inp.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g,'').slice(-1);
            if (this.value && idx < otpDigits.length - 1) otpDigits[idx + 1].focus();
            syncOtp();
        });
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !this.value && idx > 0) otpDigits[idx - 1].focus();
        });
        inp.addEventListener('paste', function (e) {
            e.preventDefault();
            var paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
            paste.split('').slice(0, otpDigits.length - idx).forEach(function (ch, i) {
                if (otpDigits[idx + i]) otpDigits[idx + i].value = ch;
            });
            otpDigits[Math.min(idx + paste.length, otpDigits.length - 1)].focus();
            syncOtp();
        });
    });

    var step2Form = document.getElementById('step2Form');
    if (step2Form) {
        step2Form.addEventListener('submit', function (e) {
            syncOtp();
            var otp = (document.getElementById('otp_full_hidden').value || '');
            var err = document.getElementById('err_otp');
            if (err) err.textContent = '';
            if (!/^\d{6}$/.test(otp)) {
                e.preventDefault();
                if (err) err.textContent = 'Vui lòng nhập đủ 6 chữ số.';
                return;
            }
            var btn = step2Form.querySelector('button[type=submit]');
            var lbl = btn ? btn.querySelector('.btn-label') : null;
            var spn = btn ? btn.querySelector('.spinner')   : null;
            if (btn) btn.disabled = true;
            if (lbl) lbl.style.display = 'none';
            if (spn) spn.style.display = 'inline-block';
        });
    }
}

// ── OTP countdown timer ───────────────────────────────────────
var countdownEl = document.getElementById('otpCountdown');
var resendBtn   = document.getElementById('resendOtpBtn');
if (countdownEl) {
    var seconds = 5 * 60;
    var timer = setInterval(function () {
        seconds--;
        if (seconds <= 0) {
            clearInterval(timer);
            countdownEl.textContent = '00:00';
            if (resendBtn) resendBtn.disabled = false;
            return;
        }
        var m = Math.floor(seconds / 60), s = seconds % 60;
        countdownEl.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    }, 1000);
}

window.resendOtp = function () {
    var form = document.getElementById('resendOtpForm');
    if (form) form.submit();
};

})();
