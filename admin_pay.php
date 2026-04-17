<?php
require_once 'config/koneksi.php';

// ── helpers ────────────────────────────────────────────────────────────────
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

// ── Handle AJAX confirm payment ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_confirm_payment'])) {
    header('Content-Type: application/json');
    $id_payment = intval($_POST['id_payment'] ?? 0);
    if ($id_payment > 0) {
        $sql = "UPDATE payments SET status_bayar='paid', tgl_bayar=NOW() WHERE id_payment=$id_payment";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    }
    exit;
}

// ── sidebar badge ──────────────────────────────────────────────────────────
$pending = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='pending'");

// ── stat cards ─────────────────────────────────────────────────────────────
$total_paid    = db_val($conn, "SELECT COALESCE(SUM(total_bayar),0) FROM payments WHERE status_bayar='paid'");
$total_unpaid  = db_val($conn, "SELECT COALESCE(SUM(total_bayar),0) FROM payments WHERE status_bayar='unpaid'");
$total_partial = db_val($conn, "SELECT COALESCE(SUM(total_bayar),0) FROM payments WHERE status_bayar='partial'");
$count_paid    = db_val($conn, "SELECT COUNT(*) FROM payments WHERE status_bayar='paid'");
$count_unpaid  = db_val($conn, "SELECT COUNT(*) FROM payments WHERE status_bayar='unpaid'");
$count_partial = db_val($conn, "SELECT COUNT(*) FROM payments WHERE status_bayar='partial'");
$total_all     = db_val($conn, "SELECT COUNT(*) FROM payments");

