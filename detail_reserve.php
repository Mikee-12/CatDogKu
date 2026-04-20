<?php
include "config/koneksi.php";
session_start();

if (!isset($_SESSION['customer_id'])) {
    echo "<script>window.location='cust_login.php';</script>";
    exit;
}

$customer_id = $_SESSION['customer_id'];

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    $id_reservation = (int)$_POST['id_reservation'];
    $metode_bayar   = $_POST['metode_bayar'];
    $allowed_methods = ['cash', 'transfer', 'qris'];

    if (!in_array($metode_bayar, $allowed_methods)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment method.']);
        exit;
    }

    $bukti_path = null;
    if (isset($_FILES['bukti_bayar']) && $_FILES['bukti_bayar']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['bukti_bayar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) {
            echo json_encode(['success' => false, 'message' => 'Unsupported file format. Use JPG, JPEG, or PNG.']);
            exit;
        }
        $upload_dir = 'uploads/bukti_bayar/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $filename   = 'bukti_' . $id_reservation . '_' . time() . '.' . $ext;
        $dest       = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['bukti_bayar']['tmp_name'], $dest)) {
            $bukti_path = $dest;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
            exit;
        }
    }

    $check = mysqli_query($conn, "SELECT id_payment FROM payments WHERE id_reservation = $id_reservation LIMIT 1");
    $metode_esc  = mysqli_real_escape_string($conn, $metode_bayar);
    $bukti_esc   = $bukti_path ? "'" . mysqli_real_escape_string($conn, $bukti_path) . "'" : "NULL";

    $total_bayar = 0;
    $query_total = "SELECT SUM(subtotal) AS total FROM reservation_details WHERE id_reservation = $id_reservation";
    $result_total = mysqli_query($conn, $query_total);
    if ($result_total && $row_total = mysqli_fetch_assoc($result_total)) {
        $total_bayar = $row_total['total'] ?? 0;
    }

    if (mysqli_num_rows($check) > 0) {
        $sql = "UPDATE payments SET metode_bayar = '$metode_esc', bukti_bayar = $bukti_esc, status_bayar = 'partial', tgl_bayar = NOW(), total_bayar = $total_bayar WHERE id_reservation = $id_reservation";
    } else {
        $sql = "INSERT INTO payments (id_reservation, status_bayar, metode_bayar, bukti_bayar, tgl_bayar, total_bayar) VALUES ($id_reservation, 'partial', '$metode_esc', $bukti_esc, NOW(), $total_bayar)";
    }

    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Payment proof submitted successfully. Awaiting admin confirmation.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save data: ' . mysqli_error($conn)]);
    }
    exit;
}

// Handle cancel reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $id_reservation = (int)$_POST['id_reservation'];

    $check_owner = mysqli_query($conn, "SELECT id_reservation FROM reservations WHERE id_reservation = $id_reservation AND id_user = $customer_id LIMIT 1");
    if (mysqli_num_rows($check_owner) === 0) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found or access denied.']);
        exit;
    }

    mysqli_query($conn, "DELETE FROM payments WHERE id_reservation = $id_reservation");
    mysqli_query($conn, "DELETE FROM reservation_details WHERE id_reservation = $id_reservation");
    mysqli_query($conn, "DELETE FROM reservations WHERE id_reservation = $id_reservation");

    echo json_encode(['success' => true, 'message' => 'Reservation cancelled successfully.']);
    exit;
}

// Handle reschedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reschedule') {
    $id_reservation = (int)$_POST['id_reservation'];
    $waktu_mulai    = mysqli_real_escape_string($conn, $_POST['waktu_mulai']);
    $waktu_selesai  = mysqli_real_escape_string($conn, $_POST['waktu_selesai']);

    $sql = "UPDATE reservations SET waktu_mulai = '$waktu_mulai', waktu_selesai = '$waktu_selesai', status = 'pending' WHERE id_reservation = $id_reservation AND id_user = $customer_id";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Schedule successfully changed. Awaiting re-confirmation.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change schedule.']);
    }
    exit;
}

// AUTO STATUS UPDATE (polling)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'auto_update_status') {
    $now = date('Y-m-d H:i:s');
    mysqli_query($conn, "UPDATE reservations SET status = 'in_progress' WHERE id_user = $customer_id AND status = 'confirmed' AND waktu_mulai <= '$now' AND waktu_selesai > '$now'");
    mysqli_query($conn, "UPDATE reservations SET status = 'completed' WHERE id_user = $customer_id AND status IN ('confirmed', 'in_progress') AND waktu_selesai <= '$now'");
    echo json_encode(['success' => true]);
    exit;
}

// AUTO STATUS UPDATE ON PAGE LOAD
$now = date('Y-m-d H:i:s');
mysqli_query($conn, "UPDATE reservations SET status = 'in_progress' WHERE id_user = $customer_id AND status = 'confirmed' AND waktu_mulai <= '$now' AND waktu_selesai > '$now'");
mysqli_query($conn, "UPDATE reservations SET status = 'completed' WHERE id_user = $customer_id AND status IN ('confirmed', 'in_progress') AND waktu_selesai <= '$now'");

// Fetch all customer reservations
$query = "
    SELECT
        r.id_reservation, r.waktu_mulai, r.waktu_selesai, r.status, r.catatan, r.created_at,
        p.nama_pet, sp.nama_species, br.nama_breed,
        sv.nama_service, sv.harga_base, sv.durasi_estimasi,
        st.nama_staff, st.jabatan,
        rd.price_snapshot, rd.subtotal,
        pay.status_bayar, pay.metode_bayar, pay.tgl_bayar, pay.total_bayar
    FROM reservations r
    LEFT JOIN pets p          ON r.id_pet = p.id_pet
    LEFT JOIN breeds br       ON p.id_breed = br.id_breed
    LEFT JOIN species sp      ON br.id_species = sp.id_species
    LEFT JOIN reservation_details rd ON r.id_reservation = rd.id_reservation
    LEFT JOIN services sv     ON rd.id_service = sv.id_service
    LEFT JOIN staffs st       ON rd.id_staff = st.id_staff
    LEFT JOIN payments pay    ON r.id_reservation = pay.id_reservation
    WHERE r.id_user = $customer_id
    ORDER BY r.created_at DESC
";

$result = mysqli_query($conn, $query);
$reservations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $reservations[] = $row;
}

