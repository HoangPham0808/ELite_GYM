<?php
/**
 * management_statistics.php  — Elite Gym
 * Thiết kế lại: dữ liệu thực từ DB, layout hiện đại
 */
session_start();
// if (!isset($_SESSION['account_id'])) { header('Location: ../../login.php'); exit; }

require_once '../../../Database/db.php';

// ── Helper: hỗ trợ cả PDO & mysqli ──────────────────────────
function dbQuery($conn, string $sql, array $params = []): array {
    if ($conn instanceof PDO) {
        $st = $conn->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    // mysqli
    if (empty($params)) {
        $res = $conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    $types = str_repeat('s', count($params));
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    return $st->get_result()->fetch_all(MYSQLI_ASSOC);
}
function dbVal($conn, string $sql, array $params = []) {
    $r = dbQuery($conn, $sql, $params);
    return $r[0][array_key_first($r[0])] ?? 0;
}

$conn = $pdo ?? $conn; // dùng PDO nếu có, fallback mysqli

$m = date('m'); $y = date('Y');

// ══ KPI ══════════════════════════════════════════════════════
$rev_month   = (float) dbVal($conn, "SELECT COALESCE(SUM(final_amount),0) FROM Invoice WHERE status='Paid' AND MONTH(invoice_date)=? AND YEAR(invoice_date)=?", [$m, $y]);
$new_members = (int)   dbVal($conn, "SELECT COUNT(*) FROM Customer WHERE MONTH(registered_at)=? AND YEAR(registered_at)=?", [$m, $y]);
$active_mem  = (int)   dbVal($conn, "SELECT COUNT(DISTINCT customer_id) FROM MembershipRegistration WHERE status='active' AND end_date>=CURDATE()");
$checkin_today=(int)   dbVal($conn, "SELECT COUNT(*) FROM GymCheckIn WHERE DATE(check_time)=CURDATE() AND type='checkin'");
$equip_maint = (int)   dbVal($conn, "SELECT COUNT(*) FROM EquipmentMaintenance WHERE status='In Progress'");
$payroll_month=(float) dbVal($conn, "SELECT COALESCE(SUM(net_salary),0) FROM Payroll WHERE month=? AND year=?", [$m, $y]);

// ══ Bar chart: doanh thu 12 tháng năm hiện tại ════════════
$rev_rows = dbQuery($conn, "SELECT MONTH(invoice_date) AS m, COALESCE(SUM(final_amount),0) AS total FROM Invoice WHERE status='Paid' AND YEAR(invoice_date)=? GROUP BY MONTH(invoice_date)", [$y]);
$rev_by_month = array_fill(1, 12, 0);
foreach ($rev_rows as $r) $rev_by_month[(int)$r['m']] = (float)$r['total'];

// ══ Donut: phân bổ gói (active) ════════════════════════════
$plan_dist = dbQuery($conn, "SELECT pt.type_name, pt.color_code, COUNT(mr.registration_id) AS cnt FROM MembershipRegistration mr JOIN MembershipPlan mp ON mr.plan_id=mp.plan_id JOIN PackageType pt ON mp.package_type_id=pt.type_id WHERE mr.status='active' GROUP BY pt.type_id ORDER BY cnt DESC");
$total_dist = array_sum(array_column($plan_dist, 'cnt')) ?: 1;

// ══ Mini sparkline: check-in 7 ngày gần đây ═══════════════
$ci_week = dbQuery($conn, "SELECT DATE(check_time) AS d, COUNT(*) AS n FROM GymCheckIn WHERE type='checkin' AND check_time >= DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(check_time) ORDER BY d ASC");
$ci_map = []; foreach ($ci_week as $r) $ci_map[$r['d']] = (int)$r['n'];
$ci_spark = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $ci_spark[] = $ci_map[$d] ?? 0;
}

// ══ Giao dịch gần đây ══════════════════════════════════════
$recent = dbQuery($conn, "SELECT i.invoice_id, c.full_name, mp.plan_name, i.invoice_date, i.final_amount, i.status FROM Invoice i JOIN Customer c ON i.customer_id=c.customer_id JOIN InvoiceDetail id2 ON i.invoice_id=id2.invoice_id JOIN MembershipPlan mp ON id2.plan_id=mp.plan_id ORDER BY i.invoice_date DESC, i.invoice_id DESC LIMIT 12");

// ══ Sắp hết hạn trong 7 ngày ══════════════════════════════
$expiring = dbQuery($conn, "SELECT c.full_name, c.phone, mp.plan_name, mr.end_date, DATEDIFF(mr.end_date,CURDATE()) AS days_left FROM MembershipRegistration mr JOIN Customer c ON mr.customer_id=c.customer_id JOIN MembershipPlan mp ON mr.plan_id=mp.plan_id WHERE mr.status='active' AND mr.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) ORDER BY mr.end_date ASC LIMIT 8");

// ══ Helpers format ══════════════════════════════════════════
function fmtVND(float $n): string {
    if ($n >= 1_000_000_000) return number_format($n/1_000_000_000,1,'.',',').'B';
    if ($n >= 1_000_000)     return number_format($n/1_000_000,1,'.',',').'M';
    if ($n >= 1_000)         return number_format($n/1_000,0,'.',',').'K';
    return number_format($n,0,'.',',');
}
$rev_max = max($rev_by_month) ?: 1;
$months_vi = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Thống Kê &amp; Báo Cáo — Elite Gym</title>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="management_statistics.css">
</head>
<body>
<div class="wrapper">

  <!-- ══ TOPBAR ════════════════════════════════════════════ -->
  <header class="topbar">
    <div class="topbar-left">
      <div class="brand-icon"><i class="fas fa-dumbbell"></i></div>
      <div>
        <div class="brand-name">Elite Gym</div>
        <div class="brand-sub">Thống Kê &amp; Báo Cáo</div>
      </div>
    </div>
    <div class="topbar-meta">
      <span class="meta-chip"><i class="fas fa-calendar-day"></i> <?= date('d/m/Y') ?></span>
      <span class="meta-chip"><i class="fas fa-clock"></i> <?= date('H:i') ?></span>
    </div>
    <div class="topbar-right">
      <button class="btn btn-ghost" onclick="window.print()"><i class="fas fa-print"></i> In trang</button>
      <button class="btn btn-gold"  onclick="openExportModal('all')"><i class="fas fa-file-export"></i> Xuất báo cáo</button>
    </div>
  </header>

  <!-- ══ KPI GRID ══════════════════════════════════════════ -->
  <div class="kpi-grid">

    <div class="kpi-card kpi-gold">
      <div class="kpi-icon-wrap"><i class="fas fa-coins"></i></div>
      <div class="kpi-body">
        <div class="kpi-val"><?= fmtVND($rev_month) ?> ₫</div>
        <div class="kpi-lbl">Doanh thu tháng <?= (int)$m ?></div>
      </div>
      <i class="fas fa-chart-line kpi-bg-icon"></i>
    </div>

    <div class="kpi-card kpi-blue">
      <div class="kpi-icon-wrap"><i class="fas fa-id-badge"></i></div>
      <div class="kpi-body">
        <div class="kpi-val"><?= number_format($active_mem) ?></div>
        <div class="kpi-lbl">Hội viên đang active</div>
      </div>
      <i class="fas fa-users kpi-bg-icon"></i>
    </div>

    <div class="kpi-card kpi-green">
      <div class="kpi-icon-wrap"><i class="fas fa-user-plus"></i></div>
      <div class="kpi-body">
        <div class="kpi-val"><?= $new_members ?></div>
        <div class="kpi-lbl">Hội viên mới tháng <?= (int)$m ?></div>
      </div>
      <i class="fas fa-user-plus kpi-bg-icon"></i>
    </div>

    <div class="kpi-card kpi-purple">
      <div class="kpi-icon-wrap"><i class="fas fa-sign-in-alt"></i></div>
      <div class="kpi-body">
        <div class="kpi-val"><?= $checkin_today ?></div>
        <div class="kpi-lbl">Check-in hôm nay</div>
        <!-- sparkline -->
        <div class="kpi-spark">
          <?php $spmax = max($ci_spark) ?: 1; foreach ($ci_spark as $sv): ?>
            <div class="spark-bar" style="height:<?= round($sv/$spmax*100) ?>%"></div>
          <?php endforeach; ?>
        </div>
      </div>
      <i class="fas fa-door-open kpi-bg-icon"></i>
    </div>

    <div class="kpi-card kpi-orange">
      <div class="kpi-icon-wrap"><i class="fas fa-tools"></i></div>
      <div class="kpi-body">
        <div class="kpi-val"><?= $equip_maint ?></div>
        <div class="kpi-lbl">Thiết bị đang bảo trì</div>
      </div>
      <i class="fas fa-wrench kpi-bg-icon"></i>
    </div>

    <div class="kpi-card kpi-red">
      <div class="kpi-icon-wrap"><i class="fas fa-wallet"></i></div>
      <div class="kpi-body">
        <div class="kpi-val"><?= fmtVND($payroll_month) ?> ₫</div>
        <div class="kpi-lbl">Lương phải trả tháng <?= (int)$m ?></div>
      </div>
      <i class="fas fa-wallet kpi-bg-icon"></i>
    </div>

  </div>

  <!-- ══ CHARTS ROW ════════════════════════════════════════ -->
  <div class="charts-row">

    <!-- Bar chart: doanh thu -->
    <div class="chart-card">
      <div class="chart-head">
        <span class="chart-title"><i class="fas fa-chart-bar"></i> Doanh thu <?= $y ?></span>
        <span class="chart-subtitle">Đơn vị: VNĐ</span>
      </div>
      <div class="bar-chart-wrap" id="barChartWrap">
        <?php foreach ($months_vi as $idx => $ml):
          $val = $rev_by_month[$idx + 1];
          $h   = $val > 0 ? max(4, round($val / $rev_max * 100)) : 0;
          $cur = ($idx + 1 == (int)$m);
        ?>
        <div class="bar-col <?= $cur ? 'bar-col--current' : '' ?>">
          <div class="bar-tooltip"><?= fmtVND($val) ?> ₫</div>
          <div class="bar-fill" style="height:<?= $h ?>%"></div>
          <div class="bar-lbl"><?= $ml ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Donut: phân bổ gói -->
    <div class="chart-card">
      <div class="chart-head">
        <span class="chart-title"><i class="fas fa-circle-half-stroke"></i> Phân bổ gói tập</span>
        <span class="chart-subtitle"><?= $active_mem ?> hội viên active</span>
      </div>
      <div class="donut-outer">
        <svg class="donut-svg" viewBox="0 0 160 160">
          <?php
            $R = 60; $CX = 80; $CY = 80; $SW = 22;
            $circ = 2 * M_PI * $R;
            $offset = 0;
            $donut_colors = ['#d4a017','#3b82f6','#22c55e','#a855f7','#f97316','#ef4444'];
            foreach ($plan_dist as $pi => $pd):
                $pct = $pd['cnt'] / $total_dist;
                $len = $pct * $circ;
                $col = $pd['color_code'] ?: $donut_colors[$pi % count($donut_colors)];
          ?>
          <circle cx="<?=$CX?>" cy="<?=$CY?>" r="<?=$R?>"
            fill="none" stroke="<?= htmlspecialchars($col) ?>" stroke-width="<?=$SW?>"
            stroke-dasharray="<?= number_format($len,2,'.','') ?> <?= number_format($circ-$len,2,'.','') ?>"
            stroke-dashoffset="<?= number_format(-$offset,2,'.','') ?>"
            transform="rotate(-90 <?=$CX?> <?=$CY?>)"/>
          <?php $offset += $len; endforeach; ?>
          <text x="80" y="75" text-anchor="middle" fill="#e8eaf0"
                font-size="18" font-family="Syne,sans-serif" font-weight="800"><?= $active_mem ?></text>
          <text x="80" y="91" text-anchor="middle" fill="#6b7280" font-size="9">hội viên</text>
        </svg>
        <div class="donut-legend">
          <?php foreach ($plan_dist as $pi => $pd):
            $col = $pd['color_code'] ?: $donut_colors[$pi % count($donut_colors)];
            $pct = round($pd['cnt'] / $total_dist * 100);
          ?>
          <div class="dl-row">
            <span class="dl-dot" style="background:<?= htmlspecialchars($col) ?>"></span>
            <span class="dl-name"><?= htmlspecialchars($pd['type_name']) ?></span>
            <span class="dl-cnt"><?= $pd['cnt'] ?></span>
            <span class="dl-pct"><?= $pct ?>%</span>
          </div>
          <?php endforeach; ?>
          <?php if (empty($plan_dist)): ?>
          <div style="color:var(--muted);font-size:13px">Chưa có dữ liệu</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- ══ ALERT: SẮP HẾT HẠN ════════════════════════════════ -->
  <?php if (!empty($expiring)): ?>
  <div class="alert-row">
    <div class="alert-card">
      <div class="alert-head">
        <i class="fas fa-exclamation-triangle"></i>
        <span><?= count($expiring) ?> hội viên hết hạn trong 7 ngày tới</span>
      </div>
      <div class="alert-list">
        <?php foreach ($expiring as $ex): ?>
        <div class="alert-item">
          <div class="ai-name"><?= htmlspecialchars($ex['full_name']) ?></div>
          <div class="ai-plan"><?= htmlspecialchars($ex['plan_name']) ?></div>
          <div class="ai-days <?= $ex['days_left'] <= 2 ? 'ai-days--urgent' : '' ?>">
            còn <?= $ex['days_left'] ?> ngày
          </div>
          <?php if ($ex['phone']): ?>
          <div class="ai-phone"><i class="fas fa-phone"></i> <?= htmlspecialchars($ex['phone']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══ REPORT CARDS ══════════════════════════════════════ -->
  <div class="section-head">
    <div class="section-title"><span class="st-dot"></span> Xuất báo cáo</div>
  </div>

  <div class="tabs" id="tabsBar">
    <button class="tab-btn active"  onclick="switchTab('all',this)"><i class="fas fa-th-large"></i> Tất cả</button>
    <button class="tab-btn"         onclick="switchTab('revenue',this)"><i class="fas fa-coins"></i> Doanh thu</button>
    <button class="tab-btn"         onclick="switchTab('member',this)"><i class="fas fa-users"></i> Hội viên</button>
    <button class="tab-btn"         onclick="switchTab('employee',this)"><i class="fas fa-id-card"></i> Nhân sự</button>
    <button class="tab-btn"         onclick="switchTab('equipment',this)"><i class="fas fa-tools"></i> Thiết bị</button>
    <button class="tab-btn"         onclick="switchTab('checkin',this)"><i class="fas fa-door-open"></i> Check-in</button>
  </div>

  <div class="report-grid" id="reportGrid"></div>

  <!-- ══ RECENT INVOICES ════════════════════════════════════ -->
  <div class="chart-card" style="margin-bottom:0">
    <div class="chart-head">
      <span class="chart-title"><i class="fas fa-receipt"></i> Giao dịch gần đây</span>
      <button class="btn-sm btn-export" onclick="exportRecentTable()">
        <i class="fas fa-download"></i> CSV
      </button>
    </div>
    <div class="table-wrap">
      <table id="recentTable">
        <thead>
          <tr>
            <th>#</th><th>Khách hàng</th><th>Gói tập</th>
            <th>Ngày</th><th>Tổng tiền</th><th>Trạng thái</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $statusMap = [
            'Paid'      => ['rgba(34,197,94,.12)',  '#22c55e', 'Đã thanh toán'],
            'Pending'   => ['rgba(212,160,23,.12)', '#d4a017', 'Chờ thanh toán'],
            'Cancelled' => ['rgba(239,68,68,.12)',  '#ef4444', 'Đã huỷ'],
          ];
          foreach ($recent as $row):
            [$bg, $cl, $lb] = $statusMap[$row['status']] ?? $statusMap['Pending'];
          ?>
          <tr>
            <td><span style="color:var(--muted)">#<?= $row['invoice_id'] ?></span></td>
            <td><?= htmlspecialchars($row['full_name']) ?></td>
            <td><?= htmlspecialchars($row['plan_name']) ?></td>
            <td><?= $row['invoice_date'] ?></td>
            <td class="td-money"><?= number_format((float)$row['final_amount'], 0, '.', '.') ?> ₫</td>
            <td><span class="status-badge" style="background:<?=$bg?>;color:<?=$cl?>"><?=$lb?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recent)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:32px">Chưa có giao dịch nào</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /wrapper -->

<!-- ══ EXPORT MODAL ══════════════════════════════════════════ -->
<div class="modal-overlay" id="exportModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
    <h2 id="modalTitle">Xuất báo cáo</h2>
    <p id="modalDesc">Chọn định dạng và khoảng thời gian</p>
    <div class="export-options">
      <div class="export-opt selected" onclick="selectFormat(this)" data-fmt="pdf">
        <i class="fas fa-file-pdf" style="color:#ef4444"></i>
        <div class="eo-name">PDF</div><div class="eo-desc">In ấn chuyên nghiệp</div>
      </div>
      <div class="export-opt" onclick="selectFormat(this)" data-fmt="excel">
        <i class="fas fa-file-excel" style="color:#22c55e"></i>
        <div class="eo-name">Excel</div><div class="eo-desc">Phân tích dữ liệu</div>
      </div>
      <div class="export-opt" onclick="selectFormat(this)" data-fmt="csv">
        <i class="fas fa-file-csv" style="color:#3b82f6"></i>
        <div class="eo-name">CSV</div><div class="eo-desc">Nhập vào hệ thống</div>
      </div>
      <div class="export-opt" onclick="selectFormat(this)" data-fmt="json">
        <i class="fas fa-code" style="color:#a855f7"></i>
        <div class="eo-name">JSON</div><div class="eo-desc">Tích hợp API</div>
      </div>
    </div>
    <div class="date-row">
      <div class="form-group"><label>Từ ngày</label><input type="date" id="dateFrom"></div>
      <div class="form-group"><label>Đến ngày</label><input type="date" id="dateTo"></div>
    </div>
    <div class="form-group" style="margin-bottom:22px">
      <label>Nhóm theo</label>
      <select id="groupBy">
        <option value="day">Ngày</option>
        <option value="week">Tuần</option>
        <option value="month" selected>Tháng</option>
        <option value="year">Năm</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal()">Huỷ</button>
      <button class="btn btn-gold"  onclick="doExport()"><i class="fas fa-download"></i> Xuất</button>
    </div>
  </div>
</div>

<!-- ══ TOAST ════════════════════════════════════════════════ -->
<div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>

<script src="management_statistics.js"></script>
</body>
</html>
