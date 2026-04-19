<?php
require_once 'config/koneksi.php';

// ── Helpers ────────────────────────────────────────────────────────────────
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

// ── Filters ─────────────────────────────────────────────────────────────────
$filter_status  = isset($_GET['status'])  && $_GET['status']  !== '' ? $_GET['status']  : null;
$filter_payment = isset($_GET['payment']) && $_GET['payment'] !== '' ? $_GET['payment'] : null;
$search         = isset($_GET['q'])       && $_GET['q']       !== '' ? trim($_GET['q']) : null;

$where_parts = [];
if ($filter_status)  $where_parts[] = "r.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
if ($filter_payment) $where_parts[] = "pay.status_bayar = '" . mysqli_real_escape_string($conn, $filter_payment) . "'";
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where_parts[] = "(CONCAT(u.nama_depan,' ',u.nama_belakang) LIKE '%$s%'
                       OR p.nama_pet LIKE '%$s%'
                       OR r.id_reservation LIKE '%$s%')";
}
$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// ── Pagination ───────────────────────────────────────────────────────────────
$per_page    = 10;
$current_pg  = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($current_pg - 1) * $per_page;

$total_rows  = db_val($conn, "
    SELECT COUNT(*)
    FROM reservations r
    LEFT JOIN user u ON r.id_user = u.id_user
    LEFT JOIN pets p ON r.id_pet = p.id_pet
    LEFT JOIN payments pay ON r.id_reservation = pay.id_reservation
    $where_sql
");
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// ── Main reservation list ────────────────────────────────────────────────────
$reservations = db_rows($conn, "
    SELECT
        r.id_reservation,
        CONCAT(u.nama_depan,' ',u.nama_belakang) AS nama_user,
        u.email,
        p.nama_pet,
        r.waktu_mulai,
        r.waktu_selesai,
        r.status,
        r.catatan,
        r.created_at,
        pay.id_payment,
        pay.total_bayar,
        pay.status_bayar,
        pay.metode_bayar,
        (
            SELECT GROUP_CONCAT(s.nama_service SEPARATOR ', ')
            FROM reservation_details rd
            JOIN services s ON rd.id_service = s.id_service
            WHERE rd.id_reservation = r.id_reservation
        ) AS services_list,
        (
            SELECT GROUP_CONCAT(rd.subtotal SEPARATOR ',')
            FROM reservation_details rd
            WHERE rd.id_reservation = r.id_reservation
        ) AS subtotals_list
    FROM reservations r
    LEFT JOIN user u      ON r.id_user        = u.id_user
    LEFT JOIN pets p      ON r.id_pet          = p.id_pet
    LEFT JOIN payments pay ON r.id_reservation = pay.id_reservation
    $where_sql
    ORDER BY r.created_at DESC
    LIMIT $per_page OFFSET $offset
");

// ── Summary stats ─────────────────────────────────────────────────────────────
$stat_pending   = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='pending'");
$stat_confirmed = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='confirmed'");
$stat_progress  = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='in_progress'");
$stat_completed = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='completed'");
$stat_cancelled = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='cancelled'");
$stat_revenue   = db_val($conn, "SELECT COALESCE(SUM(total_bayar),0) FROM payments WHERE status_bayar='paid'");

$current_page_file = basename($_SERVER['PHP_SELF']);

// ── Handle quick status update (AJAX-friendly POST) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id  = (int)$_POST['id_reservation'];
    $act = $_POST['action'];
    $map = [
        'confirm'   => 'confirmed',
        'progress'  => 'in_progress',
        'complete'  => 'completed',
        'cancel'    => 'cancelled',
    ];
    if (isset($map[$act])) {
        $new_status = $map[$act];
        mysqli_query($conn, "UPDATE reservations SET status='$new_status' WHERE id_reservation=$id");
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter([
        'status'  => $filter_status,
        'payment' => $filter_payment,
        'q'       => $search,
        'page'    => $current_pg,
    ])));
    exit;
}

