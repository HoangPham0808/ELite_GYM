/* ══════════════════════════════════════════════════
   PAYMENT.JS — Elite Gym
   Xử lý toàn bộ luồng mua gói tập:
   1. Load & hiển thị danh sách gói
   2. Checkout modal (số lượng, khuyến mãi, credit nâng cấp)
   3. QR thanh toán + auto-polling
   4. Xác nhận thanh toán thủ công
   5. Modal thành công
══════════════════════════════════════════════════ */

const API = 'Payment_function.php';

// ── State ──────────────────────────────────────────
let allPlans         = [];
let selectedPlan     = null;
let currentQty       = 1;
let selectedPromo    = null;
let upgradeCredit    = 0;
let currentInvoiceId = null;
let qrTimer          = null;
let pollTimer        = null;
let countdown        = 300;
let promoOpen        = false;

// ── Helpers ────────────────────────────────────────
function fmtMoney(v) {
    return Number(v).toLocaleString('vi-VN') + '₫';
}

function showToast(msg, type = 'info') {
    const icons  = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle' };
    const colors = { success: '#22c55e', error: '#f87171', info: '#60a5fa', warning: '#fbbf24' };
    const c = colors[type] || colors.info;
    const el = document.createElement('div');
    el.className = 'eg-toast';
    el.style.cssText = `display:flex;align-items:center;gap:10px;padding:12px 18px;
        background:#1e1e1e;border:1px solid ${c}33;border-left:3px solid ${c};
        border-radius:4px;color:#fff;font-size:13px;max-width:320px;
        box-shadow:0 8px 24px rgba(0,0,0,.45);animation:toastIn .25s ease;
        font-family:'Barlow',sans-serif;`;
    el.innerHTML = `<i class="fas ${icons[type]||icons.info}" style="color:${c};flex-shrink:0"></i><span>${msg}</span>`;
    const container = document.getElementById('toastContainer');
    if (container) container.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

// ══════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    loadPlans();

    // Đóng modal khi click nền
    document.querySelectorAll('.modal-overlay').forEach(o => {
        o.addEventListener('click', e => {
            if (e.target === o) o.classList.remove('active');
        });
    });
});

// ══════════════════════════════════════════════════
// LOAD & RENDER PLANS
// ══════════════════════════════════════════════════
async function loadPlans() {
    const grid = document.getElementById('plansGrid');
    grid.innerHTML = `<div class="plans-loading"><i class="fas fa-spinner fa-spin"></i> Đang tải gói tập...</div>`;

    try {
        const res = await fetch(`${API}?action=get_plans`);
        const d   = await res.json();
        if (!d.success) throw new Error(d.message);

        allPlans = d.data;
        renderPlans(allPlans);

        const countEl = document.getElementById('plansCount');
        if (countEl) {
            countEl.textContent = allPlans.length + ' gói tập' +
                (d.cur_type_name ? ' · đang dùng: ' + d.cur_type_name : '');
        }
    } catch(e) {
        grid.innerHTML = `<div class="plans-empty" style="color:#f87171">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Lỗi tải gói tập. Vui lòng thử lại.</p>
        </div>`;
    }
}

