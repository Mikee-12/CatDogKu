<?php
session_start();
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

// ── Handle LOGOUT (POST) ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    $password_input = trim($_POST['logout_password'] ?? '');
    $admin_id = $_SESSION['admin_id'] ?? 0;
    $row = null;

    if ($admin_id > 0 && $password_input !== '') {
        $stmt = mysqli_prepare($conn, "SELECT password FROM user WHERE id_user = ? AND role = 'admin' LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $admin_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }

    if ($row && password_verify($password_input, $row['password'])) {
        session_destroy();
        header('Location: cust_login.php');
        exit;
    } else {
        $logout_error = 'Wrong password. Please try again.';
    }
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
    'Pending'     => $pending,
    'Confirmed'   => $confirmed,
    'In Progress' => $progress,
    'Completed'   => $completed,
];

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CatDogKu Admin Dashboard</title>
<link rel="icon" type="image/png" href="assets/icon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ── Base ── */
body {
    background-color: #f4f7f6;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0;
    padding: 0;
}

/* ── Sidebar ── */
.sidebar {
    width: 260px;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    overflow-y: auto;
    z-index: 1050;
    background-color: #2c3e50;
    color: #fff;
    box-shadow: 4px 0 10px rgba(0,0,0,0.05);
    transition: transform .3s ease;
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
    display: flex;
    align-items: center;
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
    transform: none;
}

/* ── Sidebar overlay ── */
.sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1040; }
.sidebar-overlay.show { display:block; }

/* ── Topbar (mobile only) ── */
.topbar {
    display: none;
    position: sticky; top: 0; z-index: 1030;
    background: #2c3e50; color: #fff;
    padding: 12px 16px;
    align-items: center; justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}
.topbar-brand { font-size: 1rem; font-weight: 700; letter-spacing: .5px; }
.topbar-right  { display: flex; align-items: center; gap: 12px; }
.btn-hamburger {
    background: none; border: none; color: #fff;
    font-size: 1.4rem; cursor: pointer; padding: 4px;
    display: flex; align-items: center; transition: opacity .2s;
}
.btn-hamburger:hover { opacity: .8; }

/* ── Layout ── */
.main-content {
    margin-left: 260px;
    width: calc(100% - 260px);
    min-height: 100vh;
}

/* ── Stat Cards ── */
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
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* ── Status chips grid ── */
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

