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

// ── Handle AJAX confirm payment ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_confirm_payment'])) {
    header('Content-Type: application/json');
    $id_payment = intval($_POST['id_payment'] ?? 0);
    if ($id_payment > 0) {
        $result = mysqli_query($conn, "UPDATE payments SET status_bayar='paid', tgl_bayar=NOW() WHERE id_payment=$id_payment");
        echo json_encode($result ? ['success' => true] : ['success' => false, 'message' => mysqli_error($conn)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    }
    exit;
}

// ── Sidebar badge ──────────────────────────────────────────────────────────
$pending = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='pending'");

// ── Stat cards ─────────────────────────────────────────────────────────────
$total_paid    = db_val($conn, "SELECT COALESCE(SUM(total_bayar),0) FROM payments WHERE status_bayar='paid'");
$total_unpaid  = db_val($conn, "SELECT COALESCE(SUM(total_bayar),0) FROM payments WHERE status_bayar='unpaid'");
$total_partial = db_val($conn, "SELECT COALESCE(SUM(total_bayar),0) FROM payments WHERE status_bayar='partial'");
$count_paid    = db_val($conn, "SELECT COUNT(*) FROM payments WHERE status_bayar='paid'");
$count_unpaid  = db_val($conn, "SELECT COUNT(*) FROM payments WHERE status_bayar='unpaid'");
$count_partial = db_val($conn, "SELECT COUNT(*) FROM payments WHERE status_bayar='partial'");
$total_all     = db_val($conn, "SELECT COUNT(*) FROM payments");

// ── Method breakdown ───────────────────────────────────────────────────────
$methods = db_rows($conn, "
    SELECT metode_bayar, COUNT(*) AS cnt, COALESCE(SUM(total_bayar),0) AS total
    FROM payments GROUP BY metode_bayar ORDER BY cnt DESC
");

// ── Yearly revenue (current year, per month) ──────────────────────────────
$monthly_raw = db_rows($conn, "
    SELECT MONTH(tgl_bayar) AS bulan_angka,
           DATE_FORMAT(tgl_bayar, '%b') AS bulan_label,
           COALESCE(SUM(total_bayar), 0) AS total
    FROM payments
    WHERE status_bayar = 'paid'
      AND YEAR(tgl_bayar) = YEAR(CURDATE())
    GROUP BY MONTH(tgl_bayar), DATE_FORMAT(tgl_bayar, '%b')
    ORDER BY MONTH(tgl_bayar)
");

$raw_by_month = [];
foreach ($monthly_raw as $r) $raw_by_month[(int)$r['bulan_angka']] = floatval($r['total']);

$month_names = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthly = [];
for ($m = 1; $m <= 12; $m++) {
    $monthly[] = [
        'bulan' => $month_names[$m - 1],
        'total' => $raw_by_month[$m] ?? 0
    ];
}

// ── Recent payments (with filters) ────────────────────────────────────────
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

$total_rows  = db_val($conn, "SELECT COUNT(*) FROM payments pay LEFT JOIN reservations r ON pay.id_reservation=r.id_reservation LEFT JOIN user u ON r.id_user=u.id_user WHERE $where_sql");
$total_pages = max(1, ceil($total_rows / $per_page));
$offset      = ($page - 1) * $per_page;

$payments = db_rows($conn, "
    SELECT pay.id_payment, pay.id_reservation,
           CONCAT(u.nama_depan,' ',u.nama_belakang) AS nama_user,
           u.email,
           p.nama_pet,
           pay.total_bayar, pay.status_bayar, pay.metode_bayar,
           pay.tgl_bayar AS tanggal_bayar,
           r.status AS status_reservasi,
           r.waktu_mulai, r.waktu_selesai, r.catatan, r.created_at,
           (
               SELECT GROUP_CONCAT(s.nama_service SEPARATOR ', ')
               FROM reservation_details rd
               JOIN services s ON rd.id_service = s.id_service
               WHERE rd.id_reservation = pay.id_reservation
           ) AS services_list,
           (
               SELECT GROUP_CONCAT(rd.subtotal SEPARATOR ',')
               FROM reservation_details rd
               WHERE rd.id_reservation = pay.id_reservation
           ) AS subtotals_list
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

$method_icon_map = [
    'cash'            => ['bi-cash-coin',             '#16a34a'],
    'transfer'        => ['bi-bank2',                 '#2563eb'],
    'bank_transfer'   => ['bi-bank2',                 '#2563eb'],
    'credit_card'     => ['bi-credit-card-2-front',   '#7c3aed'],
    'debit_card'      => ['bi-credit-card',           '#0891b2'],
    'qris'            => ['bi-qr-code-scan',          '#dc2626'],
    'qr'              => ['bi-qr-code-scan',          '#dc2626'],
    'ovo'             => ['bi-phone',                 '#4f46e5'],
    'gopay'           => ['bi-phone-fill',            '#00aa5b'],
    'dana'            => ['bi-wallet-fill',            '#1d4ed8'],
    'shopeepay'       => ['bi-bag-fill',              '#ee4d2d'],
    'virtual_account' => ['bi-building',              '#0e7490'],
];
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
/* ── Base ── */
body { background:#f4f7f6; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; margin:0; padding:0; }

/* ── Sidebar ── */
.sidebar {
    width:260px; position:fixed; top:0; left:0; height:100vh;
    overflow-y:auto; z-index:1050; background:#2c3e50;
    color:#fff; box-shadow:4px 0 10px rgba(0,0,0,.05);
    transition:transform .3s ease;
}
.sidebar-brand { font-size:1.25rem; letter-spacing:1px; border-bottom:1px solid rgba(255,255,255,.1); }
.sidebar a {
    color:#aeb6bf; text-decoration:none; padding:12px 20px;
    display:flex; align-items:center; border-radius:10px;
    margin-bottom:8px; transition:all .3s ease; font-weight:500;
}
.sidebar a:hover, .sidebar a.active { background:#3498db; color:#fff; transform:translateX(5px); }
.sidebar a.logout-link:hover { background:rgba(231,76,60,.2); color:#e74c3c; transform:none; }

/* ── Sidebar overlay ── */
.sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1040; }
.sidebar-overlay.show { display:block; }

/* ── Topbar (mobile only) ── */
.topbar {
    display:none;
    position:sticky; top:0; z-index:1030;
    background:#2c3e50; color:#fff;
    padding:12px 16px;
    align-items:center; justify-content:space-between;
    box-shadow:0 2px 8px rgba(0,0,0,.15);
}
.topbar-brand { font-size:1rem; font-weight:700; letter-spacing:.5px; }
.topbar-right  { display:flex; align-items:center; gap:12px; }
.btn-hamburger {
    background:none; border:none; color:#fff;
    font-size:1.4rem; cursor:pointer; padding:4px;
    display:flex; align-items:center; transition:opacity .2s;
}
.btn-hamburger:hover { opacity:.8; }

/* ── Layout ── */
.main-content { margin-left:260px; width:calc(100% - 260px); min-height:100vh; }

/* ── Stat cards ── */
.stat-card { border-radius:15px; border:none; transition:transform .3s,box-shadow .3s; }
.stat-card:hover { transform:translateY(-5px); box-shadow:0 12px 20px rgba(0,0,0,.08)!important; }
.icon-box { border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

/* ── Chart / table wrappers ── */
.chart-container { border-radius:15px; background:#fff; box-shadow:0 5px 20px rgba(0,0,0,.03); padding:30px; }
.table-card { border-radius:15px; background:#fff; box-shadow:0 5px 20px rgba(0,0,0,.03); overflow:hidden; }
.table-card-header { padding:18px 24px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
.table>thead>tr>th { font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#6c757d; background:#f8f9fa; border-bottom:1px solid #e9ecef; padding:12px 16px; white-space:nowrap; }
.table>tbody>tr>td { padding:12px 16px; vertical-align:middle; font-size:14px; border-bottom:1px solid #f5f5f5; }
.table>tbody>tr:last-child>td { border-bottom:none; }
.table>tbody>tr:hover>td { background:#f8f9fa; }

/* ── Status chips ── */
.status-row-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
.status-chip { background:#fff; border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:14px; box-shadow:0 2px 10px rgba(0,0,0,.04); }
.status-dot { width:12px; height:12px; border-radius:50%; flex-shrink:0; }

/* ── Method items ── */
.method-item { display:flex; align-items:center; gap:12px; padding:14px 20px; border-bottom:1px solid #f5f5f5; transition:background .15s; }
.method-item:last-child { border-bottom:none; }
.method-item:hover { background:#f8f9fa; }
.method-rank { width:28px; height:28px; border-radius:8px; background:#f1f3f4; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#6c757d; flex-shrink:0; }
.progress { height:5px; background:#f0f0f0; border-radius:4px; margin-top:5px; }
.progress-bar { height:100%; border-radius:4px; background:linear-gradient(90deg,#3498db,#74b9e8); }

/* ── Filter bar ── */
.filter-bar { background:#fff; border-radius:15px; padding:16px 20px; box-shadow:0 2px 10px rgba(0,0,0,.04); margin-bottom:20px; }
.filter-bar .form-control, .filter-bar .form-select { border-radius:10px; border:1.5px solid #e9ecef; font-size:13px; }
.filter-bar .form-control:focus, .filter-bar .form-select:focus { border-color:#3498db; box-shadow:0 0 0 3px rgba(52,152,219,.1); }
.btn-filter { border-radius:10px; font-size:13px; font-weight:500; padding:8px 18px; }

/* ── Service tags ── */
.service-tag { display:inline-block; background:#eaf4fd; color:#2980b9; border-radius:6px; padding:2px 8px; font-size:11.5px; font-weight:600; margin:2px 2px 2px 0; white-space:nowrap; }

/* ── Pagination ── */
.pagination .page-link { border-radius:8px!important; margin:0 2px; border:none; color:#3498db; font-size:13px; }
.pagination .page-item.active .page-link { background:#3498db; color:#fff; }

/* ── Mobile payment cards ── */
.pay-mobile-card {
    background:#fff; border-radius:12px; padding:14px 16px;
    margin-bottom:10px; box-shadow:0 2px 8px rgba(0,0,0,.05);
    border:1px solid #f0f0f0;
}
.pay-mobile-card .pm-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:4px; }
.pay-mobile-card .pm-name { font-size:14px; font-weight:600; color:#1a1a2e; }
.pay-mobile-card .pm-meta { font-size:12px; color:#6c757d; margin-bottom:8px; }
.pay-mobile-card .pm-amount { font-size:15px; font-weight:700; color:#198754; }
.pay-mobile-card .pm-badges { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px; }
.pay-mobile-card .pm-actions { display:flex; gap:6px; }
.btn-action { padding:5px 12px; font-size:12px; border-radius:8px; font-weight:600; border:none; cursor:pointer; transition:opacity .2s; display:inline-flex; align-items:center; gap:4px; }
.btn-action:hover { opacity:.8; }
.btn-view  { background:#eaf4fd; color:#2980b9; }
.btn-check { background:#e8f8f0; color:#16a34a; }

/* ── Detail Modal ── */
.modal-content { border-radius:18px; border:none; }
.modal-header  { border-bottom:1px solid #f0f0f0; padding:20px 24px; }
.modal-body    { padding:24px; }
.detail-row { display:flex; gap:8px; padding:10px 0; border-bottom:1px solid #f5f5f5; font-size:13.5px; }
.detail-row:last-child { border-bottom:none; }
.detail-label { width:140px; flex-shrink:0; color:#6c757d; font-weight:600; }
.detail-value { color:#1a1a2e; }

/* ── Payment Confirm Modal ── */
.confirm-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.5); backdrop-filter:blur(4px);
    z-index:9999; align-items:center; justify-content:center; padding:16px;
}
.confirm-overlay.show { display:flex; }
.confirm-modal {
    background:#fff; border-radius:20px; padding:36px 32px;
    width:420px; max-width:92vw;
    box-shadow:0 20px 60px rgba(0,0,0,.2);
    animation:modalIn .25s ease;
}
@keyframes modalIn { from{transform:scale(.9) translateY(10px);opacity:0} to{transform:scale(1) translateY(0);opacity:1} }
.confirm-modal .c-icon { width:64px; height:64px; border-radius:50%; background:rgba(34,197,94,.12); display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:30px; color:#16a34a; }
.confirm-modal h5 { text-align:center; font-weight:700; font-size:18px; margin-bottom:8px; }
.confirm-modal .c-sub { text-align:center; color:#6c757d; font-size:13px; margin-bottom:22px; }
.confirm-modal .c-box { background:#f8f9fa; border-radius:12px; padding:16px 18px; margin-bottom:24px; }
.confirm-modal .c-row { display:flex; justify-content:space-between; align-items:center; font-size:13px; margin-bottom:8px; }
.confirm-modal .c-row:last-child { margin-bottom:0; }
.confirm-modal .c-lbl { color:#6c757d; font-weight:500; }
.confirm-modal .c-val { font-weight:700; color:#1a1a2e; }
.confirm-modal .c-val.price { color:#16a34a; font-size:15px; }
.btn-yes { background:linear-gradient(135deg,#16a34a,#22c55e); color:#fff; border:none; border-radius:12px; padding:12px 0; width:100%; font-weight:600; font-size:15px; cursor:pointer; transition:all .2s; margin-bottom:10px; }
.btn-yes:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(34,197,94,.35); }
.btn-yes:disabled { opacity:.7; transform:none; cursor:not-allowed; }
.btn-no { background:#f1f3f4; color:#6c757d; border:none; border-radius:12px; padding:11px 0; width:100%; font-weight:600; font-size:14px; cursor:pointer; transition:background .2s; }
.btn-no:hover { background:#e2e6ea; }

/* ── Logout modal ── */
.logout-icon-wrap { width:64px; height:64px; border-radius:18px; background:rgba(231,76,60,.1); display:flex; align-items:center; justify-content:center; font-size:28px; color:#e74c3c; margin:0 auto 6px; }
.logout-modal-error { background:#fdecea; border:1.5px solid #f5c6c6; border-radius:10px; padding:10px 14px; font-size:13px; color:#922b21; display:flex; align-items:center; gap:8px; margin-bottom:16px; }
.pw-wrapper { position:relative; }
.pw-wrapper .form-control { padding-right:50px; }
.pw-toggle { position:absolute; right:0; top:0; bottom:0; width:50px; border:none; background:none; display:flex; align-items:center; justify-content:center; color:#6c757d; cursor:pointer; font-size:16px; transition:color .2s; }
.pw-toggle:hover { color:#495057; }

/* ── Toast ── */
.toast-notif { position:fixed; top:24px; right:24px; background:#16a34a; color:#fff; border-radius:12px; padding:14px 20px; font-size:14px; font-weight:600; display:flex; align-items:center; gap:10px; box-shadow:0 8px 24px rgba(0,0,0,.15); z-index:99999; opacity:0; transform:translateX(40px); transition:all .3s ease; pointer-events:none; }
.toast-notif.show { opacity:1; transform:translateX(0); }

/* ════════════════════════════════
   RESPONSIVE
════════════════════════════════ */
@media (max-width:992px) {
    .sidebar { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0); }
    .main-content { margin-left:0; width:100%; }
    .topbar { display:flex; }
}

@media (max-width:768px) {
    .main-content { padding:0 !important; }
    .page-inner { padding:16px; }

    /* Stat cards 2 kolom */
    .stat-cards-grid { grid-template-columns:repeat(2,1fr) !important; gap:10px !important; }
    .stat-card .card-body { padding:12px; }
    .stat-card h4 { font-size:1rem !important; }
    .icon-box { width:42px !important; height:42px !important; font-size:20px !important; }

    /* Status chips 3 kolom tetap, tapi compact */
    .status-row-grid { gap:10px; }
    .status-chip { padding:12px 14px; gap:10px; }
    .status-chip > div > div:last-child { font-size:18px !important; }

    /* Chart compact */
    .chart-container { padding:16px; border-radius:12px; }

    /* Filter bar stack */
    .filter-bar { padding:14px; }
    .filter-bar .row > div { margin-bottom:4px; }

    /* Tabel hilang, mobile cards muncul */
    .desktop-table { display:none !important; }
    .mobile-cards  { display:block !important; }

    /* Table card header */
    .table-card-header { padding:14px 16px; }
    .table-card-header h5 { font-size:14px; }

    /* Method items compact */
    .method-item { padding:12px 14px; }
}

@media (max-width:576px) {
    .status-row-grid { grid-template-columns:1fr 1fr; }
}

@media (min-width:769px) {
    .mobile-cards  { display:none !important; }
    .desktop-table { display:block !important; }
}

@media (max-width:480px) {
    .page-inner { padding:12px; }
    .confirm-modal { padding:28px 20px; }
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
    <div class="sidebar-brand text-center py-3 mb-4 fw-bold text-white">CatDogKu Admin</div>

    <a href="admin_dash.php" class="<?= $current_page_file==='admin_dash.php'?'active':'' ?>">
        <i class="bi bi-speedometer2 me-2 fs-5"></i> Dashboard
    </a>

    <div class="text-uppercase text-secondary px-2 mb-1 mt-3" style="font-size:11px;letter-spacing:1px;font-weight:600;">Management</div>
    <a href="admin_reserve.php" class="<?= $current_page_file==='admin_reserve.php'?'active':'' ?>">
        <i class="bi bi-calendar-check me-2 fs-5"></i> Reservations
        <?php if($pending>0): ?><span class="badge bg-danger ms-auto"><?= $pending ?></span><?php endif; ?>
    </a>
    <a href="admin_pay.php" class="<?= $current_page_file==='admin_pay.php'?'active':'' ?>">
        <i class="bi bi-credit-card me-2 fs-5"></i> Payments
    </a>

    <div class="text-uppercase text-secondary px-2 mb-1 mt-3" style="font-size:11px;letter-spacing:1px;font-weight:600;">Master Data</div>
    <a href="admin_user.php" class="<?= $current_page_file==='admin_user.php'?'active':'' ?>"><i class="bi bi-people me-2 fs-5"></i> Users</a>
    <a href="admin_staff.php" class="<?= $current_page_file==='admin_staff.php'?'active':'' ?>"><i class="bi bi-person-badge me-2 fs-5"></i> Staff</a>
    <a href="admin_service.php" class="<?= $current_page_file==='admin_service.php'?'active':'' ?>"><i class="bi bi-stars me-2 fs-5"></i> Services</a>
    <a href="admin_breed.php" class="<?= $current_page_file==='admin_breed.php'?'active':'' ?>"><i class="bi bi-bug me-2 fs-5"></i> Breeds</a>
    <a href="admin_staffschedule.php" class="<?= $current_page_file==='admin_staffschedule.php'?'active':'' ?>"><i class="bi bi-clock me-2 fs-5"></i> Staff Schedules</a>

    <div class="mt-4 pt-3 border-top border-secondary">
        <a href="#" class="logout-link fw-bold" onclick="openLogoutModal(); return false;">
            <i class="bi bi-box-arrow-left me-2 fs-5"></i> Logout
        </a>
    </div>
</div>

<!-- ══ MAIN CONTENT ══ -->
<div class="main-content p-4 p-md-5">
<div class="page-inner">

    <!-- Header desktop -->
    <div class="d-none d-lg-flex justify-content-between align-items-center mb-5">
        <h2 class="fw-bold text-dark mb-0">Payments</h2>
        <a href="admin_reserve.php?status=pending" class="position-relative text-decoration-none text-secondary">
            <i class="bi bi-bell fs-5"></i>
            <?php if($pending>0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px"><?= $pending ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Header mobile -->
    <div class="d-lg-none mb-3">
        <h4 class="fw-bold text-dark mb-0">Payments</h4>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stat-cards-grid mb-4" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
        <div class="card stat-card shadow-sm h-100 p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">Total Revenue</p>
                    <h4 class="fw-bold mb-0 text-dark" style="font-size:16px">Rp <?= number_format($total_paid,0,',','.') ?></h4>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px"><?= number_format($count_paid) ?> paid</p>
                </div>
                <div class="icon-box bg-success bg-opacity-10 text-success" style="width:50px;height:50px;font-size:22px">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
        </div>
        <div class="card stat-card shadow-sm h-100 p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">Unpaid</p>
                    <h4 class="fw-bold mb-0 text-dark" style="font-size:16px">Rp <?= number_format($total_unpaid,0,',','.') ?></h4>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px"><span class="text-danger fw-semibold"><?= number_format($count_unpaid) ?></span> awaiting</p>
                </div>
                <div class="icon-box bg-danger bg-opacity-10 text-danger" style="width:50px;height:50px;font-size:22px">
                    <i class="bi bi-exclamation-circle"></i>
                </div>
            </div>
        </div>
        <div class="card stat-card shadow-sm h-100 p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">Partial</p>
                    <h4 class="fw-bold mb-0 text-dark" style="font-size:16px">Rp <?= number_format($total_partial,0,',','.') ?></h4>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px"><span class="text-warning fw-semibold"><?= number_format($count_partial) ?></span> partial</p>
                </div>
                <div class="icon-box bg-warning bg-opacity-10" style="color:#d97706;width:50px;height:50px;font-size:22px">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
        </div>
        <div class="card stat-card shadow-sm h-100 p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">Total Transactions</p>
                    <h4 class="fw-bold mb-0 text-dark" style="font-size:16px"><?= number_format($total_all) ?></h4>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">All records</p>
                </div>
                <div class="icon-box bg-primary bg-opacity-10 text-primary" style="width:50px;height:50px;font-size:22px">
                    <i class="bi bi-credit-card"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Status Chips ── -->
    <div class="status-row-grid mb-4">
        <div class="status-chip"><div class="status-dot" style="background:#22c55e"></div><div><div style="font-size:12px;color:#6c757d;font-weight:500">Paid</div><div style="font-size:22px;font-weight:700;line-height:1.2"><?= $count_paid ?></div></div></div>
        <div class="status-chip"><div class="status-dot" style="background:#ef4444"></div><div><div style="font-size:12px;color:#6c757d;font-weight:500">Unpaid</div><div style="font-size:22px;font-weight:700;line-height:1.2"><?= $count_unpaid ?></div></div></div>
        <div class="status-chip"><div class="status-dot" style="background:#f59e0b"></div><div><div style="font-size:12px;color:#6c757d;font-weight:500">Partial</div><div style="font-size:22px;font-weight:700;line-height:1.2"><?= $count_partial ?></div></div></div>
    </div>

    <!-- ── Chart + Method Breakdown ── -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="chart-container h-100">
                <h5 class="fw-bold mb-1 border-bottom pb-3" style="font-size:15px">
                    Yearly Revenue
                    <span class="text-muted fw-normal ms-2" style="font-size:13px"><?= date('Y') ?></span>
                </h5>
                <div style="height:260px"><canvas id="revenueChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="table-card h-100">
                <div class="table-card-header">
                    <h5 class="fw-bold mb-0" style="font-size:15px">Payment Methods</h5>
                </div>
                <?php if(empty($methods)): ?>
                    <div class="text-center text-muted py-4">No data yet</div>
                <?php else:
                    $max_m = max(array_column($methods,'cnt')) ?: 1;
                    foreach($methods as $i => $m):
                        $pct      = round(($m['cnt']/$max_m)*100);
                        $label    = $m['metode_bayar'] ? ucwords(str_replace('_',' ',$m['metode_bayar'])) : 'Unknown';
                        $raw      = strtolower($m['metode_bayar'] ?? '');
                        [$m_icon, $m_color] = $method_icon_map[$raw] ?? ['bi-wallet2','#6c757d'];
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
                    <div style="font-size:12px;font-weight:700;color:#198754;white-space:nowrap;margin-left:8px">Rp <?= number_format($m['total'],0,',','.') ?></div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Filter Bar ── -->
    <div class="filter-bar">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label mb-1" style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.5px">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0" style="border-radius:10px 0 0 10px;border:1.5px solid #e9ecef"><i class="bi bi-search text-muted" style="font-size:13px"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" style="border-radius:0 10px 10px 0" placeholder="Customer name…" value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1" style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.5px">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="paid"    <?= $filter_status==='paid'   ?'selected':'' ?>>Paid</option>
                    <option value="unpaid"  <?= $filter_status==='unpaid' ?'selected':'' ?>>Unpaid</option>
                    <option value="partial" <?= $filter_status==='partial'?'selected':'' ?>>Partial</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label mb-1" style="font-size:12px;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.5px">Method</label>
                <select name="metode" class="form-select">
                    <option value="">All Methods</option>
                    <?php foreach($all_methods as $am): ?>
                        <option value="<?= htmlspecialchars($am['metode_bayar']) ?>" <?= $filter_method===$am['metode_bayar']?'selected':'' ?>>
                            <?= ucwords(str_replace('_',' ',$am['metode_bayar'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-filter flex-fill"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="admin_pay.php" class="btn btn-filter" style="border:1.5px solid #dee2e6;color:#6c757d;background:#fff;display:inline-flex;align-items:center"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>

    <!-- ── Payments Table / Mobile Cards ── -->
    <div class="table-card mb-4">
        <div class="table-card-header">
            <h5 class="fw-bold mb-0" style="font-size:15px">
                Payment Records
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2" style="font-size:12px;font-weight:600"><?= number_format($total_rows) ?></span>
            </h5>
            <span class="text-muted" style="font-size:13px">Page <?= $page ?> of <?= $total_pages ?></span>
        </div>

        <!-- DESKTOP TABLE -->
        <div class="table-responsive desktop-table">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Customer</th><th>Pet</th><th>Method</th>
                        <th>Amount</th><th>Pay Status</th><th>Res. Status</th>
                        <th>Date</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($payments)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No payment records found</td></tr>
                <?php else: ?>
                <?php foreach($payments as $pay):
                    $raw_m = strtolower($pay['metode_bayar'] ?? '');
                    [$tbl_icon,$tbl_color] = $method_icon_map[$raw_m] ?? ['bi-wallet2','#6c757d'];
                    $m_label = $pay['metode_bayar'] ? ucwords(str_replace('_',' ',$pay['metode_bayar'])) : '—';
                    $sp = $pay['status_bayar'] ?? 'unpaid';
                    $pay_map   = ['paid'=>'bg-success text-white','unpaid'=>'bg-danger text-white','partial'=>'bg-warning text-dark'];
                    $pay_label = ['paid'=>'Paid','unpaid'=>'Unpaid','partial'=>'Partial'];
                    $rs = $pay['status_reservasi'] ?? '';
                    $rb = ['pending'=>'bg-warning text-dark','confirmed'=>'bg-info text-white','in_progress'=>'bg-primary text-white','completed'=>'bg-success text-white','cancelled'=>'bg-danger text-white'];
                    $rl = ['pending'=>'Pending','confirmed'=>'Confirmed','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'];
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
                    <td class="fw-bold text-dark">Rp <?= number_format(floatval($pay['total_bayar']??0),0,',','.') ?></td>
                    <td><span class="badge <?= $pay_map[$sp]??'bg-secondary text-white' ?>" style="font-size:11px"><?= $pay_label[$sp]??$sp ?></span></td>
                    <td><span class="badge <?= $rb[$rs]??'bg-secondary text-white' ?>" style="font-size:11px"><?= $rl[$rs]??($rs?:'—') ?></span></td>
                    <td class="text-muted" style="font-size:12px"><?= $pay['tanggal_bayar'] ? date('d M Y H:i',strtotime($pay['tanggal_bayar'])) : '—' ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:12px" title="View Detail"
                                onclick="openDetail(<?= htmlspecialchars(json_encode($pay), ENT_QUOTES) ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if($sp !== 'paid'): ?>
                            <button class="btn btn-sm btn-outline-success" style="border-radius:8px;font-size:12px" title="Mark Paid"
                                onclick="openConfirm(<?= $pay['id_payment'] ?>,'<?= addslashes(htmlspecialchars($pay['nama_user']??'—')) ?>','<?= addslashes(htmlspecialchars($pay['nama_pet']??'—')) ?>','<?= addslashes(htmlspecialchars($m_label)) ?>',<?= floatval($pay['total_bayar']??0) ?>)">
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

        <!-- MOBILE CARDS -->
        <div class="mobile-cards p-3">
            <?php if(empty($payments)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>No payment records found
                </div>
            <?php else: ?>
            <?php foreach($payments as $pay):
                $raw_m = strtolower($pay['metode_bayar'] ?? '');
                [$tbl_icon,$tbl_color] = $method_icon_map[$raw_m] ?? ['bi-wallet2','#6c757d'];
                $m_label = $pay['metode_bayar'] ? ucwords(str_replace('_',' ',$pay['metode_bayar'])) : '—';
                $sp = $pay['status_bayar'] ?? 'unpaid';
                $pay_map   = ['paid'=>'bg-success text-white','unpaid'=>'bg-danger text-white','partial'=>'bg-warning text-dark'];
                $pay_label = ['paid'=>'Paid','unpaid'=>'Unpaid','partial'=>'Partial'];
                $rs = $pay['status_reservasi'] ?? '';
                $rb = ['pending'=>'bg-warning text-dark','confirmed'=>'bg-info text-white','in_progress'=>'bg-primary text-white','completed'=>'bg-success text-white','cancelled'=>'bg-danger text-white'];
                $rl = ['pending'=>'Pending','confirmed'=>'Confirmed','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'];
            ?>
            <div class="pay-mobile-card">
                <div class="pm-top">
                    <div>
                        <div class="pm-name"><?= htmlspecialchars($pay['nama_user'] ?? '—') ?></div>
                        <div class="pm-meta">
                            🐾 <?= htmlspecialchars($pay['nama_pet'] ?? '—') ?>
                            &nbsp;·&nbsp;
                            <i class="bi <?= $tbl_icon ?>" style="color:<?= $tbl_color ?>"></i>
                            <?= htmlspecialchars($m_label) ?>
                        </div>
                    </div>
                    <div class="pm-amount">Rp <?= number_format(floatval($pay['total_bayar']??0),0,',','.') ?></div>
                </div>
                <div class="pm-badges">
                    <span class="badge <?= $pay_map[$sp]??'bg-secondary text-white' ?>" style="font-size:11px"><?= $pay_label[$sp]??$sp ?></span>
                    <span class="badge <?= $rb[$rs]??'bg-secondary text-white' ?>" style="font-size:11px"><?= $rl[$rs]??($rs?:'—') ?></span>
                    <?php if($pay['tanggal_bayar']): ?>
                    <span class="badge bg-light text-muted border" style="font-size:11px"><?= date('d M Y', strtotime($pay['tanggal_bayar'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="pm-actions">
                    <button class="btn-action btn-view" onclick="openDetail(<?= htmlspecialchars(json_encode($pay), ENT_QUOTES) ?>)">
                        <i class="bi bi-eye-fill"></i> Detail
                    </button>
                    <?php if($sp !== 'paid'): ?>
                    <button class="btn-action btn-check" onclick="openConfirm(<?= $pay['id_payment'] ?>,'<?= addslashes(htmlspecialchars($pay['nama_user']??'—')) ?>','<?= addslashes(htmlspecialchars($pay['nama_pet']??'—')) ?>','<?= addslashes(htmlspecialchars($m_label)) ?>',<?= floatval($pay['total_bayar']??0) ?>)">
                        <i class="bi bi-check-circle-fill"></i> Mark Paid
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if($total_pages>1): ?>
        <div class="d-flex justify-content-center py-3 border-top">
            <nav><ul class="pagination mb-0">
                <?php $qs = http_build_query(array_filter(['search'=>$search,'status'=>$filter_status,'metode'=>$filter_method])); ?>
                <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="?<?= $qs ?>&page=<?= $page-1 ?>"><i class="bi bi-chevron-left"></i></a></li>
                <?php for($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
                    <li class="page-item <?= $p==$page?'active':'' ?>"><a class="page-link" href="?<?= $qs ?>&page=<?= $p ?>"><?= $p ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>"><a class="page-link" href="?<?= $qs ?>&page=<?= $page+1 ?>"><i class="bi bi-chevron-right"></i></a></li>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /page-inner -->
</div><!-- /main-content -->

<!-- ══ DETAIL MODAL ══ -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="modal-title">Payment Detail</h5>
                    <p class="text-muted mb-0" id="modal-subtitle" style="font-size:13px"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <p class="fw-bold text-muted mb-3" style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">Customer & Reservation</p>
                        <div id="modal-left"></div>
                    </div>
                    <div class="col-md-6">
                        <p class="fw-bold text-muted mb-3" style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">Payment Info</p>
                        <div id="modal-right"></div>
                    </div>
                </div>
                <hr class="my-3">
                <p class="fw-bold text-muted mb-3" style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">Services Ordered</p>
                <div id="modal-services"></div>
                <div id="modal-notes-wrap" class="mt-3" style="display:none">
                    <p class="fw-bold text-muted mb-2" style="font-size:11px;text-transform:uppercase;letter-spacing:.8px"><i class="bi bi-chat-left-text me-1"></i> Notes</p>
                    <div id="modal-notes" class="p-3" style="background:#f8f9fa;border-radius:10px;font-size:13.5px"></div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:16px 24px">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:10px">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ PAYMENT CONFIRM OVERLAY ══ -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-modal">
        <div class="c-icon"><i class="bi bi-shield-check"></i></div>
        <h5>Confirm Payment</h5>
        <p class="c-sub">Please review details before confirming.</p>
        <div class="c-box">
            <div class="c-row"><span class="c-lbl"><i class="bi bi-person me-1"></i>Customer</span><span class="c-val" id="c_customer">—</span></div>
            <div class="c-row"><span class="c-lbl"><i class="bi bi-heart me-1"></i>Pet</span><span class="c-val" id="c_pet">—</span></div>
            <div class="c-row"><span class="c-lbl"><i class="bi bi-wallet me-1"></i>Method</span><span class="c-val" id="c_method">—</span></div>
            <div class="c-row" style="margin-top:10px;padding-top:10px;border-top:1px solid #e9ecef">
                <span class="c-lbl" style="font-size:14px"><i class="bi bi-cash me-1"></i>Amount</span>
                <span class="c-val price" id="c_amount">Rp 0</span>
            </div>
        </div>
        <button class="btn-yes" id="btnYes" onclick="submitConfirm()"><i class="bi bi-check-circle me-2"></i> Confirm as Paid</button>
        <button class="btn-no" onclick="closeConfirm()">Cancel</button>
    </div>
</div>

<!-- ══ TOAST ══ -->
<div class="toast-notif" id="toastNotif">
    <i class="bi bi-check-circle-fill"></i>
    <span id="toastMsg">Payment confirmed!</span>
</div>

<!-- ══ LOGOUT MODAL ══ -->
<div class="modal fade form-modal" id="logoutModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="clearLogoutForm()"></button>
            </div>
            <form method="POST" action="admin_pay.php" id="logoutForm">
                <input type="hidden" name="confirm_logout" value="1">
                <div class="modal-body pt-2">
                    <div class="text-center mb-4">
                        <div class="logout-icon-wrap"><i class="bi bi-box-arrow-left"></i></div>
                        <h5 class="fw-bold mt-3 mb-1" style="font-size:16px;color:#1a1a2e">Log Out Confirmation</h5>
                        <p class="text-muted mb-0" style="font-size:13px">Enter your password to log out of this session.</p>
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
                            <input type="password" name="logout_password" id="logout-pw" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="pw-toggle" onclick="togglePwVisibility()">
                                <i class="bi bi-eye" id="pw-toggle-icon"></i>
                            </button>
                        </div>
                        <div class="form-text">Confirm your identity before logging out.</div>
                    </div>
                    <hr style="border-color:#f0f0f0;margin:18px 0 16px">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger fw-bold" style="border-radius:10px;font-size:13.5px;padding:10px" onclick="return validateLogout()">Yes, Logout</button>
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal" style="border-radius:8px;font-size:13px" onclick="clearLogoutForm()">Close</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Sidebar ───────────────────────────────────────────────────────────────
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

// ── Chart ─────────────────────────────────────────────────────────────────
const ctx         = document.getElementById('revenueChart').getContext('2d');
const chartLabels = <?= json_encode(array_column($monthly, 'bulan')) ?>;
const chartData   = <?= json_encode(array_column($monthly, 'total')) ?>;

new Chart(ctx, {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Revenue',
            data: chartData,
            backgroundColor: 'rgba(52, 152, 219, 0.15)',
            borderColor: 'rgba(52, 152, 219, 1)',
            borderWidth: 3,
            pointBackgroundColor: '#fff',
            pointBorderColor: 'rgba(52, 152, 219, 1)',
            pointBorderWidth: 2,
            pointRadius: new Array(12).fill(5),
            pointHoverRadius: 7,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true, min: 0, max: 15000000,
                ticks: {
                    stepSize: 1000000, autoSkip: false, maxTicksLimit: 16,
                    font: { size: 11 },
                    callback: v => {
                        if (v === 0) return 'Rp 0';
                        return 'Rp ' + (v / 1000000).toFixed(0) + ' jt';
                    }
                },
                grid: { color: 'rgba(0,0,0,0.04)' }
            },
            x: {
                grid: { display: false },
                ticks: { autoSkip: false, font: { size: 11 } }
            }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(44,62,80,0.9)',
                titleFont: { size: 13 }, bodyFont: { size: 13 },
                padding: 12, cornerRadius: 8, displayColors: false,
                callbacks: {
                    title: items => chartLabels[items[0].dataIndex] + ' ' + new Date().getFullYear(),
                    label: c => ' Rp ' + c.raw.toLocaleString('id-ID')
                }
            }
        }
    }
});

// ── Detail Modal ──────────────────────────────────────────────────────────
function fmt(date) {
    if (!date) return '—';
    const d = new Date(date);
    return d.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})
         + ' ' + d.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'});
}
function statusBadge(s) {
    const map = {
        pending:     ['bg-warning text-dark',  'Pending'],
        confirmed:   ['bg-info text-white',    'Confirmed'],
        in_progress: ['bg-primary text-white', 'In Progress'],
        completed:   ['bg-success text-white', 'Completed'],
        cancelled:   ['bg-danger text-white',  'Cancelled']
    };
    const [cls,lbl] = map[s] || ['bg-secondary text-white', s];
    return `<span class="badge ${cls}" style="font-size:12px;padding:5px 10px">${lbl}</span>`;
}
function payBadge(s) {
    const map = {
        paid:    ['bg-success text-white', 'Paid'],
        unpaid:  ['bg-danger text-white',  'Unpaid'],
        partial: ['bg-warning text-dark',  'Partial']
    };
    const [cls,lbl] = map[s] || ['bg-secondary text-white', s];
    return `<span class="badge ${cls}" style="font-size:12px;padding:5px 10px">${lbl}</span>`;
}
function dRow(label, value) {
    return `<div class="detail-row"><span class="detail-label">${label}</span><span class="detail-value">${value||'—'}</span></div>`;
}

function openDetail(p) {
    document.getElementById('modal-title').textContent    = 'Payment Detail';
    document.getElementById('modal-subtitle').textContent = 'Transaction date: ' + fmt(p.tanggal_bayar);

    document.getElementById('modal-left').innerHTML =
        dRow('Customer',    p.nama_user  || '—') +
        dRow('Email',       p.email      || '—') +
        dRow('Pet',         p.nama_pet   || '—') +
        dRow('Start',       fmt(p.waktu_mulai)) +
        dRow('End',         fmt(p.waktu_selesai)) +
        dRow('Res. Status', statusBadge(p.status_reservasi));

    const total = p.total_bayar ? 'Rp ' + Number(p.total_bayar).toLocaleString('id-ID') : 'Rp 0';
    document.getElementById('modal-right').innerHTML =
        dRow('Total',      `<strong class="text-success" style="font-size:15px">${total}</strong>`) +
        dRow('Pay Status', payBadge(p.status_bayar || 'unpaid')) +
        dRow('Method',     p.metode_bayar ? p.metode_bayar.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()) : '—') +
        dRow('Paid At',    fmt(p.tanggal_bayar));

    const svcEl = document.getElementById('modal-services');
    if (p.services_list) {
        const svcs = p.services_list.split(', ');
        const subs = p.subtotals_list ? p.subtotals_list.split(',') : [];
        let html = '<div class="table-responsive"><table class="table table-sm mb-0" style="font-size:13.5px"><thead><tr>'
                 + '<th style="padding:8px 12px;font-weight:600;color:#6c757d;font-size:11px;text-transform:uppercase">Service</th>'
                 + '<th style="padding:8px 12px;font-weight:600;color:#6c757d;font-size:11px;text-transform:uppercase;text-align:right">Subtotal</th>'
                 + '</tr></thead><tbody>';
        svcs.forEach((svc, i) => {
            const sub = subs[i] ? 'Rp ' + Number(subs[i]).toLocaleString('id-ID') : '—';
            html += `<tr><td style="padding:10px 12px"><span class="service-tag">${svc.trim()}</span></td>`
                  + `<td style="padding:10px 12px;text-align:right;font-weight:600;color:#198754">${sub}</td></tr>`;
        });
        html += '</tbody></table></div>';
        svcEl.innerHTML = html;
    } else {
        svcEl.innerHTML = '<p class="text-muted" style="font-size:13px">No services recorded.</p>';
    }

    const nw = document.getElementById('modal-notes-wrap');
    if (p.catatan) { document.getElementById('modal-notes').textContent = p.catatan; nw.style.display = ''; }
    else nw.style.display = 'none';

    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

// ── Confirm Payment ───────────────────────────────────────────────────────
let _pid = null;
function openConfirm(id, customer, pet, method, amount) {
    _pid = id;
    document.getElementById('c_customer').textContent = customer;
    document.getElementById('c_pet').textContent      = pet;
    document.getElementById('c_method').textContent   = method;
    document.getElementById('c_amount').textContent   = 'Rp ' + amount.toLocaleString('id-ID');
    document.getElementById('confirmOverlay').classList.add('show');
}
function closeConfirm() { document.getElementById('confirmOverlay').classList.remove('show'); _pid = null; }
document.getElementById('confirmOverlay').addEventListener('click', e => { if (e.target === e.currentTarget) closeConfirm(); });

function submitConfirm() {
    if (!_pid) return;
    const btn = document.getElementById('btnYes');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Confirming…';
    const fd = new FormData();
    fd.append('ajax_confirm_payment', '1');
    fd.append('id_payment', _pid);
    fetch(window.location.pathname, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeConfirm();
                showToast('Payment #' + String(_pid).padStart(4,'0') + ' confirmed as Paid!');
                setTimeout(() => location.reload(), 1400);
            } else {
                alert('Error: ' + (d.message || 'Failed'));
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle me-2"></i> Confirm as Paid';
            }
        })
        .catch(() => {
            alert('Network error');
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

// ── Logout ────────────────────────────────────────────────────────────────
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
    if (pw === '') {
        document.getElementById('logout-js-error-msg').textContent = 'Password cannot be empty.';
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
    if (input.type === 'password') { input.type = 'text';     icon.className = 'bi bi-eye-slash'; }
    else                           { input.type = 'password'; icon.className = 'bi bi-eye'; }
}
<?php if(!empty($logout_error)): ?>
window.addEventListener('DOMContentLoaded', () => { new bootstrap.Modal(document.getElementById('logoutModal')).show(); });
<?php endif; ?>
</script>
</body>
</html>