function renderPlans(plans) {
    const grid = document.getElementById('plansGrid');
    if (!plans.length) {
        grid.innerHTML = `<div class="plans-empty">
            <i class="fas fa-box-open"></i>
            <p>Không có gói tập phù hợp</p>
        </div>`;
        return;
    }

    const iconMap = {
        Basic:    'fas fa-fire',
        Standard: 'fas fa-bolt',
        Premium:  'fas fa-crown',
        VIP:      'fas fa-gem',
        Student:  'fas fa-graduation-cap',
    };

    grid.innerHTML = plans.map(p => {
        const color  = p.color_code || '#cc0000';
        const icon   = iconMap[p.type_name] || 'fas fa-dumbbell';
        const isHot  = ['Premium', 'VIP'].includes(p.type_name);
        const oldPrc = Math.round(p.price * 1.2 / 1000000) * 1000000;
        const planJson   = encodeURIComponent(JSON.stringify(p));
        const onclickStr = `openCheckout(decodeURIComponent('${planJson}'))`;

        return `
        <div class="plan-card ${isHot ? 'plan-hot' : ''}"
             data-price="${p.price}" data-duration="${p.duration_months}"
             style="${isHot
                ? `--pt:${color};border-color:${color};box-shadow:0 0 0 1px ${color}40,0 12px 40px rgba(0,0,0,.35)`
                : `--pt:${color}`}"
             onclick="${onclickStr}">

          <!-- Image -->
          <div class="plan-card-img">
            ${isHot
                ? `<span class="plan-hot-badge" style="background:${color}"><i class="fas fa-star"></i> ${p.type_name}</span>`
                : (p.type_name ? `<span class="plan-type-badge" style="background:${color}">${p.type_name}</span>` : '')
            }
            <span class="plan-sale-badge">SALE</span>
            ${p.image_url
                ? `<img src="${p.image_url}" alt="${p.plan_name}" class="plan-real-img" loading="lazy"/>
                   <div class="plan-img-overlay"></div>`
                : `<div class="plan-placeholder" style="background:linear-gradient(135deg,#1a0a0a,#2a1010)">
                     <i class="${icon}" style="font-size:44px;color:${color};opacity:.7"></i>
                   </div>`
            }
            <div class="plan-dur-chip" style="border-color:${color}40;color:${color}">${p.duration_months} THÁNG</div>
          </div>

          <!-- Body -->
          <div class="plan-card-body">
            <div class="plan-duration" style="color:${color}">${p.duration_months} THÁNG</div>
            <div class="plan-name">${p.plan_name}</div>
            ${p.description ? `<div class="plan-desc">${p.description}</div>` : ''}
            ${p.type_name
                ? `<div class="plan-type-chip" style="border-color:${color}40;color:${color}">
                     <i class="${icon}" style="font-size:.65rem"></i> ${p.type_name}
                   </div>`
                : ''}
          </div>

          <!-- Footer -->
          <div class="plan-card-foot" style="${isHot ? `border-top-color:${color}60` : ''}">
            <div class="plan-price-box">
              <div class="plan-old-price">${fmtMoney(oldPrc)}</div>
              <div class="plan-new-price" style="color:${isHot ? color : '#cc0000'}">${fmtMoney(p.price)}<sub>₫</sub></div>
            </div>
            <button class="plan-buy-btn" style="${isHot ? `background:${color}` : ''}"
                    onclick="event.stopPropagation();${onclickStr}">
              <i class="fas fa-shopping-cart"></i> Mua ngay
            </button>
          </div>
        </div>`;
    }).join('');
}

function sortPlans(val) {
    let arr = [...allPlans];
    if (val === 'price-asc')       arr.sort((a,b) => a.price - b.price);
    else if (val === 'price-desc') arr.sort((a,b) => b.price - a.price);
    else if (val === 'duration-asc') arr.sort((a,b) => a.duration_months - b.duration_months);
    renderPlans(arr);
}

// ══════════════════════════════════════════════════
// CHECKOUT MODAL
// ══════════════════════════════════════════════════
async function openCheckout(planJson) {
    // planJson có thể là chuỗi JSON thuần hoặc đã decodeURIComponent từ onclick
    selectedPlan = typeof planJson === 'string' ? JSON.parse(planJson) : planJson;
    currentQty    = 1;
    selectedPromo = null;
    upgradeCredit = 0;
    promoOpen     = false;

    // Reset promo UI
    document.getElementById('promoList').style.display    = 'none';
    document.getElementById('promoChevron').className     = 'fas fa-chevron-down';
    document.getElementById('promoItems').innerHTML       = '';
    document.getElementById('promoNone').style.display    = 'none';
    document.getElementById('qtyDisplay').textContent     = '1';

    const color = selectedPlan.color_code || '#cc0000';
    const iconMap = { Basic:'fas fa-fire', Standard:'fas fa-bolt', Premium:'fas fa-crown', VIP:'fas fa-gem', Student:'fas fa-graduation-cap' };
    const icon  = iconMap[selectedPlan.type_name] || 'fas fa-dumbbell';

    document.getElementById('checkoutPlanInfo').innerHTML = `
        <div class="co-plan-row">
            <div class="co-plan-icon" style="background:${color}22;color:${color}">
                <i class="${icon}"></i>
            </div>
            <div style="flex:1">
                <div class="co-plan-name">${selectedPlan.plan_name}</div>
                <div class="co-plan-meta">${selectedPlan.duration_months} tháng · ${selectedPlan.type_name || 'Gói tập'}</div>
            </div>
            <div class="co-plan-price" style="color:${color}">${fmtMoney(selectedPlan.price)}</div>
        </div>`;

    // Fetch upgrade credit
    if (selectedPlan.package_type_id) {
        try {
            const r = await fetch(`${API}?action=get_upgrade_credit&package_type_id=${selectedPlan.package_type_id}`);
            const d = await r.json();
            if (d.success && d.is_upgrade && d.credit > 0) {
                upgradeCredit = d.credit;
                document.getElementById('upgradeNotice').style.display = 'flex';
                document.getElementById('upgradeText').textContent =
                    `Nâng cấp từ ${d.old_type} → hoàn lại ${fmtMoney(d.credit)} (còn ${d.days_remaining} ngày).`;
            } else {
                upgradeCredit = 0;
                document.getElementById('upgradeNotice').style.display = 'none';
            }
        } catch(e) {
            upgradeCredit = 0;
            document.getElementById('upgradeNotice').style.display = 'none';
        }
    } else {
        upgradeCredit = 0;
        document.getElementById('upgradeNotice').style.display = 'none';
    }

    recalcSummary();
    document.getElementById('checkoutModal').classList.add('active');
}

