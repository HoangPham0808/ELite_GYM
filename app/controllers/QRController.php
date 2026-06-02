<?php

class QRController extends Controller
{
    private QRCheckin $qrModel;

    public function __construct()
    {
        $this->qrModel = new QRCheckin();
    }

    public function api(): void
    {
        ob_start(); // Buffer mọi output trước (tránh PHP warning làm hỏng JSON)

        // Cho phép credentials (cookie/session) qua ngrok
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, ngrok-skip-browser-warning');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        ensureSession(); // helper function từ session_helper.php
        ob_clean(); // Xóa mọi output PHP warning trước khi gửi JSON
        header('Content-Type: application/json; charset=utf-8');
        header('ngrok-skip-browser-warning: true');

        // Kiểm tra quyền: Admin hoặc Staff/Employee hoặc Referer từ cùng ngrok host
        $allowedRoles = ['Admin', 'Staff', 'Employee'];
        $hasSession = isset($_SESSION['role']) && in_array($_SESSION['role'], $allowedRoles);
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host    = $_SERVER['HTTP_HOST'] ?? '';
        $fromSameHost = strpos($referer, $host) !== false || strpos($referer, 'ngrok') !== false;

        if (!$hasSession && !$fromSameHost) {
            echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập — vui lòng đăng nhập lại']);
            exit;
        }

        $action = trim($_POST['action'] ?? $_GET['action'] ?? '');

        switch ($action) {
            case 'get_checkin_info':
                $id = intval($_GET['id'] ?? 0);
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
                    exit;
                }

                $customer = $this->qrModel->getCustomerInfo($id);
                if (!$customer) {
                    echo json_encode(['success' => false, 'message' => 'Không tìm thấy khách hàng #' . $id]);
                    exit;
                }

                $pkg = $this->qrModel->getActivePackage($id);
                $today = $this->qrModel->getTodayCheckinOut($id);

                // Khách có đang trong gym không?
                $currentlyIn = false;
                if (!empty($today['last_checkin'])) {
                    if (empty($today['last_checkout'])) {
                        $currentlyIn = true;
                    } else {
                        $currentlyIn = strtotime($today['last_checkin']) > strtotime($today['last_checkout']);
                    }
                }

                $today_classes = $this->qrModel->getTodayClasses($id);

                // Tự động checkout nếu lớp cuối kết thúc
                if ($currentlyIn && !empty($today_classes)) {
                    $ok = $this->qrModel->autoCheckoutIfFinished($id, $today_classes, $today['last_checkin'], $today['last_checkout']);
                    if ($ok) {
                        $currentlyIn = false;
                        // Cập nhật lại checkout mới
                        $latest_end = null;
                        foreach ($today_classes as $cls) {
                            if (!$latest_end || $cls['end_time'] > $latest_end) {
                                $latest_end = $cls['end_time'];
                            }
                        }
                        $today['last_checkout'] = $latest_end;
                    }
                }

                echo json_encode([
                    'success'       => true,
                    'customer'      => $customer,
                    'pkg_name'      => $pkg['plan_name']  ?? null,
                    'pkg_end'       => $pkg['end_date']   ?? null,
                    'pkg_status'    => $pkg['pkg_status'] ?? 'none',
                    'last_checkin'  => $today['last_checkin']  ?? null,
                    'last_checkout' => $today['last_checkout'] ?? null,
                    'currently_in'  => $currentlyIn,
                    'today_classes' => $today_classes,
                ]);
                break;

            case 'do_checkin':
                $customer_id = intval($_POST['customer_id'] ?? 0);
                $type        = trim($_POST['type'] ?? ''); // 'checkin' | 'checkout'
                $by          = intval($_SESSION['account_id'] ?? 0);

                if ($customer_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
                    exit;
                }
                if (!in_array($type, ['checkin', 'checkout'])) {
                    echo json_encode(['success' => false, 'message' => 'Loại không hợp lệ']);
                    exit;
                }

                $cust = $this->qrModel->getCustomerInfo($customer_id);
                if (!$cust) {
                    echo json_encode(['success' => false, 'message' => 'Không tìm thấy khách hàng']);
                    exit;
                }

                if ($type === 'checkin') {
                    // Check duplicate
                    if ($this->qrModel->hasUnresolvedCheckinToday($customer_id)) {
                        echo json_encode(['success' => false, 'message' => $cust['full_name'] . ' đã check-in và chưa check-out!']);
                        exit;
                    }

                    // Check package
                    if (!$this->qrModel->hasActivePackage($customer_id)) {
                        echo json_encode(['success' => false, 'message' => 'Gói tập đã hết hạn hoặc chưa đăng ký!']);
                        exit;
                    }

                    // Check ongoing class
                    $ongoingClass = $this->qrModel->getOngoingClass($customer_id);
                    if (!$ongoingClass) {
                        $next = $this->qrModel->getNextClass($customer_id);
                        if ($next) {
                            echo json_encode(['success' => false, 'message' => "Chưa đến giờ tập — lớp bắt đầu lúc {$next['start_hm']}"]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Không có lịch tập nào đang diễn ra hôm nay!']);
                        }
                        exit;
                    }
                } else {
                    // checkout - check if checked in today
                    if (!$this->qrModel->hasUnresolvedCheckinToday($customer_id)) {
                        echo json_encode(['success' => false, 'message' => $cust['full_name'] . ' chưa check-in hôm nay!']);
                        exit;
                    }
                }

                // Insert DB
                $ok = $this->qrModel->addCheckInRecord($customer_id, $type, $by);
                if (!$ok) {
                    echo json_encode(['success' => false, 'message' => 'Lỗi ghi DB']);
                    exit;
                }

                $label   = $type === 'checkin' ? 'Check-in' : 'Check-out';
                $timeStr = date('H:i:s');
                echo json_encode([
                    'success'  => true,
                    'message'  => "$label thành công cho {$cust['full_name']} lúc $timeStr",
                    'type'     => $type,
                    'time'     => $timeStr,
                    'customer' => $cust,
                ]);
                break;

            case 'today_stats':
                echo json_encode(array_merge(['success' => true], $this->qrModel->getTodayStats()));
                break;

            default:
                echo json_encode(['success' => false, 'message' => "Action '$action' không hợp lệ"]);
        }
    }

