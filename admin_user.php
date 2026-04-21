<?php
session_start();
require_once 'config/koneksi.php';

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

$search = isset($_GET['q']) && $_GET['q'] !== '' ? trim($_GET['q']) : null;

$where_parts = ["u.role = 'user'"];
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where_parts[] = "(CONCAT(u.nama_depan,' ',u.nama_belakang) LIKE '%$s%' OR u.email LIKE '%$s%' OR u.no_telepon LIKE '%$s%')";
}
$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

$per_page   = 10;
$current_pg = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($current_pg - 1) * $per_page;
$total_rows  = db_val($conn, "SELECT COUNT(*) FROM user u $where_sql");
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$users = db_rows($conn, "
    SELECT u.id_user, u.nama_depan, u.nama_belakang, u.email, u.no_telepon, u.alamat, u.created_at,
           COUNT(DISTINCT r.id_reservation) AS total_reservasi,
           COUNT(DISTINCT p.id_pet)         AS total_pets,
           COALESCE(SUM(pay.total_bayar), 0) AS total_spent
    FROM user u
    LEFT JOIN reservations r ON u.id_user = r.id_user
    LEFT JOIN pets p         ON u.id_user = p.id_user
    LEFT JOIN payments pay   ON r.id_reservation = pay.id_reservation AND pay.status_bayar = 'paid'
    $where_sql
    GROUP BY u.id_user
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
");

$stat_total  = db_val($conn, "SELECT COUNT(*) FROM user WHERE role='user'");
$stat_new    = db_val($conn, "SELECT COUNT(*) FROM user WHERE role='user' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stat_active = db_val($conn, "SELECT COUNT(DISTINCT r.id_user) FROM reservations r JOIN user u ON r.id_user=u.id_user WHERE u.role='user'");
$pending     = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='pending'");
$current_page_file = basename($_SERVER['PHP_SELF']);

function pg_qs($page, $search) {
    return '?' . http_build_query(array_filter(['q' => $search, 'page' => $page]));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users — CatDogKu Admin</title>
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

/* ── Stat Cards grid ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card { border-radius:15px; border:none; transition:transform .3s,box-shadow .3s; cursor:default; }
.stat-card:hover { transform:translateY(-5px); box-shadow:0 12px 20px rgba(0,0,0,.08)!important; }
.icon-box { width:55px; height:55px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:26px; flex-shrink:0; }

/* ── Toolbar ── */
.toolbar { background:#fff; border-radius:14px; padding:18px 22px; box-shadow:0 2px 10px rgba(0,0,0,.04); display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar .form-control { border-radius:10px; border:1.5px solid #e9ecef; font-size:13px; height:40px; }
.toolbar .form-control:focus { border-color:#3498db; box-shadow:0 0 0 3px rgba(52,152,219,.12); }
.btn-filter { height:40px; border-radius:10px; font-size:13px; font-weight:600; padding:0 18px; display:inline-flex; align-items:center; gap:4px; }

/* ── Table card ── */
.table-card { background:#fff; border-radius:16px; box-shadow:0 5px 20px rgba(0,0,0,.04); overflow:hidden; }
.table-card-header { padding:18px 24px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
.table > thead > tr > th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#6c757d; background:#f8f9fa; border-bottom:1px solid #e9ecef; padding:13px 16px; white-space:nowrap; }
.table > tbody > tr > td { padding:13px 16px; vertical-align:middle; font-size:13.5px; border-bottom:1px solid #f5f5f5; }
.table > tbody > tr:last-child > td { border-bottom:none; }
.table > tbody > tr:hover > td { background:#f8f9fa; }

/* ── Avatar ── */
.avatar { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:#fff; flex-shrink:0; }

/* ── Action button ── */
.btn-action { padding:5px 12px; font-size:12px; border-radius:8px; font-weight:600; border:none; cursor:pointer; transition:opacity .2s; background:#eaf4fd; color:#2980b9; display:inline-flex; align-items:center; gap:4px; }
.btn-action:hover { opacity:.8; }

/* ── Pagination ── */
.pagination .page-link { border-radius:8px!important; margin:0 2px; font-size:13px; font-weight:600; color:#3498db; border:1.5px solid #e9ecef; }
.pagination .page-item.active .page-link { background:#3498db; border-color:#3498db; color:#fff; }
.pagination .page-link:hover { background:#eaf4fd; color:#2980b9; }

/* ── Modal ── */
.modal-content { border-radius:18px; border:none; }
.modal-header  { border-bottom:1px solid #f0f0f0; padding:20px 24px; }
.modal-body    { padding:24px; }
.detail-row { display:flex; gap:8px; padding:10px 0; border-bottom:1px solid #f5f5f5; font-size:13.5px; }
.detail-row:last-child { border-bottom:none; }
.detail-label { width:140px; flex-shrink:0; color:#6c757d; font-weight:600; font-size:13px; }
.detail-value { color:#1a1a2e; }
.mini-stat { background:#f8f9fa; border-radius:12px; padding:14px 16px; text-align:center; }
.mini-stat .num { font-size:22px; font-weight:700; line-height:1.1; color:#1a1a2e; }
.mini-stat .lbl { font-size:11px; color:#6c757d; font-weight:500; margin-top:2px; }

/* ── Mobile user cards ── */
.user-mobile-card { background:#fff; border-radius:12px; padding:14px 16px; margin-bottom:10px; box-shadow:0 2px 8px rgba(0,0,0,.05); border:1px solid #f0f0f0; }
.user-mobile-card .card-name  { font-size:14px; font-weight:600; color:#1a1a2e; margin-bottom:2px; }
.user-mobile-card .card-email { font-size:12px; color:#6c757d; margin-bottom:8px; }
.user-mobile-card .card-meta  { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px; }
.user-mobile-card .card-actions { display:flex; gap:6px; }

/* ── Logout modal ── */
.logout-icon-wrap { width:64px; height:64px; border-radius:18px; background:rgba(231,76,60,.1); display:flex; align-items:center; justify-content:center; font-size:28px; color:#e74c3c; margin:0 auto 6px; }
.logout-modal-error { color:#dc3545; font-size:14px; margin-top:8px; display:none; }
.pw-wrapper { position:relative; }
.pw-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:#6c757d; cursor:pointer; font-size:16px; padding:4px; }
.pw-toggle:hover { color:#495057; }

/* ════════════════════════════════
   RESPONSIVE
════════════════════════════════ */
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

    /* Toolbar stack */
    .toolbar { padding:14px; gap:8px; }
    .toolbar .flex-grow-1 { min-width:100% !important; order:-1; }
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
    .stats-grid { grid-template-columns:1fr 1fr; gap:8px; }
    .page-inner { padding:12px; }
    .stat-card .card-body { padding:12px; }
    .stat-card h3 { font-size:1.3rem !important; }
    .icon-box { width:42px!important; height:42px!important; font-size:20px!important; }
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
        <h2 class="fw-bold text-dark mb-0">Users</h2>
        <a href="admin_reserve.php?status=pending" class="position-relative text-decoration-none text-secondary">
            <i class="bi bi-bell fs-5"></i>
            <?php if($pending > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px"><?= $pending ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Header mobile -->
    <div class="d-lg-none mb-3">
        <h4 class="fw-bold text-dark mb-0">Users</h4>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stats-grid">
        <div class="card stat-card shadow-sm p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">Total Users</p>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size:1.5rem"><?= number_format($stat_total) ?></h3>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">Registered customers</p>
                </div>
                <div class="icon-box bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>
        <div class="card stat-card shadow-sm p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">New This Month</p>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size:1.5rem"><?= number_format($stat_new) ?></h3>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">Last 30 days</p>
                </div>
                <div class="icon-box bg-success bg-opacity-10 text-success">
                    <i class="bi bi-person-plus"></i>
                </div>
            </div>
        </div>
        <div class="card stat-card shadow-sm p-2">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted fw-semibold mb-1" style="font-size:12px">Active Users</p>
                    <h3 class="fw-bold mb-0 text-dark" style="font-size:1.5rem"><?= number_format($stat_active) ?></h3>
                    <p class="text-muted mb-0 d-none d-sm-block" style="font-size:11px">Have reserved</p>
                </div>
                <div class="icon-box bg-info bg-opacity-10 text-info">
                    <i class="bi bi-person-check"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Toolbar ── -->
    <form method="GET" action="admin_user.php">
        <div class="toolbar mb-4">
            <div class="flex-grow-1" style="min-width:200px">
                <div class="input-group" style="height:40px">
                    <span class="input-group-text bg-white border-end-0"
                          style="border-radius:10px 0 0 10px;border:1.5px solid #e9ecef;border-right:none">
                        <i class="bi bi-search text-muted" style="font-size:13px"></i>
                    </span>
                    <input type="text" name="q" class="form-control border-start-0"
                           style="border-radius:0 10px 10px 0;border:1.5px solid #e9ecef;border-left:none"
                           placeholder="Search name, email, phone…"
                           value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-filter">
                <i class="bi bi-search me-1"></i> Search
            </button>
            <?php if($search): ?>
                <a href="admin_user.php" class="btn btn-filter" style="border:1.5px solid #dee2e6;color:#6c757d;background:#fff;">
                    <i class="bi bi-x-lg me-1"></i> Reset
                </a>
            <?php endif; ?>
            <span class="ms-auto text-muted" style="font-size:13px;white-space:nowrap">
                <b><?= $total_rows ?></b> users found
            </span>
        </div>
    </form>

    <!-- ── Table card ── -->
    <div class="table-card mb-4">
        <div class="table-card-header">
            <h5 class="fw-bold mb-0" style="font-size:15px">All Users</h5>
            <span class="text-muted pagination-info" style="font-size:13px">Page <?= $current_pg ?> of <?= $total_pages ?></span>
        </div>

        <!-- DESKTOP TABLE -->
        <div class="table-responsive desktop-table">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Reservations</th>
                        <th>Pets</th>
                        <th>Total Spent</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($users)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-people fs-2 d-block mb-2 text-secondary"></i>No users found
                    </td></tr>
                <?php else:
                $avatar_colors = ['#3498db','#e74c3c','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#16a085'];
                foreach($users as $i => $u):
                    $initials = strtoupper(substr($u['nama_depan'],0,1) . substr($u['nama_belakang'],0,1));
                    $color    = $avatar_colors[$i % count($avatar_colors)];
                ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar" style="background:<?= $color ?>"><?= htmlspecialchars($initials) ?></div>
                            <div class="fw-semibold" style="font-size:13.5px">
                                <?= htmlspecialchars($u['nama_depan'] . ' ' . $u['nama_belakang']) ?>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px"><?= htmlspecialchars($u['email']) ?></td>
                    <td style="font-size:13px"><?= htmlspecialchars($u['no_telepon'] ?? '—') ?></td>
                    <td>
                        <span class="badge bg-info bg-opacity-10 text-info fw-semibold" style="font-size:12px;padding:4px 10px">
                            <?= $u['total_reservasi'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-warning bg-opacity-10 text-warning fw-semibold" style="font-size:12px;padding:4px 10px">
                            <?= $u['total_pets'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="fw-bold text-success" style="font-size:13px">
                            Rp <?= number_format($u['total_spent'],0,',','.') ?>
                        </span>
                    </td>
                    <td style="font-size:12px;color:#6c757d"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <button class="btn-action"
                            onclick="openDetail(<?= htmlspecialchars(json_encode($u),ENT_QUOTES) ?>, '<?= $color ?>', '<?= htmlspecialchars($initials) ?>')">
                            <i class="bi bi-eye-fill"></i> Details
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- MOBILE CARDS -->
        <div class="mobile-cards p-3">
            <?php if(empty($users)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-people fs-2 d-block mb-2 text-secondary"></i>No users found
                </div>
            <?php else:
            $avatar_colors = ['#3498db','#e74c3c','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#16a085'];
            foreach($users as $i => $u):
                $initials = strtoupper(substr($u['nama_depan'],0,1) . substr($u['nama_belakang'],0,1));
                $color    = $avatar_colors[$i % count($avatar_colors)];
            ?>
            <div class="user-mobile-card">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="avatar" style="background:<?= $color ?>;width:40px;height:40px;font-size:15px">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                    <div>
                        <div class="card-name"><?= htmlspecialchars($u['nama_depan'] . ' ' . $u['nama_belakang']) ?></div>
                        <div class="card-email"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                </div>
                <div class="card-meta">
                    <span class="badge bg-info bg-opacity-10 text-info fw-semibold" style="font-size:11px">
                        <?= $u['total_reservasi'] ?> reservations
                    </span>
                    <span class="badge bg-warning bg-opacity-10 text-warning fw-semibold" style="font-size:11px">
                        <?= $u['total_pets'] ?> pets
                    </span>
                    <span class="badge bg-success bg-opacity-10 text-success fw-semibold" style="font-size:11px">
                        Rp <?= number_format($u['total_spent'],0,',','.') ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:11px;color:#9ca3af"><?= date('d M Y', strtotime($u['created_at'])) ?></span>
                    <button class="btn-action"
                        onclick="openDetail(<?= htmlspecialchars(json_encode($u),ENT_QUOTES) ?>, '<?= $color ?>', '<?= htmlspecialchars($initials) ?>')">
                        <i class="bi bi-eye-fill"></i> Details
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
                        <a class="page-link" href="<?= pg_qs($current_pg-1,$search) ?>">
                            <i class="bi bi-chevron-left" style="font-size:11px"></i>
                        </a>
                    </li>
                    <?php
                    $from=max(1,$current_pg-2); $to=min($total_pages,$current_pg+2);
                    if($from>1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    for($pg=$from;$pg<=$to;$pg++):
                    ?>
                        <li class="page-item <?= $pg===$current_pg?'active':'' ?>">
                            <a class="page-link" href="<?= pg_qs($pg,$search) ?>"><?= $pg ?></a>
                        </li>
                    <?php endfor;
                    if($to<$total_pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    ?>
                    <li class="page-item <?= $current_pg>=$total_pages?'disabled':'' ?>">
                        <a class="page-link" href="<?= pg_qs($current_pg+1,$search) ?>">
                            <i class="bi bi-chevron-right" style="font-size:11px"></i>
                        </a>
                    </li>
                </ul>
            </nav>
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
                <div class="d-flex align-items-center gap-3">
                    <div id="modal-avatar" class="avatar" style="width:48px;height:48px;font-size:17px"></div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modal-name"></h5>
                        <p class="text-muted mb-0" id="modal-joined" style="font-size:12px"></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="num" id="ms-reservasi">0</div>
                            <div class="lbl">Reservations</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="num" id="ms-pets">0</div>
                            <div class="lbl">Pets</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="num text-success" id="ms-spent" style="font-size:16px"></div>
                            <div class="lbl">Total Spent</div>
                        </div>
                    </div>
                </div>
                <p class="fw-bold text-muted mb-3" style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">
                    Contact Information
                </p>
                <div id="modal-info"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:16px 24px">
                <a id="modal-reservasi-link" href="#" class="btn btn-primary btn-sm"
                   style="border-radius:8px;font-size:13px;font-weight:600">
                    View Reservations
                </a>
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal"
                        style="border-radius:8px;font-size:13px">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ LOGOUT MODAL ══ -->
<div class="modal fade" id="logoutModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="clearLogoutForm()"></button>
            </div>
            <form method="POST" action="admin_user.php" id="logoutForm">
                <input type="hidden" name="confirm_logout" value="1">
                <div class="modal-body pt-2">
                    <div class="text-center mb-4">
                        <div class="logout-icon-wrap"><i class="bi bi-box-arrow-left"></i></div>
                        <h5 class="fw-bold mt-3 mb-1" style="font-size:16px;color:#1a1a2e">Log Out Confirmation</h5>
                        <p class="text-muted mb-0" style="font-size:13px">Enter your password to log out of this session.</p>
                    </div>
                    <?php if(!empty($logout_error)): ?>
                    <div style="background:#fdecea;border:1.5px solid #f5c6c6;border-radius:10px;padding:10px 14px;font-size:13px;color:#922b21;display:flex;align-items:center;gap:8px;margin-bottom:16px">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <?= htmlspecialchars($logout_error) ?>
                    </div>
                    <?php endif; ?>
                    <div id="logout-js-error" style="display:none;background:#fdecea;border:1.5px solid #f5c6c6;border-radius:10px;padding:10px 14px;font-size:13px;color:#922b21;align-items:center;gap:8px;margin-bottom:16px">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span id="logout-js-error-msg">Password is required.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:600;color:#555">Password <span class="text-danger">*</span></label>
                        <div class="pw-wrapper">
                            <input type="password" name="logout_password" id="logout-pw"
                                   class="form-control" placeholder="Enter your password" required
                                   autocomplete="current-password" style="border-radius:10px;border:1.5px solid #e9ecef;padding:10px 44px 10px 14px">
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

/* ── Detail Modal ── */
function detailRow(label, value) {
    return `<div class="detail-row">
        <span class="detail-label">${label}</span>
        <span class="detail-value">${value || '—'}</span>
    </div>`;
}

function openDetail(u, color, initials) {
    const av = document.getElementById('modal-avatar');
    av.textContent       = initials;
    av.style.background  = color;

    document.getElementById('modal-name').textContent =
        (u.nama_depan || '') + ' ' + (u.nama_belakang || '');
    document.getElementById('modal-joined').textContent =
        'Joined: ' + (u.created_at ? new Date(u.created_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) : '—');

    document.getElementById('ms-reservasi').textContent = u.total_reservasi ?? 0;
    document.getElementById('ms-pets').textContent      = u.total_pets ?? 0;
    document.getElementById('ms-spent').textContent     =
        'Rp ' + Number(u.total_spent || 0).toLocaleString('id-ID');

    document.getElementById('modal-info').innerHTML =
        detailRow('Full Name', (u.nama_depan || '') + ' ' + (u.nama_belakang || '')) +
        detailRow('Email',     u.email) +
        detailRow('Phone',     u.no_telepon) +
        detailRow('Address',   u.alamat);

    document.getElementById('modal-reservasi-link').href =
        'admin_reserve.php?q=' + encodeURIComponent((u.nama_depan || '') + ' ' + (u.nama_belakang || ''));

    new bootstrap.Modal(document.getElementById('detailModal')).show();
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