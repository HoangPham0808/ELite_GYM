<?php

class ReviewController extends Controller
{
    private Review $reviewModel;
    private Customer $customerModel;

    public function __construct()
    {
        $this->reviewModel = new Review();
        $this->customerModel = new Customer();
    }

    // API Reviews dành cho Khách hàng
    public function api(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'submit_review':
                $this->ensureSession();
                AuthMiddleware::requireRole('Customer');

                $aid = (int)($_SESSION['account_id'] ?? 0);
                if (!$aid) {
                    echo json_encode(['ok' => false, 'msg' => 'Bạn chưa đăng nhập.']);
                    exit;
                }

                $content = trim($_POST['content'] ?? '');
                $rating  = (int)($_POST['rating'] ?? 0);

                if ($rating < 1 || $rating > 5) {
                    echo json_encode(['ok' => false, 'msg' => 'Vui lòng chọn số sao (1–5).']);
                    exit;
                }
                if (mb_strlen($content) < 10) {
                    echo json_encode(['ok' => false, 'msg' => 'Nội dung tối thiểu 10 ký tự.']);
                    exit;
                }
                if (mb_strlen($content) > 500) {
                    echo json_encode(['ok' => false, 'msg' => 'Nội dung tối đa 500 ký tự.']);
                    exit;
                }

                $cid = $this->customerModel->findIdByAccount($aid);
                if (!$cid) {
                    echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy thông tin khách hàng.']);
                    exit;
                }

                $res = $this->reviewModel->submitReview($cid, $content, $rating);
                echo json_encode([
                    'ok'   => $res['success'],
                    'msg'  => $res['message'],
                    'data' => $res['success'] ? ['review_id' => $res['review_id']] : null
                ]);
                break;

            case 'get_reviews':
                $page  = max(1, (int)($_GET['page'] ?? 1));
                $limit = 6;
                $res = $this->reviewModel->getReviewsList($page, $limit);
                echo json_encode([
                    'ok'   => true,
                    'data' => $res
                ]);
                break;

            default:
                echo json_encode(['ok' => false, 'msg' => 'Hành động không hợp lệ.']);
        }
    }

    // API Reviews dành cho Admin & Employee quản lý
    public function apiAdmin(): void
    {
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        $this->ensureSession();
        header('Content-Type: application/json; charset=utf-8');

        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'get_reviews':
                $page    = max(1, (int)($_GET['page'] ?? 1));
                $limit   = 10;
                $rating  = (int)($_GET['rating'] ?? 0);
                $replied = $_GET['replied'] ?? 'all';
                $search  = trim($_GET['search'] ?? '');

                $res = $this->reviewModel->getReviewsAdmin($page, $limit, $rating, $replied, $search);
                echo json_encode([
                    'ok'   => true,
                    'data' => $res
                ]);
                break;

            case 'reply':
                $rid   = (int)($_POST['review_id'] ?? 0);
                $reply = trim($_POST['reply'] ?? '');
                $by    = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';

                if ($rid <= 0) {
                    echo json_encode(['ok' => false, 'msg' => 'Review ID không hợp lệ.']);
                    exit;
                }
                if (mb_strlen($reply) < 5) {
                    echo json_encode(['ok' => false, 'msg' => 'Phản hồi quá ngắn.']);
                    exit;
                }

                $ok = $this->reviewModel->replyReview($rid, $reply, $by);
                echo json_encode([
                    'ok'  => $ok,
                    'msg' => $ok ? 'Đã lưu phản hồi và thông báo cho khách hàng.' : 'Lỗi cập nhật DB.'
                ]);
                break;

            case 'delete_reply':
                $rid = (int)($_POST['review_id'] ?? 0);
                if ($rid <= 0) {
                    echo json_encode(['ok' => false, 'msg' => 'Review ID không hợp lệ.']);
                    exit;
                }
                $ok = $this->reviewModel->deleteReply($rid);
                echo json_encode([
                    'ok'  => $ok,
                    'msg' => $ok ? 'Đã xóa phản hồi.' : 'Lỗi cập nhật DB.'
                ]);
                break;

            case 'delete_review':
                $rid = (int)($_POST['review_id'] ?? 0);
                if ($rid <= 0) {
                    echo json_encode(['ok' => false, 'msg' => 'Review ID không hợp lệ.']);
                    exit;
                }
                $ok = $this->reviewModel->deleteReview($rid);
                echo json_encode([
                    'ok'  => $ok,
                    'msg' => $ok ? 'Đã xóa đánh giá.' : 'Lỗi cập nhật DB.'
                ]);
                break;

            default:
                echo json_encode(['ok' => false, 'msg' => 'Hành động không hợp lệ.']);
        }
    }

    // API Quản lý thông báo cho Khách hàng
    public function notification(): void
    {
        $this->ensureSession();
        header('Content-Type: application/json; charset=utf-8');

        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        $role   = $_SESSION['role'] ?? '';
        $aid    = (int)($_SESSION['account_id'] ?? 0);

        switch ($action) {
            case 'get_notifications':
                if ($role !== 'Customer' || !$aid) {
                    echo json_encode(['ok' => false, 'msg' => 'Chưa đăng nhập.']);
                    exit;
                }
                $cid = $this->customerModel->findIdByAccount($aid);
                if (!$cid) {
                    echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy khách hàng.']);
                    exit;
                }

                $limit  = min(20, (int)($_GET['limit']  ?? 15));
                $offset = max(0,  (int)($_GET['offset'] ?? 0));

                $res = $this->reviewModel->getNotifications($cid, $limit, $offset);
                echo json_encode([
                    'ok'   => true,
                    'data' => $res
                ]);
                break;

            case 'get_unread_count':
                if ($role !== 'Customer' || !$aid) {
                    echo json_encode(['ok' => true, 'data' => ['count' => 0]]);
                    exit;
                }
                $cid = $this->customerModel->findIdByAccount($aid);
                if (!$cid) {
                    echo json_encode(['ok' => true, 'data' => ['count' => 0]]);
                    exit;
                }
                $count = $this->reviewModel->getUnreadCount($cid);
                echo json_encode([
                    'ok'   => true,
                    'data' => ['count' => $count]
                ]);
                break;

            case 'mark_read':
                if ($role !== 'Customer' || !$aid) {
                    echo json_encode(['ok' => false, 'msg' => 'Chưa đăng nhập.']);
                    exit;
                }
                $nid = (int)($_POST['notif_id'] ?? 0);
                $cid = $this->customerModel->findIdByAccount($aid);
                if (!$nid || !$cid) {
                    echo json_encode(['ok' => false, 'msg' => 'Dữ liệu không hợp lệ.']);
                    exit;
                }
                $ok = $this->reviewModel->markRead($nid, $cid);
                echo json_encode(['ok' => $ok]);
                break;

            case 'mark_all_read':
                if ($role !== 'Customer' || !$aid) {
                    echo json_encode(['ok' => false, 'msg' => 'Chưa đăng nhập.']);
                    exit;
                }
                $cid = $this->customerModel->findIdByAccount($aid);
                if (!$cid) {
                    echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy khách hàng.']);
                    exit;
                }
                $ok = $this->reviewModel->markAllRead($cid);
                echo json_encode(['ok' => $ok]);
                break;

            case 'push_reply_notif':
                AuthMiddleware::requireRole(['Admin', 'Employee']);
                $review_id  = (int)($_POST['review_id']  ?? 0);
                $reply_text = trim($_POST['reply_text']  ?? '');
                $staff_name = trim($_POST['staff_name']  ?? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Nhân viên Elite Gym'));

                if (!$review_id) {
                    echo json_encode(['ok' => false, 'msg' => 'review_id không hợp lệ.']);
                    exit;
                }

                $ok = $this->reviewModel->pushReplyNotif($review_id, $reply_text, $staff_name);
                echo json_encode([
                    'ok'  => $ok,
                    'msg' => $ok ? 'Thông báo đã gửi tới khách hàng.' : 'Không thể gửi thông báo.'
                ]);
                break;

            default:
                echo json_encode(['ok' => false, 'msg' => 'Hành động không hợp lệ.']);
        }
    }

    // Tự động kiểm tra chấm công, ca vắng, ca chưa đặt của Customer
    public function notificationAuto(): void
    {
        $this->ensureSession();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
            echo json_encode(['ok' => false, 'reason' => 'unauthorized']);
            exit;
        }

        $aid = (int)$_SESSION['account_id'];
        $cid = (int)($_GET['customer_id'] ?? 0);
        if ($cid <= 0) {
            echo json_encode(['ok' => false, 'reason' => 'invalid_cid']);
            exit;
        }

        // Đảm bảo account_id trong session khớp với customer_id
        $verified_cid = $this->customerModel->findIdByAccount($aid);
        if ($verified_cid !== $cid) {
            echo json_encode(['ok' => false, 'reason' => 'forbidden']);
            exit;
        }

        // Kiểm tra gói tập
        $hasActivePlan = $this->reviewModel->checkActivePlan($cid);
        if (!$hasActivePlan) {
            echo json_encode(['ok' => true, 'skipped' => 'no_active_plan']);
            exit;
        }

        $results = [];

        // 1. Chưa đăng ký ca hôm nay (sau 9h sáng)
        $now = new DateTime();
        if ((int)$now->format('H') >= 9) {
            $results['no_reg'] = $this->reviewModel->checkTodayRegistrationAndPush($cid);
        }

        // 2. Vắng ca tập trong vòng 7 ngày qua
        $results['absent_pushed'] = $this->reviewModel->checkAbsentAndPush($cid);

        echo json_encode([
            'ok'      => true,
            'results' => $results
        ]);
    }
}
