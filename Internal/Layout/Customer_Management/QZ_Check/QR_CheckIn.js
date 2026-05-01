/**
 * QR_CheckIn.js — Quét mã QR check-in · Elite Gym
 * Hỗ trợ: Camera điện thoại (trước/sau), desktop webcam, nhập thủ công
 * Yêu cầu: jsQR, QRious, Customer_Management.js
 */

// Paths are relative to the page (Customer_Management.php), NOT this JS file.
// This JS file lives in QZ_Check/ but fetch() resolves from the page's directory.
const QR_API    = 'QZ_Check/QR_CheckIn_function.php';
const QR_BRIDGE = 'QZ_Check/qr_bridge.php';

// ── Bridge / Phone mode state ──
let bridgePollTimer = null;
let bridgeLastTs    = 0;
let phoneModeActive = false;

// ── State ──
let qrStream       = null;
let qrScanActive   = false;
let qrAnimFrame    = null;
let lastScanned    = null;
let qrResultData   = null;
let todayLog       = [];
let qrCodeModal_id = null;
let currentFacing  = 'environment'; // 'environment' = camera sau, 'user' = camera trước
let availableCams  = [];

const el = id => document.getElementById(id);

// ===================================================
// INIT
// ===================================================
function initQRCheckIn() {
    const filterBar = document.querySelector('.filter-bar');
    if (filterBar) {
        const btn = document.createElement('button');
        btn.className = 'btn-qr-scan';
        btn.innerHTML = `<span class="qr-pulse"></span><i class="fas fa-qrcode"></i> Quét QR Check-in`;
        btn.onclick   = openQRModal;
        filterBar.appendChild(btn);
    }
    document.body.insertAdjacentHTML('beforeend', buildScannerModal());
    document.body.insertAdjacentHTML('beforeend', buildQRCodeModal());
}