// ── method breakdown ───────────────────────────────────────────────────────
$methods = db_rows($conn, "
    SELECT metode_bayar, COUNT(*) AS cnt, COALESCE(SUM(total_bayar),0) AS total
    FROM payments
    GROUP BY metode_bayar
    ORDER BY cnt DESC
");

// ── monthly revenue (last 6 months) — PER MONTH ───────────────────────────
$monthly = db_rows($conn, "
    SELECT DATE_FORMAT(tgl_bayar,'%b %Y') AS bulan,
           YEAR(tgl_bayar) AS yr,
           MONTH(tgl_bayar) AS mo,
           COALESCE(SUM(total_bayar),0) AS total
    FROM payments
    WHERE status_bayar='paid'
      AND tgl_bayar >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY yr, mo, bulan
    ORDER BY yr, mo
");

// ── recent payments (with filters) ────────────────────────────────────────
$filter_status = $_GET['status'] ?? '';
$filter_method = $_GET['metode'] ?? '';
$search        = $_GET['search'] ?? '';
$page          = max(1, intval($_GET['page'] ?? 1));
$per_page      = 10;

$where = ['1=1'];
if ($filter_status !== '') $where[] = "pay.status_bayar = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
if ($filter_method !== '') $where[] = "pay.metode_bayar = '" . mysqli_real_escape_string($conn, $filter_method) . "'";
if ($search !== '')        $where[] = "(CONCAT(u.nama_depan,' ',u.nama_belakang) LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' OR pay.id_payment LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";

$where_sql = implode(' AND ', $where);

$total_rows = db_val($conn, "
    SELECT COUNT(*)
    FROM payments pay
    LEFT JOIN reservations r  ON pay.id_reservation = r.id_reservation
    LEFT JOIN user u           ON r.id_user = u.id_user
    WHERE $where_sql
");

$total_pages = max(1, ceil($total_rows / $per_page));
$offset      = ($page - 1) * $per_page;

$payments = db_rows($conn, "
    SELECT pay.id_payment, pay.id_reservation,
           CONCAT(u.nama_depan,' ',u.nama_belakang) AS nama_user,
           p.nama_pet,
           pay.total_bayar, pay.status_bayar, pay.metode_bayar,
           pay.tgl_bayar AS tanggal_bayar, r.status AS status_reservasi
    FROM payments pay
    LEFT JOIN reservations r ON pay.id_reservation = r.id_reservation
    LEFT JOIN user u          ON r.id_user = u.id_user
    LEFT JOIN pets p          ON r.id_pet  = p.id_pet
    WHERE $where_sql
    ORDER BY pay.tgl_bayar DESC
    LIMIT $per_page OFFSET $offset
");

$all_methods = db_rows($conn, "SELECT DISTINCT metode_bayar FROM payments WHERE metode_bayar IS NOT NULL ORDER BY metode_bayar");

$current_page_file = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments – CatDogKu Admin</title>
<link rel="icon" type="image/png" href="assets/icon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    body {
        background-color: #f4f7f6;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0; padding: 0;
    }
    /* ── Sidebar ─────────────────────────────────────────────────── */
    .sidebar {
        width: 260px;
        position: fixed;
        top: 0; left: 0;
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
        display: block;
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
        background-color: rgba(231,76,60,0.2);
        color: #e74c3c;
        transform: none;
    }
    /* ── Stat cards ──────────────────────────────────────────────── */
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
        width: 55px; height: 55px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px;
    }
    /* ── Chart / table wrappers ──────────────────────────────────── */
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
        display: flex; align-items: center; justify-content: space-between;
    }
    .table > thead > tr > th {
        font-size: 12px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.5px;
        color: #6c757d; background-color: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        padding: 12px 16px;
    }
    .table > tbody > tr > td {
        padding: 12px 16px;
        vertical-align: middle; font-size: 14px;
        border-bottom: 1px solid #f5f5f5;
    }
    .table > tbody > tr:last-child > td { border-bottom: none; }
    .table > tbody > tr:hover > td { background-color: #f8f9fa; }
    /* ── Status chips row ────────────────────────────────────────── */
    .status-row-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    .status-chip {
        background: #fff;
        border-radius: 12px;
        padding: 16px 20px;
        display: flex; align-items: center; gap: 14px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    .status-dot {
        width: 12px; height: 12px;
        border-radius: 50%; flex-shrink: 0;
    }
    /* ── Method bar items ────────────────────────────────────────── */
    .method-item {
        display: flex; align-items: center; gap: 12px;
        padding: 14px 20px;
        border-bottom: 1px solid #f5f5f5;
        transition: background 0.15s;
    }
    .method-item:last-child { border-bottom: none; }
    .method-item:hover { background: #f8f9fa; }
    .method-rank {
        width: 28px; height: 28px;
        border-radius: 8px; background: #f1f3f4;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 700; color: #6c757d; flex-shrink: 0;
    }
    .progress { height: 5px; background: #f0f0f0; border-radius: 4px; margin-top: 5px; }
    .progress-bar { height: 100%; border-radius: 4px; background: linear-gradient(90deg,#3498db,#74b9e8); }
    /* ── Filter bar ──────────────────────────────────────────────── */
    .filter-bar {
        background: #fff;
        border-radius: 15px;
        padding: 16px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        margin-bottom: 20px;
    }
    .filter-bar .form-control,
    .filter-bar .form-select {
        border-radius: 10px;
        border: 1.5px solid #e9ecef;
        font-size: 13px;
    }
    .filter-bar .form-control:focus,
    .filter-bar .form-select:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
    }
    .btn-filter {
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        padding: 8px 18px;
    }
    /* ── Pagination ──────────────────────────────────────────────── */
    .pagination .page-link {
        border-radius: 8px !important;
        margin: 0 2px;
        border: none;
        color: #3498db;
        font-size: 13px;
    }
    .pagination .page-item.active .page-link {
        background-color: #3498db;
        color: #fff;
    }

    /* ── Payment Confirmation Modal ──────────────────────────────── */
    .confirm-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    .confirm-modal-overlay.show {
        display: flex;
    }
    .confirm-modal {
        background: #fff;
        border-radius: 20px;
        padding: 36px 32px;
        width: 420px;
        max-width: 90vw;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        animation: modalIn 0.25s ease;
        position: relative;
    }
    @keyframes modalIn {
        from { transform: scale(0.9) translateY(10px); opacity: 0; }
        to   { transform: scale(1) translateY(0); opacity: 1; }
    }
    .confirm-modal .modal-icon {
        width: 64px; height: 64px;
        border-radius: 50%;
        background: rgba(34,197,94,0.12);
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px;
        font-size: 30px; color: #16a34a;
    }
    .confirm-modal h5 {
        text-align: center;
        font-weight: 700;
        font-size: 18px;
        margin-bottom: 8px;
    }
    .confirm-modal .modal-sub {
        text-align: center;
        color: #6c757d;
        font-size: 13px;
        margin-bottom: 22px;
    }
    .confirm-modal .detail-box {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 16px 18px;
        margin-bottom: 24px;
    }
    .confirm-modal .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
        margin-bottom: 8px;
    }
    .confirm-modal .detail-row:last-child { margin-bottom: 0; }
    .confirm-modal .detail-label { color: #6c757d; font-weight: 500; }
    .confirm-modal .detail-value { font-weight: 700; color: #1a1a2e; }
    .confirm-modal .detail-value.price { color: #16a34a; font-size: 15px; }
    .btn-confirm-yes {
        background: linear-gradient(135deg, #16a34a, #22c55e);
        color: #fff;
        border: none;
        border-radius: 12px;
        padding: 12px 0;
        width: 100%;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s;
        margin-bottom: 10px;
    }
    .btn-confirm-yes:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(34,197,94,0.35);
    }
    .btn-confirm-yes:disabled {
        opacity: 0.7;
        transform: none;
        cursor: not-allowed;
    }
    .btn-confirm-cancel {
        background: #f1f3f4;
        color: #6c757d;
        border: none;
        border-radius: 12px;
        padding: 11px 0;
        width: 100%;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-confirm-cancel:hover { background: #e2e6ea; }

    /* ── Toast notification ──────────────────────────────────────── */
    .toast-notif {
        position: fixed;
        top: 24px; right: 24px;
        background: #16a34a;
        color: #fff;
        border-radius: 12px;
        padding: 14px 20px;
        font-size: 14px;
        font-weight: 600;
        display: flex; align-items: center; gap: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        z-index: 99999;
        opacity: 0;
        transform: translateX(40px);
        transition: all 0.3s ease;
        pointer-events: none;
    }
    .toast-notif.show {
        opacity: 1;
        transform: translateX(0);
    }

    /* ── Responsive ──────────────────────────────────────────────── */
    @media (max-width: 992px) {
        .sidebar { transform: translateX(-100%); }
        .sidebar.open { transform: translateX(0); }
        .main-content { margin-left: 0; width: 100%; }
        .status-row-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 576px) {
        .status-row-grid { grid-template-columns: 1fr 1fr; }
    }
</style>
</head>
<body>

<!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
<div class="sidebar p-3" id="sidebar">
    <div class="sidebar-brand text-center py-3 mb-4 fw-bold text-white">
        CatDogKu Admin
    </div>

    <a href="admin_dash.php" class="<?= $current_page_file==='admin_dash.php'?'active':'' ?>">
        <i class="bi bi-speedometer2 me-2 fs-5 align-middle"></i> Dashboard
    </a>

    <div class="text-uppercase text-secondary px-2 mb-1 mt-3" style="font-size:11px;letter-spacing:1px;font-weight:600;">Management</div>
    <a href="reservations.php" class="<?= $current_page_file==='reservations.php'?'active':'' ?>">
        <i class="bi bi-calendar-check me-2 fs-5 align-middle"></i> Reservations
        <?php if($pending>0): ?>
            <span class="badge bg-danger ms-auto"><?= $pending ?></span>
        <?php endif; ?>
    </a>
    <a href="admin_pay.php" class="<?= $current_page_file==='admin_pay.php'?'active':'' ?>">
        <i class="bi bi-credit-card me-2 fs-5 align-middle"></i> Payments
    </a>

    <div class="text-uppercase text-secondary px-2 mb-1 mt-3" style="font-size:11px;letter-spacing:1px;font-weight:600;">Master Data</div>
    <a href="users.php"     class="<?= $current_page_file==='users.php'?'active':'' ?>"><i class="bi bi-people me-2 fs-5 align-middle"></i> Users</a>
    <a href="staffs.php"    class="<?= $current_page_file==='staffs.php'?'active':'' ?>"><i class="bi bi-person-badge me-2 fs-5 align-middle"></i> Staff</a>
    <a href="services.php"  class="<?= $current_page_file==='services.php'?'active':'' ?>"><i class="bi bi-stars me-2 fs-5 align-middle"></i> Services</a>
    <a href="breeds.php"    class="<?= $current_page_file==='breeds.php'?'active':'' ?>"><i class="bi bi-bug me-2 fs-5 align-middle"></i> Breeds</a>
    <a href="schedules.php" class="<?= $current_page_file==='schedules.php'?'active':'' ?>"><i class="bi bi-clock me-2 fs-5 align-middle"></i> Staff Schedules</a>

    <div class="mt-4 pt-3 border-top border-secondary">
        <a href="settings.php" class="<?= $current_page_file==='settings.php'?'active':'' ?>"><i class="bi bi-gear me-2 fs-5 align-middle"></i> Settings</a>
        <a href="logout.php" class="logout-link text-danger fw-bold"><i class="bi bi-box-arrow-left me-2 fs-5 align-middle"></i> Logout</a>
    </div>
</div>

<!-- ── Main Content ────────────────────────────────────────────────────── -->
<div class="main-content p-4 p-md-5">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Payments</h2>
            <p class="text-muted mb-0" style="font-size:14px">Manage and monitor all payment transactions</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="reservations.php?status=pending" class="position-relative text-decoration-none text-secondary">
                <i class="bi bi-bell fs-5"></i>
                <?php if($pending>0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px"><?= $pending ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- ── Stat Cards ─────────────────────────────────────────────────── -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card shadow-sm h-100 p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">Total Revenue</p>
                        <h4 class="fw-bold mb-0 text-dark" style="font-size:18px">Rp <?= number_format($total_paid,0,',','.') ?></h4>
                        <p class="text-muted mb-0" style="font-size:12px"><?= number_format($count_paid) ?> paid transactions</p>
                    </div>
                    <div class="icon-box bg-success bg-opacity-10 text-success">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card shadow-sm h-100 p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">Unpaid</p>
                        <h4 class="fw-bold mb-0 text-dark" style="font-size:18px">Rp <?= number_format($total_unpaid,0,',','.') ?></h4>
                        <p class="text-muted mb-0" style="font-size:12px"><span class="text-danger fw-semibold"><?= number_format($count_unpaid) ?></span> awaiting payment</p>
                    </div>
                    <div class="icon-box bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-exclamation-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card shadow-sm h-100 p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">Partial</p>
                        <h4 class="fw-bold mb-0 text-dark" style="font-size:18px">Rp <?= number_format($total_partial,0,',','.') ?></h4>
                        <p class="text-muted mb-0" style="font-size:12px"><span class="text-warning fw-semibold"><?= number_format($count_partial) ?></span> partial payments</p>
                    </div>
                    <div class="icon-box bg-warning bg-opacity-10" style="color:#d97706">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card stat-card shadow-sm h-100 p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">Total Transactions</p>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($total_all) ?></h3>
                        <p class="text-muted mb-0" style="font-size:12px">All payment records</p>
                    </div>
                    <div class="icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-credit-card"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Status Chips ───────────────────────────────────────────────── -->
    <div class="status-row-grid mb-4">
        <div class="status-chip">
            <div class="status-dot" style="background:#22c55e"></div>
            <div>
                <div style="font-size:12px;color:#6c757d;font-weight:500">Paid</div>
                <div style="font-size:22px;font-weight:700;line-height:1.2"><?= $count_paid ?></div>
            </div>
        </div>
        <div class="status-chip">
            <div class="status-dot" style="background:#ef4444"></div>
            <div>
                <div style="font-size:12px;color:#6c757d;font-weight:500">Unpaid</div>
                <div style="font-size:22px;font-weight:700;line-height:1.2"><?= $count_unpaid ?></div>
            </div>
        </div>
        <div class="status-chip">
            <div class="status-dot" style="background:#f59e0b"></div>
            <div>
                <div style="font-size:12px;color:#6c757d;font-weight:500">Partial</div>
                <div style="font-size:22px;font-weight:700;line-height:1.2"><?= $count_partial ?></div>
            </div>
        </div>
    </div>

    <!-- ── Chart + Method Breakdown ──────────────────────────────────── -->
    <div class="row g-4 mb-4">
        <!-- Monthly Revenue Chart -->
        <div class="col-lg-8">
            <div class="chart-container h-100">
                <h5 class="fw-bold mb-4 border-bottom pb-3">
                    <i class="bi bi-bar-chart-line text-primary me-2"></i> Monthly Revenue
                </h5>
                <div style="height:270px;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Payment Method Breakdown -->
        <div class="col-lg-4">
            <div class="table-card h-100">
                <div class="table-card-header">
                    <h5 class="fw-bold mb-0" style="font-size:15px">
                        <i class="bi bi-wallet2 text-warning me-2"></i> Payment Methods
                    </h5>
                </div>
                <?php if(empty($methods)): ?>
                    <div class="text-center text-muted py-4">No data yet</div>
                <?php else:
                    $max_m = max(array_column($methods,'cnt')) ?: 1;
                    foreach($methods as $i => $m):
                        $pct = round(($m['cnt']/$max_m)*100);
                        $label = $m['metode_bayar'] ? ucwords(str_replace('_',' ',$m['metode_bayar'])) : 'Unknown';
                        // ── Method icon map ──
                        $raw_method = strtolower($m['metode_bayar'] ?? '');
                        $method_icon_map = [
                            'cash'          => ['bi-cash-coin',       '#16a34a'],
                            'transfer'      => ['bi-bank2',            '#2563eb'],
                            'bank_transfer' => ['bi-bank2',            '#2563eb'],
                            'credit_card'   => ['bi-credit-card-2-front','#7c3aed'],
                            'debit_card'    => ['bi-credit-card',      '#0891b2'],
                            'qris'          => ['bi-qr-code-scan',     '#dc2626'],
                            'qr'            => ['bi-qr-code-scan',     '#dc2626'],
                            'ovo'           => ['bi-phone',            '#4f46e5'],
                            'gopay'         => ['bi-phone-fill',       '#00aa5b'],
                            'dana'          => ['bi-wallet-fill',      '#1d4ed8'],
                            'shopeepay'     => ['bi-bag-fill',         '#ee4d2d'],
                            'virtual_account'=> ['bi-building',        '#0e7490'],
                        ];
                        [$m_icon, $m_color] = $method_icon_map[$raw_method] ?? ['bi-wallet2', '#6c757d'];
                ?>
                <div class="method-item">
                    <div class="method-rank"><?= $i+1 ?></div>
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:6px">
                            <i class="bi <?= $m_icon ?>" style="color:<?= $m_color ?>;font-size:15px;flex-shrink:0"></i>
                            <span style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($label) ?></span>
                        </div>
                        <div style="font-size:11px;color:#6c757d"><?= $m['cnt'] ?> transactions</div>
                        <div class="progress"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
                    </div>
                    <div style="font-size:13px;font-weight:700;color:#198754;white-space:nowrap;margin-left:8px">
                        Rp <?= number_format($m['total'],0,',','.') ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Filter Bar ─────────────────────────────────────────────────── -->
    <div class="filter-bar">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label mb-1" style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.5px">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0" style="border-radius:10px 0 0 10px;border:1.5px solid #e9ecef"><i class="bi bi-search text-muted" style="font-size:13px"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" style="border-radius:0 10px 10px 0" placeholder="Customer name…" value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1" style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.5px">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="paid"    <?= $filter_status==='paid'    ?'selected':'' ?>>Paid</option>
                    <option value="unpaid"  <?= $filter_status==='unpaid'  ?'selected':'' ?>>Unpaid</option>
                    <option value="partial" <?= $filter_status==='partial' ?'selected':'' ?>>Partial</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.5px">Method</label>
                <select name="metode" class="form-select">
                    <option value="">All Methods</option>
                    <?php foreach($all_methods as $am): ?>
                        <option value="<?= htmlspecialchars($am['metode_bayar']) ?>" <?= $filter_method===$am['metode_bayar']?'selected':'' ?>>
                            <?= ucwords(str_replace('_',' ', $am['metode_bayar'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-filter flex-fill">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="admin_pay.php" class="btn btn-outline-secondary btn-filter">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- ── Payments Table ─────────────────────────────────────────────── -->
    <div class="table-card mb-4">
        <div class="table-card-header">
            <h5 class="fw-bold mb-0" style="font-size:15px">
                <i class="bi bi-list-ul text-primary me-2"></i> Payment Records
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2" style="font-size:12px;font-weight:600"><?= number_format($total_rows) ?></span>
            </h5>
            <span class="text-muted" style="font-size:13px">Page <?= $page ?> of <?= $total_pages ?></span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Pet</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Pay Status</th>
                        <th>Res. Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($payments)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>No payment records found
                    </td></tr>
                <?php else: ?>
                <?php foreach($payments as $pay): ?>
                <?php
                    // ── Method icon for table ──
                    $raw_m = strtolower($pay['metode_bayar'] ?? '');
                    $tbl_icon_map = [
                        'cash'           => ['bi-cash-coin',            '#16a34a'],
                        'transfer'       => ['bi-bank2',                '#2563eb'],
                        'bank_transfer'  => ['bi-bank2',                '#2563eb'],
                        'credit_card'    => ['bi-credit-card-2-front',  '#7c3aed'],
                        'debit_card'     => ['bi-credit-card',          '#0891b2'],
                        'qris'           => ['bi-qr-code-scan',         '#dc2626'],
                        'qr'             => ['bi-qr-code-scan',         '#dc2626'],
                        'ovo'            => ['bi-phone',                '#4f46e5'],
                        'gopay'          => ['bi-phone-fill',           '#00aa5b'],
                        'dana'           => ['bi-wallet-fill',          '#1d4ed8'],
                        'shopeepay'      => ['bi-bag-fill',             '#ee4d2d'],
                        'virtual_account'=> ['bi-building',             '#0e7490'],
                    ];
                    [$tbl_icon, $tbl_color] = $tbl_icon_map[$raw_m] ?? ['bi-wallet2', '#6c757d'];
                    $m_label = $pay['metode_bayar'] ? ucwords(str_replace('_',' ',$pay['metode_bayar'])) : '—';
                ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($pay['nama_user'] ?? '—') ?></td>
                    <td class="text-muted"><?= htmlspecialchars($pay['nama_pet'] ?? '—') ?></td>
                    <td>
                        <span style="font-size:13px;display:inline-flex;align-items:center;gap:5px">
                            <i class="bi <?= $tbl_icon ?>" style="color:<?= $tbl_color ?>;font-size:15px"></i>
                            <?= htmlspecialchars($m_label) ?>
                        </span>
                    </td>
                    <td class="fw-bold text-dark">
                        Rp <?= number_format(floatval($pay['total_bayar'] ?? 0), 0, ',', '.') ?>
                    </td>
                    <td>
                        <?php
                        $sp = $pay['status_bayar'] ?? 'unpaid';
                        $pay_map   = ['paid'=>'bg-success text-white','unpaid'=>'bg-danger text-white','partial'=>'bg-warning text-dark'];
                        $pay_label = ['paid'=>'Paid','unpaid'=>'Unpaid','partial'=>'Partial'];
                        ?>
                        <span class="badge <?= $pay_map[$sp] ?? 'bg-secondary text-white' ?>" style="font-size:11px">
                            <?= $pay_label[$sp] ?? $sp ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $rs = $pay['status_reservasi'] ?? '';
                        $rb = ['pending'=>'bg-warning text-dark','confirmed'=>'bg-info text-white','in_progress'=>'bg-primary text-white','completed'=>'bg-success text-white','cancelled'=>'bg-danger text-white'];
                        $rl = ['pending'=>'Pending','confirmed'=>'Confirmed','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'];
                        ?>
                        <span class="badge <?= $rb[$rs] ?? 'bg-secondary text-white' ?>" style="font-size:11px">
                            <?= $rl[$rs] ?? ($rs ?: '—') ?>
                        </span>
                    </td>
                    <td class="text-muted" style="font-size:12px">
                        <?= $pay['tanggal_bayar'] ? date('d M Y H:i', strtotime($pay['tanggal_bayar'])) : '—' ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="pay_detail.php?id=<?= $pay['id_payment'] ?>" class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:12px" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if($sp !== 'paid'): ?>
                            <button type="button"
                                class="btn btn-sm btn-outline-success"
                                style="border-radius:8px;font-size:12px"
                                title="Mark Paid"
                                onclick="openConfirmModal(
                                    <?= $pay['id_payment'] ?>,
                                    '<?= addslashes(htmlspecialchars($pay['nama_user'] ?? '—')) ?>',
                                    '<?= addslashes(htmlspecialchars($pay['nama_pet'] ?? '—')) ?>',
                                    '<?= addslashes(htmlspecialchars($m_label)) ?>',
                                    <?= floatval($pay['total_bayar'] ?? 0) ?>
                                )">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="d-flex justify-content-center py-3 border-top">
            <nav>
                <ul class="pagination mb-0">
                    <?php
                    $qs = http_build_query(array_filter(['search'=>$search,'status'=>$filter_status,'metode'=>$filter_method]));
                    ?>
                    <li class="page-item <?= $page<=1?'disabled':'' ?>">
                        <a class="page-link" href="?<?= $qs ?>&page=<?= $page-1 ?>"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <?php for($p=max(1,$page-2); $p<=min($total_pages,$page+2); $p++): ?>
                    <li class="page-item <?= $p==$page?'active':'' ?>">
                        <a class="page-link" href="?<?= $qs ?>&page=<?= $p ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
                        <a class="page-link" href="?<?= $qs ?>&page=<?= $page+1 ?>"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /main-content -->

<!-- ── Payment Confirmation Modal ─────────────────────────────────────── -->
<div class="confirm-modal-overlay" id="confirmModalOverlay">
    <div class="confirm-modal">
        <div class="modal-icon">
            <i class="bi bi-shield-check"></i>
        </div>
        <h5>Confirm Payment</h5>
        <p class="modal-sub">Please review the payment details below before confirming.</p>

        <div class="detail-box">
            <div class="detail-row">
                <span class="detail-label"><i class="bi bi-person me-1"></i>Customer</span>
                <span class="detail-value" id="modal_customer">—</span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="bi bi-heart me-1"></i>Pet</span>
                <span class="detail-value" id="modal_pet">—</span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="bi bi-wallet me-1"></i>Method</span>
                <span class="detail-value" id="modal_method">—</span>
            </div>
            <div class="detail-row" style="margin-top:10px;padding-top:10px;border-top:1px solid #e9ecef">
                <span class="detail-label" style="font-size:14px"><i class="bi bi-cash me-1"></i>Amount</span>
                <span class="detail-value price" id="modal_amount">Rp 0</span>
            </div>
        </div>

        <button class="btn-confirm-yes" id="btnConfirmYes" onclick="submitConfirmPayment()">
            <i class="bi bi-check-circle me-2"></i> Confirm as Paid
        </button>
        <button class="btn-confirm-cancel" onclick="closeConfirmModal()">
            Cancel
        </button>
    </div>
</div>

<!-- ── Toast Notification ─────────────────────────────────────────────── -->
<div class="toast-notif" id="toastNotif">
    <i class="bi bi-check-circle-fill"></i>
    <span id="toastMsg">Payment confirmed successfully!</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Monthly Revenue Chart ─────────────────────────────────────────────────
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($monthly,'bulan')) ?: '[]' ?>,
        datasets: [
            {
                type: 'bar',
                label: 'Revenue (Rp)',
                data: <?= json_encode(array_map(fn($r)=>floatval($r['total']),$monthly)) ?: '[]' ?>,
                backgroundColor: 'rgba(52,152,219,0.18)',
                borderColor: 'rgba(52,152,219,1)',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
            },
            {
                type: 'line',
                label: 'Trend',
                data: <?= json_encode(array_map(fn($r)=>floatval($r['total']),$monthly)) ?: '[]' ?>,
                borderColor: 'rgba(34,197,94,0.9)',
                borderWidth: 2.5,
                pointBackgroundColor: '#fff',
                pointBorderColor: 'rgba(34,197,94,1)',
                pointBorderWidth: 2,
                pointRadius: 5,
                fill: false,
                tension: 0.4,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
            y: {
                beginAtZero: true,
                // FIX: Y-axis per bulan, skala 0–10jt
                max: 5000000,
                ticks: {
                    stepSize: 500000,
callback: function(v) {
    if (v === 0) return 'Rp 0';
    return 'Rp ' + (v / 1000000) + ' jt';
}
                },
                grid: { color: 'rgba(0,0,0,0.04)' }
            },
            x: { grid: { display: false } }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(44,62,80,0.9)',
                titleFont: { size: 13 },
                bodyFont: { size: 13 },
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(ctx) {
                        return ' Rp ' + ctx.raw.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});

// ── Payment Confirmation Modal ────────────────────────────────────────────
let _confirmPaymentId = null;

function openConfirmModal(id, customer, pet, method, amount) {
    _confirmPaymentId = id;
    document.getElementById('modal_customer').textContent = customer;
    document.getElementById('modal_pet').textContent      = pet;
    document.getElementById('modal_method').textContent   = method;
    document.getElementById('modal_amount').textContent   = 'Rp ' + amount.toLocaleString('id-ID');
    document.getElementById('confirmModalOverlay').classList.add('show');
}

function closeConfirmModal() {
    document.getElementById('confirmModalOverlay').classList.remove('show');
    _confirmPaymentId = null;
}

// Close on backdrop click
document.getElementById('confirmModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeConfirmModal();
});

function submitConfirmPayment() {
    if (!_confirmPaymentId) return;

    const btn = document.getElementById('btnConfirmYes');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Confirming…';

    const formData = new FormData();
    formData.append('ajax_confirm_payment', '1');
    formData.append('id_payment', _confirmPaymentId);

    fetch(window.location.pathname, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeConfirmModal();
            showToast('Payment #' + String(_confirmPaymentId).padStart(4,'0') + ' confirmed as Paid!');
            // Update the row badge without full reload
            setTimeout(() => location.reload(), 1400);
        } else {
            alert('Error: ' + (data.message || 'Failed to update payment.'));
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-2"></i> Confirm as Paid';
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-2"></i> Confirm as Paid';
    });
}

// ── Toast ─────────────────────────────────────────────────────────────────
function showToast(msg) {
    const t = document.getElementById('toastNotif');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}
</script>
</body>
</html>