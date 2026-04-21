<?php
session_start();
require_once 'config/koneksi.php';

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

// ── Handle ADD SERVICE (POST) ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $nama_service      = mysqli_real_escape_string($conn, trim($_POST['nama_service']      ?? ''));
    $deskripsi         = mysqli_real_escape_string($conn, trim($_POST['deskripsi']         ?? ''));
    $harga_base        = (float)str_replace(['.', ','], ['', '.'], $_POST['harga_base']    ?? 0);
    $durasi_estimasi   = $_POST['durasi_estimasi'] !== '' ? (int)$_POST['durasi_estimasi'] : 'NULL';
    $id_specialization = (int)($_POST['id_specialization'] ?? 0);

    if ($nama_service !== '' && $harga_base >= 0) {
        $spec_val = $id_specialization > 0 ? $id_specialization : 'NULL';
        $dur_val  = $durasi_estimasi === 'NULL' ? 'NULL' : (int)$durasi_estimasi;
        mysqli_query($conn,
            "INSERT INTO services (nama_service, deskripsi, harga_base, durasi_estimasi, id_specialization)
             VALUES ('$nama_service', '$deskripsi', $harga_base, $dur_val, $spec_val)"
        );
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter([
            'q'     => $_GET['q']    ?? null,
            'spec'  => $_GET['spec'] ?? null,
            'page'  => $_GET['page'] ?? null,
            'added' => 1,
        ])));
        exit;
    }
}

// ── Handle EDIT SERVICE (POST) ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_service'])) {
    $id_service        = (int)$_POST['id_service'];
    $nama_service      = mysqli_real_escape_string($conn, trim($_POST['nama_service']      ?? ''));
    $deskripsi         = mysqli_real_escape_string($conn, trim($_POST['deskripsi']         ?? ''));
    $harga_base        = (float)str_replace(['.', ','], ['', '.'], $_POST['harga_base']    ?? 0);
    $durasi_estimasi   = $_POST['durasi_estimasi'] !== '' ? (int)$_POST['durasi_estimasi'] : 'NULL';
    $id_specialization = (int)($_POST['id_specialization'] ?? 0);

    if ($id_service > 0 && $nama_service !== '') {
        $spec_val = $id_specialization > 0 ? $id_specialization : 'NULL';
        $dur_val  = $durasi_estimasi === 'NULL' ? 'NULL' : (int)$durasi_estimasi;
        mysqli_query($conn,
            "UPDATE services
             SET nama_service='$nama_service', deskripsi='$deskripsi',
                 harga_base=$harga_base, durasi_estimasi=$dur_val,
                 id_specialization=$spec_val
             WHERE id_service=$id_service"
        );
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter([
            'q'      => $_GET['q']    ?? null,
            'spec'   => $_GET['spec'] ?? null,
            'page'   => $_GET['page'] ?? null,
            'edited' => 1,
        ])));
        exit;
    }
}

// ── Handle DELETE SERVICE (POST) ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service'])) {
    $id = (int)$_POST['id_service'];
    mysqli_query($conn, "DELETE FROM services WHERE id_service=$id");
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter([
        'q'       => $_GET['q']    ?? null,
        'spec'    => $_GET['spec'] ?? null,
        'page'    => $_GET['page'] ?? null,
        'deleted' => 1,
    ])));
    exit;
}

// ── Filters ───────────────────────────────────────────────────────────────────
$search      = isset($_GET['q'])    && $_GET['q']    !== '' ? trim($_GET['q'])    : null;
$filter_spec = isset($_GET['spec']) && $_GET['spec'] !== '' ? (int)$_GET['spec'] : null;

$where_parts = ['1=1'];
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where_parts[] = "(sv.nama_service LIKE '%$s%' OR sv.deskripsi LIKE '%$s%')";
}
if ($filter_spec) {
    $where_parts[] = "sv.id_specialization = $filter_spec";
}
$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

// ── Pagination ────────────────────────────────────────────────────────────────
$per_page   = 10;
$current_pg = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($current_pg - 1) * $per_page;

