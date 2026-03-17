<?php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

if (session_status() === PHP_SESSION_NONE) { session_name('PHPSESSID'); session_start(); }

function jsonOut(array $d): void {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

$role = $_SESSION['role'] ?? '';
if ($role !== 'Admin') jsonOut(['ok'=>false,'msg'=>'Không có quyền truy cập']);

$db = @new mysqli('localhost','root','','datn');
if ($db->connect_error) jsonOut(['ok'=>false,'msg'=>'Lỗi DB: '.$db->connect_error]);
$db->set_charset('utf8mb4');

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

// ════════════════════════════════════════════
// PACKAGE TYPE
// ════════════════════════════════════════════
if ($action === 'list') {
    $res=$db->query("SELECT * FROM PackageType ORDER BY sort_order ASC, type_id ASC");
    $rows=[]; if($res) while($r=$res->fetch_assoc()) $rows[]=$r;
    jsonOut(['ok'=>true,'data'=>$rows]);
}
if ($action === 'add') {
    $name=trim($_POST['type_name']??''); $desc=trim($_POST['description']??'');
    $color=trim($_POST['color_code']??'#6b7280'); $order=max(0,intval($_POST['sort_order']??0));
    if(!$name) jsonOut(['ok'=>false,'msg'=>'Tên loại gói không được trống']);
    if(!preg_match('/^#[0-9a-fA-F]{3,6}$/',$color)) $color='#6b7280';
    if($order===0){$mx=$db->query("SELECT MAX(sort_order) AS m FROM PackageType")->fetch_assoc()['m']??0;$order=(int)$mx+1;}
    $st=$db->prepare("INSERT INTO PackageType (type_name,description,color_code,sort_order,is_active) VALUES (?,?,?,?,1)");
    $st->bind_param('sssi',$name,$desc,$color,$order);
    $st->execute() ? jsonOut(['ok'=>true,'msg'=>'Đã thêm loại gói thành công!','id'=>$db->insert_id]) : jsonOut(['ok'=>false,'msg'=>'Lỗi: '.$db->error]);
}
if ($action === 'update') {
    $id=intval($_POST['type_id']??0); $name=trim($_POST['type_name']??''); $desc=trim($_POST['description']??'');
    $color=trim($_POST['color_code']??'#6b7280'); $order=intval($_POST['sort_order']??0);
    if(!$id) jsonOut(['ok'=>false,'msg'=>'ID không hợp lệ']);
    if(!$name) jsonOut(['ok'=>false,'msg'=>'Tên không được trống']);
    if(!preg_match('/^#[0-9a-fA-F]{3,6}$/',$color)) $color='#6b7280';
    $st=$db->prepare("UPDATE PackageType SET type_name=?,description=?,color_code=?,sort_order=? WHERE type_id=?");
    $st->bind_param('sssii',$name,$desc,$color,$order,$id);
    $st->execute() ? jsonOut(['ok'=>true,'msg'=>'Đã cập nhật thành công!']) : jsonOut(['ok'=>false,'msg'=>'Lỗi: '.$db->error]);
}
if ($action === 'toggle') {
    $id=intval($_POST['type_id']??0);
    if(!$id) jsonOut(['ok'=>false,'msg'=>'ID không hợp lệ']);
    $st=$db->prepare("UPDATE PackageType SET is_active=1-is_active WHERE type_id=?");
    $st->bind_param('i',$id); $st->execute();
    $row=$db->query("SELECT is_active FROM PackageType WHERE type_id=$id")->fetch_assoc();
    jsonOut(['ok'=>true,'msg'=>($row['is_active']?'Đã bật':'Đã tắt').' loại gói!']);
}
if ($action === 'delete') {
    $id=intval($_POST['type_id']??0);
    if(!$id) jsonOut(['ok'=>false,'msg'=>'ID không hợp lệ']);
    $used=(int)$db->query("SELECT COUNT(*) c FROM MembershipPlan WHERE package_type_id=$id")->fetch_assoc()['c'];
    if($used>0) jsonOut(['ok'=>false,'msg'=>"Không thể xóa — có $used gói tập đang dùng!"]);
    $st=$db->prepare("DELETE FROM PackageType WHERE type_id=?"); $st->bind_param('i',$id);
    $st->execute() ? jsonOut(['ok'=>true,'msg'=>'Đã xóa loại gói!']) : jsonOut(['ok'=>false,'msg'=>'Lỗi: '.$db->error]);
}

// ════════════════════════════════════════════
// EQUIPMENT TYPE
// ════════════════════════════════════════════
if ($action === 'eq_list') {
    $res=$db->query("SELECT * FROM EquipmentType ORDER BY type_id ASC");
    $rows=[]; if($res) while($r=$res->fetch_assoc()) $rows[]=$r;
    jsonOut(['ok'=>true,'data'=>$rows]);
}
if ($action === 'eq_add') {
    $name=trim($_POST['type_name']??''); $desc=trim($_POST['description']??'');
    $interval=max(1,intval($_POST['maintenance_interval']??180));
    if(!$name) jsonOut(['ok'=>false,'msg'=>'Tên loại thiết bị không được trống']);
    $chk=$db->prepare("SELECT type_id FROM EquipmentType WHERE type_name=?");
    $chk->bind_param('s',$name); $chk->execute();
    if($chk->get_result()->num_rows>0) jsonOut(['ok'=>false,'msg'=>'Tên loại thiết bị đã tồn tại']);
    $st=$db->prepare("INSERT INTO EquipmentType (type_name,description,maintenance_interval) VALUES (?,?,?)");
    $st->bind_param('ssi',$name,$desc,$interval);
    $st->execute() ? jsonOut(['ok'=>true,'msg'=>'Đã thêm loại thiết bị thành công!','id'=>$db->insert_id]) : jsonOut(['ok'=>false,'msg'=>'Lỗi: '.$db->error]);
}
if ($action === 'eq_update') {
    $id=intval($_POST['type_id']??0); $name=trim($_POST['type_name']??'');
    $desc=trim($_POST['description']??''); $interval=max(1,intval($_POST['maintenance_interval']??180));
    if(!$id) jsonOut(['ok'=>false,'msg'=>'ID không hợp lệ']);
    if(!$name) jsonOut(['ok'=>false,'msg'=>'Tên không được trống']);
    $chk=$db->prepare("SELECT type_id FROM EquipmentType WHERE type_name=? AND type_id!=?");
    $chk->bind_param('si',$name,$id); $chk->execute();
    if($chk->get_result()->num_rows>0) jsonOut(['ok'=>false,'msg'=>'Tên đã tồn tại']);
    $st=$db->prepare("UPDATE EquipmentType SET type_name=?,description=?,maintenance_interval=? WHERE type_id=?");
    $st->bind_param('ssii',$name,$desc,$interval,$id);
    $st->execute() ? jsonOut(['ok'=>true,'msg'=>'Đã cập nhật thành công!']) : jsonOut(['ok'=>false,'msg'=>'Lỗi: '.$db->error]);
}
if ($action === 'eq_delete') {
    $id=intval($_POST['type_id']??0);
    if(!$id) jsonOut(['ok'=>false,'msg'=>'ID không hợp lệ']);
    $used=(int)$db->query("SELECT COUNT(*) c FROM Equipment WHERE type_id=$id")->fetch_assoc()['c'];
    if($used>0) jsonOut(['ok'=>false,'msg'=>"Không thể xóa — có $used thiết bị đang dùng!"]);
    $st=$db->prepare("DELETE FROM EquipmentType WHERE type_id=?"); $st->bind_param('i',$id);
    $st->execute() ? jsonOut(['ok'=>true,'msg'=>'Đã xóa loại thiết bị!']) : jsonOut(['ok'=>false,'msg'=>'Lỗi: '.$db->error]);
}

jsonOut(['ok'=>false,'msg'=>"Action '$action' không hợp lệ"]);
?>
