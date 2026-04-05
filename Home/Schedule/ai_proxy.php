<?php
// Home/Schedule/ai_proxy.php
// Proxy gọi Ollama local → tạo lịch tập AI dựa trên thiết bị phòng thật từ DB
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

// ── Lấy thông số người dùng từ JS ────────────────────────────
$bmi        = floatval($body['bmi']        ?? 0);
$bmi_cat    = trim($body['bmi_cat']        ?? 'Bình thường');
$goal       = trim($body['goal']           ?? 'Tăng cơ');
$burn_target = intval($body['burn_target'] ?? 400);
$gender     = trim($body['gender']         ?? 'Nam');
$age        = intval($body['age']          ?? 25);

$equip = $cls['equipment_list'] ?: 'thiết bị cơ bản (thảm tập, tạ tay nhẹ)';

// ── Xây dựng prompt ───────────────────────────────────────────
$main_time = $duration - 18;
$prompt = "Bạn là HLV thể hình chuyên nghiệp tại Elite Gym. Tạo lịch tập chi tiết cho khách hàng.

## THÔNG TIN KHÁCH HÀNG
- Giới tính: {$gender} | Tuổi: {$age}
- BMI: {$bmi} ({$bmi_cat})
- Mục tiêu: {$goal}
- Calo cần đốt buổi này: ~{$burn_target} kcal

## THÔNG TIN BUỔI TẬP
- Lớp: {$cls['class_name']}
- Phòng: {$cls['room_name']}
- HLV: {$cls['trainer_name']}
- Thời lượng: {$duration} phút
- Thiết bị có sẵn trong phòng: {$equip}

## QUY TẮC BẮT BUỘC
- CHỈ dùng thiết bị liệt kê ở trên, không dùng thiết bị khác
- Viết tiếng Việt, ngắn gọn, thực tế
- Bài tập phù hợp với BMI và mục tiêu

## FORMAT BẮT BUỘC (giữ nguyên các dòng ### và emoji):

### 🔥 KHỞI ĐỘNG (5-8 phút)
(liệt kê 3-4 bài khởi động, mỗi bài một dòng)

### 💪 BÀI TẬP CHÍNH ({$main_time} phút)
(5-6 bài, mỗi bài theo format: Tên bài • Xsets×Yreps • nghỉ Zs • ~N kcal)

### 🧘 GIÃN CƠ (5-8 phút)
(3 bài giãn cơ)

### 📊 TỔNG KẾT
Tổng kcal: ~X kcal | So với mục tiêu: Y% | Lời khuyên: (1 câu dinh dưỡng ngắn)";

// ── Gọi Ollama local ──────────────────────────────────────────
$ollama_url = 'http://localhost:11434/api/generate';
$payload    = json_encode([
    'model'  => 'llama3.2:3b',
    'prompt' => $prompt,
    'stream' => false
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($ollama_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 120,
    // Ollama chạy local nên không cần SSL
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$curl_err = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    echo json_encode(['success' => false, 'message' => 'Không kết nối được Ollama: ' . $curl_err . '. Hãy chắc chắn Ollama đang chạy (ollama serve).']);
    exit;
}

$data  = json_decode($response, true);
$reply = trim($data['response'] ?? '');

if (!$reply) {
    echo json_encode(['success' => false, 'message' => 'Ollama không trả về kết quả. Kiểm tra model llama3.2:3b đã được pull chưa.']);
    exit;
}

echo json_encode([
    'success'   => true,
    'text'      => $reply,
    'equipment' => $equip,
    'room'      => $cls['room_name'],
    'duration'  => $duration
]);
?>
