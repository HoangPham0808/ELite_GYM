<?php

class AdminController extends Controller
{
    /** @var array<string, string> slug → view path */
    private array $moduleMap = [
        'overview'        => 'admin/overview',
        'customer'        => 'admin/customer_management',
        'review'          => 'admin/review_management',
        'employee'        => 'admin/employee_management',
        'gym'             => 'admin/gym_management',
        'invoice'         => 'admin/invoice_management',
        'package'         => 'admin/package_management',
        'promotion'       => 'admin/promotion_management',
        'schedule'        => 'admin/schedule_management',
        'facility'        => 'admin/facilities_management',
        'statistics'      => 'admin/statistics',
        'account'         => 'admin/account_management',
        'profile'         => 'admin/profile',
        'setting-system'  => 'admin/setting_system',
        'setting-landing' => 'admin/setting_landing',
        'setting-gps'     => 'admin/setting_gps',
        'attendance'      => 'admin/employee_attendance',
    ];

    public function dashboard(): void
    {
        AuthMiddleware::requireRole('Admin');
        $this->view('admin/dashboard');
    }

    public function module(string $page): void
    {
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        $view = $this->moduleMap[$page] ?? null;
        if (!$view) {
            http_response_code(404);
            echo 'Module không tồn tại';
            return;
        }
        $this->view($view);
    }

