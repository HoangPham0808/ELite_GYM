<?php
/**
 * ai_proxy.php  v5.0
 *
 * THAY ĐỔI v5.0:
 *  - PHP tự chọn bài tập từ pool riêng biệt cho từng goal
 *  - Lọc bài theo thiết bị thực tế trong phòng (keyword matching)
 *  - Inject tên bài THẬT (không còn placeholder) vào template
 *  - Model chỉ output lại text → kcal/sets/reps không bao giờ bị thay đổi
 *  - Groq API với SSL auto-detect cho localhost WAMP
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sseError('Method khong hop le'); exit; }
if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    sseError('Chua dang nhap'); exit;
}

// ── Parse input ──────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!$body || empty($body['class_id'])) { sseError('Thieu class_id'); exit; }

$class_id    = (int)$body['class_id'];
$bmi         = floatval($body['bmi']         ?? 22);
$bmi_cat     = trim($body['bmi_cat']         ?? 'Binh thuong');
$goal        = trim($body['goal']            ?? 'Tang co');
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
                           AND LOWER(TRIM(eq.condition_status)) NOT IN ('broken','hong','damaged')
    WHERE tc.class_id = ?
    GROUP BY gr.room_name, tc.start_time, tc.end_time
    LIMIT 1
");
$sql->bind_param("i", $class_id);
$sql->execute();
$cls = $sql->get_result()->fetch_assoc();
$sql->close();
$conn->close();

if (!$cls) { sseError('Khong tim thay lop hoc'); exit; }

$room_name  = $cls['room_name'];
$equip      = $cls['equipment_list'] ?: 'thiet bi co ban';
$start_ts   = strtotime($cls['start_time']);
$end_ts     = strtotime($cls['end_time']);
$duration   = ($end_ts > $start_ts) ? (int)(($end_ts - $start_ts) / 60) : 60;
$duration   = max(30, min(180, $duration));

// ── Meta event ───────────────────────────────────────────────
sseEvent('meta', ['room' => $room_name, 'equipment' => $equip, 'duration' => $duration]);

// ═══════════════════════════════════════════════════════════════
// BUOC 1: TINH KCAL
// ═══════════════════════════════════════════════════════════════

$t_warmup   = 7;
$t_cooldown = 6;
$t_main     = max(15, $duration - $t_warmup - $t_cooldown);

$phase_pct = [
    'Giam mo'          => ['w' => 0.12, 'c' => 0.05],
    'Tang co'          => ['w' => 0.09, 'c' => 0.04],
    'Tang suc ben'     => ['w' => 0.13, 'c' => 0.05],
    'Duy tri the hinh' => ['w' => 0.10, 'c' => 0.04],
    // UTF-8 keys also
    'Giảm mỡ'          => ['w' => 0.12, 'c' => 0.05],
    'Tăng cơ'          => ['w' => 0.09, 'c' => 0.04],
    'Tăng sức bền'     => ['w' => 0.13, 'c' => 0.05],
    'Duy trì thể hình' => ['w' => 0.10, 'c' => 0.04],
];
$pp = $phase_pct[$goal] ?? ['w' => 0.10, 'c' => 0.04];

// Round to nearest 5
$r5 = fn(float $v): int => (int)(round($v / 5) * 5);

$kcal_warmup   = max(25, $r5($burn_target * $pp['w']));
$kcal_cooldown = max(10, $r5($burn_target * $pp['c']));
$kcal_main     = max(50, $burn_target - $kcal_warmup - $kcal_cooldown);

// Warmup: 3 bai, ratio 40/35/25
$kw = [$r5($kcal_warmup * 0.40), $r5($kcal_warmup * 0.35)];
$kw[] = max(5, $kcal_warmup - $kw[0] - $kw[1]);

// Main: 5 bai, ty le theo goal
$main_ratio = [
    'Giảm mỡ'          => [0.25, 0.23, 0.21, 0.19, 0.12],
    'Tăng cơ'          => [0.28, 0.25, 0.20, 0.17, 0.10],
    'Tăng sức bền'     => [0.22, 0.22, 0.20, 0.20, 0.16],
    'Duy trì thể hình' => [0.25, 0.23, 0.20, 0.18, 0.14],
];
$mr  = $main_ratio[$goal] ?? [0.25, 0.23, 0.20, 0.18, 0.14];
$km  = [];
$ksum = 0;
foreach ($mr as $i => $r) {
    if ($i < 4) { $v = $r5($kcal_main * $r); $km[] = $v; $ksum += $v; }
    else        { $km[] = max(10, $kcal_main - $ksum); }
}

// Cooldown: 3 bai, ratio 40/35/25
$kc = [$r5($kcal_cooldown * 0.40), $r5($kcal_cooldown * 0.35)];
$kc[] = max(5, $kcal_cooldown - $kc[0] - $kc[1]);

// Tong thuc te
$kcal_total    = $kcal_warmup + array_sum($km) + $kcal_cooldown;
$pct_achieved  = ($burn_target > 0) ? (int)round($kcal_total / $burn_target * 100) : 100;

// Sets/reps/rest + note theo goal
$goal_config = [
    'Giảm mỡ'          => ['sets' => 3,  'reps' => 15, 'rest' => 45,
                            'note' => 'Nhịp nhanh, ít nghỉ — tối đa hóa đốt mỡ'],
    'Tăng cơ'          => ['sets' => 4,  'reps' => 10, 'rest' => 90,
                            'note' => 'Tạ nặng, nghỉ đủ — kích thích phát triển cơ tối đa'],
    'Tăng sức bền'     => ['sets' => 3,  'reps' => 20, 'rest' => 30,
                            'note' => 'Tạ nhẹ, reps cao — xây dựng sức bền tim mạch'],
    'Duy trì thể hình' => ['sets' => 3,  'reps' => 12, 'rest' => 60,
                            'note' => 'Cân bằng sức mạnh và sức bền toàn thân'],
];
$gc = $goal_config[$goal] ?? $goal_config['Tăng cơ'];
$s  = $gc['sets'];
$rp = $gc['reps'];
$rs = $gc['rest'];

$cd_secs = [45, 40, 35];

$nutrition = [
    'Giảm mỡ'          => 'Ăn đủ 1.6g protein/kg, hạn chế carb tinh chế, uống ≥2.5L nước/ngày.',
    'Tăng cơ'          => 'Bổ sung 1.8–2g protein/kg ngay sau tập, ưu tiên carb phức hợp.',
    'Tăng sức bền'     => 'Nạp carb phức hợp trước tập 1-2h, bổ sung điện giải sau cardio.',
    'Duy trì thể hình' => 'Cân bằng macro 40% carb – 30% protein – 30% chất béo lành mạnh.',
];
$advice = $nutrition[$goal] ?? 'Uống đủ nước và nghỉ ngơi hợp lý.';

// ═══════════════════════════════════════════════════════════════
// BUOC 2: POOL BAI TAP THEO GOAL
// ═══════════════════════════════════════════════════════════════
// Cau truc: ['name'=>'Ten bai VN', 'equip'=>'Ten hien thi', 'keys'=>['keyword1','keyword2']]
// keys=['*'] => khong can thiet bi

$pools = [
    'Giảm mỡ' => [
        'warmup' => [
            ['name'=>'Chạy bộ nhẹ khởi động',         'equip'=>'Commercial Treadmill',        'keys'=>['treadmill']],
            ['name'=>'Đạp xe nhẹ khởi động',           'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
            ['name'=>'Leo máy thang nhẹ khởi động',    'equip'=>'Stair Climber',               'keys'=>['stair climber']],
            ['name'=>'Chạy elip nhẹ khởi động',        'equip'=>'Elliptical Trainer',          'keys'=>['elliptical']],
            ['name'=>'Nhảy jumping jack tại chỗ',      'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Bước tại chỗ nâng cao gối',      'equip'=>'Thảm tập',                    'keys'=>['*']],
        ],
        'main' => [
            ['name'=>'Chạy HIIT tốc độ cao',           'equip'=>'Commercial Treadmill',        'keys'=>['treadmill']],
            ['name'=>'Đạp xe HIIT cường độ cao',       'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
            ['name'=>'Leo thang liên tục cường độ cao','equip'=>'Stair Climber',               'keys'=>['stair climber']],
            ['name'=>'Chạy elip kháng lực cao',        'equip'=>'Elliptical Trainer',          'keys'=>['elliptical']],
            ['name'=>'Kéo cáp cao xuống (Lat Pulldown)','equip'=>'Lat Pulldown Machine',       'keys'=>['lat pulldown']],
            ['name'=>'Kéo cáp ngực chéo (Cable Fly)',  'equip'=>'Cable Crossover',             'keys'=>['cable crossover']],
            ['name'=>'Đẩy ngực máy nhịp nhanh',        'equip'=>'Chest Press Machine',         'keys'=>['chest press machine']],
            ['name'=>'Đẩy ngực nghiêng máy',           'equip'=>'Incline Chest Press Machine', 'keys'=>['incline chest press']],
            ['name'=>'Kéo lưng ngồi nhịp nhanh',       'equip'=>'Seated Row Machine',          'keys'=>['seated row']],
            ['name'=>'Squat tạ đôi nhịp nhanh',        'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
            ['name'=>'Deadlift tạ đôi nhịp nhanh',     'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
            ['name'=>'Lunge tạ tay di chuyển',         'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
            ['name'=>'Squat tạ đòn Olympic nhịp nhanh','equip'=>'Olympic Barbell + Squat Rack','keys'=>['squat rack']],
            ['name'=>'Deadlift tạ đòn nhịp nhanh',     'equip'=>'Olympic Barbell',             'keys'=>['olympic barbell']],
            ['name'=>'Ép đùi Leg Press nhịp nhanh',    'equip'=>'Leg Press Machine',           'keys'=>['leg press']],
            ['name'=>'Duỗi chân máy (Leg Extension)',  'equip'=>'Leg Extension Machine',       'keys'=>['leg extension']],
            ['name'=>'Cuộn đùi sau (Leg Curl)',        'equip'=>'Leg Curl Machine',            'keys'=>['leg curl']],
            ['name'=>'Fly ngực máy (Pec Deck)',        'equip'=>'Pec Deck Fly Machine',        'keys'=>['pec deck']],
            ['name'=>'Smith Squat nhịp nhanh',         'equip'=>'Smith Machine',               'keys'=>['smith machine']],
            ['name'=>'Đẩy vai máy nhịp nhanh',         'equip'=>'Shoulder Press Machine',      'keys'=>['shoulder press']],
            ['name'=>'Burpee tại chỗ liên tục',        'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Mountain Climber tại chỗ',       'equip'=>'Thảm tập',                    'keys'=>['*']],
        ],
        'cooldown' => [
            ['name'=>'Kéo giãn cơ đùi trước đứng',    'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn cơ bắp chân nghiêng',  'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn cơ lưng dưới ngồi',    'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn cơ hông ngồi xổm',     'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn cơ ngực mở rộng',      'equip'=>'Thảm tập',                    'keys'=>['*']],
        ],
    ],

    'Tăng cơ' => [
        'warmup' => [
            ['name'=>'Đẩy ngực tạ nhẹ khởi động',     'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
            ['name'=>'Kéo cáp nhẹ khởi động',         'equip'=>'Lat Pulldown Machine',        'keys'=>['lat pulldown']],
            ['name'=>'Chạy bộ nhẹ khởi động',         'equip'=>'Commercial Treadmill',        'keys'=>['treadmill']],
            ['name'=>'Đạp xe nhẹ khởi động',          'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
            ['name'=>'Xoay khớp vai và cổ tay',       'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Hip hinge bodyweight khởi động', 'equip'=>'Thảm tập',                    'keys'=>['*']],
        ],
        'main' => [
            ['name'=>'Đẩy ngực tạ đòn (Bench Press)', 'equip'=>'Adjustable Bench + Barbell',  'keys'=>['adjustable bench']],
            ['name'=>'Kéo cáp xuống rộng tay',        'equip'=>'Lat Pulldown Machine',        'keys'=>['lat pulldown']],
            ['name'=>'Squat tạ đòn Olympic',           'equip'=>'Olympic Barbell + Squat Rack','keys'=>['squat rack']],
            ['name'=>'Deadlift tạ đòn nặng',           'equip'=>'Olympic Barbell',             'keys'=>['olympic barbell']],
            ['name'=>'Đẩy vai tạ đòn (Overhead Press)','equip'=>'Olympic Barbell',            'keys'=>['olympic barbell']],
            ['name'=>'Kéo lưng ngồi nặng (Seated Row)','equip'=>'Seated Row Machine',         'keys'=>['seated row']],
            ['name'=>'Đẩy ngực nghiêng tạ đôi',       'equip'=>'Adjustable Bench + Dumbbell', 'keys'=>['adjustable bench']],
            ['name'=>'Fly ngực tạ đôi nằm',           'equip'=>'Adjustable Bench + Dumbbell', 'keys'=>['adjustable bench']],
            ['name'=>'Kéo cáp ngực (Cable Fly)',       'equip'=>'Cable Crossover',             'keys'=>['cable crossover']],
            ['name'=>'Đẩy ngực máy tạ nặng',          'equip'=>'Chest Press Machine',         'keys'=>['chest press machine']],
            ['name'=>'Fly ngực máy (Pec Deck Fly)',   'equip'=>'Pec Deck Fly Machine',        'keys'=>['pec deck']],
            ['name'=>'Smith Squat tạ nặng',            'equip'=>'Smith Machine',               'keys'=>['smith machine']],
            ['name'=>'Ép đùi Leg Press tạ nặng',      'equip'=>'Leg Press Machine',           'keys'=>['leg press']],
            ['name'=>'Duỗi chân máy (Leg Extension)', 'equip'=>'Leg Extension Machine',       'keys'=>['leg extension']],
            ['name'=>'Cuộn đùi sau (Leg Curl)',        'equip'=>'Leg Curl Machine',            'keys'=>['leg curl']],
            ['name'=>'Đẩy vai máy tạ nặng',           'equip'=>'Shoulder Press Machine',      'keys'=>['shoulder press']],
            ['name'=>'Hít đất nâng cao (Diamond Push-up)','equip'=>'Thảm tập',               'keys'=>['*']],
        ],
        'cooldown' => [
            ['name'=>'Kéo giãn cơ ngực trên ghế',    'equip'=>'Adjustable Bench',            'keys'=>['adjustable bench']],
            ['name'=>'Kéo giãn cơ lưng xà máy',      'equip'=>'Lat Pulldown Machine',        'keys'=>['lat pulldown']],
            ['name'=>'Kéo giãn cơ đùi sau đứng',     'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn cơ vai chéo ngực',    'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn cơ tay sau (Tricep)', 'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn cơ lưng dưới nằm',   'equip'=>'Thảm tập',                    'keys'=>['*']],
        ],
    ],

    'Tăng sức bền' => [
        'warmup' => [
            ['name'=>'Chạy bộ dần tăng tốc độ',      'equip'=>'Commercial Treadmill',        'keys'=>['treadmill']],
            ['name'=>'Đạp xe tăng dần cường độ',      'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
            ['name'=>'Leo thang nhẹ khởi động',       'equip'=>'Stair Climber',               'keys'=>['stair climber']],
            ['name'=>'Chạy elip tốc độ trung bình',   'equip'=>'Elliptical Trainer',          'keys'=>['elliptical']],
            ['name'=>'Nhảy jumping jack 2 phút',      'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Chạy bộ nhẹ tại chỗ',          'equip'=>'Thảm tập',                    'keys'=>['*']],
        ],
        'main' => [
            ['name'=>'Chạy bộ Zone 2 liên tục (65-75% max HR)','equip'=>'Commercial Treadmill','keys'=>['treadmill']],
            ['name'=>'Đạp xe Zone 2 liên tục',        'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
            ['name'=>'Leo thang bền bỉ liên tục',     'equip'=>'Stair Climber',               'keys'=>['stair climber']],
            ['name'=>'Chạy elip Zone 2 dài',          'equip'=>'Elliptical Trainer',          'keys'=>['elliptical']],
            ['name'=>'Kéo cáp rep cao (Lat Pulldown)','equip'=>'Lat Pulldown Machine',        'keys'=>['lat pulldown']],
            ['name'=>'Kéo lưng rep cao (Seated Row)', 'equip'=>'Seated Row Machine',          'keys'=>['seated row']],
            ['name'=>'Đẩy ngực máy rep cao',          'equip'=>'Chest Press Machine',         'keys'=>['chest press machine']],
            ['name'=>'Kéo cáp đứng rep cao',          'equip'=>'Cable Crossover',             'keys'=>['cable crossover']],
            ['name'=>'Squat tạ nhẹ liên tục',         'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
            ['name'=>'Lunge tạ tay bước dài',         'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
            ['name'=>'Deadlift tạ nhẹ rep cao',       'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
            ['name'=>'Duỗi chân rep cao (Leg Ext)',   'equip'=>'Leg Extension Machine',       'keys'=>['leg extension']],
            ['name'=>'Cuộn đùi rep cao (Leg Curl)',   'equip'=>'Leg Curl Machine',            'keys'=>['leg curl']],
            ['name'=>'Ép đùi Leg Press rep cao',      'equip'=>'Leg Press Machine',           'keys'=>['leg press']],
            ['name'=>'Đẩy vai tạ nhẹ rep cao',        'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
            ['name'=>'Squat nhảy (Jump Squat)',       'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Burpee liên tục',               'equip'=>'Thảm tập',                    'keys'=>['*']],
        ],
        'cooldown' => [
            ['name'=>'Đi bộ chậm hạ nhịp tim',       'equip'=>'Commercial Treadmill',        'keys'=>['treadmill']],
            ['name'=>'Đạp xe chậm hạ nhịp tim',       'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
            ['name'=>'Kéo giãn cơ bắp chân tường',   'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn cơ đùi trước ngồi',   'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn cơ hông tiến (Lunge Stretch)','equip'=>'Thảm tập',            'keys'=>['*']],
            ['name'=>'Hít thở sâu nằm phục hồi',     'equip'=>'Thảm tập',                    'keys'=>['*']],
        ],
    ],

    'Duy trì thể hình' => [
        'warmup' => [
            ['name'=>'Chạy bộ nhẹ khởi động',        'equip'=>'Commercial Treadmill',        'keys'=>['treadmill']],
            ['name'=>'Đạp xe nhẹ khởi động',          'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
            ['name'=>'Chạy elip nhẹ khởi động',       'equip'=>'Elliptical Trainer',          'keys'=>['elliptical']],
            ['name'=>'Xoay khớp toàn thân',           'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Arm circle + hip circle',       'equip'=>'Thảm tập',                    'keys'=>['*']],
        ],
        'main' => [
            ['name'=>'Đẩy ngực tạ đòn vừa (Bench Press)','equip'=>'Adjustable Bench + Barbell','keys'=>['adjustable bench']],
            ['name'=>'Kéo cáp xuống (Lat Pulldown)',  'equip'=>'Lat Pulldown Machine',        'keys'=>['lat pulldown']],
            ['name'=>'Squat tạ đòn cân bằng',         'equip'=>'Olympic Barbell + Squat Rack','keys'=>['squat rack']],
            ['name'=>'Kéo lưng ngồi (Seated Row)',    'equip'=>'Seated Row Machine',          'keys'=>['seated row']],
            ['name'=>'Đẩy ngực máy vừa',              'equip'=>'Chest Press Machine',         'keys'=>['chest press machine']],
            ['name'=>'Kéo cáp ngực (Cable Fly)',      'equip'=>'Cable Crossover',             'keys'=>['cable crossover']],
            ['name'=>'Đẩy vai tạ đôi vừa',            'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
            ['name'=>'Curl tay tạ đôi (Bicep Curl)',  'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
            ['name'=>'Ép đùi Leg Press vừa',          'equip'=>'Leg Press Machine',           'keys'=>['leg press']],
            ['name'=>'Đẩy vai máy vừa',               'equip'=>'Shoulder Press Machine',      'keys'=>['shoulder press']],
            ['name'=>'Fly ngực máy (Pec Deck)',       'equip'=>'Pec Deck Fly Machine',        'keys'=>['pec deck']],
            ['name'=>'Smith Squat vừa',               'equip'=>'Smith Machine',               'keys'=>['smith machine']],
            ['name'=>'Hít đất thường',                'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Plank giữ thăng bằng',          'equip'=>'Thảm tập',                    'keys'=>['*']],
        ],
        'cooldown' => [
            ['name'=>'Kéo giãn cơ ngực + vai',        'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn cơ đùi trước đứng',   'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn lưng dưới (Cat-Cow)', 'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn cơ hông',              'equip'=>'Thảm tập',                    'keys'=>['*']],
            ['name'=>'Kéo giãn cơ tay sau (Tricep)', 'equip'=>'Thảm tập',                    'keys'=>['*']],
        ],
    ],
];

// ═══════════════════════════════════════════════════════════════
// BUOC 3: LOC BAI THEO THIET BI + PICK
// ═══════════════════════════════════════════════════════════════

$equip_lower = strtolower($equip);

function equipMatch(array $ex, string $equip_lower): bool {
    foreach ($ex['keys'] as $key) {
        if ($key === '*') return true;
        if (str_contains($equip_lower, strtolower($key))) return true;
    }
    return false;
}

function pickExercises(array $pool, string $equip_lower, int $n): array {
    $matched = array_values(array_filter($pool, fn($e) => equipMatch($e, $equip_lower)));
    // Uu tien thiet bi that (keys != ['*'])
    $real = array_values(array_filter($matched, fn($e) => !in_array('*', $e['keys']) || count($e['keys']) > 1));
    $body = array_values(array_filter($matched, fn($e) => in_array('*', $e['keys']) && count($e['keys']) === 1));
    shuffle($real);
    shuffle($body);
    return array_slice(array_merge($real, $body), 0, $n);
}

$goal_pool = $pools[$goal] ?? $pools['Tăng cơ'];

$warmup_exs   = pickExercises($goal_pool['warmup'],   $equip_lower, 3);
$main_exs     = pickExercises($goal_pool['main'],     $equip_lower, 5);
$cooldown_exs = pickExercises($goal_pool['cooldown'], $equip_lower, 3);

// Fallback
$fb_w = ['name'=>'Xoay khớp tại chỗ',    'equip'=>'Thảm tập'];
$fb_m = ['name'=>'Hít đất tại chỗ',      'equip'=>'Thảm tập'];
$fb_c = ['name'=>'Kéo giãn toàn thân',   'equip'=>'Thảm tập'];
while (count($warmup_exs)   < 3) $warmup_exs[]   = $fb_w;
while (count($main_exs)     < 5) $main_exs[]     = $fb_m;
while (count($cooldown_exs) < 3) $cooldown_exs[] = $fb_c;

// ═══════════════════════════════════════════════════════════════
// BUOC 4: BUILD PROMPT VAN BAN DAY DU
// ═══════════════════════════════════════════════════════════════

$w_mins = [3, 2, 2];

$warmup_block = '';
for ($i = 0; $i < 3; $i++) {
    $e = $warmup_exs[$i];
    $warmup_block .= "- {$e['name']} [{$e['equip']}] • {$w_mins[$i]} phút • ~{$kw[$i]} kcal\n";
}

$main_block = '';
for ($i = 0; $i < 5; $i++) {
    $e = $main_exs[$i];
    $main_block .= "- {$e['name']} [{$e['equip']}] • {$s}sets × {$rp}reps • nghỉ {$rs}s • ~{$km[$i]} kcal\n";
}

$cooldown_block = '';
for ($i = 0; $i < 3; $i++) {
    $e = $cooldown_exs[$i];
    $cooldown_block .= "- {$e['name']} [{$e['equip']}] • {$cd_secs[$i]} giây mỗi bên • ~{$kc[$i]} kcal\n";
}

$prompt = <<<PROMPT
Output CHÍNH XÁC đoạn văn bản dưới đây. KHÔNG thay đổi bất kỳ con số nào.

### 🔥 KHỞI ĐỘNG ({$t_warmup} phút) — tổng {$kcal_warmup} kcal
{$warmup_block}
### 💪 BÀI TẬP CHÍNH ({$t_main} phút) — tổng {$kcal_main} kcal
*{$gc['note']}*
{$main_block}
### 🧘 GIÃN CƠ ({$t_cooldown} phút) — tổng {$kcal_cooldown} kcal
{$cooldown_block}
### 📊 TỔNG KẾT
Tổng kcal: ~{$kcal_total} kcal | Đạt {$pct_achieved}% mục tiêu đốt calo | Lời khuyên: {$advice}
PROMPT;

// ── Groq API ─────────────────────────────────────────────────
$groq_api_key = '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';
$groq_url     = 'https://api.groq.com/openai/v1/chat/completions';
$groq_model   = 'llama-3.1-8b-instant';

$host = $_SERVER['HTTP_HOST'] ?? '';
$ssl_verify = !(str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'));

$payload = json_encode([
    'model'       => $groq_model,
    'stream'      => true,
    'temperature' => 0.0,
    'max_tokens'  => 1200,
    'messages'    => [
        [
            'role'    => 'system',
            'content' => 'Bạn là máy in văn bản. Nhiệm vụ DUY NHẤT: output LẠI CHÍNH XÁC nội dung người dùng cung cấp, KHÔNG thay đổi bất kỳ con số nào (kcal, sets, reps, giây, phút, %), KHÔNG thêm/bớt/sửa bất kỳ từ nào, KHÔNG giải thích, KHÔNG thêm lời mở đầu hay kết thúc. Chỉ in lại nguyên văn.',
        ],
        [
            'role'    => 'user',
            'content' => $prompt,
        ],
    ],
], JSON_UNESCAPED_UNICODE);

$lineBuffer = '';
$doneSent   = false;

$ch = curl_init($groq_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groq_api_key,
    ],
    CURLOPT_SSL_VERIFYPEER => $ssl_verify,
    CURLOPT_SSL_VERIFYHOST => $ssl_verify ? 2 : 0,
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 10,

    CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use (&$lineBuffer, &$doneSent) {
        $lineBuffer .= $chunk;
        while (($pos = strpos($lineBuffer, "\n")) !== false) {
            $line       = trim(substr($lineBuffer, 0, $pos));
            $lineBuffer = substr($lineBuffer, $pos + 1);
            if (!$line) continue;
            if ($line === 'data: [DONE]') { sseDone(); $doneSent = true; continue; }
            if (strpos($line, 'data: ') !== 0) continue;
            $obj = json_decode(substr($line, 6), true);
            if (!is_array($obj)) continue;
            $token = $obj['choices'][0]['delta']['content'] ?? '';
            if ($token !== '') sseToken($token);
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
    if ($curlErrno)                         sseError("Groq không phản hồi (cURL #{$curlErrno}): {$curlError}");
    elseif ($httpCode === 401)              sseError("Groq API key không hợp lệ.");
    elseif ($httpCode === 429)              sseError("Groq rate limit. Thử lại sau vài giây.");
    elseif ($httpCode && $httpCode !== 200) sseError("Groq lỗi HTTP {$httpCode}.");
    else                                    sseDone();
}
?>