function statusBadge($status) {
    $map = [
        'pending'     => ['label' => 'Pending',     'class' => 'badge-pending'],
        'confirmed'   => ['label' => 'Confirmed',   'class' => 'badge-confirmed'],
        'in_progress' => ['label' => 'In Progress', 'class' => 'badge-progress'],
        'completed'   => ['label' => 'Completed',   'class' => 'badge-completed'],
        'cancelled'   => ['label' => 'Cancelled',   'class' => 'badge-cancelled'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-pending'];
    return '<span class="badge ' . $s['class'] . '">' . $s['label'] . '</span>';
}

function payBadge($status) {
    if (!$status) return '<span class="badge badge-pending">Unpaid</span>';
    $map = ['unpaid' => 'badge-cancelled', 'partial' => 'badge-progress', 'paid' => 'badge-completed'];
    $cls = $map[$status] ?? 'badge-pending';
    return '<span class="badge ' . $cls . '">' . ucfirst($status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Reservations — CatDogKu</title>
  <link rel="icon" type="image/png" href="assets/icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />

  <style>
    :root {
      --bg:         #ffffff;
      --bg2:        #f6f8f3;
      --surface:    #ffffff;
      --border:     #e4e9dc;
      --text:       #1a1f12;
      --text-sub:   #5a6248;
      --accent:     #4caf50;
      --accent-dk:  #388e3c;
      --accent-lt:  #e8f5e9;
      --nav-h:      64px;
      --radius:     14px;
      --shadow:     0 4px 24px rgba(0,0,0,.08);
      --transition: .35s cubic-bezier(.4,0,.2,1);
    }
    [data-theme="dark"] {
      --bg:         #121812;
      --bg2:        #1a2118;
      --surface:    #1e271e;
      --border:     #2d3d2d;
      --text:       #e8f0e8;
      --text-sub:   #8fa88f;
      --accent:     #66bb6a;
      --accent-dk:  #4caf50;
      --accent-lt:  #1b2e1b;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      transition: background var(--transition), color var(--transition);
      min-height: 100vh;
    }
    a { text-decoration: none; color: inherit; }
    img { display: block; max-width: 100%; }

    /* ── Navbar ── */
    .navbar {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 1000;
      height: var(--nav-h);
      display: flex;
      align-items: center;
      padding: 0 5%;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      box-shadow: var(--shadow);
      transition: background var(--transition), border-color var(--transition);
    }
    .nav-logo { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
    .nav-logo img { height: 40px; width: auto; }
    [data-theme="light"] .logo-dark  { display: none; }
    [data-theme="dark"]  .logo-light { display: none; }

    .nav-links {
      display: flex; align-items: center; gap: 32px;
      list-style: none; margin-left: 48px;
    }
    .nav-links a {
      font-size: .88rem; font-weight: 500; letter-spacing: .04em;
      text-transform: uppercase; color: var(--text-sub);
      position: relative; transition: color var(--transition);
      white-space: nowrap;
    }
    .nav-links a::after {
      content: ''; position: absolute; left: 0; bottom: -4px;
      width: 0; height: 2px; background: var(--accent);
      border-radius: 2px; transition: width var(--transition);
    }
    .nav-links a:hover, .nav-links a.active { color: var(--accent); }
    .nav-links a:hover::after, .nav-links a.active::after { width: 100%; }
    .nav-spacer { flex: 1; }

    /* ── Theme Toggle ── */
    .theme-toggle { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
    .toggle-label {
      font-size: .75rem; font-weight: 600; letter-spacing: .08em;
      text-transform: uppercase; color: var(--text-sub);
      width: 28px; text-align: right;
    }
    .toggle-track {
      position: relative; width: 44px; height: 24px;
      background: var(--border); border-radius: 999px;
      border: 2px solid var(--border); cursor: pointer;
      transition: background var(--transition), border-color var(--transition);
    }
    .toggle-track.on { background: var(--accent); border-color: var(--accent); }
    .toggle-knob {
      position: absolute; top: 2px; left: 2px;
      width: 16px; height: 16px; border-radius: 50%;
      background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.2);
      transition: transform var(--transition);
    }
    .toggle-track.on .toggle-knob { transform: translateX(20px); }

    /* ── Hamburger ── */
    .hamburger {
      display: none;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      width: 40px; height: 40px;
      gap: 5px; cursor: pointer;
      background: none; border: none; padding: 4px;
      margin-left: 12px; border-radius: 8px;
      transition: background .2s;
    }
    .hamburger:hover { background: var(--border); }
    .hamburger span {
      display: block; width: 22px; height: 2px;
      background: var(--text); border-radius: 2px;
      transition: transform .3s, opacity .3s, background var(--transition);
    }
    .hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .hamburger.open span:nth-child(2) { opacity: 0; }
    .hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    /* ── Mobile Menu ── */
    .mobile-menu {
      display: none;
      position: fixed;
      top: var(--nav-h); left: 0; right: 0;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      box-shadow: 0 8px 32px rgba(0,0,0,.12);
      z-index: 999;
      pointer-events: none;  
      padding: 12px 0 20px;
      transform: translateY(-8px); opacity: 0;
      transition: transform .3s, opacity .3s, background var(--transition);
    }
    .mobile-menu.open { transform: translateY(0); opacity: 1;pointer-events: all; }
    .mobile-menu ul { list-style: none; padding: 0; }
    .mobile-menu ul li a {
      display: block; padding: 14px 24px;
      font-size: .95rem; font-weight: 500;
      letter-spacing: .03em; text-transform: uppercase;
      color: var(--text-sub);
      border-left: 3px solid transparent;
      transition: color .2s, background .2s, border-color .2s;
    }
    .mobile-menu ul li a:hover,
    .mobile-menu ul li a.active {
      color: var(--accent); background: var(--accent-lt); border-left-color: var(--accent);
    }
    .mobile-menu-footer {
      display: flex; align-items: center; justify-content: space-between;
      padding: 14px 24px 0;
      border-top: 1px solid var(--border); margin-top: 8px;
    }
    .mobile-menu-footer span { font-size: .8rem; color: var(--text-sub); font-weight: 500; }

    /* ── Background ── */
    .bg-image {
      position: fixed;
      top: var(--nav-h); left: 0; right: 0; bottom: 0;
      z-index: 0;
      background: url('assets/heroo.jpg') center 30% / cover no-repeat;
      filter: brightness(.52);
      transition: filter var(--transition);
    }
    [data-theme="dark"] .bg-image { filter: brightness(.32); }
    .bg-overlay {
      position: fixed;
      top: var(--nav-h); left: 0; right: 0; bottom: 0;
      z-index: 1;
      background: linear-gradient(100deg, rgba(0,0,0,.55) 35%, rgba(0,0,0,.15) 75%);
    }

    /* ── Page wrapper — reduced top padding ── */
    .page-wrapper {
      margin-top: var(--nav-h);
      position: relative;
      z-index: 2;
      padding: 24px 5% 80px;
      max-width: 1200px;
      margin-left: auto;
      margin-right: auto;
    }

    /* ── Page header — reduced spacing ── */
    .page-header {
      padding-top: 16px;
      margin-bottom: 24px;
      animation: fadeUp .6s both;
    }
    .page-label {
      font-size: .76rem; font-weight: 700; letter-spacing: .14em;
      text-transform: uppercase; color: var(--accent); margin-bottom: 6px;
    }
    .page-title {
      font-family: 'Playfair Display', serif;
      font-size: 2.2rem; font-weight: 700;
      color: #fff; line-height: 1.2; margin-bottom: 8px;
    }
    .page-subtitle { font-size: .9rem; color: rgba(255,255,255,.6); }

    /* ── Stats row ── */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin-bottom: 24px;
      animation: fadeUp .6s .1s both;
    }
    .stat-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 16px 20px;
      transition: background var(--transition), border-color var(--transition);
    }
    .stat-label { font-size: .72rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--text-sub); margin-bottom: 6px; }
    .stat-value { font-size: 1.6rem; font-weight: 700; color: var(--text); line-height: 1; }
    .stat-value.accent { color: var(--accent); }

    /* ── Filter tabs ── */
    .filter-row {
      display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;
      animation: fadeUp .6s .15s both;
    }
    .filter-btn {
      padding: 6px 16px; border-radius: 999px;
      border: 1.5px solid var(--border);
      background: transparent; color: var(--text-sub);
      font-family: 'DM Sans', sans-serif; font-size: .8rem; font-weight: 500;
      cursor: pointer; transition: all .2s; letter-spacing: .03em;
    }
    .filter-btn:hover { border-color: var(--accent); color: var(--accent); }
    .filter-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }

    /* ── Empty state ── */
    .empty-state {
      text-align: center; padding: 80px 20px;
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 20px;
    }
    .empty-icon {
      width: 72px; height: 72px; background: var(--accent-lt);
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      margin: 0 auto 20px;
    }
    .empty-icon svg { width: 34px; height: 34px; stroke: var(--accent); fill: none; stroke-width: 1.8; }
    .empty-title { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--text); margin-bottom: 10px; }
    .empty-sub { font-size: .88rem; color: var(--text-sub); margin-bottom: 24px; }
    .btn-go {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 11px 24px; background: var(--accent); color: #fff;
      border-radius: var(--radius); font-family: 'DM Sans', sans-serif;
      font-size: .88rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
      border: none; cursor: pointer; transition: background .2s, transform .2s;
      box-shadow: 0 6px 20px rgba(76,175,80,.3);
    }
    .btn-go:hover { background: var(--accent-dk); transform: translateY(-1px); }

    /* ── Reservation cards ── */
    .reservations-list { display: flex; flex-direction: column; gap: 16px; animation: fadeUp .6s .2s both; }

    .res-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 18px;
      overflow: hidden;
      transition: background var(--transition), border-color var(--transition), box-shadow .2s;
      cursor: pointer;
      position: relative;