// ── Build pagination query string helper ─────────────────────────────────────
function pg_qs($page, $filter_status, $filter_payment, $search) {
    return '?' . http_build_query(array_filter([
        'status'  => $filter_status,
        'payment' => $filter_payment,
        'q'       => $search,
        'page'    => $page,
    ]));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservations — PetCare Admin</title>
<link rel="icon" type="image/png" href="assets/icon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
/* ── Base ──────────────────────────────────────────────────────────────── */
body {
    background-color: #f4f7f6;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0; padding: 0;
}

/* ── Sidebar (identical to dashboard) ─────────────────────────────────── */
.sidebar {
    width: 260px;
    position: fixed;
    top: 0; left: 0;
    height: 100vh;
    overflow-y: auto;
    z-index: 1000;
    background-color: #2c3e50;
    color: #fff;
    box-shadow: 4px 0 10px rgba(0,0,0,.05);
}
.sidebar-brand {
    font-size: 1.25rem;
    letter-spacing: 1px;
    border-bottom: 1px solid rgba(255,255,255,.1);
}
.sidebar a {
    color: #aeb6bf;
    text-decoration: none;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    border-radius: 10px;
    margin-bottom: 8px;
    transition: all .3s ease;
    font-weight: 500;
}
.sidebar a:hover,
.sidebar a.active {
    background-color: #3498db;
    color: #fff;
    transform: translateX(5px);
}
.sidebar a.logout-link:hover {
    background-color: rgba(231,76,60,.2);
    color: #e74c3c;
}

/* ── Layout ─────────────────────────────────────────────────────────────── */
.main-content {
    margin-left: 260px;
    width: calc(100% - 260px);
    min-height: 100vh;
}

/* ── Stat chips ──────────────────────────────────────────────────────────── */
.stat-chip {
    background: #fff;
    border-radius: 14px;
    padding: 18px 22px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 2px 10px rgba(0,0,0,.04);
    cursor: pointer;
    transition: transform .25s, box-shadow .25s;
    text-decoration: none;
}
.stat-chip:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 22px rgba(0,0,0,.08);
}
.stat-dot {
    width: 13px; height: 13px;
    border-radius: 50%;
    flex-shrink: 0;
}
.stat-chip .count {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.1;
    color: #1a1a2e;
}
.stat-chip .label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
}

/* ── Toolbar ─────────────────────────────────────────────────────────────── */
.toolbar {
    background: #fff;
    border-radius: 14px;
    padding: 18px 22px;
    box-shadow: 0 2px 10px rgba(0,0,0,.04);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.toolbar .form-select,
.toolbar .form-control {
    border-radius: 10px;
    border: 1.5px solid #e9ecef;
    font-size: 13px;
    height: 40px;
}
.toolbar .form-select:focus,
.toolbar .form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,.12);
}
.btn-filter {
    height: 40px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    padding: 0 18px;
}

/* Tambahkan ini */
.btn-outline-secondary.btn-filter {
    border: 1.5px solid #dee2e6;
    color: #6c757d;
    background: #fff;
}
.btn-outline-secondary.btn-filter:hover {
    background: #f8f9fa;
    color: #495057;
    border-color: #adb5bd;
}

