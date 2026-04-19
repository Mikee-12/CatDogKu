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

// ── Filters ──────────────────────────────────────────────────────────────────
$search = isset($_GET['q']) && $_GET['q'] !== '' ? trim($_GET['q']) : null;

$where_parts = ["u.role = 'user'"];
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where_parts[] = "(CONCAT(u.nama_depan,' ',u.nama_belakang) LIKE '%$s%'
                       OR u.email LIKE '%$s%'
                       OR u.no_telepon LIKE '%$s%')";
}
$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

// ── Pagination ────────────────────────────────────────────────────────────────
$per_page   = 10;
$current_pg = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($current_pg - 1) * $per_page;

$total_rows  = db_val($conn, "SELECT COUNT(*) FROM user u $where_sql");
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// ── User list ─────────────────────────────────────────────────────────────────
$users = db_rows($conn, "
    SELECT
        u.id_user,
        u.nama_depan,
        u.nama_belakang,
        u.email,
        u.no_telepon,
        u.alamat,
        u.created_at,
        COUNT(DISTINCT r.id_reservation) AS total_reservasi,
        COUNT(DISTINCT p.id_pet)         AS total_pets,
        COALESCE(SUM(pay.total_bayar), 0) AS total_spent
    FROM user u
    LEFT JOIN reservations r  ON u.id_user = r.id_user
    LEFT JOIN pets p          ON u.id_user = p.id_user
    LEFT JOIN payments pay    ON r.id_reservation = pay.id_reservation AND pay.status_bayar = 'paid'
    $where_sql
    GROUP BY u.id_user
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
");

// ── Summary stats ─────────────────────────────────────────────────────────────
$stat_total    = db_val($conn, "SELECT COUNT(*) FROM user WHERE role='user'");
$stat_new      = db_val($conn, "SELECT COUNT(*) FROM user WHERE role='user' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stat_active   = db_val($conn, "SELECT COUNT(DISTINCT r.id_user) FROM reservations r JOIN user u ON r.id_user=u.id_user WHERE u.role='user'");

$pending   = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='pending'");
$current_page_file = basename($_SERVER['PHP_SELF']);

// ── Pagination helper ─────────────────────────────────────────────────────────
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
/* ── Base ─────────────────────────────────────────────────────────────────── */
body {
    background-color: #f4f7f6;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0; padding: 0;
}

/* ── Sidebar ──────────────────────────────────────────────────────────────── */
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

/* ── Layout ───────────────────────────────────────────────────────────────── */
.main-content {
    margin-left: 260px;
    width: calc(100% - 260px);
    min-height: 100vh;
}

/* ── Stat Cards ───────────────────────────────────────────────────────────── */
.stat-card {
    border-radius: 15px;
    border: none;
    transition: transform .3s ease, box-shadow .3s ease;
    cursor: default;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 20px rgba(0,0,0,.08) !important;
}
.icon-box {
    width: 55px; height: 55px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    flex-shrink: 0;
}

/* ── Toolbar ──────────────────────────────────────────────────────────────── */
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
.toolbar .form-control {
    border-radius: 10px;
    border: 1.5px solid #e9ecef;
    font-size: 13px;
    height: 40px;
}
.toolbar .form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52,152,219,.12);
}
.btn-filter {
    height: 40px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    padding: 0 18px;
    display: inline-flex;
    align-items: center;
}

/* ── Table card ───────────────────────────────────────────────────────────── */
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

/* ── Avatar ───────────────────────────────────────────────────────────────── */
.avatar {
    width: 38px; height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}

