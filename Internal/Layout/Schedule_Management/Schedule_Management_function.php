<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require_once '../../../Database/db.php';

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Cannot connect to DB']);
    exit;
}
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

// ── STATS ──────────────────────────────────────────────────────────
case 'get_stats':
    $total     = $conn->query("SELECT COUNT(*) c FROM TrainingClass")->fetch_assoc()['c'];
    $today     = $conn->query("SELECT COUNT(*) c FROM TrainingClass WHERE DATE(class_time)=CURDATE()")->fetch_assoc()['c'];
    $this_week = $conn->query("SELECT COUNT(*) c FROM TrainingClass WHERE YEARWEEK(class_time,1)=YEARWEEK(CURDATE(),1)")->fetch_assoc()['c'];
    $registrations   = $conn->query("SELECT COUNT(*) c FROM ClassRegistration")->fetch_assoc()['c'];
    $hlv       = $conn->query("SELECT COUNT(DISTINCT trainer_id) c FROM TrainingClass WHERE trainer_id IS NOT NULL")->fetch_assoc()['c'];
    $sap_dien  = $conn->query("SELECT COUNT(*) c FROM TrainingClass WHERE class_time > NOW() AND class_time <= DATE_ADD(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['c'];
    echo json_encode(['success'=>true,'total'=>(int)$total,'today'=>(int)$today,
        'this_week'=>(int)$this_week,'dang_ky'=>(int)$registrations,
        'hlv'=>(int)$hlv,'sap_dien'=>(int)$sap_dien]);
    break;

// ── LẤY LỊCH TẬP (danh sách + phân trang) ────────────────────────
case 'get_schedules':
    $page   = max(1,(int)($_GET['page']??1));
    $limit  = (int)($_GET['limit']??15);
    $search = trim($_GET['search']??'');
    $trainer_id = trim($_GET['hlv_id']??'');
    $from   = trim($_GET['from']??'');
    $to     = trim($_GET['to']??'');
    $offset = ($page-1)*$limit;

    $w=[]; $p=[]; $t='';
    if ($search!=='') { $w[]="tc.class_name LIKE ?"; $p[]="%$search%"; $t.='s'; }
    if ($trainer_id!=='') { $w[]="tc.trainer_id=?"; $p[]=(int)$trainer_id; $t.='i'; }
    if ($from!=='')   { $w[]="DATE(tc.class_time)>=?"; $p[]=$from; $t.='s'; }
    if ($to!=='')     { $w[]="DATE(tc.class_time)<=?"; $p[]=$to;   $t.='s'; }
    $ws = $w ? 'WHERE '.implode(' AND ',$w) : '';

    $cs=$conn->prepare("SELECT COUNT(*) total FROM TrainingClass tc $ws");
    if ($p) $cs->bind_param($t,...$p); $cs->execute();
    $total=$cs->get_result()->fetch_assoc()['total'];

    $ds=$conn->prepare("
        SELECT tc.*, e.full_name AS trainer_name,
               (SELECT COUNT(*) FROM ClassRegistration WHERE class_id=tc.class_id) AS registration_count
        FROM TrainingClass tc
        LEFT JOIN Employee e ON e.employee_id=tc.trainer_id
        $ws ORDER BY tc.class_time DESC LIMIT ? OFFSET ?");
    $dp=$p; $dt=$t; $dp[]=$limit; $dp[]=$offset; $dt.='ii';
    $ds->bind_param($dt,...$dp); $ds->execute();
    $rows=$ds->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success'=>true,'data'=>$rows,'total'=>(int)$total,'page'=>$page,'totalPages'=>max(1,ceil($total/$limit))]);
    break;

// ── LẤY LỊCH THEO TUẦN (cho calendar view) ───────────────────────
case 'get_week_schedules':
    $week_start = trim($_GET['week_start']??date('Y-m-d', strtotime('monday this week')));
    $week_end   = date('Y-m-d', strtotime($week_start.' +6 days'));
    $s=$conn->prepare("
        SELECT tc.*, e.full_name AS trainer_name,
               (SELECT COUNT(*) FROM ClassRegistration WHERE class_id=tc.class_id) AS registration_count
        FROM TrainingClass tc
        LEFT JOIN Employee e ON e.employee_id=tc.trainer_id
        WHERE DATE(tc.class_time) BETWEEN ? AND ?
        ORDER BY tc.class_time ASC");
    $s->bind_param('ss',$week_start,$week_end); $s->execute();
    $rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success'=>true,'data'=>$rows,'week_start'=>$week_start,'week_end'=>$week_end]);
    break;

// ── CHI TIẾT BUỔI TẬP ────────────────────────────────────────────
case 'get_schedule_detail':
    $id=(int)($_GET['id']??0);
    $s=$conn->prepare("SELECT tc.*, e.full_name AS trainer_name, e.phone AS trainer_phone
        FROM TrainingClass tc LEFT JOIN Employee e ON e.employee_id=tc.trainer_id
        WHERE tc.class_id=?");
    $s->bind_param('i',$id); $s->execute();
    $lt=$s->get_result()->fetch_assoc();

    $s2=$conn->prepare("SELECT dk.*, c.full_name, c.phone, c.email
        FROM ClassRegistration dk JOIN Customer c ON c.customer_id=dk.customer_id
        WHERE dk.class_id=? ORDER BY c.full_name");
    $s2->bind_param('i',$id); $s2->execute();
    $members=$s2->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success'=>true,'schedule'=>$lt,'members'=>$members]);
    break;

// ── THÊM BUỔI TẬP ────────────────────────────────────────────────
case 'add_schedule':
    $class_name      = trim($_POST['class_name']??'');
    $trainer_id   = (int)($_POST['trainer_id']??0) ?: null;
    $class_time= trim($_POST['class_time']??'');
    if (!$class_name||!$class_time) { echo json_encode(['success'=>false,'message'=>'Class name and time are required']); exit; }
    $s=$conn->prepare("INSERT INTO TrainingClass (class_name,trainer_id,class_time) VALUES(?,?,?)");
    $s->bind_param('sis',$class_name,$trainer_id,$class_time);
    echo json_encode($s->execute()
        ? ['success'=>true,'message'=>'Training class added successfully','id'=>$conn->insert_id]
        : ['success'=>false,'message'=>$conn->error]);
    break;

// ── SỬA BUỔI TẬP ─────────────────────────────────────────────────
case 'update_schedule':
    $id       = (int)($_POST['id']??0);
    $class_name      = trim($_POST['class_name']??'');
    $trainer_id   = (int)($_POST['trainer_id']??0) ?: null;
    $class_time= trim($_POST['class_time']??'');
    if (!$id||!$class_name||!$class_time) { echo json_encode(['success'=>false,'message'=>'Invalid data']); exit; }
    $s=$conn->prepare("UPDATE TrainingClass SET class_name=?,trainer_id=?,class_time=? WHERE class_id=?");
    $s->bind_param('sisi',$class_name,$trainer_id,$class_time,$id);
    echo json_encode($s->execute() ? ['success'=>true,'message'=>'Updated successfully'] : ['success'=>false,'message'=>$conn->error]);
    break;

// ── XÓA BUỔI TẬP ─────────────────────────────────────────────────
case 'delete_schedule':
    $id=(int)($_POST['id']??0);
    $conn->query("DELETE FROM ClassRegistration WHERE class_id=$id");
    $s=$conn->prepare("DELETE FROM TrainingClass WHERE class_id=?");
    $s->bind_param('i',$id);
    echo json_encode($s->execute() ? ['success'=>true,'message'=>'Training class deleted'] : ['success'=>false,'message'=>$conn->error]);
    break;

// ── ĐĂNG KÝ THAM GIA ─────────────────────────────────────────────
case 'add_registration':
    $class_id = (int)($_POST['class_id']??0);
    $customer_id   = (int)($_POST['customer_id']??0);
    if (!$class_id||!$customer_id) { echo json_encode(['success'=>false,'message'=>'Missing required data']); exit; }
    $chk=$conn->query("SELECT class_registration_id FROM ClassRegistration WHERE class_id=$class_id AND customer_id=$customer_id");
    if ($chk->num_rows>0) { echo json_encode(['success'=>false,'message'=>'Customer already registered for this class']); exit; }
    $s=$conn->prepare("INSERT INTO ClassRegistration (class_id,customer_id) VALUES(?,?)");
    $s->bind_param('ii',$class_id,$customer_id);
    echo json_encode($s->execute() ? ['success'=>true,'message'=>'Registration successful'] : ['success'=>false,'message'=>$conn->error]);
    break;

// ── HỦY ĐĂNG KÝ ──────────────────────────────────────────────────
case 'delete_registration':
    $id=(int)($_POST['id']??0);
    $s=$conn->prepare("DELETE FROM ClassRegistration WHERE class_registration_id=?");
    $s->bind_param('i',$id);
    echo json_encode($s->execute() ? ['success'=>true,'message'=>'Registration cancelled'] : ['success'=>false,'message'=>$conn->error]);
    break;

// ── DANH SÁCH HLV (cho dropdown) ─────────────────────────────────
case 'get_trainers':
    $rows=$conn->query("SELECT employee_id, full_name FROM Employee ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success'=>true,'data'=>$rows]);
    break;

// ── DANH SÁCH KHÁCH HÀNG (cho dropdown đăng ký) ──────────────────
case 'get_customers':
    $search = trim($_GET['search']??'');
    $s=$conn->prepare("SELECT customer_id, full_name, phone FROM Customer WHERE full_name LIKE ? ORDER BY full_name LIMIT 30");
    $like="%$search%"; $s->bind_param('s',$like); $s->execute();
    $rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success'=>true,'data'=>$rows]);
    break;

default:
    echo json_encode(['success'=>false,'message'=>'Invalid action']);
}
?>