// ===================================================
// BUILD MODAL HTML
// ===================================================
function buildScannerModal() {
    return `
<div class="modal-overlay" id="qrModal">
  <div class="modal qr-modal-box">

    <!-- Header -->
    <div class="modal-header qr-modal-header">
      <h3><i class="fas fa-qrcode"></i> QUÉT MÃ QR CHECK-IN</h3>
      <div style="display:flex;gap:8px;align-items:center">
        <button class="btn-cam-switch" id="btnCamSwitch" onclick="switchCamera()" title="Đổi camera trước/sau" style="display:none">
          <i class="fas fa-camera-rotate"></i>
        </button>
        <button id="btnPhoneMode" onclick="togglePhoneMode()" style="display:flex;align-items:center;gap:6px;padding:7px 12px;background:rgba(212,160,23,0.1);border:1px solid rgba(212,160,23,0.3);border-radius:8px;color:#d4a017;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;">
          <i class="fas fa-mobile-alt"></i> Camera ĐT
        </button>
        <button class="btn-close" onclick="closeQRModal()"><i class="fas fa-times"></i></button>
      </div>
    </div>

    <!-- HTTPS warning -->
    <div class="cam-https-warn" id="httpsWarn" style="display:none">
      <i class="fas fa-lock-open"></i>
      <span>Camera cần kết nối <strong>HTTPS</strong>. Dùng <strong>ngrok</strong> (https://...) hoặc nhập ID thủ công bên dưới.</span>
    </div>

    <!-- Phone mode panel -->
    <div id="phoneModePanel" style="display:none;padding:14px 18px;background:rgba(212,160,23,0.05);border-bottom:1px solid rgba(212,160,23,0.15);">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <i class="fas fa-mobile-alt" style="color:#d4a017;font-size:18px;flex-shrink:0;"></i>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:700;color:#fff;">Mở link này trên điện thoại để quét QR:</div>
        </div>
        <div id="bridgeStatusDot" style="width:10px;height:10px;border-radius:50%;background:#f59e0b;box-shadow:0 0 6px rgba(245,158,11,0.5);flex-shrink:0;"></div>
      </div>
      <div style="background:rgba(0,0,0,0.4);border-radius:8px;padding:9px 12px;font-family:monospace;font-size:11px;color:#d4a017;word-break:break-all;margin-bottom:10px;" id="phoneLinkDisplay">Đang lấy link...</div>
      <div style="display:flex;gap:8px;">
        <button onclick="copyPhoneLink()" style="flex:1;padding:9px;background:rgba(212,160,23,0.12);border:1px solid rgba(212,160,23,0.3);border-radius:8px;color:#d4a017;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;">
          <i class="fas fa-copy"></i> Sao chép link
        </button>
        <div id="bridgeStatusText" style="flex:1;display:flex;align-items:center;justify-content:center;font-size:12px;color:rgba(255,255,255,0.4);gap:6px;">
          <i class="fas fa-circle-notch fa-spin"></i> Chờ điện thoại quét...
        </div>
      </div>
    </div>

    <!-- Camera view -->
    <div class="qr-scanner-wrap" id="qrScannerWrap">
      <video id="qrVideo" autoplay muted playsinline webkit-playsinline></video>
      <canvas id="qrCanvas"></canvas>

      <div class="scan-overlay"></div>

      <div class="scan-frame" id="scanFrame">
        <div class="scan-frame-inner">
          <span class="scan-corner-br"></span>
          <span class="scan-corner-bl"></span>
          <div class="scan-line" id="scanLine"></div>
        </div>
      </div>

      <div class="scan-status" id="scanStatus">Đưa mã QR vào khung ngắm</div>
      <div class="scan-flash" id="scanFlash"></div>

      <div class="qr-loading" id="qrLoadingOverlay">
        <i class="fas fa-spinner fa-spin"></i>
        <span id="qrLoadingText">Đang tải thông tin...</span>
      </div>

      <div class="checkin-success-overlay" id="successOverlay">
        <div class="success-circle"><i class="fas fa-check"></i></div>
        <div class="success-text" id="successText">CHECK-IN THÀNH CÔNG!</div>
        <div class="success-subtext" id="successSubtext"></div>
      </div>

      <!-- Camera error -->
      <div class="cam-error" id="camError" style="display:none">
        <i class="fas fa-video-slash"></i>
        <p id="camErrorMsg">Không thể mở camera</p>
        <small>Cho phép quyền camera hoặc dùng nhập ID bên dưới</small>
        <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;justify-content:center">
          <button class="btn-primary" style="font-size:13px;padding:9px 18px" onclick="startCamera()">
            <i class="fas fa-redo"></i> Thử lại
          </button>
          <button onclick="tryCameraFallback()" style="font-size:13px;padding:9px 18px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);border-radius:10px;color:rgba(255,255,255,0.8);cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px">
            <i class="fas fa-camera"></i> Dùng camera trước
          </button>
        </div>
      </div>
    </div>

    <!-- Bottom panel -->
    <div class="qr-bottom">

      <div class="manual-input-row">
        <label><i class="fas fa-keyboard" style="color:#d4a017;margin-right:4px"></i>ID thủ công:</label>
        <input type="number" id="qrManualInput" placeholder="Nhập Customer ID..." min="1"
               onkeydown="if(event.key==='Enter') lookupManual()"/>
        <button class="btn-manual-lookup" onclick="lookupManual()">
          <i class="fas fa-search"></i> Tìm
        </button>
      </div>

      <div class="qr-result" id="qrResult">
        <div class="result-header">
          <div class="result-avatar" id="resAvatar">?</div>
          <div class="result-name-wrap">
            <div class="result-name" id="resName">—</div>
            <div class="result-id"   id="resId">ID: —</div>
          </div>
          <div class="result-status-badge rsb--none" id="resPkgBadge">Chưa có gói</div>
        </div>
        <div class="result-info-grid">
          <div class="ri-item"><div class="ri-label"><i class="fas fa-phone"></i> SĐT</div><div class="ri-val" id="resPhone">—</div></div>
          <div class="ri-item"><div class="ri-label"><i class="fas fa-venus-mars"></i> Giới tính</div><div class="ri-val" id="resGender">—</div></div>
          <div class="ri-item"><div class="ri-label"><i class="fas fa-dumbbell"></i> Gói tập</div><div class="ri-val highlight" id="resPkg">—</div></div>
          <div class="ri-item"><div class="ri-label"><i class="fas fa-calendar-xmark"></i> Hết hạn</div><div class="ri-val" id="resExpiry">—</div></div>
          <div class="ri-item"><div class="ri-label"><i class="fas fa-clock"></i> Check-in gần nhất</div><div class="ri-val" id="resLastIn">—</div></div>
          <div class="ri-item"><div class="ri-label"><i class="fas fa-door-open"></i> Check-out gần nhất</div><div class="ri-val" id="resLastOut">—</div></div>
        </div>

        <!-- Lịch tập hôm nay -->
        <div id="resScheduleWrap" style="display:none;margin:10px 0 4px;border-top:1px solid rgba(212,160,23,0.15);padding-top:10px;">
          <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,0.35);letter-spacing:1px;text-transform:uppercase;margin-bottom:8px;">
            <i class="fas fa-calendar-day" style="color:#d4a017;margin-right:5px;"></i>Lịch tập hôm nay
          </div>
          <div id="resScheduleList"></div>
        </div>

        <div class="result-actions">
          <button class="btn-checkin-confirm"  id="btnDoCheckIn"  onclick="doCheckIn()"><i class="fas fa-sign-in-alt"></i> CHECK IN</button>
          <button class="btn-checkout-confirm" id="btnDoCheckOut" onclick="doCheckOut()"><i class="fas fa-sign-out-alt"></i> CHECK OUT</button>
          <button class="btn-scan-again" onclick="resetScanResult()"><i class="fas fa-redo"></i> Quét lại</button>
        </div>
      </div>

      <div class="checkin-log-header">
        <div class="checkin-log-title"><i class="fas fa-history"></i> Lịch sử hôm nay</div>
        <span class="checkin-log-count" id="logCount">0 lượt</span>
      </div>
      <div class="checkin-log-list" id="checkinLogList">
        <div style="text-align:center;font-size:12px;color:rgba(255,255,255,0.2);padding:16px 0">
          Chưa có check-in trong phiên này
        </div>
      </div>

    </div>
  </div>
</div>`;
}

