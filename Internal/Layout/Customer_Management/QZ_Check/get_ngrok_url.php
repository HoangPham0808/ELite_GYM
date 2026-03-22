<?php
/**
 * get_ngrok_url.php — Lấy ngrok public URL từ server
 * Đặt cùng thư mục với qr_phone_scanner.php
 */
header('Content-Type: application/json; charset=utf-8');
header('ngrok-skip-browser-warning: true');
header('Access-Control-Allow-Origin: *');

$ngrokUrl = null;

// Cách 1: Đọc từ ngrok local API (port 4040)
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

// Cách 2: Lấy từ header X-Forwarded-Host (ngrok gửi kèm)
if (!$ngrokUrl) {
    $forwardedHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '';
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https';
    if ($forwardedHost && strpos($forwardedHost, 'ngrok') !== false) {
        $ngrokUrl = $forwardedProto . '://' . $forwardedHost;
    }
}

// Cách 3: Dùng HTTP_HOST nếu đang chạy trực tiếp trên ngrok
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
?>
