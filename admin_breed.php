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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['confirm_logout'])) {
    if (isset($_POST['delete_breed'])) {
        $id = (int)$_POST['id_breed'];
        mysqli_query($conn, "DELETE FROM breeds WHERE id_breed=$id");
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter(['q'=>$_GET['q']??null,'species'=>$_GET['species']??null,'page'=>$_GET['page']??null])));
        exit;
    }
    if (isset($_POST['delete_species'])) {
        $id = (int)$_POST['id_species'];
        mysqli_query($conn, "UPDATE breeds SET id_species=NULL WHERE id_species=$id");
        mysqli_query($conn, "DELETE FROM species WHERE id_species=$id");
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    if (isset($_POST['add_species'])) {
        $nama = mysqli_real_escape_string($conn, trim($_POST['nama_species'] ?? ''));
        if ($nama !== '') mysqli_query($conn, "INSERT INTO species (nama_species) VALUES ('$nama')");
        header('Location: ' . $_SERVER['PHP_SELF'] . '#tab-species');
        exit;
    }
    if (isset($_POST['edit_species'])) {
        $id   = (int)$_POST['id_species'];
        $nama = mysqli_real_escape_string($conn, trim($_POST['nama_species'] ?? ''));
        if ($id > 0 && $nama !== '') mysqli_query($conn, "UPDATE species SET nama_species='$nama' WHERE id_species=$id");
        header('Location: ' . $_SERVER['PHP_SELF'] . '#tab-species');
        exit;
    }
    if (isset($_POST['add_breed'])) {
        $nama   = mysqli_real_escape_string($conn, trim($_POST['nama_breed'] ?? ''));
        $id_sp  = (int)($_POST['id_species'] ?? 0);
        $sp_val = $id_sp > 0 ? $id_sp : 'NULL';
        if ($nama !== '') mysqli_query($conn, "INSERT INTO breeds (id_species, nama_breed) VALUES ($sp_val, '$nama')");
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    if (isset($_POST['edit_breed'])) {
        $id     = (int)$_POST['id_breed'];
        $nama   = mysqli_real_escape_string($conn, trim($_POST['nama_breed'] ?? ''));
        $id_sp  = (int)($_POST['id_species'] ?? 0);
        $sp_val = $id_sp > 0 ? $id_sp : 'NULL';
        if ($id > 0 && $nama !== '') mysqli_query($conn, "UPDATE breeds SET id_species=$sp_val, nama_breed='$nama' WHERE id_breed=$id");
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter(['q'=>$_GET['q']??null,'species'=>$_GET['species']??null,'page'=>$_GET['page']??null])));
        exit;
    }
}

$search         = isset($_GET['q'])       && $_GET['q']       !== '' ? trim($_GET['q'])       : null;
$filter_species = isset($_GET['species']) && $_GET['species'] !== '' ? (int)$_GET['species'] : null;

$where_parts = ['1=1'];
if ($search) { $s = mysqli_real_escape_string($conn, $search); $where_parts[] = "b.nama_breed LIKE '%$s%'"; }
if ($filter_species) $where_parts[] = "b.id_species = $filter_species";
$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

$per_page   = 10;
$current_pg = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($current_pg - 1) * $per_page;
$total_rows  = db_val($conn, "SELECT COUNT(*) FROM breeds b $where_sql");
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$breeds = db_rows($conn, "SELECT b.id_breed,b.nama_breed,b.id_species,sp.nama_species,COUNT(DISTINCT p.id_pet) AS total_pets FROM breeds b LEFT JOIN species sp ON b.id_species=sp.id_species LEFT JOIN pets p ON b.id_breed=p.id_breed $where_sql GROUP BY b.id_breed ORDER BY b.nama_breed ASC LIMIT $per_page OFFSET $offset");

$all_species_full = db_rows($conn, "SELECT sp.id_species,sp.nama_species,COUNT(DISTINCT b.id_breed) AS total_breeds,COUNT(DISTINCT p.id_pet) AS total_pets FROM species sp LEFT JOIN breeds b ON sp.id_species=b.id_species LEFT JOIN pets p ON b.id_breed=p.id_breed GROUP BY sp.id_species ORDER BY sp.nama_species ASC");

$stat_total_breeds  = db_val($conn, "SELECT COUNT(*) FROM breeds");
$stat_total_species = db_val($conn, "SELECT COUNT(*) FROM species");
$stat_no_species    = db_val($conn, "SELECT COUNT(*) FROM breeds WHERE id_species IS NULL");
$all_species        = db_rows($conn, "SELECT id_species, nama_species FROM species ORDER BY nama_species");
$pending            = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='pending'");
$current_page_file  = basename($_SERVER['PHP_SELF']);

