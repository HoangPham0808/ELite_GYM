<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>📱 Quét QR — Elite Gym</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--gold:#d4a017;--gold2:#f0c040;--bg:#0a0a0a;--green:#22c55e;--red:#ef4444;}
body{background:var(--bg);color:#fff;font-family:'Barlow',sans-serif;min-height:100svh;display:flex;flex-direction:column;align-items:center;padding:16px;gap:14px;}

header{text-align:center;padding-top:8px;}
header h1{font-family:'Barlow Condensed',sans-serif;font-size:1.7rem;font-weight:800;color:var(--gold);letter-spacing:2px;}
header p{font-size:12px;color:rgba(255,255,255,0.35);margin-top:3px;}

/* Camera box */
.cam-wrap{
  position:relative;width:100%;max-width:400px;
  background:#000;border-radius:16px;overflow:hidden;
  border:2px solid rgba(212,160,23,0.25);
  aspect-ratio:1/1;
}
#scanVideo{width:100%;height:100%;object-fit:cover;display:block;}
#scanCanvas{display:none;}

/* Scan frame overlay */
.scan-frame{
  position:absolute;inset:0;pointer-events:none;
  display:flex;align-items:center;justify-content:center;
}
.scan-inner{
  width:65%;height:65%;position:relative;
}
.scan-inner::before,.scan-inner::after,
.sc-br,.sc-bl{
  content:'';position:absolute;
  width:28px;height:28px;
  border-color:var(--gold);border-style:solid;
}
.scan-inner::before{top:0;left:0;border-width:3px 0 0 3px;border-radius:4px 0 0 0;}
.scan-inner::after{top:0;right:0;border-width:3px 3px 0 0;border-radius:0 4px 0 0;}
.sc-br{bottom:0;right:0;border-width:0 3px 3px 0;border-radius:0 0 4px 0;}
.sc-bl{bottom:0;left:0;border-width:0 0 3px 3px;border-radius:0 0 0 4px;}
.scan-line{
  position:absolute;top:0;left:4px;right:4px;height:2px;
  background:linear-gradient(90deg,transparent,var(--gold),var(--gold2),var(--gold),transparent);
  animation:scanMove 1.8s ease-in-out infinite;
  box-shadow:0 0 10px rgba(212,160,23,0.7);
}
@keyframes scanMove{0%{top:4px;opacity:1}50%{top:calc(100% - 6px);opacity:.7}100%{top:4px;opacity:1}}

.scan-overlay{
  position:absolute;inset:0;
  background:radial-gradient(ellipse 65% 65% at center,transparent 45%,rgba(0,0,0,0.55) 55%);
  pointer-events:none;
}

/* Status */
.scan-status-bar{
  position:absolute;bottom:10px;left:0;right:0;text-align:center;
  font-size:13px;font-weight:600;color:rgba(255,255,255,0.7);
  text-shadow:0 1px 4px rgba(0,0,0,0.9);
}
.scan-status-bar.found{color:var(--green);}
.scan-status-bar.error{color:var(--red);}

/* Flash */
.scan-flash{position:absolute;inset:0;background:rgba(212,160,23,0.3);opacity:0;pointer-events:none;transition:opacity .08s;}
.scan-flash.on{opacity:1;}

/* Result card */
.result-card{
  width:100%;max-width:400px;
  background:#141414;border:1px solid rgba(212,160,23,0.25);
  border-radius:14px;overflow:hidden;
  display:none;animation:slideUp .25s ease;
}
.result-card.show{display:block;}
@keyframes slideUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

.result-top{display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.05);}
.result-avatar{
  width:46px;height:46px;border-radius:11px;flex-shrink:0;
  background:linear-gradient(135deg,var(--gold),var(--gold2));
  display:flex;align-items:center;justify-content:center;
  font-size:18px;font-weight:800;color:#000;font-family:'Barlow Condensed',sans-serif;
}
.result-name{font-size:16px;font-weight:800;color:#fff;}
.result-id{font-size:11px;color:rgba(255,255,255,0.3);font-family:monospace;margin-top:2px;}

.result-sending{
  padding:16px;text-align:center;
  font-size:13px;color:rgba(255,255,255,0.5);
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.result-sent{
  padding:14px 16px;
  display:flex;align-items:center;gap:10px;
  background:rgba(34,197,94,0.08);
  font-size:14px;font-weight:700;color:var(--green);
}

/* Start button */
.btn-start{
  width:100%;max-width:400px;
  padding:15px;border-radius:13px;border:none;
  background:linear-gradient(135deg,var(--gold),var(--gold2));
  color:#000;font-size:15px;font-weight:800;
  font-family:'Barlow Condensed',sans-serif;letter-spacing:1px;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;
  transition:all .2s;
}
.btn-start:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(212,160,23,0.35);}
.btn-start:disabled{opacity:.4;cursor:not-allowed;transform:none;}

.btn-flip{
  padding:11px 20px;border-radius:10px;
  background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);
  color:rgba(255,255,255,0.7);font-size:13px;font-weight:600;
  font-family:'Barlow',sans-serif;cursor:pointer;
  display:flex;align-items:center;gap:6px;transition:all .2s;
}
.btn-flip:disabled{opacity:.3;cursor:not-allowed;}