    public function ngrok(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('ngrok-skip-browser-warning: true');
        header('Access-Control-Allow-Origin: *');

        $ngrokUrl = null;
        $apiUrls = [
            'http://127.0.0.1:4040/api/tunnels',
            'http://localhost:4040/api/tunnels',
        ];

        foreach ($apiUrls as $apiUrl) {
            $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
            $raw = @file_get_contents($apiUrl, false, $ctx);
            if ($raw) {
                $data = json_decode($raw, true);
                $tunnels = $data['tunnels'] ?? [];
                foreach ($tunnels as $t) {
                    if (($t['proto'] ?? '') === 'https') {
                        $ngrokUrl = rtrim($t['public_url'], '/');
                        break 2;
                    }
                }
            }
        }

        if (!$ngrokUrl) {
            $forwardedHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '';
            $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https';
            if ($forwardedHost && strpos($forwardedHost, 'ngrok') !== false) {
                $ngrokUrl = $forwardedProto . '://' . $forwardedHost;
            }
        }

        if (!$ngrokUrl) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if (strpos($host, 'ngrok') !== false) {
                $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $ngrokUrl = $proto . '://' . $host;
            }
        }

        echo json_encode([
            'success'   => !empty($ngrokUrl),
            'ngrok_url' => $ngrokUrl,
            'message'   => $ngrokUrl ? 'Found' : 'ngrok URL not found — make sure ngrok is running'
        ]);
    }

    // QR bridge: điện thoại POST và máy tính poll mã QR
    public function bridge(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('ngrok-skip-browser-warning: true');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, ngrok-skip-browser-warning');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $storageDir = __DIR__ . '/../storage';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
        $bridgeFile = $storageDir . '/qr_bridge_store.json';

        // Đọc body 1 lần duy nhất
        $rawInput  = file_get_contents('php://input');
        $jsonBody  = $rawInput ? (json_decode($rawInput, true) ?? []) : [];

        $action = $_GET['action'] ?? $_POST['action'] ?? trim($jsonBody['action'] ?? '');

        if ($action === 'push') {
            $qr_data = trim($jsonBody['qr_data'] ?? $_POST['qr_data'] ?? $_GET['qr_data'] ?? '');
            if (!$qr_data) {
                echo json_encode(['success' => false, 'message' => 'No QR data']);
                exit;
            }
            file_put_contents($bridgeFile, json_encode(['qr_data' => $qr_data, 'ts' => time(), 'consumed' => false]), LOCK_EX);
            echo json_encode(['success' => true, 'message' => 'QR received']);
            exit;
        }

        if ($action === 'poll') {
            $since = intval($_GET['since'] ?? 0);
            $store = ['qr_data' => null, 'ts' => 0, 'consumed' => true];
            if (file_exists($bridgeFile)) {
                $store = json_decode(file_get_contents($bridgeFile), true) ?: $store;
            }

            if (!$store['consumed'] && $store['ts'] > $since) {
                $store['consumed'] = true;
                file_put_contents($bridgeFile, json_encode($store), LOCK_EX);
                echo json_encode(['success' => true, 'qr_data' => $store['qr_data'], 'ts' => $store['ts']]);
            } else {
                echo json_encode(['success' => false, 'qr_data' => null]);
            }
            exit;
        }

        if ($action === 'check_consumed') {
            $store = ['qr_data' => null, 'ts' => 0, 'consumed' => true];
            if (file_exists($bridgeFile)) {
                $store = json_decode(file_get_contents($bridgeFile), true) ?: $store;
            }
            echo json_encode(['consumed' => $store['consumed'] ?? true]);
            exit;
        }

        echo json_encode(['error' => 'Unknown action']);
    }

    // Giao diện web máy quét QR cho điện thoại di động
    public function scanner(): void
    {
        // Trả về trực tiếp giao diện quét QR với bridge URL được ánh xạ động tới route `/api/admin/qr/bridge`
        ?>
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
        .cam-wrap{
          position:relative;width:100%;max-width:400px;
          background:#000;border-radius:16px;overflow:hidden;
          border:2px solid rgba(212,160,23,0.25);
          aspect-ratio:1/1;
        }
        #scanVideo{width:100%;height:100%;object-fit:cover;display:block;}
        #scanCanvas{display:none;}
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
        .scan-status-bar{
          position:absolute;bottom:10px;left:0;right:0;text-align:center;
          font-size:13px;font-weight:600;color:rgba(255,255,255,0.7);
          text-shadow:0 1px 4px rgba(0,0,0,0.9);
        }
        .scan-status-bar.found{color:var(--green);}
        .scan-status-bar.error{color:var(--red);}
        .scan-flash{position:absolute;inset:0;background:rgba(212,160,23,0.3);opacity:0;pointer-events:none;transition:opacity .08s;}
        .scan-flash.on{opacity:1;}
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
        // Bridge URL — tự nhận base từ location hiện tại (ngrok hoặc localhost)
        (function() {
            var loc = window.location;
            // Lấy base path: bỏ /qr-scanner ở cuối
            var basePath = loc.pathname.replace(/\/qr-scanner\/?$/, '').replace(/\/index\.php$/, '');
            window.BRIDGE = loc.protocol + '//' + loc.host + basePath + '/api/admin/qr/bridge';
        })();
        const BRIDGE = window.BRIDGE;

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
          const flash = document.getElementById('scanFlash');
          flash.classList.add('on');
          setTimeout(() => flash.classList.remove('on'), 180);

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

          document.getElementById('resAvatar').textContent = '#' + cid;
          document.getElementById('resName').textContent = 'Customer #' + cid;
          document.getElementById('resIdText').textContent = 'QR: ' + data.substring(0, 30);
          document.getElementById('resSending').style.display = 'flex';
          document.getElementById('resSent').style.display = 'none';
          document.getElementById('resultCard').classList.add('show');

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
        <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
        </body>
        </html>
        <?php
    }
}
