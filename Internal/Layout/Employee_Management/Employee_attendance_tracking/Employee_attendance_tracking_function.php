<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

require_once '../../../../Database/db.php';

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Cannot connect to database']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ========================
    // STATS STRIP
    // ========================
    case 'get_stats':
        $date = $conn->real_escape_string($_GET['date'] ?? date('Y-m-d'));

        $total   = $conn->query("SELECT COUNT(*) as c FROM Employee")->fetch_assoc()['c'];
        $present = $conn->query("SELECT COUNT(*) as c FROM Attendance WHERE work_date = '$date' AND status = 'Present'")->fetch_assoc()['c'];
        $absent  = $conn->query("SELECT COUNT(*) as c FROM Attendance WHERE work_date = '$date' AND status = 'Absent'")->fetch_assoc()['c'];
        $late    = $conn->query("SELECT COUNT(*) as c FROM Attendance WHERE work_date = '$date' AND status = 'Late'")->fetch_assoc()['c'];
        $leave   = $conn->query("SELECT COUNT(*) as c FROM Attendance WHERE work_date = '$date' AND (status = 'Day Off' OR status = 'On Leave')")->fetch_assoc()['c'];

        echo json_encode([
            'success' => true,
            'total'   => $total,
            'present' => $present,
            'absent'  => $absent,
            'late'    => $late,
            'leave'   => $leave
        ]);
        break;

    // ========================
    // GET ATTENDANCE LIST
    // ========================
    case 'get_attendance':
        $date   = $_GET['date'] ?? date('Y-m-d');
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = intval($_GET['limit'] ?? 20);
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];
        $types  = '';

        if ($search !== '') {
            $where[]  = "e.full_name LIKE ?";
            $params[] = "%$search%";
            $types   .= 's';
        }

        if ($status === 'not_recorded') {
            $where[] = "cc.attendance_id IS NULL";
        } elseif ($status !== '') {
            $where[]  = "cc.status = ?";
            $params[] = $status;
            $types   .= 's';
        }

        $whereStr = implode(' AND ', $where);

        array_unshift($params, $date);
        $types = 's' . $types;

        // COUNT
        $countSql = "
            SELECT COUNT(*) as total
            FROM Employee e
            LEFT JOIN Attendance cc ON cc.employee_id = e.employee_id AND cc.work_date = ?
            WHERE $whereStr
        ";
        $stmt = $conn->prepare($countSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];

        // DATA — FIX: use cc.note (matches DB column name, not cc.notes)
        $dataSql = "
            SELECT
                e.employee_id,
                e.full_name,
                e.gender,
                cc.attendance_id,
                cc.status,
                cc.check_in,
                cc.check_out,
                cc.note
            FROM Employee e
            LEFT JOIN Attendance cc ON cc.employee_id = e.employee_id AND cc.work_date = ?
            WHERE $whereStr
            ORDER BY e.full_name ASC
            LIMIT ? OFFSET ?
        ";
        $dataParams   = $params;
        $dataTypes    = $types;
        $dataParams[] = $limit;
        $dataParams[] = $offset;
        $dataTypes   .= 'ii';

        $stmt = $conn->prepare($dataSql);
        $stmt->bind_param($dataTypes, ...$dataParams);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;

        echo json_encode([
            'success'    => true,
            'data'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => max(1, ceil($total / $limit))
        ]);
        break;

    // ========================
    // GET UNCHECKED (for bulk modal)
    // ========================
    case 'get_unchecked':
        $date = $_GET['date'] ?? date('Y-m-d');

        $stmt = $conn->prepare("
            SELECT e.employee_id, e.full_name, e.gender
            FROM Employee e
            WHERE e.employee_id NOT IN (
                SELECT employee_id FROM Attendance WHERE work_date = ?
            )
            ORDER BY e.full_name ASC
        ");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ========================
    // ADD ATTENDANCE
    // ========================
    case 'add_attendance':
        $employee_id = intval($_POST['employee_id'] ?? 0);
        $work_date   = trim($_POST['work_date'] ?? '');
        $status      = trim($_POST['status'] ?? '');
        $check_in    = trim($_POST['check_in']  ?? '') ?: null;
        $check_out   = trim($_POST['check_out'] ?? '') ?: null;
        $note        = trim($_POST['note'] ?? $_POST['notes'] ?? '') ?: null; // FIX: accept both

        if ($employee_id === 0 || $work_date === '' || $status === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }

        $chk = $conn->prepare("SELECT attendance_id FROM Attendance WHERE employee_id = ? AND work_date = ?");
        $chk->bind_param('is', $employee_id, $work_date);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Attendance already recorded for this employee today']);
            exit;
        }

        // FIX: column is `note` not `notes`
        $stmt = $conn->prepare("
            INSERT INTO Attendance (employee_id, work_date, check_in, check_out, status, note)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isssss', $employee_id, $work_date, $check_in, $check_out, $status, $note);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Attendance recorded successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
        }
        break;

    // ========================
    // UPDATE ATTENDANCE
    // ========================
    case 'update_attendance':
        $attendance_id = intval($_POST['attendance_id'] ?? 0);
        $status        = trim($_POST['status']    ?? '');
        $check_in      = trim($_POST['check_in']  ?? '') ?: null;
        $check_out     = trim($_POST['check_out'] ?? '') ?: null;
        $note          = trim($_POST['note'] ?? $_POST['notes'] ?? '') ?: null; // FIX: accept both

        if ($attendance_id === 0 || $status === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }

        // FIX: column is `note` not `notes`
        $stmt = $conn->prepare("
            UPDATE Attendance SET status=?, check_in=?, check_out=?, note=?
            WHERE attendance_id=?
        ");
        $stmt->bind_param('ssssi', $status, $check_in, $check_out, $note, $attendance_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
        }
        break;

    // ========================
    // DELETE ATTENDANCE
    // ========================
    case 'delete_attendance':
        $attendance_id = intval($_POST['attendance_id'] ?? 0);
        if ($attendance_id === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM Attendance WHERE attendance_id = ?");
        $stmt->bind_param('i', $attendance_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Attendance record deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
        }
        break;

    // ========================
    // BULK ATTENDANCE
    // ========================
    case 'bulk_attendance':
        $work_date = trim($_POST['work_date'] ?? '');
        $status    = trim($_POST['status']    ?? '');
        $check_in  = trim($_POST['check_in']  ?? '') ?: null;
        $ids_json  = $_POST['employee_ids'] ?? '[]';
        $ids       = json_decode($ids_json, true);

        if ($work_date === '' || $status === '' || !is_array($ids) || count($ids) === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }

        $inserted = 0;
        $stmt = $conn->prepare("
            INSERT IGNORE INTO Attendance (employee_id, work_date, check_in, status)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($ids as $empId) {
            $eid = intval($empId);
            if ($eid <= 0) continue;
            $stmt->bind_param('isss', $eid, $work_date, $check_in, $status);
            if ($stmt->execute()) $inserted++;
        }

        echo json_encode([
            'success' => true,
            'message' => "Bulk attendance recorded for $inserted employees"
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