/* ── CSS lịch tập được inject động ── */
(function injectScheduleCSS() {
    if (document.getElementById('qr-schedule-style')) return;
    const s = document.createElement('style');
    s.id = 'qr-schedule-style';
    s.textContent = `
.sch-item {
    display:flex;justify-content:space-between;align-items:center;
    padding:8px 10px;border-radius:9px;margin-bottom:6px;
    background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);
    font-size:12px;gap:8px;
}
.sch-item.sch-ongoing  { background:rgba(34,197,94,0.08); border-color:rgba(34,197,94,0.25); }
.sch-item.sch-upcoming { background:rgba(212,160,23,0.06); border-color:rgba(212,160,23,0.2); }
.sch-item.sch-done     { background:rgba(255,255,255,0.02); border-color:rgba(255,255,255,0.05); opacity:.6; }
.sch-item-left { flex:1;min-width:0; }
.sch-name { font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px; }
.sch-meta { color:rgba(255,255,255,0.35);font-size:11px;margin-top:2px; }
.sch-item-right { text-align:right;flex-shrink:0; }
.sch-time { font-weight:700;color:#d4a017;font-size:12px; }
.sch-status { font-size:10px;margin-top:2px; }
.sch-ongoing  .sch-status { color:#22c55e; }
.sch-upcoming .sch-status { color:#f59e0b; }
.sch-done     .sch-status { color:rgba(255,255,255,0.3); }
    `;
    document.head.appendChild(s);
})();

function buildQRCodeModal() {
    return `
<div class="modal-overlay" id="qrCodeModal">
  <div class="modal" style="max-width:360px">
    <div class="modal-header">
      <h3><i class="fas fa-qrcode" style="color:#d4a017"></i> Mã QR Khách Hàng</h3>
      <button class="btn-close" onclick="el('qrCodeModal').classList.remove('active')"><i class="fas fa-times"></i></button>
    </div>
    <div class="qr-code-display">
      <canvas id="qrCodeCanvas" width="200" height="200"></canvas>
      <div class="qr-code-name" id="qrCodeName">—</div>
      <div class="qr-code-sub"  id="qrCodeSub">—</div>
      <button class="btn-download-qr" onclick="downloadQRCode()"><i class="fas fa-download"></i> Tải mã QR</button>
    </div>
  </div>
</div>`;
}

// ===================================================
// OPEN / CLOSE
// ===================================================
function openQRModal() {
    el('qrModal').classList.add('active');
    resetScanResult();
    checkAndStartCamera();
}

function closeQRModal() {
    el('qrModal').classList.remove('active');
    stopCamera();
}

// ===================================================
// CAMERA — kiểm tra trước khi mở
// ===================================================
async function checkAndStartCamera() {
    // Kiểm tra HTTPS (bắt buộc để getUserMedia hoạt động trên trình duyệt)
    // https: bao gồm cả ngrok, vercel, hoặc bất kỳ domain HTTPS nào
    const isSecure = location.protocol === 'https:'
                  || location.hostname === 'localhost'
                  || location.hostname === '127.0.0.1'
                  || location.hostname.startsWith('192.168.')
                  || location.hostname.startsWith('10.')
                  || location.hostname.endsWith('.ngrok-free.dev')
                  || location.hostname.endsWith('.ngrok.io')
                  || location.hostname.endsWith('.ngrok.app');

    if (!isSecure) {
        el('httpsWarn').style.display  = 'flex';
        el('qrScannerWrap').style.display = 'none';
        return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        showCamError('Trình duyệt không hỗ trợ camera. Hãy dùng Chrome hoặc Safari mới nhất.');
        return;
    }

    // Liệt kê camera để biết số lượng (hiện nút đổi camera nếu ≥ 2)
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        availableCams = devices.filter(d => d.kind === 'videoinput');
        if (availableCams.length >= 2) {
            el('btnCamSwitch').style.display = 'flex';
        }
    } catch (_) {}

    await startCamera();
}

