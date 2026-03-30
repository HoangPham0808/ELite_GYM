<?php
/**
 * management_statistics_function.php
 * Elite Gym — Statistics Backend
 * Xử lý: lấy dữ liệu KPI, báo cáo và xuất file (CSV / JSON / PDF / Excel)
 */

session_start();
// if (!isset($_SESSION['account_id'])) { http_response_code(401); exit('Unauthorized'); }

require_once '../../../Database/db.php';

/* ══════════════════════════════════════════
   RESOLVE CONNECTION
   db.php có thể export $pdo (PDO) hoặc $conn (mysqli)
   → chuẩn hóa về $pdo để dùng thống nhất
══════════════════════════════════════════ */
$db = null;
if      (isset($pdo)  && $pdo  instanceof PDO)    $db = $pdo;
elseif  (isset($conn) && $conn instanceof PDO)    $db = $conn;
elseif  (isset($conn) && $conn instanceof mysqli) $db = $conn;

if (!$db) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection không khả dụng. Kiểm tra db.php']);
    exit;
}

/* ══════════════════════════════════════════
   UNIVERSAL DB HELPER
   Hỗ trợ cả PDO lẫn mysqli
══════════════════════════════════════════ */
function dbExec($db, string $sql, array $params = []): array
{
    if ($db instanceof PDO) {
        $st = $db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($db instanceof mysqli) {
        if (empty($params)) {
            $res = $db->query($sql);
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }
        $types = str_repeat('s', count($params));
        $st = $db->prepare($sql);
        $st->bind_param($types, ...$params);
        $st->execute();
        return $st->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

function dbVal($db, string $sql, array $params = [])
{
    $rows = dbExec($db, $sql, $params);
    return $rows ? reset($rows[0]) : null;
}

/* ══════════════════════════════════════════
   ROUTER
══════════════════════════════════════════ */
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'kpi':        echo json_encode(getKPI($db));              break;
    case 'revenue':    echo json_encode(getRevenue($db));          break;
    case 'plans':      echo json_encode(getPlanDistribution($db)); break;
    case 'recent':     echo json_encode(getRecentInvoices($db));   break;
    case 'export':     handleExport($db);                          break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
exit;

/* ══════════════════════════════════════════
   KPI — 5 chỉ số tổng quan
══════════════════════════════════════════ */
function getKPI($pdo): array
{
    $month = date('m');
    $year  = date('Y');

    $rows = dbExec($pdo, "SELECT COALESCE(SUM(final_amount),0) AS total FROM Invoice WHERE status='Paid' AND MONTH(invoice_date)=? AND YEAR(invoice_date)=?", [$month, $year]);
    $revenue = (float)($rows[0]['total'] ?? 0);

    $rows = dbExec($pdo, "SELECT COUNT(*) AS cnt FROM Customer WHERE MONTH(registered_at)=? AND YEAR(registered_at)=?", [$month, $year]);
    $newMembers = (int)($rows[0]['cnt'] ?? 0);

    $rows = dbExec($pdo, "SELECT COUNT(DISTINCT customer_id) AS cnt FROM MembershipRegistration WHERE status='active' AND end_date>=CURDATE()");
    $activeMembers = (int)($rows[0]['cnt'] ?? 0);

    $rows = dbExec($pdo, "SELECT COUNT(*) AS cnt FROM GymCheckIn WHERE DATE(check_time)=CURDATE() AND type='checkin'");
    $checkinToday = (int)($rows[0]['cnt'] ?? 0);

    $rows = dbExec($pdo, "SELECT COUNT(*) AS cnt FROM EquipmentMaintenance WHERE status='In Progress'");
    $equipMaint = (int)($rows[0]['cnt'] ?? 0);

    return compact('revenue', 'newMembers', 'activeMembers', 'checkinToday', 'equipMaint');
}

/* ══════════════════════════════════════════
   DOANH THU THEO THÁNG (12 tháng gần nhất)
══════════════════════════════════════════ */
function getRevenue($pdo): array
{
    $year = $_GET['year'] ?? date('Y');
    $rawRows = dbExec($pdo, "SELECT MONTH(invoice_date) AS m, COALESCE(SUM(final_amount),0) AS total FROM Invoice WHERE status='Paid' AND YEAR(invoice_date)=? GROUP BY MONTH(invoice_date) ORDER BY m", [$year]);
    $map = [];
    foreach ($rawRows as $r) $map[(int)$r['m']] = (float)$r['total'];
    $result = [];
    for ($i = 1; $i <= 12; $i++) $result[] = $map[$i] ?? 0;
    return $result;
}

/* ══════════════════════════════════════════
   PHÂN BỔ GÓI TẬP
══════════════════════════════════════════ */
function getPlanDistribution($pdo): array
{
    return dbExec($pdo, "SELECT pt.type_name, COUNT(mr.registration_id) AS cnt FROM MembershipRegistration mr JOIN MembershipPlan mp ON mr.plan_id=mp.plan_id JOIN PackageType pt ON mp.package_type_id=pt.type_id WHERE mr.status='active' GROUP BY pt.type_name ORDER BY cnt DESC");
}

/* ══════════════════════════════════════════
   GIAO DỊCH GẦN ĐÂY
══════════════════════════════════════════ */
function getRecentInvoices($pdo): array
{
    $limit = (int)($_GET['limit'] ?? 10);
    return dbExec($pdo, "SELECT i.invoice_id, c.full_name, mp.plan_name, i.invoice_date, i.final_amount, i.status FROM Invoice i JOIN Customer c ON i.customer_id=c.customer_id JOIN InvoiceDetail id ON i.invoice_id=id.invoice_id JOIN MembershipPlan mp ON id.plan_id=mp.plan_id ORDER BY i.invoice_date DESC, i.invoice_id DESC LIMIT ?", [$limit]);
}

/* ══════════════════════════════════════════
   EXPORT HANDLER
══════════════════════════════════════════ */
function handleExport($pdo): void
{
    $report = $_GET['report'] ?? 'all';
    $format = $_GET['format'] ?? 'csv';
    $from   = $_GET['from']   ?? date('Y-m-01');
    $to     = $_GET['to']     ?? date('Y-m-d');
    $group  = $_GET['group']  ?? 'month';

    // Validate dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

    $data     = fetchReportData($pdo, $report, $from, $to, $group);
    $filename = "elite_gym_{$report}_{$from}_{$to}";

    switch ($format) {
        case 'csv':   exportCSV($data, $filename);   break;
        case 'json':  exportJSON($data, $filename);  break;
        case 'excel': exportExcel($data, $filename); break;
        case 'pdf':   exportPDF($data, $filename, $report, $from, $to); break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unsupported format']);
    }
}

/* ── Fetch data theo loại báo cáo ── */
function fetchReportData($pdo, string $report, string $from, string $to, string $group): array
{
    switch ($report) {

        // ─ Doanh thu theo tháng ─
        case 'revenue-monthly':
            $groupSql = buildGroupSQL($group);
            return dbExec($pdo, "SELECT {$groupSql} AS period,
                       COUNT(i.invoice_id)       AS total_invoices,
                       SUM(i.original_amount)    AS original_amount,
                       SUM(i.discount_amount)    AS discount_amount,
                       SUM(i.final_amount)        AS revenue
                FROM Invoice i
                WHERE i.status = 'Paid'
                  AND i.invoice_date BETWEEN ? AND ?
                GROUP BY period ORDER BY period", [$from, $to]);

        // ─ Doanh thu theo gói ─
        case 'revenue-plan':
            return dbExec($pdo, "SELECT mp.plan_name, pt.type_name AS package_type,
                       COUNT(id.detail_id)    AS qty,
                       SUM(id.subtotal)        AS revenue
                FROM InvoiceDetail id
                JOIN Invoice i        ON id.invoice_id = i.invoice_id
                JOIN MembershipPlan mp ON id.plan_id   = mp.plan_id
                LEFT JOIN PackageType pt ON mp.package_type_id = pt.type_id
                WHERE i.status = 'Paid'
                  AND i.invoice_date BETWEEN ? AND ?
                GROUP BY mp.plan_id ORDER BY revenue DESC", [$from, $to]);

        // ─ Hiệu quả khuyến mãi ─
        case 'revenue-promo':
            return dbExec($pdo, "SELECT p.promotion_name, p.discount_percent,
                       COUNT(i.invoice_id)     AS usage_count,
                       SUM(i.discount_amount)  AS total_discount,
                       SUM(i.final_amount)      AS revenue_after_discount
                FROM Invoice i
                JOIN Promotion p ON i.promotion_id = p.promotion_id
                WHERE i.status = 'Paid'
                  AND i.invoice_date BETWEEN ? AND ?
                GROUP BY p.promotion_id ORDER BY total_discount DESC", [$from, $to]);

        // ─ Hội viên mới ─
        case 'member-new':
            return dbExec($pdo, "SELECT c.customer_id, c.full_name, c.phone, c.email,
                       c.gender, c.registered_at,
                       mp.plan_name, mr.start_date, mr.end_date
                FROM Customer c
                JOIN MembershipRegistration mr ON c.customer_id = mr.customer_id
                JOIN MembershipPlan mp          ON mr.plan_id = mp.plan_id
                WHERE c.registered_at BETWEEN ? AND ?
                ORDER BY c.registered_at DESC", [$from, $to]);

        // ─ Hội viên đang hoạt động ─
        case 'member-active':
            return dbExec($pdo, "SELECT c.customer_id, c.full_name, c.phone, c.email,
                       mp.plan_name, mr.start_date, mr.end_date,
                       DATEDIFF(mr.end_date, CURDATE()) AS days_remaining
                FROM MembershipRegistration mr
                JOIN Customer c       ON mr.customer_id = c.customer_id
                JOIN MembershipPlan mp ON mr.plan_id = mp.plan_id
                WHERE mr.status = 'active'
                  AND mr.end_date >= CURDATE()
                ORDER BY mr.end_date ASC");

        // ─ Hội viên hết hạn ─
        case 'member-expire':
            return dbExec($pdo, "SELECT c.customer_id, c.full_name, c.phone, c.email, mp.plan_name, mr.start_date, mr.end_date, DATEDIFF(CURDATE(), mr.end_date) AS days_expired FROM MembershipRegistration mr JOIN Customer c ON mr.customer_id=c.customer_id JOIN MembershipPlan mp ON mr.plan_id=mp.plan_id WHERE mr.status='inactive' AND mr.end_date BETWEEN ? AND ? ORDER BY mr.end_date DESC", [$from, $to]);

        // ─ Chuyên cần nhân viên ─
        case 'employee-attend':
            return dbExec($pdo, "SELECT e.employee_id, e.full_name, e.position,
                       SUM(a.status = 'Present')  AS present,
                       SUM(a.status = 'Late')     AS late,
                       SUM(a.status = 'Absent')   AS absent,
                       SUM(a.status = 'On Leave') AS on_leave,
                       COUNT(a.attendance_id)      AS total_days
                FROM Attendance a
                JOIN Employee e ON a.employee_id = e.employee_id
                WHERE a.work_date BETWEEN ? AND ?
                GROUP BY e.employee_id ORDER BY e.full_name", [$from, $to]);

        // ─ Bảng lương ─
        case 'employee-payroll':
            $m = (int)date('m', strtotime($from));
            $y = (int)date('Y', strtotime($from));
            return dbExec($pdo, "SELECT e.full_name, e.position,
                       p.month, p.year,
                       p.base_salary, p.allowance, p.bonus,
                       p.deduction, p.net_salary
                FROM Payroll p
                JOIN Employee e ON p.employee_id = e.employee_id
                WHERE p.month = ? AND p.year = ?
                ORDER BY e.full_name", [$m, $y]);

        // ─ Tình trạng thiết bị ─
        case 'equipment-status':
            return dbExec($pdo, "SELECT eq.equipment_id, eq.equipment_name, eq.condition_status, gr.room_name, et.type_name, eq.purchase_date, eq.purchase_price, eq.last_maintenance_date, DATEDIFF(CURDATE(), eq.last_maintenance_date) AS days_since_maint FROM Equipment eq LEFT JOIN GymRoom gr ON eq.room_id=gr.room_id LEFT JOIN EquipmentType et ON eq.type_id=et.type_id ORDER BY days_since_maint DESC");

        // ─ Lịch sử bảo trì ─
        case 'equipment-maint':
            return dbExec($pdo, "SELECT em.maintenance_id, eq.equipment_name, et.type_name,
                       em.maintenance_date, em.description,
                       em.cost, em.performed_by, em.status
                FROM EquipmentMaintenance em
                JOIN Equipment eq      ON em.equipment_id = eq.equipment_id
                LEFT JOIN EquipmentType et ON eq.type_id  = et.type_id
                WHERE em.maintenance_date BETWEEN ? AND ?
                ORDER BY em.maintenance_date DESC", [$from, $to]);

        // ─ Check-in theo ngày ─
        case 'checkin-daily':
            $groupSql = buildGroupSQL($group, 'check_time');
            return dbExec($pdo, "SELECT {$groupSql} AS period,
                       COUNT(*) AS total_checkins,
                       COUNT(DISTINCT customer_id) AS unique_members
                FROM GymCheckIn
                WHERE type = 'checkin'
                  AND DATE(check_time) BETWEEN ? AND ?
                GROUP BY period ORDER BY period", [$from, $to]);

        // ─ Tham gia lớp học ─
        case 'checkin-class':
            return dbExec($pdo, "SELECT tc.class_name, e.full_name AS trainer,
                       tc.start_time, tc.end_time,
                       gr.room_name,
                       COUNT(cr.class_registration_id) AS registered
                FROM TrainingClass tc
                LEFT JOIN Employee e          ON tc.trainer_id = e.employee_id
                LEFT JOIN GymRoom gr          ON tc.room_id    = gr.room_id
                LEFT JOIN ClassRegistration cr ON tc.class_id = cr.class_id
                WHERE DATE(tc.start_time) BETWEEN ? AND ?
                GROUP BY tc.class_id ORDER BY tc.start_time DESC", [$from, $to]);

        // ─ Tất cả (giao dịch gần đây) ─
        default:
            return dbExec($pdo, "SELECT i.invoice_id, c.full_name, mp.plan_name,
                       i.invoice_date, i.original_amount,
                       i.discount_amount, i.final_amount, i.status, i.created_by
                FROM Invoice i
                JOIN Customer c        ON i.customer_id = c.customer_id
                JOIN InvoiceDetail id  ON i.invoice_id  = id.invoice_id
                JOIN MembershipPlan mp ON id.plan_id    = mp.plan_id
                WHERE i.invoice_date BETWEEN ? AND ?
                ORDER BY i.invoice_date DESC", [$from, $to]);
    }
}

/* ── Build GROUP BY SQL theo period ── */
function buildGroupSQL(string $group, string $col = 'invoice_date'): string
{
    return match($group) {
        'day'   => "DATE({$col})",
        'week'  => "YEARWEEK({$col}, 1)",
        'year'  => "YEAR({$col})",
        default => "DATE_FORMAT({$col}, '%Y-%m')",   // month
    };
}

/* ══════════════════════════════════════════
   EXPORT: CSV
══════════════════════════════════════════ */
function exportCSV(array $data, string $filename): void
{
    if (empty($data)) { echo 'Không có dữ liệu'; return; }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, no-store');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
    fputcsv($out, array_keys($data[0]));               // header row
    foreach ($data as $row) fputcsv($out, $row);
    fclose($out);
}

/* ══════════════════════════════════════════
   EXPORT: JSON
══════════════════════════════════════════ */
function exportJSON(array $data, string $filename): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    header('Cache-Control: no-cache, no-store');
    echo json_encode([
        'exported_at' => date('Y-m-d H:i:s'),
        'total'       => count($data),
        'data'        => $data,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/* ══════════════════════════════════════════
   EXPORT: EXCEL — SpreadsheetML XML (pure PHP, không Composer)
   Format chuẩn Microsoft SpreadsheetML — Excel mở không cảnh báo,
   LibreOffice / Google Sheets cũng hỗ trợ.
══════════════════════════════════════════ */
function exportExcel(array $data, string $filename): void
{
    if (empty($data)) {
        http_response_code(204);
        echo json_encode(['error' => 'Không có dữ liệu trong khoảng thời gian đã chọn']);
        return;
    }

    $cols     = array_keys($data[0]);
    $exported = date('d/m/Y H:i:s');

    /* ── Helper: escape XML ── */
    $x = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_XML1, 'UTF-8');

    /* ── Build Worksheet rows ── */
    $rows = '';

    // Row 1: tiêu đề
    $rows .= '<Row ss:Height="22">'
           . '<Cell ss:MergeAcross="' . (count($cols) - 1) . '" ss:StyleID="sTitle">'
           . '<Data ss:Type="String">Elite Gym Management — Báo cáo xuất lúc ' . $exported . '</Data>'
           . '</Cell></Row>' . "\n";

    // Row 2: header cột
    $rows .= '<Row ss:Height="18">';
    foreach ($cols as $col) {
        $rows .= '<Cell ss:StyleID="sHeader"><Data ss:Type="String">' . $x($col) . '</Data></Cell>';
    }
    $rows .= '</Row>' . "\n";

    // Data rows
    foreach ($data as $i => $row) {
        $style = ($i % 2 === 0) ? 'sRow' : 'sAlt';
        $rows .= '<Row>';
        foreach ($row as $val) {
            if ($val === null || $val === '') {
                $rows .= '<Cell ss:StyleID="' . $style . '"><Data ss:Type="String"></Data></Cell>';
            } elseif (is_numeric($val)) {
                $rows .= '<Cell ss:StyleID="sNum"><Data ss:Type="Number">' . $x($val) . '</Data></Cell>';
            } else {
                $rows .= '<Cell ss:StyleID="' . $style . '"><Data ss:Type="String">' . $x($val) . '</Data></Cell>';
            }
        }
        $rows .= '</Row>' . "\n";
    }

    /* ── Column widths: auto estimate 90pt default ── */
    $colDefs = '';
    foreach ($cols as $col) {
        $w = max(80, min(200, strlen($col) * 9 + 20));
        $colDefs .= '<Column ss:AutoFitWidth="1" ss:Width="' . $w . '"/>';
    }

    /* ── Full SpreadsheetML document ── */
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
         . '<?mso-application progid="Excel.Sheet"?>' . "\n"
         . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
         . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
         . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
         . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
         . ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n"

         /* Office settings */
         . '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">'
         . '<Title>Elite Gym — Báo cáo</Title>'
         . '<Author>Elite Gym Management System</Author>'
         . '<Created>' . date('Y-m-d') . 'T00:00:00Z</Created>'
         . '</DocumentProperties>' . "\n"

         /* Styles */
         . '<Styles>' . "\n"

         /* sTitle */
         . '<Style ss:ID="sTitle">'
         . '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="0"/>'
         . '<Font ss:FontName="Segoe UI" ss:Size="12" ss:Bold="1" ss:Color="#D4A017"/>'
         . '<Interior ss:Color="#1A1A2E" ss:Pattern="Solid"/>'
         . '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#D4A017"/></Borders>'
         . '</Style>' . "\n"

         /* sHeader */
         . '<Style ss:ID="sHeader">'
         . '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>'
         . '<Font ss:FontName="Segoe UI" ss:Size="10" ss:Bold="1" ss:Color="#000000"/>'
         . '<Interior ss:Color="#D4A017" ss:Pattern="Solid"/>'
         . '<Borders>'
         . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B8860B"/>'
         . '<Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B8860B"/>'
         . '</Borders>'
         . '</Style>' . "\n"

         /* sRow (even) */
         . '<Style ss:ID="sRow">'
         . '<Alignment ss:Vertical="Center"/>'
         . '<Font ss:FontName="Segoe UI" ss:Size="10"/>'
         . '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>'
         . '<Borders>'
         . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>'
         . '<Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>'
         . '</Borders>'
         . '</Style>' . "\n"

         /* sAlt (odd) */
         . '<Style ss:ID="sAlt">'
         . '<Alignment ss:Vertical="Center"/>'
         . '<Font ss:FontName="Segoe UI" ss:Size="10"/>'
         . '<Interior ss:Color="#FFF8EC" ss:Pattern="Solid"/>'
         . '<Borders>'
         . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>'
         . '<Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>'
         . '</Borders>'
         . '</Style>' . "\n"

         /* sNum (numbers, right-align) */
         . '<Style ss:ID="sNum">'
         . '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>'
         . '<Font ss:FontName="Segoe UI" ss:Size="10"/>'
         . '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>'
         . '<NumberFormat ss:Format="#,##0.##"/>'
         . '<Borders>'
         . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>'
         . '<Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>'
         . '</Borders>'
         . '</Style>' . "\n"

         . '</Styles>' . "\n"

         /* Worksheet */
         . '<Worksheet ss:Name="Báo cáo">'
         . '<Table>' . $colDefs . "\n" . $rows . '</Table>'
         . '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">'
         . '<FreezePanes/><FrozenNoSplit/><SplitHorizontal>2</SplitHorizontal>'
         . '<TopRowBottomPane>2</TopRowBottomPane>'
         . '</WorksheetOptions>'
         . '</Worksheet>' . "\n"
         . '</Workbook>';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: no-cache, no-store');
    header('Pragma: no-cache');
    echo $xml;
}

/* ══════════════════════════════════════════
   EXPORT: PDF — pure PHP (không cần Composer)
   Sinh trang HTML có print-CSS đẹp, tự gọi window.print()
   Người dùng chọn "Save as PDF" trong hộp thoại in của trình duyệt
══════════════════════════════════════════ */
function exportPDF(array $data, string $filename, string $report, string $from, string $to): void
{
    if (empty($data)) {
        http_response_code(204);
        echo '<script>alert("Không có dữ liệu trong khoảng thời gian đã chọn.");</script>';
        return;
    }

    // Map report id → tiêu đề tiếng Việt
    $titles = [
        'revenue-monthly'  => 'Doanh Thu Theo Tháng',
        'revenue-plan'     => 'Doanh Thu Theo Gói',
        'revenue-promo'    => 'Hiệu Quả Khuyến Mãi',
        'member-new'       => 'Hội Viên Mới Đăng Ký',
        'member-active'    => 'Hội Viên Đang Hoạt Động',
        'member-expire'    => 'Hội Viên Hết Hạn / Rời Bỏ',
        'employee-attend'  => 'Chuyên Cần Nhân Viên',
        'employee-payroll' => 'Bảng Lương Nhân Viên',
        'equipment-status' => 'Tình Trạng Thiết Bị',
        'equipment-maint'  => 'Lịch Sử Bảo Trì Thiết Bị',
        'checkin-daily'    => 'Check-in Theo Ngày',
        'checkin-class'    => 'Tham Gia Lớp Học',
    ];
    $reportTitle = $titles[$report] ?? 'Tổng Hợp Báo Cáo';

    $cols      = array_keys($data[0]);
    $colCount  = count($cols);
    $rowCount  = count($data);
    $fromFmt   = date('d/m/Y', strtotime($from));
    $toFmt     = date('d/m/Y', strtotime($to));
    $exportAt  = date('d/m/Y H:i:s');

    // Build table rows HTML
    $tbody = '';
    foreach ($data as $i => $row) {
        $rowClass = ($i % 2 === 1) ? ' class="alt"' : '';
        $tbody .= "<tr{$rowClass}>";
        foreach ($row as $val) {
            $display = ($val === null) ? '<span class="null">—</span>' : htmlspecialchars((string)$val);
            $numClass = (is_numeric($val) && $val !== '') ? ' class="num"' : '';
            $tbody .= "<td{$numClass}>{$display}</td>";
        }
        $tbody .= '</tr>';
    }

    $theadCells = implode('', array_map(fn($c) => '<th>' . htmlspecialchars($c) . '</th>', $cols));

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, no-store');

    echo <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Elite Gym — {$reportTitle}</title>
<style>
  /* ── Screen ── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
    font-size: 11pt;
    color: #111;
    background: #f4f4f4;
    padding: 20px;
  }

  .page {
    max-width: 1100px;
    margin: 0 auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 24px rgba(0,0,0,.12);
    overflow: hidden;
  }

  /* Header */
  .pdf-header {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
    padding: 28px 32px 20px;
    color: #fff;
    display: flex;
    align-items: flex-start;
    gap: 20px;
  }
  .pdf-header .logo {
    width: 56px; height: 56px;
    background: #d4a017;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; flex-shrink: 0;
  }
  .pdf-header .info h1 {
    font-size: 22pt; font-weight: 700;
    color: #d4a017; line-height: 1.2;
  }
  .pdf-header .info h2 {
    font-size: 13pt; font-weight: 400;
    color: #e0e0e0; margin-top: 4px;
  }
  .pdf-header .meta {
    margin-left: auto; text-align: right; font-size: 9pt; color: #aaa;
    line-height: 1.8;
  }
  .pdf-header .meta strong { color: #d4a017; }

  /* Stats bar */
  .stats-bar {
    background: #f9f9f9;
    border-bottom: 2px solid #d4a017;
    padding: 10px 32px;
    display: flex; gap: 32px;
    font-size: 9.5pt; color: #555;
  }
  .stats-bar span strong { color: #d4a017; font-size: 12pt; }

  /* Table */
  .table-wrap {
    padding: 20px 28px 28px;
    overflow-x: auto;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9.5pt;
  }

  thead th {
    background: #d4a017;
    color: #000;
    font-weight: 700;
    padding: 8px 10px;
    text-align: center;
    border: 1px solid #b8860b;
    white-space: nowrap;
    font-size: 9pt;
  }

  tbody td {
    padding: 6px 10px;
    border: 1px solid #e0e0e0;
    vertical-align: middle;
  }
  tbody tr:hover td { background: #fffbf0; }
  tbody tr.alt td   { background: #f8f8f8; }
  td.num            { text-align: right; font-variant-numeric: tabular-nums; }
  span.null         { color: #bbb; }

  /* Footer */
  .pdf-footer {
    background: #f0f0f0;
    border-top: 1px solid #ddd;
    padding: 10px 32px;
    font-size: 8.5pt;
    color: #888;
    display: flex; justify-content: space-between;
    align-items: center;
  }
  .pdf-footer .watermark { color: #d4a017; font-weight: 600; }

  /* Print button (ẩn khi in) */
  .print-bar {
    background: #1a1a2e;
    padding: 14px 32px;
    display: flex; gap: 10px; justify-content: flex-end;
    align-items: center;
  }
  .btn-print {
    background: #d4a017; color: #000;
    border: none; border-radius: 6px;
    padding: 9px 22px; font-size: 11pt; font-weight: 700;
    cursor: pointer;
    display: flex; align-items: center; gap: 8px;
  }
  .btn-print:hover { background: #f0b800; }
  .btn-close {
    background: transparent; color: #aaa;
    border: 1px solid #555; border-radius: 6px;
    padding: 9px 16px; font-size: 10pt;
    cursor: pointer;
  }
  .btn-close:hover { color: #fff; border-color: #999; }
  .print-hint { color: #888; font-size: 9pt; margin-right: auto; }

  /* ── Print media ── */
  @media print {
    body { background: #fff; padding: 0; }
    .page { box-shadow: none; border-radius: 0; max-width: 100%; }
    .print-bar { display: none !important; }

    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    tr    { page-break-inside: avoid; }

    @page {
      size: A4 landscape;
      margin: 12mm 10mm 16mm;
    }
  }
</style>
</head>
<body>

<div class="print-bar">
  <span class="print-hint">💡 Trong hộp thoại in, chọn <strong style="color:#d4a017">Save as PDF</strong> hoặc máy in để lưu file</span>
  <button class="btn-close" onclick="window.close()">✕ Đóng</button>
  <button class="btn-print" onclick="window.print()">🖨️ In / Lưu PDF</button>
</div>

<div class="page">

  <div class="pdf-header">
    <div class="logo">🏋️</div>
    <div class="info">
      <h1>Elite Gym</h1>
      <h2>Báo cáo: {$reportTitle}</h2>
    </div>
    <div class="meta">
      <div>Từ ngày: <strong>{$fromFmt}</strong></div>
      <div>Đến ngày: <strong>{$toFmt}</strong></div>
      <div>Xuất lúc: {$exportAt}</div>
      <div>Tổng dòng: <strong>{$rowCount}</strong></div>
    </div>
  </div>

  <div class="stats-bar">
    <span>📊 Báo cáo: <strong>{$reportTitle}</strong></span>
    <span>📅 Kỳ: <strong>{$fromFmt} → {$toFmt}</strong></span>
    <span>📋 Số bản ghi: <strong>{$rowCount}</strong></span>
    <span>🗂️ Cột: <strong>{$colCount}</strong></span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>{$theadCells}</tr>
      </thead>
      <tbody>
        {$tbody}
      </tbody>
    </table>
  </div>

  <div class="pdf-footer">
    <span class="watermark">🏋️ Elite Gym Management System</span>
    <span>Tài liệu nội bộ — Xuất: {$exportAt}</span>
  </div>

</div>

<script>
  // Tự động mở hộp thoại in sau khi trang load xong
  window.addEventListener('load', function() {
    setTimeout(function() { window.print(); }, 600);
  });
</script>

</body>
</html>
HTML;
}
