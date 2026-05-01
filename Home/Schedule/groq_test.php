<?php
/**
 * groq_test.php — Kiểm tra kết nối Groq API từ WAMP
 * Đặt file này vào: C:\wamp64\www\PHP\ELite_GYM\Home\Schedule\groq_test.php
 * Truy cập: http://localhost/PHP/ELite_GYM/Home/Schedule/groq_test.php
 * XÓA SAU KHI TEST XONG!
 */
session_start();
if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    // Comment dòng dưới nếu muốn test không cần đăng nhập
    // die('Cần đăng nhập');
}

header('Content-Type: text/plain; charset=utf-8');

$groq_key = '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';
$groq_url = 'https://api.groq.com/openai/v1/chat/completions';

echo "=== GROQ API TEST ===\n\n";

// 1. cURL extension
echo "1. cURL: " . (function_exists('curl_init') ? "✅ OK\n\n" : "❌ KHÔNG CÓ — bật extension=curl trong php.ini\n\n");

// 2. DNS
echo "2. DNS api.groq.com: ";
$ip = gethostbyname('api.groq.com');
if ($ip === 'api.groq.com') {
    echo "❌ Không phân giải được — Kiểm tra internet\n\n";
} else {
    echo "✅ OK → $ip\n\n";
}

// 3. TCP port 443
echo "3. TCP port 443: ";
$sock = @fsockopen('ssl://api.groq.com', 443, $errno, $errstr, 8);
if (!$sock) {
    echo "❌ Không kết nối được ($errno: $errstr) — Firewall chặn?\n\n";
} else {
    fclose($sock);
    echo "✅ OK\n\n";
}

// 4. Gọi API non-stream
echo "4. Groq API call (non-stream): ";
$payload = json_encode([
    'model'       => 'llama-3.1-8b-instant',
    'stream'      => false,
    'max_tokens'  => 20,
    'messages'    => [['role' => 'user', 'content' => 'Say "OK" only, in English.']],
]);

$ch = curl_init($groq_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groq_key,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
$curlNo   = curl_errno($ch);
curl_close($ch);

if ($curlNo) {
    echo "❌ cURL error #$curlNo: $curlErr\n\n";
} elseif ($httpCode === 200) {
    $data  = json_decode($resp, true);
    $text  = $data['choices'][0]['message']['content'] ?? '(empty)';
    $model = $data['model'] ?? '?';
    echo "✅ HTTP 200 — Model: $model — Response: \"$text\"\n\n";
} elseif ($httpCode === 401) {
    echo "❌ HTTP 401 — API key không hợp lệ hoặc hết hạn!\n";
    echo "   Raw: " . substr($resp, 0, 200) . "\n\n";
} elseif ($httpCode === 429) {
    echo "⚠️ HTTP 429 — Rate limit. Thử lại sau.\n\n";
} else {
    echo "❌ HTTP $httpCode\n   Raw: " . substr($resp, 0, 300) . "\n\n";
}

// 5. Gọi API stream
echo "5. Groq API call (stream=true): ";
$payload_stream = json_encode([
    'model'      => 'llama-3.1-8b-instant',
    'stream'     => true,
    'max_tokens' => 30,
    'messages'   => [['role' => 'user', 'content' => 'Count 1 to 3']],
]);

$tokens = '';
$ch2 = curl_init($groq_url);
curl_setopt_array($ch2, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload_stream,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groq_key,
    ],
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use (&$tokens) {
        foreach (explode("\n", $chunk) as $line) {
            $line = trim($line);
            if (strpos($line, 'data: ') !== 0 || $line === 'data: [DONE]') continue;
            $obj = json_decode(substr($line, 6), true);
            $t   = $obj['choices'][0]['delta']['content'] ?? '';
            $tokens .= $t;
        }
        return strlen($chunk);
    },
]);
curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$curlErr2  = curl_error($ch2);
$curlNo2   = curl_errno($ch2);
curl_close($ch2);

if ($curlNo2) {
    echo "❌ cURL error #$curlNo2: $curlErr2\n\n";
} elseif ($httpCode2 === 200 && $tokens) {
    echo "✅ HTTP 200 — Tokens nhận được: \"$tokens\"\n\n";
} elseif ($httpCode2 === 200) {
    echo "⚠️ HTTP 200 nhưng không parse được token\n\n";
} else {
    echo "❌ HTTP $httpCode2, cURL#$curlNo2: $curlErr2\n\n";
}

echo "=== XONG ===\n";
echo "Nếu test 4 và 5 đều ✅ → ai_proxy.php hoạt động được.\n";
echo "⚠️ XÓA FILE NÀY SAU KHI TEST XONG!\n";
?>