async function startCamera() {
    el('camError').style.display   = 'none';
    el('qrVideo').style.display    = 'block';
    el('scanFrame').style.display  = 'flex';
    el('scanStatus').textContent   = 'Đang khởi động camera...';

    stopCamera(); // dừng stream cũ

    try {
        qrStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: currentFacing },
                width:      { ideal: 1280 },
                height:     { ideal: 720 }
            },
            audio: false
        });

        const video = el('qrVideo');
        video.srcObject = qrStream;
        // Một số trình duyệt mobile cần set attribute trực tiếp
        video.setAttribute('autoplay',    '');
        video.setAttribute('muted',       '');
        video.setAttribute('playsinline', '');
        await video.play().catch(() => {});

        qrScanActive = true;
        lastScanned  = null;
        el('scanStatus').textContent = 'Đưa mã QR vào khung ngắm';
        requestAnimationFrame(scanFrame);

    } catch (err) {
        console.warn('[QR Camera]', err.name, err.message);

        // Nếu lỗi do constraints quá chặt → thử lại constraint đơn giản
        if (err.name === 'OverconstrainedError' || err.name === 'ConstraintNotSatisfiedError') {
            return startCameraSimple();
        }

        let msg = 'Không thể mở camera.';
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            msg = '🔒 Bị từ chối quyền camera.\n\nCách cấp quyền:\n• Chrome Android: nhấn biểu tượng 🔒 trên thanh URL → Camera → Cho phép\n• Safari iOS: Cài đặt → Safari → Camera → Cho phép\n\n⚠️ Nếu dùng qua ngrok: mở URL bằng trình duyệt mặc định (không dùng in-app browser).';
        } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
            msg = 'Không tìm thấy camera trên thiết bị này.';
        } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
            msg = 'Camera đang bị ứng dụng khác chiếm. Đóng app đó và thử lại.';
        } else if (err.name === 'AbortError') {
            msg = 'Camera bị ngắt. Thử lại.';
        }

        showCamError(msg);
    }
}

// Fallback: constraint tối giản (không chỉ định facing/resolution)
async function startCameraSimple() {
    try {
        qrStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        const video = el('qrVideo');
        video.srcObject = qrStream;
        video.setAttribute('playsinline', '');
        await video.play().catch(() => {});
        qrScanActive = true;
        lastScanned  = null;
        el('camError').style.display  = 'none';
        el('qrVideo').style.display   = 'block';
        el('scanFrame').style.display = 'flex';
        el('scanStatus').textContent  = 'Đưa mã QR vào khung ngắm';
        requestAnimationFrame(scanFrame);
    } catch (err) {
        showCamError('Không thể mở camera: ' + err.message);
    }
}

async function tryCameraFallback() {
    currentFacing = 'user';
    await startCamera();
}

async function switchCamera() {
    currentFacing = (currentFacing === 'environment') ? 'user' : 'environment';
    const btn = el('btnCamSwitch');
    if (btn) { btn.style.opacity = '0.5'; btn.style.pointerEvents = 'none'; }
    await startCamera();
    if (btn) { btn.style.opacity = '1'; btn.style.pointerEvents = ''; }
}

function showCamError(msg) {
    el('camError').style.display  = 'flex';
    el('qrVideo').style.display   = 'none';
    el('scanFrame').style.display = 'none';
    el('camErrorMsg').innerHTML   = msg.replace(/\n/g, '<br>');
}

function stopCamera() {
    qrScanActive = false;
    if (qrAnimFrame) { cancelAnimationFrame(qrAnimFrame); qrAnimFrame = null; }
    if (qrStream) { qrStream.getTracks().forEach(t => t.stop()); qrStream = null; }
}

// ===================================================
// SCAN LOOP
// ===================================================
function scanFrame() {
    if (!qrScanActive) return;

    const video  = el('qrVideo');
    const canvas = el('qrCanvas');

    if (video.readyState >= video.HAVE_ENOUGH_DATA && video.videoWidth > 0) {
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

        if (typeof jsQR === 'function') {
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: 'dontInvert'
            });
            if (code && code.data && code.data !== lastScanned) {
                lastScanned = code.data;
                handleQRCode(code.data);
                return;
            }
        }
    }

    qrAnimFrame = requestAnimationFrame(scanFrame);
}

// ===================================================
// HANDLE QR DATA
// ===================================================
function handleQRCode(data) {
    let customerId = null;
    const match = data.match(/ELITEGYM_CID_(\d+)/);
    if (match) {
        customerId = parseInt(match[1]);
    } else if (/^\d+$/.test(data.trim())) {
        customerId = parseInt(data.trim());
    }

    if (!customerId) {
        flashScanStatus('QR không hợp lệ — thử lại', 'error');
        setTimeout(() => { lastScanned = null; resumeScan(); }, 1500);
        return;
    }

    triggerFlash();
    flashScanStatus('Đã nhận mã — Đang tải...', 'found');
    qrScanActive = false;
    fetchCustomerForCheckin(customerId);
}