/* ── Table card ──────────────────────────────────────────────────────────── */
.table-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(0,0,0,.04);
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
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: #6c757d;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 13px 16px;
    white-space: nowrap;
}
.table > tbody > tr > td {
    padding: 13px 16px;
    vertical-align: middle;
    font-size: 13.5px;
    border-bottom: 1px solid #f5f5f5;
}
.table > tbody > tr:last-child > td { border-bottom: none; }
.table > tbody > tr:hover > td { background: #f8f9fa; }

/* ── Service tags ────────────────────────────────────────────────────────── */
.service-tag {
    display: inline-block;
    background: #eaf4fd;
    color: #2980b9;
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 11.5px;
    font-weight: 600;
    margin: 2px 2px 2px 0;
    white-space: nowrap;
}

/* ── Action buttons ──────────────────────────────────────────────────────── */
.btn-action {
    padding: 4px 10px;
    font-size: 11.5px;
    border-radius: 7px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: opacity .2s;
}
.btn-action:hover { opacity: .8; }
.btn-view { background: #eaf4fd; color: #2980b9; }
.btn-confirm  { background: #d1f2eb; color: #1a7a4a; }
.btn-progress { background: #d6eaf8; color: #1a5276; }
.btn-complete { background: #d5f5e3; color: #145a32; }
.btn-cancel   { background: #fdecea; color: #922b21; }

/* ── Pagination ──────────────────────────────────────────────────────────── */
.pagination .page-link {
    border-radius: 8px !important;
    margin: 0 2px;
    font-size: 13px;
    font-weight: 600;
    color: #3498db;
    border: 1.5px solid #e9ecef;
}
.pagination .page-item.active .page-link {
    background: #3498db;
    border-color: #3498db;
    color: #fff;
}
.pagination .page-link:hover {
    background: #eaf4fd;
    color: #2980b9;
}

/* ── Detail modal ────────────────────────────────────────────────────────── */
.modal-content { border-radius: 18px; border: none; }
.modal-header  { border-bottom: 1px solid #f0f0f0; padding: 20px 24px; }
.modal-body    { padding: 24px; }
.detail-row {
    display: flex;
    gap: 8px;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
    font-size: 13.5px;
}
.detail-row:last-child { border-bottom: none; }
.detail-label {
    width: 140px;
    flex-shrink: 0;
    color: #6c757d;
    font-weight: 600;
}
.detail-value { color: #1a1a2e; }

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 992px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main-content { margin-left: 0; width: 100%; }
}
</style>
</head>
<body>

<!-- ═══════════════════════════ SIDEBAR ════════════════════════════════════ -->
<div class="sidebar p-3" id="sidebar">
    <div class="sidebar-brand text-center py-3 mb-4 fw-bold text-white">
        CatDogKu Admin
    </div>

    <a href="admin_dash.php" class="<?= $current_page_file==='admin_dash.php'?'active':'' ?>">
        <i class="bi bi-speedometer2 me-2 fs-5 align-middle"></i> Dashboard
    </a>

    <div class="text-uppercase text-secondary px-2 mb-1 mt-3" style="font-size:11px;letter-spacing:1px;font-weight:600;">Management</div>
    <a href="admin_reserve.php" class="<?= $current_page_file==='admin_reserve.php'?'active':'' ?>">
        <i class="bi bi-calendar-check me-2 fs-5 align-middle"></i> Reservations
        <?php if($stat_pending > 0): ?>
            <span class="badge bg-danger ms-auto"><?= $stat_pending ?></span>
        <?php endif; ?>
    </a>
    <a href="admin_pay.php" class="<?= $current_page_file==='admin_pay.php'?'active':'' ?>">
        <i class="bi bi-credit-card me-2 fs-5 align-middle"></i> Payments
    </a>

    <div class="text-uppercase text-secondary px-2 mb-1 mt-3" style="font-size:11px;letter-spacing:1px;font-weight:600;">Master Data</div>
    <a href="admin_user.php" class="<?= $current_page_file==='admin_user.php'?'active':'' ?>">
        <i class="bi bi-people me-2 fs-5 align-middle"></i> Users
    </a>
    <a href="admin_staff.php" class="<?= $current_page_file==='admin_staff.php'?'active':'' ?>">
        <i class="bi bi-person-badge me-2 fs-5 align-middle"></i> Staff
    </a>
    <a href="admin_service.php" class="<?= $current_page_file==='admin_service.php'?'active':'' ?>">
        <i class="bi bi-stars me-2 fs-5 align-middle"></i> Services
    </a>
    <a href="admin_breed.php" class="<?= $current_page_file==='admin_breed.php'?'active':'' ?>">
        <i class="bi bi-bug me-2 fs-5 align-middle"></i> Breeds
    </a>
    <a href="admin_staffschedule.php" class="<?= $current_page_file==='admin_staffschedule.php'?'active':'' ?>">
        <i class="bi bi-clock me-2 fs-5 align-middle"></i> Staff Schedules
    </a>

    <div class="mt-4 pt-3 border-top border-secondary">
        <a href="logout.php" class="logout-link text-danger fw-bold">
            <i class="bi bi-box-arrow-left me-2 fs-5 align-middle"></i> Logout
        </a>
    </div>
</div>

<!-- ═══════════════════════════ MAIN CONTENT ═══════════════════════════════ -->
<div class="main-content p-4 p-md-5">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Reservations</h2>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="admin_reserve.php?status=pending" class="position-relative text-decoration-none text-secondary">
                <i class="bi bi-bell fs-5"></i>
                <?php if($stat_pending > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px"><?= $stat_pending ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- ── Status summary chips ──────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <?php
        $chips = [
    ['label' => 'Pending',     'count' => $stat_pending,   'color' => '#f59e0b', 'status' => 'pending',     'col' => 'col-6 col-md-4 col-lg-2'],
    ['label' => 'Confirmed',   'count' => $stat_confirmed,  'color' => '#06b6d4', 'status' => 'confirmed',   'col' => 'col-6 col-md-4 col-lg-2'],
    ['label' => 'In Progress', 'count' => $stat_progress,   'color' => '#6366f1', 'status' => 'in_progress', 'col' => 'col-6 col-md-4 col-lg-2'],
    ['label' => 'Completed',   'count' => $stat_completed,  'color' => '#22c55e', 'status' => 'completed',   'col' => 'col-6 col-md-4 col-lg-2'],
    ['label' => 'Revenue',     'count' => 'Rp '.number_format($stat_revenue,0,',','.'), 'color' => '#8b5cf6', 'status' => '', 'col' => 'col-12 col-md-8 col-lg-4'],
];
        foreach ($chips as $c):
            $href = $c['status'] ? 'admin_reserve.php?status=' . $c['status'] : 'admin_reserve.php';
            $active_cls = ($filter_status === $c['status'] && $c['status']) ? 'border border-2' : '';
        ?>
        <div class="<?= $c['col'] ?>">
            <a href="<?= $href ?>" class="stat-chip <?= $active_cls ?>" style="<?= ($filter_status===$c['status'] && $c['status']) ? 'border-color:'.$c['color'].'!important' : '' ?>">
                <div class="stat-dot" style="background:<?= $c['color'] ?>"></div>
                <div>
                    <div class="count"><?= $c['count'] ?></div>
                    <div class="label"><?= $c['label'] ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Toolbar / Filters ─────────────────────────────────────────────── -->
    <form method="GET" action="admin_reserve.php">
        <div class="toolbar mb-4">
            <!-- Search -->
            <div class="flex-grow-1" style="min-width:180px">
                <div class="input-group" style="height:40px">
                    <span class="input-group-text bg-white border-end-0" style="border-radius:10px 0 0 10px;border:1.5px solid #e9ecef;border-right:none">
                        <i class="bi bi-search text-muted" style="font-size:13px"></i>
                    </span>
                    <input type="text" name="q" class="form-control border-start-0" style="border-radius:0 10px 10px 0;border:1.5px solid #e9ecef;border-left:none"
                        placeholder="Search customer, pet, ID…" value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
            </div>

            <!-- Status filter -->
            <select name="status" class="form-select" style="width:auto">
                <option value="">All Status</option>
                <?php foreach(['pending'=>'Pending','confirmed'=>'Confirmed','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= $filter_status===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Payment filter -->
            <select name="payment" class="form-select" style="width:auto">
                <option value="">All Payment</option>
                <?php foreach(['paid'=>'Paid','unpaid'=>'Unpaid','partial'=>'Partial'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= $filter_payment===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary btn-filter">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            <?php if($filter_status || $filter_payment || $search): ?>
                <a href="admin_reserve.php" class="btn btn-filter" 
   style="border:1.5px solid #dee2e6;color:#6c757d;background:#fff;display:inline-flex;align-items:center">
    <i class="bi bi-x-lg me-1"></i> Reset
</a>
            <?php endif; ?>

            <!-- Result count -->
            <span class="ms-auto text-muted" style="font-size:13px;white-space:nowrap">
                <b><?= $total_rows ?></b> reservations found
            </span>
        </div>
    </form>

    <!-- ── Table ─────────────────────────────────────────────────────────── -->
    <div class="table-card mb-4">
        <div class="table-card-header">
            <h5 class="fw-bold mb-0" style="font-size:15px">
                All Reservations
                <?php if($filter_status): ?>
                    <span class="badge bg-primary ms-2" style="font-size:12px;font-weight:500"><?= ucfirst(str_replace('_',' ',$filter_status)) ?></span>
                <?php endif; ?>
            </h5>
            <span class="text-muted" style="font-size:13px">Page <?= $current_pg ?> of <?= $total_pages ?></span>
        </div>

        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Pet</th>
                        <th>Services</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($reservations)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x fs-2 d-block mb-2 text-secondary"></i>
                            No reservations found
                        </td>
                    </tr>
                <?php else: ?>
                <?php foreach ($reservations as $r):
                    $status = $r['status'];
                    $pay    = $r['status_bayar'] ?? 'unpaid';

                    $status_badge = [
                        'pending'     => ['bg-warning text-dark', 'Pending'],
                        'confirmed'   => ['bg-info text-white',   'Confirmed'],
                        'in_progress' => ['bg-primary text-white','In Progress'],
                        'completed'   => ['bg-success text-white','Completed'],
                        'cancelled'   => ['bg-danger text-white', 'Cancelled'],
                    ];
                    $pay_badge = [
                        'paid'    => ['bg-success text-white', 'Paid'],
                        'unpaid'  => ['bg-danger text-white',  'Unpaid'],
                        'partial' => ['bg-warning text-dark',  'Partial'],
                    ];
                    [$sb_cls, $sb_lbl] = $status_badge[$status]  ?? ['bg-secondary text-white', $status];
                    [$pb_cls, $pb_lbl] = $pay_badge[$pay]         ?? ['bg-secondary text-white', $pay];

                    $services_arr = $r['services_list'] ? explode(', ', $r['services_list']) : [];
                    $shown        = array_slice($services_arr, 0, 2);
                    $extra        = count($services_arr) - 2;
                ?>
                <tr>

                    <!-- Customer -->
                    <td>
                        <div class="fw-semibold" style="font-size:13.5px"><?= htmlspecialchars($r['nama_user'] ?? '—') ?></div>
                        <div class="text-muted" style="font-size:11.5px"><?= htmlspecialchars($r['email'] ?? '') ?></div>
                    </td>

                    <!-- Pet -->
                    <td>
                        <div class="fw-semibold" style="font-size:13.5px"><?= htmlspecialchars($r['nama_pet'] ?? '—') ?></div>
                    </td>

                    <!-- Services -->
                    <td>
                        <?php foreach ($shown as $svc): ?>
                            <span class="service-tag"><?= htmlspecialchars($svc) ?></span>
                        <?php endforeach; ?>
                        <?php if ($extra > 0): ?>
                            <span class="service-tag" style="background:#e9ecef;color:#6c757d">+<?= $extra ?> more</span>
                        <?php elseif (empty($services_arr)): ?>
                            <span class="text-muted" style="font-size:12px">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Start -->
                    <td style="font-size:12.5px">
                        <?= $r['waktu_mulai'] ? date('d M Y', strtotime($r['waktu_mulai'])) : '—' ?>
                        <?php if ($r['waktu_mulai']): ?>
                            <div class="text-muted" style="font-size:11px"><?= date('H:i', strtotime($r['waktu_mulai'])) ?></div>
                        <?php endif; ?>
                    </td>

                    <!-- End -->
                    <td style="font-size:12.5px">
                        <?= $r['waktu_selesai'] ? date('d M Y', strtotime($r['waktu_selesai'])) : '—' ?>
                        <?php if ($r['waktu_selesai']): ?>
                            <div class="text-muted" style="font-size:11px"><?= date('H:i', strtotime($r['waktu_selesai'])) ?></div>
                        <?php endif; ?>
                    </td>

                    <!-- Total -->
                    <td>
                        <span class="fw-bold text-success" style="font-size:13px">
                            Rp <?= $r['total_bayar'] ? number_format($r['total_bayar'],0,',','.') : '0' ?>
                        </span>
                    </td>

                    <!-- Status -->
                    <td>
                        <span class="badge <?= $sb_cls ?>" style="font-size:11.5px;padding:5px 9px"><?= $sb_lbl ?></span>
                    </td>

                    <!-- Payment -->
                    <td>
                        <span class="badge <?= $pb_cls ?>" style="font-size:11.5px;padding:5px 9px"><?= $pb_lbl ?></span>
                        <?php if ($r['metode_bayar']): ?>
                            <div class="text-muted" style="font-size:11px;margin-top:2px"><?= htmlspecialchars($r['metode_bayar']) ?></div>
                        <?php endif; ?>
                    </td>

                    <!-- Actions -->
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <!-- View detail -->
                            <button class="btn-action btn-view"
                                onclick="openDetail(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)">
                                <i class="bi bi-eye-fill"></i>
                            </button>

                            <!-- Quick status change -->
                            <?php if ($status === 'pending'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="id_reservation" value="<?= $r['id_reservation'] ?>">
                                    <input type="hidden" name="action" value="confirm">
                                    <button type="submit" class="btn-action btn-confirm" title="Confirm">
                                        <i class="bi bi-check2"></i> Confirm
                                    </button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="id_reservation" value="<?= $r['id_reservation'] ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn-action btn-cancel" title="Cancel"
                                        onclick="return confirm('Cancel this reservation?')">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            <?php elseif ($status === 'confirmed'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="id_reservation" value="<?= $r['id_reservation'] ?>">
                                    <input type="hidden" name="action" value="progress">
                                    <button type="submit" class="btn-action btn-progress" title="Mark In Progress">
                                        <i class="bi bi-play-fill"></i> Start
                                    </button>
                                </form>
                            <?php elseif ($status === 'in_progress'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="id_reservation" value="<?= $r['id_reservation'] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit" class="btn-action btn-complete" title="Mark Completed">
                                        <i class="bi bi-check2-all"></i> Done
                                    </button>
                                </form>
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
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top" style="background:#fafafa">
            <span class="text-muted" style="font-size:13px">
                Showing <?= ($offset+1) ?>–<?= min($offset+$per_page, $total_rows) ?> of <?= $total_rows ?>
            </span>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $current_pg<=1?'disabled':'' ?>">
                        <a class="page-link" href="<?= pg_qs($current_pg-1,$filter_status,$filter_payment,$search) ?>">
                            <i class="bi bi-chevron-left" style="font-size:11px"></i>
                        </a>
                    </li>
                    <?php
                    $from = max(1, $current_pg - 2);
                    $to   = min($total_pages, $current_pg + 2);
                    if ($from > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    for ($pg = $from; $pg <= $to; $pg++):
                    ?>
                        <li class="page-item <?= $pg===$current_pg?'active':'' ?>">
                            <a class="page-link" href="<?= pg_qs($pg,$filter_status,$filter_payment,$search) ?>"><?= $pg ?></a>
                        </li>
                    <?php endfor;
                    if ($to < $total_pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    ?>
                    <li class="page-item <?= $current_pg>=$total_pages?'disabled':'' ?>">
                        <a class="page-link" href="<?= pg_qs($current_pg+1,$filter_status,$filter_payment,$search) ?>">
                            <i class="bi bi-chevron-right" style="font-size:11px"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

    </div><!-- /table-card -->

</div><!-- /main-content -->

<!-- ═══════════════════════════ DETAIL MODAL ═══════════════════════════════ -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="modal-title">Reservation Detail</h5>
                    <p class="text-muted mb-0" id="modal-subtitle" style="font-size:13px"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Two column: Info + Payment -->
                <div class="row g-4">
                    <div class="col-md-6">
                        <p class="fw-bold text-muted mb-3" style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">
                            Customer & Pet
                        </p>
                        <div id="modal-customer"></div>
                    </div>
                    <div class="col-md-6">
                        <p class="fw-bold text-muted mb-3" style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">
                            Payment
                        </p>
                        <div id="modal-payment"></div>
                    </div>
                </div>

                <hr class="my-3">

                <!-- Services breakdown -->
                <p class="fw-bold text-muted mb-3" style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">
                   Services Ordered
                </p>
                <div id="modal-services"></div>

                <!-- Notes -->
                <div id="modal-notes-wrap" class="mt-3" style="display:none">
                    <p class="fw-bold text-muted mb-2" style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">
                        <i class="bi bi-chat-left-text me-1"></i> Notes
                    </p>
                    <div id="modal-notes" class="p-3" style="background:#f8f9fa;border-radius:10px;font-size:13.5px"></div>
                </div>

            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:16px 24px">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:10px">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function fmt(date) {
    if (!date) return '—';
    const d = new Date(date);
    return d.toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'})
           + ' ' + d.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'});
}

function statusBadge(s) {
    const map = {
        pending:     ['bg-warning text-dark', 'Pending'],
        confirmed:   ['bg-info text-white',   'Confirmed'],
        in_progress: ['bg-primary text-white','In Progress'],
        completed:   ['bg-success text-white','Completed'],
        cancelled:   ['bg-danger text-white', 'Cancelled'],
    };
    const [cls, lbl] = map[s] || ['bg-secondary text-white', s];
    return `<span class="badge ${cls}" style="font-size:12px;padding:5px 10px">${lbl}</span>`;
}

function payBadge(s) {
    const map = {
        paid:    ['bg-success text-white','Paid'],
        unpaid:  ['bg-danger text-white', 'Unpaid'],
        partial: ['bg-warning text-dark', 'Partial'],
    };
    const [cls, lbl] = map[s] || ['bg-secondary text-white', s];
    return `<span class="badge ${cls}" style="font-size:12px;padding:5px 10px">${lbl}</span>`;
}

function row(label, value) {
    return `<div class="detail-row"><span class="detail-label">${label}</span><span class="detail-value">${value}</span></div>`;
}

function openDetail(r) {
    const modal = new bootstrap.Modal(document.getElementById('detailModal'));

    document.getElementById('modal-title').textContent =
        'Reservation Details';
    document.getElementById('modal-subtitle').textContent =
        'Created: ' + fmt(r.created_at);

    // Customer & Pet
    document.getElementById('modal-customer').innerHTML =
        row('Customer',   r.nama_user  || '—') +
        row('Email',      r.email      || '—') +
        row('Pet',        r.nama_pet   || '—') +
        row('Start',      fmt(r.waktu_mulai)) +
        row('End',        fmt(r.waktu_selesai)) +
        row('Status',     statusBadge(r.status));

    // Payment
    const total = r.total_bayar
        ? 'Rp ' + Number(r.total_bayar).toLocaleString('id-ID')
        : 'Rp 0';
    document.getElementById('modal-payment').innerHTML =
        row('Total',    `<strong class="text-success" style="font-size:15px">${total}</strong>`) +
        row('Status',   payBadge(r.status_bayar || 'unpaid')) +
        row('Method',   r.metode_bayar || '—');

    // Services
    const svcEl = document.getElementById('modal-services');
    if (r.services_list) {
        const svcs      = r.services_list.split(', ');
        const subtotals = r.subtotals_list ? r.subtotals_list.split(',') : [];
        let html = '<div class="table-responsive"><table class="table table-sm mb-0" style="font-size:13.5px"><thead><tr><th style="padding:8px 12px;font-weight:600;color:#6c757d;font-size:11px;text-transform:uppercase">Service</th><th style="padding:8px 12px;font-weight:600;color:#6c757d;font-size:11px;text-transform:uppercase;text-align:right">Subtotal</th></tr></thead><tbody>';
        svcs.forEach((svc, i) => {
            const sub = subtotals[i]
                ? 'Rp ' + Number(subtotals[i]).toLocaleString('id-ID')
                : '—';
            html += `<tr><td style="padding:10px 12px"><span class="service-tag">${svc.trim()}</span></td><td style="padding:10px 12px;text-align:right;font-weight:600;color:#198754">${sub}</td></tr>`;
        });
        html += '</tbody></table></div>';
        svcEl.innerHTML = html;
    } else {
        svcEl.innerHTML = '<p class="text-muted" style="font-size:13px">No services recorded.</p>';
    }

    // Notes
    const notesWrap = document.getElementById('modal-notes-wrap');
    if (r.catatan) {
        document.getElementById('modal-notes').textContent = r.catatan;
        notesWrap.style.display = '';
    } else {
        notesWrap.style.display = 'none';
    }

    modal.show();
}
</script>
</body>
</html>