    public function apiAccount(): void
    {
        AuthMiddleware::requireRole('Admin');
        header('Content-Type: application/json; charset=utf-8');
        
        $accountModel = new Account();
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        
        switch ($action) {
            case 'get_stats':
                echo json_encode(array_merge(['success' => true], $accountModel->getAdminStats()));
                break;
                
            case 'get_accounts':
                $page   = max(1, (int)($_GET['page']   ?? 1));
                $limit  = (int)($_GET['limit']  ?? 15);
                $search = trim($_GET['search']  ?? '');
                $role   = trim($_GET['role']    ?? '');
                $status = trim($_GET['status']  ?? '');
                
                $result = $accountModel->getAccountsList($page, $limit, $search, $role, $status);
                echo json_encode(array_merge(['success' => true, 'page' => $page], $result));
                break;
                
            case 'get_account_detail':
                $id = (int)($_GET['id'] ?? 0);
                $result = $accountModel->getAccountDetail($id);
                echo json_encode(array_merge(['success' => true], $result));
                break;
                
            case 'add_account':
                $username  = trim($_POST['username']   ?? '');
                $pass      = trim($_POST['password']   ?? '');
                $role      = trim($_POST['role']       ?? 'Customer');
                $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
                
                if (!$username || !$pass) {
                    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
                    break;
                }
                if (strlen($pass) < 6) {
                    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
                    break;
                }
                if ($accountModel->usernameExists($username)) {
                    echo json_encode(['success' => false, 'message' => 'Username already exists']);
                    break;
                }
                
                $id = $accountModel->createAccount($username, $pass, $role, $is_active);
                if ($id) {
                    echo json_encode(['success' => true, 'message' => 'Account created successfully', 'id' => $id]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create account']);
                }
                break;
                
            case 'update_account':
                $id        = (int)($_POST['id']        ?? 0);
                $role      = trim($_POST['role']       ?? '');
                $is_active = (int)($_POST['is_active'] ?? 1);
                $pass      = trim($_POST['password']   ?? '');
                
                if (!$id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                    break;
                }
                if ($pass && strlen($pass) < 6) {
                    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
                    break;
                }
                
                $ok = $accountModel->updateAccount($id, $role, $is_active, $pass);
                echo json_encode($ok
                    ? ['success' => true,  'message' => 'Account updated successfully']
                    : ['success' => false, 'message' => 'Failed to update account']);
                break;
                
            case 'toggle_status':
                $id  = (int)($_POST['id'] ?? 0);
                $newActive = $accountModel->toggleStatus($id);
                if ($newActive === null) {
                    echo json_encode(['success' => false, 'message' => 'Account not found']);
                } else {
                    echo json_encode([
                        'success'   => true,
                        'message'   => $newActive ? 'Account unlocked' : 'Account locked',
                        'is_active' => $newActive,
                    ]);
                }
                break;
                
            case 'reset_password':
                $id   = (int)($_POST['id']       ?? 0);
                $pass = trim($_POST['password']  ?? '');
                if (!$id || strlen($pass) < 6) {
                    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
                    break;
                }
                $ok = $accountModel->resetPassword($id, $pass);
                echo json_encode($ok
                    ? ['success' => true,  'message' => 'Password reset successfully']
                    : ['success' => false, 'message' => 'Failed to reset password']);
                break;
                
            case 'delete_account':
                $id  = (int)($_POST['id'] ?? 0);
                $res = $accountModel->deleteAccount($id);
                echo json_encode($res);
                break;
                
            case 'get_login_history':
                $page   = max(1, (int)($_GET['page']   ?? 1));
                $limit  = (int)($_GET['limit']  ?? 20);
                $search = trim($_GET['search']  ?? '');
                $result = trim($_GET['result']  ?? '');
                $date   = trim($_GET['date']    ?? '');
                $acc_id = (int)($_GET['acc_id'] ?? 0);
                
                $res = $accountModel->getLoginHistory($page, $limit, $search, $result, $date, $acc_id);
                echo json_encode(array_merge(['success' => true, 'page' => $page], $res));
                break;
                
            case 'clear_login_history':
                $id = (int)($_POST['account_id'] ?? 0);
                $accountModel->clearLoginHistory($id);
                echo json_encode(['success' => true, 'message' => 'Login history cleared']);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }

    public function apiGym(): void
    {
        AuthMiddleware::requireRole('Admin');
        header('Content-Type: application/json; charset=utf-8');

        $gymModel = new Gym();
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'get_package_types':
                echo json_encode(['success' => true, 'data' => $gymModel->getPackageTypes()]);
                break;

            case 'get_stats':
                echo json_encode(array_merge(['success' => true], $gymModel->getStats()));
                break;

            case 'get_gyms':
                $page   = max(1, (int)($_GET['page']  ?? 1));
                $limit  = (int)($_GET['limit']         ?? 12);
                $search = trim($_GET['search']          ?? '');
                $status = trim($_GET['status']          ?? '');
                $sort   = $_GET['sort']                 ?? 'id_desc';

                $res = $gymModel->getGymsList($page, $limit, $search, $status, $sort);
                echo json_encode(array_merge(['success' => true, 'limit' => $limit], $res));
                break;

            case 'get_detail':
                $id = (int)($_GET['id'] ?? 0);
                if (!$id) {
                    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
                    break;
                }
                $res = $gymModel->getDetail($id);
                if ($res === null) {
                    echo json_encode(['success' => false, 'message' => 'Không tìm thấy phòng tập']);
                } else {
                    echo json_encode(array_merge(['success' => true], $res));
                }
                break;

            case 'add_gym':
                $room_name   = trim($_POST['ten_phong']  ?? '');
                $status      = trim($_POST['trang_thai'] ?? 'Active');
                $status      = str_replace(["\r", "\n"], '', $status);
                $capacity    = (int)($_POST['suc_chua']  ?? 0) ?: null;
                $area        = (float)($_POST['dien_tich'] ?? 0) ?: null;
                $floor_raw   = trim($_POST['tang']        ?? '');
                $floor       = $floor_raw !== '' ? (int)$floor_raw : null;
                $open_time   = trim($_POST['gio_mo']       ?? '') ?: null;
                $close_time  = trim($_POST['gio_dong']     ?? '') ?: null;
                $description     = trim($_POST['mo_ta']         ?? '') ?: null;
                $package_type_id = intval($_POST['package_type_id'] ?? 0) ?: null;

                if (!$room_name) {
                    echo json_encode(['success' => false, 'message' => 'Tên phòng không được để trống']);
                    break;
                }
                if ($gymModel->roomNameExists($room_name)) {
                    echo json_encode(['success' => false, 'message' => 'Tên phòng tập đã tồn tại']);
                    break;
                }

                $id = $gymModel->addGym($room_name, $status, $capacity, $area, $floor, $open_time, $close_time, $description, $package_type_id);
                echo json_encode($id
                    ? ['success' => true, 'message' => 'Thêm phòng tập thành công', 'id' => $id]
                    : ['success' => false, 'message' => 'Không thể thêm phòng tập']);
                break;

            case 'update_gym':
                $id          = (int)($_POST['id']          ?? 0);
                $room_name   = trim($_POST['ten_phong']    ?? '');
                $status      = trim($_POST['trang_thai']   ?? 'Active');
                $status      = str_replace(["\r", "\n"], '', $status);
                $capacity    = (int)($_POST['suc_chua']    ?? 0) ?: null;
                $area        = (float)($_POST['dien_tich'] ?? 0) ?: null;
                $floor_raw   = trim($_POST['tang']         ?? '');
                $floor       = $floor_raw !== '' ? (int)$floor_raw : null;
                $open_time   = trim($_POST['gio_mo']       ?? '') ?: null;
                $close_time  = trim($_POST['gio_dong']     ?? '') ?: null;
                $description     = trim($_POST['mo_ta']         ?? '') ?: null;
                $package_type_id = intval($_POST['package_type_id'] ?? 0) ?: null;

                if (!$id || !$room_name) {
                    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
                    break;
                }
                if ($gymModel->roomNameExists($room_name, $id)) {
                    echo json_encode(['success' => false, 'message' => 'Tên phòng tập đã tồn tại']);
                    break;
                }

                $ok = $gymModel->updateGym($id, $room_name, $status, $capacity, $area, $floor, $open_time, $close_time, $description, $package_type_id);
                echo json_encode($ok
                    ? ['success' => true, 'message' => 'Cập nhật phòng tập thành công']
                    : ['success' => false, 'message' => 'Không thể cập nhật phòng tập']);
                break;

            case 'delete_gym':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) {
                    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
                    break;
                }
                echo json_encode($gymModel->deleteGym($id));
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
        }
    }
}