.controls-row{display:flex;gap:10px;width:100%;max-width:400px;}
.controls-row .btn-start{flex:1;}

/* Camera error */
.cam-error-box{
  width:100%;max-width:400px;padding:28px 20px;text-align:center;
  background:#141414;border:1px solid rgba(239,68,68,0.2);border-radius:14px;
  display:none;
}
.cam-error-box.show{display:block;}
.cam-error-box i{font-size:36px;color:rgba(239,68,68,0.4);margin-bottom:12px;}
.cam-error-box p{font-size:14px;color:rgba(255,255,255,0.6);line-height:1.6;}
</style>
</head>
<body>

<header>
  <h1>📱 QUÉT MÃ QR</h1>
  <p>Kết quả tự động gửi về máy tính</p>
</header>

<div class="cam-wrap" id="camWrap">
  <video id="scanVideo" autoplay muted playsinline webkit-playsinline></video>
  <canvas id="scanCanvas"></canvas>
  <div class="scan-overlay"></div>
  <div class="scan-frame">
    <div class="scan-inner">
      <span class="sc-br"></span><span class="sc-bl"></span>
      <div class="scan-line"></div>
    </div>
  </div>
  <div class="scan-status-bar" id="scanStatus">Nhấn Bắt đầu để quét</div>
  <div class="scan-flash" id="scanFlash"></div>
</div>

<div class="cam-error-box" id="camErrorBox">
  <i class="fas fa-video-slash"></i>
  <p id="camErrorMsg">Không thể mở camera</p>
</div>

<div class="result-card" id="resultCard">
  <div class="result-top">
    <div class="result-avatar" id="resAvatar">?</div>
    <div>
      <div class="result-name" id="resName">—</div>
      <div class="result-id" id="resIdText">—</div>
    </div>
  </div>
  <div class="result-sending" id="resSending">
    <i class="fas fa-spinner fa-spin"></i> Đang gửi về máy tính...
  </div>
  <div class="result-sent" id="resSent" style="display:none">
    <i class="fas fa-check-circle"></i> Đã gửi! Máy tính đang xử lý...
  </div>
</div>

<div class="controls-row">
  <button class="btn-start" id="btnStart" onclick="startScan()">
    <i class="fas fa-qrcode"></i> BẮT ĐẦU QUÉT
  </button>
  <button class="btn-flip" id="btnFlip" onclick="flipCam()" disabled>
    <i class="fas fa-camera-rotate"></i>
  </button>
</div>

<script>
// ── Config ──
// Bridge URL: thay bằng đường dẫn thực tế trên server của bạn
const BRIDGE = 'qr_bridge.php';

let stream = null;
let animFrame = null;
let scanning = false;
let lastScanned = null;
let facing = 'environment';

