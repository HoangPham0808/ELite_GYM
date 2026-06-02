<?php

class Employee extends Model
{
    public function getByAccountId(int $accountId): ?array
    {
        $stmt = $this->db->prepare("SELECT full_name, position FROM Employee WHERE account_id = ? LIMIT 1");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function getEmployeeStats(): array
    {
        $total = $this->db->query("SELECT COUNT(*) AS c FROM Employee")->fetch_assoc()['c'];

        $active = $this->db->query("
            SELECT COUNT(*) AS c FROM Employee e
            JOIN Account a ON a.account_id = e.account_id
            WHERE a.is_active = 1
        ")->fetch_assoc()['c'];

        $new_month = $this->db->query("
            SELECT COUNT(*) AS c FROM Employee
            WHERE MONTH(hire_date) = MONTH(CURDATE())
              AND YEAR(hire_date)  = YEAR(CURDATE())
        ")->fetch_assoc()['c'];

        $avg_salary = $this->db->query("
            SELECT AVG(net_salary) AS avg FROM Payroll
            WHERE month = MONTH(CURDATE()) AND year = YEAR(CURDATE())
        ")->fetch_assoc()['avg'];

        return [
            'total'      => (int)$total,
            'active'     => (int)$active,
            'new_month'  => (int)$new_month,
            'avg_salary' => $avg_salary ? (int)round($avg_salary) : 0
        ];
    }

    public function getEmployeesList(int $page, int $limit, string $search, string $gender, string $sort): array
    {
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
        $stmt = $this->db->prepare($countSql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types   .= 'ii';

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $employees = [];
        while ($row = $result->fetch_assoc()) $employees[] = $row;
        $stmt->close();

        return [
            'data'       => $employees,
            'total'      => (int)$total,
            'totalPages' => max(1, ceil($total / $limit))
        ];
    }

    public function emailExists(string $email, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $stmt = $this->db->prepare("SELECT employee_id FROM Employee WHERE email = ? AND employee_id != ? LIMIT 1");
            $stmt->bind_param('si', $email, $excludeId);
        } else {
            $stmt = $this->db->prepare("SELECT employee_id FROM Employee WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
        }
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function phoneExists(string $phone, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $stmt = $this->db->prepare("SELECT employee_id FROM Employee WHERE phone = ? AND employee_id != ? LIMIT 1");
            $stmt->bind_param('si', $phone, $excludeId);
        } else {
            $stmt = $this->db->prepare("SELECT employee_id FROM Employee WHERE phone = ? LIMIT 1");
            $stmt->bind_param('s', $phone);
        }
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function createEmployee(string $fullName, ?string $dob, ?string $gender, ?string $position, ?string $phone, ?string $email, ?string $address, ?string $hireDate, int $accountId, float $monthlySalary): int|false
    {
        $stmt = $this->db->prepare("
            INSERT INTO Employee (full_name, date_of_birth, gender, position, phone, email, address, hire_date, account_id, monthly_salary)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('ssssssssid', $fullName, $dob, $gender, $position, $phone, $email, $address, $hireDate, $accountId, $monthlySalary);
        $ok = $stmt->execute();
        $id = $ok ? (int)$this->db->insert_id : false;
        $stmt->close();
        return $id;
    }

    public function updateEmployee(int $id, string $fullName, ?string $dob, ?string $gender, ?string $position, ?string $phone, ?string $email, ?string $address, ?string $hireDate, float $monthlySalary): bool
    {
        $stmt = $this->db->prepare("
            UPDATE Employee
            SET full_name=?, date_of_birth=?, gender=?, position=?, phone=?, email=?, address=?, hire_date=?, monthly_salary=?
            WHERE employee_id=?
        ");
        $stmt->bind_param('ssssssssdi', $fullName, $dob, $gender, $position, $phone, $email, $address, $hireDate, $monthlySalary, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteEmployee(int $id): array
    {
        $checks = [
            ["SELECT COUNT(*) AS c FROM Attendance       WHERE employee_id = $id",  'attendance records'],
            ["SELECT COUNT(*) AS c FROM Payroll          WHERE employee_id = $id",  'payroll records'],
            ["SELECT COUNT(*) AS c FROM TrainingClass    WHERE trainer_id  = $id",  'training class records'],
        ];

        foreach ($checks as [$chk, $label]) {
            if ($this->db->query($chk)->fetch_assoc()['c'] > 0) {
                return [
                    'success' => false,
                    'message' => "Cannot delete: employee has related $label"
                ];
            }
        }

        $row    = $this->db->query("SELECT account_id FROM Employee WHERE employee_id = $id")->fetch_assoc();
        $acc_id = $row ? (int)$row['account_id'] : null;

        $stmt = $this->db->prepare("DELETE FROM Employee WHERE employee_id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            if ($acc_id) $this->db->query("DELETE FROM Account WHERE account_id = $acc_id");
            return ['success' => true, 'message' => 'Employee deleted'];
        } else {
            return ['success' => false, 'message' => 'Error: ' . $this->db->error];
        }
    }

    public function getEmployeeDetail(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, a.username, a.is_active AS acc_is_active
            FROM Employee e
            LEFT JOIN Account a ON a.account_id = e.account_id
            WHERE e.employee_id = ?
        ");
        $stmt->bind_param('i', $id); $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$employee) return null;

        $stmt = $this->db->prepare("
            SELECT month, year, base_salary, allowance, bonus, deduction, net_salary
            FROM Payroll
            WHERE employee_id = ?
            ORDER BY year DESC, month DESC
            LIMIT 6
        ");
        $stmt->bind_param('i', $id); $stmt->execute();
        $salaries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            'employee' => $employee,
            'salaries' => $salaries
        ];
    }

    public function getHoursWorked(int $employeeId, int $month, int $year): ?array
    {
        $nv = $this->db->query("SELECT full_name, monthly_salary FROM Employee WHERE employee_id = $employeeId")->fetch_assoc();
        if (!$nv) return null;

        $monthly_salary = floatval($nv['monthly_salary'] ?? 0);

        // Count working days excluding Sundays
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $working_days  = 0;
        for ($d = 1; $d <= $days_in_month; $d++) {
            $dow = date('N', mktime(0,0,0,$month,$d,$year));
            if ($dow != 7) $working_days++;
        }

        $luong_gio = ($working_days > 0 && $monthly_salary > 0)
            ? $monthly_salary / $working_days / 8
            : 0;

        $stmt = $this->db->prepare("
            SELECT work_date, check_in, check_out, status
            FROM Attendance
            WHERE employee_id = ?
              AND MONTH(work_date) = ?
              AND YEAR(work_date)  = ?
              AND status IN ('Present', 'Late')
        ");
        $stmt->bind_param('iii', $employeeId, $month, $year);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

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

            $minutes_worked = 0;
            if ($check_in && $check_out) {
                [$hi, $mi] = explode(':', $check_in);
                [$ho, $mo] = explode(':', $check_out);
                $minutes_worked = max(0, (intval($ho)*60+intval($mo)) - (intval($hi)*60+intval($mi)));
            }
            $hours_worked = $minutes_worked / 60;

            $luong_ngay = 0;
            if ($is_sunday) {
                $luong_ngay = $hours_worked * $luong_gio * 2;
                $days_sunday++;
            } elseif ($hours_worked > 8) {
                $gio_tc     = $hours_worked - 8;
                $luong_ngay = 8 * $luong_gio + $gio_tc * $luong_gio * 1.5;
                $tong_gio_hc += 8;
                $tong_gio_tc += $gio_tc;
            } else {
                $luong_ngay  = $hours_worked * $luong_gio;
                $tong_gio_hc += $hours_worked;
            }

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

        return [
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
        ];
    }

    public function savePayroll(int $employeeId, int $month, int $year, float $baseSalary, float $allowance, float $bonus, float $deduction, float $netSalary): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO Payroll (employee_id, month, year, base_salary, allowance, bonus, deduction, net_salary)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                base_salary  = VALUES(base_salary),
                allowance    = VALUES(allowance),
                bonus        = VALUES(bonus),
                deduction    = VALUES(deduction),
                net_salary   = VALUES(net_salary)
        ");
        $stmt->bind_param('iiiddddd', $employeeId, $month, $year, $baseSalary, $allowance, $bonus, $deduction, $netSalary);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // Attendance tracking
    public function getAttendanceStatsToday(string $date): array
    {
        $date = $this->db->real_escape_string($date);
        $total   = $this->db->query("SELECT COUNT(*) as c FROM Employee")->fetch_assoc()['c'];
        $present = $this->db->query("SELECT COUNT(*) as c FROM Attendance WHERE work_date = '$date' AND status = 'Present'")->fetch_assoc()['c'];
        $absent  = $this->db->query("SELECT COUNT(*) as c FROM Attendance WHERE work_date = '$date' AND status = 'Absent'")->fetch_assoc()['c'];
        $late    = $this->db->query("SELECT COUNT(*) as c FROM Attendance WHERE work_date = '$date' AND status = 'Late'")->fetch_assoc()['c'];
        $leave   = $this->db->query("SELECT COUNT(*) as c FROM Attendance WHERE work_date = '$date' AND (status = 'Day Off' OR status = 'On Leave')")->fetch_assoc()['c'];

        return [
            'total'   => (int)$total,
            'present' => (int)$present,
            'absent'  => (int)$absent,
            'late'    => (int)$late,
            'leave'   => (int)$leave
        ];
    }

    public function getAttendanceList(string $date, int $page, int $limit, string $search, string $status): array
    {
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
        $stmt = $this->db->prepare($countSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        // DATA
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

        $stmt = $this->db->prepare($dataSql);
        $stmt->bind_param($dataTypes, ...$dataParams);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        $stmt->close();

        return [
            'data'       => $rows,
            'total'      => (int)$total,
            'totalPages' => max(1, ceil($total / $limit))
        ];
    }

    public function getUncheckedEmployees(string $date): array
    {
        $stmt = $this->db->prepare("
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
        $stmt->close();
        return $data;
    }

    public function attendanceExists(int $employeeId, string $workDate): bool
    {
        $chk = $this->db->prepare("SELECT attendance_id FROM Attendance WHERE employee_id = ? AND work_date = ? LIMIT 1");
        $chk->bind_param('is', $employeeId, $workDate);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();
        return $exists;
    }

    public function addAttendance(int $employeeId, string $workDate, string $status, ?string $checkIn, ?string $checkOut, ?string $note): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO Attendance (employee_id, work_date, check_in, check_out, status, note)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isssss', $employeeId, $workDate, $checkIn, $checkOut, $status, $note);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateAttendance(int $attendanceId, string $status, ?string $checkIn, ?string $checkOut, ?string $note): bool
    {
        $stmt = $this->db->prepare("
            UPDATE Attendance SET status=?, check_in=?, check_out=?, note=?
            WHERE attendance_id=?
        ");
        $stmt->bind_param('ssssi', $status, $checkIn, $checkOut, $note, $attendanceId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteAttendance(int $attendanceId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM Attendance WHERE attendance_id = ?");
        $stmt->bind_param('i', $attendanceId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function bulkAttendance(string $workDate, string $status, ?string $checkIn, array $employeeIds): int
    {
        $inserted = 0;
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO Attendance (employee_id, work_date, check_in, status)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($employeeIds as $empId) {
            $eid = intval($empId);
            if ($eid <= 0) continue;
            $stmt->bind_param('isss', $eid, $workDate, $checkIn, $status);
            if ($stmt->execute()) $inserted++;
        }
        $stmt->close();
        return $inserted;
    }
}
