<?php

class StatisticsController extends Controller
{
    private Statistics $statsModel;

    public function __construct()
    {
        $this->statsModel = new Statistics();
    }

    public function api(): void
    {
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        ensureSession(); // helper from session_helper.php

        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'kpi':
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($this->statsModel->getKPI());
                break;

            case 'revenue':
                header('Content-Type: application/json; charset=utf-8');
                $year = $_GET['year'] ?? date('Y');
                echo json_encode($this->statsModel->getRevenue($year));
                break;

            case 'plans':
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($this->statsModel->getPlanDistribution());
                break;

            case 'recent':
                header('Content-Type: application/json; charset=utf-8');
                $limit = (int)($_GET['limit'] ?? 10);
                echo json_encode($this->statsModel->getRecentInvoices($limit));
                break;

            case 'view':
                $this->handleView();
                break;

            case 'export':
                $this->handleExport();
                break;

            default:
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['error' => 'Unknown action']);
        }
    }

    public function apiOverview(): void
    {
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        ensureSession(); // helper from session_helper.php
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'kpi'     => $this->statsModel->getKPI()
        ]);
    }

    private function handleView(): void
    {
        $report = $_GET['report'] ?? 'all';
        $from   = $_GET['from']   ?? date('Y-m-01');
        $to     = $_GET['to']     ?? date('Y-m-d');
        $group  = $_GET['group']  ?? 'month';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

        $data = $this->statsModel->fetchReportData($report, $from, $to, $group);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        echo json_encode([
            'report' => $report,
            'from'   => $from,
            'to'     => $to,
            'count'  => count($data),
            'data'   => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    private function handleExport(): void
    {
        $report = $_GET['report'] ?? 'all';
        $format = $_GET['format'] ?? 'csv';
        $from   = $_GET['from']   ?? date('Y-m-01');
        $to     = $_GET['to']     ?? date('Y-m-d');
        $group  = $_GET['group']  ?? 'month';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

        $data     = $this->statsModel->fetchReportData($report, $from, $to, $group);
        $filename = "elite_gym_{$report}_{$from}_{$to}";

        switch ($format) {
            case 'csv':
                $this->exportCSV($data, $filename);
                break;
            case 'json':
                $this->exportJSON($data, $filename);
                break;
            case 'excel':
                $this->exportExcel($data, $filename);
                break;
            case 'pdf':
                $this->exportPDF($data, $filename, $report, $from, $to);
                break;
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Unsupported format']);
        }
    }

    private function exportCSV(array $data, string $filename): void
    {
        if (empty($data)) {
            echo 'Không có dữ liệu';
            return;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: no-cache, no-store');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        fputcsv($out, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
    }

    private function exportJSON(array $data, string $filename): void
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

    private function exportExcel(array $data, string $filename): void
    {
        if (empty($data)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Không có dữ liệu trong khoảng thời gian đã chọn']);
            return;
        }

        $cols     = array_keys($data[0]);
        $exported = date('d/m/Y H:i:s');
        $x = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_XML1, 'UTF-8');

        $rows = '';
        $rows .= '<Row ss:Height="22">'
               . '<Cell ss:MergeAcross="' . (count($cols) - 1) . '" ss:StyleID="sTitle">'
               . '<Data ss:Type="String">Elite Gym Management — Báo cáo xuất lúc ' . $exported . '</Data>'
               . '</Cell></Row>' . "\n";

        $rows .= '<Row ss:Height="18">';
        foreach ($cols as $col) {
            $rows .= '<Cell ss:StyleID="sHeader"><Data ss:Type="String">' . $x($col) . '</Data></Cell>';
        }
        $rows .= '</Row>' . "\n";

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

        $colDefs = '';
        foreach ($cols as $col) {
            $w = max(80, min(200, strlen($col) * 9 + 20));
            $colDefs .= '<Column ss:AutoFitWidth="1" ss:Width="' . $w . '"/>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<?mso-application progid="Excel.Sheet"?>' . "\n"
             . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
             . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
             . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
             . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
             . ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n"
             . '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">'
             . '<Title>Elite Gym — Báo cáo</Title>'
             . '<Author>Elite Gym Management System</Author>'
             . '<Created>' . date('Y-m-d') . 'T00:00:00Z</Created>'
             . '</DocumentProperties>' . "\n"
             . '<Styles>' . "\n"
             . '<Style ss:ID="sTitle">'
             . '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="0"/>'
             . '<Font ss:FontName="Segoe UI" ss:Size="12" ss:Bold="1" ss:Color="#D4A017"/>'
             . '<Interior ss:Color="#1A1A2E" ss:Pattern="Solid"/>'
             . '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#D4A017"/></Borders>'
             . '</Style>' . "\n"
             . '<Style ss:ID="sHeader">'
             . '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>'
             . '<Font ss:FontName="Segoe UI" ss:Size="10" ss:Bold="1" ss:Color="#000000"/>'
             . '<Interior ss:Color="#D4A017" ss:Pattern="Solid"/>'
             . '<Borders>'
             . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B8860B"/>'
             . '<Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B8860B"/>'
             . '</Borders>'
             . '</Style>' . "\n"
             . '<Style ss:ID="sRow">'
             . '<Alignment ss:Vertical="Center"/>'
             . '<Font ss:FontName="Segoe UI" ss:Size="10"/>'
             . '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>'
             . '<Borders>'
             . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>'
             . '<Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>'
             . '</Borders>'
             . '</Style>' . "\n"
             . '<Style ss:ID="sAlt">'
             . '<Alignment ss:Vertical="Center"/>'
             . '<Font ss:FontName="Segoe UI" ss:Size="10"/>'
             . '<Interior ss:Color="#FFF8EC" ss:Pattern="Solid"/>'
             . '<Borders>'
             . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>'
             . '<Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>'
             . '</Borders>'
             . '</Style>' . "\n"
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

    private function exportPDF(array $data, string $filename, string $report, string $from, string $to): void
    {
        if (empty($data)) {
            $fromFmt = date('d/m/Y', strtotime($from));
            $toFmt   = date('d/m/Y', strtotime($to));
            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">
            <title>Không có dữ liệu — Elite Gym</title>
            <style>
              body{font-family:'Segoe UI',sans-serif;background:#f4f4f4;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
              .box{background:#fff;border-radius:12px;padding:48px 56px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.12);max-width:440px}
              .icon{font-size:52px;margin-bottom:16px}
              h2{font-size:20px;color:#1a1a2e;margin-bottom:10px}
              p{color:#666;font-size:14px;line-height:1.6;margin-bottom:24px}
              .period{background:#fffbf0;border:1px solid #d4a017;border-radius:8px;padding:10px 16px;font-size:13px;color:#92400e;margin-bottom:24px}
              .btn{background:#d4a017;color:#000;border:none;border-radius:8px;padding:11px 28px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}
              .btn:hover{background:#f0b800}
            </style></head><body>
            <div class="box">
              <div class="icon">📋</div>
              <h2>Không có dữ liệu để xuất</h2>
              <div class="period">📅 Kỳ: <strong><?= $fromFmt ?></strong> → <strong><?= $toFmt ?></strong></div>
              <p>Không tìm thấy bản ghi nào cho báo cáo <strong><?= htmlspecialchars($report) ?></strong> trong khoảng thời gian đã chọn.<br>Hãy thử chọn khoảng thời gian khác.</p>
              <button class="btn" onclick="window.close()">✕ Đóng tab này</button>
            </div>
            </body></html>
            <?php
            return;
        }

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
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
        <meta charset="UTF-8">
        <title>Elite Gym — <?= $reportTitle ?></title>
        <style>
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
          .stats-bar {
            background: #f9f9f9;
            border-bottom: 2px solid #d4a017;
            padding: 10px 32px;
            display: flex; gap: 32px;
            font-size: 9.5pt; color: #555;
          }
          .stats-bar span strong { color: #d4a017; font-size: 12pt; }
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
              <h2>Báo cáo: <?= $reportTitle ?></h2>
            </div>
            <div class="meta">
              <div>Từ ngày: <strong><?= $fromFmt ?></strong></div>
              <div>Đến ngày: <strong><?= $toFmt ?></strong></div>
              <div>Xuất lúc: <?= $exportAt ?></div>
              <div>Tổng dòng: <strong><?= $rowCount ?></strong></div>
            </div>
          </div>

          <div class="stats-bar">
            <span>📊 Báo cáo: <strong><?= $reportTitle ?></strong></span>
            <span>📅 Kỳ: <strong><?= $fromFmt ?> → <?= $toFmt ?></strong></span>
            <span>📋 Số bản ghi: <strong><?= $rowCount ?></strong></span>
            <span>🗂️ Cột: <strong><?= $colCount ?></strong></span>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr><?= $theadCells ?></tr>
              </thead>
              <tbody>
                <?= $tbody ?>
              </tbody>
            </table>
          </div>

          <div class="pdf-footer">
            <span>Hệ thống quản lý Elite Gym</span>
            <span class="watermark">ELITE FITNESS GYM</span>
            <span>Trang 1 / 1</span>
          </div>
        </div>

        </body>
        </html>
        <?php
    }
}