const v   = document.getElementById('scanVideo');
const c   = document.getElementById('scanCanvas');
const ctx = c.getContext('2d', { willReadFrequently: true });

function setStatus(msg, cls) {
  const el = document.getElementById('scanStatus');
  el.textContent = msg;
  el.className = 'scan-status-bar ' + (cls || '');
}

async function startScan() {
  document.getElementById('btnStart').disabled = true;
  document.getElementById('camErrorBox').classList.remove('show');
  setStatus('Đang khởi động camera...', '');

  if (stream) stream.getTracks().forEach(t => t.stop());

  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: facing }, width: { ideal: 1280 }, height: { ideal: 1280 } },
      audio: false
    });
    v.srcObject = stream;
    await v.play();
    scanning = true;
    lastScanned = null;
    document.getElementById('btnFlip').disabled = false;
    setStatus('Đưa mã QR vào khung ngắm', '');
    requestAnimationFrame(scanLoop);
  } catch(e) {
    document.getElementById('camErrorBox').classList.add('show');
    document.getElementById('camErrorMsg').textContent = 'Lỗi camera: ' + e.message;
    document.getElementById('btnStart').disabled = false;
  }
}

function scanLoop() {
  if (!scanning) return;
  if (v.readyState >= v.HAVE_ENOUGH_DATA && v.videoWidth > 0) {
    c.width  = v.videoWidth;
    c.height = v.videoHeight;
    ctx.drawImage(v, 0, 0, c.width, c.height);
    const imgData = ctx.getImageData(0, 0, c.width, c.height);
    if (typeof jsQR === 'function') {
      const code = jsQR(imgData.data, imgData.width, imgData.height, { inversionAttempts: 'dontInvert' });
      if (code && code.data && code.data !== lastScanned) {
        lastScanned = code.data;
        handleQR(code.data);
        return;
      }
    }
  }
  animFrame = requestAnimationFrame(scanLoop);
}

async function handleQR(data) {
  scanning = false;

  // Flash effect
  const flash = document.getElementById('scanFlash');
  flash.classList.add('on');
  setTimeout(() => flash.classList.remove('on'), 180);

  // Parse customer ID
  let cid = null;
  const m = data.match(/ELITEGYM_CID_(\d+)/);
  if (m) cid = parseInt(m[1]);
  else if (/^\d+$/.test(data.trim())) cid = parseInt(data.trim());

  if (!cid) {
    setStatus('QR không hợp lệ — thử lại', 'error');
    setTimeout(() => { lastScanned = null; scanning = true; requestAnimationFrame(scanLoop); }, 1500);
    return;
  }

  setStatus('✅ Đã nhận mã #' + cid, 'found');

  // Hiện result card
  const initials = 'ID';
  document.getElementById('resAvatar').textContent = '#' + cid;
  document.getElementById('resName').textContent = 'Customer #' + cid;
  document.getElementById('resIdText').textContent = 'QR: ' + data.substring(0, 30);
  document.getElementById('resSending').style.display = 'flex';
  document.getElementById('resSent').style.display = 'none';
  document.getElementById('resultCard').classList.add('show');

  // Gửi lên bridge
  try {
    await fetch(BRIDGE + '?action=push', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'ngrok-skip-browser-warning': 'true' },
      body: JSON.stringify({ qr_data: data })
    });
    document.getElementById('resSending').style.display = 'none';
    document.getElementById('resSent').style.display = 'flex';
  } catch(e) {
    setStatus('Lỗi gửi về máy tính', 'error');
  }

  // Sau 3s tự quét lại
  setTimeout(() => {
    document.getElementById('resultCard').classList.remove('show');
    lastScanned = null;
    scanning = true;
    requestAnimationFrame(scanLoop);
    setStatus('Đưa mã QR vào khung ngắm', '');
  }, 3000);
}

async function flipCam() {
  facing = facing === 'environment' ? 'user' : 'environment';
  await startScan();
}
</script>
<!-- jsQR -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
</body>
</html>
