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

// ── Handle toggle active status (POST) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    $id         = (int)$_POST['id_staff'];
    $new_status = (int)$_POST['new_status'];
    mysqli_query($conn, "UPDATE staffs SET is_active=$new_status WHERE id_staff=$id");
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter([
        'q'      => $_GET['q']      ?? null,
        'status' => $_GET['status'] ?? null,
        'page'   => $_GET['page']   ?? null,
    ])));
    exit;
}

// ── Filters ───────────────────────────────────────────────────────────────────
$search        = isset($_GET['q'])      && $_GET['q']      !== '' ? trim($_GET['q'])      : null;
$filter_status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status']       : null;

$where_parts = ['1=1'];
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where_parts[] = "(st.nama_staff LIKE '%$s%' OR st.jabatan LIKE '%$s%' OR st.no_telepon LIKE '%$s%')";
}
if ($filter_status === 'active')   $where_parts[] = "st.is_active = 1";
if ($filter_status === 'inactive') $where_parts[] = "st.is_active = 0";
$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

// ── Pagination ────────────────────────────────────────────────────────────────
$per_page   = 10;
$current_pg = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($current_pg - 1) * $per_page;

$total_rows  = db_val($conn, "SELECT COUNT(*) FROM staffs st $where_sql");
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// ── Staff list ────────────────────────────────────────────────────────────────
$staffs = db_rows($conn, "
    SELECT
        st.id_staff,
        st.nama_staff,
        st.jabatan,
        st.no_telepon,
        st.is_active,
        sp.nama_specialization,
        COUNT(DISTINCT r.id_reservation) AS total_handled
    FROM staffs st
    LEFT JOIN specializations sp ON st.id_specialization = sp.id_specialization
    LEFT JOIN reservation_details rd ON st.id_staff = rd.id_staff
    LEFT JOIN reservations r ON rd.id_reservation = r.id_reservation AND r.status = 'completed'
    $where_sql
    GROUP BY st.id_staff
    ORDER BY st.is_active DESC, st.nama_staff ASC
    LIMIT $per_page OFFSET $offset
");

// ── Summary stats ─────────────────────────────────────────────────────────────
$stat_total    = db_val($conn, "SELECT COUNT(*) FROM staffs");
$stat_active   = db_val($conn, "SELECT COUNT(*) FROM staffs WHERE is_active=1");
$stat_inactive = db_val($conn, "SELECT COUNT(*) FROM staffs WHERE is_active=0");

$pending           = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='pending'");
$current_page_file = basename($_SERVER['PHP_SELF']);

// ── Pagination helper ─────────────────────────────────────────────────────────
function pg_qs($page, $search, $filter_status) {
    return '?' . http_build_query(array_filter([
        'q'      => $search,
        'status' => $filter_status,
        'page'   => $page,
    ]));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff — CatDogKu Admin</title>
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
.toolbar .form-control,
.toolbar .form-select {
    border-radius: 10px;
    border: 1.5px solid #e9ecef;
    font-size: 13px;
    height: 40px;
}
.toolbar .form-control:focus,
.toolbar .form-select:focus {
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
    gap: 4px;
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

/* ── Action buttons ───────────────────────────────────────────────────────── */
.btn-action {
    padding: 5px 12px;
    font-size: 12px;
    border-radius: 8px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: opacity .2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-action:hover { opacity: .8; }
.btn-view     { background: #eaf4fd; color: #2980b9; }
.btn-activate { background: #d5f5e3; color: #145a32; }
.btn-deact    { background: #fdecea; color: #922b21; }

/* ── Status badge pill ────────────────────────────────────────────────────── */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border-radius: 20px;
    padding: 4px 11px;
    font-size: 11.5px;
    font-weight: 600;
}
.pill-active   { background: #d5f5e3; color: #145a32; }
.pill-inactive { background: #fdecea; color: #922b21; }

/* ── Specialization tag ───────────────────────────────────────────────────── */
.spec-tag {
    display: inline-block;
    background: #f0eafd;
    color: #6d28d9;
    border-radius: 6px;
    padding: 2px 9px;
    font-size: 11.5px;
    font-weight: 600;
    white-space: nowrap;
}

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
    width: 160px;
    flex-shrink: 0;
    color: #6c757d;
    font-weight: 600;
    font-size: 13px;
}
.detail-value { color: #1a1a2e; }

/* ── Mini stat modal ──────────────────────────────────────────────────────── */
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
    <a href="services.php" class="<?= $current_page_file==='services.php'?'active':'' ?>">
        <i class="bi bi-stars me-2 fs-5"></i> Services
    </a>
    <a href="breeds.php" class="<?= $current_page_file==='breeds.php'?'active':'' ?>">
        <i class="bi bi-bug me-2 fs-5"></i> Breeds
    </a>
    <a href="schedules.php" class="<?= $current_page_file==='schedules.php'?'active':'' ?>">
        <i class="bi bi-clock me-2 fs-5"></i> Staff Schedules
    </a>

    <div class="mt-4 pt-3 border-top border-secondary">
        <a href="settings.php" class="<?= $current_page_file==='settings.php'?'active':'' ?>">
            <i class="bi bi-gear me-2 fs-5"></i> Settings
        </a>
        <a href="logout.php" class="logout-link text-danger fw-bold">
            <i class="bi bi-box-arrow-left me-2 fs-5"></i> Logout
        </a>
    </div>
</div>

<!-- ═══════════════════════════ MAIN CONTENT ═══════════════════════════════ -->
<div class="main-content p-4 p-md-5">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Staff</h2>
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

    <!-- ── Stat Cards ─────────────────────────────────────────────────────── -->
    <div class="row g-4 mb-4">
        <div class="col-md-4 col-sm-6">
            <div class="card stat-card shadow-sm h-100 p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">Total Staff</p>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($stat_total) ?></h3>
                        <p class="text-muted mb-0" style="font-size:12px">All registered staff</p>
                    </div>
                    <div class="icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-person-badge"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card stat-card shadow-sm h-100 p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">Active Staff</p>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($stat_active) ?></h3>
                        <p class="text-muted mb-0" style="font-size:12px">Ready to serve customers</p>
                    </div>
                    <div class="icon-box bg-success bg-opacity-10 text-success">
                        <i class="bi bi-person-check"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card stat-card shadow-sm h-100 p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">Inactive Staff</p>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($stat_inactive) ?></h3>
                        <p class="text-muted mb-0" style="font-size:12px">Currently not active</p>
                    </div>
                    <div class="icon-box bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-person-dash"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Toolbar / Filters ──────────────────────────────────────────────── -->
    <form method="GET" action="admin_staff.php">
        <div class="toolbar mb-4">
            <!-- Search -->
            <div class="flex-grow-1" style="min-width:200px">
                <div class="input-group" style="height:40px">
                    <span class="input-group-text bg-white border-end-0"
                          style="border-radius:10px 0 0 10px;border:1.5px solid #e9ecef;border-right:none">
                        <i class="bi bi-search text-muted" style="font-size:13px"></i>
                    </span>
                    <input type="text" name="q" class="form-control border-start-0"
                           style="border-radius:0 10px 10px 0;border:1.5px solid #e9ecef;border-left:none"
                           placeholder="Search name, position, phone…"
                           value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
            </div>

            <!-- Status filter -->
            <select name="status" class="form-select" style="width:auto;min-width:140px">
                <option value="">All Status</option>
                <option value="active"   <?= $filter_status==='active'  ?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $filter_status==='inactive'?'selected':'' ?>>Inactive</option>
            </select>

            <button type="submit" class="btn btn-primary btn-filter">
                <i class="bi bi-search"></i> Search
            </button>

            <?php if($search || $filter_status): ?>
                <a href="admin_staff.php" class="btn btn-filter"
                   style="border:1.5px solid #dee2e6;color:#6c757d;background:#fff;">
                    <i class="bi bi-x-lg"></i> Reset
                </a>
            <?php endif; ?>

            <span class="ms-auto text-muted" style="font-size:13px;white-space:nowrap">
                <b><?= $total_rows ?></b> staff found
            </span>
        </div>
    </form>

    <!-- ── Table ──────────────────────────────────────────────────────────── -->
    <div class="table-card mb-4">
        <div class="table-card-header">
    <h5 class="fw-bold mb-0" style="font-size:15px">
        All Staff
    </h5>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted" style="font-size:13px">Page <?= $current_pg ?> of <?= $total_pages ?></span>
        <a href="admin_addstaff.php" class="btn btn-primary btn-sm"
           style="border-radius:9px;font-size:13px;font-weight:600;padding:6px 16px;display:inline-flex;align-items:center;gap:5px">
            <i class="bi bi-plus-lg"></i> Add Staff
        </a>
    </div>
</div>

        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Position</th>
                        <th>Specialization</th>
                        <th>Phone</th>
                        <th>Completed Jobs</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($staffs)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-person-badge fs-2 d-block mb-2 text-secondary"></i>
                            No staff found
                        </td>
                    </tr>
                <?php else: ?>
                <?php
                $avatar_colors = ['#3498db','#e74c3c','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#16a085'];
                foreach($staffs as $i => $st):
                    $words    = explode(' ', trim($st['nama_staff']));
                    $initials = strtoupper(substr($words[0],0,1) . (isset($words[1]) ? substr($words[1],0,1) : ''));
                    $color    = $avatar_colors[$i % count($avatar_colors)];
                ?>
                <tr>
                    <!-- Staff name + avatar -->
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar" style="background:<?= $color ?>">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                            <div class="fw-semibold" style="font-size:13.5px">
                                <?= htmlspecialchars($st['nama_staff']) ?>
                            </div>
                        </div>
                    </td>

                    <!-- Position / Jabatan -->
                    <td style="font-size:13px">
                        <?= htmlspecialchars($st['jabatan'] ?? '—') ?>
                    </td>

                    <!-- Specialization -->
                    <td>
                        <?php if($st['nama_specialization']): ?>
                            <span class="spec-tag"><?= htmlspecialchars($st['nama_specialization']) ?></span>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:12px">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Phone -->
                    <td style="font-size:13px">
                        <?= htmlspecialchars($st['no_telepon'] ?? '—') ?>
                    </td>

                    <!-- Completed jobs -->
                    <td>
                        <span class="badge bg-info bg-opacity-10 text-info fw-semibold" style="font-size:12px;padding:4px 10px">
                            <?= $st['total_handled'] ?> jobs
                        </span>
                    </td>

                    <!-- Status -->
                    <td>
                        <?php if($st['is_active']): ?>
                            <span class="status-pill pill-active">
                                <i class="bi bi-circle-fill" style="font-size:7px"></i> Active
                            </span>
                        <?php else: ?>
                            <span class="status-pill pill-inactive">
                                <i class="bi bi-circle-fill" style="font-size:7px"></i> Inactive
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- Actions -->
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <!-- View Detail -->
                            <button class="btn-action btn-view"
                                onclick="openDetail(<?= htmlspecialchars(json_encode($st), ENT_QUOTES) ?>, '<?= $color ?>', '<?= htmlspecialchars($initials) ?>')">
                                <i class="bi bi-eye-fill"></i> Details
                            </button>

                            <!-- Toggle active/inactive -->
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="id_staff"    value="<?= $st['id_staff'] ?>">
                                <input type="hidden" name="toggle_active" value="1">
                                <?php if($st['is_active']): ?>
                                    <input type="hidden" name="new_status" value="0">
                                    <button type="submit" class="btn-action btn-deact"
                                            onclick="return confirm('Deactivate <?= addslashes(htmlspecialchars($st['nama_staff'])) ?>?')">
                                        <i class="bi bi-pause-circle"></i> Deactivate
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="new_status" value="1">
                                    <button type="submit" class="btn-action btn-activate">
                                        <i class="bi bi-play-circle"></i> Activate
                                    </button>
                                <?php endif; ?>
                            </form>
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
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top" style="background:#fafafa">
            <span class="text-muted" style="font-size:13px">
                Showing <?= ($offset+1) ?>–<?= min($offset+$per_page, $total_rows) ?> of <?= $total_rows ?>
            </span>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $current_pg<=1?'disabled':'' ?>">
                        <a class="page-link" href="<?= pg_qs($current_pg-1, $search, $filter_status) ?>">
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
                            <a class="page-link" href="<?= pg_qs($pg, $search, $filter_status) ?>"><?= $pg ?></a>
                        </li>
                    <?php endfor;
                    if($to<$total_pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    ?>
                    <li class="page-item <?= $current_pg>=$total_pages?'disabled':'' ?>">
                        <a class="page-link" href="<?= pg_qs($current_pg+1, $search, $filter_status) ?>">
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
                    <div id="modal-avatar" class="avatar" style="width:52px;height:52px;font-size:18px"></div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modal-name"></h5>
                        <p class="text-muted mb-0" id="modal-jabatan" style="font-size:13px"></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <!-- Mini stats -->
                <div class="row g-3 mb-4">
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="num" id="ms-jobs">0</div>
                            <div class="lbl">Jobs Done</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="mini-stat">
                            <div id="ms-status" style="font-size:14px;font-weight:700;line-height:1.1;margin-bottom:4px"></div>
                            <div class="lbl">Status</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="num" id="ms-spec" style="font-size:14px"></div>
                            <div class="lbl">Specialization</div>
                        </div>
                    </div>
                </div>

                <!-- Staff Info -->
                <p class="fw-bold text-muted mb-3"
                   style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">
                    Staff Information
                </p>
                <div id="modal-info"></div>

            </div>

            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:16px 24px">
                <a id="modal-schedule-link" href="schedules.php" class="btn btn-primary btn-sm"
                   style="border-radius:8px;font-size:13px;font-weight:600">
                    <i class="bi bi-clock me-1"></i> View Schedule
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

function openDetail(st, color, initials) {
    // Avatar
    const av = document.getElementById('modal-avatar');
    av.textContent      = initials;
    av.style.background = color;

    // Name & jabatan
    document.getElementById('modal-name').textContent    = st.nama_staff || '—';
    document.getElementById('modal-jabatan').textContent = st.jabatan    || '—';

    // Mini stats
    document.getElementById('ms-jobs').textContent = st.total_handled ?? 0;

    const isActive = parseInt(st.is_active) === 1;
    document.getElementById('ms-status').innerHTML = isActive
        ? `<span style="color:#145a32">● Active</span>`
        : `<span style="color:#922b21">● Inactive</span>`;

    document.getElementById('ms-spec').textContent =
        st.nama_specialization || 'General';

    // Info rows
    document.getElementById('modal-info').innerHTML =
        detailRow('Full Name',      st.nama_staff) +
        detailRow('Position',       st.jabatan) +
        detailRow('Specialization', st.nama_specialization || 'General') +
        detailRow('Phone',          st.no_telepon) +
        detailRow('Status',         isActive ? 'Active' : 'Inactive') +
        detailRow('Completed Jobs', (st.total_handled ?? 0) + ' reservations');

    new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>
</body>
</html>