<?php
require_once 'config/koneksi.php';

function db_val($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    if (!$res) return 0;
    $row = mysqli_fetch_row($res);
    return $row ? $row[0] : 0;
}

function db_rows($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    if (!$res) return [];
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    return $rows;
}

$total_users      = db_val($conn, "SELECT COUNT(*) FROM user WHERE role='user'");
$total_reservasi  = db_val($conn, "SELECT COUNT(*) FROM reservations");
$total_staff      = db_val($conn, "SELECT COUNT(*) FROM staffs WHERE is_active=1");
$total_pendapatan = db_val($conn, "SELECT COALESCE(SUM(total_bayar),0) FROM payments WHERE status_bayar='paid'");

$pending   = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='pending'");
$confirmed = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='confirmed'");
$progress  = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='in_progress'");
$completed = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='completed'");

$recent = db_rows($conn, "
    SELECT r.id_reservation, CONCAT(u.nama_depan,' ',u.nama_belakang) AS nama_user,
           p.nama_pet, r.waktu_mulai, r.status, pay.total_bayar, pay.status_bayar
    FROM reservations r
    LEFT JOIN user u ON r.id_user = u.id_user
    LEFT JOIN pets p ON r.id_pet = p.id_pet
    LEFT JOIN payments pay ON r.id_reservation = pay.id_reservation
    ORDER BY r.created_at DESC LIMIT 8
");

$services = db_rows($conn, "
    SELECT s.nama_service, COUNT(rd.id_detail) AS total, SUM(rd.subtotal) AS revenue
    FROM services s
    LEFT JOIN reservation_details rd ON s.id_service = rd.id_service
    GROUP BY s.id_service ORDER BY total DESC LIMIT 5
");

$status_chart = [
    'Pending'    => $pending,
    'Confirmed'  => $confirmed,
    'In Progress' => $progress,
    'Completed'    => $completed,
];

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PetCare Admin Dashboard</title>
<link rel="icon" type="image/png" href="assets/icon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    body {
        background-color: #f4f7f6;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        padding: 0;
    }

    .sidebar {
        width: 260px;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        overflow-y: auto;
        z-index: 1000;
        background-color: #2c3e50;
        color: #fff;
        box-shadow: 4px 0 10px rgba(0,0,0,0.05);
    }

    .main-content {
        margin-left: 260px;
        width: calc(100% - 260px);
        min-height: 100vh;
    }

    .sidebar-brand {
        font-size: 1.25rem;
        letter-spacing: 1px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

.sidebar a {
    color: #aeb6bf;
    text-decoration: none;
    padding: 12px 20px;
    display: flex;        /* ← jadi ini */
    align-items: center;  /* ← tambahkan ini */
    border-radius: 10px;
    margin-bottom: 8px;
    transition: all 0.3s ease;
    font-weight: 500;
}

    .sidebar a:hover,
    .sidebar a.active {
        background-color: #3498db;
        color: #fff;
        transform: translateX(5px);
    }

    .sidebar a.logout-link:hover {
        background-color: rgba(231, 76, 60, 0.2);
        color: #e74c3c;
    }

    .stat-card {
        border-radius: 15px;
        border: none;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.08) !important;
    }

    .icon-box {
        width: 55px;
        height: 55px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
    }

    .chart-container {
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 5px 20px rgba(0,0,0,0.03);
        padding: 30px;
    }

    .table-card {
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 5px 20px rgba(0,0,0,0.03);
        overflow: hidden;
    }

    .table-card-header {
        padding: 18px 24px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .table > thead > tr > th {
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        background-color: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        padding: 12px 16px;
    }

    .table > tbody > tr > td {
        padding: 12px 16px;
        vertical-align: middle;
        font-size: 14px;
        border-bottom: 1px solid #f5f5f5;
    }

    .table > tbody > tr:last-child > td {
        border-bottom: none;
    }

    .table > tbody > tr:hover > td {
        background-color: #f8f9fa;
    }

    .status-row-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .status-chip {
        background: #fff;
        border-radius: 12px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }

    .status-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .service-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        border-bottom: 1px solid #f5f5f5;
        transition: background 0.15s;
    }

    .service-item:last-child { border-bottom: none; }
    .service-item:hover { background: #f8f9fa; }

    .service-rank {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: #f1f3f4;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        color: #6c757d;
        flex-shrink: 0;
    }

    .progress {
        height: 5px;
        background: #f0f0f0;
        border-radius: 4px;
        margin-top: 5px;
    }

    .progress-bar {
        height: 100%;
        border-radius: 4px;
        background: linear-gradient(90deg, #3498db, #74b9e8);
    }

    @media (max-width: 992px) {
        .sidebar { transform: translateX(-100%); }
        .sidebar.open { transform: translateX(0); }
        .main-content { margin-left: 0; width: 100%; }
        .status-row-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 576px) {
        .status-row-grid { grid-template-columns: 1fr 1fr; }
    }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar p-3" id="sidebar">
<div class="sidebar-brand text-center py-3 mb-4 fw-bold text-white">
    CatDogKu Admin
</div>

    <a href="admin_dash.php" class="<?= $current_page==='admin_dash.php'?'active':'' ?>">
        <i class="bi bi-speedometer2 me-2 fs-5 align-middle"></i> Dashboard
    </a>

    <div class="text-uppercase text-secondary px-2 mb-1 mt-3" style="font-size:11px;letter-spacing:1px;font-weight:600;">Management</div>
    <a href="admin_reserve.php" class="<?= $current_page==='admin_reserve.php'?'active':'' ?>">
        <i class="bi bi-calendar-check me-2 fs-5 align-middle"></i> Reservations
        <?php if($pending>0): ?>
            <span class="badge bg-danger ms-auto"><?= $pending ?></span>
        <?php endif; ?>
    </a>
    <a href="admin_pay.php" class="<?= $current_page==='payments.php'?'active':'' ?>">
        <i class="bi bi-credit-card me-2 fs-5 align-middle"></i> Payments
    </a>

    <div class="text-uppercase text-secondary px-2 mb-1 mt-3" style="font-size:11px;letter-spacing:1px;font-weight:600;">Master Data</div>
    <a href="admin_user.php" class="<?= $current_page==='admin_user.php'?'active':'' ?>">
        <i class="bi bi-people me-2 fs-5 align-middle"></i> Users
    </a>
    <a href="admin_staff.php" class="<?= $current_page==='admin_staffs.php'?'active':'' ?>">
        <i class="bi bi-person-badge me-2 fs-5 align-middle"></i> Staff
    </a>
    <a href="admin_service.php" class="<?= $current_page==='admin_service.php'?'active':'' ?>">
        <i class="bi bi-stars me-2 fs-5 align-middle"></i> Services
    </a>
    <a href="admin_breed.php" class="<?= $current_page==='admin_breed.php'?'active':'' ?>">
        <i class="bi bi-bug me-2 fs-5 align-middle"></i> Breeds
    </a>
    <a href="admin_staffschedule.php" class="<?= $current_page==='admin_staffschedule.php'?'active':'' ?>">
        <i class="bi bi-clock me-2 fs-5 align-middle"></i> Staff Schedules
    </a>

    <div class="mt-4 pt-3 border-top border-secondary">
        <a href="logout.php" class="logout-link text-danger fw-bold">
            <i class="bi bi-box-arrow-left me-2 fs-5 align-middle"></i> Logout
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content p-4 p-md-5">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Dashboard</h2>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="admin_reserve.php?status=pending" class="position-relative text-decoration-none text-secondary">
                <i class="bi bi-bell fs-5"></i>
                <?php if($pending>0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px"><?= $pending ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <a href="admin_user.php" class="text-decoration-none">
                <div class="card stat-card shadow-sm h-100 p-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size:13px">Total Users</p>
                            <h3 class="fw-bold mb-0 text-dark"><?= number_format($total_users) ?></h3>
                            <p class="text-muted mb-0" style="font-size:12px">Registered Customers</p>
                        </div>
                        <div class="icon-box bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-sm-6">
            <a href="admin_reserve.php" class="text-decoration-none">
                <div class="card stat-card shadow-sm h-100 p-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size:13px">Total Reservations</p>
                            <h3 class="fw-bold mb-0 text-dark"><?= number_format($total_reservasi) ?></h3>
                            <p class="text-muted mb-0" style="font-size:12px"><span class="text-warning fw-semibold"><?= $pending ?> pending</span> waiting</p>
                        </div>
                        <div class="icon-box bg-info bg-opacity-10 text-info">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-sm-6">
            <a href="admin_pay.php" class="text-decoration-none">
                <div class="card stat-card shadow-sm h-100 p-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size:13px">Revenue</p>
                            <h4 class="fw-bold mb-0 text-dark" style="font-size:18px">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></h4>
                            <p class="text-muted mb-0" style="font-size:12px">Total paid payments</p>
                        </div>
                        <div class="icon-box bg-success bg-opacity-10 text-success">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-sm-6">
            <a href="admin_staff.php" class="text-decoration-none">
                <div class="card stat-card shadow-sm h-100 p-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size:13px">Active Staff</p>
                            <h3 class="fw-bold mb-0 text-dark"><?= number_format($total_staff) ?></h3>
                            <p class="text-muted mb-0" style="font-size:12px">Ready to serve customers</p>
                        </div>
                        <div class="icon-box bg-warning bg-opacity-10" style="color:#d97706">
                            <i class="bi bi-person-badge"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Status Row -->
    <div class="status-row-grid mb-4">
        <div class="status-chip">
            <div class="status-dot" style="background:#f59e0b"></div>
            <div>
                <div style="font-size:12px;color:#6c757d;font-weight:500">Pending</div>
                <div style="font-size:22px;font-weight:700;line-height:1.2"><?= $pending ?></div>
            </div>
        </div>
        <div class="status-chip">
            <div class="status-dot" style="background:#06b6d4"></div>
            <div>
                <div style="font-size:12px;color:#6c757d;font-weight:500">Confirmed</div>
                <div style="font-size:22px;font-weight:700;line-height:1.2"><?= $confirmed ?></div>
            </div>
        </div>
        <div class="status-chip">
            <div class="status-dot" style="background:#6366f1"></div>
            <div>
                <div style="font-size:12px;color:#6c757d;font-weight:500">In Progress</div>
                <div style="font-size:22px;font-weight:700;line-height:1.2"><?= $progress ?></div>
            </div>
        </div>
        <div class="status-chip">
            <div class="status-dot" style="background:#22c55e"></div>
            <div>
                <div style="font-size:12px;color:#6c757d;font-weight:500">Completed</div>
                <div style="font-size:22px;font-weight:700;line-height:1.2"><?= $completed ?></div>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="chart-container mb-4">
        <h5 class="fw-bold mb-4 border-bottom pb-3">
            Reservation Status Trend
        </h5>
        <div style="height:300px;">
            <canvas id="reservasiChart"></canvas>
        </div>
    </div>

    <!-- Bottom Grid: Table + Services -->
    <div class="row g-4">

        <!-- Recent Reservations -->
        <div class="col-lg-8">
            <div class="table-card">
                <div class="table-card-header">
                    <h5 class="fw-bold mb-0" style="font-size:15px">
                       Recent Reservations
                    </h5>
                    <a href="admin_reserve.php" class="text-primary text-decoration-none" style="font-size:13px;font-weight:500">View all →</a>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Customer</th>
                                <th>Pet</th>
                                <th>Start Time</th>
                                <th>Status</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recent)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No reservations yet</td></tr>
                            <?php else: ?>
                            <?php foreach($recent as $r): ?>
                            <tr>
                                <td class="text-muted" style="font-family:monospace;font-size:12px">#<?= str_pad($r['id_reservation'],4,'0',STR_PAD_LEFT) ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($r['nama_user'] ?? '—') ?></td>
                                <td class="text-muted"><?= htmlspecialchars($r['nama_pet'] ?? '—') ?></td>
                                <td class="text-muted" style="font-size:12px">
                                    <?= $r['waktu_mulai'] ? date('d M Y H:i', strtotime($r['waktu_mulai'])) : '—' ?>
                                </td>
                                <td>
                                    <?php
                                    $s = $r['status'];
                                    $badge_map = [
                                        'pending'     => 'bg-warning text-dark',
                                        'confirmed'   => 'bg-info text-white',
                                        'in_progress' => 'bg-primary text-white',
                                        'completed'   => 'bg-success text-white',
                                        'cancelled'   => 'bg-danger text-white',
                                    ];
                                    $label_map = [
                                        'pending'     => 'Pending',
                                        'confirmed'   => 'Confirmed',
                                        'in_progress' => 'In Progress',
                                        'completed'   => 'Completed',
                                        'cancelled'   => 'Cancelled',
                                    ];
                                    $cls = $badge_map[$s] ?? 'bg-secondary text-white';
                                    ?>
                                    <span class="badge <?= $cls ?>" style="font-size:11px"><?= $label_map[$s] ?? $s ?></span>
                                </td>
                                <td>
                                    <?php
                                    $sp = $r['status_bayar'] ?? 'unpaid';
                                    $pay_map = ['paid'=>'bg-success text-white','unpaid'=>'bg-danger text-white','partial'=>'bg-warning text-dark'];
                                    $pay_label = ['paid'=>'Paid','unpaid'=>'Unpaid','partial'=>'Partial'];
                                    ?>
                                    <span class="badge <?= $pay_map[$sp] ?? 'bg-secondary text-white' ?>" style="font-size:11px"><?= $pay_label[$sp] ?? $sp ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Popular Services -->
        <div class="col-lg-4">
            <div class="table-card h-100">
                <div class="table-card-header">
                    <h5 class="fw-bold mb-0" style="font-size:15px">
                        Popular Services
                    </h5>
                    <a href="services.php" class="text-primary text-decoration-none" style="font-size:13px;font-weight:500">Details →</a>
                </div>
                <?php if(empty($services)): ?>
                <div class="text-center text-muted py-4">No data yet</div>
                <?php else: ?>
                <?php
                $max = max(array_column($services,'total')) ?: 1;
                foreach($services as $i => $sv):
                    $pct = round(($sv['total']/$max)*100);
                ?>
                <div class="service-item">
                    <div class="service-rank"><?= $i+1 ?></div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($sv['nama_service']) ?></div>
                        <div style="font-size:11px;color:#6c757d"><?= $sv['total'] ?> transactions</div>
                        <div class="progress"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
                    </div>
                    <div style="font-size:13px;font-weight:700;color:#198754;white-space:nowrap;margin-left:8px">
                        Rp <?= number_format($sv['revenue'],0,',','.') ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /row -->

</div><!-- /main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ctx = document.getElementById('reservasiChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($status_chart)) ?>,
        datasets: [{
            label: 'Number of Reservations',
            data: <?= json_encode(array_values($status_chart)) ?>,
            backgroundColor: 'rgba(52, 152, 219, 0.15)',
            borderColor: 'rgba(52, 152, 219, 1)',
            borderWidth: 3,
            pointBackgroundColor: '#fff',
            pointBorderColor: 'rgba(52, 152, 219, 1)',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(44, 62, 80, 0.9)',
                titleFont: { size: 14 },
                bodyFont: { size: 14 },
                padding: 12,
                cornerRadius: 8,
                displayColors: false
            }
        }
    }
});
</script>
</body>
</html>