function closeCheckout() {
    document.getElementById('checkoutModal').classList.remove('active');
}

function changeQty(delta) {
    currentQty = Math.max(1, Math.min(12, currentQty + delta));
    document.getElementById('qtyDisplay').textContent = currentQty;
    recalcSummary();
    if (promoOpen) loadPromos();
}

function recalcSummary() {
    if (!selectedPlan) return;
    const original = selectedPlan.price * currentQty;
    let promoDisc  = 0;

    if (selectedPromo) {
        promoDisc = original * parseFloat(selectedPromo.discount_percent) / 100;
        if (selectedPromo.max_discount_amount && promoDisc > parseFloat(selectedPromo.max_discount_amount)) {
            promoDisc = parseFloat(selectedPromo.max_discount_amount);
        }
    }

    const total = Math.max(0, original - upgradeCredit - promoDisc);

    document.getElementById('sumOriginal').textContent = fmtMoney(original);
    document.getElementById('sumTotal').textContent    = fmtMoney(total);

    // Upgrade row
    const upRow = document.getElementById('sumUpgradeRow');
    if (upgradeCredit > 0) {
        upRow.style.display = 'flex';
        document.getElementById('sumUpgrade').textContent = `- ${fmtMoney(upgradeCredit)}`;
    } else {
        upRow.style.display = 'none';
    }

    // Promo row
    const prRow = document.getElementById('sumPromoRow');
    if (promoDisc > 0 && selectedPromo) {
        prRow.style.display = 'flex';
        document.getElementById('sumPromo').textContent = `- ${fmtMoney(promoDisc)} (${selectedPromo.discount_percent}%)`;
    } else {
        prRow.style.display = 'none';
    }
}

// ══════════════════════════════════════════════════
// PROMO
// ══════════════════════════════════════════════════
function togglePromo() {
    promoOpen = !promoOpen;
    document.getElementById('promoList').style.display = promoOpen ? 'block' : 'none';
    document.getElementById('promoChevron').className  = promoOpen ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    if (promoOpen) loadPromos();
}

