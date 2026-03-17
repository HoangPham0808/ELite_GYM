<?php
// Profile_function.php — ELITE GYM
// Đặt tại: ELITE_GYM/Internal/Layout/Profile/Profile_function.php

ob_start();
ini_set('display_errors', 0);
error_reporting(0);

// ── Timezone Việt Nam (UTC+7) — bắt buộc để date() trả đúng giờ ──
date_default_timezone_set('Asia/Ho_Chi_Minh');

// ── Session ──
if (session_status() === PHP_SESSION_NONE) {
    session_name('PHPSESSID');
    session_start();
}

// ── JSON output helper ──
function out(array $d): void {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Auth check (không dùng require_once để tránh HTML output) ──
$sid      = session_id();
$role     = $_SESSION['role']       ?? '';
$position = $_SESSION['position']   ?? '';
$acc_id   = (int)($_SESSION['account_id'] ?? 0);

$allowed = ($acc_id > 0) && (
    in_array($role, ['Employee','Admin']) ||
    in_array($position, ['Personal Trainer','Receptionist'])
);

if (!$allowed) {
    out(['ok'=>false,'success'=>false,'msg'=>'Chua dang nhap','debug'=>"role=$role pos=$position acc=$acc_id sid=$sid"]);
}

// ── DB ──
$db = @new mysqli('localhost','root','','datn');
if ($db->connect_error) {
    out(['ok'=>false,'success'=>false,'msg'=>'DB error: '.$db->connect_error]);
}
$db->set_charset('utf8mb4');

// ── Read action ──
$action = trim($_GET['action'] ?? '');
$body   = [];
if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = $raw ? (json_decode($raw,true) ?? []) : [];
    $action = trim($body['action'] ?? $_POST['action'] ?? '');
}

// ════════════════════════════════════════
// PROFILE
// ════════════════════════════════════════
if ($action === 'get_profile') {
    $st = $db->prepare("SELECT employee_id,full_name,date_of_birth,gender,phone,email,address,hire_date,position FROM Employee WHERE account_id=? LIMIT 1");
    $st->bind_param('i',$acc_id); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();
    if (!$row) out(['success'=>false,'message'=>'Khong tim thay ho so']);
    out(['success'=>true,'data'=>$row]);
}

if ($action === 'update_profile') {
    $fn   = trim($body['full_name']??'');
    if (!$fn) out(['success'=>false,'message'=>'Ho va ten khong duoc de trong']);
    $ph   = trim($body['phone']??'');
    $em   = trim($body['email']??'');
    $dob  = ($body['date_of_birth']??'') ?: null;
    $gen  = in_array($body['gender']??'',['Male','Female','Other']) ? $body['gender'] : null;
    $adr  = trim($body['address']??'');
    $st   = $db->prepare("UPDATE Employee SET full_name=?,phone=?,email=?,date_of_birth=?,gender=?,address=? WHERE account_id=?");
    $st->bind_param('ssssssi',$fn,$ph,$em,$dob,$gen,$adr,$acc_id);
    if ($st->execute()) { $_SESSION['ho_ten']=$_SESSION['full_name']=$fn; out(['success'=>true,'message'=>'Cap nhat thanh cong']); }
    out(['success'=>false,'message'=>'Loi: '.$db->error]);
}

if ($action === 'change_password') {
    $cur = $body['current_password']??'';
    $npw = $body['new_password']??'';
    if (strlen($npw)<6) out(['success'=>false,'message'=>'Mat khau moi phai co it nhat 6 ky tu']);
    $st  = $db->prepare("SELECT password FROM Account WHERE account_id=? LIMIT 1");
    $st->bind_param('i',$acc_id); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();
    if (!$row) out(['success'=>false,'message'=>'Tai khoan khong ton tai']);
    if (!password_verify($cur,$row['password']) && $cur !== $row['password'])
        out(['success'=>false,'message'=>'Mat khau hien tai khong dung']);
    $h   = password_hash($npw,PASSWORD_DEFAULT);
    $st2 = $db->prepare("UPDATE Account SET password=? WHERE account_id=?");
    $st2->bind_param('si',$h,$acc_id);
    out($st2->execute() ? ['success'=>true,'message'=>'Doi mat khau thanh cong'] : ['success'=>false,'message'=>'Loi cap nhat']);
}

// ════════════════════════════════════════
// GPS CHECKIN
// ════════════════════════════════════════

// Ensure GPS columns exist (silent)
// Thêm cột GPS an toàn — check information_schema trước để tránh lỗi trên MySQL 5.7/MariaDB cũ
$gps_cols = ['checkin_lat','checkin_lng','checkout_lat','checkout_lng'];
$exist_res = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='datn' AND TABLE_NAME='Attendance'");
$exist_cols = [];
if ($exist_res) while ($ec = $exist_res->fetch_assoc()) $exist_cols[] = $ec['COLUMN_NAME'];
foreach ($gps_cols as $gc) {
    if (!in_array($gc, $exist_cols)) {
        $db->query("ALTER TABLE Attendance ADD COLUMN `$gc` DOUBLE NULL");
    }
}

$today = date('Y-m-d');

