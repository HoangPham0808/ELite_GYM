<?php
/**
 * GPS_function.php — AJAX handler CHỈ cho GPS Admin
 * Vị trí: Internal/Layout/Setting/GPS/GPS_function.php
 */
ini_set('display_errors', 0);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_name('PHPSESSID');
    @session_start();
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $data): void {
    ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Chỉ Admin được dùng file này
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    jsonOut(['ok' => false, 'msg' => 'Không có quyền truy cập']);
}

// Load DB
$dbFile = __DIR__ . '/../../../../Database/db.php';
if (!file_exists($dbFile)) jsonOut(['ok'=>false,'msg'=>'Không tìm thấy db.php']);
require_once $dbFile;
if (!isset($conn) || $conn->connect_error) jsonOut(['ok'=>false,'msg'=>'Lỗi DB: '.($conn->connect_error??'')]);

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

// ════════════════════════════════════════════════════════════
// SEARCH LOCATION — PHP proxy Nominatim (tránh CORS/firewall)
// ════════════════════════════════════════════════════════════
if ($action === 'search_location') {
    $q = trim($_GET['q'] ?? '');
    if (!$q) jsonOut(['ok'=>false,'results'=>[]]);
    $url = 'https://nominatim.openstreetmap.org/search?'
         . http_build_query(['q'=>$q,'format'=>'json','limit'=>5,'accept-language'=>'vi']);
    $ctx = stream_context_create([
        'http'=>['method'=>'GET','header'=>"User-Agent: EliteGym/1.0\r\n",'timeout'=>8],
        'ssl' =>['verify_peer'=>false,'verify_peer_name'=>false],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) jsonOut(['ok'=>false,'results'=>[],'msg'=>'Không kết nối Nominatim']);
    jsonOut(['ok'=>true,'results'=>json_decode($raw,true)??[]]);
}

// ════════════════════════════════════════════════════════════
// LƯU VỊ TRÍ PHÒNG TẬP
// ════════════════════════════════════════════════════════════
if ($action === 'save_gym_location') {
    $lat    = floatval($_POST['lat']    ?? 0);
    $lng    = floatval($_POST['lng']    ?? 0);
    $radius = max(50, intval($_POST['radius'] ?? 100));
    $name   = trim($_POST['name']       ?? 'Elite Gym');
    $check  = intval($_POST['location_check'] ?? 1);
    $by     = (int)($_SESSION['account_id']   ?? 0);
    if ($lat==0 && $lng==0) jsonOut(['ok'=>false,'msg'=>'Tọa độ không hợp lệ']);
    $stmt = $conn->prepare("INSERT INTO gym_settings (setting_key,setting_value,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)");
    foreach ([['gym_lat',(string)$lat],['gym_lng',(string)$lng],['gym_radius_m',(string)$radius],['gym_location_name',$name],['location_check',(string)$check]] as [$k,$v]) {
        $stmt->bind_param('ssi',$k,$v,$by); $stmt->execute();
    }
    $stmt->close();
    jsonOut(['ok'=>true,'msg'=>'Đã lưu cài đặt GPS!']);
}

// ════════════════════════════════════════════════════════════
// THỐNG KÊ CHẤM CÔNG HÔM NAY
// ════════════════════════════════════════════════════════════
if ($action === 'get_stats') {
    $date    = $conn->real_escape_string($_GET['date'] ?? date('Y-m-d'));
    $total   = (int)$conn->query("SELECT COUNT(*) AS c FROM Employee")->fetch_assoc()['c'];
    $present = (int)$conn->query("SELECT COUNT(*) AS c FROM Attendance WHERE work_date='$date' AND status IN('Present','Late')")->fetch_assoc()['c'];
    $gpsRes  = $conn->query("SELECT COUNT(*) AS c FROM Attendance WHERE work_date='$date' AND checkin_lat IS NOT NULL");
    $gps     = $gpsRes ? (int)$gpsRes->fetch_assoc()['c'] : 0;
    jsonOut(['ok'=>true,'total'=>$total,'present'=>$present,'not_yet'=>max(0,$total-$present),'gps_count'=>$gps]);
}

jsonOut(['ok'=>false,'msg'=>"Action '$action' không hợp lệ"]);
?>
