<?php
// Home/Schedule/ai_proxy.php
// Proxy: tìm mẫu trong JSONL trước → fallback sang Ollama nếu không có
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ── Chỉ nhận POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method không hợp lệ']);
    exit;
}

// ── Auth: chỉ cho Customer đã đăng nhập gọi ──────────────────
if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

// ── Đọc body JSON từ JS ───────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || empty($body['class_id'])) {
    echo json_encode(['success' => false, 'message' => 'Thiếu class_id']);
    exit;
}

$class_id = (int)$body['class_id'];

// ── Kết nối DB ────────────────────────────────────────────────
require_once '../../Database/db.php';

// ── Lấy thông tin lớp + phòng + thiết bị từ DB ───────────────
$sql = $conn->prepare("
    SELECT
        tc.class_name,
        tc.start_time,
        tc.end_time,
        gr.room_name,
        e.full_name AS trainer_name,
        GROUP_CONCAT(
            eq.equipment_name ORDER BY eq.equipment_name SEPARATOR ', '
        ) AS equipment_list
    FROM TrainingClass tc
    LEFT JOIN GymRoom    gr ON gr.room_id    = tc.room_id
    LEFT JOIN Employee   e  ON e.employee_id = tc.trainer_id
    LEFT JOIN Equipment  eq ON eq.room_id    = gr.room_id
                            AND LOWER(TRIM(eq.condition_status)) NOT IN ('broken','hỏng','damaged')
    WHERE tc.class_id = ?
    GROUP BY tc.class_id, tc.class_name, tc.start_time, tc.end_time,
             gr.room_name, e.full_name
    LIMIT 1
");
$sql->bind_param("i", $class_id);
$sql->execute();
$cls = $sql->get_result()->fetch_assoc();
$sql->close();
$conn->close();

if (!$cls) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy lớp học']);
    exit;
}

// ── Tính thời lượng buổi tập ──────────────────────────────────
$start_ts = strtotime($cls['start_time']);
$end_ts   = strtotime($cls['end_time']);
$duration = ($end_ts > $start_ts) ? (int)(($end_ts - $start_ts) / 60) : 60;

// ── Làm tròn duration về mốc gần nhất [45, 60, 90] ───────────
$dur_buckets = [45, 60, 90];
$dur_key = $dur_buckets[0];
foreach ($dur_buckets as $b) {
    if (abs($duration - $b) <= abs($duration - $dur_key)) $dur_key = $b;
}

// ── Lấy thông số người dùng từ JS ────────────────────────────
$bmi         = floatval($body['bmi']        ?? 0);
$bmi_cat     = trim($body['bmi_cat']        ?? 'Bình thường');
$goal        = trim($body['goal']           ?? 'Tăng cơ');
$burn_target = intval($body['burn_target']  ?? 400);
$gender      = trim($body['gender']         ?? 'Nam');
$age         = intval($body['age']          ?? 25);

$equip = $cls['equipment_list'] ?: 'thiết bị cơ bản (thảm tập, tạ tay nhẹ)';

// ── Trích tên lớp (bỏ ngày giờ) ─────────────────────────────
// "Basic A101 - 06/04/2026 08:00–10:00" → "Basic A101"
$class_name_raw   = $cls['class_name'];
$class_name_short = trim(preg_replace('/\s*[-–]\s*\d{2}\/\d{2}\/\d{4}.*$/u', '', $class_name_raw));

// ── Tìm trong JSONL ───────────────────────────────────────────
// Xử lý 3 dạng class_name trong JSONL:
//   Dạng 1 (cũ):  "Basic A1"   → DB startsWith "Basic A1"   ✓
//   Dạng 2 (mới): "Basic A101" → match chính xác             ✓
//   Dạng 3 (raw): "Basic A101 - 06/04/..." → cắt ngày = "Basic A101" ✓
$jsonl_path   = 'C:/wamp64/www/PHP/ELite_GYM/gym-ai/gym_training_data.jsonl';
$cached_reply = null;
$source       = 'ollama';