function getEid(mysqli $db, int $aid): ?int {
    $st = $db->prepare("SELECT employee_id FROM Employee WHERE account_id=? LIMIT 1");
    $st->bind_param('i',$aid); $st->execute();
    $r  = $st->get_result()->fetch_assoc(); $st->close();
    return $r ? (int)$r['employee_id'] : null;
}

// ── Gym settings ──
if ($action === 'get_gym_settings') {
    $res = $db->query("SELECT setting_key,setting_value FROM gym_settings");
    $cfg = [];
    if ($res) while ($r=$res->fetch_assoc()) $cfg[$r['setting_key']]=$r['setting_value'];
    out([
        'ok'     => true,
        'lat'    => floatval($cfg['gym_lat']           ?? 21.0285),
        'lng'    => floatval($cfg['gym_lng']           ?? 105.8542),
        'radius' => intval( $cfg['gym_radius_m']       ?? 100),
        'name'   =>         $cfg['gym_location_name']  ?? 'Elite Gym',
        'check'  => intval( $cfg['location_check']     ?? 1),
    ]);
}

// ── Today status ──
if ($action === 'get_today_status') {
    $eid = getEid($db,$acc_id);
    if (!$eid) out(['ok'=>false,'msg'=>"Khong tim thay nhan vien (account_id=$acc_id)"]);
    $st  = $db->prepare("SELECT status,check_in,check_out FROM Attendance WHERE employee_id=? AND work_date=? LIMIT 1");
    $st->bind_param('is',$eid,$today); $st->execute();
    $rec = $st->get_result()->fetch_assoc(); $st->close();
    out(['ok'=>true,'record'=>$rec?:null]);
}

// ── Check In ──
if ($action === 'checkin') {
    $eid = getEid($db,$acc_id);
    if (!$eid) out(['ok'=>false,'msg'=>'Khong tim thay nhan vien']);

    $st  = $db->prepare("SELECT attendance_id,check_in FROM Attendance WHERE employee_id=? AND work_date=? LIMIT 1");
    $st->bind_param('is',$eid,$today); $st->execute();
    $ex  = $st->get_result()->fetch_assoc(); $st->close();
    if ($ex && $ex['check_in']) out(['ok'=>false,'msg'=>'Ban da check in hom nay roi!']);

    $now    = date('H:i:s');
    $lat    = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
    $lng    = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
    $status = (intval(date('H')) >= 9) ? 'Late' : 'Present';

    if ($ex) {
        $s = $db->prepare("UPDATE Attendance SET check_in=?,status=?,checkin_lat=?,checkin_lng=? WHERE attendance_id=?");
        $s->bind_param('ssddi',$now,$status,$lat,$lng,$ex['attendance_id']); $s->execute(); $s->close();
    } else {
        $s = $db->prepare("INSERT INTO Attendance (employee_id,work_date,check_in,status,checkin_lat,checkin_lng) VALUES (?,?,?,?,?,?)");
        $s->bind_param('isssdd',$eid,$today,$now,$status,$lat,$lng); $s->execute(); $s->close();
    }
    out(['ok'=>true,'time'=>substr($now,0,5),'status'=>$status]);
}

// ── Check Out ──
if ($action === 'checkout') {
    $eid = getEid($db,$acc_id);
    if (!$eid) out(['ok'=>false,'msg'=>'Khong tim thay nhan vien']);

    $st  = $db->prepare("SELECT attendance_id,check_in,check_out FROM Attendance WHERE employee_id=? AND work_date=? LIMIT 1");
    $st->bind_param('is',$eid,$today); $st->execute();
    $rec = $st->get_result()->fetch_assoc(); $st->close();
    if (!$rec || !$rec['check_in']) out(['ok'=>false,'msg'=>'Ban chua check in hom nay!']);
    if ($rec['check_out'])          out(['ok'=>false,'msg'=>'Ban da check out roi!']);

    $now  = date('H:i:s');
    $lat  = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
    $lng  = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
    $cin  = explode(':',$rec['check_in']);
    $cout = explode(':',$now);
    $mins = (intval($cout[0])*60+intval($cout[1])) - (intval($cin[0])*60+intval($cin[1]));
    $h    = floor(max(0,$mins)/60); $m = max(0,$mins)%60;
    $hrs  = "{$h}h".str_pad($m,2,'0',STR_PAD_LEFT)."p";

    $s = $db->prepare("UPDATE Attendance SET check_out=?,checkout_lat=?,checkout_lng=? WHERE attendance_id=?");
    $s->bind_param('sddi',$now,$lat,$lng,$rec['attendance_id']); $s->execute(); $s->close();
    out(['ok'=>true,'time'=>substr($now,0,5),'hours'=>$hrs]);
}

// ── History ──
if ($action === 'get_history') {
    $eid   = getEid($db,$acc_id);
    if (!$eid) out(['ok'=>false,'msg'=>'Khong tim thay nhan vien']);
    $limit = max(1,min(30,intval($_GET['limit']??7)));
    $st    = $db->prepare("SELECT work_date,check_in,check_out,status FROM Attendance WHERE employee_id=? ORDER BY work_date DESC LIMIT ?");
    $st->bind_param('ii',$eid,$limit); $st->execute();
    $rows  = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
    out(['ok'=>true,'records'=>$rows]);
}

out(['ok'=>false,'success'=>false,'msg'=>"Action '$action' khong hop le",'debug'=>"acc=$acc_id role=$role"]);
?>
