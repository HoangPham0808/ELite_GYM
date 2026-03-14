<?php
require_once '../../../Database/db.php';

// ========================
// STATS: Tổng thành viên
// ========================
$result = $conn->query("SELECT COUNT(*) as total FROM Customer");
$total_members = $result->fetch_assoc()['total'];

// Thành viên mới trong tháng này
$result = $conn->query("SELECT COUNT(*) as new_members FROM Customer 
    WHERE MONTH(registered_at) = MONTH(CURDATE()) AND YEAR(registered_at) = YEAR(CURDATE())");
$new_members = $result->fetch_assoc()['new_members'];

// ========================
// STATS: Hoạt động hôm nay (check-in)
// ========================
$result = $conn->query("SELECT COUNT(*) as active_today FROM CheckInHistory 
    WHERE DATE(check_in) = CURDATE()");
$active_today = $result->fetch_assoc()['active_today'];

// ========================
// STATS: Doanh thu tháng (từ Invoice)
// ========================
$result = $conn->query("SELECT COALESCE(SUM(final_amount), 0) as revenue_month 
    FROM Invoice 
    WHERE MONTH(invoice_date) = MONTH(CURDATE()) 
      AND YEAR(invoice_date)  = YEAR(CURDATE())
      AND status = 'Paid'");
$revenue_month = $result->fetch_assoc()['revenue_month'];

// Doanh thu tháng trước (để tính % thay đổi)
$result = $conn->query("SELECT COALESCE(SUM(final_amount), 0) as revenue_last 
    FROM Invoice 
    WHERE MONTH(invoice_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
      AND YEAR(invoice_date)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND status = 'Paid'");
$revenue_last   = $result->fetch_assoc()['revenue_last'];
$revenue_change = $revenue_last > 0 
    ? round((($revenue_month - $revenue_last) / $revenue_last) * 100, 1) 
    : 0;

// ========================
// STATS: Gói tập sắp hết hạn (trong 7 ngày)
// ========================
$result = $conn->query("SELECT COUNT(*) as expiring_soon FROM MembershipRegistration 
    WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$expiring_soon = $result->fetch_assoc()['expiring_soon'];

// Hóa đơn chờ xử lý
$result = $conn->query("SELECT COUNT(*) as pending_invoices FROM Invoice 
    WHERE status = 'Pending'");
$pending_invoices = $result->fetch_assoc()['pending_invoices'];

$stats = [
    'total_members'    => $total_members,
    'active_today'     => $active_today,
    'revenue_month'    => $revenue_month,
    'revenue_change'   => $revenue_change,
    'pending_invoices' => $pending_invoices,
    'new_members'      => $new_members,
    'expiring_soon'    => $expiring_soon
];

// ========================
// HOẠT ĐỘNG GẦN ĐÂY
// ========================
$sql = "
    (SELECT 
        c.full_name          AS name,
        'Check-in phòng tập' AS action,
        ci.check_in          AS event_time,
        'info'               AS type
    FROM CheckInHistory ci
    JOIN Customer c ON c.customer_id = ci.customer_id
    ORDER BY ci.check_in DESC LIMIT 3)
    
    UNION ALL
    
    (SELECT 
        c.full_name                                   AS name,
        CONCAT('Thanh toán hóa đơn #', inv.invoice_id) AS action,
        inv.invoice_date                              AS event_time,
        'success'                                     AS type
    FROM Invoice inv
    JOIN Customer c ON c.customer_id = inv.customer_id
    WHERE inv.status = 'Paid'
    ORDER BY inv.invoice_date DESC LIMIT 3)
    
    UNION ALL
    
    (SELECT 
        c.full_name                                             AS name,
        CONCAT('Gói \"', mp.plan_name, '\" sắp hết hạn')      AS action,
        mr.end_date                                             AS event_time,
        'warning'                                               AS type
    FROM MembershipRegistration mr
    JOIN Customer c       ON c.customer_id = mr.customer_id
    JOIN MembershipPlan mp ON mp.plan_id   = mr.plan_id
    WHERE mr.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY mr.end_date ASC LIMIT 2)
    
    ORDER BY event_time DESC LIMIT 8
";
$result = $conn->query($sql);
$recent_activities = [];
while ($row = $result->fetch_assoc()) {
    $event_time = strtotime($row['event_time']);
    $diff = time() - $event_time;
    if      ($diff < 60)    $time_str = 'Vừa xong';
    elseif  ($diff < 3600)  $time_str = round($diff / 60)    . ' phút trước';
    elseif  ($diff < 86400) $time_str = round($diff / 3600)  . ' giờ trước';
    else                    $time_str = round($diff / 86400) . ' ngày trước';
    
    $recent_activities[] = [
        'name'   => $row['name'],
        'action' => $row['action'],
        'time'   => $time_str,
        'type'   => $row['type']
    ];
}

// ========================
// GÓI TẬP PHỔ BIẾN
// ========================
$sql = "
    SELECT 
        mp.plan_name                              AS name,
        COUNT(id.detail_id)                       AS sales,
        COALESCE(SUM(id.subtotal), 0)             AS revenue
    FROM MembershipPlan mp
    LEFT JOIN InvoiceDetail id  ON id.plan_id    = mp.plan_id
    LEFT JOIN Invoice inv       ON inv.invoice_id = id.invoice_id
        AND MONTH(inv.invoice_date) = MONTH(CURDATE())
        AND YEAR(inv.invoice_date)  = YEAR(CURDATE())
        AND inv.status = 'Paid'
    GROUP BY mp.plan_id, mp.plan_name
    ORDER BY sales DESC
    LIMIT 5
";
$result = $conn->query($sql);
$popular_packages = [];
$max_sales = 1;
while ($row = $result->fetch_assoc()) {
    $popular_packages[] = $row;
    if ($row['sales'] > $max_sales) $max_sales = $row['sales'];
}

// ========================
// CHART: Doanh thu 7 ngày
// ========================
$sql = "
    SELECT 
        DATE(invoice_date)                AS ngay,
        COALESCE(SUM(final_amount), 0)    AS total
    FROM Invoice
    WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND status = 'Paid'
    GROUP BY DATE(invoice_date)
    ORDER BY ngay ASC
";
$result = $conn->query($sql);
$revenue_by_day_raw = [];
while ($row = $result->fetch_assoc()) {
    $revenue_by_day_raw[$row['ngay']] = $row['total'];
}

$chart_labels = [];
$chart_data   = [];
$day_names    = ['CN','T2','T3','T4','T5','T6','T7'];
for ($i = 6; $i >= 0; $i--) {
    $date           = date('Y-m-d', strtotime("-$i days"));
    $dow            = date('w', strtotime($date));
    $chart_labels[] = $day_names[$dow];
    $chart_data[]   = round(($revenue_by_day_raw[$date] ?? 0) / 1000000, 1);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tổng quan - Elite Gym</title>
    <link rel="stylesheet" href="overview.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="overview-container">
        
        <!-- Stats Cards Grid -->
        <div class="stats-grid">

            <div class="stat-card card-blue">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Tổng thành viên</div>
                    <div class="stat-value"><?php echo number_format($stats['total_members']); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> +<?php echo $stats['new_members']; ?> tháng này
                    </div>
                </div>
            </div>

            <div class="stat-card card-green">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Check-in hôm nay</div>
                    <div class="stat-value"><?php echo $stats['active_today']; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-chart-line"></i> Lượt vào hôm nay
                    </div>
                </div>
            </div>

            <div class="stat-card card-gold">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Doanh thu tháng</div>
                    <div class="stat-value"><?php echo number_format($stats['revenue_month'] / 1000000, 1); ?>M</div>
                    <div class="stat-change <?php echo $stats['revenue_change'] >= 0 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-arrow-<?php echo $stats['revenue_change'] >= 0 ? 'up' : 'down'; ?>"></i>
                        <?php echo ($stats['revenue_change'] >= 0 ? '+' : '') . $stats['revenue_change']; ?>% so tháng trước
                    </div>
                </div>
            </div>

            <div class="stat-card card-red">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Hóa đơn chờ xử lý</div>
                    <div class="stat-value"><?php echo $stats['pending_invoices']; ?></div>
                    <div class="stat-change negative">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $stats['expiring_soon']; ?> gói sắp hết hạn
                    </div>
                </div>
            </div>

        </div>

        <!-- Two Column Layout -->
        <div class="content-grid">
            
            <div class="left-column">
                
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Doanh thu 7 ngày gần nhất</h3>
                        <div class="card-actions">
                            <button class="btn-icon" onclick="location.reload()"><i class="fas fa-sync"></i></button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <div class="packages-card">
                    <div class="card-header">
                        <h3>Gói tập phổ biến tháng này</h3>
                        <a href="#management_package.php" class="link-view-all">Xem tất cả <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="packages-list">
                        <?php if (empty($popular_packages)): ?>
                        <div style="padding: 20px; text-align: center; color: rgba(255,255,255,0.4);">
                            Chưa có dữ liệu gói tập tháng này
                        </div>
                        <?php else: ?>
                        <?php foreach ($popular_packages as $index => $pkg): ?>
                        <div class="package-item">
                            <div class="package-rank">#<?php echo $index + 1; ?></div>
                            <div class="package-info">
                                <div class="package-name"><?php echo htmlspecialchars($pkg['name']); ?></div>
                                <div class="package-stats">
                                    <span><i class="fas fa-shopping-cart"></i> <?php echo $pkg['sales']; ?> giao dịch</span>
                                    <span><i class="fas fa-coins"></i> <?php echo number_format($pkg['revenue'] / 1000000, 1); ?>M VNĐ</span>
                                </div>
                            </div>
                            <div class="package-progress">
                                <div class="progress-bar" style="width: <?php echo $max_sales > 0 ? round(($pkg['sales'] / $max_sales) * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="right-column">
                
                <div class="quick-actions-card">
                    <h3>Thao tác nhanh</h3>
                    <div class="actions-grid">
                        <a href="#customer.php" class="action-btn">
                            <i class="fas fa-user-plus"></i>
                            <span>Thêm khách hàng</span>
                        </a>
                        <a href="#management_invoice.php" class="action-btn">
                            <i class="fas fa-file-invoice"></i>
                            <span>Tạo hóa đơn</span>
                        </a>
                        <a href="#management_schedule.php" class="action-btn">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Đặt lịch tập</span>
                        </a>
                        <a href="#management_statistics.php" class="action-btn">
                            <i class="fas fa-chart-pie"></i>
                            <span>Xem báo cáo</span>
                        </a>
                    </div>
                </div>

                <div class="activity-card">
                    <div class="card-header">
                        <h3>Hoạt động gần đây</h3>
                        <button class="btn-icon" onclick="location.reload()"><i class="fas fa-sync"></i></button>
                    </div>
                    <div class="activity-list">
                        <?php if (empty($recent_activities)): ?>
                        <div style="padding: 20px; text-align: center; color: rgba(255,255,255,0.4);">
                            Chưa có hoạt động nào
                        </div>
                        <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item activity-<?php echo $activity['type']; ?>">
                            <div class="activity-icon">
                                <i class="fas <?php 
                                    echo $activity['type'] === 'success' ? 'fa-check-circle' : 
                                        ($activity['type'] === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'); 
                                ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($activity['name']); ?></div>
                                <div class="activity-desc"><?php echo htmlspecialchars($activity['action']); ?></div>
                                <div class="activity-time"><i class="fas fa-clock"></i> <?php echo $activity['time']; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Doanh thu (triệu VNĐ)',
                    data: <?php echo json_encode($chart_data); ?>,
                    borderColor: '#d4a017',
                    backgroundColor: function(context) {
                        const c = context.chart.ctx;
                        const g = c.createLinearGradient(0, 0, 0, 300);
                        g.addColorStop(0, 'rgba(212, 160, 23, 0.3)');
                        g.addColorStop(1, 'rgba(212, 160, 23, 0)');
                        return g;
                    },
                    borderWidth: 3, fill: true, tension: 0.4,
                    pointRadius: 5, pointHoverRadius: 7,
                    pointBackgroundColor: '#d4a017', pointBorderColor: '#000', pointBorderWidth: 2,
                    pointHoverBackgroundColor: '#f0c040', pointHoverBorderColor: '#d4a017', pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(26,26,26,0.95)', titleColor: '#fff',
                        bodyColor: '#d4a017', borderColor: 'rgba(212,160,23,0.3)',
                        borderWidth: 1, padding: 12, displayColors: false,
                        callbacks: { label: c => c.parsed.y.toFixed(1) + ' triệu VNĐ' }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false },
                        ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 12 }, callback: v => v + 'M' }
                    },
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: 'rgba(255,255,255,0.6)', font: { size: 13, weight: '600' } }
                    }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    });

    // Animate stat values
    function animateValue(el, start, end, duration) {
        const inc = (end - start) / (duration / 16);
        let cur = start;
        const t = setInterval(() => {
            cur += inc;
            if (cur >= end) { cur = end; clearInterval(t); }
            el.textContent = Math.floor(cur).toLocaleString('vi-VN');
        }, 16);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const obs = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    const el = e.target;
                    const val = parseFloat(el.textContent.replace(/[^\d.]/g, ''));
                    if (!isNaN(val) && val > 0) { el.textContent = '0'; animateValue(el, 0, val, 1500); }
                    obs.unobserve(el);
                }
            });
        }, { threshold: 0.5 });
        document.querySelectorAll('.stat-value').forEach(s => obs.observe(s));

        const barObs = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    const b = e.target, w = b.style.width;
                    b.style.width = '0';
                    setTimeout(() => { b.style.width = w; }, 100);
                    barObs.unobserve(b);
                }
            });
        }, { threshold: 0.5 });
        document.querySelectorAll('.progress-bar').forEach(b => barObs.observe(b));
    });

    console.log('%c✨ Elite Gym Overview - Live Data', 'color: #d4a017; font-size: 16px; font-weight: bold;');
    </script>
</body>
</html>
