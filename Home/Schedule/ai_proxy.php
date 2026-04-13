<?php
/**
 * ai_proxy.php  v4.0  — Ollama Streaming + PHP kcal pre-calculation
 *
 * THAY ĐỔI v4.0:
 *  - Tính kcal trước trong PHP bằng công thức MET chuẩn
 *  - Inject số kcal thật vào prompt → model không cần tự đoán
 *  - Model chỉ chịu trách nhiệm: tên bài tập + thiết bị + sets/reps
 */

ob_end_clean();
session_start();

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

// ── SSE helpers ──────────────────────────────────────────────
function sseFlush(): void { if (ob_get_level()) ob_flush(); flush(); }
function sseEvent(string $event, array $data): void {
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    sseFlush();
}
function sseToken(string $token): void {
    echo 'data: ' . json_encode(['token' => $token], JSON_UNESCAPED_UNICODE) . "\n\n";
    sseFlush();
}
function sseDone(): void { echo "event: done\ndata: {}\n\n"; sseFlush(); }
function sseError(string $msg): void { sseEvent('error', ['error' => $msg]); sseDone(); }

// ── Auth ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sseError('Method không hợp lệ'); exit; }
if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    sseError('Chưa đăng nhập'); exit;
}

// ── Parse input ──────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!$body || empty($body['class_id'])) { sseError('Thiếu class_id'); exit; }

$class_id    = (int)$body['class_id'];
$bmi         = floatval($body['bmi']         ?? 22);
$bmi_cat     = trim($body['bmi_cat']         ?? 'Bình thường');
$goal        = trim($body['goal']            ?? 'Tăng cơ');
$burn_target = max(100, intval($body['burn_target'] ?? 350));
$gender      = trim($body['gender']          ?? 'Nam');
$age         = intval($body['age']           ?? 25);

// ── DB ───────────────────────────────────────────────────────
require_once '../../Database/db.php';