function pg_qs($page, $search, $filter_species) {
    return '?' . http_build_query(array_filter(['q'=>$search,'species'=>$filter_species,'page'=>$page]));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Breeds & Species — CatDogKu Admin</title>
<link rel="icon" type="image/png" href="assets/icon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
/* ── Base ── */
body { background-color:#f4f7f6; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; margin:0; padding:0; }

/* ── Sidebar ── */
.sidebar {
    width:260px; position:fixed; top:0; left:0;
    height:100vh; overflow-y:auto; z-index:1050;
    background-color:#2c3e50; color:#fff;
    box-shadow:4px 0 10px rgba(0,0,0,.05);
    transition:transform .3s ease;
}
.sidebar-brand { font-size:1.25rem; letter-spacing:1px; border-bottom:1px solid rgba(255,255,255,.1); }
.sidebar a {
    color:#aeb6bf; text-decoration:none; padding:12px 20px;
    display:flex; align-items:center; border-radius:10px;
    margin-bottom:8px; transition:all .3s ease; font-weight:500;
}
.sidebar a:hover, .sidebar a.active { background-color:#3498db; color:#fff; transform:translateX(5px); }
.sidebar a.logout-link:hover { background-color:rgba(231,76,60,.2); color:#e74c3c; transform:none; }

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

/* ── Stat Cards ── */
.stat-card { border-radius:15px; border:none; transition:transform .3s,box-shadow .3s; cursor:default; }
.stat-card:hover { transform:translateY(-5px); box-shadow:0 12px 20px rgba(0,0,0,.08)!important; }
.icon-box { border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

/* ── Stats grid — pure CSS, no inline style ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

/* ── Tabs ── */
.tab-nav {
    display: flex;          /* flex bukan inline-flex agar bisa dikendalikan width */
    width: fit-content;     /* desktop: ikut konten */
    background:#fff; border-radius:14px; padding:6px;
    box-shadow:0 2px 10px rgba(0,0,0,.04);
    gap:4px; margin-bottom:20px;
}
.tab-btn {
    padding:9px 24px; border-radius:10px; border:none;
    font-size:13.5px; font-weight:600; cursor:pointer; transition:all .2s;
    color:#6c757d; background:transparent;
    display:inline-flex; align-items:center; gap:7px;
    white-space:nowrap;
}
.tab-btn.active { background:#3498db; color:#fff; box-shadow:0 4px 12px rgba(52,152,219,.3); }
.tab-btn:not(.active):hover { background:#f0f4f8; color:#2c3e50; }
.tab-pane { display:none; }
.tab-pane.show { display:block; }

/* ── Toolbar ── */
.toolbar { background:#fff; border-radius:14px; padding:18px 22px; box-shadow:0 2px 10px rgba(0,0,0,.04); display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar .form-control, .toolbar .form-select { border-radius:10px; border:1.5px solid #e9ecef; font-size:13px; height:40px; }
.toolbar .form-control:focus, .toolbar .form-select:focus { border-color:#3498db; box-shadow:0 0 0 3px rgba(52,152,219,.12); }
.btn-filter { height:40px; border-radius:10px; font-size:13px; font-weight:600; padding:0 18px; display:inline-flex; align-items:center; gap:4px; }

/* ── Table card ── */
.table-card { background:#fff; border-radius:16px; box-shadow:0 5px 20px rgba(0,0,0,.04); overflow:hidden; }
.table-card-header { padding:18px 24px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
.table > thead > tr > th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#6c757d; background:#f8f9fa; border-bottom:1px solid #e9ecef; padding:13px 24px; white-space:nowrap; }
.table > tbody > tr > td { padding:13px 24px; vertical-align:middle; font-size:13.5px; border-bottom:1px solid #f5f5f5; }
.table > tbody > tr:last-child > td { border-bottom:none; }
.table > tbody > tr:hover > td { background:#f8f9fa; }

/* ── Mobile cards ── */
.breed-mobile-card { background:#fff; border-radius:12px; padding:14px 16px; margin-bottom:10px; box-shadow:0 2px 8px rgba(0,0,0,.05); border:1px solid #f0f0f0; }
.breed-mobile-card .card-title { font-size:14px; font-weight:600; color:#1a1a2e; margin-bottom:4px; }
.breed-mobile-card .card-sub { font-size:12px; color:#6c757d; margin-bottom:10px; }
.breed-mobile-card .card-actions { display:flex; gap:6px; flex-wrap:wrap; }

/* ── Icon boxes ── */
.breed-icon  { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; background:linear-gradient(135deg,#fd794822,#fd794844); color:#e8590c; }
.species-icon{ width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; background:linear-gradient(135deg,#3498db22,#3498db44); color:#2980b9; }

/* ── Species tag ── */
.species-tag { display:inline-block; background:#fff3e0; color:#e65100; border-radius:6px; padding:2px 9px; font-size:11.5px; font-weight:600; white-space:nowrap; }

/* ── Action buttons ── */
.btn-action { padding:5px 12px; font-size:12px; border-radius:8px; font-weight:600; border:none; cursor:pointer; transition:opacity .2s; display:inline-flex; align-items:center; gap:4px; }
.btn-action:hover { opacity:.8; }
.btn-view   { background:#eaf4fd; color:#2980b9; }
.btn-edit   { background:#fef9e7; color:#b7770d; }
.btn-delete { background:#fdecea; color:#922b21; }

/* ── Pagination ── */
.pagination .page-link { border-radius:8px!important; margin:0 2px; font-size:13px; font-weight:600; color:#3498db; border:1.5px solid #e9ecef; }
.pagination .page-item.active .page-link { background:#3498db; border-color:#3498db; color:#fff; }
.pagination .page-link:hover { background:#eaf4fd; color:#2980b9; }

/* ── Modal ── */
.modal-content { border-radius:18px; border:none; }
.modal-header { border-bottom:1px solid #f0f0f0; padding:20px 24px; }
.modal-body   { padding:24px; }
.modal-footer { border-top:1px solid #f0f0f0; padding:16px 24px; }
.detail-row { display:flex; gap:8px; padding:10px 0; border-bottom:1px solid #f5f5f5; font-size:13.5px; }
.detail-row:last-child { border-bottom:none; }
.detail-label { width:160px; flex-shrink:0; color:#6c757d; font-weight:600; font-size:13px; }
.detail-value { color:#1a1a2e; }
.mini-stat { background:#f8f9fa; border-radius:12px; padding:14px 16px; text-align:center; }
.mini-stat .num { font-size:22px; font-weight:700; line-height:1.1; color:#1a1a2e; }
.mini-stat .lbl { font-size:11px; color:#6c757d; font-weight:500; margin-top:2px; }

.form-modal .modal-body label { font-size:13px; font-weight:600; color:#555; margin-bottom:5px; }
.form-modal .form-control,
.form-modal .form-select { border-radius:10px; border:1.5px solid #e9ecef; font-size:13.5px; padding:10px 14px; }
.form-modal .form-control:focus,
.form-modal .form-select:focus { border-color:#3498db; box-shadow:0 0 0 3px rgba(52,152,219,.12); }

/* ── Confirm overlay ── */
.confirm-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; padding:16px; }
.confirm-overlay.show { display:flex; }
.confirm-modal { background:#fff; border-radius:20px; padding:36px 32px; width:400px; max-width:92vw; box-shadow:0 20px 60px rgba(0,0,0,.2); animation:modalIn .25s ease; }
@keyframes modalIn { from{transform:scale(.9) translateY(10px);opacity:0} to{transform:scale(1) translateY(0);opacity:1} }
.confirm-modal .c-icon { width:60px; height:60px; border-radius:50%; background:rgba(220,38,38,.1); display:flex; align-items:center; justify-content:center; margin:0 auto 18px; font-size:28px; color:#dc2626; }
.confirm-modal h5 { text-align:center; font-weight:700; font-size:17px; margin-bottom:6px; }
.confirm-modal p  { text-align:center; color:#6c757d; font-size:13px; margin-bottom:22px; }
.btn-del-yes { background:linear-gradient(135deg,#dc2626,#ef4444); color:#fff; border:none; border-radius:12px; padding:11px 0; width:100%; font-weight:600; font-size:14px; cursor:pointer; margin-bottom:10px; transition:all .2s; }
.btn-del-yes:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(220,38,38,.3); }
.btn-del-no  { background:#f1f3f4; color:#6c757d; border:none; border-radius:12px; padding:10px 0; width:100%; font-weight:600; font-size:13px; cursor:pointer; transition:background .2s; }
.btn-del-no:hover { background:#e2e6ea; }

/* ── Toast ── */
.toast-notif { position:fixed; top:24px; right:24px; background:#16a34a; color:#fff; border-radius:12px; padding:14px 20px; font-size:14px; font-weight:600; display:flex; align-items:center; gap:10px; box-shadow:0 8px 24px rgba(0,0,0,.15); z-index:99999; opacity:0; transform:translateX(40px); transition:all .3s ease; pointer-events:none; }
.toast-notif.show { opacity:1; transform:translateX(0); }

/* ── Logout modal ── */
.logout-icon-wrap { width:64px; height:64px; border-radius:18px; background:rgba(231,76,60,.1); display:flex; align-items:center; justify-content:center; font-size:28px; color:#e74c3c; margin:0 auto 6px; }
.logout-modal-error { background:#fdecea; border:1.5px solid #f5c6c6; border-radius:10px; padding:10px 14px; font-size:13px; color:#922b21; display:flex; align-items:center; gap:8px; margin-bottom:16px; }
.pw-wrapper { position:relative; }
.pw-wrapper .form-control { padding-right:50px; }
.pw-toggle { position:absolute; right:0; top:0; bottom:0; width:50px; border:none; background:none; display:flex; align-items:center; justify-content:center; color:#6c757d; cursor:pointer; font-size:16px; transition:color .2s; }
.pw-toggle:hover { color:#495057; }

/* ════════════════════════════════
   RESPONSIVE
════════════════════════════════ */

/* Sidebar hidden on tablet/mobile */
@media (max-width: 992px) {
    .sidebar { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0); }
    .main-content { margin-left:0; width:100%; }
    .topbar { display:flex; }
}

@media (max-width: 768px) {
    .main-content { padding:0 !important; }
    .page-inner  { padding:16px; }

    /* Stats: 2 kolom */
    .stats-grid { grid-template-columns:1fr 1fr; gap:10px; }

    /* Tab nav: full width */
    .tab-nav { width:100%; border-radius:10px; }
    .tab-btn { flex:1; justify-content:center; padding:9px 6px; font-size:12px; }

    /* Toolbar stack */
    .toolbar { padding:14px; gap:8px; }
    .toolbar .flex-grow-1 { min-width:100% !important; order:-1; }
    .toolbar .form-select { width:100% !important; min-width:unset !important; }
    .toolbar .btn-filter  { width:100%; justify-content:center; }
    .toolbar > span.ms-auto { display:none; }

    /* Tabel hilang, cards muncul */
    .desktop-table { display:none !important; }
    .mobile-cards  { display:block !important; }

    .pagination-info { display:none; }
    .table-card-header { padding:14px 16px; }
    .table-card-header h5 { font-size:14px; }
}

@media (min-width: 769px) {
    .mobile-cards  { display:none !important; }
    .desktop-table { display:block !important; }
}

@media (max-width: 480px) {
    /* Stats tetap 2 kolom di HP kecil */
    .stats-grid { grid-template-columns:1fr 1fr; gap:8px; }
    .page-inner { padding:12px; }
    .confirm-modal { padding:28px 20px; }
    /* Stat card content compact */
    .stat-card .card-body { padding:12px; }
    .stat-card h3 { font-size:1.3rem !important; }
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
        <?php if($pending > 0): ?><span class="badge bg-danger ms-auto"><?= $pending ?></span><?php endif; ?>
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

<!-- ══ MAIN CONTENT ══ -->
<div class="main-content p-4 p-md-5">
<div class="page-inner">

    <!-- Header desktop -->
    <div class="d-none d-lg-flex justify-content-between align-items-center mb-5">
        <h2 class="fw-bold text-dark mb-0">Breeds & Species</h2>
        <a href="admin_reserve.php?status=pending" class="position-relative text-decoration-none text-secondary">
            <i class="bi bi-bell fs-5"></i>
            <?php if($pending > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px"><?= $pending ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Header mobile -->
    <div class="d-lg-none mb-3">
        <h4 class="fw-bold text-dark mb-0">Breeds & Species</h4>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stats-grid">
        <!-- Total Breeds -->
        <div class="card stat-card shadow-sm p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">Total Breeds</p>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size:1.5rem"><?= number_format($stat_total_breeds) ?></h3>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">All registered</p>
                </div>
                <div class="icon-box bg-warning bg-opacity-10" style="color:#e8590c;width:46px;height:46px;font-size:22px">
                    <i class="bi bi-bug"></i>
                </div>
            </div>
        </div>
        <!-- Species -->
        <div class="card stat-card shadow-sm p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">Species</p>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size:1.5rem"><?= number_format($stat_total_species) ?></h3>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">Animal types</p>
                </div>
                <div class="icon-box bg-primary bg-opacity-10 text-primary" style="width:46px;height:46px;font-size:22px">
                    <i class="bi bi-diagram-3"></i>
                </div>
            </div>
        </div>
        <!-- Unassigned -->
        <div class="card stat-card shadow-sm p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">Unassigned</p>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size:1.5rem"><?= number_format($stat_no_species) ?></h3>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">No species</p>
                </div>
                <div class="icon-box bg-danger bg-opacity-10 text-danger" style="width:46px;height:46px;font-size:22px">
                    <i class="bi bi-exclamation-circle"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tabs ── -->
    <div class="tab-nav" id="mainTabs">
        <button class="tab-btn active" onclick="switchTab('breeds', this)">
            <i class="bi bi-bug"></i> <span>Breeds</span>
            <span class="badge ms-1" style="background:rgba(76,152,219,.2);color:#2980b9;font-size:10px"><?= $stat_total_breeds ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('species', this)">
            <i class="bi bi-diagram-3"></i> <span>Species</span>
            <span class="badge ms-1" style="background:rgba(76,152,219,.2);color:#2980b9;font-size:10px"><?= $stat_total_species ?></span>
        </button>
    </div>

    <!-- ══ TAB: BREEDS ══ -->
    <div class="tab-pane show" id="tab-breeds">

        <form method="GET" action="admin_breed.php">
            <div class="toolbar mb-4">
                <div class="flex-grow-1" style="min-width:200px">
                    <div class="input-group" style="height:40px">
                        <span class="input-group-text bg-white border-end-0" style="border-radius:10px 0 0 10px;border:1.5px solid #e9ecef;border-right:none">
                            <i class="bi bi-search text-muted" style="font-size:13px"></i>
                        </span>
                        <input type="text" name="q" class="form-control border-start-0"
                               style="border-radius:0 10px 10px 0;border:1.5px solid #e9ecef;border-left:none"
                               placeholder="Search breed…"
                               value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>
                </div>
                <select name="species" class="form-select" style="width:auto;min-width:180px">
                    <option value="">All Species</option>
                    <?php foreach($all_species as $sp): ?>
                        <option value="<?= $sp['id_species'] ?>" <?= $filter_species===(int)$sp['id_species']?'selected':'' ?>>
                            <?= htmlspecialchars($sp['nama_species']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-filter">
                    <i class="bi bi-search"></i> Search
                </button>
                <?php if($search || $filter_species): ?>
                    <a href="admin_breed.php" class="btn btn-filter" style="border:1.5px solid #dee2e6;color:#6c757d;background:#fff;">
                        <i class="bi bi-x-lg"></i> Reset
                    </a>
                <?php endif; ?>
                <span class="ms-auto text-muted" style="font-size:13px;white-space:nowrap">
                    <b><?= $total_rows ?></b> found
                </span>
            </div>
        </form>

        <div class="table-card mb-4">
            <div class="table-card-header">
                <h5 class="fw-bold mb-0" style="font-size:15px">All Breeds</h5>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted pagination-info" style="font-size:13px">Page <?= $current_pg ?> of <?= $total_pages ?></span>
                    <button class="btn btn-primary btn-sm"
                            style="border-radius:9px;font-size:13px;font-weight:600;padding:6px 16px;display:inline-flex;align-items:center;gap:5px"
                            onclick="openAddBreed()">
                        <i class="bi bi-plus-lg"></i> Add Breed
                    </button>
                </div>
            </div>

            <!-- DESKTOP TABLE -->
            <div class="table-responsive desktop-table">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Breed</th><th>Species</th><th>Pets Using</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(empty($breeds)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-5">
                            <i class="bi bi-bug fs-2 d-block mb-2 text-secondary"></i>No breeds found
                        </td></tr>
                    <?php else: foreach($breeds as $br): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="breed-icon"><i class="bi bi-bug-fill"></i></div>
                                <div class="fw-semibold" style="font-size:13.5px"><?= htmlspecialchars($br['nama_breed']) ?></div>
                            </div>
                        </td>
                        <td>
                            <?php if($br['nama_species']): ?>
                                <span class="species-tag"><?= htmlspecialchars($br['nama_species']) ?></span>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:12px">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold" style="font-size:12px;padding:4px 10px">
                                <?= $br['total_pets'] ?> pets
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <button class="btn-action btn-view" onclick="openDetailBreed(<?= htmlspecialchars(json_encode($br),ENT_QUOTES) ?>)">
                                    <i class="bi bi-eye-fill"></i> Details
                                </button>
                                <button class="btn-action btn-edit" onclick="openEditBreed(<?= htmlspecialchars(json_encode($br),ENT_QUOTES) ?>)">
                                    <i class="bi bi-pencil-fill"></i> Edit
                                </button>
                                <button class="btn-action btn-delete" onclick="openDeleteBreed(<?= $br['id_breed'] ?>,'<?= addslashes(htmlspecialchars($br['nama_breed'])) ?>')">
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
                <?php if(empty($breeds)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-bug fs-2 d-block mb-2 text-secondary"></i>No breeds found
                    </div>
                <?php else: foreach($breeds as $br): ?>
                <div class="breed-mobile-card">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="breed-icon"><i class="bi bi-bug-fill"></i></div>
                        <div>
                            <div class="card-title"><?= htmlspecialchars($br['nama_breed']) ?></div>
                            <div class="card-sub">
                                <?php if($br['nama_species']): ?>
                                    <span class="species-tag"><?= htmlspecialchars($br['nama_species']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Unassigned</span>
                                <?php endif; ?>
                                &nbsp;·&nbsp;
                                <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold" style="font-size:11px"><?= $br['total_pets'] ?> pets</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn-action btn-view" onclick="openDetailBreed(<?= htmlspecialchars(json_encode($br),ENT_QUOTES) ?>)">
                            <i class="bi bi-eye-fill"></i> Details
                        </button>
                        <button class="btn-action btn-edit" onclick="openEditBreed(<?= htmlspecialchars(json_encode($br),ENT_QUOTES) ?>)">
                            <i class="bi bi-pencil-fill"></i> Edit
                        </button>
                        <button class="btn-action btn-delete" onclick="openDeleteBreed(<?= $br['id_breed'] ?>,'<?= addslashes(htmlspecialchars($br['nama_breed'])) ?>')">
                            <i class="bi bi-trash-fill"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top" style="background:#fafafa;flex-wrap:wrap;gap:8px">
                <span class="text-muted pagination-info" style="font-size:13px">
                    Showing <?= ($offset+1) ?>–<?= min($offset+$per_page,$total_rows) ?> of <?= $total_rows ?>
                </span>
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item <?= $current_pg<=1?'disabled':'' ?>">
                            <a class="page-link" href="<?= pg_qs($current_pg-1,$search,$filter_species) ?>">
                                <i class="bi bi-chevron-left" style="font-size:11px"></i>
                            </a>
                        </li>
                        <?php
                        $from=max(1,$current_pg-2); $to=min($total_pages,$current_pg+2);
                        if($from>1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        for($pg=$from;$pg<=$to;$pg++):
                        ?>
                            <li class="page-item <?= $pg===$current_pg?'active':'' ?>">
                                <a class="page-link" href="<?= pg_qs($pg,$search,$filter_species) ?>"><?= $pg ?></a>
                            </li>
                        <?php endfor;
                        if($to<$total_pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        ?>
                        <li class="page-item <?= $current_pg>=$total_pages?'disabled':'' ?>">
                            <a class="page-link" href="<?= pg_qs($current_pg+1,$search,$filter_species) ?>">
                                <i class="bi bi-chevron-right" style="font-size:11px"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div><!-- /tab-breeds -->

    <!-- ══ TAB: SPECIES ══ -->
    <div class="tab-pane" id="tab-species">
        <div class="table-card mb-4">
            <div class="table-card-header">
                <h5 class="fw-bold mb-0" style="font-size:15px">All Species</h5>
                <button class="btn btn-primary btn-sm"
                        style="border-radius:9px;font-size:13px;font-weight:600;padding:6px 16px;display:inline-flex;align-items:center;gap:5px"
                        onclick="openAddSpecies()">
                    <i class="bi bi-plus-lg"></i> Add Species
                </button>
            </div>

            <!-- DESKTOP TABLE -->
            <div class="table-responsive desktop-table">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Species</th><th>Total Breeds</th><th>Total Pets</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if(empty($all_species_full)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-5">
                            <i class="bi bi-diagram-3 fs-2 d-block mb-2 text-secondary"></i>No species found
                        </td></tr>
                    <?php else: foreach($all_species_full as $sp): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="species-icon"><i class="bi bi-diagram-3-fill"></i></div>
                                <div class="fw-semibold" style="font-size:13.5px"><?= htmlspecialchars($sp['nama_species']) ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-warning bg-opacity-10 fw-semibold" style="color:#e8590c;font-size:12px;padding:4px 10px">
                                <?= $sp['total_breeds'] ?> breeds
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-success bg-opacity-10 text-success fw-semibold" style="font-size:12px;padding:4px 10px">
                                <?= $sp['total_pets'] ?> pets
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <button class="btn-action btn-edit" onclick="openEditSpecies(<?= $sp['id_species'] ?>,'<?= addslashes(htmlspecialchars($sp['nama_species'])) ?>')">
                                    <i class="bi bi-pencil-fill"></i> Edit
                                </button>
                                <button class="btn-action btn-delete" onclick="openDeleteSpecies(<?= $sp['id_species'] ?>,'<?= addslashes(htmlspecialchars($sp['nama_species'])) ?>',<?= $sp['total_breeds'] ?>)">
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
                <?php if(empty($all_species_full)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-diagram-3 fs-2 d-block mb-2 text-secondary"></i>No species found
                    </div>
                <?php else: foreach($all_species_full as $sp): ?>
                <div class="breed-mobile-card">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="species-icon"><i class="bi bi-diagram-3-fill"></i></div>
                        <div>
                            <div class="card-title"><?= htmlspecialchars($sp['nama_species']) ?></div>
                            <div class="card-sub">
                                <span class="badge bg-warning bg-opacity-10 fw-semibold" style="color:#e8590c;font-size:11px"><?= $sp['total_breeds'] ?> breeds</span>
                                &nbsp;·&nbsp;
                                <span class="badge bg-success bg-opacity-10 text-success fw-semibold" style="font-size:11px"><?= $sp['total_pets'] ?> pets</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn-action btn-edit" onclick="openEditSpecies(<?= $sp['id_species'] ?>,'<?= addslashes(htmlspecialchars($sp['nama_species'])) ?>')">
                            <i class="bi bi-pencil-fill"></i> Edit
                        </button>
                        <button class="btn-action btn-delete" onclick="openDeleteSpecies(<?= $sp['id_species'] ?>,'<?= addslashes(htmlspecialchars($sp['nama_species'])) ?>',<?= $sp['total_breeds'] ?>)">
                            <i class="bi bi-trash-fill"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div><!-- /tab-species -->

</div><!-- /page-inner -->
</div><!-- /main-content -->


<!-- ══ MODALS ══ -->

<!-- Breed Detail -->
<div class="modal fade" id="breedDetailModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="breed-icon" style="width:48px;height:48px;font-size:20px;border-radius:12px"><i class="bi bi-bug-fill"></i></div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="bd-name"></h5>
                        <p class="text-muted mb-0" id="bd-species" style="font-size:13px"></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-6"><div class="mini-stat"><div class="num text-primary" id="bd-ms-pets">0</div><div class="lbl">Pets Using</div></div></div>
                    <div class="col-6"><div class="mini-stat"><div class="num" id="bd-ms-id">—</div><div class="lbl">Breed ID</div></div></div>
                </div>
                <p class="fw-bold text-muted mb-3" style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">Breed Information</p>
                <div id="bd-info"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-warning btn-sm" style="border-radius:8px;font-size:13px;font-weight:600" onclick="switchToEdit()">
                    <i class="bi bi-pencil me-1"></i> Edit Breed
                </button>
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal" style="border-radius:8px;font-size:13px">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Breed -->
<div class="modal fade form-modal" id="addBreedModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Add New Breed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="add_breed" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Breed Name <span class="text-danger">*</span></label>
                        <input type="text" name="nama_breed" class="form-control" placeholder="e.g. Golden Retriever" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Species</label>
                        <select name="id_species" class="form-select">
                            <option value="">— No Species —</option>
                            <?php foreach($all_species as $sp): ?>
                                <option value="<?= $sp['id_species'] ?>"><?= htmlspecialchars($sp['nama_species']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal" style="border-radius:8px;font-size:13px">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="border-radius:8px;font-size:13px;font-weight:600"><i class="bi bi-plus-lg me-1"></i> Add Breed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Breed -->
<div class="modal fade form-modal" id="editBreedModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Breed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="edit_breed" value="1">
                <input type="hidden" name="id_breed" id="edit-breed-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Breed Name <span class="text-danger">*</span></label>
                        <input type="text" name="nama_breed" id="edit-breed-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Species</label>
                        <select name="id_species" id="edit-breed-species" class="form-select">
                            <option value="">— No Species —</option>
                            <?php foreach($all_species as $sp): ?>
                                <option value="<?= $sp['id_species'] ?>"><?= htmlspecialchars($sp['nama_species']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal" style="border-radius:8px;font-size:13px">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm" style="border-radius:8px;font-size:13px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Species -->
<div class="modal fade form-modal" id="addSpeciesModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Add New Species</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="add_species" value="1">
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Species Name <span class="text-danger">*</span></label>
                        <input type="text" name="nama_species" class="form-control" placeholder="e.g. Dog" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal" style="border-radius:8px;font-size:13px">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="border-radius:8px;font-size:13px;font-weight:600"><i class="bi bi-plus-lg me-1"></i> Add Species</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Species -->
<div class="modal fade form-modal" id="editSpeciesModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Species</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="edit_species" value="1">
                <input type="hidden" name="id_species" id="edit-species-id">
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Species Name <span class="text-danger">*</span></label>
                        <input type="text" name="nama_species" id="edit-species-name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal" style="border-radius:8px;font-size:13px">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm" style="border-radius:8px;font-size:13px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Breed Confirm -->
<div class="confirm-overlay" id="deleteBreedOverlay">
    <div class="confirm-modal">
        <div class="c-icon"><i class="bi bi-trash3-fill"></i></div>
        <h5>Delete Breed?</h5>
        <p id="delete-breed-msg">This action cannot be undone.</p>
        <form method="POST" id="deleteBreedForm">
            <input type="hidden" name="delete_breed" value="1">
            <input type="hidden" name="id_breed" id="delete-breed-id">
            <button type="submit" class="btn-del-yes"><i class="bi bi-trash me-2"></i> Yes, Delete</button>
        </form>
        <button class="btn-del-no" onclick="closeDeleteBreed()">Cancel</button>
    </div>
</div>

<!-- Delete Species Confirm -->
<div class="confirm-overlay" id="deleteSpeciesOverlay">
    <div class="confirm-modal">
        <div class="c-icon"><i class="bi bi-trash3-fill"></i></div>
        <h5>Delete Species?</h5>
        <p id="delete-species-msg">All breeds linked to this species will become unassigned.</p>
        <form method="POST" id="deleteSpeciesForm">
            <input type="hidden" name="delete_species" value="1">
            <input type="hidden" name="id_species" id="delete-species-id">
            <button type="submit" class="btn-del-yes"><i class="bi bi-trash me-2"></i> Yes, Delete</button>
        </form>
        <button class="btn-del-no" onclick="closeDeleteSpecies()">Cancel</button>
    </div>
</div>

<!-- Toast -->
<div class="toast-notif" id="toastNotif">
    <i class="bi bi-check-circle-fill"></i>
    <span id="toastMsg">Done!</span>
</div>

<!-- Logout Modal -->
<div class="modal fade form-modal" id="logoutModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="clearLogoutForm()"></button>
            </div>
            <form method="POST" action="admin_breed.php" id="logoutForm">
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
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="pw-wrapper">
                            <input type="password" name="logout_password" id="logout-pw" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="pw-toggle" onclick="togglePwVisibility()">
                                <i class="bi bi-eye" id="pw-toggle-icon"></i>
                            </button>
                        </div>
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

/* ── Tabs ── */
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('show'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('show');
    btn.classList.add('active');
}
window.addEventListener('DOMContentLoaded', () => {
    if (location.hash === '#tab-species') {
        switchTab('species', document.querySelectorAll('.tab-btn')[1]);
    }
});

/* ── Detail row helper ── */
function dRow(label, value) {
    return `<div class="detail-row"><span class="detail-label">${label}</span><span class="detail-value">${value||'—'}</span></div>`;
}

/* ── Breed Detail ── */
let _currentBreed = null;
function openDetailBreed(br) {
    _currentBreed = br;
    document.getElementById('bd-name').textContent    = br.nama_breed || '—';
    document.getElementById('bd-species').textContent = br.nama_species || 'Unassigned';
    document.getElementById('bd-ms-pets').textContent = br.total_pets ?? 0;
    document.getElementById('bd-ms-id').textContent   = '#' + br.id_breed;
    document.getElementById('bd-info').innerHTML =
        dRow('Breed Name', br.nama_breed) +
        dRow('Species',    br.nama_species || 'Unassigned') +
        dRow('Total Pets', (br.total_pets ?? 0) + ' pets');
    new bootstrap.Modal(document.getElementById('breedDetailModal')).show();
}
function switchToEdit() {
    bootstrap.Modal.getInstance(document.getElementById('breedDetailModal')).hide();
    setTimeout(() => openEditBreed(_currentBreed), 300);
}

/* ── Add / Edit Breed ── */
function openAddBreed() { new bootstrap.Modal(document.getElementById('addBreedModal')).show(); }
function openEditBreed(br) {
    document.getElementById('edit-breed-id').value      = br.id_breed;
    document.getElementById('edit-breed-name').value    = br.nama_breed;
    document.getElementById('edit-breed-species').value = br.id_species ?? '';
    new bootstrap.Modal(document.getElementById('editBreedModal')).show();
}

/* ── Delete Breed ── */
function openDeleteBreed(id, name) {
    document.getElementById('delete-breed-id').value        = id;
    document.getElementById('delete-breed-msg').textContent = `Are you sure you want to delete "${name}"? This cannot be undone.`;
    document.getElementById('deleteBreedOverlay').classList.add('show');
}
function closeDeleteBreed() { document.getElementById('deleteBreedOverlay').classList.remove('show'); }
document.getElementById('deleteBreedOverlay').addEventListener('click', e => { if(e.target===e.currentTarget) closeDeleteBreed(); });

/* ── Add / Edit Species ── */
function openAddSpecies() { new bootstrap.Modal(document.getElementById('addSpeciesModal')).show(); }
function openEditSpecies(id, name) {
    document.getElementById('edit-species-id').value   = id;
    document.getElementById('edit-species-name').value = name;
    new bootstrap.Modal(document.getElementById('editSpeciesModal')).show();
}

/* ── Delete Species ── */
function openDeleteSpecies(id, name, breedCount) {
    document.getElementById('delete-species-id').value = id;
    const warning = breedCount > 0
        ? `"${name}" has ${breedCount} breed(s) linked. They will become unassigned.`
        : `Are you sure you want to delete "${name}"?`;
    document.getElementById('delete-species-msg').textContent = warning;
    document.getElementById('deleteSpeciesOverlay').classList.add('show');
}
function closeDeleteSpecies() { document.getElementById('deleteSpeciesOverlay').classList.remove('show'); }
document.getElementById('deleteSpeciesOverlay').addEventListener('click', e => { if(e.target===e.currentTarget) closeDeleteSpecies(); });

/* ── Toast ── */
function showToast(msg) {
    const t = document.getElementById('toastNotif');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

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