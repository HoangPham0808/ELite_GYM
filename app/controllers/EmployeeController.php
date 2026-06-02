<?php

class EmployeeController extends Controller
{
    public function api(): void
    {
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        header('Content-Type: application/json; charset=utf-8');

        $employeeModel = new Employee();
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'get_stats':
                echo json_encode(array_merge(['success' => true], $employeeModel->getEmployeeStats()));
                break;

            case 'get_employees':
                $page   = max(1, intval($_GET['page']   ?? 1));
                $limit  = intval($_GET['limit']  ?? 15);
                $search = trim($_GET['search']   ?? '');
                $gender = trim($_GET['gender']   ?? '');
                $sort   = $_GET['sort']          ?? 'id_desc';

                $res = $employeeModel->getEmployeesList($page, $limit, $search, $gender, $sort);
                echo json_encode(array_merge(['success' => true, 'page' => $page, 'limit' => $limit], $res));
                break;

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
                    echo json_encode(['success' => false, 'message' => 'Full name is required']); break;
                }
                if ($username === '') {
                    echo json_encode(['success' => false, 'message' => 'Username is required when adding a new employee']); break;
                }
                if (strlen($username) < 3) {
                    echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters']); break;
                }

                if ($email !== null && $employeeModel->emailExists($email)) {
                    echo json_encode(['success' => false, 'message' => 'Email already in use']); break;
                }
                if ($phone !== null && $employeeModel->phoneExists($phone)) {
                    echo json_encode(['success' => false, 'message' => 'Phone number already in use']); break;
                }

                $accountModel = new Account();
                if ($accountModel->usernameExists($username)) {
                    echo json_encode(['success' => false, 'message' => 'Username already exists']); break;
                }

                $raw_pass = ($password_raw !== '') ? $password_raw : 'elitegym@2025';
                $hashed   = password_hash($raw_pass, PASSWORD_BCRYPT);

                $db = Database::getInstance();
                $db->begin_transaction();
                try {
                    $accountId = $accountModel->createAccount($username, $hashed, 'Employee', 1);
                    if (!$accountId) throw new Exception('Account creation failed');

                    $empId = $employeeModel->createEmployee($full_name, $date_of_birth, $gender, $position, $phone, $email, $address, $hire_date, $accountId, $monthly_salary);
                    if (!$empId) throw new Exception('Employee creation failed');

                    $db->commit();
                    $pw_msg = ($password_raw !== '') ? '' : ' | Default password: elitegym@2025';
                    echo json_encode([
                        'success' => true,
                        'message' => "Employee added successfully. Account: $username$pw_msg",
                        'id'      => $empId
                    ]);
                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;

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
                    echo json_encode(['success' => false, 'message' => 'Invalid data']); break;
                }

                if ($email !== null && $employeeModel->emailExists($email, $id)) {
                    echo json_encode(['success' => false, 'message' => 'Email already in use']); break;
                }
                if ($phone !== null && $employeeModel->phoneExists($phone, $id)) {
                    echo json_encode(['success' => false, 'message' => 'Phone number already in use']); break;
                }

                $ok = $employeeModel->updateEmployee($id, $full_name, $date_of_birth, $gender, $position, $phone, $email, $address, $hire_date, $monthly_salary);
                echo json_encode($ok
                    ? ['success' => true,  'message' => 'Employee updated successfully']
                    : ['success' => false, 'message' => 'Failed to update employee']);
                break;

            case 'delete_employee':
                $id = intval($_POST['id'] ?? 0);
                if ($id === 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid ID']); break;
                }
                echo json_encode($employeeModel->deleteEmployee($id));
                break;

            case 'bulk_delete_employees':
                $raw_ids = $_POST['ids'] ?? [];
                $ids = array_filter(array_map('intval', (array)$raw_ids));

                if (empty($ids)) {
                    echo json_encode(['success' => false, 'message' => 'Không có ID nào được chọn']); break;
                }

                $deleted = 0;
                $skipped = 0;
                foreach ($ids as $id) {
                    $res = $employeeModel->deleteEmployee($id);
                    if ($res['success']) {
                        $deleted++;
                    } else {
                        $skipped++;
                    }
                }

                if ($deleted === 0 && $skipped > 0) {
                    echo json_encode([
                        'success' => false,
                        'message' => "Không thể xóa: tất cả nhân viên đã chọn có dữ liệu liên quan.",
                        'skipped' => $skipped
                    ]);
                } else {
                    $msg = "Đã xóa $deleted nhân viên.";
                    if ($skipped > 0) $msg .= " Bỏ qua $skipped người có dữ liệu liên quan.";
                    echo json_encode([
                        'success' => true,
                        'message' => $msg,
                        'deleted' => $deleted,
                        'skipped' => $skipped
                    ]);
                }
                break;

            case 'get_detail':
                $id = intval($_GET['id'] ?? 0);
                if ($id === 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid ID']); break;
                }
                $res = $employeeModel->getEmployeeDetail($id);
                if (!$res) {
                    echo json_encode(['success' => false, 'message' => 'Employee not found']);
                } else {
                    echo json_encode(array_merge(['success' => true], $res));
                }
                break;