$sql = $conn->prepare("
    SELECT gr.room_name, tc.start_time, tc.end_time,
           GROUP_CONCAT(eq.equipment_name ORDER BY eq.equipment_name SEPARATOR ', ') AS equipment_list
    FROM TrainingClass tc
    LEFT JOIN GymRoom   gr ON gr.room_id  = tc.room_id
    LEFT JOIN Equipment eq ON eq.room_id  = gr.room_id
                           AND LOWER(TRIM(eq.condition_status)) NOT IN ('broken','hỏng','damaged')
    WHERE tc.class_id = ?
    GROUP BY gr.room_name, tc.start_time, tc.end_time
    LIMIT 1
");
$sql->bind_param("i", $class_id);
$sql->execute();
$cls = $sql->get_result()->fetch_assoc();
$sql->close();
$conn->close();

if (!$cls) { sseError('Không tìm thấy lớp học'); exit; }

$room_name = $cls['room_name'];
$equip     = $cls['equipment_list'] ?: 'thiết bị cơ bản';
$start_ts  = strtotime($cls['start_time']);
$end_ts    = strtotime($cls['end_time']);
$duration  = ($end_ts > $start_ts) ? (int)(($end_ts - $start_ts) / 60) : 60;
$duration  = max(30, min(180, $duration));
$main_time = max(10, $duration - 15);

// ── Meta ─────────────────────────────────────────────────────
sseEvent('meta', ['room' => $room_name, 'equipment' => $equip, 'duration' => $duration]);

// ═══════════════════════════════════════════════════════════════
// ── TÍNH KCAL TRƯỚC BẰNG CÔNG THỨC MET (Harris-Benedict + MET) ─
// ═══════════════════════════════════════════════════════════════

// Thời gian từng phase (phút)
$t_warmup   = 7;
$t_cooldown = 6;
$t_main     = max(15, $duration - $t_warmup - $t_cooldown);

// ── Phân bổ kcal theo % cố định theo goal ────────────────────
// Warmup ~10%, Main ~85%, Cooldown ~5%
// Đảm bảo sàn tối thiểu thực tế: warmup ≥30, cooldown ≥15
$pct_table = [
    'Giảm mỡ'          => ['w' => 0.12, 'c' => 0.06],
    'Tăng cơ'          => ['w' => 0.10, 'c' => 0.05],
    'Tăng sức bền'     => ['w' => 0.13, 'c' => 0.06],
    'Duy trì thể hình' => ['w' => 0.10, 'c' => 0.05],
];
$pct = $pct_table[$goal] ?? $pct_table['Tăng cơ'];

$kcal_warmup   = max(30, (int)(round($burn_target * $pct['w'] / 5) * 5));
$kcal_cooldown = max(15, (int)(round($burn_target * $pct['c'] / 5) * 5));
$kcal_main     = max(50, $burn_target - $kcal_warmup - $kcal_cooldown);

// Chia kcal_main cho 5 bài tập chính (không đều — bài nặng hơn ăn nhiều hơn)
// Tỷ lệ: 25% / 25% / 20% / 20% / 10%  (3 bài nặng đầu, 2 bài sau nhẹ hơn)
$ratio = [0.26, 0.24, 0.22, 0.18, 0.10];
$kcal_main_exercises = [];
$running_total = 0;
foreach ($ratio as $i => $r) {
    if ($i < 4) {
        $v = (int)(round($kcal_main * $r / 5) * 5);
        $kcal_main_exercises[] = $v;
        $running_total += $v;
    } else {
        // Bài cuối lấy phần còn lại để đảm bảo tổng chính xác
        $kcal_main_exercises[] = max(10, $kcal_main - $running_total);
    }
}

// Chia kcal_warmup cho 3 bài khởi động
$kcal_w = [
    (int)(round($kcal_warmup * 0.40 / 5) * 5),
    (int)(round($kcal_warmup * 0.35 / 5) * 5),
];
$kcal_w[] = max(5, $kcal_warmup - $kcal_w[0] - $kcal_w[1]);

// Chia kcal_cooldown cho 3 bài giãn cơ
$kcal_c = [
    (int)(round($kcal_cooldown * 0.40 / 5) * 5),
    (int)(round($kcal_cooldown * 0.35 / 5) * 5),
];
$kcal_c[] = max(5, $kcal_cooldown - $kcal_c[0] - $kcal_c[1]);

// Tổng thực tế
$kcal_real_total = $kcal_warmup + array_sum($kcal_main_exercises) + $kcal_cooldown;
$pct = ($burn_target > 0) ? (int)round($kcal_real_total / $burn_target * 100) : 100;

// Sets/reps phù hợp với goal
$sets_reps = [
    'Giảm mỡ'          => ['sets' => 3, 'reps' => 15, 'rest' => 45],
    'Tăng cơ'          => ['sets' => 4, 'reps' => 10, 'rest' => 60],
    'Tăng sức bền'     => ['sets' => 3, 'reps' => 20, 'rest' => 30],
    'Duy trì thể hình' => ['sets' => 3, 'reps' => 12, 'rest' => 45],
];
$sr = $sets_reps[$goal] ?? $sets_reps['Tăng cơ'];

// Thời gian khởi động mỗi bài (phút)
$warmup_mins = [3, 2, 2]; // tổng 7 phút

// ── Nutrition ────────────────────────────────────────────────
$nutrition = [
    'Giảm mỡ'          => 'Ăn đủ 1.6g protein/kg, hạn chế carb tinh chế, uống ≥2.5L nước/ngày.',
    'Tăng cơ'          => 'Bổ sung 1.8–2g protein/kg ngay sau tập, ưu tiên carb phức hợp.',
    'Tăng sức bền'     => 'Nạp carb phức hợp trước tập 1-2h, bổ sung điện giải sau cardio.',
    'Duy trì thể hình' => 'Cân bằng macro 40% carb – 30% protein – 30% chất béo lành mạnh.',
];
$advice = $nutrition[$goal] ?? 'Uống đủ nước và nghỉ ngơi hợp lý để tối ưu phục hồi.';

// ═══════════════════════════════════════════════════════════════
// ── PROMPT: inject số kcal thật, model chỉ điền tên bài + thiết bị
// ═══════════════════════════════════════════════════════════════

$s  = $sr['sets'];
$rp = $sr['reps'];
$rs = $sr['rest'];

// Thời gian giãn cơ mỗi bài (giây)
$cooldown_secs = [40, 35, 30];

$prompt = <<<PROMPT
Bạn là HLV gym chuyên nghiệp. Viết lịch tập theo FORMAT DƯỚI ĐÂY, điền tên bài + thiết bị vào chỗ [___].
KHÔNG thay đổi số kcal, sets, reps, thời gian — đã được tính sẵn, chỉ COPY nguyên xi.

THÔNG TIN:
- Giới tính: {$gender} | Tuổi: {$age} | BMI: {$bmi} ({$bmi_cat})
- Mục tiêu: {$goal} | Phòng: {$room_name}
- Thiết bị có sẵn: {$equip}
- Tổng thời lượng: {$duration} phút | Mục tiêu đốt calo: {$burn_target} kcal

QUY TẮC:
1. CHỈ dùng thiết bị trong danh sách: {$equip}
2. Tên bài viết tiếng Việt, cụ thể (VD: "Đẩy ngực trên ghế phẳng", "Squat tạ đòn")
3. Mỗi bài PHẢI dùng thiết bị KHÁC NHAU nếu có thể, không lặp lại cùng bài 2 lần
4. Phù hợp mục tiêu {$goal} và BMI {$bmi_cat}
5. SAO CHÉP CHÍNH XÁC số kcal, sets, reps, thời gian từ template dưới đây

=== FORMAT BẮT BUỘC — COPY CHÍNH XÁC, CHỈ THAY [___] ===

### 🔥 KHỞI ĐỘNG ({$t_warmup} phút) — tổng {$kcal_warmup} kcal
- [Tên bài khởi động 1 + tên thiết bị] • {$warmup_mins[0]} phút • ~{$kcal_w[0]} kcal
- [Tên bài khởi động 2 + tên thiết bị] • {$warmup_mins[1]} phút • ~{$kcal_w[1]} kcal
- [Tên bài khởi động 3 + tên thiết bị] • {$warmup_mins[2]} phút • ~{$kcal_w[2]} kcal

### 💪 BÀI TẬP CHÍNH ({$t_main} phút) — tổng {$kcal_main} kcal
- [Tên bài chính 1 + tên thiết bị] • {$s}sets × {$rp}reps • nghỉ {$rs}s • ~{$kcal_main_exercises[0]} kcal
- [Tên bài chính 2 + tên thiết bị] • {$s}sets × {$rp}reps • nghỉ {$rs}s • ~{$kcal_main_exercises[1]} kcal
- [Tên bài chính 3 + tên thiết bị] • {$s}sets × {$rp}reps • nghỉ {$rs}s • ~{$kcal_main_exercises[2]} kcal
- [Tên bài chính 4 + tên thiết bị] • {$s}sets × {$rp}reps • nghỉ {$rs}s • ~{$kcal_main_exercises[3]} kcal
- [Tên bài chính 5 + tên thiết bị] • {$s}sets × {$rp}reps • nghỉ {$rs}s • ~{$kcal_main_exercises[4]} kcal

### 🧘 GIÃN CƠ ({$t_cooldown} phút) — tổng {$kcal_cooldown} kcal
- [Tên bài giãn cơ 1 + tên thiết bị] • {$cooldown_secs[0]} giây mỗi bên • ~{$kcal_c[0]} kcal
- [Tên bài giãn cơ 2 + tên thiết bị] • {$cooldown_secs[1]} giây mỗi bên • ~{$kcal_c[1]} kcal
- [Tên bài giãn cơ 3 + tên thiết bị] • {$cooldown_secs[2]} giây mỗi bên • ~{$kcal_c[2]} kcal

### 📊 TỔNG KẾT
Tổng kcal: ~{$kcal_real_total} kcal | Đạt {$pct}% mục tiêu đốt calo | Lời khuyên: {$advice}

=== KẾT THÚC FORMAT ===
PROMPT;

// ── Gọi Ollama ───────────────────────────────────────────────
$ollama_url   = 'http://localhost:11434/api/generate';
$ollama_model = 'qwen2.5:1.5b';

// ── WARM-UP: ping để model load vào RAM ──────────────────────
$warmup_ch = curl_init($ollama_url);
curl_setopt_array($warmup_ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'model'   => $ollama_model,
        'prompt'  => 'hi',
        'stream'  => false,
        'options' => ['num_predict' => 1],
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 3,
]);
curl_exec($warmup_ch);
curl_close($warmup_ch);

