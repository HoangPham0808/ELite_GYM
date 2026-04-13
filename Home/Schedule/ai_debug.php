<?php
/**
 * ai_debug.php — Kiểm tra kết nối Anthropic API từ PHP server
 * Truy cập: https://yourdomain.com/Home/Schedule/ai_debug.php
 * XÓA FILE NÀY SAU KHI DEBUG XONG!
 */
session_start();

// Chỉ cho phép Customer đăng nhập (bảo mật cơ bản)
// Comment 3 dòng dưới nếu muốn test không cần đăng nhập
if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    die('<h3 style="color:red">⛔ Cần đăng nhập với role Customer</h3>');
}

header('Content-Type: text/plain; charset=utf-8');

// ── 1. Load API key (giống ai_proxy.php) ─────────────────────
$api_key = getenv('ANTHROPIC_API_KEY') ?: 'YOUR_ANTHROPIC_API_KEY_HERE';

echo "=== ANTHROPIC API DEBUG ===\n\n";

// ── 2. Kiểm tra API key ───────────────────────────────────────
echo "1. API Key: ";
if (!$api_key || $api_key === 'YOUR_ANTHROPIC_API_KEY_HERE') {
    echo "❌ CHƯA SET! Cần thêm ANTHROPIC_API_KEY vào biến môi trường.\n\n";
} else {
    echo "✅ Đã set (bắt đầu bằng: " . substr($api_key, 0, 15) . "...)\n\n";
}

// ── 3. Kiểm tra cURL có sẵn không ────────────────────────────
echo "2. cURL extension: ";
if (!function_exists('curl_init')) {
    echo "❌ KHÔNG CÓ! Cần bật extension=curl trong php.ini\n\n";
} else {
    echo "✅ OK\n\n";
}

// ── 4. Test DNS resolution ────────────────────────────────────
echo "3. DNS cho api.anthropic.com: ";
$ip = gethostbyname('api.anthropic.com');
if ($ip === 'api.anthropic.com') {
    echo "❌ KHÔNG PHÂN GIẢI ĐƯỢC! Server không có internet hoặc DNS bị block.\n\n";
} else {
    echo "✅ OK → IP: $ip\n\n";
}

// ── 5. Test HTTPS kết nối (không gọi API thật) ───────────────
echo "4. HTTPS đến api.anthropic.com:443: ";
$sock = @fsockopen('ssl://api.anthropic.com', 443, $errno, $errstr, 5);
if (!$sock) {
    echo "❌ KHÔNG KẾT NỐI ĐƯỢC! errno=$errno, $errstr\n   → Server bị firewall chặn outbound HTTPS.\n\n";
} else {
    fclose($sock);
    echo "✅ OK (port 443 mở)\n\n";
}

// ── 6. Gọi API thật với prompt nhỏ ───────────────────────────
echo "5. Gọi Anthropic API (non-stream, prompt nhỏ): ";

if (!$api_key || $api_key === 'YOUR_ANTHROPIC_API_KEY_HERE') {
    echo "⏭️  BỎ QUA (chưa có API key)\n\n";
} else {
    $payload = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 20,
        'messages'   => [['role' => 'user', 'content' => 'Say "OK" only']],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    $curlNo   = curl_errno($ch);
    curl_close($ch);

    if ($curlNo) {
        echo "❌ cURL error $curlNo: $curlErr\n\n";
    } elseif ($httpCode === 200) {
        $data = json_decode($resp, true);
        $text = $data['content'][0]['text'] ?? '(empty)';
        echo "✅ HTTP 200 — Response: \"$text\"\n\n";
    } elseif ($httpCode === 401) {
        echo "❌ HTTP 401 — API key không hợp lệ!\n   Raw: " . substr($resp, 0, 200) . "\n\n";
    } elseif ($httpCode === 429) {
        echo "⚠️  HTTP 429 — Rate limit / quota hết. Kiểm tra billing tại console.anthropic.com\n\n";
    } else {
        echo "❌ HTTP $httpCode\n   Raw: " . substr($resp, 0, 300) . "\n\n";
    }
}

// ── 7. Test streaming ─────────────────────────────────────────
echo "6. Test streaming (stream=true): ";

if (!$api_key || $api_key === 'YOUR_ANTHROPIC_API_KEY_HERE') {
    echo "⏭️  BỎ QUA (chưa có API key)\n\n";
} else {
    $payload = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 30,
        'stream'     => true,
        'messages'   => [['role' => 'user', 'content' => 'Count 1 to 5']],
    ]);

    $tokens = [];
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use (&$tokens) {
            // Tìm text delta
            preg_match_all('/"text"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $chunk, $m);
            foreach ($m[1] as $t) {
                $tokens[] = json_decode('"' . $t . '"');
            }
            return strlen($chunk);
        },
    ]);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    $curlNo   = curl_errno($ch);
    curl_close($ch);

    if ($curlNo) {
        echo "❌ cURL error $curlNo: $curlErr\n\n";
    } elseif ($httpCode === 200 && count($tokens) > 0) {
        echo "✅ HTTP 200 — Nhận được " . count($tokens) . " tokens: \"" . implode('', $tokens) . "\"\n\n";
    } elseif ($httpCode === 200) {
        echo "⚠️  HTTP 200 nhưng không parse được tokens. Có thể SSL buffering issue.\n\n";
    } else {
        echo "❌ HTTP $httpCode, cURL#$curlNo: $curlErr\n\n";
    }
}

// ── 8. Kiểm tra ob_end_clean / flush support ─────────────────
echo "7. Output buffering level: " . ob_get_level() . " (lý tưởng là 0 hoặc 1)\n\n";

// ── Tóm tắt ──────────────────────────────────────────────────
echo "=== XONG ===\n";
echo "Nếu tất cả ✅ nhưng vẫn lỗi trong modal:\n";
echo "  → Kiểm tra Network tab (DevTools) khi bấm 'Tạo lịch': xem response của ai_proxy.php\n";
echo "  → Paste nội dung response vào đây để debug tiếp.\n";
echo "\n⚠️  XÓA FILE NÀY SAU KHI DEBUG XONG!\n";
?>
