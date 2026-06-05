<?php

class ScheduleController extends Controller
{
    private Schedule $scheduleModel;

    public function __construct()
    {
        $this->scheduleModel = new Schedule();
    }

    // API dành cho Khách hàng đăng ký / hủy lớp học tập
    public function api(): void
    {
        AuthMiddleware::requireRole('Customer');
        ensureSession(); // helper from session_helper.php
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method không hợp lệ']);
            exit;
        }

        $account_id = (int)$_SESSION['account_id'];
        $action     = trim($_POST['action']   ?? '');   // 'register' | 'cancel'
        $class_id   = (int)($_POST['class_id'] ?? 0);

        $cid = $this->scheduleModel->getCustomerInfoByAccount($account_id);
        if (!$cid || !$class_id || !in_array($action, ['register', 'cancel'], true)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit;
        }

        $cls = $this->scheduleModel->getClassInfo($class_id);
        if (!$cls) {
            echo json_encode(['success' => false, 'message' => 'Lớp không tồn tại']);
            exit;
        }

        // Không cho thay đổi lớp đã diễn ra
        if (strtotime($cls['start_time']) < time()) {
            echo json_encode(['success' => false, 'message' => 'Lớp học đã diễn ra, không thể thay đổi']);
            exit;
        }

        if ($action === 'register') {
            // Kiểm tra PackageType
            if ($cls['room_sort_order'] !== null) {
                $cust_sort = $this->scheduleModel->getCustomerActivePackageSortOrder($cid);
                if ($cust_sort === null) {
                    echo json_encode(['success' => false, 'message' => 'Bạn chưa có gói tập. Vui lòng mua gói để đăng ký lớp.']);
                    exit;
                }
                if ((int)$cust_sort < (int)$cls['room_sort_order']) {
                    echo json_encode(['success' => false, 'message' => 'Gói tập của bạn không đủ để đăng ký lớp tại phòng ' . $cls['room_type_name'] . '. Vui lòng nâng cấp gói.']);
                    exit;
                }
            }

            // Kiểm tra đã đăng ký chưa
            if ($this->scheduleModel->checkClassRegistrationExists($class_id, $cid)) {
                echo json_encode(['success' => false, 'message' => 'Bạn đã đăng ký lớp này rồi']);
                exit;
            }

            // Kiểm tra trùng slot giờ tập
            $conflict = $this->scheduleModel->checkTimeSlotConflict($cid, $cls['start_time']);
            if ($conflict) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Bạn đã đăng ký một lớp khác vào cùng giờ ' . $conflict['time'] . ' ngày ' . $conflict['date'] . '. Hủy lớp đó trước nếu muốn đổi sang lớp này.'
                ]);
                exit;
            }

            $ok = $this->scheduleModel->registerClass($class_id, $cid);
            echo json_encode([
                'success' => $ok,
                'message' => $ok ? 'Đăng ký lớp thành công!' : 'Đăng ký thất bại, vui lòng thử lại',
                'action'  => 'registered'
            ]);
        } elseif ($action === 'cancel') {
            if (!$this->scheduleModel->checkClassRegistrationExists($class_id, $cid)) {
                echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng ký lớp này']);
                exit;
            }

            $ok = $this->scheduleModel->cancelClass($class_id, $cid);
            echo json_encode([
                'success' => $ok,
                'message' => $ok ? 'Đã hủy đăng ký lớp' : 'Hủy thất bại, vui lòng thử lại',
                'action'  => 'cancelled'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        }
    }

    // API dành cho Admin & Staff quản lý lịch biểu
    public function apiAdmin(): void
    {
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        ensureSession(); // helper from session_helper.php

        // Suppress HTML error output immediately — must happen before any output
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        error_reporting(E_ALL);

        // Clean any previous output buffer (e.g. from framework bootstrapping)
        while (ob_get_level() > 0) ob_end_clean();

        header('Content-Type: application/json; charset=utf-8');

        // Custom error handler: convert PHP warnings/notices to exceptions
        set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
            if ($errno & (E_ERROR | E_WARNING | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            }
            return true; // suppress notices/deprecations
        });

        try {
            $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'get_stats':
                echo json_encode(array_merge(['success' => true], $this->scheduleModel->getAdminStats()));
                break;

            case 'get_list_view':
            case 'get_schedules':
                $page       = max(1, (int)($_GET['page'] ?? 1));
                $limit      = (int)($_GET['limit'] ?? 15);
                $search     = trim($_GET['search'] ?? '');
                $trainer_id = trim($_GET['hlv_id'] ?? '');
                $room_id    = trim($_GET['room_id'] ?? '');
                $class_type = trim($_GET['class_type'] ?? '');
                $from       = trim($_GET['from'] ?? trim($_GET['start_time'] ?? ''));
                $to         = trim($_GET['to'] ?? trim($_GET['end_time'] ?? ''));

                $res = $this->scheduleModel->getSchedulesList($page, $limit, $search, $trainer_id, $room_id, $from, $to, $class_type);
                echo json_encode(array_merge(['success' => true, 'page' => $page], $res));
                break;

            case 'get_day_schedules':
                $date     = trim($_GET['date'] ?? date('Y-m-d'));
                $room_id_f = trim($_GET['room_id'] ?? '');
                // Reuse getWeekSchedules with single-day range
                $rows = $this->scheduleModel->getWeekSchedules($date, $room_id_f, '', true);
                echo json_encode(['success' => true, 'data' => $rows, 'date' => $date]);
                break;

            case 'get_week_schedules':
                $week_start = trim($_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week')));
                $room_id_f  = trim($_GET['room_id'] ?? '');
                $class_type_f = trim($_GET['class_type'] ?? '');
                $rows = $this->scheduleModel->getWeekSchedules($week_start, $room_id_f, $class_type_f);
                echo json_encode([
                    'success'    => true,
                    'data'       => $rows,
                    'week_start' => $week_start,
                    'week_end'   => date('Y-m-d', strtotime($week_start . ' +6 days'))
                ]);
                break;

            case 'get_class_detail':
            case 'get_schedule_detail':
                $id = (int)($_GET['id'] ?? 0);
                $res = $this->scheduleModel->getScheduleDetail($id);
                if (!$res) {
                    echo json_encode(['success' => false, 'message' => 'Không tìm thấy lớp học']);
                } else {
                    echo json_encode(array_merge(['success' => true], $res));
                }
                break;

            case 'search_customer':
                $search = trim($_GET['q'] ?? '');
                $pkgReq = trim($_GET['package_requirement'] ?? '');
                echo json_encode(['success' => true, 'data' => $this->scheduleModel->getCustomersSearch($search, $pkgReq)]);
                break;

            case 'register_customer_proxy':
            case 'add_registration':
                $class_id    = (int)($_POST['class_id'] ?? 0);
                $customer_id = (int)($_POST['customer_id'] ?? 0);
                if (!$class_id || !$customer_id) {
                    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu']);
                    return;
                }
                if ($this->scheduleModel->checkClassRegistrationExists($class_id, $customer_id)) {
                    echo json_encode(['success' => false, 'message' => 'Khách hàng đã đăng ký lớp này']);
                    return;
                }
                $ok = $this->scheduleModel->addRegistration($class_id, $customer_id);
                echo json_encode([
                    'success' => $ok,
                    'message' => $ok ? 'Đăng ký thành công' : 'Lỗi DB'
                ]);
                break;

            case 'cancel_customer_proxy':
            case 'delete_registration':
                $class_id    = (int)($_POST['class_id'] ?? 0);
                $customer_id = (int)($_POST['customer_id'] ?? 0);
                if (!$class_id || !$customer_id) {
                    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu']);
                    return;
                }
                $ok = $this->scheduleModel->cancelRegistrationByClassAndCustomer($class_id, $customer_id);
                echo json_encode([
                    'success' => $ok,
                    'message' => $ok ? 'Đã hủy đăng ký' : 'Lỗi DB'
                ]);
                break;

            case 'add_class':
            case 'add_schedule':
                $class_name  = trim($_POST['class_name'] ?? '');
                $trainer_id  = (int)($_POST['trainer_id'] ?? 0) ?: null;
                $room_id     = (int)($_POST['room_id'] ?? 0) ?: null;
                $start_time  = trim($_POST['start_time'] ?? '');
                $end_time    = trim($_POST['end_time'] ?? '') ?: null;
                $max_capacity = (int)($_POST['max_capacity'] ?? 0) ?: null;
                $repeat_type = trim($_POST['repeat_type'] ?? 'none');

                if (!$class_name || !$start_time) {
                    echo json_encode(['success' => false, 'message' => 'Tên lớp và giờ bắt đầu là bắt buộc']);
                    return;
                }
                if ($end_time && strtotime($end_time) <= strtotime($start_time)) {
                    echo json_encode(['success' => false, 'message' => 'Giờ kết thúc phải lớn hơn giờ bắt đầu']);
                    return;
                }

                // Build list of start times based on repeat_type
                $schedule_times = [$start_time];
                $end_times      = [$end_time];
                $duration_secs  = ($end_time) ? (strtotime($end_time) - strtotime($start_time)) : 0;

                if ($repeat_type === 'daily') {
                    $end_of_month = strtotime(date('Y-m-t 23:59:59', strtotime($start_time)));
                    $cur = strtotime($start_time) + 86400;
                    while ($cur <= $end_of_month) {
                        $schedule_times[] = date('Y-m-d H:i:s', $cur);
                        $end_times[]      = $duration_secs ? date('Y-m-d H:i:s', $cur + $duration_secs) : null;
                        $cur += 86400;
                    }
                } elseif ($repeat_type === 'weekly') {
                    for ($w = 1; $w <= 3; $w++) {
                        $cur = strtotime($start_time) + $w * 7 * 86400;
                        $schedule_times[] = date('Y-m-d H:i:s', $cur);
                        $end_times[]      = $duration_secs ? date('Y-m-d H:i:s', $cur + $duration_secs) : null;
                    }
                }

                $created = 0;
                $errors  = [];
                foreach ($schedule_times as $i => $st) {
                    $et = $end_times[$i];
                    if ($room_id) {
                        $roomConflict = $this->checkRoomTimeRange($room_id, $st, $et);
                        if ($roomConflict !== true) { $errors[] = $roomConflict; continue; }
                        $conflict = $this->scheduleModel->checkRoomConflict($room_id, $st, $et);
                        if ($conflict) {
                            $cs = date('H:i', strtotime($conflict['start_time']));
                            $ce = $conflict['end_time'] ? date('H:i', strtotime($conflict['end_time'])) : '?';
                            $errors[] = "Bỏ qua {$st}: phòng đã có lớp \"{$conflict['class_name']}\" ({$cs}–{$ce})";
                            continue;
                        }
                    }
                    $new_id = $this->scheduleModel->addSchedule($class_name, $trainer_id, $room_id, $st, $et, $max_capacity);
                    if ($new_id) $created++;
                }

                if ($created === 0) {
                    echo json_encode(['success' => false, 'message' => 'Không tạo được buổi tập nào. ' . implode('; ', $errors)]);
                } else {
                    $msg = "Đã thêm {$created} buổi tập thành công";
                    if ($errors) $msg .= ' (' . count($errors) . ' bỏ qua do trùng lịch)';
                    echo json_encode(['success' => true, 'message' => $msg, 'created' => $created]);
                }
                break;

            case 'update_class':
            case 'update_schedule':
                $id          = (int)($_POST['id'] ?? 0);
                $class_name  = trim($_POST['class_name'] ?? '');
                $trainer_id  = (int)($_POST['trainer_id'] ?? 0) ?: null;
                $room_id     = (int)($_POST['room_id'] ?? 0) ?: null;
                $start_time  = trim($_POST['start_time'] ?? '');
                $end_time    = trim($_POST['end_time'] ?? '') ?: null;
                $max_capacity = (int)($_POST['max_capacity'] ?? 0) ?: null;

                if (!$id || !$class_name || !$start_time) {
                    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
                    return;
                }
                if ($end_time && strtotime($end_time) <= strtotime($start_time)) {
                    echo json_encode(['success' => false, 'message' => 'Giờ kết thúc phải lớn hơn giờ bắt đầu']);
                    return;
                }
                if ($room_id) {
                    $roomConflict = $this->checkRoomTimeRange($room_id, $start_time, $end_time);
                    if ($roomConflict !== true) {
                        echo json_encode(['success' => false, 'message' => $roomConflict]);
                        return;
                    }
                    $conflict = $this->scheduleModel->checkRoomConflict($room_id, $start_time, $end_time, $id);
                    if ($conflict) {
                        $cs = date('H:i', strtotime($conflict['start_time']));
                        $ce = $conflict['end_time'] ? date('H:i', strtotime($conflict['end_time'])) : '?';
                        echo json_encode(['success' => false, 'message' => "Phòng này đã có lớp \"{$conflict['class_name']}\" ({$cs}–{$ce}). Vui lòng chọn khung giờ không bị trùng."]);
                        return;
                    }
                }

                $ok = $this->scheduleModel->updateSchedule($id, $class_name, $trainer_id, $room_id, $start_time, $end_time, $max_capacity);
                echo json_encode([
                    'success' => $ok,
                    'message' => $ok ? 'Cập nhật thành công' : 'Lỗi DB'
                ]);
                break;

            case 'delete_class':
            case 'delete_schedule':
                $id = (int)($_POST['id'] ?? 0);
                $ok = $this->scheduleModel->deleteSchedule($id);
                echo json_encode([
                    'success' => $ok,
                    'message' => $ok ? 'Đã xóa buổi tập' : 'Lỗi DB'
                ]);
                break;

            case 'assign_trainer':
                $class_id   = (int)($_POST['class_id'] ?? 0);
                $trainer_id = (int)($_POST['trainer_id'] ?? 0);
                if (!$class_id || !$trainer_id) {
                    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu']);
                    return;
                }

                $thisClass = $this->scheduleModel->getClassInfo($class_id);
                if (!$thisClass) {
                    echo json_encode(['success' => false, 'message' => 'Không tìm thấy buổi tập']);
                    return;
                }

                // Lấy thông tin thời gian lớp từ dữ liệu đã có
                $class_date = date('Y-m-d', strtotime($thisClass['start_time']));
                $start_time = $thisClass['start_time'];
                $end_time   = $thisClass['end_time'];

                // Kiểm tra HLV trùng ca dạy
                $conflict = $this->scheduleModel->checkTrainerConflict($trainer_id, $class_date, $start_time, $end_time, $class_id);
                if ($conflict) {
                    $cs = date('H:i', strtotime($conflict['start_time']));
                    $ce = $conflict['end_time'] ? date('H:i', strtotime($conflict['end_time'])) : '?';
                    echo json_encode(['success' => false, 'message' => "HLV đã được phân công lớp \"{$conflict['class_name']}\" ({$cs}–{$ce}) trong khung giờ này."]);
                    return;
                }

                $ok = $this->scheduleModel->assignTrainer($class_id, $trainer_id);
                echo json_encode([
                    'success' => $ok,
                    'message' => $ok ? 'Đã đăng ký dạy buổi này' : 'Lỗi DB'
                ]);
                break;

            case 'unassign_trainer':
                $class_id   = (int)($_POST['class_id'] ?? 0);
                $trainer_id = (int)($_POST['trainer_id'] ?? 0);
                if (!$class_id || !$trainer_id) {
                    echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu']);
                    return;
                }

                $ok = $this->scheduleModel->unassignTrainer($class_id, $trainer_id);
                echo json_encode([
                    'success' => $ok,
                    'message' => $ok ? 'Đã hủy đăng ký dạy' : 'Bạn không phải HLV phụ trách buổi này'
                ]);
                break;

            case 'get_trainers':
                echo json_encode(['success' => true, 'data' => $this->scheduleModel->getTrainers()]);
                break;

            case 'get_rooms':
                echo json_encode(['success' => true, 'data' => $this->scheduleModel->getRooms()]);
                break;

            case 'get_customers':
                $search = trim($_GET['search'] ?? '');
                echo json_encode(['success' => true, 'data' => $this->scheduleModel->getCustomersSearch($search)]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
        }

        } catch (\Throwable $e) {
            // Catch any exception or error — return clean JSON, never HTML
            http_response_code(200); // keep 200 so fetchJson doesn't throw on status check
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi server: ' . $e->getMessage() . ' (dòng ' . $e->getLine() . ')'
            ]);
        } finally {
            restore_error_handler();
        }
    }

    // AI PT Schedule Generator - Server-Sent Events (SSE)
    public function ai(): void
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        ensureSession(); // helper from session_helper.php
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('Access-Control-Allow-Origin: *');

        $sseFlush = function() {
            if (ob_get_level()) ob_flush();
            flush();
        };

        $sseEvent = function(string $event, array $data) use ($sseFlush) {
            echo "event: {$event}\n";
            echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
            $sseFlush();
        };

        $sseToken = function(string $token) use ($sseFlush) {
            echo 'data: ' . json_encode(['token' => $token], JSON_UNESCAPED_UNICODE) . "\n\n";
            $sseFlush();
        };

        $sseDone = function() use ($sseFlush) {
            echo "event: done\ndata: {}\n\n";
            $sseFlush();
        };

        $sseError = function(string $msg) use ($sseEvent, $sseDone) {
            $sseEvent('error', ['error' => $msg]);
            $sseDone();
        };

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $sseError('Method khong hop le');
            exit;
        }
        if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
            $sseError('Chua dang nhap');
            exit;
        }

        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!$body || empty($body['class_id'])) {
            $sseError('Thieu class_id');
            exit;
        }

        $class_id    = (int)$body['class_id'];
        $bmi         = floatval($body['bmi']         ?? 22);
        $bmi_cat     = trim($body['bmi_cat']         ?? 'Binh thuong');
        $goal        = trim($body['goal']            ?? 'Tang co');
        $burn_target = max(100, intval($body['burn_target'] ?? 350));
        $gender      = trim($body['gender']          ?? 'Nam');
        $age         = intval($body['age']           ?? 25);

        $cls = $this->scheduleModel->getClassInfoForAI($class_id);
        if (!$cls) {
            $sseError('Khong tim thay lop hoc');
            exit;
        }

        $room_name  = $cls['room_name'];
        $equip      = $cls['equipment_list'] ?: 'thiet bi co ban';
        $start_ts   = strtotime($cls['start_time']);
        $end_ts     = strtotime($cls['end_time']);
        $duration   = ($end_ts > $start_ts) ? (int)(($end_ts - $start_ts) / 60) : 60;
        $duration   = max(30, min(180, $duration));

        // Meta event
        $sseEvent('meta', ['room' => $room_name, 'equipment' => $equip, 'duration' => $duration]);

        // ═══════════════════════════════════════════════════════════════
        // BUOC 1: TINH KCAL
        // ═══════════════════════════════════════════════════════════════
        $t_warmup   = 7;
        $t_cooldown = 6;
        $t_main     = max(15, $duration - $t_warmup - $t_cooldown);

        $phase_pct = [
            'Giam mo'          => ['w' => 0.12, 'c' => 0.05],
            'Tang co'          => ['w' => 0.09, 'c' => 0.04],
            'Tang suc ben'     => ['w' => 0.13, 'c' => 0.05],
            'Duy tri the hinh' => ['w' => 0.10, 'c' => 0.04],
            'Giảm mỡ'          => ['w' => 0.12, 'c' => 0.05],
            'Tăng cơ'          => ['w' => 0.09, 'c' => 0.04],
            'Tăng sức bền'     => ['w' => 0.13, 'c' => 0.05],
            'Duy trì thể hình' => ['w' => 0.10, 'c' => 0.04],
        ];
        $pp = $phase_pct[$goal] ?? ['w' => 0.10, 'c' => 0.04];

        $r5 = fn(float $v): int => (int)(round($v / 5) * 5);

        $kcal_warmup   = max(25, $r5($burn_target * $pp['w']));
        $kcal_cooldown = max(10, $r5($burn_target * $pp['c']));
        $kcal_main     = max(50, $burn_target - $kcal_warmup - $kcal_cooldown);

        $kw = [$r5($kcal_warmup * 0.40), $r5($kcal_warmup * 0.35)];
        $kw[] = max(5, $kcal_warmup - $kw[0] - $kw[1]);

        $main_ratio = [
            'Giảm mỡ'          => [0.25, 0.23, 0.21, 0.19, 0.12],
            'Tăng cơ'          => [0.28, 0.25, 0.20, 0.17, 0.10],
            'Tăng sức bền'     => [0.22, 0.22, 0.20, 0.20, 0.16],
            'Duy trì thể hình' => [0.25, 0.23, 0.20, 0.18, 0.14],
        ];
        $mr  = $main_ratio[$goal] ?? [0.25, 0.23, 0.20, 0.18, 0.14];
        $km  = [];
        $ksum = 0;
        foreach ($mr as $i => $r) {
            if ($i < 4) {
                $v = $r5($kcal_main * $r); $km[] = $v; $ksum += $v;
            } else {
                $km[] = max(10, $kcal_main - $ksum);
            }
        }

        $kc = [$r5($kcal_cooldown * 0.40), $r5($kcal_cooldown * 0.35)];
        $kc[] = max(5, $kcal_cooldown - $kc[0] - $kc[1]);

        $kcal_total    = $kcal_warmup + array_sum($km) + $kcal_cooldown;
        $pct_achieved  = ($burn_target > 0) ? (int)round($kcal_total / $burn_target * 100) : 100;

        $goal_config = [
            'Giảm mỡ'          => ['sets' => 3,  'reps' => 15, 'rest' => 45, 'note' => 'Nhịp nhanh, ít nghỉ — tối đa hóa đốt mỡ'],
            'Tăng cơ'          => ['sets' => 4,  'reps' => 10, 'rest' => 90, 'note' => 'Tạ nặng, nghỉ đủ — kích thích phát triển cơ tối đa'],
            'Tăng sức bền'     => ['sets' => 3,  'reps' => 20, 'rest' => 30, 'note' => 'Tạ nhẹ, reps cao — xây dựng sức bền tim mạch'],
            'Duy trì thể hình' => ['sets' => 3,  'reps' => 12, 'rest' => 60, 'note' => 'Cân bằng sức mạnh và sức bền toàn thân'],
        ];
        $gc = $goal_config[$goal] ?? $goal_config['Tăng cơ'];
        $s  = $gc['sets'];
        $rp = $gc['reps'];
        $rs = $gc['rest'];
        $cd_secs = [45, 40, 35];

        $nutrition = [
            'Giảm mỡ'          => 'Ăn đủ 1.6g protein/kg, hạn chế carb tinh chế, uống ≥2.5L nước/ngày.',
            'Tăng cơ'          => 'Bổ sung 1.8–2g protein/kg ngay sau tập, ưu tiên carb phức hợp.',
            'Tăng sức bền'     => 'Nạp carb phức hợp trước tập 1-2h, bổ sung điện giải sau cardio.',
            'Duy trì thể hình' => 'Cân bằng macro 40% carb – 30% protein – 30% chất béo lành mạnh.',
        ];
        $advice = $nutrition[$goal] ?? 'Uống đủ nước và nghỉ ngơi hợp lý.';

        // ═══════════════════════════════════════════════════════════════
        // BUOC 2: POOL BAI TAP THEO GOAL
        // ═══════════════════════════════════════════════════════════════
        $pools = [
            'Giảm mỡ' => [
                'warmup' => [
                    ['name'=>'Chạy bộ nhẹ khởi động',         'equip'=>'Commercial Treadmill',        'keys'=>['treadmill']],
                    ['name'=>'Đạp xe nhẹ khởi động',           'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
                    ['name'=>'Leo máy thang nhẹ khởi động',    'equip'=>'Stair Climber',               'keys'=>['stair climber']],
                    ['name'=>'Chạy elip nhẹ khởi động',        'equip'=>'Elliptical Trainer',          'keys'=>['elliptical']],
                    ['name'=>'Nhảy jumping jack tại chỗ',      'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Bước tại chỗ nâng cao gối',      'equip'=>'Thảm tập',                    'keys'=>['*']],
                ],
                'main' => [
                    ['name'=>'Chạy HIIT tốc độ cao',           'equip'=>'Commercial Treadmill',        'keys'=>['treadmill']],
                    ['name'=>'Đạp xe HIIT cường độ cao',       'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
                    ['name'=>'Leo thang liên tục cường độ cao','equip'=>'Stair Climber',               'keys'=>['stair climber']],
                    ['name'=>'Chạy elip kháng lực cao',        'equip'=>'Elliptical Trainer',          'keys'=>['elliptical']],
                    ['name'=>'Kéo cáp cao xuống (Lat Pulldown)','equip'=>'Lat Pulldown Machine',       'keys'=>['lat pulldown']],
                    ['name'=>'Kéo cáp ngực chéo (Cable Fly)',  'equip'=>'Cable Crossover',             'keys'=>['cable crossover']],
                    ['name'=>'Đẩy ngực máy nhịp nhanh',        'equip'=>'Chest Press Machine',         'keys'=>['chest press machine']],
                    ['name'=>'Đẩy ngực nghiêng máy',           'equip'=>'Incline Chest Press Machine', 'keys'=>['incline chest press']],
                    ['name'=>'Kéo lưng ngồi nhịp nhanh',       'equip'=>'Seated Row Machine',          'keys'=>['seated row']],
                    ['name'=>'Squat tạ đôi nhịp nhanh',        'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
                    ['name'=>'Deadlift tạ đôi nhịp nhanh',     'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
                    ['name'=>'Lunge tạ tay di chuyển',         'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
                    ['name'=>'Squat tạ đòn Olympic nhịp nhanh','equip'=>'Olympic Barbell + Squat Rack','keys'=>['squat rack']],
                    ['name'=>'Deadlift tạ đòn nhịp nhanh',     'equip'=>'Olympic Barbell',             'keys'=>['olympic barbell']],
                    ['name'=>'Ép đùi Leg Press nhịp nhanh',    'equip'=>'Leg Press Machine',           'keys'=>['leg press']],
                    ['name'=>'Duỗi chân máy (Leg Extension)',  'equip'=>'Leg Extension Machine',       'keys'=>['leg extension']],
                    ['name'=>'Cuộn đùi sau (Leg Curl)',        'equip'=>'Leg Curl Machine',            'keys'=>['leg curl']],
                    ['name'=>'Fly ngực máy (Pec Deck)',        'equip'=>'Pec Deck Fly Machine',        'keys'=>['pec deck']],
                    ['name'=>'Smith Squat nhịp nhanh',         'equip'=>'Smith Machine',               'keys'=>['smith machine']],
                    ['name'=>'Đẩy vai máy nhịp nhanh',         'equip'=>'Shoulder Press Machine',      'keys'=>['shoulder press']],
                    ['name'=>'Burpee tại chỗ liên tục',        'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Mountain Climber tại chỗ',       'equip'=>'Thảm tập',                    'keys'=>['*']],
                ],
                'cooldown' => [
                    ['name'=>'Kéo giãn cơ đùi trước đứng',    'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Kéo giãn cơ bắp chân nghiêng',  'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Kéo giãn cơ lưng dưới ngồi',    'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Kéo giãn cơ hông ngồi xổm',     'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Kéo giãn cơ ngực mở rộng',      'equip'=>'Thảm tập',                    'keys'=>['*']],
                ],
            ],
            'Tăng cơ' => [
                'warmup' => [
                    ['name'=>'Đẩy ngực tạ nhẹ khởi động',     'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
                    ['name'=>'Kéo cáp nhẹ khởi động',         'equip'=>'Lat Pulldown Machine',        'keys'=>['lat pulldown']],
                    ['name'=>'Chạy bộ nhẹ khởi động',         'equip'=>'Commercial Treadmill',        'keys'=>['treadmill']],
                    ['name'=>'Đạp xe nhẹ khởi động',          'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
                    ['name'=>'Xoay khớp vai và cổ tay',       'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Hip hinge bodyweight khởi động', 'equip'=>'Thảm tập',                    'keys'=>['*']],
                ],
                'main' => [
                    ['name'=>'Đẩy ngực tạ đòn (Bench Press)', 'equip'=>'Adjustable Bench + Barbell',  'keys'=>['adjustable bench']],
                    ['name'=>'Kéo cáp xuống rộng tay',        'equip'=>'Lat Pulldown Machine',        'keys'=>['lat pulldown']],
                    ['name'=>'Squat tạ đòn Olympic',           'equip'=>'Olympic Barbell + Squat Rack','keys'=>['squat rack']],
                    ['name'=>'Deadlift tạ đòn nặng',           'equip'=>'Olympic Barbell',             'keys'=>['olympic barbell']],
                    ['name'=>'Đẩy vai tạ đòn (Overhead Press)','equip'=>'Olympic Barbell',            'keys'=>['olympic barbell']],
                    ['name'=>'Kéo lưng ngồi nặng (Seated Row)','equip'=>'Seated Row Machine',         'keys'=>['seated row']],
                    ['name'=>'Đẩy ngực nghiêng tạ đôi',       'equip'=>'Adjustable Bench + Dumbbell', 'keys'=>['adjustable bench']],
                    ['name'=>'Fly ngực tạ đôi nằm',           'equip'=>'Adjustable Bench + Dumbbell', 'keys'=>['adjustable bench']],
                    ['name'=>'Kéo cáp ngực (Cable Fly)',       'equip'=>'Cable Crossover',             'keys'=>['cable crossover']],
                    ['name'=>'Đẩy ngực máy tạ nặng',          'equip'=>'Chest Press Machine',         'keys'=>['chest press machine']],
                    ['name'=>'Fly ngực máy (Pec Deck Fly)',   'equip'=>'Pec Deck Fly Machine',        'keys'=>['pec deck']],
                    ['name'=>'Smith Squat tạ nặng',            'equip'=>'Smith Machine',               'keys'=>['smith machine']],
                    ['name'=>'Ép đùi Leg Press tạ nặng',      'equip'=>'Leg Press Machine',           'keys'=>['leg press']],
                    ['name'=>'Duỗi chân máy (Leg Extension)', 'equip'=>'Leg Extension Machine',       'keys'=>['leg extension']],
                    ['name'=>'Cuộn đùi sau (Leg Curl)',        'equip'=>'Leg Curl Machine',            'keys'=>['leg curl']],
                    ['name'=>'Đẩy vai máy tạ nặng',           'equip'=>'Shoulder Press Machine',      'keys'=>['shoulder press']],
                    ['name'=>'Hít đất nâng cao (Diamond Push-up)','equip'=>'Thảm tập',               'keys'=>['*']],
                ],
                'cooldown' => [
                    ['name'=>'Kéo giãn cơ ngực trên ghế',    'equip'=>'Adjustable Bench',            'keys'=>['adjustable bench']],
                    ['name'=>'Kéo giãn cơ lưng xà máy',      'equip'=>'Lat Pulldown Machine',        'keys'=>['lat pulldown']],
                    ['name'=>'Kéo giãn cơ đùi sau đứng',     'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Kéo giãn cơ vai chéo ngực',    'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Kéo giãn cơ tay sau (Tricep)', 'equip'=>'Thảm tập',                    'keys'=>['*']],
                ],
            ],
            'Tăng sức bền' => [
                'warmup' => [
                    ['name'=>'Chạy bộ dần tăng tốc độ',      'equip'=>'Commercial Treadmill',        'keys'=>['treadmill']],
                    ['name'=>'Đạp xe tăng dần cường độ',      'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
                    ['name'=>'Leo thang nhẹ khởi động',       'equip'=>'Stair Climber',               'keys'=>['stair climber']],
                    ['name'=>'Chạy elip tốc độ trung bình',   'equip'=>'Elliptical Trainer',          'keys'=>['elliptical']],
                    ['name'=>'Nhảy jumping jack 2 phút',      'equip'=>'Thảm tập',                    'keys'=>['*']],
                ],
                'main' => [
                    ['name'=>'Chạy bộ Zone 2 liên tục','equip'=>'Commercial Treadmill','keys'=>['treadmill']],
                    ['name'=>'Đạp xe Zone 2 liên tục',        'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
                    ['name'=>'Leo thang bền bỉ liên tục',     'equip'=>'Stair Climber',               'keys'=>['stair climber']],
                    ['name'=>'Chạy elip Zone 2 dài',          'equip'=>'Elliptical Trainer',          'keys'=>['elliptical']],
                    ['name'=>'Kéo cáp rep cao (Lat Pulldown)','equip'=>'Lat Pulldown Machine',        'keys'=>['lat pulldown']],
                    ['name'=>'Kéo lưng rep cao (Seated Row)', 'equip'=>'Seated Row Machine',          'keys'=>['seated row']],
                    ['name'=>'Đẩy ngực máy rep cao',          'equip'=>'Chest Press Machine',         'keys'=>['chest press machine']],
                    ['name'=>'Kéo cáp đứng rep cao',          'equip'=>'Cable Crossover',             'keys'=>['cable crossover']],
                    ['name'=>'Squat tạ nhẹ liên tục',         'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
                    ['name'=>'Lunge tạ tay bước dài',         'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
                    ['name'=>'Duỗi chân rep cao (Leg Ext)',   'equip'=>'Leg Extension Machine',       'keys'=>['leg extension']],
                    ['name'=>'Cuộn đùi rep cao (Leg Curl)',   'equip'=>'Leg Curl Machine',            'keys'=>['leg curl']],
                    ['name'=>'Squat nhảy (Jump Squat)',       'equip'=>'Thảm tập',                    'keys'=>['*']],
                ],
                'cooldown' => [
                    ['name'=>'Đi bộ chậm hạ nhịp tim',       'equip'=>'Commercial Treadmill',        'keys'=>['treadmill']],
                    ['name'=>'Đạp xe chậm hạ nhịp tim',       'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
                    ['name'=>'Kéo giãn cơ bắp chân tường',   'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Kéo giãn cơ đùi trước ngồi',   'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Hít thở sâu nằm phục hồi',     'equip'=>'Thảm tập',                    'keys'=>['*']],
                ],
            ],
            'Duy trì thể hình' => [
                'warmup' => [
                    ['name'=>'Chạy bộ nhẹ khởi động',        'equip'=>'Commercial Treadmill',        'keys'=>['treadmill']],
                    ['name'=>'Đạp xe nhẹ khởi động',          'equip'=>'Exercise Bike',               'keys'=>['exercise bike']],
                    ['name'=>'Chạy elip nhẹ khởi động',       'equip'=>'Elliptical Trainer',          'keys'=>['elliptical']],
                    ['name'=>'Xoay khớp toàn thân',           'equip'=>'Thảm tập',                    'keys'=>['*']],
                ],
                'main' => [
                    ['name'=>'Đẩy ngực tạ đòn vừa (Bench Press)','equip'=>'Adjustable Bench + Barbell','keys'=>['adjustable bench']],
                    ['name'=>'Kéo cáp xuống (Lat Pulldown)',  'equip'=>'Lat Pulldown Machine',        'keys'=>['lat pulldown']],
                    ['name'=>'Squat tạ đòn Olympic',           'equip'=>'Olympic Barbell + Squat Rack','keys'=>['squat rack']],
                    ['name'=>'Kéo lưng ngồi (Seated Row)',    'equip'=>'Seated Row Machine',          'keys'=>['seated row']],
                    ['name'=>'Đẩy ngực máy vừa',              'equip'=>'Chest Press Machine',         'keys'=>['chest press machine']],
                    ['name'=>'Kéo cáp ngực (Cable Fly)',      'equip'=>'Cable Crossover',             'keys'=>['cable crossover']],
                    ['name'=>'Đẩy vai tạ đôi vừa',            'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
                    ['name'=>'Curl tay tạ đôi (Bicep Curl)',  'equip'=>'Dumbbell Set',                'keys'=>['dumbbell']],
                    ['name'=>'Hít đất thường',                'equip'=>'Thảm tập',                    'keys'=>['*']],
                ],
                'cooldown' => [
                    ['name'=>'Kéo giãn cơ ngực + vai',        'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Kéo giãn cơ đùi trước đứng',   'equip'=>'Thảm tập',                    'keys'=>['*']],
                    ['name'=>'Kéo giãn lưng dưới (Cat-Cow)', 'equip'=>'Thảm tập',                    'keys'=>['*']],
                ],
            ],
        ];

        // ═══════════════════════════════════════════════════════════════
        // BUOC 3: LOC BAI THEO THIET BI + PICK
        // ═══════════════════════════════════════════════════════════════
        $equip_lower = strtolower($equip);
        $equipMatch = function(array $ex, string $equip_lower): bool {
            foreach ($ex['keys'] as $key) {
                if ($key === '*') return true;
                if (str_contains($equip_lower, strtolower($key))) return true;
            }
            return false;
        };

        $pickExercises = function(array $pool, string $equip_lower, int $n) use ($equipMatch): array {
            $matched = array_values(array_filter($pool, fn($e) => $equipMatch($e, $equip_lower)));
            $real = array_values(array_filter($matched, fn($e) => !in_array('*', $e['keys']) || count($e['keys']) > 1));
            $body = array_values(array_filter($matched, fn($e) => in_array('*', $e['keys']) && count($e['keys']) === 1));
            shuffle($real);
            shuffle($body);
            return array_slice(array_merge($real, $body), 0, $n);
        };

        $goal_pool = $pools[$goal] ?? $pools['Tăng cơ'];

        $warmup_exs   = $pickExercises($goal_pool['warmup'],   $equip_lower, 3);
        $main_exs     = $pickExercises($goal_pool['main'],     $equip_lower, 5);
        $cooldown_exs = $pickExercises($goal_pool['cooldown'], $equip_lower, 3);

        $fb_w = ['name'=>'Xoay khớp tại chỗ',    'equip'=>'Thảm tập'];
        $fb_m = ['name'=>'Hít đất tại chỗ',      'equip'=>'Thảm tập'];
        $fb_c = ['name'=>'Kéo giãn toàn thân',   'equip'=>'Thảm tập'];
        while (count($warmup_exs)   < 3) $warmup_exs[]   = $fb_w;
        while (count($main_exs)     < 5) $main_exs[]     = $fb_m;
        while (count($cooldown_exs) < 3) $cooldown_exs[] = $fb_c;

        // ═══════════════════════════════════════════════════════════════
        // BUOC 4: BUILD PROMPT VAN BAN DAY DU
        // ═══════════════════════════════════════════════════════════════
        $w_mins = [3, 2, 2];
        $warmup_block = '';
        for ($i = 0; $i < 3; $i++) {
            $e = $warmup_exs[$i];
            $warmup_block .= "- {$e['name']} [{$e['equip']}] • {$w_mins[$i]} phút • ~{$kw[$i]} kcal\n";
        }

        $main_block = '';
        for ($i = 0; $i < 5; $i++) {
            $e = $main_exs[$i];
            $main_block .= "- {$e['name']} [{$e['equip']}] • {$s}sets × {$rp}reps • nghỉ {$rs}s • ~{$km[$i]} kcal\n";
        }

        $cooldown_block = '';
        for ($i = 0; $i < 3; $i++) {
            $e = $cooldown_exs[$i];
            $cooldown_block .= "- {$e['name']} [{$e['equip']}] • {$cd_secs[$i]} giây mỗi bên • ~{$kc[$i]} kcal\n";
        }

        $prompt = "Output CHÍNH XÁC đoạn văn bản dưới đây. KHÔNG thay đổi bất kỳ con số nào.

### 🔥 KHỞI ĐỘNG ({$t_warmup} phút) — tổng {$kcal_warmup} kcal
{$warmup_block}
### 💪 BÀI TẬP CHÍNH ({$t_main} phút) — tổng {$kcal_main} kcal
*{$gc['note']}*
{$main_block}
### 🧘 GIÃN CƠ ({$t_cooldown} phút) — tổng {$kcal_cooldown} kcal
{$cooldown_block}
### 📊 TỔNG KẾT
Tổng kcal: ~{$kcal_total} kcal | Đạt {$pct_achieved}% mục tiêu đốt calo | Lời khuyên: {$advice}";

        // ── Groq API ─────────────────────────────────────────────────
        $groq_api_key = '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';
        $groq_url     = 'https://api.groq.com/openai/v1/chat/completions';
        $groq_model   = 'llama-3.1-8b-instant';

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $ssl_verify = !(str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'));

        $payload = json_encode([
            'model'       => $groq_model,
            'stream'      => true,
            'temperature' => 0.0,
            'max_tokens'  => 1200,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'Bạn là máy in văn bản. Nhiệm vụ DUY NHẤT: output LẠI CHÍNH XÁC nội dung người dùng cung cấp, KHÔNG thay đổi bất kỳ con số nào (kcal, sets, reps, giây, phút, %), KHÔNG thêm/bớt/sửa bất kỳ từ nào, KHÔNG giải thích, KHÔNG thêm lời mở đầu hay kết thúc. Chỉ in lại nguyên văn.',
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $lineBuffer = '';
        $doneSent   = false;

        $ch = curl_init($groq_url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $groq_api_key,
            ],
            CURLOPT_SSL_VERIFYPEER => $ssl_verify,
            CURLOPT_SSL_VERIFYHOST => $ssl_verify ? 2 : 0,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use (&$lineBuffer, &$doneSent, $sseToken, $sseDone, $sseError) {
                $lineBuffer .= $chunk;
                while (($pos = strpos($lineBuffer, "\n")) !== false) {
                    $line       = trim(substr($lineBuffer, 0, $pos));
                    $lineBuffer = substr($lineBuffer, $pos + 1);
                    if (!$line) continue;
                    if ($line === 'data: [DONE]') {
                        $sseDone();
                        $doneSent = true;
                        continue;
                    }
                    if (strpos($line, 'data: ') !== 0) continue;
                    $obj = json_decode(substr($line, 6), true);
                    if (!is_array($obj)) continue;
                    $token = $obj['choices'][0]['delta']['content'] ?? '';
                    if ($token !== '') {
                        $sseToken($token);
                    }
                }
                return strlen($chunk);
            },
        ]);

        curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$doneSent) {
            if ($curlErrno) {
                $sseError("Groq không phản hồi (cURL #{$curlErrno}): {$curlError}");
            } elseif ($httpCode === 401) {
                $sseError("Groq API key không hợp lệ.");
            } elseif ($httpCode === 429) {
                $sseError("Groq rate limit. Thử lại sau vài giây.");
            } elseif ($httpCode && $httpCode !== 200) {
                $sseError("Groq lỗi HTTP {$httpCode}.");
            } else {
                $sseDone();
            }
        }
    }

    // Helper kiểm tra giờ bắt đầu/kết thúc so với mở/đóng cửa của GymRoom
    private function checkRoomTimeRange(int $room_id, string $start_time, ?string $end_time): true|string
    {
        $roomRow = $this->scheduleModel->getRoomInfo($room_id);
        if ($roomRow) {
            $tStart = date('H:i:s', strtotime($start_time));
            $tEnd   = $end_time ? date('H:i:s', strtotime($end_time)) : null;
            $tOpen  = $roomRow['open_time'];
            $tClose = $roomRow['close_time'];
            $rName  = $roomRow['room_name'];
            if ($tOpen  && $tStart < $tOpen) {
                return "Giờ bắt đầu sớm hơn giờ mở cửa phòng $rName (" . substr($tOpen, 0, 5) . ")";
            }
            if ($tClose && $tStart > $tClose) {
                return "Giờ bắt đầu muộn hơn giờ đóng cửa phòng $rName (" . substr($tClose, 0, 5) . ")";
            }
            if ($tEnd && $tClose && $tEnd > $tClose) {
                return "Giờ kết thúc vượt quá giờ đóng cửa phòng $rName (" . substr($tClose, 0, 5) . ")";
            }
        }
        return true;
    }
}