async function loadPromos() {
    if (!selectedPlan) return;
    const total = selectedPlan.price * currentQty;

    document.getElementById('promoLoading').style.display = 'block';
    document.getElementById('promoItems').innerHTML       = '';
    document.getElementById('promoNone').style.display    = 'none';

    try {
        const r = await fetch(`${API}?action=get_promotions&total=${total}`);
        const d = await r.json();
        document.getElementById('promoLoading').style.display = 'none';

        if (!d.success || !d.data.length) {
            document.getElementById('promoNone').style.display = 'block';
            return;
        }

        document.getElementById('promoItems').innerHTML = d.data.map(p => {
            const disc = Math.min(
                total * p.discount_percent / 100,
                p.max_discount_amount ? parseFloat(p.max_discount_amount) : Infinity
            );
            const isSelected = selectedPromo && selectedPromo.promotion_id == p.promotion_id;
            return `
            <div class="promo-option ${isSelected ? 'selected' : ''}"
                 onclick="applyPromo(${JSON.stringify(JSON.stringify(p))})">
                <div class="promo-opt-inner">
                    <div class="promo-opt-name">${p.promotion_name}</div>
                    <div class="promo-opt-meta">
                        ${p.discount_percent}% OFF · Tiết kiệm <strong>${fmtMoney(disc)}</strong>
                        ${p.max_usage ? `<span class="promo-usage">${p.usage_count}/${p.max_usage} lượt</span>` : ''}
                    </div>
                </div>
                <div class="promo-opt-check ${isSelected ? 'active' : ''}">
                    <i class="fas fa-check"></i>
                </div>
            </div>`;
        }).join('');
    } catch(e) {
        document.getElementById('promoLoading').style.display = 'none';
        document.getElementById('promoNone').style.display    = 'block';
    }
}

function applyPromo(promoJson) {
    const promo = JSON.parse(promoJson);
    // Toggle: bỏ chọn nếu đã chọn
    if (selectedPromo && selectedPromo.promotion_id == promo.promotion_id) {
        selectedPromo = null;
    } else {
        selectedPromo = promo;
    }
    recalcSummary();
    loadPromos();
}