z-index: 2;
    }
    .res-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,.1); border-color: var(--accent); }
    .res-card-body { padding: 20px 24px; }

    .res-header {
      display: flex; align-items: flex-start;
      justify-content: space-between; gap: 12px; margin-bottom: 16px;
    }
    .res-id { display: none; }
    .res-service-name { font-size: 1.05rem; font-weight: 600; color: var(--text); }
    .res-header-right { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; flex-shrink: 0; }
    .res-badges-row { display: flex; align-items: center; gap: 6px; }

    .badge {
      display: inline-block; padding: 3px 10px; border-radius: 999px;
      font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    }
    .badge-pending   { background: #fef3c7; color: #92400e; }
    .badge-confirmed { background: var(--accent-lt); color: var(--accent-dk); }
    .badge-progress  { background: #dbeafe; color: #1e40af; }
    .badge-completed { background: #d1fae5; color: #065f46; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }

    .btn-pay {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 4px 12px; background: var(--accent); color: #fff;
      border-radius: 999px; border: none;
      font-family: 'DM Sans', sans-serif; font-size: .7rem; font-weight: 700;
      letter-spacing: .06em; text-transform: uppercase;
      cursor: pointer; transition: background .2s, box-shadow .2s;
      box-shadow: 0 3px 10px rgba(76,175,80,.35);
    }
    .btn-pay:hover { background: var(--accent-dk); box-shadow: 0 4px 14px rgba(76,175,80,.45); }
    .btn-pay svg { width: 12px; height: 12px; stroke: #fff; fill: none; stroke-width: 2.5; }

    .btn-cancel {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 4px 12px; background: #ef4444; color: #fff;
      border-radius: 999px; border: none;
      font-family: 'DM Sans', sans-serif; font-size: .7rem; font-weight: 700;
      letter-spacing: .06em; text-transform: uppercase;
      cursor: pointer; transition: background .2s, box-shadow .2s;
      box-shadow: 0 3px 10px rgba(239,68,68,.35);
    }
    .btn-cancel:hover { background: #dc2626; box-shadow: 0 4px 14px rgba(239,68,68,.45); }
    .btn-cancel svg { width: 12px; height: 12px; stroke: #fff; fill: none; stroke-width: 2.5; }

    .res-info-grid {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: 12px; padding-top: 16px; border-top: 1px solid var(--border);
      position: relative;
      z-index: 2;
    }
    .res-info-label {
      font-size: .67rem; font-weight: 600; letter-spacing: .08em;
      text-transform: uppercase; color: var(--text-sub); margin-bottom: 3px;
    }
    .res-info-value { font-size: .85rem; color: var(--text); font-weight: 500; }
    .res-info-value.price { color: var(--accent); font-weight: 700; }

    .schedule-clickable {
      cursor: pointer; color: var(--accent);
      text-decoration: underline dotted; text-underline-offset: 3px;
      transition: color .2s; display: inline-flex; align-items: center; gap: 4px;
    }
    .schedule-clickable:hover { color: var(--accent-dk); }
    .schedule-clickable svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; }

    .res-expand {
      max-height: 0; overflow: hidden;
      transition: max-height 0.35s cubic-bezier(.4,0,.2,1);
      border-top: 1px dashed transparent;
    }
    .res-expand.open { max-height: 1000px; border-top-color: var(--border); }
    .res-expand .expand-grid, .res-expand .notes-box { margin: 0 24px; }
    .res-expand .expand-grid { padding-top: 20px; padding-bottom: 20px; }

    .expand-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding-top: 20px; }
    .expand-section-title {
      font-size: .7rem; font-weight: 700; letter-spacing: .1em;
      text-transform: uppercase; color: var(--accent); margin-bottom: 10px;
    }
    .expand-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 7px 0; border-bottom: 1px solid var(--border); font-size: .84rem;
    }
    .expand-row:last-child { border-bottom: none; }
    .expand-row-label { color: var(--text-sub); }
    .expand-row-value { color: var(--text); font-weight: 500; text-align: right; }
    .expand-row-value.price { color: var(--accent); font-weight: 700; }

    .notes-box {
      margin-top: 16px; background: var(--bg2);
      border-radius: 10px; padding: 12px 16px;
      font-size: .84rem; color: var(--text-sub); line-height: 1.6;
    }
    .notes-label {
      font-size: .67rem; font-weight: 700; letter-spacing: .1em;
      text-transform: uppercase; color: var(--accent); margin-bottom: 5px;
    }

    .chevron {
      width: 16px; height: 16px; stroke: var(--text-sub); fill: none; stroke-width: 2;
      transition: transform .3s; flex-shrink: 0;
    }
    .res-card.expanded .chevron { transform: rotate(180deg); }

    @keyframes statusPulse { 0%, 100% { opacity: 1; } 50% { opacity: .55; } }
    .badge-progress-live { animation: statusPulse 1.8s ease-in-out infinite; }

    /* ── Modals ── */
    .modal-backdrop {
      position: fixed; inset: 0; background: rgba(0,0,0,.65);
      backdrop-filter: blur(6px); z-index: 9000;
      display: flex; align-items: center; justify-content: center;
      padding: 16px; opacity: 0; pointer-events: none; transition: opacity .3s;
    }
    .modal-backdrop.open { opacity: 1; pointer-events: all; }
    .modal {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 20px; width: 100%; max-width: 520px;
      max-height: 90vh; overflow-y: auto;
      box-shadow: 0 24px 64px rgba(0,0,0,.3);
      transform: translateY(24px) scale(.97);
      transition: transform .3s, opacity .3s;
    }
    .modal-backdrop.open .modal { transform: translateY(0) scale(1); }
    .modal-header {
      padding: 24px 28px 16px; border-bottom: 1px solid var(--border);
      display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    }
    .modal-label { font-size: .7rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--accent); margin-bottom: 3px; }
    .modal-title { font-family: 'Playfair Display', serif; font-size: 1.35rem; color: var(--text); }
    .modal-close {
      width: 32px; height: 32px; border-radius: 50%; border: 1.5px solid var(--border);
      background: transparent; cursor: pointer; display: flex; align-items: center; justify-content: center;
      transition: background .2s, border-color .2s; flex-shrink: 0; margin-top: 2px;
    }
    .modal-close:hover { background: var(--bg2); border-color: var(--accent); }
    .modal-close svg { width: 14px; height: 14px; stroke: var(--text-sub); fill: none; stroke-width: 2.2; }
    .modal-body { padding: 20px 28px 28px; }
    .modal-footer {
      padding: 16px 28px 24px; border-top: 1px solid var(--border);
      display: flex; gap: 10px; align-items: center; justify-content: flex-end;
    }

    .pay-service-strip {
      background: var(--bg2); border-radius: 12px; padding: 14px 16px;
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 20px; border: 1px solid var(--border);
    }
    .pay-service-name { font-size: .9rem; font-weight: 600; color: var(--text); }
    .pay-service-id { display: none; }
    .pay-service-price { font-size: 1.1rem; font-weight: 700; color: var(--accent); }
    .method-label { font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--text-sub); margin-bottom: 8px; }
    .method-select {
      width: 100%; padding: 10px 14px; border-radius: 10px;
      border: 1.5px solid var(--border); background: var(--bg2);
      color: var(--text); font-family: 'DM Sans', sans-serif;
      font-size: .88rem; font-weight: 500; outline: none;
      cursor: pointer; transition: border-color .2s; margin-bottom: 16px;
    }
    .method-select:hover, .method-select:focus { border-color: var(--accent); }
    .method-select option { background: var(--surface); color: var(--text); }

    .pay-instructions { display: none; }
    .pay-instructions.visible { display: block; }

    .transfer-info {
      background: var(--accent-lt); border: 1.5px solid var(--accent);
      border-radius: 12px; padding: 14px 16px; margin-bottom: 16px;
    }
    .transfer-info-label { font-size: .67rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--accent-dk); margin-bottom: 6px; }
    .transfer-number { font-size: 1.1rem; font-weight: 700; color: var(--text); letter-spacing: .04em; }
    .transfer-name { font-size: .8rem; color: var(--text-sub); margin-top: 2px; }

    .qris-wrapper { text-align: center; margin-bottom: 16px; }
    .qris-wrapper img { width: 180px; height: auto; border-radius: 12px; border: 2px solid var(--border); margin: 0 auto; }
    .qris-caption { font-size: .78rem; color: var(--text-sub); margin-top: 8px; text-align: center; }

    .cash-info {
      background: var(--bg2); border-radius: 12px; padding: 14px 16px;
      margin-bottom: 16px; font-size: .86rem; color: var(--text-sub); line-height: 1.6;
    }

    .upload-section { margin-top: 4px; }
    .upload-label { font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--text-sub); margin-bottom: 8px; }
    .upload-drop {
      border: 2px dashed var(--border); border-radius: 12px; padding: 24px 16px;
      text-align: center; cursor: pointer; transition: border-color .2s, background .2s; position: relative;
    }
    .upload-drop:hover, .upload-drop.dragover { border-color: var(--accent); background: var(--accent-lt); }
    .upload-drop input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
    .upload-icon { width: 36px; height: 36px; background: var(--accent-lt); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; }
    .upload-icon svg { width: 18px; height: 18px; stroke: var(--accent); fill: none; stroke-width: 2; }
    .upload-text { font-size: .82rem; color: var(--text-sub); }
    .upload-text strong { color: var(--accent); font-weight: 600; }
    .upload-formats { font-size: .7rem; color: var(--text-sub); margin-top: 4px; opacity: .7; }
    .upload-preview { display: none; margin-top: 12px; text-align: center; }
    .upload-preview img { max-height: 160px; border-radius: 8px; margin: 0 auto; border: 1.5px solid var(--border); }
    .upload-preview-name { font-size: .75rem; color: var(--text-sub); margin-top: 6px; }

    .btn-cancel-modal {
      padding: 10px 20px; border-radius: var(--radius);
      border: 1.5px solid var(--border); background: transparent;
      color: var(--text-sub); font-family: 'DM Sans', sans-serif;
      font-size: .85rem; font-weight: 500; cursor: pointer; transition: border-color .2s, color .2s;
    }
    .btn-cancel-modal:hover { border-color: var(--accent); color: var(--accent); }
    .btn-send {
      padding: 10px 24px; border-radius: var(--radius);
      background: var(--accent); color: #fff; border: none;
      font-family: 'DM Sans', sans-serif; font-size: .88rem; font-weight: 600;
      letter-spacing: .04em; text-transform: uppercase;
      cursor: pointer; transition: background .2s, box-shadow .2s;
      box-shadow: 0 4px 16px rgba(76,175,80,.3);
      display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-send:hover { background: var(--accent-dk); box-shadow: 0 6px 20px rgba(76,175,80,.4); }
    .btn-send:disabled { opacity: .55; cursor: not-allowed; box-shadow: none; }
    .btn-send svg { width: 14px; height: 14px; stroke: #fff; fill: none; stroke-width: 2.5; }

    .toast {
      position: fixed; bottom: 32px; left: 50%; transform: translateX(-50%) translateY(20px);
      background: var(--text); color: var(--bg);
      padding: 12px 24px; border-radius: 999px;
      font-size: .86rem; font-weight: 500;
      z-index: 9999; opacity: 0;
      transition: opacity .3s, transform .3s;
      pointer-events: none; white-space: nowrap;
    }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .toast.success { background: var(--accent); color: #fff; }
    .toast.error   { background: #ef4444; color: #fff; }

    /* ── Reschedule modal ── */
    .reschedule-modal { max-width: 440px; }
    .datetime-group { margin-bottom: 16px; }
    .datetime-group label { display: block; font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--text-sub); margin-bottom: 6px; }
    .datetime-group input[type="datetime-local"] {
      width: 100%; padding: 10px 14px; border: 1.5px solid var(--border);
      border-radius: 10px; background: var(--bg2); color: var(--text);
      font-family: 'DM Sans', sans-serif; font-size: .88rem; outline: none; transition: border-color .2s;
    }
    .datetime-group input:focus { border-color: var(--accent); }
    .reschedule-note {
      background: #fef3c7; color: #92400e; border-radius: 10px;
      padding: 10px 14px; font-size: .8rem; line-height: 1.5;
      margin-bottom: 20px; display: flex; gap: 8px; align-items: flex-start;
    }
    [data-theme="dark"] .reschedule-note { background: #2d1f00; color: #f59e0b; }
    .reschedule-note svg { width: 16px; height: 16px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 2; margin-top: 1px; }

    @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

    /* ── Responsive ── */
    @media (max-width: 900px) {
      .stats-row { grid-template-columns: repeat(2, 1fr); }
      .res-info-grid { grid-template-columns: repeat(2, 1fr); }
      .expand-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      :root { --nav-h: 60px; }
      .nav-links { display: none; }
      .hamburger { display: flex; }
      .mobile-menu { display: block; }
    }
    @media (max-width: 620px) {
      .page-title { font-size: 1.6rem; }
      .stats-row { grid-template-columns: repeat(2, 1fr); }
      .res-info-grid { grid-template-columns: 1fr 1fr; }
      .res-card-body { padding: 16px; }
      .modal-body, .modal-header, .modal-footer { padding-left: 18px; padding-right: 18px; }
    }
    @media (max-width: 480px) {
      :root { --nav-h: 56px; }
      .res-info-grid { grid-template-columns: 1fr; }
      .stats-row { grid-template-columns: 1fr 1fr; }
      .method-select { font-size: .8rem; padding: 8px 10px; }
      .toggle-label { display: none; }
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <a href="index.php" class="nav-logo">
    <img src="assets/logolm.png" alt="CatDogKu" class="logo-light" />
    <img src="assets/logodm.png" alt="CatDogKu" class="logo-dark" />
  </a>
  <ul class="nav-links">
    <li><a href="index.php#home">Home</a></li>
    <li><a href="index.php#about">About</a></li>
    <li><a href="index.php#service">Service</a></li>
    <li><a href="reserve.php">Reserve</a></li>
    <li><a href="cust_profile.php">Profile</a></li>
    <li><a href="index.php#contact">Contact</a></li>
  </ul>
  <div class="nav-spacer"></div>
  <div class="theme-toggle" id="themeToggle" role="button" tabindex="0" aria-label="Toggle dark mode">
    <span class="toggle-label" id="toggleLabel">OFF</span>
    <div class="toggle-track" id="toggleTrack"><div class="toggle-knob"></div></div>
  </div>
  <button class="hamburger" id="hamburger" aria-label="Toggle navigation menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- MOBILE MENU -->
<div class="mobile-menu" id="mobileMenu" aria-hidden="true">
  <ul>
    <li><a href="index.php#home" onclick="closeMenu()">Home</a></li>
    <li><a href="index.php#about" onclick="closeMenu()">About</a></li>
    <li><a href="index.php#service" onclick="closeMenu()">Service</a></li>
    <li><a href="reserve.php" onclick="closeMenu()">Reserve</a></li>
    <li><a href="cust_profile.php" onclick="closeMenu()">Profile</a></li>
    <li><a href="index.php#contact" onclick="closeMenu()">Contact</a></li>
  </ul>
  <div class="mobile-menu-footer">
    <span>Dark Mode</span>
    <div class="theme-toggle" id="themeToggleMobile" role="button" tabindex="0" aria-label="Toggle dark mode">
      <div class="toggle-track" id="toggleTrackMobile"><div class="toggle-knob"></div></div>
    </div>
  </div>
</div>

<div class="bg-image"></div>
<div class="bg-overlay"></div>

<div class="page-wrapper" style="margin-top: var(--nav-h);">

  <div class="page-header">
    <p class="page-label">History</p>
    <p class="page-subtitle">Track all your bookings and their current status.</p>
  </div>

  <?php
  $total    = count($reservations);
  $pending  = count(array_filter($reservations, fn($r) => $r['status'] === 'pending'));
  $active   = count(array_filter($reservations, fn($r) => in_array($r['status'], ['confirmed', 'in_progress'])));
  $done     = count(array_filter($reservations, fn($r) => $r['status'] === 'completed'));
  ?>

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-label">Total Reservations</div>
      <div class="stat-value accent"><?= $total ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pending</div>
      <div class="stat-value"><?= $pending ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Active</div>
      <div class="stat-value"><?= $active ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Completed</div>
      <div class="stat-value"><?= $done ?></div>
    </div>
  </div>

  <div class="filter-row">
    <button class="filter-btn active" data-filter="all">All</button>
    <button class="filter-btn" data-filter="pending">Pending</button>
    <button class="filter-btn" data-filter="confirmed">Confirmed</button>
    <button class="filter-btn" data-filter="in_progress">In Progress</button>
    <button class="filter-btn" data-filter="completed">Completed</button>
  </div>

  <?php if (empty($reservations)): ?>
  <div class="empty-state">
    <div class="empty-icon">
      <svg viewBox="0 0 24 24">
        <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
        <rect x="9" y="3" width="6" height="4" rx="1" ry="1"/>
        <line x1="9" y1="12" x2="15" y2="12"/>
        <line x1="9" y1="16" x2="11" y2="16"/>
      </svg>
    </div>
    <p class="empty-title">No reservations yet</p>
    <p class="empty-sub">You haven't made any bookings. Book your first session now!</p>
    <a href="reserve.php" class="btn-go">Make a Reservation</a>
  </div>

  <?php else: ?>
  <div class="reservations-list" id="reservationList">

    <?php foreach ($reservations as $i => $res): ?>
    <?php
      $status   = $res['status'] ?? 'pending';
      $mulai    = $res['waktu_mulai'] ? date('D, d M Y · H:i', strtotime($res['waktu_mulai'])) : '—';
      $selesai  = $res['waktu_selesai'] ? date('H:i', strtotime($res['waktu_selesai'])) : '—';
      $created  = $res['created_at'] ? date('d M Y', strtotime($res['created_at'])) : '—';
      $subtotal = $res['subtotal'] ? 'Rp ' . number_format($res['subtotal'], 0, ',', '.') : '—';
      $harga    = $res['price_snapshot'] ? 'Rp ' . number_format($res['price_snapshot'], 0, ',', '.') : '—';
      $totalBayar = $res['total_bayar'] ? 'Rp ' . number_format($res['total_bayar'], 0, ',', '.') : '—';
      $statusBayar = $res['status_bayar'] ?? '';
      // Show Pay button only if not yet paid and reservation is not cancelled
      $showPay = ($statusBayar !== 'paid') && ($status !== 'cancelled');
      // Show reschedule only for pending/confirmed
      $canReschedule = in_array($status, ['pending', 'confirmed']);

      // Safely encode data for JS
      $jsService  = htmlspecialchars($res['nama_service'] ?? '', ENT_QUOTES);
      $jsSubtotal = $res['subtotal'] ? number_format($res['subtotal'], 0, ',', '.') : '0';
      $jsId       = (int)$res['id_reservation'];
      $jsMulai    = $res['waktu_mulai'] ? date('Y-m-d\TH:i', strtotime($res['waktu_mulai'])) : '';
      $jsSelesai  = $res['waktu_selesai'] ? date('Y-m-d\TH:i', strtotime($res['waktu_selesai'])) : '';
      $jsDurasi   = (int)($res['durasi_estimasi'] ?? 0);
      $rawMulai   = $res['waktu_mulai'] ?? '';
      $rawSelesai = $res['waktu_selesai'] ?? '';
    ?>
    <div class="res-card" data-status="<?= htmlspecialchars($status) ?>"
         style="animation-delay: <?= $i * 0.05 ?>s;">
      <div class="res-card-body">
        <div class="res-header" onclick="toggleCard(this.closest('.res-card'))">
          <div>
            <div class="res-id"> Booked <?= $created ?></div>
            <div class="res-service-name"><?= htmlspecialchars($res['nama_service'] ?? '—') ?></div>
          </div>
          <div class="res-header-right">
            <div class="res-badges-row">
              <span class="status-badge-wrap"><?= statusBadge($status) ?></span>
              <?= payBadge($statusBayar) ?>
              <?php if ($showPay): ?>
              <button type="button" class="btn-pay" onclick="event.stopPropagation(); openPayModal(<?= $jsId ?>, '<?= $jsService ?>', '<?= $jsSubtotal ?>')">
                <svg viewBox="0 0 24 24"><path d="M2 9h20M2 15h8"/><rect x="2" y="5" width="20" height="14" rx="2"/></svg>
                Pay
              </button>
              <?php endif; ?>
              <svg class="chevron" viewBox="0 0 24 24" onclick="event.stopPropagation(); toggleCard(this.closest('.res-card'))"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
          </div>
        </div>

<div class="res-info-grid" onclick="toggleCard(this.closest('.res-card'))" style="cursor:pointer;">
  <div class="res-info-item">
            <div class="res-info-label">Pet</div>
            <div class="res-info-value">
              <?= htmlspecialchars($res['nama_pet'] ?? '—') ?>
              <?php if ($res['nama_species']): ?>
                <span style="color:var(--text-sub); font-size:.75rem;">(<?= htmlspecialchars($res['nama_species']) ?>)</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="res-info-item">
  <div class="res-info-label">Schedule</div>
  <div class="res-info-value">
    <?php if ($canReschedule): ?>
    <span class="schedule-clickable" 
          onclick="event.stopPropagation(); event.preventDefault(); openReschedule(<?= $jsId ?>, '<?= $jsMulai ?>', <?= $jsDurasi ?>); return false;">
      <svg viewBox="0 0 24 24">...</svg>
      <?= $mulai ?> – <?= $selesai ?>
    </span>
    <?php else: ?>
      <span><?= $mulai ?> – <?= $selesai ?></span>
    <?php endif; ?>
  </div>
</div>
          <div class="res-info-item">
            <div class="res-info-label">Total</div>
            <div class="res-info-value price"><?= $subtotal ?></div>
          </div>
        </div>
      </div>

      <!-- Expand detail -->
      <div class="res-expand">
        <div class="expand-grid">
          <div>
            <div class="expand-section-title">Details</div>
            <div class="expand-row">
              <span class="expand-row-label">Service</span>
              <span class="expand-row-value"><?= htmlspecialchars($res['nama_service'] ?? '—') ?></span>
            </div>
            <div class="expand-row">
              <span class="expand-row-label">Duration</span>
              <span class="expand-row-value"><?= $res['durasi_estimasi'] ? $res['durasi_estimasi'] . ' min' : '—' ?></span>
            </div>
            <div class="expand-row">
              <span class="expand-row-label">Bill</span>
              <span class="expand-row-value price"><?= $harga ?></span>
            </div>
            <div class="expand-row">
              <span class="expand-row-label">Staff</span>
              <span class="expand-row-value">
                <?= htmlspecialchars($res['nama_staff'] ?? '—') ?>
                <?php if ($res['jabatan']): ?>
                  <span style="color:var(--text-sub); font-size:.75rem;">(<?= htmlspecialchars($res['jabatan']) ?>)</span>
                <?php endif; ?>
              </span>
            </div>
          </div>
          <div>
            <div class="expand-section-title">Payment</div>
            <div class="expand-row">
              <span class="expand-row-label">Status</span>
              <span class="expand-row-value"><?= payBadge($statusBayar) ?></span>
            </div>
            <div class="expand-row">
              <span class="expand-row-label">Payment Date</span>
              <span class="expand-row-value"><?= $res['tgl_bayar'] ? date('d M Y · H:i', strtotime($res['tgl_bayar'])) : '—' ?></span>
            </div>
            <div class="expand-row">
              <span class="expand-row-label">Amount Paid</span>
              <span class="expand-row-value price"><?= $totalBayar ?></span>
            </div>
            <div class="expand-row">
              <span class="expand-row-label">End Time</span>
              <span class="expand-row-value">
                <?= $res['waktu_selesai'] ? date('d M Y · H:i', strtotime($res['waktu_selesai'])) : '—' ?>
              </span>
            </div>
          </div>
        </div>

        <?php if (!empty($res['catatan'])): ?>
        <div class="notes-box">
          <div class="notes-label">Catatan</div>
          <?= htmlspecialchars($res['catatan']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

  </div>
  <?php endif; ?>

</div><!-- /page-wrapper -->


<!-- PAYMENT MODAL -->
<div class="modal-backdrop" id="payModalBackdrop">
  <div class="modal" id="payModal">
    <div class="modal-header">
      <div class="modal-title-wrap">
        <div class="modal-label">Payment</div>
      </div>
      <button class="modal-close" onclick="closePayModal()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="modal-body">
      <div class="pay-service-strip">
        <div>
          <div class="pay-service-name" id="payServiceName">—</div>
          <div class="pay-service-id" id="payServiceId"></div>
        </div>
        <div class="pay-service-price" id="payServicePrice">—</div>
      </div>
      <div class="method-label">Payment Method</div>
      <select class="method-select" id="paymentMethodSelect" onchange="selectMethod(this.value)">
        <option value="transfer">Transfer Bank / GoPay</option>
        <option value="qris">QRIS</option>
        <option value="cash">Cash On-Site</option>
      </select>
      <div class="pay-instructions visible" id="instr-transfer">
        <div class="transfer-info">
          <div class="transfer-info-label">Transfer to</div>
          <div class="transfer-number">085624312595</div>
          <div class="transfer-name">GoPay · a/n CatDogKu</div>
        </div>
        <div class="upload-section">
          <div class="upload-label">upload proof of payment <span style="color:#ef4444">*</span></div>
          <div class="upload-drop" id="uploadDrop" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event)">
            <input type="file" id="fileInput" accept=".jpg,.jpeg,.png" onchange="handleFileSelect(event)" />
            <div class="upload-icon"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div>
            <div class="upload-text"><strong>Click or drag & drop</strong> your proof of payment</div>
            <div class="upload-formats">JPG, JPEG, PNG</div>
          </div>
          <div class="upload-preview" id="uploadPreview">
            <img id="previewImg" src="" alt="Preview" />
            <div class="upload-preview-name" id="previewName"></div>
          </div>
        </div>
      </div>
      <div class="pay-instructions" id="instr-qris">
        <div class="qris-wrapper">
          <img src="assets/qris.jpeg" alt="QRIS CatDogKu" />
          <div class="qris-caption">Scan the QR code above using your payment app</div>
        </div>
        <div class="upload-section">
          <div class="upload-label">upload proof of payment <span style="color:#ef4444">*</span></div>
          <div class="upload-drop" id="uploadDropQris" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, 'qris')">
            <input type="file" id="fileInputQris" accept=".jpg,.jpeg,.png" onchange="handleFileSelect(event, 'qris')" />
            <div class="upload-icon"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div>
            <div class="upload-text"><strong>Click or drag & drop</strong> screenshot of proof of payment</div>
            <div class="upload-formats">JPG, JPEG, PNG</div>
          </div>
          <div class="upload-preview" id="uploadPreviewQris">
            <img id="previewImgQris" src="" alt="Preview" />
            <div class="upload-preview-name" id="previewNameQris"></div>
          </div>
        </div>
      </div>
      <div class="pay-instructions" id="instr-cash">
        <div class="cash-info">Cash payments are made on-site at arrival or pet pick-up. Please bring the exact amount due.</div>
        <div style="font-size:.78rem; color:var(--text-sub); padding: 0 2px; line-height:1.6;">
          You do not need to upload proof of payment for cash. Click <strong>Send</strong> to confirm your cash payment intention.
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel-modal" onclick="closePayModal()">Cancel</button>
      <button class="btn-send" id="btnSend" onclick="submitPayment()">
        <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Send
      </button>
    </div>
  </div>
</div>


<!-- RESCHEDULE MODAL -->
<div class="modal-backdrop" id="rescheduleBackdrop">
  <div class="modal reschedule-modal">
    <div class="modal-header">
      <div class="modal-title-wrap">
        <div class="modal-label">Reschedule</div>
      </div>
      <button class="modal-close" onclick="closeReschedule()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="reschedule-note">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Rescheduling will return the booking status to <strong>Pending</strong> and require admin re-approval.
      </div>
      <div class="datetime-group">
        <label>Start Time</label>
        <input type="datetime-local" id="rsMulai" onchange="updateEndTime()" />
      </div>
      <div class="datetime-group">
        <label>End Time (Auto-calculated)</label>
        <input type="datetime-local" id="rsSelesai" readonly style="background:var(--bg2);cursor:not-allowed;" />
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel-modal" onclick="closeReschedule()">Cancel</button>
      <button class="btn-send" id="btnReschedule" onclick="submitReschedule()">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Save
      </button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  /* ── Theme ── */
  const html  = document.documentElement;
  const track = document.getElementById('toggleTrack');
  const label = document.getElementById('toggleLabel');
  const trackMobile = document.getElementById('toggleTrackMobile');

  function applyTheme(dark) {
    html.setAttribute('data-theme', dark ? 'dark' : 'light');
    track.classList.toggle('on', dark);
    if (trackMobile) trackMobile.classList.toggle('on', dark);
    label.textContent = dark ? 'ON' : 'OFF';
    localStorage.setItem('theme', dark ? 'dark' : 'light');
  }

  function toggleTheme() { applyTheme(html.getAttribute('data-theme') !== 'dark'); }

  const saved = localStorage.getItem('theme');
  applyTheme(saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches);

  document.getElementById('themeToggle').addEventListener('click', toggleTheme);
  document.getElementById('themeToggle').addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleTheme(); }
  });
  if (document.getElementById('themeToggleMobile')) {
    document.getElementById('themeToggleMobile').addEventListener('click', toggleTheme);
  }

  /* ── Hamburger / Mobile Menu ── */
  const hamburger  = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  let menuOpen = false;

  function closeMenu() {
    menuOpen = false;
    hamburger.classList.remove('open');
    hamburger.setAttribute('aria-expanded', 'false');
    mobileMenu.classList.remove('open');
    mobileMenu.setAttribute('aria-hidden', 'true');
  }

  hamburger.addEventListener('click', () => {
    menuOpen = !menuOpen;
    hamburger.classList.toggle('open', menuOpen);
    hamburger.setAttribute('aria-expanded', menuOpen);
    mobileMenu.classList.toggle('open', menuOpen);
    mobileMenu.setAttribute('aria-hidden', !menuOpen);
  });

  document.addEventListener('click', e => {
    if (menuOpen && !hamburger.contains(e.target) && !mobileMenu.contains(e.target)) closeMenu();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && menuOpen) closeMenu(); });

  /* ── Expand / collapse card ── */
  function toggleCard(card) {
    const panel  = card.querySelector('.res-expand');
    const isOpen = card.classList.contains('expanded');
    document.querySelectorAll('.res-card.expanded').forEach(c => {
      c.classList.remove('expanded');
      c.querySelector('.res-expand').classList.remove('open');
    });
    if (!isOpen) { card.classList.add('expanded'); panel.classList.add('open'); }
  }

  /* ── Filter ── */
  const filterBtns = document.querySelectorAll('.filter-btn');
  const cards      = document.querySelectorAll('.res-card');

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const f = btn.dataset.filter;
      cards.forEach(card => {
        card.style.display = (f === 'all' || card.dataset.status === f) ? '' : 'none';
      });
    });
  });

  /* ── Auto Status Update ── */
  const STATUS_BADGE = {
    pending:     '<span class="badge badge-pending">Pending</span>',
    confirmed:   '<span class="badge badge-confirmed">Confirmed</span>',
    in_progress: '<span class="badge badge-progress badge-progress-live">In Progress</span>',
    completed:   '<span class="badge badge-completed">Completed</span>',
    cancelled:   '<span class="badge badge-cancelled">Cancelled</span>',
  };

  function computeStatus(currentStatus, mulaiStr, selesaiStr) {
    if (!mulaiStr || !selesaiStr) return currentStatus;
    if (currentStatus === 'pending' || currentStatus === 'cancelled') return currentStatus;
    const now = new Date(), mulai = new Date(mulaiStr), selesai = new Date(selesaiStr);
    if (currentStatus === 'confirmed') {
      if (now >= selesai) return 'completed';
      if (now >= mulai)   return 'in_progress';
    }
    if (currentStatus === 'in_progress' && now >= selesai) return 'completed';
    return currentStatus;
  }

  function syncCardStatus(card) {
    const newStatus = computeStatus(card.dataset.status, card.dataset.mulai, card.dataset.selesai);
    if (newStatus === card.dataset.status) return false;
    card.dataset.status = newStatus;
    const badgeWrap = card.querySelector('.status-badge-wrap');
    if (badgeWrap) badgeWrap.innerHTML = STATUS_BADGE[newStatus] || STATUS_BADGE.pending;
    if (!['pending', 'confirmed'].includes(newStatus)) {
      const cancelBtn = card.querySelector('.btn-cancel');
      if (cancelBtn) cancelBtn.style.display = 'none';
      const sched = card.querySelector('.schedule-clickable');
      if (sched) { sched.style.cursor = 'default'; sched.style.textDecoration = 'none'; sched.style.color = 'var(--text)'; sched.onclick = null; }
    }
    return true;
  }

  function pushStatusToServer() {
    const fd = new FormData();
    fd.append('action', 'auto_update_status');
    fetch(window.location.href, { method: 'POST', body: fd }).catch(() => {});
  }

  function runAutoStatusSync() {
    let anyChanged = false;
    document.querySelectorAll('.res-card').forEach(card => { if (syncCardStatus(card)) anyChanged = true; });
    if (anyChanged) {
      pushStatusToServer();
      const activeFilter = document.querySelector('.filter-btn.active');
      if (activeFilter) activeFilter.click();
    }
  }

  runAutoStatusSync();
  setInterval(runAutoStatusSync, 30000);

  /* ── Payment Modal ── */
  let currentResId = null, currentMethod = 'transfer', selectedFile = null;

  function openPayModal(id, serviceName, price) {
    currentResId = id; currentMethod = 'transfer'; selectedFile = null;
    document.getElementById('payServiceName').textContent  = serviceName || '—';
    document.getElementById('payServicePrice').textContent = price ? 'Rp ' + price : '—';
    document.getElementById('paymentMethodSelect').value = 'transfer';
    document.querySelectorAll('.pay-instructions').forEach(p => p.classList.remove('visible'));
    document.getElementById('instr-transfer').classList.add('visible');
    resetFileUpload();
    document.getElementById('payModalBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closePayModal() {
    document.getElementById('payModalBackdrop').classList.remove('open');
    document.body.style.overflow = '';
  }

  document.getElementById('payModalBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closePayModal();
  });

  function selectMethod(method) {
    currentMethod = method; selectedFile = null;
    document.querySelectorAll('.pay-instructions').forEach(p => p.classList.remove('visible'));
    document.getElementById('instr-' + method).classList.add('visible');
    resetFileUpload();
  }

  function resetFileUpload() {
    ['fileInput','fileInputQris'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    ['uploadPreview','uploadPreviewQris'].forEach(id => { const el = document.getElementById(id); if (el) el.style.display = 'none'; });
    ['uploadDrop','uploadDropQris'].forEach(id => { const el = document.getElementById(id); if (el) el.classList.remove('dragover'); });
  }

  function handleFileSelect(event, type) { const file = event.target.files[0]; if (!file) return; processFile(file, type); }
  function handleDragOver(event) { event.preventDefault(); event.currentTarget.classList.add('dragover'); }
  function handleDragLeave(event) { event.currentTarget.classList.remove('dragover'); }
  function handleDrop(event, type) {
    event.preventDefault(); event.currentTarget.classList.remove('dragover');
    const file = event.dataTransfer.files[0]; if (!file) return; processFile(file, type);
  }

  function processFile(file, type) {
    if (!['image/jpeg','image/jpg','image/png'].includes(file.type)) {
      showToast('Format tidak didukung. Gunakan JPG/JPEG/PNG.', 'error'); return;
    }
    selectedFile = file;
    const suffix = type === 'qris' ? 'Qris' : '';
    const previewDiv  = document.getElementById('uploadPreview' + suffix);
    const previewImg  = document.getElementById('previewImg' + suffix);
    const previewName = document.getElementById('previewName' + suffix);
    const dt = new DataTransfer(); dt.items.add(file);
    document.getElementById(type === 'qris' ? 'fileInputQris' : 'fileInput').files = dt.files;
    const reader = new FileReader();
    reader.onload = e => { previewImg.src = e.target.result; previewName.textContent = file.name; previewDiv.style.display = 'block'; };
    reader.readAsDataURL(file);
  }

  function submitPayment() {
    if (!currentResId) return;
    if (currentMethod !== 'cash') {
      const input = document.getElementById(currentMethod === 'qris' ? 'fileInputQris' : 'fileInput');
      if (!input.files || input.files.length === 0) { showToast('Please upload proof of payment first.', 'error'); return; }
      selectedFile = input.files[0];
    }
    const btn = document.getElementById('btnSend');
    btn.disabled = true;
    btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;animation:spin .8s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Sending...';
    const formData = new FormData();
    formData.append('action', 'pay');
    formData.append('id_reservation', currentResId);
    formData.append('metode_bayar', currentMethod);
    if (selectedFile) formData.append('bukti_bayar', selectedFile);
    fetch(window.location.href, { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) { showToast(data.message, 'success'); closePayModal(); setTimeout(() => location.reload(), 1800); }
        else showToast(data.message || 'Failed to send payment.', 'error');
      })
      .catch(() => showToast('A network error occurred.', 'error'))
      .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send';
      });
  }

  /* ── Reschedule Modal ── */
  let currentRsId = null, currentDuration = 0;

  function openReschedule(id, mulai, durasi) {
    currentRsId = id; currentDuration = durasi;
    document.getElementById('rsMulai').value = mulai || '';
    updateEndTime();
    document.getElementById('rescheduleBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function updateEndTime() {
    const startInput = document.getElementById('rsMulai').value;
    if (!startInput || currentDuration <= 0) { document.getElementById('rsSelesai').value = ''; return; }
    const startTime = new Date(startInput);
    const endTime   = new Date(startTime.getTime() + currentDuration * 60000);
    const pad = n => String(n).padStart(2, '0');
    document.getElementById('rsSelesai').value =
      `${endTime.getFullYear()}-${pad(endTime.getMonth()+1)}-${pad(endTime.getDate())}T${pad(endTime.getHours())}:${pad(endTime.getMinutes())}`;
  }

  function closeReschedule() {
    document.getElementById('rescheduleBackdrop').classList.remove('open');
    document.body.style.overflow = '';
  }

  document.getElementById('rescheduleBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeReschedule();
  });

  function submitReschedule() {
    if (!currentRsId) return;
    const mulai   = document.getElementById('rsMulai').value;
    const selesai = document.getElementById('rsSelesai').value;
    if (!mulai)   { showToast('Please select a start time.', 'error'); return; }
    if (!selesai) { showToast('End time could not be calculated. Please try again.', 'error'); return; }
    const btn = document.getElementById('btnReschedule');
    btn.disabled = true;
    const formData = new FormData();
    formData.append('action', 'reschedule');
    formData.append('id_reservation', currentRsId);
    formData.append('waktu_mulai',   mulai.replace('T', ' ') + ':00');
    formData.append('waktu_selesai', selesai.replace('T', ' ') + ':00');
    fetch(window.location.href, { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) { showToast(data.message, 'success'); closeReschedule(); setTimeout(() => location.reload(), 1800); }
        else showToast(data.message || 'Failed to change schedule.', 'error');
      })
      .catch(() => showToast('A network error occurred.', 'error'))
      .finally(() => { btn.disabled = false; currentDuration = 0; });
  }

  /* ── Toast ── */
  function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.className = 'toast ' + (type || ''); t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
  }

  /* ── Cancel reservation ── */
  function cancelReservation(id) {
    if (!confirm('Are you sure you want to cancel this reservation? This action cannot be undone.')) return;
    const formData = new FormData();
    formData.append('action', 'cancel');
    formData.append('id_reservation', id);
    fetch(window.location.href, { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1800); }
        else showToast(data.message || 'Failed to cancel reservation.', 'error');
      })
      .catch(() => showToast('A network error occurred.', 'error'));
  }

  const style = document.createElement('style');
  style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
  document.head.appendChild(style);
</script>
</body>
</html>