/* ── Action button ────────────────────────────────────────────────────────── */
.btn-action {
    padding: 5px 12px;
    font-size: 12px;
    border-radius: 8px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: opacity .2s;
    background: #eaf4fd;
    color: #2980b9;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-action:hover { opacity: .8; }

/* ── Pagination ───────────────────────────────────────────────────────────── */
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

/* ── Modal ────────────────────────────────────────────────────────────────── */
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
    font-size: 13px;
}
.detail-value { color: #1a1a2e; }

/* ── Stat mini cards in modal ─────────────────────────────────────────────── */
.mini-stat {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 14px 16px;
    text-align: center;
}
.mini-stat .num {
    font-size: 22px;
    font-weight: 700;
    line-height: 1.1;
    color: #1a1a2e;
}
.mini-stat .lbl {
    font-size: 11px;
    color: #6c757d;
    font-weight: 500;
    margin-top: 2px;
}

/* ── Responsive ───────────────────────────────────────────────────────────── */
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
        <?php if($pending > 0): ?>
            <span class="badge bg-danger ms-auto"><?= $pending ?></span>
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
            <h2 class="fw-bold text-dark mb-0">Users</h2>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="admin_reserve.php?status=pending" class="position-relative text-decoration-none text-secondary">
                <i class="bi bi-bell fs-5"></i>
                <?php if($pending > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px"><?= $pending ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4 col-sm-6">
            <div class="card stat-card shadow-sm h-100 p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">Total Users</p>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($stat_total) ?></h3>
                        <p class="text-muted mb-0" style="font-size:12px">Registered customers</p>
                    </div>
                    <div class="icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card stat-card shadow-sm h-100 p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">New This Month</p>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($stat_new) ?></h3>
                        <p class="text-muted mb-0" style="font-size:12px">Joined last 30 days</p>
                    </div>
                    <div class="icon-box bg-success bg-opacity-10 text-success">
                        <i class="bi bi-person-plus"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card stat-card shadow-sm h-100 p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">Active Users</p>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($stat_active) ?></h3>
                        <p class="text-muted mb-0" style="font-size:12px">Have made reservations</p>
                    </div>
                    <div class="icon-box bg-info bg-opacity-10 text-info">
                        <i class="bi bi-person-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toolbar / Search -->
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
                <a href="admin_user.php" class="btn btn-filter"
                   style="border:1.5px solid #dee2e6;color:#6c757d;background:#fff;">
                    <i class="bi bi-x-lg me-1"></i> Reset
                </a>
            <?php endif; ?>

            <span class="ms-auto text-muted" style="font-size:13px;white-space:nowrap">
                <b><?= $total_rows ?></b> users found
            </span>
        </div>
    </form>

    <!-- Table -->
    <div class="table-card mb-4">
        <div class="table-card-header">
            <h5 class="fw-bold mb-0" style="font-size:15px">
                All Users
            </h5>
            <span class="text-muted" style="font-size:13px">Page <?= $current_pg ?> of <?= $total_pages ?></span>
        </div>

        <div class="table-responsive">
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
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-people fs-2 d-block mb-2 text-secondary"></i>
                            No users found
                        </td>
                    </tr>
                <?php else: ?>
                <?php
                $avatar_colors = ['#3498db','#e74c3c','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#16a085'];
                foreach($users as $i => $u):
                    $initials = strtoupper(substr($u['nama_depan'],0,1) . substr($u['nama_belakang'],0,1));
                    $color    = $avatar_colors[$i % count($avatar_colors)];
                ?>
                <tr>
                    <!-- User -->
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar" style="background:<?= $color ?>">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                            <div>
                                <div class="fw-semibold" style="font-size:13.5px">
                                    <?= htmlspecialchars($u['nama_depan'] . ' ' . $u['nama_belakang']) ?>
                                </div>
                            </div>
                        </div>
                    </td>

                    <!-- Email -->
                    <td style="font-size:13px"><?= htmlspecialchars($u['email']) ?></td>

                    <!-- Phone -->
                    <td style="font-size:13px"><?= htmlspecialchars($u['no_telepon'] ?? '—') ?></td>

                    <!-- Reservations -->
                    <td>
                        <span class="badge bg-info bg-opacity-10 text-info fw-semibold" style="font-size:12px;padding:4px 10px">
                            <?= $u['total_reservasi'] ?>
                        </span>
                    </td>

                    <!-- Pets -->
                    <td>
                        <span class="badge bg-warning bg-opacity-10 text-warning fw-semibold" style="font-size:12px;padding:4px 10px">
                            <?= $u['total_pets'] ?>
                        </span>
                    </td>

                    <!-- Total Spent -->
                    <td>
                        <span class="fw-bold text-success" style="font-size:13px">
                            Rp <?= number_format($u['total_spent'],0,',','.') ?>
                        </span>
                    </td>

                    <!-- Joined -->
                    <td style="font-size:12px;color:#6c757d">
                        <?= date('d M Y', strtotime($u['created_at'])) ?>
                    </td>

                    <!-- Action -->
                    <td>
                        <button class="btn-action"
                            onclick="openDetail(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>, '<?= $color ?>', '<?= htmlspecialchars($initials) ?>')">
                            <i class="bi bi-eye-fill"></i> Details
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top" style="background:#fafafa">
            <span class="text-muted" style="font-size:13px">
                Showing <?= ($offset+1) ?>–<?= min($offset+$per_page, $total_rows) ?> of <?= $total_rows ?>
            </span>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $current_pg<=1?'disabled':'' ?>">
                        <a class="page-link" href="<?= pg_qs($current_pg-1, $search) ?>">
                            <i class="bi bi-chevron-left" style="font-size:11px"></i>
                        </a>
                    </li>
                    <?php
                    $from = max(1, $current_pg-2);
                    $to   = min($total_pages, $current_pg+2);
                    if($from>1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    for($pg=$from; $pg<=$to; $pg++):
                    ?>
                        <li class="page-item <?= $pg===$current_pg?'active':'' ?>">
                            <a class="page-link" href="<?= pg_qs($pg, $search) ?>"><?= $pg ?></a>
                        </li>
                    <?php endfor;
                    if($to<$total_pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    ?>
                    <li class="page-item <?= $current_pg>=$total_pages?'disabled':'' ?>">
                        <a class="page-link" href="<?= pg_qs($current_pg+1, $search) ?>">
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

                <!-- Mini stats -->
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

                <!-- Contact Info -->
                <p class="fw-bold text-muted mb-3"
                   style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function detailRow(label, value) {
    return `<div class="detail-row">
        <span class="detail-label">${label}</span>
        <span class="detail-value">${value || '—'}</span>
    </div>`;
}

function openDetail(u, color, initials) {
    // Avatar
    const av = document.getElementById('modal-avatar');
    av.textContent   = initials;
    av.style.background = color;

    // Name & joined
    document.getElementById('modal-name').textContent =
        (u.nama_depan || '') + ' ' + (u.nama_belakang || '');
    document.getElementById('modal-joined').textContent =
        'Joined: ' + (u.created_at ? new Date(u.created_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) : '—');

    // Mini stats
    document.getElementById('ms-reservasi').textContent = u.total_reservasi ?? 0;
    document.getElementById('ms-pets').textContent      = u.total_pets ?? 0;
    document.getElementById('ms-spent').textContent     =
        'Rp ' + Number(u.total_spent || 0).toLocaleString('id-ID');

    // Info rows
    document.getElementById('modal-info').innerHTML =
        detailRow('Full Name',  (u.nama_depan || '') + ' ' + (u.nama_belakang || '')) +
        detailRow('Email',      u.email) +
        detailRow('Phone',      u.no_telepon) +
        detailRow('Address',    u.alamat);

    // Link to reservations filtered by user (optional — adjust query param to your needs)
    document.getElementById('modal-reservasi-link').href =
        'admin_reserve.php?q=' + encodeURIComponent((u.nama_depan || '') + ' ' + (u.nama_belakang || ''));

    new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>
</body>
</html>