function flashScanStatus(msg, type) {
    const s = el('scanStatus');
    s.textContent = msg;
    s.className   = 'scan-status ' + (type || '');
}

function triggerFlash() {
    const f = el('scanFlash');
    f.classList.add('active');
    setTimeout(() => f.classList.remove('active'), 200);
}

// ===================================================
// MANUAL LOOKUP
// ===================================================
function lookupManual() {
    const val = parseInt(el('qrManualInput').value);
    if (!val || val < 1) { showToast('Nhập Customer ID hợp lệ', 'warning'); return; }
    fetchCustomerForCheckin(val);
}

// ===================================================
// FETCH CUSTOMER
// ===================================================
// ── Ngrok bypass header (không ảnh hưởng khi chạy local) ──
const FETCH_HEADERS = { 'ngrok-skip-browser-warning': 'true' };

async function fetchCustomerForCheckin(customerId) {
    showQRLoading(true, 'Đang tải thông tin...');
    try {
        const res = await fetch(`${QR_API}?action=get_checkin_info&id=${customerId}`, {
            headers: FETCH_HEADERS
        });
        const d   = await res.json();
        if (!d.success) {
            showToast(d.message || 'Không tìm thấy khách hàng', 'error');
            flashScanStatus('Không tìm thấy — quét lại', 'error');
            resumeScan();
            return;
        }
        qrResultData = d;
        renderResult(d);
    } catch(e) {
        showToast('Lỗi kết nối server', 'error');
        resumeScan();
    } finally {
        showQRLoading(false);
    }
}

function resumeScan() {
    lastScanned  = null;
    qrScanActive = true;
    qrAnimFrame  = requestAnimationFrame(scanFrame);
}

// ===================================================
// RENDER RESULT
// ===================================================
function renderResult(d) {
    const c = d.customer;
    const initials = (c.full_name || '?').split(' ').slice(-2).map(w => w[0]).join('').toUpperCase();
    el('resAvatar').textContent = initials;
    el('resName').textContent   = c.full_name || '—';
    el('resId').textContent     = `ID: ${c.customer_id}`;
    el('resPhone').textContent  = c.phone  || '—';
    el('resGender').textContent = c.gender || '—';
    el('resPkg').textContent    = d.pkg_name || 'Chưa đăng ký';

    const badge = el('resPkgBadge');
    const statusMap = {
        active:   ['rsb--active',   '<i class="fas fa-circle-check"></i> Còn hiệu lực'],
        expiring: ['rsb--expiring', '<i class="fas fa-clock"></i> Sắp hết hạn'],
        expired:  ['rsb--expired',  '<i class="fas fa-circle-xmark"></i> Hết hạn'],
        none:     ['rsb--none',     '<i class="fas fa-ban"></i> Chưa có gói'],
    };
    const [cls, html] = statusMap[d.pkg_status] || statusMap.none;
    badge.className = `result-status-badge ${cls}`;
    badge.innerHTML = html;

    const expEl = el('resExpiry');
    if (d.pkg_end) {
        const days = Math.ceil((new Date(d.pkg_end) - new Date()) / 86400000);
        expEl.textContent = formatDate(d.pkg_end);
        expEl.className   = 'ri-val ' + (days < 0 ? 'danger' : days <= 7 ? 'warning' : 'success');
    } else { expEl.textContent = '—'; expEl.className = 'ri-val'; }

    el('resLastIn').textContent  = d.last_checkin  ? formatDateTime(d.last_checkin)  : '—';
    el('resLastOut').textContent = d.last_checkout ? formatDateTime(d.last_checkout) : '—';

    // ── Check-in chỉ được phép khi đang trong khung giờ lịch tập ──
    const now = new Date();
    const hasOngoingClass = (d.today_classes || []).some(cls => {
        const start = new Date(cls.start_time);
        const end   = new Date(cls.end_time);
        return now >= start && now <= end;
    });
    const pkgInvalid = (d.pkg_status === 'none' || d.pkg_status === 'expired');

    // Disable CHECK IN nếu: gói hết hạn, hoặc không có lớp đang diễn ra, hoặc đang trong gym rồi
    el('btnDoCheckIn').disabled  = pkgInvalid || !hasOngoingClass || d.currently_in;
    el('btnDoCheckOut').disabled = !d.currently_in;

    // Tooltip giải thích lý do disable
    const btnIn = el('btnDoCheckIn');
    if (pkgInvalid) {
        btnIn.title = 'Gói tập đã hết hạn hoặc chưa đăng ký';
    } else if (d.currently_in) {
        btnIn.title = 'Khách đang trong gym';
    } else if (!hasOngoingClass) {
        const upcoming = (d.today_classes || []).filter(cls => new Date(cls.start_time) > now);
        if (upcoming.length > 0) {
            const nextTime = formatTimeStr(upcoming[0].start_time);
            btnIn.title = `Chưa đến giờ tập — lớp bắt đầu lúc ${nextTime}`;
        } else if ((d.today_classes || []).length > 0) {
            btnIn.title = 'Tất cả lớp hôm nay đã kết thúc';
        } else {
            btnIn.title = 'Không có lịch tập hôm nay';
        }
    } else {
        btnIn.title = '';
    }

    // ── Hiển thị lịch tập hôm nay ──────────────────────────
    const schedWrap = el('resScheduleWrap');
    const schedList = el('resScheduleList');
    if (d.today_classes && d.today_classes.length > 0) {
        schedWrap.style.display = 'block';
        schedList.innerHTML = d.today_classes.map(cls => {
            const now = new Date();
            const start = new Date(cls.start_time);
            const end   = new Date(cls.end_time);
            let statusCls = 'sch-upcoming';
            let statusTxt = 'Sắp diễn ra';
            if (now >= start && now <= end) { statusCls = 'sch-ongoing';  statusTxt = 'Đang diễn ra'; }
            if (now > end)                  { statusCls = 'sch-done';     statusTxt = 'Đã kết thúc'; }
            return `<div class="sch-item ${statusCls}">
              <div class="sch-item-left">
                <div class="sch-name">${cls.class_name}</div>
                <div class="sch-meta"><i class="fas fa-door-open"></i> ${cls.room_name} &nbsp;|&nbsp; <i class="fas fa-user-tie"></i> ${cls.trainer_name || '—'}</div>
              </div>
              <div class="sch-item-right">
                <div class="sch-time">${formatTimeStr(cls.start_time)}–${formatTimeStr(cls.end_time)}</div>
                <div class="sch-status">${statusTxt}</div>
              </div>
            </div>`;
        }).join('');
    } else {
        schedWrap.style.display = 'none';
        schedList.innerHTML = '';
    }

    el('qrResult').classList.add('visible');
    flashScanStatus('Tìm thấy: ' + c.full_name, 'found');
}

