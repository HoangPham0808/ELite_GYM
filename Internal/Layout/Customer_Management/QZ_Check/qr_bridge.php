<?php
/**
 * qr_bridge.php — Cầu nối giữa camera điện thoại và máy tính
 * Điện thoại POST mã QR lên đây → Máy tính poll để nhận
 * Đặt cùng thư mục với QR_CheckIn_function.php
 */
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('ngrok-skip-browser-warning: true');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, ngrok-skip-browser-warning');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$bridgeFile = __DIR__ . '/qr_bridge_store.json';

function readBridge($f) {
    if (!file_exists($f)) return ['qr_data' => null, 'ts' => 0, 'consumed' => true];
    return json_decode(file_get_contents($f), true) ?: ['qr_data' => null, 'ts' => 0, 'consumed' => true];
}
function writeBridge($f, $d) {
    file_put_contents($f, json_encode($d), LOCK_EX);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Điện thoại POST mã QR lên
if ($action === 'push') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $qr_data = trim($body['qr_data'] ?? $_POST['qr_data'] ?? '');
    if (!$qr_data) { echo json_encode(['success' => false, 'message' => 'No QR data']); exit; }
    writeBridge($bridgeFile, ['qr_data' => $qr_data, 'ts' => time(), 'consumed' => false]);
    echo json_encode(['success' => true, 'message' => 'QR received']);
    exit;
}

// Máy tính poll để nhận mã QR mới
if ($action === 'poll') {
    $since = intval($_GET['since'] ?? 0);
    $store = readBridge($bridgeFile);
    // Chỉ trả về nếu có dữ liệu mới và chưa được xử lý
    if (!$store['consumed'] && $store['ts'] > $since) {
        // Đánh dấu đã consumed
        $store['consumed'] = true;
        writeBridge($bridgeFile, $store);
        echo json_encode(['success' => true, 'qr_data' => $store['qr_data'], 'ts' => $store['ts']]);
    } else {
        echo json_encode(['success' => false, 'qr_data' => null]);
    }
    exit;
}

// Điện thoại kiểm tra đã được xử lý chưa
if ($action === 'check_consumed') {
    $store = readBridge($bridgeFile);
    echo json_encode(['consumed' => $store['consumed'] ?? true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
?>