// ── MAIN: gọi với stream=true ─────────────────────────────────
$payload = json_encode([
    'model'   => $ollama_model,
    'prompt'  => $prompt,
    'stream'  => true,
    'options' => [
        'temperature'    => 0.6,   // giảm xuống để model "copy" tốt hơn
        'num_predict'    => 1200,
        'num_ctx'        => 3072,  // tăng context cho prompt dài hơn
        'top_p'          => 0.85,
        'repeat_penalty' => 1.15,
    ],
], JSON_UNESCAPED_UNICODE);

$lineBuffer = '';
$doneSent   = false;

$ch = curl_init($ollama_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_TIMEOUT        => 180,
    CURLOPT_CONNECTTIMEOUT => 5,

    CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use (&$lineBuffer, &$doneSent) {
        $lineBuffer .= $chunk;

        while (($pos = strpos($lineBuffer, "\n")) !== false) {
            $line       = trim(substr($lineBuffer, 0, $pos));
            $lineBuffer = substr($lineBuffer, $pos + 1);
            if (!$line) continue;

            $obj = json_decode($line, true);
            if (!is_array($obj)) continue;

            if (isset($obj['response']) && $obj['response'] !== '') {
                sseToken($obj['response']);
            }

            if (!empty($obj['done'])) {
                sseDone();
                $doneSent = true;
            }
        }
        return strlen($chunk);
    },
]);

curl_exec($ch);
$curlErrno = curl_errno($ch);
$curlError = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$doneSent) {
    if ($curlErrno) {
        sseError("Ollama không phản hồi (cURL #{$curlErrno}): {$curlError}");
    } elseif ($httpCode && $httpCode !== 200) {
        sseError("Ollama lỗi HTTP {$httpCode}. Kiểm tra: ollama pull {$ollama_model}");
    } else {
        sseDone();
    }
}
?>
