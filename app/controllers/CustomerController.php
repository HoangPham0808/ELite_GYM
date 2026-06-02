<?php

class CustomerController extends Controller
{
    public function api(): void
    {
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        header('Content-Type: application/json; charset=utf-8');
        header('ngrok-skip-browser-warning: true');

        $customerModel = new Customer();
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'get_customers':
                $page   = max(1, (int)($_GET['page']   ?? 1));
                $limit  = (int)($_GET['limit']  ?? 15);
                $search = trim($_GET['search']   ?? '');
                $gender = $_GET['gender']        ?? '';
                $status = $_GET['status']        ?? '';

                $res = $customerModel->getCustomersList($page, $limit, $search, $gender, $status);
                echo json_encode(array_merge(['success' => true, 'page' => $page, 'limit' => $limit], $res));
                break;

            case 'add_customer':
                $full_name    = trim($_POST['full_name']    ?? '');
                $date_of_birth= $_POST['date_of_birth']     ?? null;
                $gender       = $_POST['gender']            ?? null;
                $phone        = trim($_POST['phone']        ?? '');
                $email        = trim($_POST['email']        ?? '');
                $address      = trim($_POST['address']      ?? '');
                $username     = trim($_POST['username']     ?? '');
                $password_raw = trim($_POST['password']     ?? '');

                if ($full_name === '') {
                    echo json_encode(['success' => false, 'message' => 'Full name is required']);
                    break;
                }
                if ($username === '') {
                    echo json_encode(['success' => false, 'message' => 'Username is required when adding a new customer']);
                    break;
                }
                if (strlen($username) < 3) {
                    echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters']);
                    break;
                }

                if ($phone && $customerModel->phoneExists($phone)) {
                    echo json_encode(['success' => false, 'message' => 'Phone number already registered']);
                    break;
                }

                $accountModel = new Account();
                if ($accountModel->usernameExists($username)) {
                    echo json_encode(['success' => false, 'message' => 'Username already exists']);
                    break;
                }

                $raw_pass = ($password_raw !== '') ? $password_raw : 'elitegym@2025';
                $hashed   = password_hash($raw_pass, PASSWORD_BCRYPT);

                $db = Database::getInstance();
                $db->begin_transaction();
                try {
                    $accountId = $accountModel->createAccount($username, $hashed, 'Customer', 1);
                    if (!$accountId) throw new Exception('Account creation failed');

                    $customerId = $customerModel->createCustomerAdmin(
                        $full_name,
                        $date_of_birth ?: null,
                        $gender ?: null,
                        $phone ?: null,
                        $email ?: null,
                        $address ?: null,
                        $accountId
                    );
                    if (!$customerId) throw new Exception('Customer creation failed');

                    $db->commit();
                    $pw_msg = ($password_raw !== '') ? '' : ' | Default password: elitegym@2025';
                    echo json_encode([
                        'success' => true,
                        'message' => "Customer added successfully. Account: $username$pw_msg",
                        'id'      => $customerId
                    ]);
                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;

            case 'update_customer':
                $id            = (int)($_POST['id']            ?? 0);
                $full_name     = trim($_POST['full_name']       ?? '');
                $date_of_birth = $_POST['date_of_birth']        ?? null;
                $gender        = $_POST['gender']               ?? null;
                $phone         = trim($_POST['phone']           ?? '');
                $email         = trim($_POST['email']           ?? '');
                $address       = trim($_POST['address']         ?? '');

                if ($id === 0 || $full_name === '') {
                    echo json_encode(['success' => false, 'message' => 'Invalid data']);
                    break;
                }

                if ($phone && $customerModel->phoneExists($phone, $id)) {
                    echo json_encode(['success' => false, 'message' => 'Phone number already registered']);
                    break;
                }

                $ok = $customerModel->updateCustomer(
                    $id,
                    $full_name,
                    $date_of_birth ?: null,
                    $gender ?: null,
                    $phone ?: null,
                    $email ?: null,
                    $address ?: null
                );
                echo json_encode($ok
                    ? ['success' => true,  'message' => 'Customer updated successfully']
                    : ['success' => false, 'message' => 'Failed to update customer']);
                break;

            case 'delete_customer':
                $id = (int)($_POST['id'] ?? 0);
                if ($id === 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                    break;
                }
                echo json_encode($customerModel->deleteCustomer($id));
                break;

            case 'get_detail':
                $id = (int)($_GET['id'] ?? 0);
                if ($id === 0) {
                    echo json_encode(['success' => false]);
                    break;
                }
                $res = $customerModel->getCustomerDetail($id);
                if ($res === null) {
                    echo json_encode(['success' => false, 'message' => 'Customer not found']);
                } else {
                    echo json_encode(array_merge(['success' => true], $res));
                }
                break;

            case 'get_stats':
                echo json_encode(array_merge(['success' => true], $customerModel->getCustomerStats()));
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }
}
