<?php
require_once '../../../Database/db.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ========================
    // STATS
    // ========================
    case 'get_stats':
        $total = $conn->query("SELECT COUNT(*) AS c FROM Employee")->fetch_assoc()['c'];

        $active = $conn->query("
            SELECT COUNT(*) AS c FROM Employee e
            JOIN Account a ON a.account_id = e.account_id
            WHERE a.is_active = 1
        ")->fetch_assoc()['c'];

        $new_month = $conn->query("
            SELECT COUNT(*) AS c FROM Employee
            WHERE MONTH(hire_date) = MONTH(CURDATE())
              AND YEAR(hire_date)  = YEAR(CURDATE())
        ")->fetch_assoc()['c'];

        $avg_salary = $conn->query("
            SELECT AVG(net_salary) AS avg FROM Payroll
            WHERE month = MONTH(CURDATE()) AND year = YEAR(CURDATE())
        ")->fetch_assoc()['avg'];

        echo json_encode([
            'success'    => true,
            'total'      => $total,
            'active'     => $active,
            'new_month'  => $new_month,
            'avg_salary' => $avg_salary ? round($avg_salary) : 0
        ]);
        break;

    // ========================
    // GET EMPLOYEE LIST
    // ========================
    case 'get_employees':
        $page   = max(1, intval($_GET['page']   ?? 1));
        $limit  = intval($_GET['limit']  ?? 15);
        $search = trim($_GET['search']   ?? '');
        $gender = trim($_GET['gender']   ?? '');
        $sort   = $_GET['sort']          ?? 'id_desc';
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];
        $types  = '';

        if ($search !== '') {
            $where[] = "(e.full_name LIKE ? OR e.phone LIKE ? OR e.email LIKE ?)";
            $s = "%$search%";
            $params[] = $s; $params[] = $s; $params[] = $s;
            $types .= 'sss';
        }

        if ($gender !== '') {
            $where[] = "e.gender = ?";
            $params[] = $gender;
            $types .= 's';
        }

        $whereStr = implode(' AND ', $where);

        $orderBy = match($sort) {
            'id_asc'    => 'e.employee_id ASC',
            'name_asc'  => 'e.full_name ASC',
            'name_desc' => 'e.full_name DESC',
            'join_desc' => 'e.hire_date DESC',
            default     => 'e.employee_id DESC'
        };

        $sql = "
            SELECT
                e.employee_id,
                e.full_name,
                e.position,
                e.date_of_birth,
                e.gender,
                e.phone,
                e.email,
                e.address,
                e.hire_date,
                e.monthly_salary,
                a.username,
                a.is_active AS acc_is_active
            FROM Employee e
            LEFT JOIN Account a ON a.account_id = e.account_id
            WHERE $whereStr
            ORDER BY $orderBy
        ";

        $countSql = "SELECT COUNT(*) AS total FROM ($sql) AS sub";
        $stmt = $conn->prepare($countSql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types   .= 'ii';

        $stmt = $conn->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result    = $stmt->get_result();
        $employees = [];
        while ($row = $result->fetch_assoc()) $employees[] = $row;

        echo json_encode([
            'success'    => true,
            'data'       => $employees,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => max(1, ceil($total / $limit))
        ]);
        break;

    // ========================
    // ADD EMPLOYEE
    // ========================
    case 'add_employee':
        $full_name    = trim($_POST['full_name']    ?? '');
        $date_of_birth= trim($_POST['date_of_birth']?? '') ?: null;
        $gender       = trim($_POST['gender']       ?? '') ?: null;
        $position     = trim($_POST['position']     ?? '') ?: null;
        $phone        = trim($_POST['phone']        ?? '') ?: null;
        $email        = trim($_POST['email']        ?? '') ?: null;
        $address      = trim($_POST['address']      ?? '') ?: null;
        $hire_date    = trim($_POST['hire_date']    ?? '') ?: null;
        $username     = trim($_POST['username']     ?? '');
        $password_raw = trim($_POST['password']     ?? '');
        $monthly_salary  = floatval($_POST['monthly_salary'] ?? 0);

        if ($full_name === '') {
            echo json_encode(['success' => false, 'message' => 'Full name is required']); exit;
        }
        if ($username === '') {
            echo json_encode(['success' => false, 'message' => 'Username is required when adding a new employee']); exit;
        }
        if (strlen($username) < 3) {
            echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters']); exit;
        }

        if ($email !== null) {
            $chk = $conn->prepare("SELECT employee_id FROM Employee WHERE email = ?");
            $chk->bind_param('s', $email); $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Email already in use']); exit;
            }
        }

        if ($phone !== null) {
            $chk = $conn->prepare("SELECT employee_id FROM Employee WHERE phone = ?");
            $chk->bind_param('s', $phone); $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Phone number already in use']); exit;
            }
        }

        $chk = $conn->prepare("SELECT account_id FROM Account WHERE username = ?");
        $chk->bind_param('s', $username); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']); exit;
        }

        $raw_pass = ($password_raw !== '') ? $password_raw : 'elitegym@2025';
        $hashed   = password_hash($raw_pass, PASSWORD_BCRYPT);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO Account (username, password, role) VALUES (?, ?, 'Employee')");
            $stmt->bind_param('ss', $username, $hashed);
            if (!$stmt->execute()) throw new Exception('Account creation failed: ' . $conn->error);
            $account_id = $conn->insert_id;

            $stmt = $conn->prepare("
                INSERT INTO Employee (full_name, date_of_birth, gender, position, phone, email, address, hire_date, account_id, monthly_salary)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ssssssssid',
                $full_name, $date_of_birth, $gender, $position,
                $phone, $email, $address, $hire_date, $account_id, $monthly_salary
            );
            if (!$stmt->execute()) throw new Exception('Employee creation failed: ' . $conn->error);
            $emp_id = $conn->insert_id;

            $conn->commit();
            $pw_msg = ($password_raw !== '') ? '' : ' | Default password: elitegym@2025';
            echo json_encode([
                'success' => true,
                'message' => "Employee added successfully. Account: $username$pw_msg",
                'id'      => $emp_id
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ========================
    // UPDATE EMPLOYEE
    // ========================
    case 'update_employee':
        $id            = intval($_POST['id']            ?? 0);
        $full_name     = trim($_POST['full_name']       ?? '');
        $date_of_birth = trim($_POST['date_of_birth']   ?? '') ?: null;
        $gender        = trim($_POST['gender']          ?? '') ?: null;
        $position      = trim($_POST['position']        ?? '') ?: null;
        $phone         = trim($_POST['phone']           ?? '') ?: null;
        $email         = trim($_POST['email']           ?? '') ?: null;
        $address       = trim($_POST['address']         ?? '') ?: null;
        $hire_date     = trim($_POST['hire_date']       ?? '') ?: null;
        $monthly_salary   = floatval($_POST['monthly_salary'] ?? 0);

        if ($id === 0 || $full_name === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid data']); exit;
        }

        if ($email !== null) {
            $chk = $conn->prepare("SELECT employee_id FROM Employee WHERE email = ? AND employee_id != ?");
            $chk->bind_param('si', $email, $id); $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Email already in use']); exit;
            }
        }

        if ($phone !== null) {
            $chk = $conn->prepare("SELECT employee_id FROM Employee WHERE phone = ? AND employee_id != ?");
            $chk->bind_param('si', $phone, $id); $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Phone number already in use']); exit;
            }
        }

        $stmt = $conn->prepare("
            UPDATE Employee
            SET full_name=?, date_of_birth=?, gender=?, position=?, phone=?, email=?, address=?, hire_date=?, monthly_salary=?
            WHERE employee_id=?
        ");
        $stmt->bind_param('ssssssssdi',
            $full_name, $date_of_birth, $gender, $position,
            $phone, $email, $address, $hire_date, $monthly_salary, $id
        );

        echo json_encode($stmt->execute()
            ? ['success' => true,  'message' => 'Employee updated successfully']
            : ['success' => false, 'message' => 'Error: ' . $conn->error]);
        break;

    // ========================
    // DELETE EMPLOYEE
    // ========================
    case 'delete_employee':
        $id = intval($_POST['id'] ?? 0);
        if ($id === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit;
        }

        $checks = [
            ["SELECT COUNT(*) AS c FROM Attendance       WHERE employee_id = $id",  'attendance records'],
            ["SELECT COUNT(*) AS c FROM Payroll          WHERE employee_id = $id",  'payroll records'],
            ["SELECT COUNT(*) AS c FROM TrainingClass    WHERE trainer_id  = $id",  'training class records'],
        ];

        foreach ($checks as [$chk, $label]) {
            if ($conn->query($chk)->fetch_assoc()['c'] > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => "Cannot delete: employee has related $label"
                ]);
                exit;
            }
        }

        $row       = $conn->query("SELECT account_id FROM Employee WHERE employee_id = $id")->fetch_assoc();
        $acc_id    = $row ? $row['account_id'] : null;

        $stmt = $conn->prepare("DELETE FROM Employee WHERE employee_id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            if ($acc_id) $conn->query("DELETE FROM Account WHERE account_id = $acc_id");
            echo json_encode(['success' => true, 'message' => 'Employee deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
        }
        break;

    // ========================
    // GET EMPLOYEE DETAIL
    // ========================
    case 'get_detail':
        $id = intval($_GET['id'] ?? 0);
        if ($id === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit;
        }

        $stmt = $conn->prepare("
            SELECT e.*, a.username, a.is_active AS acc_is_active
            FROM Employee e
            LEFT JOIN Account a ON a.account_id = e.account_id
            WHERE e.employee_id = ?
        ");
        $stmt->bind_param('i', $id); $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();

        if (!$employee) {
            echo json_encode(['success' => false, 'message' => 'Employee not found']); exit;
        }

        $stmt = $conn->prepare("
            SELECT month, year, base_salary, allowance, bonus, deduction, net_salary
            FROM Payroll
            WHERE employee_id = ?
            ORDER BY year DESC, month DESC
            LIMIT 6
        ");
        $stmt->bind_param('i', $id); $stmt->execute();
        $salaries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'success'  => true,
            'employee' => $employee,
            'salaries' => $salaries
        ]);
        break;

    // ========================
    // TÍNH LƯƠNG CHI TIẾT THEO THÁNG
    // Công thức:
    //   luongGio = monthly_salary / (số ngày không CN trong tháng) / 8
    //   Hành chính (≤8h):  số_giờ × luongGio
    //   Tăng ca (>8h):     8h × luongGio + (extra_h × luongGio × 1.5)
    //   Chủ nhật:          số_giờ × luongGio × 2
    //   Đi muộn (Late):    trừ 50.000đ/ngày
    // ========================
    case 'get_hours_worked':
        $employee_id = intval($_GET['employee_id'] ?? 0);
        $month       = intval($_GET['month']       ?? 0);
        $year        = intval($_GET['year']        ?? 0);

        if ($employee_id === 0 || $month < 1 || $month > 12 || $year < 2000) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
        }

        // Lấy lương tháng cố định
        $nv = $conn->query("SELECT full_name, monthly_salary FROM Employee WHERE employee_id = $employee_id")->fetch_assoc();
        if (!$nv) { echo json_encode(['success' => false, 'message' => 'Employee not found']); exit; }

        $monthly_salary = floatval($nv['monthly_salary'] ?? 0);

        // Đếm số ngày không phải Chủ nhật trong tháng
        $days_in_month   = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $working_days    = 0;
        for ($d = 1; $d <= $days_in_month; $d++) {
            $dow = date('N', mktime(0,0,0,$month,$d,$year)); // 7 = Sunday
            if ($dow != 7) $working_days++;
        }

        // Lương 1 giờ hành chính
        $luong_gio = ($working_days > 0 && $monthly_salary > 0)
            ? $monthly_salary / $working_days / 8
            : 0;

        // Lấy toàn bộ chấm công trong tháng
        $stmt = $conn->prepare("
            SELECT work_date, check_in, check_out, status
            FROM Attendance
            WHERE employee_id = ?
              AND MONTH(work_date) = ?
              AND YEAR(work_date)  = ?
              AND status IN ('Present', 'Late')
        ");
        $stmt->bind_param('iii', $employee_id, $month, $year);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $tong_luong      = 0;
        $days_present    = 0;
        $days_late       = 0;
        $days_sunday     = 0;
        $tong_gio_hc     = 0;
        $tong_gio_tc     = 0;
        $detail_days     = [];

        foreach ($rows as $row) {
            $days_present++;
            $is_late    = ($row['status'] === 'Late');
            $is_sunday  = (date('N', strtotime($row['work_date'])) == 7);
            $check_in   = $row['check_in'];
            $check_out  = $row['check_out'];

            // Tính số phút làm thực tế
            $minutes_worked = 0;
            if ($check_in && $check_out) {
                [$hi, $mi] = explode(':', $check_in);
                [$ho, $mo] = explode(':', $check_out);
                $minutes_worked = max(0, (intval($ho)*60+intval($mo)) - (intval($hi)*60+intval($mi)));
            }
            $hours_worked = $minutes_worked / 60;

            // Tính lương ngày
            $luong_ngay = 0;
            if ($is_sunday) {
                // Chủ nhật: x2 lương giờ hành chính
                $luong_ngay = $hours_worked * $luong_gio * 2;
                $days_sunday++;
            } elseif ($hours_worked > 8) {
                // Có tăng ca
                $gio_tc     = $hours_worked - 8;
                $luong_ngay = 8 * $luong_gio + $gio_tc * $luong_gio * 1.5;
                $tong_gio_hc += 8;
                $tong_gio_tc += $gio_tc;
            } else {
                $luong_ngay  = $hours_worked * $luong_gio;
                $tong_gio_hc += $hours_worked;
            }

            // Đi muộn: trừ 50.000đ
            $tru_muon = 0;
            if ($is_late) {
                $tru_muon = 50000;
                $luong_ngay = max(0, $luong_ngay - $tru_muon);
                $days_late++;
            }

            $tong_luong += $luong_ngay;

            $detail_days[] = [
                'date'     => $row['work_date'],
                'is_sunday'=> $is_sunday,
                'is_late'  => $is_late,
                'hours'    => round($hours_worked, 2),
                'salary'   => round($luong_ngay),
                'late_deduct' => $tru_muon,
            ];
        }

        echo json_encode([
            'success'        => true,
            'full_name'      => $nv['full_name'],
            'monthly_salary' => $monthly_salary,
            'luong_gio'      => round($luong_gio, 2),
            'working_days'   => $working_days,
            'days_present'   => $days_present,
            'days_late'      => $days_late,
            'days_sunday'    => $days_sunday,
            'tong_gio_hc'    => round($tong_gio_hc, 2),
            'tong_gio_tc'    => round($tong_gio_tc, 2),
            'tong_luong'     => round($tong_luong),
            'detail_days'    => $detail_days,
        ]);
        break;

    // ========================
    // SAVE PAYROLL
    // ========================
    case 'save_salary':
        $employee_id  = intval($_POST['employee_id']  ?? 0);
        $month        = intval($_POST['month']        ?? 0);
        $year         = intval($_POST['year']         ?? 0);
        $base_salary  = floatval($_POST['base_salary']  ?? 0);
        $allowance    = floatval($_POST['allowance']    ?? 0);
        $bonus        = floatval($_POST['bonus']        ?? 0);
        $deduction    = floatval($_POST['deduction']    ?? 0);
        $net_salary   = floatval($_POST['net_salary']   ?? 0);

        if ($employee_id === 0 || $month < 1 || $month > 12 || $year < 2000) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']); exit;
        }
        if ($base_salary < 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid salary']); exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO Payroll (employee_id, month, year, base_salary, allowance, bonus, deduction, net_salary)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                base_salary  = VALUES(base_salary),
                allowance    = VALUES(allowance),
                bonus        = VALUES(bonus),
                deduction    = VALUES(deduction),
                net_salary   = VALUES(net_salary)
        ");
        $stmt->bind_param('iiiddddd',
            $employee_id, $month, $year,
            $base_salary, $allowance, $bonus, $deduction, $net_salary
        );

        echo json_encode($stmt->execute()
            ? ['success' => true,  'message' => "Payroll for {$month}/{$year} saved successfully!"]
            : ['success' => false, 'message' => 'Error: ' . $conn->error]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
