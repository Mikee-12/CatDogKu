<?php
include "config/koneksi.php";
session_start();

if (!isset($_SESSION['customer_id'])) {
    echo "<script>window.location='cust_login.php';</script>";
    exit;
}

$customer_id = $_SESSION['customer_id'];

$pets_query = "SELECT hp.id_pet AS id_hewan, hp.nama_pet AS nama_hewan, s.nama_species AS nama_jenis, b.nama_breed AS nama_ras
               FROM pets hp
               LEFT JOIN breeds b ON hp.id_breed = b.id_breed
               LEFT JOIN species s ON b.id_species = s.id_species
               WHERE hp.id_user = $customer_id";
$pets_result = mysqli_query($conn, $pets_query);
$pets = [];
while ($row = mysqli_fetch_assoc($pets_result)) { $pets[] = $row; }

$services_query = "SELECT s.id_service AS id_layanan, s.nama_service, s.harga_base, s.durasi_estimasi FROM services s";
$services_result = mysqli_query($conn, $services_query);
$services = [];
while ($row = mysqli_fetch_assoc($services_result)) { $services[] = $row; }

if (isset($_GET['action']) && $_GET['action'] === 'get_staff') {
    $service_id  = intval($_GET['service_id']);
    $waktu_mulai = $_GET['waktu_mulai'];
    $english_day = date('l', strtotime($waktu_mulai));
    $day_map = ['Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu','Sunday'=>'Minggu'];
    $day_of_week = $day_map[$english_day] ?? $english_day;
    $time_check  = date('H:i:s', strtotime($waktu_mulai));

    $staff_result = mysqli_query($conn, "
        SELECT st.id_staff AS id_staf, st.nama_staff AS nama_staf
        FROM staffs st
        JOIN staff_schedules ss ON st.id_staff = ss.id_staff
        JOIN services sv ON sv.id_specialization = st.id_specialization
        WHERE st.is_active = 1
          AND sv.id_service = $service_id
          AND ss.hari = '$day_of_week'
          AND '$time_check' BETWEEN ss.jam_mulai AND ss.jam_selesai
        ORDER BY RAND()
        LIMIT 1");
    $staff = mysqli_fetch_assoc($staff_result);

    $dur_row   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT durasi_estimasi FROM services WHERE id_service = $service_id"));
    $durasi    = $dur_row ? intval($dur_row['durasi_estimasi']) : 60;
    $waktu_selesai = date('Y-m-d H:i:s', strtotime($waktu_mulai) + $durasi * 60);

    echo json_encode(['staff' => $staff, 'waktu_selesai' => $waktu_selesai, 'durasi' => $durasi]);
    exit;
}

if (isset($_POST['reserve'])) {
    $id_hewan      = intval($_POST['id_hewan']);
    $id_service    = intval($_POST['id_service']);
    $id_staf       = intval($_POST['id_staf']);
    $waktu_mulai   = $_POST['waktu_mulai'];
    $waktu_selesai = $_POST['waktu_selesai'];
    $catatan       = mysqli_real_escape_string($conn, $_POST['catatan_reservasi'] ?? '');

    $price_row   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT harga_base AS harga FROM services WHERE id_service = $id_service"));
    $total_harga = $price_row ? $price_row['harga'] : 0;

    $insert_result = mysqli_query($conn, "INSERT INTO reservations (id_user, id_pet, waktu_mulai, waktu_selesai, status, catatan)
               VALUES ($customer_id, $id_hewan, '$waktu_mulai', '$waktu_selesai', 'pending', '$catatan')");

    if ($insert_result) {
        $new_id = mysqli_insert_id($conn);
        mysqli_query($conn, "INSERT INTO reservation_details (id_reservation, id_service, id_staff, price_snapshot, subtotal)
                             VALUES ($new_id, $id_service, $id_staf, $total_harga, $total_harga)");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reserve — CatDogKu</title>
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
      --nav-h:      72px;
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
      height: 100vh;
      overflow: hidden;
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
    .nav-logo img { height: 42px; width: auto; }
    [data-theme="light"] .logo-dark  { display: none; }
    [data-theme="dark"]  .logo-light { display: none; }

    .nav-links {
      display: flex;
      align-items: center;
      gap: 36px;
      list-style: none;
      margin-left: 52px;
    }
    .nav-links a {
      font-size: .92rem;
      font-weight: 500;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: var(--text-sub);
      position: relative;
      transition: color var(--transition);
    }
    .nav-links a::after {
      content: '';
      position: absolute;
      left: 0; bottom: -4px;
      width: 0; height: 2px;
      background: var(--accent);
      border-radius: 2px;
      transition: width var(--transition);
    }
    .nav-links a:hover,
    .nav-links a.active { color: var(--accent); }
    .nav-links a:hover::after,
    .nav-links a.active::after { width: 100%; }

    .nav-spacer { flex: 1; }

    /* ── Theme Toggle ── */
    .theme-toggle { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
    .toggle-label {
      font-size: .78rem; font-weight: 600; letter-spacing: .08em;
      text-transform: uppercase; color: var(--text-sub);
      transition: color var(--transition); width: 28px; text-align: right;
    }
    .toggle-track {
      position: relative; width: 48px; height: 26px;
      background: var(--border); border-radius: 999px;
      transition: background var(--transition), border-color var(--transition);
      border: 2px solid var(--border); cursor: pointer;
    }
    .toggle-track.on { background: var(--accent); border-color: var(--accent); }
    .toggle-knob {
      position: absolute; top: 2px; left: 2px;
      width: 18px; height: 18px; border-radius: 50%;
      background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.2);
      transition: transform var(--transition);
    }
    .toggle-track.on .toggle-knob { transform: translateX(22px); }

    /* ── Hamburger ── */
    .hamburger {
      display: none;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      width: 40px; height: 40px;
      gap: 5px;
      cursor: pointer;
      background: none;
      border: none;
      padding: 4px;
      margin-left: 12px;
      border-radius: 8px;
      transition: background .2s;
      flex-shrink: 0;
    }
    .hamburger:hover { background: var(--border); }
    .hamburger span {
      display: block;
      width: 22px; height: 2px;
      background: var(--text);
      border-radius: 2px;
      transition: transform .3s, opacity .3s, background var(--transition);
    }
    .hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .hamburger.open span:nth-child(2) { opacity: 0; }
    .hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    /* ── Mobile Menu Drawer ── */
    .mobile-menu {
      display: none;
      position: fixed;
      top: var(--nav-h); left: 0; right: 0;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      box-shadow: 0 8px 32px rgba(0,0,0,.12);
      z-index: 1002;
      padding: 12px 0 20px;
      transform: translateY(-8px);
      opacity: 0;
      transition: transform .3s, opacity .3s, background var(--transition);
    }
    .mobile-menu.open { transform: translateY(0); opacity: 1; }
    .mobile-menu ul { list-style: none; padding: 0; }
    .mobile-menu ul li a {
      display: block;
      padding: 14px 24px;
      font-size: .95rem; font-weight: 500;
      letter-spacing: .03em; text-transform: uppercase;
      color: var(--text-sub);
      border-left: 3px solid transparent;
      transition: color .2s, background .2s, border-color .2s;
    }
    .mobile-menu ul li a:hover,
    .mobile-menu ul li a.active {
      color: var(--accent);
      background: var(--accent-lt);
      border-left-color: var(--accent);
    }
    .mobile-menu-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 24px 0;
      border-top: 1px solid var(--border);
      margin-top: 8px;
    }
    .mobile-menu-footer span {
      font-size: .8rem; color: var(--text-sub); font-weight: 500;
    }

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

    /* ── Page Wrapper ── */
    .page-wrapper {
      margin-top: var(--nav-h);
      height: calc(100vh - var(--nav-h));
      position: relative;
      z-index: 2;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px 3%;
      overflow: hidden;
    }

    /* ── Reserve Card ── */
    .reserve-card {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 1380px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 28px;
      padding: 36px 52px;
      box-shadow: 0 24px 80px rgba(0,0,0,.28);
      animation: fadeUp .7s .15s both;
      transition: background var(--transition), border-color var(--transition);
    }

    /* ── Card Header ── */
    .card-header { margin-bottom: 26px; }
    .card-label {
      font-size: .76rem; font-weight: 700; letter-spacing: .14em;
      text-transform: uppercase; color: var(--accent); margin-bottom: 3px;
    }
    .card-subtitle {
      font-size: .87rem; color: var(--text-sub); line-height: 1.5;
    }

    /* ── Two-column layout ── */
    .reserve-inner {
      display: grid;
      grid-template-columns: 400px 1fr;
      align-items: start;
      position: relative;
    }
    .reserve-inner::after {
      content: '';
      position: absolute;
      left: 400px;
      top: 0; bottom: 0;
      width: 1px;
      background: var(--border);
      pointer-events: none;
    }
    .reserve-left {
      display: flex;
      flex-direction: column;
      gap: 15px;
      padding-right: 44px;
    }
    .reserve-right {
      display: flex;
      flex-direction: column;
      gap: 12px;
      padding-left: 44px;
    }

    /* ── Form Elements ── */
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label {
      font-size: .73rem; font-weight: 600; letter-spacing: .08em;
      text-transform: uppercase; color: var(--text-sub);
    }
    .input-wrap { position: relative; display: flex; align-items: center; }
    .input-icon {
      position: absolute; left: 13px;
      width: 15px; height: 15px;
      stroke: var(--text-sub); fill: none; stroke-width: 1.8;
      pointer-events: none; transition: stroke var(--transition);
      flex-shrink: 0;
    }
    .input-icon-top {
      position: absolute; left: 13px; top: 12px;
      width: 15px; height: 15px;
      stroke: var(--text-sub); fill: none; stroke-width: 1.8;
      pointer-events: none; transition: stroke var(--transition);
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      background: var(--bg2);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 10px 14px 10px 38px;
      font-family: 'DM Sans', sans-serif;
      font-size: .92rem;
      color: var(--text);
      outline: none;
      transition: border-color .2s, background var(--transition), color var(--transition), box-shadow .2s;
      appearance: none;
    }
    .form-group textarea {
      resize: none;
      height: 78px;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      border-color: var(--accent);
      background: var(--surface);
      box-shadow: 0 0 0 3px rgba(76,175,80,.12);
    }
    .input-wrap:focus-within .input-icon,
    .input-wrap:focus-within .input-icon-top { stroke: var(--accent); }
    .select-wrap::after {
      content: '';
      position: absolute; right: 13px; top: 50%;
      transform: translateY(-50%);
      width: 0; height: 0;
      border-left: 5px solid transparent;
      border-right: 5px solid transparent;
      border-top: 5px solid var(--text-sub);
      pointer-events: none;
    }

    /* ── Service label ── */
    .service-label {
      font-size: .73rem; font-weight: 600; letter-spacing: .08em;
      text-transform: uppercase; color: var(--text-sub);
    }

    /* ── Service scroll container ── */
    .service-scroll {
      overflow-y: auto;
      overflow-x: hidden;
      max-height: 340px;
      padding-right: 4px;
      scrollbar-width: thin;
      scrollbar-color: var(--border) transparent;
    }
    .service-scroll::-webkit-scrollbar { width: 4px; }
    .service-scroll::-webkit-scrollbar-track { background: transparent; }
    .service-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }
    .service-scroll::-webkit-scrollbar-thumb:hover { background: var(--accent); }

    /* ── Service picker: 3 columns ── */
    .service-picker {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
       align-items: stretch;
    }
    .service-option { position: relative; cursor: pointer; }
    .service-option input[type="radio"] {
      position: absolute; opacity: 0; width: 0; height: 0;
    }
    .service-card {
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 12px 13px;
      background: var(--bg2);
      transition: border-color .2s, background .2s, box-shadow .2s, transform .15s;
      cursor: pointer;
      min-height: 86px;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .service-option input[type="radio"]:checked + .service-card {
      border-color: var(--accent);
      background: var(--accent-lt);
      box-shadow: 0 0 0 3px rgba(76,175,80,.15);
    }
    .service-card:hover {
      border-color: var(--accent-dk);
      transform: translateY(-2px);
      box-shadow: 0 4px 14px rgba(76,175,80,.18);
    }
    .service-name {
      font-weight: 600; font-size: .81rem;
      color: var(--text); line-height: 1.3;
    }
    .service-meta { font-size: .70rem; color: var(--text-sub); margin-top: 3px; }
    .service-price {
      margin-top: 7px; font-weight: 700;
      font-size: .81rem; color: var(--accent);
    }

    /* ── Info Panel ── */
    .info-panel {
      background: var(--accent-lt);
      border: 1.5px solid var(--accent);
      border-radius: 10px;
      padding: 12px 16px;
      display: none;
      flex-direction: column;
      gap: 9px;
      animation: fadeUp .35s both;
    }
    .info-panel.visible { display: flex; }
    .info-row { display: flex; align-items: center; gap: 9px; }
    .info-row svg {
      width: 14px; height: 14px; flex-shrink: 0;
      stroke: var(--accent); fill: none; stroke-width: 1.8;
    }
    .info-row span { font-size: .84rem; color: var(--text-sub); }
    .info-row strong { color: var(--text); font-weight: 600; }

    /* ── Submit ── */
    .btn-primary {
      display: inline-flex; align-items: center; justify-content: center;
      gap: 8px; padding: 12px 28px;
      background: var(--accent); color: #fff;
      border-radius: var(--radius);
      font-family: 'DM Sans', sans-serif;
      font-size: .9rem; font-weight: 600;
      letter-spacing: .05em; text-transform: uppercase;
      border: none; cursor: pointer; width: 100%;
      transition: background var(--transition), transform .2s, box-shadow .2s;
      box-shadow: 0 6px 20px rgba(76,175,80,.35);
    }
    .btn-primary:hover {
      background: var(--accent-dk);
      transform: translateY(-2px);
      box-shadow: 0 10px 28px rgba(76,175,80,.45);
    }
    .btn-primary:disabled {
      opacity: .5; cursor: not-allowed; transform: none; box-shadow: none;
    }
    .spinner {
      width: 15px; height: 15px;
      border: 2px solid rgba(255,255,255,.4);
      border-top-color: #fff; border-radius: 50%;
      animation: spin .6s linear infinite; display: none;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Popup ── */
    .popup-overlay {
      position: fixed; inset: 0; z-index: 9999;
      background: rgba(0,0,0,.6);
      display: flex; align-items: center; justify-content: center; padding: 20px;
      opacity: 0; pointer-events: none; transition: opacity .35s;
    }
    .popup-overlay.visible { opacity: 1; pointer-events: all; }
    .popup-card {
      position: relative; background: var(--surface);
      border: 1px solid var(--border); border-radius: 24px;
      padding: 52px 44px 44px; max-width: 460px; width: 100%;
      text-align: center; box-shadow: 0 32px 96px rgba(0,0,0,.35);
      transform: scale(.88) translateY(20px);
      transition: transform .4s cubic-bezier(.34,1.56,.64,1);
    }
    .popup-overlay.visible .popup-card { transform: scale(1) translateY(0); }
    .popup-close {
      position: absolute; top: 16px; right: 16px;
      width: 32px; height: 32px;
      display: flex; align-items: center; justify-content: center;
      border: none; background: var(--bg2); border-radius: 8px;
      cursor: pointer; transition: background .2s;
    }
    .popup-close:hover { background: var(--border); }
    .popup-close svg { width: 16px; height: 16px; stroke: var(--text-sub); fill: none; stroke-width: 2; }
    .popup-icon {
      width: 72px; height: 72px; background: var(--accent-lt);
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      margin: 0 auto 24px;
    }
    .popup-icon svg { width: 36px; height: 36px; stroke: var(--accent); fill: none; stroke-width: 2; }
    .popup-label {
      font-size: .78rem; font-weight: 700; letter-spacing: .14em;
      text-transform: uppercase; color: var(--accent); margin-bottom: 10px;
    }
    .popup-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.65rem; font-weight: 700;
      color: var(--text); line-height: 1.2; margin-bottom: 14px;
    }
    .popup-body { font-size: .9rem; color: var(--text-sub); line-height: 1.7; margin-bottom: 28px; }
    .btn-secondary {
      display: inline-flex; align-items: center; justify-content: center;
      padding: 13px 32px; background: var(--accent); color: #fff;
      border-radius: var(--radius); font-family: 'DM Sans', sans-serif;
      font-size: .9rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
      border: none; cursor: pointer; width: 100%;
      transition: background .2s, transform .2s;
      box-shadow: 0 6px 20px rgba(76,175,80,.3);
    }
    .btn-secondary:hover { background: var(--accent-dk); transform: translateY(-1px); }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ════════════════════════════════
       RESPONSIVE
    ════════════════════════════════ */

    @media (max-width: 1080px) {
      .nav-links { gap: 22px; margin-left: 28px; }
    }

    @media (max-width: 960px) {
      .reserve-inner { grid-template-columns: 1fr; }
      .reserve-inner::after { display: none; }
      .reserve-left { padding-right: 0; }
      .reserve-right { padding-left: 0; padding-top: 22px; border-top: 1px solid var(--border); }
      .service-scroll { max-height: 240px; }
    }

    @media (max-width: 768px) {
      :root { --nav-h: 64px; }
      body { overflow: auto; }
      .page-wrapper { height: auto; overflow: visible; padding: 16px 4% 60px; }
      .reserve-card { padding: 22px 16px; border-radius: 18px; }

      /* Sembunyikan nav links desktop, tampilkan hamburger */
      .nav-links { display: none; }
      .hamburger { display: flex; }
      .mobile-menu { display: block; }

      /* Layout mobile: grid 2 kolom untuk left panel */
      .reserve-inner { grid-template-columns: 1fr; }
      .reserve-left {
        padding-right: 0;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
      }

      /* Your Pet → kolom 1 */
      .reserve-left .form-group:nth-child(1) { grid-column: 1 / -1; }
      /* Date & Time → full width */
      .reserve-left .form-group:nth-child(2) { grid-column: 1 / -1; }
      .reserve-left .form-group:nth-child(3) { grid-column: 1 / -1; }

      /* Info panels, hidden inputs, button: full width */
      .reserve-left .info-panel { grid-column: 1 / -1; }
      .reserve-left input[type="hidden"] { grid-column: 1 / -1; }
      .reserve-left .btn-primary { grid-column: 1 / -1; }

      /* Textarea lebih pendek di mobile */
      .form-group textarea { height: 62px; }

      /* Service: tetap 3 kolom di mobile */
      .reserve-right { padding-left: 0; padding-top: 18px; border-top: 1px solid var(--border); }
      .service-scroll { max-height: none; overflow-y: visible; overflow-x: hidden; }
      .service-picker { grid-template-columns: repeat(3, 1fr); gap: 8px; }
      .service-card { min-height: 76px; padding: 10px 9px; }
      .service-name { font-size: .76rem; }
      .service-meta { font-size: .65rem; }
      .service-price { font-size: .76rem; margin-top: 4px; }
    }

    @media (max-width: 420px) {
      .reserve-card { padding: 18px 12px; }
      .service-picker { gap: 6px; }
      .service-card { padding: 8px 7px; min-height: 68px; }
      .service-name { font-size: .71rem; }
      .service-meta { font-size: .60rem; }
      .service-price { font-size: .71rem; }
      .toggle-label { display: none; }
    }
  </style>
</head>
<body>

<!-- ══════════ NAVBAR ══════════ -->
<nav class="navbar">
  <a href="index.php" class="nav-logo">
    <img src="assets/logolm.png" alt="CatDogKu" class="logo-light" />
    <img src="assets/logodm.png" alt="CatDogKu" class="logo-dark" />
  </a>

  <!-- Desktop Links -->
  <ul class="nav-links">
    <li><a href="index.php#home">Home</a></li>
    <li><a href="index.php#about">About</a></li>
    <li><a href="index.php#service">Service</a></li>
    <li><a href="reserve.php" class="active">Reserve</a></li>
    <li><a href="cust_profile.php">Profile</a></li>
    <li><a href="index.php#contact">Contact</a></li>
  </ul>

  <div class="nav-spacer"></div>

  <!-- Theme Toggle -->
  <div class="theme-toggle" id="themeToggle" role="button" tabindex="0" aria-label="Toggle dark mode">
    <span class="toggle-label" id="toggleLabel">OFF</span>
    <div class="toggle-track" id="toggleTrack"><div class="toggle-knob"></div></div>
  </div>

  <!-- Hamburger (mobile only) -->
  <button class="hamburger" id="hamburger" aria-label="Toggle navigation menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- ══════════ MOBILE MENU ══════════ -->
<div class="mobile-menu" id="mobileMenu" aria-hidden="true">
  <ul>
    <li><a href="index.php#home" onclick="closeMenu()">Home</a></li>
    <li><a href="index.php#about" onclick="closeMenu()">About</a></li>
    <li><a href="index.php#service" onclick="closeMenu()">Service</a></li>
    <li><a href="reserve.php" class="active" onclick="closeMenu()">Reserve</a></li>
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

<!-- ══════════ BACKGROUND ══════════ -->
<div class="bg-image"></div>
<div class="bg-overlay"></div>

<!-- ══════════ MAIN ══════════ -->
<div class="page-wrapper">
  <div class="reserve-card">

    <div class="card-header">
      <p class="card-label">Reserve</p>
      <p class="card-subtitle">Choose a service, specify your pet, and let us schedule you with the best staff.</p>
    </div>

    <form id="reserveForm">
      <div class="reserve-inner">

        <!-- ── LEFT: Pet · Notes · DateTime · Info · Submit ── -->
        <div class="reserve-left">

          <!-- Pet (grid col 1 on mobile) -->
          <div class="form-group">
            <label for="id_hewan">Your Pet</label>
            <div class="input-wrap select-wrap">
              <svg class="input-icon" viewBox="0 0 24 24">
                <path d="M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.113 6.006-4 7 .08.703 1.725 1.722 3.656 1 1.261-.472 1.96-1.45 2.344-2.5"/>
                <path d="M14.267 5.172c0-1.39 1.577-2.493 3.5-2.172 2.823.47 4.113 6.006 4 7-.08.703-1.725 1.722-3.656 1-1.261-.472-1.855-1.45-2.239-2.5"/>
                <path d="M8 14v.5"/><path d="M16 14v.5"/>
                <path d="M11.25 16.25h1.5L12 17l-.75-.75z"/>
                <path d="M4.42 11.247A13.152 13.152 0 0 0 4 14.556C4 18.728 7.582 21 12 21s8-2.272 8-6.444c0-1.061-.162-2.2-.493-3.309m-9.243-6.082A8.801 8.801 0 0 1 12 5c.78 0 1.5.108 2.161.306"/>
              </svg>
              <select id="id_hewan" name="id_hewan" required>
                <option value="">— Select your pet —</option>
                <?php foreach ($pets as $pet): ?>
                  <option value="<?= $pet['id_hewan'] ?>">
                    <?= htmlspecialchars($pet['nama_hewan']) ?>
                    (<?= htmlspecialchars($pet['nama_jenis']) ?> – <?= htmlspecialchars($pet['nama_ras']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Date & Time (full width on mobile) -->
          <div class="form-group">
            <label for="waktu_mulai">Date &amp; Start Time</label>
            <div class="input-wrap">
              <svg class="input-icon" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
              </svg>
              <input type="datetime-local" id="waktu_mulai" name="waktu_mulai" required />
            </div>
          </div>

          <!-- Additional Notes (grid col 2 on mobile) -->
          <div class="form-group">
            <label for="catatan_reservasi">Additional Notes</label>
            <div class="input-wrap">
              <svg class="input-icon-top" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
              </svg>
              <textarea id="catatan_reservasi" name="catatan_reservasi"
                placeholder="Any special requests or health notes..."></textarea>
            </div>
          </div>

          <!-- Staff info panel -->
          <div class="info-panel" id="infoPanel">
            <div class="info-row">
              <svg viewBox="0 0 24 24">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
              <span>Assigned Staff: <strong id="staffName">—</strong></span>
            </div>
            <div class="info-row">
              <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
              </svg>
              <span>Estimated End Time: <strong id="waktuSelesaiDisplay">—</strong></span>
            </div>
          </div>

          <!-- No staff warning -->
          <div class="info-panel" id="noStaffPanel">
            <div class="info-row">
              <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              <span style="color:var(--text);">No staff available at the selected time. Please choose another schedule.</span>
            </div>
          </div>

          <input type="hidden" name="id_staf" id="id_staf" />
          <input type="hidden" name="waktu_selesai" id="waktu_selesai" />

          <!-- Submit -->
          <button type="submit" class="btn-primary" id="reserveBtn" disabled>
            <span id="btnText">Confirm Reservation</span>
            <div class="spinner" id="btnSpinner"></div>
          </button>

        </div><!-- /reserve-left -->

        <!-- ── RIGHT: Service picker ── -->
        <div class="reserve-right">
          <div style="display:flex; align-items:center; justify-content:space-between;">
            <p class="service-label">Service</p>
            <a href="detail_reserve.php" class="card-label" style="margin-bottom:0; cursor:pointer;">All Reserve</a>
          </div>
          <div class="service-scroll">
            <div class="service-picker">
              <?php foreach ($services as $svc): ?>
                <label class="service-option">
                  <input type="radio" name="id_service" value="<?= $svc['id_layanan'] ?>"
                         data-durasi="<?= $svc['durasi_estimasi'] ?>" required />
                  <div class="service-card">
                    <div class="service-name"><?= htmlspecialchars($svc['nama_service']) ?></div>
                    <div class="service-meta">⏱ <?= $svc['durasi_estimasi'] ?> min</div>
                    <div class="service-price">Rp <?= number_format($svc['harga_base'], 0, ',', '.') ?></div>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div><!-- /reserve-right -->

      </div><!-- /reserve-inner -->
    </form>
  </div>
</div>

<!-- ══════════ POPUP ══════════ -->
<div class="popup-overlay" id="popupOverlay">
  <div class="popup-card">
    <button class="popup-close" id="popupClose" aria-label="Close">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div class="popup-icon">
      <svg viewBox="0 0 24 24">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
        <polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
    </div>
    <p class="popup-label">Reservation Confirmed</p>
    <h2 class="popup-title">Thank you for trusting CatDogKu!</h2>
    <p class="popup-body">Your reservation has been placed successfully. Our team will take great care of your furry friend. You'll receive a confirmation shortly.</p>
    <button class="btn-secondary" onclick="window.location='detail_reserve.php'">View My Reservations</button>
  </div>
</div>

<script>
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
    hamburger.setAttribute('aria-expanded', String(menuOpen));
    mobileMenu.classList.toggle('open', menuOpen);
    mobileMenu.setAttribute('aria-hidden', String(!menuOpen));
  });

  document.addEventListener('click', e => {
    if (menuOpen && !hamburger.contains(e.target) && !mobileMenu.contains(e.target)) closeMenu();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });

  /* ── Theme ── */
  const html            = document.documentElement;
  const toggleDesktop   = document.getElementById('themeToggle');
  const track           = document.getElementById('toggleTrack');
  const label           = document.getElementById('toggleLabel');
  const toggleMobile    = document.getElementById('themeToggleMobile');
  const trackMobile     = document.getElementById('toggleTrackMobile');

  function applyTheme(dark) {
    html.setAttribute('data-theme', dark ? 'dark' : 'light');
    track.classList.toggle('on', dark);
    if (trackMobile) trackMobile.classList.toggle('on', dark);
    if (label) label.textContent = dark ? 'ON' : 'OFF';
    localStorage.setItem('theme', dark ? 'dark' : 'light');
  }
  function toggleTheme() { applyTheme(html.getAttribute('data-theme') !== 'dark'); }

  const saved = localStorage.getItem('theme');
  applyTheme(saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches);

  toggleDesktop.addEventListener('click', toggleTheme);
  toggleDesktop.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleTheme(); } });
  if (toggleMobile) {
    toggleMobile.addEventListener('click', toggleTheme);
    toggleMobile.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleTheme(); } });
  }

  /* ── Staff lookup ── */
  const serviceInputs     = document.querySelectorAll('input[name="id_service"]');
  const waktuMulaiInput   = document.getElementById('waktu_mulai');
  const infoPanel         = document.getElementById('infoPanel');
  const noStaffPanel      = document.getElementById('noStaffPanel');
  const staffNameEl       = document.getElementById('staffName');
  const waktuSelesaiEl    = document.getElementById('waktuSelesaiDisplay');
  const id_stafInput      = document.getElementById('id_staf');
  const waktuSelesaiInput = document.getElementById('waktu_selesai');
  const reserveBtn        = document.getElementById('reserveBtn');
  let fetchTimeout = null;

  function getSelectedService() {
    for (const inp of serviceInputs) { if (inp.checked) return inp.value; }
    return null;
  }

  function triggerLookup() {
    const service_id  = getSelectedService();
    const waktu_mulai = waktuMulaiInput.value;
    if (!service_id || !waktu_mulai) return;
    clearTimeout(fetchTimeout);
    fetchTimeout = setTimeout(() => {
      fetch(`reserve.php?action=get_staff&service_id=${service_id}&waktu_mulai=${encodeURIComponent(waktu_mulai)}`)
        .then(r => r.json())
        .then(data => {
          infoPanel.classList.remove('visible');
          noStaffPanel.classList.remove('visible');
          if (data.staff && data.staff.id_staf) {
            id_stafInput.value      = data.staff.id_staf;
            waktuSelesaiInput.value = data.waktu_selesai;
            staffNameEl.textContent = data.staff.nama_staf;
            const d = new Date(data.waktu_selesai.replace(' ', 'T'));
            waktuSelesaiEl.textContent = d.toLocaleString('en-US', {
              weekday: 'short', year: 'numeric', month: 'short',
              day: 'numeric', hour: '2-digit', minute: '2-digit'
            });
            infoPanel.classList.add('visible');
            reserveBtn.disabled = false;
          } else {
            id_stafInput.value = '';
            waktuSelesaiInput.value = '';
            noStaffPanel.classList.add('visible');
            reserveBtn.disabled = true;
          }
        })
        .catch(() => { reserveBtn.disabled = true; });
    }, 400);
  }

  serviceInputs.forEach(inp => inp.addEventListener('change', triggerLookup));
  waktuMulaiInput.addEventListener('change', triggerLookup);

  /* ── Submit ── */
  const form         = document.getElementById('reserveForm');
  const btnText      = document.getElementById('btnText');
  const btnSpinner   = document.getElementById('btnSpinner');
  const popupOverlay = document.getElementById('popupOverlay');
  const popupClose   = document.getElementById('popupClose');

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    btnText.textContent = 'Reserving...';
    btnSpinner.style.display = 'block';
    reserveBtn.disabled = true;

    const formData = new FormData(form);
    formData.append('reserve', '1');

    fetch('reserve.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          popupOverlay.classList.add('visible');
        } else {
          alert('Error: ' + (data.error || 'Something went wrong.'));
          btnText.textContent = 'Confirm Reservation';
          btnSpinner.style.display = 'none';
          reserveBtn.disabled = false;
        }
      })
      .catch(() => {
        alert('Network error. Please try again.');
        btnText.textContent = 'Confirm Reservation';
        btnSpinner.style.display = 'none';
        reserveBtn.disabled = false;
      });
  });

  popupClose.addEventListener('click', () => popupOverlay.classList.remove('visible'));
  popupOverlay.addEventListener('click', e => { if (e.target === popupOverlay) popupOverlay.classList.remove('visible'); });
</script>
</body>
</html>