$total_rows  = db_val($conn, "SELECT COUNT(*) FROM services sv $where_sql");
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// ── Service list ──────────────────────────────────────────────────────────────
$services = db_rows($conn, "
    SELECT
        sv.id_service,
        sv.nama_service,
        sv.deskripsi,
        sv.harga_base,
        sv.durasi_estimasi,
        sv.id_specialization,
        sp.nama_specialization,
        COUNT(DISTINCT rd.id_reservation) AS total_used
    FROM services sv
    LEFT JOIN specializations sp ON sv.id_specialization = sp.id_specialization
    LEFT JOIN reservation_details rd ON sv.id_service = rd.id_service
    $where_sql
    GROUP BY sv.id_service
    ORDER BY sv.nama_service ASC
    LIMIT $per_page OFFSET $offset
");

// ── Summary stats ─────────────────────────────────────────────────────────────
$stat_total   = db_val($conn, "SELECT COUNT(*) FROM services");
$stat_avg     = db_val($conn, "SELECT COALESCE(AVG(harga_base),0) FROM services");
$stat_no_spec = db_val($conn, "SELECT COUNT(*) FROM services WHERE id_specialization IS NULL");

// ── All specializations for dropdown ─────────────────────────────────────────
$all_specs = db_rows($conn, "SELECT id_specialization, nama_specialization FROM specializations ORDER BY nama_specialization");

$pending           = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='pending'");
$current_page_file = basename($_SERVER['PHP_SELF']);

function pg_qs($page, $search, $filter_spec) {
    return '?' . http_build_query(array_filter([
        'q'    => $search,
        'spec' => $filter_spec,
        'page' => $page,
    ]));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Services — CatDogKu Admin</title>
<link rel="icon" type="image/png" href="assets/icon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
/* ── Base ── */
body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }

/* ── Sidebar ── */
.sidebar {
    width: 260px; position: fixed; top: 0; left: 0;
    height: 100vh; overflow-y: auto; z-index: 1050;
    background-color: #2c3e50; color: #fff;
    box-shadow: 4px 0 10px rgba(0,0,0,.05);
    transition: transform .3s ease;
}
.sidebar-brand { font-size: 1.25rem; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,.1); }
.sidebar a {
    color: #aeb6bf; text-decoration: none; padding: 12px 20px;
    display: flex; align-items: center; border-radius: 10px;
    margin-bottom: 8px; transition: all .3s ease; font-weight: 500;
}
.sidebar a:hover, .sidebar a.active { background-color: #3498db; color: #fff; transform: translateX(5px); }
.sidebar a.logout-link:hover { background-color: rgba(231,76,60,.2); color: #e74c3c; transform: none; }

/* ── Sidebar overlay ── */
.sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1040; }
.sidebar-overlay.show { display: block; }

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
.main-content { margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; }

/* ── Stat Cards ── */
.stat-card { border-radius: 15px; border: none; transition: transform .3s ease, box-shadow .3s ease; cursor: default; }
.stat-card:hover { transform: translateY(-5px); box-shadow: 0 12px 20px rgba(0,0,0,.08) !important; }
.icon-box { width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 26px; flex-shrink: 0; }

/* ── Stats grid ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

/* ── Toolbar ── */
.toolbar { background: #fff; border-radius: 14px; padding: 18px 22px; box-shadow: 0 2px 10px rgba(0,0,0,.04); display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.toolbar .form-control, .toolbar .form-select { border-radius: 10px; border: 1.5px solid #e9ecef; font-size: 13px; height: 40px; }
.toolbar .form-control:focus, .toolbar .form-select:focus { border-color: #3498db; box-shadow: 0 0 0 3px rgba(52,152,219,.12); }
.btn-filter { height: 40px; border-radius: 10px; font-size: 13px; font-weight: 600; padding: 0 18px; display: inline-flex; align-items: center; gap: 4px; }

/* ── Table card ── */
.table-card { background: #fff; border-radius: 16px; box-shadow: 0 5px 20px rgba(0,0,0,.04); overflow: hidden; }
.table-card-header { padding: 18px 24px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.table > thead > tr > th { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #6c757d; background: #f8f9fa; border-bottom: 1px solid #e9ecef; padding: 13px 16px; white-space: nowrap; }
.table > tbody > tr > td { padding: 13px 16px; vertical-align: middle; font-size: 13.5px; border-bottom: 1px solid #f5f5f5; }
.table > tbody > tr:last-child > td { border-bottom: none; }
.table > tbody > tr:hover > td { background: #f8f9fa; }

/* ── Mobile service cards ── */
.svc-mobile-card { background: #fff; border-radius: 12px; padding: 14px 16px; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.05); border: 1px solid #f0f0f0; }
.svc-mobile-card .card-title  { font-size: 14px; font-weight: 600; color: #1a1a2e; margin-bottom: 2px; }
.svc-mobile-card .card-sub    { font-size: 12px; color: #6c757d; margin-bottom: 8px; }
.svc-mobile-card .card-actions{ display: flex; gap: 6px; flex-wrap: wrap; }

/* ── Service icon ── */
.svc-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; background: linear-gradient(135deg, #3498db22, #3498db44); color: #2980b9; }

/* ── Specialization tag ── */
.spec-tag { display: inline-block; background: #f0eafd; color: #6d28d9; border-radius: 6px; padding: 2px 9px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }

/* ── Action buttons ── */
.btn-action { padding: 5px 12px; font-size: 12px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; transition: opacity .2s; display: inline-flex; align-items: center; gap: 4px; }
.btn-action:hover { opacity: .8; }
.btn-view   { background: #eaf4fd; color: #2980b9; }
.btn-edit   { background: #fef9e7; color: #b7770d; }
.btn-delete { background: #fdecea; color: #922b21; }

/* ── Pagination ── */
.pagination .page-link { border-radius: 8px !important; margin: 0 2px; font-size: 13px; font-weight: 600; color: #3498db; border: 1.5px solid #e9ecef; }
.pagination .page-item.active .page-link { background: #3498db; border-color: #3498db; color: #fff; }
.pagination .page-link:hover { background: #eaf4fd; color: #2980b9; }

/* ── Modal base ── */
.modal-content { border-radius: 18px; border: none; }
.modal-header  { border-bottom: 1px solid #f0f0f0; padding: 20px 24px; }
.modal-body    { padding: 24px; }
.modal-footer  { border-top: 1px solid #f0f0f0; padding: 16px 24px; }

/* ── Form modal ── */
.form-modal .modal-body label { font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; }
.form-modal .form-control,
.form-modal .form-select { border-radius: 10px; border: 1.5px solid #e9ecef; font-size: 13.5px; padding: 10px 14px; }
.form-modal .form-control:focus,
.form-modal .form-select:focus { border-color: #3498db; box-shadow: 0 0 0 3px rgba(52,152,219,.12); }
.form-modal .form-text { font-size: 11.5px; color: #9ca3af; margin-top: 5px; }
.form-modal .input-group .input-group-text { border: 1.5px solid #e9ecef; background: #f8f9fa; font-size: 13px; color: #6c757d; font-weight: 600; }
.form-modal .input-group .form-control { border-left: none; border-right: none; }
.form-modal .input-group .form-control:focus { border-color: #3498db; box-shadow: none; }
.form-modal .input-group:focus-within .input-group-text { border-color: #3498db; }

/* ── Detail Modal ── */
.detail-row { display: flex; gap: 8px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; font-size: 13.5px; }
.detail-row:last-child { border-bottom: none; }
.detail-label { width: 160px; flex-shrink: 0; color: #6c757d; font-weight: 600; font-size: 13px; }
.detail-value { color: #1a1a2e; }
.mini-stat { background: #f8f9fa; border-radius: 12px; padding: 14px 16px; text-align: center; }
.mini-stat .num { font-size: 22px; font-weight: 700; line-height: 1.1; color: #1a1a2e; }
.mini-stat .lbl { font-size: 11px; color: #6c757d; font-weight: 500; margin-top: 2px; }

/* ── Delete confirm overlay ── */
.confirm-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 16px; }
.confirm-overlay.show { display: flex; }
.confirm-modal { background: #fff; border-radius: 20px; padding: 36px 32px; width: 400px; max-width: 92vw; box-shadow: 0 20px 60px rgba(0,0,0,.2); animation: modalIn .25s ease; }
@keyframes modalIn { from { transform: scale(.9) translateY(10px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
.confirm-modal .c-icon { width: 60px; height: 60px; border-radius: 50%; background: rgba(220,38,38,.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; font-size: 28px; color: #dc2626; }
.confirm-modal h5 { text-align: center; font-weight: 700; font-size: 17px; margin-bottom: 6px; }
.confirm-modal p  { text-align: center; color: #6c757d; font-size: 13px; margin-bottom: 22px; }
.btn-del-yes { background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; border: none; border-radius: 12px; padding: 11px 0; width: 100%; font-weight: 600; font-size: 14px; cursor: pointer; margin-bottom: 10px; transition: all .2s; }
.btn-del-yes:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(220,38,38,.3); }
.btn-del-no { background: #f1f3f4; color: #6c757d; border: none; border-radius: 12px; padding: 10px 0; width: 100%; font-weight: 600; font-size: 13px; cursor: pointer; transition: background .2s; }
.btn-del-no:hover { background: #e2e6ea; }

/* ── Toast ── */
.toast-notif { position: fixed; top: 24px; right: 24px; background: #16a34a; color: #fff; border-radius: 12px; padding: 14px 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.15); z-index: 99999; opacity: 0; transform: translateX(40px); transition: all .3s ease; pointer-events: none; }
.toast-notif.show { opacity: 1; transform: translateX(0); }
.toast-notif.toast-danger { background: #dc2626; }

/* ── Logout modal ── */
.logout-icon-wrap { width: 64px; height: 64px; border-radius: 18px; background: rgba(231,76,60,.1); display: flex; align-items: center; justify-content: center; font-size: 28px; color: #e74c3c; margin: 0 auto 6px; }
.logout-modal-error { background: #fdecea; border: 1.5px solid #f5c6c6; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #922b21; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.pw-wrapper { position: relative; }
.pw-wrapper .form-control { padding-right: 50px; }
.pw-toggle { position: absolute; right: 0; top: 0; bottom: 0; width: 50px; border: none; background: none; display: flex; align-items: center; justify-content: center; color: #6c757d; cursor: pointer; font-size: 16px; transition: color .2s; }
.pw-toggle:hover { color: #495057; }

.pagination-info { }

/* ════════════════
   RESPONSIVE
════════════════ */
@media (max-width: 992px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main-content { margin-left: 0; width: 100%; }
    .topbar { display: flex; }
}

@media (max-width: 768px) {
    .main-content { padding: 0 !important; }
    .page-inner { padding: 16px; }

    /* Stats: 2 kolom */
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }

    /* Toolbar stack */
    .toolbar { padding: 14px; gap: 8px; }
    .toolbar .flex-grow-1 { min-width: 100% !important; order: -1; }
    .toolbar .form-select { width: 100% !important; min-width: unset !important; }
    .toolbar .btn-filter  { width: 100%; justify-content: center; }
    .toolbar > span.ms-auto { display: none; }

    /* Table hidden, mobile cards shown */
    .desktop-table { display: none !important; }
    .mobile-cards  { display: block !important; }

    .pagination-info { display: none; }
    .table-card-header { padding: 14px 16px; }
    .table-card-header h5 { font-size: 14px; }
}

@media (min-width: 769px) {
    .mobile-cards  { display: none !important; }
    .desktop-table { display: block !important; }
}

@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .page-inner { padding: 12px; }
    .confirm-modal { padding: 28px 20px; }
    .stat-card .card-body { padding: 12px; }
    .stat-card h3 { font-size: 1.3rem !important; }
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

<!-- ═══════════════════════════ SIDEBAR ════════════════════════════════════ -->
<div class="sidebar p-3" id="sidebar">
    <!-- Close button (mobile) -->
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
        <?php if($pending > 0): ?>
            <span class="badge bg-danger ms-auto"><?= $pending ?></span>
        <?php endif; ?>
    </a>
    <a href="admin_pay.php" class="<?= $current_page_file==='admin_pay.php'?'active':'' ?>">
        <i class="bi bi-credit-card me-2 fs-5"></i> Payments
    </a>

    <div class="text-uppercase text-secondary px-2 mb-1 mt-3" style="font-size:11px;letter-spacing:1px;font-weight:600;">Master Data</div>
    <a href="admin_user.php" class="<?= $current_page_file==='admin_user.php'?'active':'' ?>">
        <i class="bi bi-people me-2 fs-5"></i> Users
    </a>
    <a href="admin_staff.php" class="<?= $current_page_file==='admin_staff.php'?'active':'' ?>">
        <i class="bi bi-person-badge me-2 fs-5"></i> Staff
    </a>
    <a href="admin_service.php" class="<?= $current_page_file==='admin_service.php'?'active':'' ?>">
        <i class="bi bi-stars me-2 fs-5"></i> Services
    </a>
    <a href="admin_breed.php" class="<?= $current_page_file==='admin_breed.php'?'active':'' ?>">
        <i class="bi bi-bug me-2 fs-5"></i> Breeds
    </a>
    <a href="admin_staffschedule.php" class="<?= $current_page_file==='admin_staffschedule.php'?'active':'' ?>">
        <i class="bi bi-clock me-2 fs-5"></i> Staff Schedules
    </a>

    <div class="mt-4 pt-3 border-top border-secondary">
        <a href="#" class="logout-link fw-bold" onclick="openLogoutModal(); return false;">
            <i class="bi bi-box-arrow-left me-2 fs-5"></i> Logout
        </a>
    </div>
</div>

<!-- ═══════════════════════════ MAIN CONTENT ═══════════════════════════════ -->
<div class="main-content p-4 p-md-5">
<div class="page-inner">

    <!-- Header desktop -->
    <div class="d-none d-lg-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Services</h2>
            <p class="text-muted mb-0" style="font-size:13px">Kelola data seluruh layanan CatDogKu</p>
        </div>
        <a href="admin_reserve.php?status=pending" class="position-relative text-decoration-none text-secondary">
            <i class="bi bi-bell fs-5"></i>
            <?php if($pending > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px"><?= $pending ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Header mobile -->
    <div class="d-lg-none mb-3">
        <h4 class="fw-bold text-dark mb-0">Services</h4>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stats-grid">
        <div class="card stat-card shadow-sm p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">Total Services</p>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size:1.5rem"><?= number_format($stat_total) ?></h3>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">All available</p>
                </div>
                <div class="icon-box bg-primary bg-opacity-10 text-primary" style="width:46px;height:46px;font-size:22px">
                    <i class="bi bi-stars"></i>
                </div>
            </div>
        </div>
        <div class="card stat-card shadow-sm p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">Avg. Base Price</p>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size:1.1rem">Rp <?= number_format($stat_avg,0,',','.') ?></h3>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">Average harga_base</p>
                </div>
                <div class="icon-box bg-success bg-opacity-10 text-success" style="width:46px;height:46px;font-size:22px">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
        <div class="card stat-card shadow-sm p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">No Specialization</p>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size:1.5rem"><?= number_format($stat_no_spec) ?></h3>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">General / unassigned</p>
                </div>
                <div class="icon-box bg-warning bg-opacity-10" style="color:#d97706;width:46px;height:46px;font-size:22px">
                    <i class="bi bi-exclamation-circle"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Toolbar ── -->
    <form method="GET" action="admin_service.php">
        <div class="toolbar mb-4">
            <div class="flex-grow-1" style="min-width:200px">
                <div class="input-group" style="height:40px">
                    <span class="input-group-text bg-white border-end-0"
                          style="border-radius:10px 0 0 10px;border:1.5px solid #e9ecef;border-right:none">
                        <i class="bi bi-search text-muted" style="font-size:13px"></i>
                    </span>
                    <input type="text" name="q" class="form-control border-start-0"
                           style="border-radius:0 10px 10px 0;border:1.5px solid #e9ecef;border-left:none"
                           placeholder="Search service name or description…"
                           value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
            </div>
            <select name="spec" class="form-select" style="width:auto;min-width:180px">
                <option value="">All Specializations</option>
                <?php foreach($all_specs as $sp): ?>
                    <option value="<?= $sp['id_specialization'] ?>"
                        <?= $filter_spec === (int)$sp['id_specialization'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sp['nama_specialization']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-filter">
                <i class="bi bi-search"></i> Search
            </button>
            <?php if($search || $filter_spec): ?>
                <a href="admin_service.php" class="btn btn-filter"
                   style="border:1.5px solid #dee2e6;color:#6c757d;background:#fff;">
                    <i class="bi bi-x-lg"></i> Reset
                </a>
            <?php endif; ?>
            <span class="ms-auto text-muted" style="font-size:13px;white-space:nowrap">
                <b><?= $total_rows ?></b> services found
            </span>
        </div>
    </form>

    <!-- ── Table Card ── -->
    <div class="table-card mb-4">
        <div class="table-card-header">
            <h5 class="fw-bold mb-0" style="font-size:15px">
                All Services
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2" style="font-size:12px"><?= number_format($total_rows) ?></span>
            </h5>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted pagination-info" style="font-size:13px">Page <?= $current_pg ?> of <?= $total_pages ?></span>
                <button type="button"
                        class="btn btn-primary btn-sm"
                        style="border-radius:9px;font-size:13px;font-weight:600;padding:6px 16px;display:inline-flex;align-items:center;gap:5px"
                        onclick="openAddService()">
                    <i class="bi bi-plus-lg"></i> Add Service
                </button>
            </div>
        </div>

        <!-- DESKTOP TABLE -->
        <div class="table-responsive desktop-table">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Specialization</th>
                        <th>Base Price</th>
                        <th>Duration</th>
                        <th>Times Used</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($services)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-stars fs-2 d-block mb-2 text-secondary"></i>
                            No services found
                        </td>
                    </tr>
                <?php else: ?>
                <?php foreach($services as $sv): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="svc-icon"><i class="bi bi-scissors"></i></div>
                            <div>
                                <div class="fw-semibold" style="font-size:13.5px"><?= htmlspecialchars($sv['nama_service']) ?></div>
                                <?php if($sv['deskripsi']): ?>
                                    <div class="text-muted" style="font-size:11.5px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                        <?= htmlspecialchars($sv['deskripsi']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if($sv['nama_specialization']): ?>
                            <span class="spec-tag"><?= htmlspecialchars($sv['nama_specialization']) ?></span>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:12px">General</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="fw-bold text-success" style="font-size:13px">
                            Rp <?= number_format(floatval($sv['harga_base']),0,',','.') ?>
                        </span>
                    </td>
                    <td>
                        <?php if($sv['durasi_estimasi']): ?>
                            <span class="badge bg-info bg-opacity-10 text-info fw-semibold" style="font-size:12px;padding:4px 10px">
                                <i class="bi bi-clock" style="font-size:10px"></i> <?= $sv['durasi_estimasi'] ?> min
                            </span>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:12px">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold" style="font-size:12px;padding:4px 10px">
                            <?= $sv['total_used'] ?> orders
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <button class="btn-action btn-view"
                                onclick="openDetail(<?= htmlspecialchars(json_encode($sv), ENT_QUOTES) ?>)">
                                <i class="bi bi-eye-fill"></i> Detail
                            </button>
                            <button class="btn-action btn-edit"
                                onclick="openEditService(<?= htmlspecialchars(json_encode($sv), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil-fill"></i> Edit
                            </button>
                            <button class="btn-action btn-delete"
                                onclick="openDeleteConfirm(<?= $sv['id_service'] ?>, '<?= addslashes(htmlspecialchars($sv['nama_service'])) ?>')">
                                <i class="bi bi-trash-fill"></i> Delete
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- MOBILE CARDS -->
        <div class="mobile-cards p-3">
            <?php if(empty($services)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-stars fs-2 d-block mb-2 text-secondary"></i>
                    No services found
                </div>
            <?php else: ?>
            <?php foreach($services as $sv): ?>
            <div class="svc-mobile-card">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="svc-icon"><i class="bi bi-scissors"></i></div>
                    <div>
                        <div class="card-title"><?= htmlspecialchars($sv['nama_service']) ?></div>
                        <div class="card-sub">
                            <?php if($sv['nama_specialization']): ?>
                                <span class="spec-tag"><?= htmlspecialchars($sv['nama_specialization']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">General</span>
                            <?php endif; ?>
                            &nbsp;·&nbsp;
                            <span class="fw-bold text-success" style="font-size:12px">
                                Rp <?= number_format(floatval($sv['harga_base']),0,',','.') ?>
                            </span>
                            <?php if($sv['durasi_estimasi']): ?>
                                &nbsp;·&nbsp;
                                <span class="text-info fw-semibold" style="font-size:11px">
                                    <i class="bi bi-clock"></i> <?= $sv['durasi_estimasi'] ?> min
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if($sv['deskripsi']): ?>
                            <div style="font-size:11.5px;color:#6c757d;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($sv['deskripsi']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold" style="font-size:11px">
                        <?= $sv['total_used'] ?> orders
                    </span>
                    <div class="card-actions">
                        <button class="btn-action btn-view"
                            onclick="openDetail(<?= htmlspecialchars(json_encode($sv), ENT_QUOTES) ?>)">
                            <i class="bi bi-eye-fill"></i> Detail
                        </button>
                        <button class="btn-action btn-edit"
                            onclick="openEditService(<?= htmlspecialchars(json_encode($sv), ENT_QUOTES) ?>)">
                            <i class="bi bi-pencil-fill"></i> Edit
                        </button>
                        <button class="btn-action btn-delete"
                            onclick="openDeleteConfirm(<?= $sv['id_service'] ?>, '<?= addslashes(htmlspecialchars($sv['nama_service'])) ?>')">
                            <i class="bi bi-trash-fill"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top"
             style="background:#fafafa;flex-wrap:wrap;gap:8px">
            <span class="text-muted pagination-info" style="font-size:13px">
                Showing <?= ($offset+1) ?>–<?= min($offset+$per_page, $total_rows) ?> of <?= $total_rows ?>
            </span>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $current_pg<=1?'disabled':'' ?>">
                        <a class="page-link" href="<?= pg_qs($current_pg-1,$search,$filter_spec) ?>">
                            <i class="bi bi-chevron-left" style="font-size:11px"></i>
                        </a>
                    </li>
                    <?php
                    $from = max(1,$current_pg-2); $to = min($total_pages,$current_pg+2);
                    if($from>1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    for($pg=$from;$pg<=$to;$pg++):
                    ?>
                        <li class="page-item <?= $pg===$current_pg?'active':'' ?>">
                            <a class="page-link" href="<?= pg_qs($pg,$search,$filter_spec) ?>"><?= $pg ?></a>
                        </li>
                    <?php endfor;
                    if($to<$total_pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    ?>
                    <li class="page-item <?= $current_pg>=$total_pages?'disabled':'' ?>">
                        <a class="page-link" href="<?= pg_qs($current_pg+1,$search,$filter_spec) ?>">
                            <i class="bi bi-chevron-right" style="font-size:11px"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div><!-- /table-card -->

</div><!-- /page-inner -->
</div><!-- /main-content -->


<!-- ══════════════════ ADD SERVICE MODAL ══════════════════════════════════ -->
<div class="modal fade form-modal" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-plus-circle-fill me-2 text-primary"></i>Add New Service
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="admin_service.php" id="addServiceForm">
                <input type="hidden" name="add_service" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Service Name <span class="text-danger">*</span></label>
                        <input type="text" name="nama_service" class="form-control"
                               placeholder="e.g. Full Body Grooming" required maxlength="100">
                        <div class="form-text">Name of the service offered to customers.</div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Base Price (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text" style="border-radius:10px 0 0 10px;border-right:none">Rp</span>
                                <input type="number" name="harga_base" class="form-control"
                                       placeholder="e.g. 150000" min="0" step="1000" required
                                       style="border-radius:0 10px 10px 0;border-left:none">
                            </div>
                            <div class="form-text">Starting / base price for this service.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Duration (minutes)</label>
                            <div class="input-group">
                                <input type="number" name="durasi_estimasi" class="form-control"
                                       placeholder="e.g. 60" min="1" max="480"
                                       style="border-radius:10px 0 0 10px;border-right:none">
                                <span class="input-group-text" style="border-radius:0 10px 10px 0;border-left:none">min</span>
                            </div>
                            <div class="form-text">Optional. Estimated service duration.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Specialization</label>
                        <select name="id_specialization" class="form-select">
                            <option value="">— General / None —</option>
                            <?php foreach($all_specs as $sp): ?>
                                <option value="<?= $sp['id_specialization'] ?>">
                                    <?= htmlspecialchars($sp['nama_specialization']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Optional. Link this service to a specialization.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="deskripsi" class="form-control" rows="3" maxlength="500"
                                  placeholder="Short description of what this service includes…"
                                  style="resize:vertical;border-radius:10px;border:1.5px solid #e9ecef;font-size:13.5px;padding:10px 14px"></textarea>
                        <div class="form-text">Optional. Shown to customers on the booking page.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal"
                            style="border-radius:8px;font-size:13px">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"
                            style="border-radius:8px;font-size:13px;font-weight:600">
                        <i class="bi bi-plus-lg me-1"></i> Add Service
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════ EDIT SERVICE MODAL ═════════════════════════════════ -->
<div class="modal fade form-modal" id="editServiceModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-pencil-square me-2 text-warning"></i>Edit Service
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="admin_service.php">
                <input type="hidden" name="edit_service" value="1">
                <input type="hidden" name="id_service" id="edit-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Service Name <span class="text-danger">*</span></label>
                        <input type="text" name="nama_service" id="edit-nama" class="form-control"
                               required maxlength="100">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Base Price (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text" style="border-radius:10px 0 0 10px;border-right:none">Rp</span>
                                <input type="number" name="harga_base" id="edit-harga" class="form-control"
                                       min="0" step="1000" required
                                       style="border-radius:0 10px 10px 0;border-left:none">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Duration (minutes)</label>
                            <div class="input-group">
                                <input type="number" name="durasi_estimasi" id="edit-durasi" class="form-control"
                                       min="1" max="480"
                                       style="border-radius:10px 0 0 10px;border-right:none">
                                <span class="input-group-text" style="border-radius:0 10px 10px 0;border-left:none">min</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Specialization</label>
                        <select name="id_specialization" id="edit-spec" class="form-select">
                            <option value="">— General / None —</option>
                            <?php foreach($all_specs as $sp): ?>
                                <option value="<?= $sp['id_specialization'] ?>">
                                    <?= htmlspecialchars($sp['nama_specialization']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="deskripsi" id="edit-deskripsi" class="form-control" rows="3" maxlength="500"
                                  style="resize:vertical;border-radius:10px;border:1.5px solid #e9ecef;font-size:13.5px;padding:10px 14px"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal"
                            style="border-radius:8px;font-size:13px">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm"
                            style="border-radius:8px;font-size:13px;font-weight:600">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════ DETAIL MODAL ═══════════════════════════════════════ -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="svc-icon" style="width:52px;height:52px;font-size:20px;border-radius:14px">
                        <i class="bi bi-scissors"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modal-name"></h5>
                        <p class="text-muted mb-0" id="modal-spec" style="font-size:13px"></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="num text-success" id="ms-price" style="font-size:16px"></div>
                            <div class="lbl">Base Price</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="num" id="ms-duration">—</div>
                            <div class="lbl">Duration (min)</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="num" id="ms-used">0</div>
                            <div class="lbl">Times Ordered</div>
                        </div>
                    </div>
                </div>
                <p class="fw-bold text-muted mb-3" style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">Service Information</p>
                <div id="modal-info"></div>
            </div>
            <div class="modal-footer">
                <button type="button" id="modal-edit-btn" class="btn btn-warning btn-sm"
                        style="border-radius:8px;font-size:13px;font-weight:600">
                    <i class="bi bi-pencil me-1"></i> Edit Service
                </button>
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal"
                        style="border-radius:8px;font-size:13px">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════ DELETE CONFIRM ═════════════════════════════════════ -->
<div class="confirm-overlay" id="deleteOverlay">
    <div class="confirm-modal">
        <div class="c-icon"><i class="bi bi-trash3-fill"></i></div>
        <h5>Delete Service?</h5>
        <p id="delete-msg">This action cannot be undone.</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="delete_service" value="1">
            <input type="hidden" name="id_service" id="delete-id">
            <button type="submit" class="btn-del-yes">
                <i class="bi bi-trash me-2"></i> Yes, Delete
            </button>
        </form>
        <button class="btn-del-no" onclick="closeDelete()">Cancel</button>
    </div>
</div>

<!-- ══════════════════ TOAST ═══════════════════════════════════════════════ -->
<div class="toast-notif" id="toastNotif">
    <i class="bi bi-check-circle-fill"></i>
    <span id="toastMsg">Berhasil!</span>
</div>

<!-- ══════════════════ LOGOUT CONFIRMATION MODAL ══════════════════════════ -->
<div class="modal fade form-modal" id="logoutModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        onclick="clearLogoutForm()"></button>
            </div>
            <form method="POST" action="admin_service.php" id="logoutForm">
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
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#555">
                            Password <span class="text-danger">*</span>
                        </label>
                        <div class="pw-wrapper">
                            <input type="password" name="logout_password" id="logout-pw"
                                   class="form-control" placeholder="Enter your password" required
                                   autocomplete="current-password">
                            <button type="button" class="pw-toggle" onclick="togglePwVisibility()">
                                <i class="bi bi-eye" id="pw-toggle-icon"></i>
                            </button>
                        </div>
                        <div class="form-text">Confirm your identity before logging out.</div>
                    </div>
                    <hr style="border-color:#f0f0f0;margin:18px 0 16px">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger fw-bold"
                                style="border-radius:10px;font-size:13.5px;padding:10px"
                                onclick="return validateLogout()">Yes, Logout</button>
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal"
                                style="border-radius:8px;font-size:13px" onclick="clearLogoutForm()">Close</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Sidebar ── */
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

/* ── Add Service ── */
function openAddService() {
    document.getElementById('addServiceForm').reset();
    new bootstrap.Modal(document.getElementById('addServiceModal')).show();
}

/* ── Edit Service ── */
function openEditService(sv) {
    document.getElementById('edit-id').value        = sv.id_service;
    document.getElementById('edit-nama').value      = sv.nama_service      || '';
    document.getElementById('edit-harga').value     = sv.harga_base        || '';
    document.getElementById('edit-durasi').value    = sv.durasi_estimasi   || '';
    document.getElementById('edit-spec').value      = sv.id_specialization || '';
    document.getElementById('edit-deskripsi').value = sv.deskripsi         || '';
    new bootstrap.Modal(document.getElementById('editServiceModal')).show();
}

/* ── Detail Modal ── */
function detailRow(label, value) {
    return `<div class="detail-row">
        <span class="detail-label">${label}</span>
        <span class="detail-value">${value || '—'}</span>
    </div>`;
}

let _currentDetailSv = null;

function openDetail(sv) {
    _currentDetailSv = sv;
    document.getElementById('modal-name').textContent    = sv.nama_service || '—';
    document.getElementById('modal-spec').textContent    = sv.nama_specialization || 'General';
    document.getElementById('ms-price').textContent      = 'Rp ' + Number(sv.harga_base || 0).toLocaleString('id-ID');
    document.getElementById('ms-duration').textContent   = sv.durasi_estimasi ? sv.durasi_estimasi + ' min' : '—';
    document.getElementById('ms-used').textContent       = sv.total_used ?? 0;
    document.getElementById('modal-info').innerHTML =
        detailRow('Service Name',   sv.nama_service) +
        detailRow('Specialization', sv.nama_specialization || 'General') +
        detailRow('Base Price',     'Rp ' + Number(sv.harga_base || 0).toLocaleString('id-ID')) +
        detailRow('Est. Duration',  sv.durasi_estimasi ? sv.durasi_estimasi + ' minutes' : '—') +
        detailRow('Times Ordered',  (sv.total_used ?? 0) + ' reservations') +
        detailRow('Description',    sv.deskripsi || '—');
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

document.getElementById('modal-edit-btn').addEventListener('click', function () {
    const detailBs = bootstrap.Modal.getInstance(document.getElementById('detailModal'));
    detailBs.hide();
    document.getElementById('detailModal').addEventListener('hidden.bs.modal', function handler() {
        openEditService(_currentDetailSv);
        this.removeEventListener('hidden.bs.modal', handler);
    });
});

/* ── Delete Confirm ── */
function openDeleteConfirm(id, name) {
    document.getElementById('delete-id').value        = id;
    document.getElementById('delete-msg').textContent =
        'Are you sure you want to delete "' + name + '"? This cannot be undone.';
    document.getElementById('deleteOverlay').classList.add('show');
}
function closeDelete() { document.getElementById('deleteOverlay').classList.remove('show'); }
document.getElementById('deleteOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeDelete();
});

/* ── Toast ── */
function showToast(msg, type) {
    const t = document.getElementById('toastNotif');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.toggle('toast-danger', type === 'danger');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3500);
}
<?php if(isset($_GET['added'])): ?>
    window.addEventListener('DOMContentLoaded', () => showToast('Service berhasil ditambahkan! 🎉'));
<?php elseif(isset($_GET['edited'])): ?>
    window.addEventListener('DOMContentLoaded', () => showToast('Data service berhasil diperbarui! ✅'));
<?php elseif(isset($_GET['deleted'])): ?>
    window.addEventListener('DOMContentLoaded', () => showToast('Service berhasil dihapus.', 'danger'));
<?php endif; ?>

/* ── Logout ── */
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
        document.getElementById('logout-js-error-msg').textContent = 'Password is required.';
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