/* ── Chart & Table cards ── */
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
    white-space: nowrap;
}
.table > tbody > tr > td {
    padding: 12px 16px;
    vertical-align: middle;
    font-size: 14px;
    border-bottom: 1px solid #f5f5f5;
}
.table > tbody > tr:last-child > td { border-bottom: none; }
.table > tbody > tr:hover > td { background-color: #f8f9fa; }

/* ── Popular Services ── */
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
.progress { height: 5px; background: #f0f0f0; border-radius: 4px; margin-top: 5px; }
.progress-bar { height: 100%; border-radius: 4px; background: linear-gradient(90deg, #3498db, #74b9e8); }

/* ── Mobile reservation cards ── */
.reservation-mobile-card {
    background: #fff;
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
    border: 1px solid #f0f0f0;
}
.reservation-mobile-card .rc-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 6px;
}
.reservation-mobile-card .rc-id {
    font-family: monospace;
    font-size: 12px;
    color: #6c757d;
}
.reservation-mobile-card .rc-name {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a2e;
}
.reservation-mobile-card .rc-meta {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 8px;
}
.reservation-mobile-card .rc-badges {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

/* ── Logout modal ── */
.logout-icon-wrap {
    width: 64px; height: 64px; border-radius: 18px;
    background: rgba(231,76,60,.1);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; color: #e74c3c;
    margin: 0 auto 6px;
}
.logout-modal-error {
    background: #fdecea;
    border: 1.5px solid #f5c6c6;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
    color: #922b21;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}
.pw-wrapper { position: relative; }
.pw-wrapper .form-control { padding-right: 50px; }
.pw-toggle {
    position: absolute; right: 0; top: 0; bottom: 0;
    width: 50px; border: none; background: none;
    display: flex; align-items: center; justify-content: center;
    color: #6c757d; cursor: pointer; font-size: 16px;
    transition: color .2s;
}
.pw-toggle:hover { color: #495057; }

/* ════════════════════════════════
   RESPONSIVE
════════════════════════════════ */
@media (max-width: 992px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main-content { margin-left: 0; width: 100%; }
    .topbar { display: flex; }
    .status-row-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
}

@media (max-width: 768px) {
    .main-content { padding: 0 !important; }
    .page-inner  { padding: 16px; }

    /* Stat cards 2 kolom */
    .stat-cards-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; }
    .stat-card .card-body { padding: 12px; }
    .stat-card h3 { font-size: 1.3rem !important; }
    .stat-card h4 { font-size: 1rem !important; }
    .icon-box { width: 42px !important; height: 42px !important; font-size: 20px !important; }

    /* Status chips 2 kolom */
    .status-row-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .status-chip { padding: 12px 14px; gap: 10px; }

    /* Chart */
    .chart-container { padding: 16px; border-radius: 12px; }

    /* Tabel hilang, mobile cards muncul */
    .desktop-table { display: none !important; }
    .mobile-cards  { display: block !important; }

    /* Popular Services compact */
    .service-item { padding: 12px 14px; }

    /* Table card header */
    .table-card-header { padding: 14px 16px; flex-wrap: wrap; gap: 8px; }
    .table-card-header h5 { font-size: 14px; }
}

@media (min-width: 769px) {
    .mobile-cards  { display: none !important; }
    .desktop-table { display: block !important; }
}

@media (max-width: 480px) {
    .page-inner { padding: 12px; }
    .status-row-grid { gap: 8px; }
    .chart-container { padding: 12px; }
}
</style>
</head>
<body>

<!-- ══ SIDEBAR OVERLAY ══ -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ══ TOPBAR (mobile) ══ -->
<div class="topbar" id="topbar">
    <button class="btn-hamburger" onclick="openSidebar()" aria-label="Open menu">
        <i class="bi bi-list"></i>
    </button>
    <span class="topbar-brand">CatDogKu Admin</span>
    <div class="topbar-right">
        <a href="admin_reserve.php?status=pending" class="position-relative text-decoration-none" style="color:#fff">
            <i class="bi bi-bell fs-5"></i>
            <?php if($pending > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px"><?= $pending ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<!-- ══ SIDEBAR ══ -->
<div class="sidebar p-3" id="sidebar">
    <div class="d-flex align-items-center justify-content-between mb-2 d-lg-none">
        <span style="font-size:.9rem;font-weight:600;color:rgba(255,255,255,.6);">Menu</span>
        <button class="btn-hamburger" onclick="closeSidebar()" style="font-size:1.2rem">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
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
        <a href="#" class="logout-link fw-bold" onclick="openLogoutModal(); return false;">
            <i class="bi bi-box-arrow-left me-2 fs-5 align-middle"></i> Logout
        </a>
    </div>
</div>

<!-- ══ MAIN CONTENT ══ -->
<div class="main-content p-4 p-md-5">
<div class="page-inner">

    <!-- Header desktop -->
    <div class="d-none d-lg-flex justify-content-between align-items-center mb-5">
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

    <!-- Header mobile -->
    <div class="d-lg-none mb-3">
        <h4 class="fw-bold text-dark mb-0">Dashboard</h4>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="row g-3 g-md-4 mb-4 stat-cards-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
        <div>
            <a href="admin_user.php" class="text-decoration-none">
                <div class="card stat-card shadow-sm h-100 p-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size:12px">Total Users</p>
                            <h3 class="fw-bold mb-0 text-dark"><?= number_format($total_users) ?></h3>
                            <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">Registered Customers</p>
                        </div>
                        <div class="icon-box bg-primary bg-opacity-10 text-primary" style="width:50px;height:50px;font-size:24px">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div>
            <a href="admin_reserve.php" class="text-decoration-none">
                <div class="card stat-card shadow-sm h-100 p-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size:12px">Reservations</p>
                            <h3 class="fw-bold mb-0 text-dark"><?= number_format($total_reservasi) ?></h3>
                            <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px"><span class="text-warning fw-semibold"><?= $pending ?> pending</span></p>
                        </div>
                        <div class="icon-box bg-info bg-opacity-10 text-info" style="width:50px;height:50px;font-size:24px">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div>
            <a href="admin_pay.php" class="text-decoration-none">
                <div class="card stat-card shadow-sm h-100 p-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size:12px">Revenue</p>
                            <h4 class="fw-bold mb-0 text-dark" style="font-size:16px">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></h4>
                            <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">Total paid</p>
                        </div>
                        <div class="icon-box bg-success bg-opacity-10 text-success" style="width:50px;height:50px;font-size:24px">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div>
            <a href="admin_staff.php" class="text-decoration-none">
                <div class="card stat-card shadow-sm h-100 p-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size:12px">Active Staff</p>
                            <h3 class="fw-bold mb-0 text-dark"><?= number_format($total_staff) ?></h3>
                            <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">Ready to serve</p>
                        </div>
                        <div class="icon-box bg-warning bg-opacity-10" style="color:#d97706;width:50px;height:50px;font-size:24px">
                            <i class="bi bi-person-badge"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- ── Status Chips ── -->
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

    <!-- ── Chart ── -->
    <div class="chart-container mb-4">
        <h5 class="fw-bold mb-4 border-bottom pb-3" style="font-size:15px">
            Reservation Status Trend
        </h5>
        <div style="height:260px;">
            <canvas id="reservasiChart"></canvas>
        </div>
    </div>

    <!-- ── Bottom Grid: Table + Services ── -->
    <div class="row g-4">

        <!-- Recent Reservations -->
        <div class="col-lg-8">
            <div class="table-card">
                <div class="table-card-header">
                    <h5 class="fw-bold mb-0" style="font-size:15px">Recent Reservations</h5>
                    <a href="admin_reserve.php" class="text-primary text-decoration-none" style="font-size:13px;font-weight:500">View all →</a>
                </div>

                <!-- DESKTOP TABLE -->
                <div class="table-responsive desktop-table">
                    <table class="table mb-0">
                        <thead>
                            <tr>
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
                                    $pay_map   = ['paid'=>'bg-success text-white','unpaid'=>'bg-danger text-white','partial'=>'bg-warning text-dark'];
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

                <!-- MOBILE CARDS -->
                <div class="mobile-cards p-3">
                    <?php if(empty($recent)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-calendar-x fs-2 d-block mb-2 text-secondary"></i>No reservations yet
                        </div>
                    <?php else: ?>
                    <?php
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
                    $pay_map   = ['paid'=>'bg-success text-white','unpaid'=>'bg-danger text-white','partial'=>'bg-warning text-dark'];
                    $pay_label = ['paid'=>'Paid','unpaid'=>'Unpaid','partial'=>'Partial'];
                    foreach($recent as $r):
                        $s  = $r['status'];
                        $sp = $r['status_bayar'] ?? 'unpaid';
                    ?>
                    <div class="reservation-mobile-card">
                        <div class="rc-top">
                            <div>
                                <div class="rc-id">#<?= str_pad($r['id_reservation'],4,'0',STR_PAD_LEFT) ?></div>
                                <div class="rc-name"><?= htmlspecialchars($r['nama_user'] ?? '—') ?></div>
                            </div>
                            <span class="badge <?= $badge_map[$s] ?? 'bg-secondary text-white' ?>" style="font-size:11px"><?= $label_map[$s] ?? $s ?></span>
                        </div>
                        <div class="rc-meta">
                            🐾 <?= htmlspecialchars($r['nama_pet'] ?? '—') ?>
                            &nbsp;·&nbsp;
                            <?= $r['waktu_mulai'] ? date('d M Y H:i', strtotime($r['waktu_mulai'])) : '—' ?>
                        </div>
                        <div class="rc-badges">
                            <span class="badge <?= $pay_map[$sp] ?? 'bg-secondary text-white' ?>" style="font-size:11px"><?= $pay_label[$sp] ?? $sp ?></span>
                            <?php if($r['total_bayar']): ?>
                            <span class="badge bg-light text-dark border" style="font-size:11px">Rp <?= number_format($r['total_bayar'],0,',','.') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- Popular Services -->
        <div class="col-lg-4">
            <div class="table-card h-100">
                <div class="table-card-header">
                    <h5 class="fw-bold mb-0" style="font-size:15px">Popular Services</h5>
                    <a href="admin_service.php" class="text-primary text-decoration-none" style="font-size:13px;font-weight:500">Details →</a>
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
                    <div style="font-size:12px;font-weight:700;color:#198754;white-space:nowrap;margin-left:8px">
                        Rp <?= number_format($sv['revenue'],0,',','.') ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /row -->

</div><!-- /page-inner -->
</div><!-- /main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart -->
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

<!-- Sidebar -->
<script>
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
    document.body.style.overflow = '';
}
</script>

<!-- ══════════════════ LOGOUT MODAL ══════════════════════════ -->
<div class="modal fade form-modal" id="logoutModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        onclick="clearLogoutForm()"></button>
            </div>
            <form method="POST" action="admin_dash.php" id="logoutForm">
                <input type="hidden" name="confirm_logout" value="1">
                <div class="modal-body pt-2">
                    <div class="text-center mb-4">
                        <div class="logout-icon-wrap">
                            <i class="bi bi-box-arrow-left"></i>
                        </div>
                        <h5 class="fw-bold mt-3 mb-1" style="font-size:16px;color:#1a1a2e">Log Out Confirmation</h5>
                        <p class="text-muted mb-0" style="font-size:13px">
                            Enter your password to log out of this session.
                        </p>
                    </div>

                    <?php if(!empty($logout_error)): ?>
                    <div class="logout-modal-error">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <?= htmlspecialchars($logout_error) ?>
                    </div>
                    <?php endif; ?>

                    <div class="logout-modal-error" id="logout-js-error" style="display:none">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span id="logout-js-error-msg">Password is required.</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#555">Password <span class="text-danger">*</span></label>
                        <div class="pw-wrapper">
                            <input type="password" name="logout_password" id="logout-pw"
                                   class="form-control" placeholder="Enter your password" required
                                   autocomplete="current-password">
                            <button type="button" class="pw-toggle" id="pw-toggle-btn"
                                    onclick="togglePwVisibility()">
                                <i class="bi bi-eye" id="pw-toggle-icon"></i>
                            </button>
                        </div>
                        <div class="form-text">Confirm your identity before logging out.</div>
                    </div>

                    <hr style="border-color:#f0f0f0;margin:18px 0 16px">

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger fw-bold"
                                style="border-radius:10px;font-size:13.5px;padding:10px"
                                onclick="return validateLogout()">
                            Yes, Logout
                        </button>
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal"
                                style="border-radius:8px;font-size:13px" onclick="clearLogoutForm()">
                            Close
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openLogoutModal() {
    clearLogoutForm();
    new bootstrap.Modal(document.getElementById('logoutModal')).show();
    document.getElementById('logoutModal').addEventListener('shown.bs.modal', function onShown() {
        document.getElementById('logout-pw').focus();
        this.removeEventListener('shown.bs.modal', onShown);
    });
}
function clearLogoutForm() {
    document.getElementById('logout-pw').value = '';
    document.getElementById('logout-js-error').style.display = 'none';
    document.getElementById('logout-pw').type = 'password';
    document.getElementById('pw-toggle-icon').className = 'bi bi-eye';
}
function validateLogout() {
    const pw  = document.getElementById('logout-pw').value.trim();
    const err = document.getElementById('logout-js-error');
    const msg = document.getElementById('logout-js-error-msg');
    if (pw === '') {
        msg.textContent = 'Password cannot be empty.';
        err.style.display = 'flex';
        document.getElementById('logout-pw').focus();
        return false;
    }
    err.style.display = 'none';
    return true;
}
function togglePwVisibility() {
    const input = document.getElementById('logout-pw');
    const icon  = document.getElementById('pw-toggle-icon');
    if (input.type === 'password') {
        input.type     = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type     = 'password';
        icon.className = 'bi bi-eye';
    }
}
<?php if(!empty($logout_error)): ?>
window.addEventListener('DOMContentLoaded', () => {
    new bootstrap.Modal(document.getElementById('logoutModal')).show();
});
<?php endif; ?>
</script>
</body>
</html>