function formatTimeStr(dtStr) {
    if (!dtStr) return '—';
    const d = new Date(dtStr);
    return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
}

// ===================================================
// CHECK IN / OUT
// ===================================================

// Map lưu timer auto-checkout: { customer_id → timeoutId }
const autoCheckoutTimers = {};

async function doCheckIn()  { if (qrResultData) await sendCheckin('checkin'); }
async function doCheckOut() { if (qrResultData) await sendCheckin('checkout'); }

async function sendCheckin(type) {
    const cid      = qrResultData.customer.customer_id;
    const classes  = qrResultData.today_classes || [];

    showQRLoading(true, type === 'checkin' ? 'Đang ghi check-in...' : 'Đang ghi check-out...');
    try {
        const fd = new FormData();
        fd.append('action', 'do_checkin');
        fd.append('customer_id', cid);
        fd.append('type', type);
        const res = await fetch(QR_API, { method: 'POST', body: fd, headers: FETCH_HEADERS });
        const d   = await res.json();
        showQRLoading(false);
        if (d.success) {
            const label = type === 'checkin' ? 'CHECK-IN THÀNH CÔNG!' : 'CHECK-OUT THÀNH CÔNG!';
            el('successText').textContent    = label;
            el('successSubtext').textContent = `${qrResultData.customer.full_name} · ${formatTime(new Date())}`;
            el('successOverlay').classList.add('show');
            addToLog(qrResultData.customer, type, new Date());
            showToast(d.message || label, 'success');

            // ── AUTO-CHECKOUT: đặt timer theo giờ kết thúc lớp ──
            if (type === 'checkin' && classes.length > 0) {
                // Hủy timer cũ nếu có
                if (autoCheckoutTimers[cid]) clearTimeout(autoCheckoutTimers[cid]);

                // Tìm lớp đang diễn ra hoặc sắp diễn ra gần nhất
                const now = Date.now();
                let targetClass = null;
                let minDiff = Infinity;
                for (const cls of classes) {
                    const endMs = new Date(cls.end_time).getTime();
                    if (endMs > now) {
                        const diff = endMs - now;
                        if (diff < minDiff) { minDiff = diff; targetClass = cls; }
                    }
                }

                if (targetClass) {
                    const delayMs  = new Date(targetClass.end_time).getTime() - now;
                    const endLabel = formatTimeStr(targetClass.end_time);
                    console.log(`[AutoCheckout] ${qrResultData.customer.full_name} → checkout lúc ${endLabel} (sau ${Math.round(delayMs/60000)} phút)`);

                    autoCheckoutTimers[cid] = setTimeout(async () => {
                        console.log(`[AutoCheckout] Tự động checkout ${qrResultData?.customer?.full_name || 'CID:'+cid}`);
                        try {
                            const fd2 = new FormData();
                            fd2.append('action', 'do_checkin');
                            fd2.append('customer_id', cid);
                            fd2.append('type', 'checkout');
                            const r2 = await fetch(QR_API, { method: 'POST', body: fd2, headers: FETCH_HEADERS });
                            const d2 = await r2.json();
                            if (d2.success) {
                                showToast(`⏰ Tự động check-out: ${d2.customer?.full_name || 'ID:'+cid} (${endLabel})`, 'success');
                                addToLog(d2.customer || { full_name: 'ID:'+cid, customer_id: cid }, 'checkout', new Date());
                            }
                        } catch(e) { console.error('[AutoCheckout] Lỗi:', e); }
                        delete autoCheckoutTimers[cid];
                    }, delayMs);
                }
            }

            // Hủy timer nếu khách tự checkout
            if (type === 'checkout' && autoCheckoutTimers[cid]) {
                clearTimeout(autoCheckoutTimers[cid]);
                delete autoCheckoutTimers[cid];
            }

            setTimeout(() => { el('successOverlay').classList.remove('show'); resetScanResult(); }, 2200);
        } else {
            showToast(d.message || 'Có lỗi xảy ra', 'error');
        }
    } catch(e) {
        showQRLoading(false);
        showToast('Lỗi kết nối server', 'error');
    }
}