if (file_exists($jsonl_path)) {
    $best          = null;
    $best_bmi_diff = PHP_INT_MAX;
    $fh = fopen($jsonl_path, 'r');
    while (($line = fgets($fh)) !== false) {
        $item = json_decode(trim($line), true);
        if (!$item) continue;

        // Chuẩn hoá class_name trong JSONL (cắt ngày nếu có)
        $jcn = trim(preg_replace('/\s*[-–]\s*\d{2}\/\d{2}\/\d{4}.*$/u', '', $item['class_name'] ?? ''));

        // Khớp nếu: bằng nhau, hoặc DB bắt đầu bằng JSONL (cũ: "Basic A101" startsWith "Basic A1")
        $class_match = ($jcn === $class_name_short)
                    || (stripos($class_name_short, $jcn) === 0 && strlen($jcn) > 3);

        if (
            $class_match &&
            ($item['room']   ?? '') === $cls['room_name'] &&
            ($item['goal']   ?? '') === $goal             &&
            ($item['gender'] ?? '') === $gender           &&
            (int)($item['duration'] ?? 0) === $dur_key
        ) {
            $bmi_diff = abs(floatval($item['bmi_val'] ?? 999) - $bmi);
            if ($bmi_diff < $best_bmi_diff) {
                $best_bmi_diff = $bmi_diff;
                $best = $item;
                if ($bmi_diff < 0.1) break; // khớp chính xác → dừng
            }
        }
    }
    fclose($fh);

    if ($best) {
        $cached_reply = $best['completion'];
        $source = ($best_bmi_diff < 0.1) ? 'cache' : 'cache_approx';
    }
}

// ── Trả về cache nếu tìm thấy → nhanh, không cần Ollama ──────
if ($cached_reply) {
    echo json_encode([
        'success'   => true,
        'text'      => $cached_reply,
        'equipment' => $equip,
        'room'      => $cls['room_name'],
        'duration'  => $duration,
        'source'    => $source
    ]);
    exit;
}

// ── Fallback: gọi Ollama nếu không có trong JSONL ────────────
$main_time = $duration - 18;
$prompt = "SYSTEM: You must respond ONLY in Vietnamese (Tiếng Việt). Do not use Chinese, Japanese or any other language. If you write in any language other than Vietnamese, you have failed.\n\nBạn là huấn luyện viên thể hình tại Elite Gym. CHỈ viết bằng TIẾNG VIỆT, tuyệt đối KHÔNG dùng tiếng Trung hay bất kỳ ngôn ngữ nào khác.
Tạo lịch tập {$duration} phút cho khách hàng {$gender}, BMI {$bmi} ({$bmi_cat}), mục tiêu: {$goal}.
Lớp: {$class_name_short} | Phòng: {$cls['room_name']}
Thiết bị có sẵn: {$equip}

QUY TẮC:
- CHỈ dùng thiết bị liệt kê, ghi đúng tên thiết bị trong ngoặc vuông
- Tên bài tập viết bằng tiếng Việt rõ ràng (Chạy bộ, Đẩy ngực, Kéo lưng, Gập bụng...)
- KHÔNG dùng ký tự tiếng Trung
- Giãn cơ là kéo giãn nhẹ nhàng, KHÔNG phải sets x reps

### 🔥 KHỞI ĐỘNG (5-8 phút)
Mỗi bài: Tên bài [Tên thiết bị] • X phút • ~N kcal
Ví dụ: Đi bộ khởi động [Commercial Treadmill 1] • 5 phút • tốc độ 5km/h • ~25 kcal

### 💪 BÀI TẬP CHÍNH ({$main_time} phút)
Mỗi bài: Tên bài [Tên thiết bị] • Xsets x Yreps • nghi Zs • ~N kcal
Ví dụ: Đẩy ngực [Chest Press Machine] • 3sets x 12reps • nghi 60s • ~40 kcal

### 🧘 GIÃN CƠ (5-8 phút)
3 bài kéo giãn nhẹ: Tên động tác • X giây mỗi bên

### 📊 TỔNG KẾT
Tổng kcal: ~X kcal | Dat Y% muc tieu | Loi khuyen: (1 cau dinh duong)";

$ollama_url = 'http://localhost:11434/api/generate';
$payload    = json_encode([
    'model'   => 'qwen2.5:3b',
    'prompt'  => $prompt,
    'stream'  => false,
    'options' => [
        'num_gpu'        => 99,
        'num_ctx'        => 2048,
        'temperature'    => 0.8,
        'top_p'          => 0.9,
        'repeat_penalty' => 1.1
    ]
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($ollama_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 600,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    echo json_encode(['success' => false, 'message' => 'Không kết nối được Ollama: ' . $curl_err]);
    exit;
}

$data  = json_decode($response, true);
$reply = trim($data['response'] ?? '');

if (!$reply) {
    echo json_encode(['success' => false, 'message' => 'Ollama không trả về kết quả. Kiểm tra model qwen2.5:3b đã pull chưa.']);
    exit;
}

echo json_encode([
    'success'   => true,
    'text'      => $reply,
    'equipment' => $equip,
    'room'      => $cls['room_name'],
    'duration'  => $duration,
    'source'    => 'ollama'
]);
?>