            case 'get_hours_worked':
                $employee_id = intval($_GET['employee_id'] ?? 0);
                $month       = intval($_GET['month']       ?? 0);
                $year        = intval($_GET['year']        ?? 0);

                if ($employee_id === 0 || $month < 1 || $month > 12 || $year < 2000) {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters']); break;
                }
                $res = $employeeModel->getHoursWorked($employee_id, $month, $year);
                if (!$res) {
                    echo json_encode(['success' => false, 'message' => 'Employee not found']);
                } else {
                    echo json_encode(array_merge(['success' => true], $res));
                }
                break;

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
                    echo json_encode(['success' => false, 'message' => 'Invalid data']); break;
                }
                if ($base_salary < 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid salary']); break;
                }
                $ok = $employeeModel->savePayroll($employee_id, $month, $year, $base_salary, $allowance, $bonus, $deduction, $net_salary);
                echo json_encode($ok
                    ? ['success' => true,  'message' => "Payroll for {$month}/{$year} saved successfully!"]
                    : ['success' => false, 'message' => 'Failed to save payroll']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }

    public function apiAttendance(): void
    {
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        header('Content-Type: application/json; charset=utf-8');

        $employeeModel = new Employee();
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'get_stats':
                $date = $_GET['date'] ?? date('Y-m-d');
                echo json_encode(array_merge(['success' => true], $employeeModel->getAttendanceStatsToday($date)));
                break;

            case 'get_attendance':
                $date   = $_GET['date'] ?? date('Y-m-d');
                $page   = max(1, intval($_GET['page'] ?? 1));
                $limit  = intval($_GET['limit'] ?? 20);
                $search = trim($_GET['search'] ?? '');
                $status = trim($_GET['status'] ?? '');

                $res = $employeeModel->getAttendanceList($date, $page, $limit, $search, $status);
                echo json_encode(array_merge(['success' => true, 'page' => $page, 'limit' => $limit], $res));
                break;

            case 'get_unchecked':
                $date = $_GET['date'] ?? date('Y-m-d');
                echo json_encode(['success' => true, 'data' => $employeeModel->getUncheckedEmployees($date)]);
                break;

            case 'add_attendance':
                $employee_id = intval($_POST['employee_id'] ?? 0);
                $work_date   = trim($_POST['work_date'] ?? '');
                $status      = trim($_POST['status'] ?? '');
                $check_in    = trim($_POST['check_in']  ?? '') ?: null;
                $check_out   = trim($_POST['check_out'] ?? '') ?: null;
                $note        = trim($_POST['note'] ?? $_POST['notes'] ?? '') ?: null;

                if ($employee_id === 0 || $work_date === '' || $status === '') {
                    echo json_encode(['success' => false, 'message' => 'Invalid data']); break;
                }
                if ($employeeModel->attendanceExists($employee_id, $work_date)) {
                    echo json_encode(['success' => false, 'message' => 'Attendance already recorded for this employee today']); break;
                }

                $ok = $employeeModel->addAttendance($employee_id, $work_date, $status, $check_in, $check_out, $note);
                echo json_encode($ok
                    ? ['success' => true, 'message' => 'Attendance recorded successfully']
                    : ['success' => false, 'message' => 'Failed to record attendance']);
                break;

            case 'update_attendance':
                $attendance_id = intval($_POST['attendance_id'] ?? 0);
                $status        = trim($_POST['status']    ?? '');
                $check_in      = trim($_POST['check_in']  ?? '') ?: null;
                $check_out     = trim($_POST['check_out'] ?? '') ?: null;
                $note          = trim($_POST['note'] ?? $_POST['notes'] ?? '') ?: null;

                if ($attendance_id === 0 || $status === '') {
                    echo json_encode(['success' => false, 'message' => 'Invalid data']); break;
                }
                $ok = $employeeModel->updateAttendance($attendance_id, $status, $check_in, $check_out, $note);
                echo json_encode($ok
                    ? ['success' => true,  'message' => 'Attendance updated successfully']
                    : ['success' => false, 'message' => 'Failed to update attendance']);
                break;

            case 'delete_attendance':
                $attendance_id = intval($_POST['attendance_id'] ?? 0);
                if ($attendance_id === 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid ID']); break;
                }
                $ok = $employeeModel->deleteAttendance($attendance_id);
                echo json_encode($ok
                    ? ['success' => true, 'message' => 'Attendance record deleted']
                    : ['success' => false, 'message' => 'Failed to delete attendance']);
                break;

            case 'bulk_attendance':
                $work_date = trim($_POST['work_date'] ?? '');
                $status    = trim($_POST['status']    ?? '');
                $check_in  = trim($_POST['check_in']  ?? '') ?: null;
                $ids_json  = $_POST['employee_ids'] ?? '[]';
                $ids       = json_decode($ids_json, true);

                if ($work_date === '' || $status === '' || !is_array($ids) || count($ids) === 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid data']); break;
                }
                $inserted = $employeeModel->bulkAttendance($work_date, $status, $check_in, $ids);
                echo json_encode([
                    'success' => true,
                    'message' => "Bulk attendance recorded for $inserted employees"
                ]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }
}