// ===================================================
// RESET
// ===================================================
function resetScanResult() {
    qrResultData = null; lastScanned = null;
    el('qrResult').classList.remove('visible');
    el('qrManualInput').value = '';
    flashScanStatus('Đưa mã QR vào khung ngắm');
    resumeScan();
}
function showQRLoading(show, text) {
    el('qrLoadingOverlay').classList.toggle('show', show);
    if (text) el('qrLoadingText').textContent = text;
}

// ===================================================
// LOG
// ===================================================
function addToLog(customer, type, time) {
    const initials = (customer.full_name || '?').split(' ').slice(-2).map(w => w[0]).join('').toUpperCase();
    todayLog.unshift({ customer, type, time, initials });
    const list = el('checkinLogList');
    list.innerHTML = '';
    el('logCount').textContent = todayLog.length + ' lượt';
    todayLog.forEach(item => {
        const div = document.createElement('div');
        div.className = 'log-item';
        div.innerHTML = `
            <div class="log-avatar">${item.initials}</div>
            <div class="log-name">${item.customer.full_name}</div>
            <span class="log-type ${item.type === 'checkin' ? 'in' : 'out'}">${item.type === 'checkin' ? 'IN' : 'OUT'}</span>
            <div class="log-time">${formatTime(item.time)}</div>`;
        list.appendChild(div);
    });
}

// ===================================================
// QR CODE VIEWER
// ===================================================
function showQRCode(customerId, customerName, phone) {
    qrCodeModal_id = customerId;
    el('qrCodeName').textContent = customerName;
    el('qrCodeSub').textContent  = `ID: ${customerId}` + (phone ? ` · ${phone}` : '');
    const canvas = el('qrCodeCanvas');
    if (typeof QRious !== 'undefined') {
        new QRious({ element: canvas, value: `ELITEGYM_CID_${customerId}`, size: 200, level: 'H', background: '#ffffff', foreground: '#000000', padding: 10 });
    }
    el('qrCodeModal').classList.add('active');
}
function downloadQRCode() {
    const link = document.createElement('a');
    link.download = `QR_KhachHang_${qrCodeModal_id}.png`;
    link.href = el('qrCodeCanvas').toDataURL('image/png');
    link.click();
}

