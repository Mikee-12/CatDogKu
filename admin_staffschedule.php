<?php
require_once 'config/koneksi.php';

// ── Handle LOGOUT (POST) ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    $password_input = $_POST['logout_password'] ?? '';
    if (!empty($password_input)) {
        $admin_id = $_SESSION['admin_id'] ?? 0;
        $stmt = mysqli_prepare($conn, "SELECT password FROM admin WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $admin_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            if (password_verify($password_input, $row['password'])) {
                session_destroy();
                header("Location: cust_login.php");
                exit;
            } else {
                $logout_error = 'Password salah. Silakan coba lagi.';
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        $logout_error = 'Password diperlukan.';
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

$days_order = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

// ── Handle POST actions ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── DELETE SCHEDULE ───────────────────────────────────────────────────────
    if (isset($_POST['delete_schedule'])) {
        $id = (int)$_POST['id_schedule'];
        mysqli_query($conn, "DELETE FROM staff_schedules WHERE id_schedule=$id");
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter([
            'staff' => $_GET['staff'] ?? null,
            'hari'  => $_GET['hari']  ?? null,
            'page'  => $_GET['page']  ?? null,
        ])));
        exit;
    }

    // ── ADD SCHEDULE ──────────────────────────────────────────────────────────
    if (isset($_POST['add_schedule'])) {
        $id_staff    = (int)($_POST['id_staff']    ?? 0);
        $hari        = mysqli_real_escape_string($conn, trim($_POST['hari']        ?? ''));
        $jam_mulai   = mysqli_real_escape_string($conn, trim($_POST['jam_mulai']   ?? ''));
        $jam_selesai = mysqli_real_escape_string($conn, trim($_POST['jam_selesai'] ?? ''));
        if ($id_staff > 0 && $hari !== '' && $jam_mulai !== '' && $jam_selesai !== '') {
            mysqli_query($conn, "INSERT INTO staff_schedules (id_staff, hari, jam_mulai, jam_selesai)
                                 VALUES ($id_staff, '$hari', '$jam_mulai', '$jam_selesai')");
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?hari=' . urlencode($hari));
        exit;
    }

    // ── EDIT SCHEDULE ─────────────────────────────────────────────────────────
    if (isset($_POST['edit_schedule'])) {
        $id          = (int)$_POST['id_schedule'];
        $id_staff    = (int)($_POST['id_staff']    ?? 0);
        $hari        = mysqli_real_escape_string($conn, trim($_POST['hari']        ?? ''));
        $jam_mulai   = mysqli_real_escape_string($conn, trim($_POST['jam_mulai']   ?? ''));
        $jam_selesai = mysqli_real_escape_string($conn, trim($_POST['jam_selesai'] ?? ''));
        if ($id > 0 && $id_staff > 0 && $hari !== '' && $jam_mulai !== '' && $jam_selesai !== '') {
            mysqli_query($conn, "UPDATE staff_schedules
                                 SET id_staff=$id_staff, hari='$hari', jam_mulai='$jam_mulai', jam_selesai='$jam_selesai'
                                 WHERE id_schedule=$id");
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter([
            'staff' => $_GET['staff'] ?? null,
            'hari'  => $_GET['hari']  ?? null,
            'page'  => $_GET['page']  ?? null,
        ])));
        exit;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_staff = isset($_GET['staff']) && $_GET['staff'] !== '' ? (int)$_GET['staff'] : null;

// Default ke Senin jika tidak ada filter hari yang dikirim
$filter_hari = isset($_GET['hari']) && $_GET['hari'] !== ''
    ? trim($_GET['hari'])
    : 'Senin';

$where_parts = ['1=1'];
if ($filter_staff) $where_parts[] = "ss.id_staff = $filter_staff";
$where_parts[] = "ss.hari = '" . mysqli_real_escape_string($conn, $filter_hari) . "'";
$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

// ── Pagination ────────────────────────────────────────────────────────────────
$per_page   = 10;
$current_pg = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($current_pg - 1) * $per_page;

$total_rows  = db_val($conn, "SELECT COUNT(*) FROM staff_schedules ss $where_sql");
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// ── Schedule list ─────────────────────────────────────────────────────────────
$schedules = db_rows($conn, "
    SELECT
        ss.id_schedule,
        ss.id_staff,
        ss.hari,
        ss.jam_mulai,
        ss.jam_selesai,
        st.nama_staff,
        st.jabatan,
        st.is_active,
        sp.nama_specialization
    FROM staff_schedules ss
    LEFT JOIN staffs st ON ss.id_staff = st.id_staff
    LEFT JOIN specializations sp ON st.id_specialization = sp.id_specialization
    $where_sql
    ORDER BY ss.jam_mulai ASC
    LIMIT $per_page OFFSET $offset
");

// ── Summary stats ─────────────────────────────────────────────────────────────
$stat_total_schedules = db_val($conn, "SELECT COUNT(*) FROM staff_schedules");
$stat_total_staff     = db_val($conn, "SELECT COUNT(DISTINCT id_staff) FROM staff_schedules");
$stat_active_staff    = db_val($conn, "SELECT COUNT(*) FROM staffs WHERE is_active=1");

// ── Per-day count ─────────────────────────────────────────────────────────────
$day_counts = db_rows($conn, "
    SELECT hari, COUNT(*) AS cnt
    FROM staff_schedules
    GROUP BY hari
");
$day_map = [];
foreach ($day_counts as $d) $day_map[$d['hari']] = $d['cnt'];

// ── All staff for dropdown ────────────────────────────────────────────────────
$all_staff = db_rows($conn, "SELECT id_staff, nama_staff, jabatan FROM staffs WHERE is_active=1 ORDER BY nama_staff");

$pending           = db_val($conn, "SELECT COUNT(*) FROM reservations WHERE status='pending'");
$current_page_file = basename($_SERVER['PHP_SELF']);

function pg_qs_sched($page, $filter_staff, $filter_hari) {
    return '?' . http_build_query(array_filter([
        'staff' => $filter_staff,
        'hari'  => $filter_hari,
        'page'  => $page,
    ]));
}

// ── Day color map ─────────────────────────────────────────────────────────────
$day_colors = [
    'Senin'  => ['#3b82f6','#dbeafe','#1d4ed8'],
    'Selasa' => ['#8b5cf6','#ede9fe','#6d28d9'],
    'Rabu'   => ['#10b981','#d1fae5','#065f46'],
    'Kamis'  => ['#f59e0b','#fef3c7','#92400e'],
    'Jumat'  => ['#ef4444','#fee2e2','#991b1b'],
    'Sabtu'  => ['#ec4899','#fce7f3','#9d174d'],
    'Minggu' => ['#6b7280','#f3f4f6','#374151'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Schedules — CatDogKu Admin</title>
<link rel="icon" type="image/png" href="assets/icon.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
/* ── Base ─────────────────────────────────────────────────────────────────── */
body { background-color:#f4f7f6; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; margin:0; padding:0; }

/* ── Sidebar ──────────────────────────────────────────────────────────────── */
.sidebar { width:260px; position:fixed; top:0; left:0; height:100vh; overflow-y:auto; z-index:1000; background-color:#2c3e50; color:#fff; box-shadow:4px 0 10px rgba(0,0,0,.05); }
.sidebar-brand { font-size:1.25rem; letter-spacing:1px; border-bottom:1px solid rgba(255,255,255,.1); }
.sidebar a { color:#aeb6bf; text-decoration:none; padding:12px 20px; display:flex; align-items:center; border-radius:10px; margin-bottom:8px; transition:all .3s ease; font-weight:500; }
.sidebar a:hover, .sidebar a.active { background-color:#3498db; color:#fff; transform:translateX(5px); }
.sidebar a.logout-link:hover { background-color:rgba(231,76,60,.2); color:#e74c3c; transform:none; }

/* ── Layout ───────────────────────────────────────────────────────────────── */
.main-content { margin-left:260px; width:calc(100% - 260px); min-height:100vh; }

/* ── Stat Cards ───────────────────────────────────────────────────────────── */
.stat-card { border-radius:15px; border:none; transition:transform .3s ease,box-shadow .3s ease; cursor:default; }
.stat-card:hover { transform:translateY(-5px); box-shadow:0 12px 20px rgba(0,0,0,.08)!important; }
.icon-box { width:55px; height:55px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:26px; flex-shrink:0; }

/* ── Day Pill Bar ─────────────────────────────────────────────────────────── */
.day-pill-bar {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    background: #fff;
    border-radius: 16px;
    padding: 14px 18px;
    box-shadow: 0 2px 10px rgba(0,0,0,.04);
    margin-bottom: 24px;
    align-items: center;
}
.day-pill-bar-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: #9ca3af;
    margin-right: 4px;
    white-space: nowrap;
}
.day-pill {
    background: #f1f5f9;
    border: 2px solid transparent;
    border-radius: 999px;
    padding: 7px 18px;
    font-size: 13px;
    font-weight: 600;
    color: #4b5563;
    cursor: pointer;
    transition: all .18s ease;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    line-height: 1;
}
.day-pill:hover {
    background: #e2e8f0;
    transform: translateY(-1px);
}
.day-pill.active {
    color: #fff;
    border-color: transparent;
    box-shadow: 0 4px 14px rgba(0,0,0,.18);
    transform: translateY(-1px);
}
.day-pill .pill-count {
    background: rgba(255,255,255,.25);
    border-radius: 999px;
    padding: 1px 7px;
    font-size: 11px;
    font-weight: 700;
    min-width: 20px;
    text-align: center;
}
.day-pill:not(.active) .pill-count {
    background: rgba(0,0,0,.08);
    color: #6b7280;
}

/* ── Toolbar ──────────────────────────────────────────────────────────────── */
.toolbar { background:#fff; border-radius:14px; padding:18px 22px; box-shadow:0 2px 10px rgba(0,0,0,.04); display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar .form-control, .toolbar .form-select { border-radius:10px; border:1.5px solid #e9ecef; font-size:13px; height:40px; }
.toolbar .form-control:focus, .toolbar .form-select:focus { border-color:#3498db; box-shadow:0 0 0 3px rgba(52,152,219,.12); }
.btn-filter { height:40px; border-radius:10px; font-size:13px; font-weight:600; padding:0 18px; display:inline-flex; align-items:center; gap:4px; }

/* ── Table card ───────────────────────────────────────────────────────────── */
.table-card { background:#fff; border-radius:16px; box-shadow:0 5px 20px rgba(0,0,0,.04); overflow:hidden; }
.table-card-header { padding:18px 24px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between; }
.table { table-layout:fixed; width:100%; }
.table > thead > tr > th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#6c757d; background:#f8f9fa; border-bottom:1px solid #e9ecef; padding:13px 20px; white-space:nowrap; }
.table > tbody > tr > td { padding:13px 20px; vertical-align:middle; font-size:13.5px; border-bottom:1px solid #f5f5f5; }
.table > tbody > tr:last-child > td { border-bottom:none; }
.table > tbody > tr:hover > td { background:#f8f9fa; }

/* ── Day header row ───────────────────────────────────────────────────────── */
.day-header-row td {
    padding: 10px 20px 8px !important;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef !important;
}
.day-header-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 8px;
    padding: 4px 14px;
    font-size: 12px;
    font-weight: 700;
}

/* ── Staff avatar ─────────────────────────────────────────────────────────── */
.staff-avatar { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:700; flex-shrink:0; color:#fff; }

/* ── Time display ─────────────────────────────────────────────────────────── */
.time-range { display:inline-flex; align-items:center; gap:6px; background:#f0f4f8; border-radius:8px; padding:5px 12px; font-size:12.5px; font-weight:600; color:#374151; }
.time-sep { color:#9ca3af; font-size:11px; }

/* ── Duration chip ────────────────────────────────────────────────────────── */
.duration-chip { display:inline-block; background:#e0f2fe; color:#0369a1; border-radius:6px; padding:2px 8px; font-size:11.5px; font-weight:600; }

/* ── Action buttons ───────────────────────────────────────────────────────── */
.btn-action { padding:5px 12px; font-size:12px; border-radius:8px; font-weight:600; border:none; cursor:pointer; transition:opacity .2s; display:inline-flex; align-items:center; gap:4px; }
.btn-action:hover { opacity:.8; }
.btn-view   { background:#eaf4fd; color:#2980b9; }
.btn-edit   { background:#fef9e7; color:#b7770d; }
.btn-delete { background:#fdecea; color:#922b21; }

/* ── Pagination ───────────────────────────────────────────────────────────── */
.pagination .page-link { border-radius:8px!important; margin:0 2px; font-size:13px; font-weight:600; color:#3498db; border:1.5px solid #e9ecef; }
.pagination .page-item.active .page-link { background:#3498db; border-color:#3498db; color:#fff; }
.pagination .page-link:hover { background:#eaf4fd; color:#2980b9; }

/* ── Modal ────────────────────────────────────────────────────────────────── */
.modal-content { border-radius:18px; border:none; }
.modal-header  { border-bottom:1px solid #f0f0f0; padding:20px 24px; }
.modal-body    { padding:24px; }
.modal-footer  { border-top:1px solid #f0f0f0; padding:16px 24px; }
.detail-row { display:flex; gap:8px; padding:10px 0; border-bottom:1px solid #f5f5f5; font-size:13.5px; }
.detail-row:last-child { border-bottom:none; }
.detail-label { width:160px; flex-shrink:0; color:#6c757d; font-weight:600; font-size:13px; }
.detail-value { color:#1a1a2e; }
.mini-stat { background:#f8f9fa; border-radius:12px; padding:14px 16px; text-align:center; }
.mini-stat .num { font-size:22px; font-weight:700; line-height:1.1; color:#1a1a2e; }
.mini-stat .lbl { font-size:11px; color:#6c757d; font-weight:500; margin-top:2px; }

/* ── Form modal ───────────────────────────────────────────────────────────── */
.form-modal .modal-body label { font-size:13px; font-weight:600; color:#555; margin-bottom:5px; }
.form-modal .form-control, .form-modal .form-select { border-radius:10px; border:1.5px solid #e9ecef; font-size:13.5px; padding:10px 14px; }
.form-modal .form-control:focus, .form-modal .form-select:focus { border-color:#3498db; box-shadow:0 0 0 3px rgba(52,152,219,.12); }
.time-inputs { display:grid; grid-template-columns:1fr auto 1fr; gap:8px; align-items:center; }
.time-inputs .sep { text-align:center; color:#9ca3af; font-size:13px; font-weight:600; padding-top:8px; }

/* ── Confirm overlay ──────────────────────────────────────────────────────── */
.confirm-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; }
.confirm-overlay.show { display:flex; }
.confirm-modal { background:#fff; border-radius:20px; padding:36px 32px; width:400px; max-width:90vw; box-shadow:0 20px 60px rgba(0,0,0,.2); animation:modalIn .25s ease; }
@keyframes modalIn { from{transform:scale(.9) translateY(10px);opacity:0} to{transform:scale(1) translateY(0);opacity:1} }
.confirm-modal .c-icon { width:60px; height:60px; border-radius:50%; background:rgba(220,38,38,.1); display:flex; align-items:center; justify-content:center; margin:0 auto 18px; font-size:28px; color:#dc2626; }
.confirm-modal h5 { text-align:center; font-weight:700; font-size:17px; margin-bottom:6px; }
.confirm-modal p  { text-align:center; color:#6c757d; font-size:13px; margin-bottom:22px; }
.btn-del-yes { background:linear-gradient(135deg,#dc2626,#ef4444); color:#fff; border:none; border-radius:12px; padding:11px 0; width:100%; font-weight:600; font-size:14px; cursor:pointer; margin-bottom:10px; transition:all .2s; }
.btn-del-yes:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(220,38,38,.3); }
.btn-del-no { background:#f1f3f4; color:#6c757d; border:none; border-radius:12px; padding:10px 0; width:100%; font-weight:600; font-size:13px; cursor:pointer; transition:background .2s; }
.btn-del-no:hover { background:#e2e6ea; }

/* ── Toast ────────────────────────────────────────────────────────────────── */
.toast-notif { position:fixed; top:24px; right:24px; background:#16a34a; color:#fff; border-radius:12px; padding:14px 20px; font-size:14px; font-weight:600; display:flex; align-items:center; gap:10px; box-shadow:0 8px 24px rgba(0,0,0,.15); z-index:99999; opacity:0; transform:translateX(40px); transition:all .3s ease; pointer-events:none; }
.toast-notif.show { opacity:1; transform:translateX(0); }

/* ── Logout modal ────────────────────────────────────────────────────────── */
.logout-icon-wrap {
    width: 64px; height: 64px; border-radius: 18px;
    background: rgba(231,76,60,.1);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; color: #e74c3c;
    margin: 0 auto 6px;
}
.logout-modal-error {
    color: #dc3545;
    font-size: 14px;
    margin-top: 8px;
    display: none;
}
.pw-wrapper {
    position: relative;
}
.pw-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    font-size: 16px;
    padding: 4px;
}
.pw-toggle:hover {
    color: #495057;
}

/* ── Active day indicator in table header ─────────────────────────────────── */
.active-day-indicator {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 10px;
    padding: 6px 16px;
    font-size: 13px;
    font-weight: 700;
}

@media (max-width:992px) {
    .sidebar { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0); }
    .main-content { margin-left:0; width:100%; }
    .day-pill-bar { gap: 6px; padding: 12px 14px; }
    .day-pill { padding: 6px 13px; font-size: 12px; }
}
@media (max-width:576px) {
    .day-pill .pill-count { display: none; }
}
</style>
</head>
<body>

<!-- ═══════════════════════════ SIDEBAR ════════════════════════════════════ -->
<div class="sidebar p-3" id="sidebar">
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
        <!-- Logout: trigger modal, bukan href langsung -->
        <a href="#" class="logout-link fw-bold" onclick="openLogoutModal(); return false;">
            <i class="bi bi-box-arrow-left me-2 fs-5"></i> Logout
        </a>
    </div>
</div>

<!-- ═══════════════════════════ MAIN CONTENT ═══════════════════════════════ -->
<div class="main-content p-4 p-md-5">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Staff Schedules</h2>
            <p class="text-muted mb-0" style="font-size:13px">Manage weekly work schedules for all staff</p>
        </div>
        <a href="admin_reserve.php?status=pending" class="position-relative text-decoration-none text-secondary">
            <i class="bi bi-bell fs-5"></i>
            <?php if($pending > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px"><?= $pending ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- ── Stat Cards ─────────────────────────────────────────────────────── -->
    <div class="row g-4 mb-4">
        <div class="col-md-4 col-sm-6">
            <div class="card stat-card shadow-sm h-100 p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">Total Schedules</p>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($stat_total_schedules) ?></h3>
                        <p class="text-muted mb-0" style="font-size:12px">All shift entries</p>
                    </div>
                    <div class="icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-calendar-week"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card stat-card shadow-sm h-100 p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">Staff Scheduled</p>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($stat_total_staff) ?></h3>
                        <p class="text-muted mb-0" style="font-size:12px">Unique staff with shifts</p>
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
                        <p class="text-muted fw-semibold mb-1" style="font-size:13px">Active Staff</p>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($stat_active_staff) ?></h3>
                        <p class="text-muted mb-0" style="font-size:12px">Currently active staff</p>
                    </div>
                    <div class="icon-box bg-warning bg-opacity-10" style="color:#d97706">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Day Filter Pills ────────────────────────────────────────────────── -->
    <form method="GET" action="admin_staffschedule.php" id="filterForm">
        <input type="hidden" name="hari" id="hari-hidden" value="<?= htmlspecialchars($filter_hari) ?>">
        <input type="hidden" name="staff" id="staff-hidden" value="<?= htmlspecialchars($filter_staff ?? '') ?>">

        <div class="day-pill-bar">
            <span class="day-pill-bar-label"><i class="bi bi-calendar3 me-1"></i>Hari</span>
            <?php foreach($days_order as $day):
                $is_active = ($filter_hari === $day);
                [$color, $bg, $dark] = $day_colors[$day];
                $cnt = $day_map[$day] ?? 0;
            ?>
            <button
                type="button"
                class="day-pill <?= $is_active ? 'active' : '' ?>"
                style="<?= $is_active ? "background:$color;" : '' ?>"
                onclick="filterByDay('<?= $day ?>')">
                <?= $day ?>
                <span class="pill-count"><?= $cnt ?></span>
            </button>
            <?php endforeach; ?>

            <?php if($filter_staff): ?>
                <span class="ms-2 badge bg-light text-secondary border" style="font-size:12px;padding:7px 12px;border-radius:8px;font-weight:600">
                    <i class="bi bi-person me-1"></i>
                    <?php
                    foreach($all_staff as $st) {
                        if ((int)$st['id_staff'] === $filter_staff) {
                            echo htmlspecialchars($st['nama_staff']);
                            break;
                        }
                    }
                    ?>
                    <a href="?hari=<?= urlencode($filter_hari) ?>" class="text-secondary ms-1" style="text-decoration:none">&times;</a>
                </span>
            <?php endif; ?>
        </div>
    </form>

    <!-- ── Schedules Table ─────────────────────────────────────────────────── -->
    <div class="table-card mb-4">
        <div class="table-card-header">
            <div class="d-flex align-items-center gap-3">
                <?php
                [$dc, $db, $dd] = $day_colors[$filter_hari];
                ?>
                <span class="active-day-indicator" style="background:<?= $db ?>;color:<?= $dc ?>">
                    <i class="bi bi-calendar2-check"></i>
                    <?= htmlspecialchars($filter_hari) ?>
                </span>
                <h5 class="fw-bold mb-0" style="font-size:15px">
                    Jadwal Staff
                    <span class="badge bg-primary bg-opacity-10 text-primary ms-1" style="font-size:12px"><?= number_format($total_rows) ?></span>
                </h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <!-- Staff filter dropdown -->
                <form method="GET" action="admin_staffschedule.php" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="hari" value="<?= htmlspecialchars($filter_hari) ?>">
                    <select name="staff" class="form-select form-select-sm" style="border-radius:9px;font-size:13px;min-width:160px;border:1.5px solid #e9ecef" onchange="this.form.submit()">
                        <option value="">All Staff</option>
                        <?php foreach($all_staff as $st): ?>
                            <option value="<?= $st['id_staff'] ?>" <?= $filter_staff===(int)$st['id_staff']?'selected':'' ?>>
                                <?= htmlspecialchars($st['nama_staff']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <span class="text-muted" style="font-size:13px;white-space:nowrap">Hal <?= $current_pg ?>/<?= $total_pages ?></span>
                <button class="btn btn-primary btn-sm"
                        style="border-radius:9px;font-size:13px;font-weight:600;padding:6px 16px;display:inline-flex;align-items:center;gap:5px"
                        onclick="openAddSchedule()">
                    <i class="bi bi-plus-lg"></i> Add Schedule
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th style="width:5%">#</th>
                        <th style="width:30%">Staff</th>
                        <th style="width:28%">Jam Kerja</th>
                        <th style="width:12%">Durasi</th>
                        <th style="width:25%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <?php [$dc,$db] = $day_colors[$filter_hari]; ?>
                            <div style="width:64px;height:64px;border-radius:50%;background:<?= $db ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px;color:<?= $dc ?>">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                            <div class="fw-semibold" style="font-size:15px;color:#374151">Tidak ada jadwal untuk <?= htmlspecialchars($filter_hari) ?></div>
                            <div class="text-muted" style="font-size:13px;margin-top:4px">Klik "Add Schedule" untuk menambah jadwal baru</div>
                        </td>
                    </tr>
                <?php else:
                    $avatar_colors = ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#ef4444','#ec4899','#06b6d4','#84cc16'];
                    foreach ($schedules as $i => $sc):
                        $av_color = $avatar_colors[$sc['id_staff'] % count($avatar_colors)];
                        $initials  = strtoupper(substr($sc['nama_staff'] ?? 'S', 0, 1));
                        $t1 = strtotime($sc['jam_mulai']);
                        $t2 = strtotime($sc['jam_selesai']);
                        $dur_min = $t2 > $t1 ? round(($t2 - $t1) / 60) : 0;
                        $dur_str = $dur_min >= 60 ? floor($dur_min/60) . 'h ' . ($dur_min%60>0?($dur_min%60).'m':'') : $dur_min . 'm';
                ?>
                    <tr>
                        <td class="text-muted" style="font-size:12px;font-weight:600"><?= $offset + $i + 1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="staff-avatar" style="background:<?= $av_color ?>"><?= $initials ?></div>
                                <div>
                                    <div class="fw-semibold" style="font-size:13.5px"><?= htmlspecialchars($sc['nama_staff'] ?? '—') ?></div>
                                    <div class="text-muted" style="font-size:11.5px">
                                        <?= htmlspecialchars($sc['jabatan'] ?? '') ?>
                                        <?php if($sc['nama_specialization']): ?> · <?= htmlspecialchars($sc['nama_specialization']) ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="time-range">
                                <i class="bi bi-clock" style="font-size:12px;color:#6b7280"></i>
                                <span><?= date('H:i', strtotime($sc['jam_mulai'])) ?></span>
                                <span class="time-sep">→</span>
                                <span><?= date('H:i', strtotime($sc['jam_selesai'])) ?></span>
                            </div>
                        </td>
                        <td><span class="duration-chip"><?= trim($dur_str) ?></span></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <button class="btn-action btn-view" onclick="openDetail(<?= htmlspecialchars(json_encode(array_merge($sc, [
                                    'dur_str' => trim($dur_str),
                                    'jam_mulai_fmt'   => date('H:i', strtotime($sc['jam_mulai'])),
                                    'jam_selesai_fmt' => date('H:i', strtotime($sc['jam_selesai'])),
                                    'av_color' => $av_color,
                                ])), ENT_QUOTES) ?>)">
                                    <i class="bi bi-eye-fill"></i> Detail
                                </button>
                                <button class="btn-action btn-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($sc), ENT_QUOTES) ?>)">
                                    <i class="bi bi-pencil-fill"></i> Edit
                                </button>
                                <button class="btn-action btn-delete" onclick="openDelete(<?= $sc['id_schedule'] ?>, '<?= addslashes(htmlspecialchars($sc['nama_staff']??'')) ?>', '<?= addslashes(htmlspecialchars($sc['hari']??'')) ?>')">
                                    <i class="bi bi-trash-fill"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
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
                        <a class="page-link" href="<?= pg_qs_sched($current_pg-1,$filter_staff,$filter_hari) ?>">
                            <i class="bi bi-chevron-left" style="font-size:11px"></i>
                        </a>
                    </li>
                    <?php
                    $from = max(1, $current_pg-2); $to = min($total_pages, $current_pg+2);
                    if($from>1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    for($pg=$from; $pg<=$to; $pg++):
                    ?>
                        <li class="page-item <?= $pg===$current_pg?'active':'' ?>">
                            <a class="page-link" href="<?= pg_qs_sched($pg,$filter_staff,$filter_hari) ?>"><?= $pg ?></a>
                        </li>
                    <?php endfor;
                    if($to<$total_pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    ?>
                    <li class="page-item <?= $current_pg>=$total_pages?'disabled':'' ?>">
                        <a class="page-link" href="<?= pg_qs_sched($current_pg+1,$filter_staff,$filter_hari) ?>">
                            <i class="bi bi-chevron-right" style="font-size:11px"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div><!-- /table-card -->

</div><!-- /main-content -->


<!-- ════════════════ DETAIL MODAL ═══════════════════════════════════════════ -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="staff-avatar" id="d-avatar" style="width:48px;height:48px;font-size:18px;font-weight:700;border-radius:12px;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0"></div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="d-name"></h5>
                        <p class="text-muted mb-0" id="d-sub" style="font-size:13px"></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="num" id="d-day" style="font-size:15px">—</div>
                            <div class="lbl">Day</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="num" id="d-time" style="font-size:13px">—</div>
                            <div class="lbl">Hours</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="num text-primary" id="d-dur">—</div>
                            <div class="lbl">Duration</div>
                        </div>
                    </div>
                </div>
                <p class="fw-bold text-muted mb-3" style="font-size:11px;text-transform:uppercase;letter-spacing:.8px">Schedule Information</p>
                <div id="d-info"></div>
            </div>
            <div class="modal-footer">
                <button id="d-edit-btn" class="btn btn-warning btn-sm" style="border-radius:8px;font-size:13px;font-weight:600" onclick="switchDetailToEdit()">
                    <i class="bi bi-pencil me-1"></i> Edit
                </button>
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal" style="border-radius:8px;font-size:13px">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════ ADD SCHEDULE MODAL ═════════════════════════════════════ -->
<div class="modal fade form-modal" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Add New Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="add_schedule" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Staff <span class="text-danger">*</span></label>
                        <select name="id_staff" class="form-select" required>
                            <option value="">— Select Staff —</option>
                            <?php foreach($all_staff as $st): ?>
                                <option value="<?= $st['id_staff'] ?>">
                                    <?= htmlspecialchars($st['nama_staff']) ?>
                                    <?= $st['jabatan'] ? ' (' . htmlspecialchars($st['jabatan']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Day <span class="text-danger">*</span></label>
                        <select name="hari" id="add-hari-select" class="form-select" required>
                            <option value="">— Select Day —</option>
                            <?php foreach($days_order as $d): ?>
                                <option value="<?= $d ?>" <?= $filter_hari===$d?'selected':'' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Work Hours <span class="text-danger">*</span></label>
                        <div class="time-inputs">
                            <div>
                                <input type="time" name="jam_mulai" class="form-control" required placeholder="Start">
                                <div class="form-text">Start time</div>
                            </div>
                            <div class="sep">→</div>
                            <div>
                                <input type="time" name="jam_selesai" class="form-control" required placeholder="End">
                                <div class="form-text">End time</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal" style="border-radius:8px;font-size:13px">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="border-radius:8px;font-size:13px;font-weight:600">
                        <i class="bi bi-plus-lg me-1"></i> Add Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ════════════════ EDIT SCHEDULE MODAL ════════════════════════════════════ -->
<div class="modal fade form-modal" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="edit_schedule" value="1">
                <input type="hidden" name="id_schedule" id="edit-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Staff <span class="text-danger">*</span></label>
                        <select name="id_staff" id="edit-staff" class="form-select" required>
                            <option value="">— Select Staff —</option>
                            <?php foreach($all_staff as $st): ?>
                                <option value="<?= $st['id_staff'] ?>">
                                    <?= htmlspecialchars($st['nama_staff']) ?>
                                    <?= $st['jabatan'] ? ' (' . htmlspecialchars($st['jabatan']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Day <span class="text-danger">*</span></label>
                        <select name="hari" id="edit-hari" class="form-select" required>
                            <option value="">— Select Day —</option>
                            <?php foreach($days_order as $d): ?>
                                <option value="<?= $d ?>"><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Work Hours <span class="text-danger">*</span></label>
                        <div class="time-inputs">
                            <div>
                                <input type="time" name="jam_mulai" id="edit-mulai" class="form-control" required>
                                <div class="form-text">Start time</div>
                            </div>
                            <div class="sep">→</div>
                            <div>
                                <input type="time" name="jam_selesai" id="edit-selesai" class="form-control" required>
                                <div class="form-text">End time</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal" style="border-radius:8px;font-size:13px">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm" style="border-radius:8px;font-size:13px;font-weight:600">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ════════════════ DELETE CONFIRM ═════════════════════════════════════════ -->
<div class="confirm-overlay" id="deleteOverlay">
    <div class="confirm-modal">
        <div class="c-icon"><i class="bi bi-trash3-fill"></i></div>
        <h5>Delete Schedule?</h5>
        <p id="delete-msg">This action cannot be undone.</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="delete_schedule" value="1">
            <input type="hidden" name="id_schedule" id="delete-id">
            <button type="submit" class="btn-del-yes"><i class="bi bi-trash me-2"></i> Yes, Delete</button>
        </form>
        <button class="btn-del-no" onclick="closeDelete()">Cancel</button>
    </div>
</div>

<!-- ════════════════ TOAST ════════════════════════════════════════════════════ -->
<div class="toast-notif" id="toastNotif">
    <i class="bi bi-check-circle-fill"></i>
    <span id="toastMsg">Done!</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Day pill filter — selalu ada hari aktif, tidak bisa deselect ─────────────
function filterByDay(day) {
    document.getElementById('hari-hidden').value = day;
    document.getElementById('filterForm').submit();
}

// ── Detail row helper ────────────────────────────────────────────────────────
function dRow(label, value) {
    return `<div class="detail-row">
        <span class="detail-label">${label}</span>
        <span class="detail-value">${value || '—'}</span>
    </div>`;
}

// ── Current schedule for edit shortcut ──────────────────────────────────────
let _currentSc = null;

function openDetail(sc) {
    _currentSc = sc;
    document.getElementById('d-avatar').textContent  = (sc.nama_staff || 'S').charAt(0).toUpperCase();
    document.getElementById('d-avatar').style.background = sc.av_color || '#3b82f6';
    document.getElementById('d-name').textContent    = sc.nama_staff || '—';
    document.getElementById('d-sub').textContent     = [sc.jabatan, sc.nama_specialization].filter(Boolean).join(' · ') || 'Staff';
    document.getElementById('d-day').textContent     = sc.hari || '—';
    document.getElementById('d-time').textContent    = sc.jam_mulai_fmt + ' – ' + sc.jam_selesai_fmt;
    document.getElementById('d-dur').textContent     = sc.dur_str || '—';
    document.getElementById('d-info').innerHTML =
        dRow('Staff Name',      sc.nama_staff) +
        dRow('Position',        sc.jabatan || '—') +
        dRow('Specialization',  sc.nama_specialization || '—') +
        dRow('Day',             sc.hari) +
        dRow('Start Time',      sc.jam_mulai_fmt) +
        dRow('End Time',        sc.jam_selesai_fmt) +
        dRow('Duration',        sc.dur_str);
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

function switchDetailToEdit() {
    bootstrap.Modal.getInstance(document.getElementById('detailModal')).hide();
    setTimeout(() => openEdit(_currentSc), 300);
}

// ── Add Schedule ─────────────────────────────────────────────────────────────
function openAddSchedule() {
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

// ── Edit Schedule ─────────────────────────────────────────────────────────────
function openEdit(sc) {
    document.getElementById('edit-id').value      = sc.id_schedule;
    document.getElementById('edit-staff').value   = sc.id_staff;
    document.getElementById('edit-hari').value    = sc.hari;
    document.getElementById('edit-mulai').value   = sc.jam_mulai   ? sc.jam_mulai.substring(0,5)   : '';
    document.getElementById('edit-selesai').value = sc.jam_selesai ? sc.jam_selesai.substring(0,5) : '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// ── Delete ───────────────────────────────────────────────────────────────────
function openDelete(id, staff, hari) {
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-msg').textContent =
        `Hapus jadwal ${staff} pada hari ${hari}? Tindakan ini tidak bisa dibatalkan.`;
    document.getElementById('deleteOverlay').classList.add('show');
}
function closeDelete() {
    document.getElementById('deleteOverlay').classList.remove('show');
}
document.getElementById('deleteOverlay').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeDelete();
});

// ── Toast ─────────────────────────────────────────────────────────────────────
function showToast(msg) {
    const t = document.getElementById('toastNotif');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}
</script>

<!-- ══════════════════ LOGOUT CONFIRMATION MODAL ══════════════════════════ -->
<div class="modal fade form-modal" id="logoutModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        onclick="clearLogoutForm()"></button>
            </div>
            <form method="POST" action="admin_staffschedule.php" id="logoutForm">
                <input type="hidden" name="confirm_logout" value="1">
                <div class="modal-body pt-2">

                    <!-- Icon + judul -->
                    <div class="text-center mb-4">
                        <div class="logout-icon-wrap">
                            <i class="bi bi-box-arrow-left"></i>
                        </div>
                        <h5 class="fw-bold mt-3 mb-1" style="font-size:16px;color:#1a1a2e">Log Out Confirmation</h5>
                        <p class="text-muted mb-0" style="font-size:13px">
                            Enter your password to log out of this session.
                        </p>
                    </div>

                    <!-- Error message (tampil jika password salah dari PHP) -->
                    <?php if(!empty($logout_error)): ?>
                    <div class="logout-modal-error">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <?= htmlspecialchars($logout_error) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Error JS (salah pw tanpa reload) -->
                    <div class="logout-modal-error" id="logout-js-error" style="display:none">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span id="logout-js-error-msg">Password is required.</span>
                    </div>

                    <!-- Password field -->
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
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

                    <!-- Divider tipis -->
                    <hr style="border-color:#f0f0f0;margin:18px 0 16px">

                    <!-- Buttons -->
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
// ── Logout Modal ──────────────────────────────────────────────────────────────
function openLogoutModal() {
    clearLogoutForm();
    new bootstrap.Modal(document.getElementById('logoutModal')).show();
    // Fokus ke input password setelah modal terbuka
    document.getElementById('logoutModal').addEventListener('shown.bs.modal', function onShown() {
        document.getElementById('logout-pw').focus();
        this.removeEventListener('shown.bs.modal', onShown);
    });
}

function clearLogoutForm() {
    document.getElementById('logout-pw').value = '';
    document.getElementById('logout-js-error').style.display = 'none';
    // Reset icon show/hide
    document.getElementById('logout-pw').type = 'password';
    document.getElementById('pw-toggle-icon').className = 'bi bi-eye';
}

function validateLogout() {
    const pw  = document.getElementById('logout-pw').value.trim();
    const err = document.getElementById('logout-js-error');
    const msg = document.getElementById('logout-js-error-msg');
    if (pw === '') {
        msg.textContent = 'Password tidak boleh kosong.';
        err.style.display = 'flex';
        document.getElementById('logout-pw').focus();
        return false;
    }
    err.style.display = 'none';
    return true; // lanjut submit
}

function togglePwVisibility() {
    const input = document.getElementById('logout-pw');
    const icon  = document.getElementById('pw-toggle-icon');
    if (input.type === 'password') {
        input.type       = 'text';
        icon.className   = 'bi bi-eye-slash';
    } else {
        input.type       = 'password';
        icon.className   = 'bi bi-eye';
    }
}

// Buka logout modal otomatis jika ada error password dari PHP
<?php if(!empty($logout_error)): ?>
    window.addEventListener('DOMContentLoaded', () => {
        new bootstrap.Modal(document.getElementById('logoutModal')).show();
    });
<?php endif; ?>
</script>
</body>
</html>