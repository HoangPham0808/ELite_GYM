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

$SEL = "
    SELECT tc.class_id, tc.class_name, tc.trainer_id, tc.room_id,
           tc.start_time, tc.end_time,
           e.full_name   AS trainer_name,
           e.phone       AS trainer_phone,
           gr.room_name,
           gr.capacity   AS room_capacity,
           pt.type_name  AS package_type_name,
           pt.color_code AS package_color,
           (SELECT COUNT(*) FROM ClassRegistration WHERE class_id = tc.class_id) AS registration_count";

$FROM_JOIN = "
    FROM TrainingClass tc
    LEFT JOIN Employee    e  ON e.employee_id  = tc.trainer_id
    LEFT JOIN GymRoom     gr ON gr.room_id     = tc.room_id
    LEFT JOIN PackageType pt ON pt.type_id     = gr.package_type_id";

switch ($action) {

case 'get_stats':
    $total     = $conn->query("SELECT COUNT(*) c FROM TrainingClass")->fetch_assoc()['c'];
    $today     = $conn->query("SELECT COUNT(*) c FROM TrainingClass WHERE DATE(start_time)=CURDATE()")->fetch_assoc()['c'];
    $this_week = $conn->query("SELECT COUNT(*) c FROM TrainingClass WHERE YEARWEEK(start_time,1)=YEARWEEK(CURDATE(),1)")->fetch_assoc()['c'];
    $registrations = $conn->query("SELECT COUNT(*) c FROM ClassRegistration")->fetch_assoc()['c'];
    $hlv       = $conn->query("SELECT COUNT(DISTINCT trainer_id) c FROM TrainingClass WHERE trainer_id IS NOT NULL")->fetch_assoc()['c'];
    $sap_dien  = $conn->query("SELECT COUNT(*) c FROM TrainingClass WHERE start_time > NOW() AND start_time <= DATE_ADD(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['c'];
    echo json_encode(['success'=>true,'total'=>(int)$total,'today'=>(int)$today,
        'this_week'=>(int)$this_week,'dang_ky'=>(int)$registrations,
        'hlv'=>(int)$hlv,'sap_dien'=>(int)$sap_dien]);
    break;

case 'get_schedules':
    $page     = max(1,(int)($_GET['page']??1));
    $limit    = (int)($_GET['limit']??15);
    $search   = trim($_GET['search']??'');
    $trainer_id = trim($_GET['hlv_id']??'');
    $room_id  = trim($_GET['room_id']??'');
    $from     = trim($_GET['from']??'');
    $to       = trim($_GET['to']??'');
    $offset   = ($page-1)*$limit;

    $w=[]; $p=[]; $t='';
    if ($search!=='')     { $w[]="tc.class_name LIKE ?"; $p[]="%$search%"; $t.='s'; }
    if ($trainer_id!=='') { $w[]="tc.trainer_id=?";  $p[]=(int)$trainer_id; $t.='i'; }
    if ($room_id!=='')    { $w[]="tc.room_id=?";     $p[]=(int)$room_id;   $t.='i'; }
    if ($from!=='')       { $w[]="DATE(tc.start_time)>=?"; $p[]=$from; $t.='s'; }
    if ($to!=='')         { $w[]="DATE(tc.start_time)<=?"; $p[]=$to;   $t.='s'; }
    $ws = $w ? 'WHERE '.implode(' AND ',$w) : '';

    $cs=$conn->prepare("SELECT COUNT(*) total FROM TrainingClass tc $ws");
    if ($p) $cs->bind_param($t,...$p); $cs->execute();
    $total=$cs->get_result()->fetch_assoc()['total'];

    $ds=$conn->prepare("$SEL $FROM_JOIN $ws ORDER BY tc.start_time DESC LIMIT ? OFFSET ?");
    $dp=$p; $dt=$t; $dp[]=$limit; $dp[]=$offset; $dt.='ii';
    $ds->bind_param($dt,...$dp); $ds->execute();
    $rows=$ds->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success'=>true,'data'=>$rows,'total'=>(int)$total,'page'=>$page,'totalPages'=>max(1,ceil($total/$limit))]);
    break;

case 'get_week_schedules':
    $week_start = trim($_GET['week_start']??date('Y-m-d', strtotime('monday this week')));
    $week_end   = date('Y-m-d', strtotime($week_start.' +6 days'));
    $s=$conn->prepare("$SEL $FROM_JOIN WHERE DATE(tc.start_time) BETWEEN ? AND ? ORDER BY tc.start_time ASC");
    $s->bind_param('ss',$week_start,$week_end); $s->execute();
    $rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success'=>true,'data'=>$rows,'week_start'=>$week_start,'week_end'=>$week_end]);
    break;

case 'get_schedule_detail':
    $id=(int)($_GET['id']??0);
    $s=$conn->prepare("$SEL $FROM_JOIN WHERE tc.class_id=?");
    $s->bind_param('i',$id); $s->execute();
    $lt=$s->get_result()->fetch_assoc();
    $s2=$conn->prepare("SELECT dk.*, c.full_name, c.phone, c.email
        FROM ClassRegistration dk JOIN Customer c ON c.customer_id=dk.customer_id
        WHERE dk.class_id=? ORDER BY c.full_name");
    $s2->bind_param('i',$id); $s2->execute();
    $members=$s2->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success'=>true,'schedule'=>$lt,'members'=>$members]);
    break;

case 'add_schedule':
    $class_name  = trim($_POST['class_name']??'');
    $trainer_id  = (int)($_POST['trainer_id']??0) ?: null;
    $room_id     = (int)($_POST['room_id']??0) ?: null;
    $start_time  = trim($_POST['start_time']??'');
    $end_time    = trim($_POST['end_time']??'') ?: null;
    if (!$class_name||!$start_time) { echo json_encode(['success'=>false,'message'=>'Tên lớp và giờ bắt đầu là bắt buộc']); exit; }
    /* Kiểm tra end_time > start_time */
    if ($end_time && strtotime($end_time) <= strtotime($start_time)) {
        echo json_encode(['success'=>false,'message'=>'Giờ kết thúc phải lớn hơn giờ bắt đầu']); exit;
    }

    /* Kiểm tra giờ phòng tập */
    if ($room_id) {
        $roomRow = $conn->query("SELECT room_name,open_time,close_time FROM GymRoom WHERE room_id=$room_id LIMIT 1")->fetch_assoc();
        if ($roomRow) {
            $tStart = date('H:i:s', strtotime($start_time));
            $tEnd   = $end_time ? date('H:i:s', strtotime($end_time)) : null;
            $tOpen  = $roomRow['open_time'];
            $tClose = $roomRow['close_time'];
            $rName  = $roomRow['room_name'];
            if ($tOpen  && $tStart < $tOpen)  { echo json_encode(['success'=>false,'message'=>"Giờ bắt đầu sớm hơn giờ mở cửa phòng $rName (" . substr($tOpen,0,5) . ")" ]); exit; }
            if ($tClose && $tStart > $tClose) { echo json_encode(['success'=>false,'message'=>"Giờ bắt đầu muộn hơn giờ đóng cửa phòng $rName (" . substr($tClose,0,5) . ")" ]); exit; }
            if ($tEnd && $tClose && $tEnd > $tClose) { echo json_encode(['success'=>false,'message'=>"Giờ kết thúc vượt quá giờ đóng cửa phòng $rName (" . substr($tClose,0,5) . ")" ]); exit; }
        }
    }

    /* Kiểm tra phòng bị trùng giờ trong cùng ngày
       Overlap: A.start < B.end AND (A.end IS NULL OR A.end > B.start)
       Nếu B chưa có end_time → dùng B.start làm điểm kết thúc tạm thời,
       vẫn bắt được trường hợp B.start rơi vào giữa buổi đang có lịch. */
    if ($room_id) {
        $newEnd = $end_time ?: $start_time;
        $chkRoom = $conn->prepare("
            SELECT class_id, class_name, start_time, end_time
            FROM TrainingClass
            WHERE room_id          = ?
              AND DATE(start_time) = DATE(?)
              AND start_time       < ?
              AND (end_time IS NULL OR end_time > ?)
            LIMIT 1
        ");
        $chkRoom->bind_param('isss', $room_id, $start_time, $newEnd, $start_time);
        $chkRoom->execute();
        $conflict = $chkRoom->get_result()->fetch_assoc();
        if ($conflict) {
            $cs = date('H:i', strtotime($conflict['start_time']));
            $ce = $conflict['end_time'] ? date('H:i', strtotime($conflict['end_time'])) : '?';
            echo json_encode(['success'=>false,'message'=>"Phòng này đã có lớp \"{$conflict['class_name']}\" ({$cs}\u2013{$ce}). Vui lòng chọn khung giờ không bị trùng."]);
            exit;
        }
    }

    $s=$conn->prepare("INSERT INTO TrainingClass (class_name,trainer_id,room_id,start_time,end_time) VALUES(?,?,?,?,?)");
    $s->bind_param('siiss',$class_name,$trainer_id,$room_id,$start_time,$end_time);
    echo json_encode($s->execute()
        ? ['success'=>true,'message'=>'Thêm buổi tập thành công','id'=>$conn->insert_id]
        : ['success'=>false,'message'=>$conn->error]);
    break;

case 'update_schedule':
    $id         = (int)($_POST['id']??0);
    $class_name = trim($_POST['class_name']??'');
    $trainer_id = (int)($_POST['trainer_id']??0) ?: null;
    $room_id    = (int)($_POST['room_id']??0) ?: null;
    $start_time = trim($_POST['start_time']??'');
    $end_time   = trim($_POST['end_time']??'') ?: null;
    if (!$id||!$class_name||!$start_time) { echo json_encode(['success'=>false,'message'=>'Dữ liệu không hợp lệ']); exit; }
    /* Kiểm tra end_time > start_time */
    if ($end_time && strtotime($end_time) <= strtotime($start_time)) {
        echo json_encode(['success'=>false,'message'=>'Giờ kết thúc phải lớn hơn giờ bắt đầu']); exit;
    }

    /* Kiểm tra giờ phòng tập */
    if ($room_id) {
        $roomRow = $conn->query("SELECT room_name,open_time,close_time FROM GymRoom WHERE room_id=$room_id LIMIT 1")->fetch_assoc();
        if ($roomRow) {
            $tStart = date('H:i:s', strtotime($start_time));
            $tEnd   = $end_time ? date('H:i:s', strtotime($end_time)) : null;
            $tOpen  = $roomRow['open_time'];
            $tClose = $roomRow['close_time'];
            $rName  = $roomRow['room_name'];
            if ($tOpen  && $tStart < $tOpen)  { echo json_encode(['success'=>false,'message'=>"Giờ bắt đầu sớm hơn giờ mở cửa phòng $rName (" . substr($tOpen,0,5) . ")" ]); exit; }
            if ($tClose && $tStart > $tClose) { echo json_encode(['success'=>false,'message'=>"Giờ bắt đầu muộn hơn giờ đóng cửa phòng $rName (" . substr($tClose,0,5) . ")" ]); exit; }
            if ($tEnd && $tClose && $tEnd > $tClose) { echo json_encode(['success'=>false,'message'=>"Giờ kết thúc vượt quá giờ đóng cửa phòng $rName (" . substr($tClose,0,5) . ")" ]); exit; }
        }
    }

    /* Kiểm tra phòng bị trùng giờ trong cùng ngày (trừ chính buổi này) */
    if ($room_id) {
        $newEnd = $end_time ?: $start_time;
        $chkRoom = $conn->prepare("
            SELECT class_id, class_name, start_time, end_time
            FROM TrainingClass
            WHERE room_id          = ?
              AND DATE(start_time) = DATE(?)
              AND class_id         <> ?
              AND start_time       < ?
              AND (end_time IS NULL OR end_time > ?)
            LIMIT 1
        ");
        $chkRoom->bind_param('isiss', $room_id, $start_time, $id, $newEnd, $start_time);
        $chkRoom->execute();
        $conflict = $chkRoom->get_result()->fetch_assoc();
        if ($conflict) {
            $cs = date('H:i', strtotime($conflict['start_time']));
            $ce = $conflict['end_time'] ? date('H:i', strtotime($conflict['end_time'])) : '?';
            echo json_encode(['success'=>false,'message'=>"Phòng này đã có lớp \"{$conflict['class_name']}\" ({$cs}\u2013{$ce}). Vui lòng chọn khung giờ không bị trùng."]);
            exit;
        }
    }

    $s=$conn->prepare("UPDATE TrainingClass SET class_name=?,trainer_id=?,room_id=?,start_time=?,end_time=? WHERE class_id=?");
    $s->bind_param('siissi',$class_name,$trainer_id,$room_id,$start_time,$end_time,$id);
    echo json_encode($s->execute() ? ['success'=>true,'message'=>'Cập nhật thành công'] : ['success'=>false,'message'=>$conn->error]);
    break;

case 'delete_schedule':
    $id=(int)($_POST['id']??0);
    $conn->query("DELETE FROM ClassRegistration WHERE class_id=$id");
    $s=$conn->prepare("DELETE FROM TrainingClass WHERE class_id=?");
    $s->bind_param('i',$id);
    echo json_encode($s->execute() ? ['success'=>true,'message'=>'Đã xóa buổi tập'] : ['success'=>false,'message'=>$conn->error]);
    break;

case 'add_registration':
    $class_id    = (int)($_POST['class_id']??0);
    $customer_id = (int)($_POST['customer_id']??0);
    if (!$class_id||!$customer_id) { echo json_encode(['success'=>false,'message'=>'Thiếu dữ liệu']); exit; }
    $chk=$conn->query("SELECT class_registration_id FROM ClassRegistration WHERE class_id=$class_id AND customer_id=$customer_id");
    if ($chk->num_rows>0) { echo json_encode(['success'=>false,'message'=>'Khách hàng đã đăng ký lớp này']); exit; }
    $s=$conn->prepare("INSERT INTO ClassRegistration (class_id,customer_id) VALUES(?,?)");
    $s->bind_param('ii',$class_id,$customer_id);
    echo json_encode($s->execute() ? ['success'=>true,'message'=>'Đăng ký thành công'] : ['success'=>false,'message'=>$conn->error]);
    break;

case 'delete_registration':
    $id=(int)($_POST['id']??0);
    $s=$conn->prepare("DELETE FROM ClassRegistration WHERE class_registration_id=?");
    $s->bind_param('i',$id);
    echo json_encode($s->execute() ? ['success'=>true,'message'=>'Đã hủy đăng ký'] : ['success'=>false,'message'=>$conn->error]);
    break;

case 'assign_trainer':
    $class_id   = (int)($_POST['class_id']??0);
    $trainer_id = (int)($_POST['trainer_id']??0);
    if (!$class_id||!$trainer_id) { echo json_encode(['success'=>false,'message'=>'Thiếu dữ liệu']); exit; }

    /* Lấy thông tin giờ của buổi tập cần đăng ký */
    $thisClass = $conn->query("SELECT DATE(start_time) AS class_date, start_time, end_time FROM TrainingClass WHERE class_id=$class_id LIMIT 1")->fetch_assoc();
    if (!$thisClass) { echo json_encode(['success'=>false,'message'=>'Không tìm thấy buổi tập']); exit; }
    $class_date  = $thisClass['class_date'];
    $thisStart   = $thisClass['start_time'];
    $thisEnd     = $thisClass['end_time'];

    /* Kiểm tra HLV bị trùng giờ trong ngày đó (trừ chính buổi này) */
    $newEnd = $thisEnd ?: $thisStart;
    $chk = $conn->prepare("
        SELECT class_id, class_name, start_time, end_time
        FROM TrainingClass
        WHERE trainer_id       = ?
          AND DATE(start_time) = ?
          AND class_id         <> ?
          AND start_time       < ?
          AND (end_time IS NULL OR end_time > ?)
        LIMIT 1
    ");
    $chk->bind_param('isiss', $trainer_id, $class_date, $class_id, $newEnd, $thisStart);
    $chk->execute();
    $conflict = $chk->get_result()->fetch_assoc();
    if ($conflict) {
        $cs = date('H:i', strtotime($conflict['start_time']));
        $ce = $conflict['end_time'] ? date('H:i', strtotime($conflict['end_time'])) : '?';
        echo json_encode(['success'=>false,'message'=>"HLV đã được phân công lớp \"{$conflict['class_name']}\" ({$cs}\u2013{$ce}) trong khung giờ này."]);
        exit;
    }

    $s=$conn->prepare("UPDATE TrainingClass SET trainer_id=? WHERE class_id=?");
    $s->bind_param('ii',$trainer_id,$class_id);
    echo json_encode($s->execute() ? ['success'=>true,'message'=>'Đã đăng ký dạy buổi này'] : ['success'=>false,'message'=>$conn->error]);
    break;

case 'unassign_trainer':
    $class_id   = (int)($_POST['class_id']??0);
    $trainer_id = (int)($_POST['trainer_id']??0);
    if (!$class_id||!$trainer_id) { echo json_encode(['success'=>false,'message'=>'Thiếu dữ liệu']); exit; }
    /* Only unassign if this trainer is currently assigned */
    $s=$conn->prepare("UPDATE TrainingClass SET trainer_id=NULL WHERE class_id=? AND trainer_id=?");
    $s->bind_param('ii',$class_id,$trainer_id);
    $s->execute();
    echo json_encode($s->affected_rows > 0
        ? ['success'=>true,'message'=>'Đã hủy đăng ký dạy']
        : ['success'=>false,'message'=>'Bạn không phải HLV phụ trách buổi này']);
    break;

case 'get_trainers':
    $rows=$conn->query("SELECT employee_id, full_name FROM Employee WHERE position='Personal Trainer' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success'=>true,'data'=>$rows]);
    break;

case 'get_rooms':
    $rows=$conn->query("
        SELECT gr.room_id, gr.room_name, gr.capacity, gr.status,
               pt.type_name AS package_type_name, pt.color_code AS package_color
        FROM GymRoom gr
        LEFT JOIN PackageType pt ON pt.type_id = gr.package_type_id
        WHERE gr.status='Active'
        ORDER BY gr.room_name
    ")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success'=>true,'data'=>$rows]);
    break;

case 'get_customers':
    $search = trim($_GET['search']??'');
    $s=$conn->prepare("SELECT customer_id, full_name, phone FROM Customer WHERE full_name LIKE ? OR phone LIKE ? ORDER BY full_name LIMIT 30");
    $like="%$search%"; $s->bind_param('ss',$like,$like); $s->execute();
    $rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success'=>true,'data'=>$rows]);
    break;

default:
    echo json_encode(['success'=>false,'message'=>'Action không hợp lệ']);
}
?>