// ══════════════════════════════════════════════════
// SUBMIT ORDER
// ══════════════════════════════════════════════════
async function submitOrder(method) {
    if (!selectedPlan) return;

    const btnTransfer = document.querySelector('.btn-pay-transfer');
    const btnCash     = document.querySelector('.btn-pay-cash');
    if (btnTransfer) btnTransfer.disabled = true;
    if (btnCash)     btnCash.disabled     = true;

    const body = new FormData();
    body.append('action',   'create_order');
    body.append('plan_id',  selectedPlan.plan_id);
    body.append('quantity', currentQty);
    if (selectedPromo) body.append('promotion_id', selectedPromo.promotion_id);

    try {
        const res = await fetch(API, { method: 'POST', body });
        const d   = await res.json();

        if (!d.success) {
            showToast(d.message || 'Lỗi tạo đơn hàng', 'error');
            if (btnTransfer) btnTransfer.disabled = false;
            if (btnCash)     btnCash.disabled     = false;
            return;
        }

        currentInvoiceId = d.invoice_id;
        closeCheckout();

        if (method === 'cash') {
            // Thanh toán tại quầy — xác nhận ngay
            const payBody = new FormData();
            payBody.append('action',     'confirm_payment');
            payBody.append('invoice_id', d.invoice_id);
            payBody.append('method',     'Thanh toán tại quầy');
            const payRes = await fetch(API, { method: 'POST', body: payBody });
            const payD   = await payRes.json();
            if (payD.success) {
                openSuccess(d.plan_name);
            } else {
                showToast(payD.message || 'Lỗi xác nhận', 'error');
            }
        } else {
            // Chuyển khoản QR
            openQRModal(d);
        }
    } catch(e) {
        showToast('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        if (btnTransfer) btnTransfer.disabled = false;
        if (btnCash)     btnCash.disabled     = false;
    }
}

// ══════════════════════════════════════════════════
// QR MODAL
// ══════════════════════════════════════════════════
function openQRModal(d) {
    currentInvoiceId = d.invoice_id;

    document.getElementById('qrInvoiceId').textContent   = `Hóa đơn #${d.invoice_id}`;
    document.getElementById('qrBankName').textContent    = d.bank.bank_id;
    document.getElementById('qrAccNo').textContent       = d.bank.account_no;
    document.getElementById('qrAccName').textContent     = d.bank.account_name;
    document.getElementById('qrAmount').textContent      = fmtMoney(d.amount);
    document.getElementById('qrDesc').textContent        = d.description;
    document.getElementById('qrAmountBadge').textContent = fmtMoney(d.amount);

    // Reset status row
    document.getElementById('qrStatusRow').innerHTML   = '<i class="fas fa-circle-notch fa-spin"></i> Đang chờ thanh toán...';
    document.getElementById('qrStatusRow').style.color = '';

    document.getElementById('qrImgArea').innerHTML = `
        <img src="${d.qr_url}" alt="QR Thanh toán" class="qr-img"
             onerror="this.parentNode.innerHTML='<div class=\\'qr-err\\'><i class=\\'fas fa-exclamation-triangle\\'></i><p>Không tải được mã QR.<br>Kiểm tra kết nối mạng.</p></div>'"/>`;

    // Countdown
    countdown = 300;
    updateQRCountdown();
    clearInterval(qrTimer);
    clearInterval(pollTimer);

    qrTimer = setInterval(() => {
        countdown--;
        updateQRCountdown();
        if (countdown <= 0) {
            clearInterval(qrTimer);
            clearInterval(pollTimer);
            document.getElementById('qrImgArea').innerHTML = `
                <div class="qr-err">
                    <i class="fas fa-clock"></i>
                    <p>QR hết hạn.<br>Nhấn <strong>Làm mới</strong> để tạo lại.</p>
                </div>`;
        }
    }, 1000);

    // Auto-poll mỗi 5 giây
    pollTimer = setInterval(async () => {
        try {
            const r  = await fetch(`${API}?action=check_status&invoice_id=${d.invoice_id}`);
            const pd = await r.json();
            if (pd.status === 'Paid') {
                clearInterval(qrTimer);
                clearInterval(pollTimer);
                document.getElementById('qrStatusRow').innerHTML   = '<i class="fas fa-check-circle" style="color:#4ade80"></i> Thanh toán thành công!';
                document.getElementById('qrStatusRow').style.color = '#4ade80';
                setTimeout(() => {
                    closeQR();
                    openSuccess(selectedPlan?.plan_name);
                }, 1500);
            }
        } catch(e) { /* bỏ qua lỗi mạng tạm thời */ }
    }, 5000);

    document.getElementById('qrModal').classList.add('active');
}

function updateQRCountdown() {
    const m  = Math.floor(countdown / 60).toString().padStart(2, '0');
    const s  = (countdown % 60).toString().padStart(2, '0');
    const el = document.getElementById('qrCountdown');
    if (el) {
        el.textContent = `${m}:${s}`;
        el.style.color = countdown <= 60 ? '#f87171' : '';
    }
}

function closeQR() {
    clearInterval(qrTimer);
    clearInterval(pollTimer);
    document.getElementById('qrModal').classList.remove('active');
}

function refreshQR() {
    if (!currentInvoiceId) return;
    const amountEl = document.getElementById('qrAmount');
    const amount   = amountEl ? amountEl.textContent.replace(/[^\d]/g, '') : 0;
    const url = `https://img.vietqr.io/image/MB-0981015808-compact2.png`
              + `?amount=${amount}&addInfo=ELITEGYM+HD${currentInvoiceId}&accountName=PHAM+VAN+HOANG`;

    document.getElementById('qrImgArea').innerHTML = `<div class="qr-loading-spin"><i class="fas fa-spinner fa-spin"></i></div>`;
    countdown = 300;
    updateQRCountdown();

    setTimeout(() => {
        document.getElementById('qrImgArea').innerHTML = `<img src="${url}" alt="QR" class="qr-img"/>`;
    }, 600);
}

async function confirmPayment() {
    if (!currentInvoiceId) return;
    const btn = document.querySelector('.btn-qr-confirm');
    if (btn) btn.disabled = true;

    const body = new FormData();
    body.append('action',     'confirm_payment');
    body.append('invoice_id', currentInvoiceId);
    body.append('method',     'Chuyển khoản');

    try {
        const res = await fetch(API, { method: 'POST', body });
        const d   = await res.json();
        if (d.success) {
            clearInterval(qrTimer);
            clearInterval(pollTimer);
            closeQR();
            openSuccess(selectedPlan?.plan_name);
        } else {
            showToast(d.message || 'Lỗi xác nhận', 'error');
            if (btn) btn.disabled = false;
        }
    } catch(e) {
        showToast('Lỗi kết nối', 'error');
        if (btn) btn.disabled = false;
    }
}

// ══════════════════════════════════════════════════
// SUCCESS MODAL
// ══════════════════════════════════════════════════
function openSuccess(planName) {
    const msgEl = document.getElementById('successMsg');
    if (msgEl) msgEl.textContent = `Gói "${planName || 'tập'}" đã được kích hoạt thành công!`;
    document.getElementById('successModal').classList.add('active');
}