// ===================================================
// HELPERS
// ===================================================
function formatDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleDateString('vi-VN', { day:'2-digit', month:'2-digit', year:'numeric' });
}
function formatDateTime(str) {
    if (!str) return '—';
    return new Date(str).toLocaleString('vi-VN', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
}
function formatTime(d) {
    return d.toLocaleTimeString('vi-VN', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
}

// ===================================================
// PHONE MODE FUNCTIONS
// ===================================================

async function togglePhoneMode() {
    phoneModeActive = !phoneModeActive;
    const panel = el('phoneModePanel');
    const btn   = el('btnPhoneMode');

    if (phoneModeActive) {
        // Bật phone mode
        panel.style.display = 'block';
        btn.style.background = 'rgba(212,160,23,0.25)';
        btn.style.borderColor = 'rgba(212,160,23,0.6)';
        // Dừng camera máy tính
        stopCamera();
        el('qrScannerWrap').style.opacity = '0.25';
        el('scanStatus').textContent = 'Đang chờ điện thoại quét...';
        // Lấy link ngrok
        await loadPhoneLink();
        // Bắt đầu poll bridge
        startBridgePoll();
    } else {
        // Tắt phone mode
        panel.style.display = 'none';
        btn.style.background = 'rgba(212,160,23,0.1)';
        btn.style.borderColor = 'rgba(212,160,23,0.3)';
        stopBridgePoll();
        el('qrScannerWrap').style.opacity = '1';
        checkAndStartCamera();
    }
}

async function loadPhoneLink() {
    const linkEl = el('phoneLinkDisplay');

    // Tính đường dẫn tới thư mục QZ_Check (nơi chứa các file bridge)
    // QR_CheckIn.js nằm trong QZ_Check/, được load bởi trang ở Customer_Management/
    // Nên cần dùng đường dẫn tuyệt đối từ origin
    const pageBase   = location.pathname.replace(/[^\/]*$/, ''); // .../Customer_Management/
    const qzBase     = pageBase + 'QZ_Check/';                   // .../Customer_Management/QZ_Check/

    try {
        // Gọi get_ngrok_url.php trong QZ_Check/
        const r = await fetch(qzBase + 'get_ngrok_url.php', {
            headers: { 'ngrok-skip-browser-warning': 'true' }
        }).then(x => x.json());

        let phoneUrl;
        if (r.success && r.ngrok_url) {
            // Ngrok URL từ API: ghép với đường dẫn scanner
            phoneUrl = r.ngrok_url + qzBase + 'qr_phone_scanner.php';
        } else if (location.hostname.includes('ngrok')) {
            // Đang truy cập qua ngrok → dùng luôn origin hiện tại
            phoneUrl = location.origin + qzBase + 'qr_phone_scanner.php';
        } else {
            phoneUrl = null;
        }

        if (phoneUrl) {
            linkEl.textContent = phoneUrl;
            linkEl.dataset.link = phoneUrl;
        } else {
            linkEl.innerHTML = '<span style="color:#f87171;">Cần chạy ngrok! Lệnh: ngrok http --host-header=localhost 80</span>';
        }
    } catch(e) {
        if (location.hostname.includes('ngrok')) {
            const phoneUrl = location.origin + qzBase + 'qr_phone_scanner.php';
            linkEl.textContent = phoneUrl;
            linkEl.dataset.link = phoneUrl;
        } else {
            linkEl.innerHTML = '<span style="color:#f87171;">Không lấy được link. Chạy ngrok trước.</span>';
        }
    }
}

function copyPhoneLink() {
    const linkEl = el('phoneLinkDisplay');
    const link = linkEl.dataset.link || linkEl.textContent;
    if (!link || link.includes('Cần chạy')) { showToast('Chưa có link để sao chép', 'warning'); return; }
    navigator.clipboard.writeText(link).then(() => {
        showToast('✅ Đã sao chép link!', 'success');
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = link; document.body.appendChild(ta);
        ta.select(); document.execCommand('copy');
        document.body.removeChild(ta);
        showToast('✅ Đã sao chép link!', 'success');
    });
}

function startBridgePoll() {
    stopBridgePoll();
    bridgeLastTs = 0;
    setBridgeStatus('wait');
    bridgePollTimer = setInterval(async () => {
        try {
            const r = await fetch(QR_BRIDGE + '?action=poll&since=' + bridgeLastTs, {
                headers: { 'ngrok-skip-browser-warning': 'true' }
            }).then(x => x.json());
            if (r.success && r.qr_data) {
                bridgeLastTs = r.ts;
                setBridgeStatus('received');
                // Xử lý QR giống quét bình thường
                handleQRCode(r.qr_data);
                setTimeout(() => { if (phoneModeActive) setBridgeStatus('wait'); }, 3000);
            }
        } catch(e) {}
    }, 1200);
}

function stopBridgePoll() {
    if (bridgePollTimer) { clearInterval(bridgePollTimer); bridgePollTimer = null; }
}

function setBridgeStatus(state) {
    const dot  = el('bridgeStatusDot');
    const text = el('bridgeStatusText');
    if (!dot || !text) return;
    if (state === 'wait') {
        dot.style.background = '#f59e0b';
        dot.style.boxShadow  = '0 0 6px rgba(245,158,11,0.5)';
        text.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Chờ điện thoại quét...';
        text.style.color = 'rgba(255,255,255,0.4)';
    } else if (state === 'received') {
        dot.style.background = '#22c55e';
        dot.style.boxShadow  = '0 0 8px rgba(34,197,94,0.6)';
        text.innerHTML = '<i class="fas fa-check-circle"></i> Nhận được mã QR!';
        text.style.color = '#22c55e';
    }
}

// Override closeQRModal để dừng bridge poll
const _origCloseQRModal = closeQRModal;
closeQRModal = function() {
    stopBridgePoll();
    phoneModeActive = false;
    _origCloseQRModal();
};

document.addEventListener('DOMContentLoaded', initQRCheckIn);
