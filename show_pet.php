<?php
include "config/koneksi.php";
session_start();

if(!isset($_SESSION['customer_id'])){
    header("Location: cust_login.php");
    exit;
}

$id = $_SESSION['customer_id'];

// ── Fetch customer info ──
$stmt = $conn->prepare("SELECT nama_depan, nama_belakang, email, no_telepon FROM customer WHERE id_pelanggan = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($nama_depan, $nama_belakang, $email, $no_telepon);
$stmt->fetch();
$stmt->close();

$full_name = htmlspecialchars($nama_depan . ' ' . $nama_belakang);
$initials  = strtoupper(substr($nama_depan,0,1) . substr($nama_belakang,0,1));

// ── Handle Add Pet ──
$success_msg = '';
$error_msg   = '';

if(isset($_POST['add_pet'])){
    $nama_hewan    = $conn->real_escape_string($_POST['nama_hewan']);
    $id_jenis      = (int)$_POST['id_jenis'];
    $id_ras        = (int)$_POST['id_ras'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $berat_kg      = (float)$_POST['berat_kg'];
    $catatan_medis = $conn->real_escape_string($_POST['catatan_medis']);

    $q = "INSERT INTO hewan_peliharaan (id_pelanggan, nama_hewan, id_jenis, id_ras, tanggal_lahir, berat_kg, catatan_medis)
          VALUES ('$id','$nama_hewan','$id_jenis','$id_ras','$tanggal_lahir','$berat_kg','$catatan_medis')";

    if($conn->query($q)){
        $success_msg = "Pet berhasil ditambahkan!";
    } else {
        $error_msg = "Gagal menambahkan pet. Coba lagi.";
    }
}

// ── Fetch pets ──
$pets_query = "
    SELECT h.id_hewan, h.nama_hewan, j.nama_jenis, r.nama_ras, h.tanggal_lahir, h.berat_kg, h.catatan_medis
    FROM hewan_peliharaan h
    LEFT JOIN jenis_hewan j ON h.id_jenis = j.id_jenis
    LEFT JOIN ras_hewan   r ON h.id_ras   = r.id_ras
    WHERE h.id_pelanggan = '$id'
    ORDER BY 
        CASE WHEN r.nama_ras = 'Other' THEN 1 ELSE 0 END,
        h.created_at ASC
";
$pets_result = $conn->query($pets_query);
$pets = [];
if($pets_result){
    while($row = $pets_result->fetch_assoc()) $pets[] = $row;
}

// ── Fetch jenis for dropdown ──
$jenis_list = [];
$jr = $conn->query("SELECT id_jenis, nama_jenis FROM jenis_hewan ORDER BY nama_jenis");
if($jr) while($row = $jr->fetch_assoc()) $jenis_list[] = $row;

// ── Fetch ALL ras with jenis mapping (for JS filtering) ──
$ras_all = [];
$rr = $conn->query("SELECT id_ras, id_jenis, nama_ras FROM ras_hewan ORDER BY CASE WHEN nama_ras = 'Other' THEN 1 ELSE 0 END, nama_ras ASC");
if($rr) while($row = $rr->fetch_assoc()) $ras_all[] = $row;

$conn->close();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Pets — CatDogKu</title>
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
      color: var(--text);
      transition: color var(--transition);
      min-height: 100vh;
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
      display: flex; align-items: center;
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
      display: flex; align-items: center; gap: 36px;
      list-style: none; margin-left: 52px;
    }
    .nav-links a {
      font-size: .92rem; font-weight: 500;
      letter-spacing: .04em; text-transform: uppercase;
      color: var(--text-sub); position: relative;
      transition: color var(--transition);
    }
    .nav-links a::after {
      content: ''; position: absolute;
      left: 0; bottom: -4px;
      width: 0; height: 2px;
      background: var(--accent); border-radius: 2px;
      transition: width var(--transition);
    }
    .nav-links a:hover, .nav-links a.active { color: var(--accent); }
    .nav-links a:hover::after, .nav-links a.active::after { width: 100%; }
    .nav-spacer { flex: 1; }
    .theme-toggle { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
    .toggle-label {
      font-size: .78rem; font-weight: 600;
      letter-spacing: .08em; text-transform: uppercase;
      color: var(--text-sub); width: 28px; text-align: right;
    }
    .toggle-track {
      position: relative; width: 48px; height: 26px;
      background: var(--border); border-radius: 999px;
      border: 2px solid var(--border); cursor: pointer;
      transition: background var(--transition), border-color var(--transition);
    }
    .toggle-track.on { background: var(--accent); border-color: var(--accent); }
    .toggle-knob {
      position: absolute; top: 2px; left: 2px;
      width: 18px; height: 18px; border-radius: 50%;
      background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.2);
      transition: transform var(--transition);
    }
    .toggle-track.on .toggle-knob { transform: translateX(22px); }

    /* ── Page ── */
.page-hero {
  margin-top: var(--nav-h);
  position: relative;
  min-height: calc(100vh - var(--nav-h));
  display: flex;
  flex-direction: column;
}
    .hero-bg {
      position: fixed;
      top: var(--nav-h); left: 0; right: 0; bottom: 0;
      background: url('assets/heroo.jpg') center 30% / cover no-repeat;
      filter: brightness(.52);
      transition: filter var(--transition);
      z-index: 0;
    }
    [data-theme="dark"] .hero-bg { filter: brightness(.32); }
    .hero-overlay {
      position: fixed;
      top: var(--nav-h); left: 0; right: 0; bottom: 0;
      background: linear-gradient(100deg, rgba(0,0,0,.58) 38%, rgba(0,0,0,.22) 100%);
      z-index: 1;
    }

.page-content {
  position: relative; z-index: 2;
  padding: 40px 6%;
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;   /* ← vertical center */
}

    /* ── Page header ── */
    .page-header {
      margin-bottom: 20px;
      animation: fadeUp .6s .1s both;
    }
    .page-tag {
      font-size: .78rem; font-weight: 700;
      letter-spacing: .14em; text-transform: uppercase;
      color: var(--accent); margin-bottom: 8px;
    }
    .page-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.8rem, 3.5vw, 2.6rem);
      font-weight: 700; color: #fff; line-height: 1.15;
    }

    /* ── Two-column layout ── */
.main-layout {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 24px;
  align-items: center;   /* ← center card kiri & kanan secara vertikal */
}

    /* ── Glass card mixin ── */
    .glass-card {
      background: rgba(255,255,255,.92);
      border: 1px solid rgba(255,255,255,.6);
      border-radius: 20px;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      transition: background var(--transition);
    }
    [data-theme="dark"] .glass-card {
      background: rgba(30,39,30,.92);
      border-color: rgba(76,175,80,.18);
    }

    /* ── LEFT: User info card ── */
.user-card {
  padding: 30px 24px 24px;
  animation: fadeUp .6s .2s both;
  /* tidak perlu overflow atau max-height */
}
    .avatar {
      width: 72px; height: 72px; border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent-dk));
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 1.7rem; font-weight: 700; color: #fff;
      margin: 0 auto 14px;
      box-shadow: 0 8px 24px rgba(76,175,80,.38);
    }
    .user-name {
      font-family: 'Playfair Display', serif;
      font-size: 1.1rem; font-weight: 700;
      color: var(--text); text-align: center; margin-bottom: 4px;
    }
    .user-badge {
      display: inline-flex; align-items: center; gap: 5px;
      background: var(--accent-lt); border-radius: 999px;
      padding: 3px 12px; margin: 0 auto 20px;
      font-size: .73rem; font-weight: 700;
      letter-spacing: .08em; text-transform: uppercase;
      color: var(--accent-dk);
    }
    .user-badge-wrap { text-align: center; margin-bottom: 20px; }

    .user-divider {
      height: 1px; background: var(--border); margin-bottom: 18px;
      transition: background var(--transition);
    }

    .user-info-list { display: flex; flex-direction: column; gap: 13px; }
    .user-info-item { display: flex; flex-direction: column; gap: 4px; }
    .uil-label {
      font-size: .7rem; font-weight: 700;
      letter-spacing: .12em; text-transform: uppercase;
      color: var(--text-sub);
    }
    .uil-value {
      font-size: .9rem; font-weight: 500;
      color: var(--text); padding: 9px 13px;
      background: var(--bg2); border: 1px solid var(--border);
      border-radius: 10px;
      transition: background var(--transition), border-color var(--transition);
    }

    .user-actions { display: flex; flex-direction: column; gap: 9px; margin-top: 20px; }
    .btn-sm {
      display: flex; align-items: center; gap: 8px;
      padding: 10px 14px; border-radius: var(--radius);
      font-size: .83rem; font-weight: 600; letter-spacing: .03em;
      cursor: pointer; border: 1.5px solid transparent;
      transition: background .22s, border-color .22s, color .22s, transform .2s;
      text-align: left;
    }
    .btn-sm svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 1.8; flex-shrink: 0; }
    .btn-outline-sm {
      background: transparent; border-color: var(--border); color: var(--text-sub);
    }
    .btn-outline-sm:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-lt); transform: translateY(-1px); }
    .btn-danger-sm {
      background: transparent; border-color: var(--border); color: #e57373;
    }
    .btn-danger-sm:hover { background: #ffebee; border-color: #e57373; transform: translateY(-1px); }
    [data-theme="dark"] .btn-danger-sm:hover { background: #2d1b1b; }

    /* ── RIGHT: Pets card ── */
    .pets-card {
      padding: 0;
      animation: fadeUp .6s .3s both;
      overflow: hidden;
    }

    .pets-card-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 24px 28px 20px;
      border-bottom: 1px solid var(--border);
      transition: border-color var(--transition);
    }
    .pets-header-left {}
    .pets-tag {
      font-size: .72rem; font-weight: 700;
      letter-spacing: .14em; text-transform: uppercase;
      color: var(--accent); margin-bottom: 3px;
    }
    .pets-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.25rem; font-weight: 700; color: var(--text);
    }

    .btn-add-pet {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 9px 18px;
      background: var(--accent); color: #fff;
      border-radius: var(--radius);
      font-family: 'DM Sans', sans-serif;
      font-size: .83rem; font-weight: 600;
      letter-spacing: .04em; text-transform: uppercase;
      border: none; cursor: pointer;
      box-shadow: 0 4px 16px rgba(76,175,80,.32);
      transition: background var(--transition), transform .2s, box-shadow .2s;
      flex-shrink: 0;
    }
    .btn-add-pet svg { width: 15px; height: 15px; stroke: #fff; fill: none; stroke-width: 2.2; }
    .btn-add-pet:hover { background: var(--accent-dk); transform: translateY(-2px); box-shadow: 0 8px 22px rgba(76,175,80,.42); }

    /* ── Pets list ── */
.pets-body {
  padding: 20px 28px 24px;
  height: 300px;
  max-height: calc(100vh - var(--nav-h) - 280px);  /* ← dynamic, tidak hardcode */
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: var(--border) transparent;
}
    .pets-body::-webkit-scrollbar { width: 5px; }
    .pets-body::-webkit-scrollbar-track { background: transparent; }
    .pets-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

    /* ── Empty state ── */
    .pet-empty {
      text-align: center;
      padding: 50px 20px;
    }
    .pet-empty-icon {
      font-size: 3.2rem;
      margin-bottom: 14px;
      opacity: .55;
    }
    .pet-empty-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.1rem; font-weight: 700;
      color: var(--text); margin-bottom: 6px;
    }
    .pet-empty-sub { font-size: .87rem; color: var(--text-sub); }

    /* ── Pet row item ── */
    .pet-item {
      display: grid;
      grid-template-columns: 44px 1fr auto;
      gap: 14px;
      align-items: center;
      padding: 14px 16px;
      border-radius: 14px;
      border: 1px solid var(--border);
      background: var(--bg2);
      margin-bottom: 12px;
      transition: background var(--transition), border-color var(--transition), transform .2s, box-shadow .2s;
      animation: fadeUp .4s both;
    }
    .pet-item:last-child { margin-bottom: 0; }
    .pet-item:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.08); border-color: var(--accent); }

    .pet-icon {
      width: 44px; height: 44px; border-radius: 12px;
      background: linear-gradient(135deg, var(--accent-lt), rgba(76,175,80,.15));
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem;
      border: 1px solid rgba(76,175,80,.2);
      flex-shrink: 0;
    }
    .pet-info {}
    .pet-name {
      font-family: 'Playfair Display', serif;
      font-size: 1rem; font-weight: 700;
      color: var(--text); margin-bottom: 4px;
    }
    .pet-meta {
      display: flex; flex-wrap: wrap; gap: 6px;
    }
    .pet-tag {
      font-size: .72rem; font-weight: 600;
      padding: 2px 10px; border-radius: 999px;
      background: var(--surface); border: 1px solid var(--border);
      color: var(--text-sub);
      transition: background var(--transition), border-color var(--transition);
    }
    .pet-tag.accent { background: var(--accent-lt); border-color: rgba(76,175,80,.25); color: var(--accent-dk); }

    .pet-age {
      font-size: .78rem; font-weight: 600;
      color: var(--text-sub); text-align: right; white-space: nowrap;
    }

    /* ── Flash messages ── */
    .flash {
      display: flex; align-items: center; gap: 9px;
      padding: 11px 16px; border-radius: 10px;
      font-size: .87rem; font-weight: 500;
      margin-bottom: 16px; animation: fadeUp .35s both;
    }
    .flash svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; }
    .flash-success { background: rgba(76,175,80,.12); border: 1px solid rgba(76,175,80,.3); color: var(--accent-dk); }
    .flash-error   { background: rgba(229,57,53,.1);  border: 1px solid rgba(229,57,53,.25); color: #c62828; }
    [data-theme="dark"] .flash-success { color: var(--accent); }
    [data-theme="dark"] .flash-error   { color: #ef5350; }

    /* ══════════════════════════════════════
       MODAL / POPUP
    ══════════════════════════════════════ */
    .modal-backdrop {
      position: fixed; inset: 0; z-index: 3000;
      background: rgba(0,0,0,.55);
      backdrop-filter: blur(6px);
      display: flex; align-items: center; justify-content: center;
      padding: 20px;
      opacity: 0; pointer-events: none;
      transition: opacity .3s;
    }
    .modal-backdrop.open { opacity: 1; pointer-events: all; }

    .modal {
      width: 100%; max-width: 520px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 24px;
      overflow: hidden;
      box-shadow: 0 32px 80px rgba(0,0,0,.35);
      transform: translateY(28px) scale(.97);
      transition: transform .35s cubic-bezier(.4,0,.2,1);
    }
    .modal-backdrop.open .modal { transform: translateY(0) scale(1); }

    .modal-header {
      display: flex; align-items: flex-start; justify-content: space-between;
      padding: 26px 28px 20px;
      border-bottom: 1px solid var(--border);
      transition: border-color var(--transition);
    }
    .modal-tag {
      font-size: .72rem; font-weight: 700;
      letter-spacing: .14em; text-transform: uppercase;
      color: var(--accent); margin-bottom: 4px;
    }
    .modal-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.25rem; font-weight: 700; color: var(--text);
    }
    .modal-close {
      background: var(--bg2); border: 1px solid var(--border);
      border-radius: 50%; width: 34px; height: 34px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; flex-shrink: 0; margin-top: 2px;
      transition: background .2s, border-color .2s, transform .2s;
    }
    .modal-close svg { width: 15px; height: 15px; stroke: var(--text-sub); fill: none; stroke-width: 2; }
    .modal-close:hover { background: var(--border); transform: rotate(90deg); }

    .modal-body { padding: 22px 28px 28px; }

    .modal-form { display: flex; flex-direction: column; gap: 14px; }

    .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label {
      font-size: .72rem; font-weight: 700;
      letter-spacing: .1em; text-transform: uppercase;
      color: var(--text-sub);
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      background: var(--bg2); border: 1.5px solid var(--border);
      border-radius: 10px; padding: 10px 13px;
      font-family: 'DM Sans', sans-serif;
      font-size: .92rem; color: var(--text); outline: none;
      transition: border-color .2s, background var(--transition), box-shadow .2s;
      appearance: none;
    }
    .form-group select { cursor: pointer; }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      border-color: var(--accent);
      background: var(--surface);
      box-shadow: 0 0 0 3px rgba(76,175,80,.12);
    }
    .form-group textarea { resize: none; min-height: 72px; }

    .select-wrap { position: relative; }
    .select-wrap::after {
      content: '';
      position: absolute; right: 12px; top: 50%;
      transform: translateY(-50%);
      width: 0; height: 0;
      border-left: 5px solid transparent;
      border-right: 5px solid transparent;
      border-top: 6px solid var(--text-sub);
      pointer-events: none;
    }

    .btn-submit {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px;
      padding: 13px 28px; background: var(--accent); color: #fff;
      border-radius: var(--radius);
      font-family: 'DM Sans', sans-serif;
      font-size: .9rem; font-weight: 600;
      letter-spacing: .05em; text-transform: uppercase;
      border: none; cursor: pointer; width: 100%;
      box-shadow: 0 6px 20px rgba(76,175,80,.32);
      transition: background var(--transition), transform .2s, box-shadow .2s;
      margin-top: 4px;
    }
    .btn-submit:hover { background: var(--accent-dk); transform: translateY(-2px); box-shadow: 0 10px 28px rgba(76,175,80,.42); }

    /* ── Footer ── */
    footer {
      position: relative; z-index: 2;
      text-align: center; padding: 24px 6%;
      font-size: .82rem; color: rgba(255,255,255,.4);
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 900px) {
      .main-layout { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
      .nav-links { display: none; }
      .page-content { padding: 32px 4% 48px; }
      .pets-card-header { padding: 18px 18px 14px; }
      .pets-body { padding: 14px 14px 18px; }
      .modal-header, .modal-body { padding-left: 20px; padding-right: 20px; }
      .form-row-2 { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- ══════════════ NAVBAR ══════════════ -->
<nav class="navbar">
  <a href="index.php" class="nav-logo">
    <img src="assets/logolm.png" alt="CatDogKu" class="logo-light" />
    <img src="assets/logodm.png" alt="CatDogKu" class="logo-dark" />
  </a>
  <ul class="nav-links">
    <li><a href="index.php">Home</a></li>
    <li><a href="index.php#about">About</a></li>
    <li><a href="index.php#service">Service</a></li>
    <li><a href="reserve.php">Reserve</a></li>
    <li><a href="cust_profile.php">Profile</a></li>
    <li><a href="index.php#contact">Contact</a></li>
  </ul>
  <div class="nav-spacer"></div>
  <div class="theme-toggle" id="themeToggle" role="button" tabindex="0" aria-label="Toggle dark mode">
    <span class="toggle-label" id="toggleLabel">OFF</span>
    <div class="toggle-track" id="toggleTrack">
      <div class="toggle-knob"></div>
    </div>
  </div>
</nav>

<!-- ══════════════ BG ══════════════ -->
<div class="hero-bg"></div>
<div class="hero-overlay"></div>

<!-- ══════════════ MAIN ══════════════ -->
<div class="page-hero">
  <div class="page-content">

    <div class="page-header">
      <p class="page-tag">Pet Management</p>
    </div>

    <?php if($success_msg): ?>
    <div class="flash flash-success">
      <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <?= htmlspecialchars($success_msg) ?>
    </div>
    <?php endif; ?>

    <?php if($error_msg): ?>
    <div class="flash flash-error">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= htmlspecialchars($error_msg) ?>
    </div>
    <?php endif; ?>

    <div class="main-layout">

      <!-- ── LEFT: User Info ── -->
      <aside class="glass-card user-card">
        <div class="user-info-list">
          <div class="user-info-item">
            <p class="uil-label">Full Name</p>
            <div class="uil-value"><?= $full_name ?></div>
          </div>
          <div class="user-info-item">
            <p class="uil-label">Email</p>
            <div class="uil-value" style="word-break:break-all;"><?= htmlspecialchars($email) ?></div>
          </div>
          <div class="user-info-item">
            <p class="uil-label">Phone Number</p>
            <div class="uil-value"><?= $no_telepon ? htmlspecialchars($no_telepon) : '—' ?></div>
          </div>
          <div class="user-info-item">
            <p class="uil-label">Total Pets</p>
            <div class="uil-value" style="color:var(--accent);font-weight:700;"><?= count($pets) ?> pet<?= count($pets) != 1 ? 's' : '' ?></div>
          </div>
        </div>
      </aside>

      <!-- ── RIGHT: Pets Card ── -->
      <div class="glass-card pets-card">
        <div class="pets-card-header">
          <div class="pets-header-left">
            <p class="pets-tag">Your Pets</p>
          </div>
          <button class="btn-add-pet" id="openModal">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Pet
          </button>
        </div>

        <div class="pets-body">
          <?php if(empty($pets)): ?>
          <div class="pet-empty">
            <p class="pet-empty-title">No pets found</p>
          </div>
          <?php else: ?>
            <?php foreach($pets as $i => $pet):
              $emoji = 'dog' === strtolower($pet['nama_jenis'] ?? '') || 'anjing' === strtolower($pet['nama_jenis'] ?? '') ? '🐶' : (('cat' === strtolower($pet['nama_jenis'] ?? '') || 'kucing' === strtolower($pet['nama_jenis'] ?? '')) ? '🐱' : '🐾');
              $age_str = '';
              if($pet['tanggal_lahir']){
                  $born = new DateTime($pet['tanggal_lahir']);
                  $now  = new DateTime();
                  $diff = $now->diff($born);
                  if($diff->y > 0) $age_str = $diff->y . ' yr' . ($diff->y>1?'s':'') . ' old';
                  elseif($diff->m > 0) $age_str = $diff->m . ' mo old';
                  else $age_str = $diff->d . ' days old';
              }
            ?>
            <div class="pet-item" style="animation-delay: <?= $i * .06 ?>s;">
              <div class="pet-icon"><?= $emoji ?></div>
              <div class="pet-info">
                <p class="pet-name"><?= htmlspecialchars($pet['nama_hewan']) ?></p>
                <div class="pet-meta">
                  <span class="pet-tag accent"><?= htmlspecialchars($pet['nama_jenis'] ?? '—') ?></span>
                  <span class="pet-tag"><?= htmlspecialchars($pet['nama_ras'] ?? '—') ?></span>
                  <?php if($pet['tanggal_lahir']): ?>
                  <span class="pet-tag">🎂 <?= date("d M Y", strtotime($pet['tanggal_lahir'])) ?></span>
                  <?php endif; ?>
                  <?php if($pet['berat_kg']): ?>
                  <span class="pet-tag">⚖️ <?= number_format($pet['berat_kg'], 1) ?> kg</span>
                  <?php endif; ?>
                </div>
              </div>
              <?php if($age_str): ?>
              <div class="pet-age"><?= $age_str ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div><!-- /.pets-card -->

    </div><!-- /.main-layout -->
  </div><!-- /.page-content -->
</div>

<!-- ══════════════ MODAL ══════════════ -->
<div class="modal-backdrop" id="modalBackdrop">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">

    <div class="modal-header">
      <div>
        <p class="modal-tag">New Registration</p>
      </div>
      <button class="modal-close" id="closeModal" aria-label="Close">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="modal-body">
      <form class="modal-form" method="POST">

        <div class="form-row-2">
          <div class="form-group">
            <label for="nama_hewan">Pet Name</label>
            <input type="text" id="nama_hewan" name="nama_hewan" placeholder="Pet Name" required />
          </div>
          <div class="form-group">
            <label for="tanggal_lahir">Date of Birth</label>
            <input type="date" id="tanggal_lahir" name="tanggal_lahir" max="<?= date('Y-m-d') ?>" />
          </div>
        </div>

        <div class="form-row-2">
          <div class="form-group">
            <label for="id_jenis">type</label>
            <div class="select-wrap">
              <select id="id_jenis" name="id_jenis" required>
                <option value="">— Select Type —</option>
                <?php foreach($jenis_list as $j): ?>
                <option value="<?= $j['id_jenis'] ?>"><?= htmlspecialchars($j['nama_jenis']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="id_ras">Breeds</label>
            <div class="select-wrap">
              <select id="id_ras" name="id_ras" required>
                <option value="">— Select Breed —</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="berat_kg">Weight (kg)</label>
          <input type="number" id="berat_kg" name="berat_kg" placeholder="Weight" step="0.01" min="0" max="999" />
        </div>

        <div class="form-group">
          <label for="catatan_medis">medical records</label>
          <textarea id="catatan_medis" name="catatan_medis" placeholder="Allergies, vaccine history, special conditions..."></textarea>
        </div>

        <button type="submit" name="add_pet" class="btn-submit">
          Add Pet
        </button>
      </form>
    </div>

  </div>
</div>

<!-- ══════════════ SCRIPT ══════════════ -->
<script>
  // ── Theme ──
  const html   = document.documentElement;
  const toggle = document.getElementById('themeToggle');
  const track  = document.getElementById('toggleTrack');
  const label  = document.getElementById('toggleLabel');
  function applyTheme(dark) {
    html.setAttribute('data-theme', dark ? 'dark' : 'light');
    track.classList.toggle('on', dark);
    label.textContent = dark ? 'ON' : 'OFF';
    localStorage.setItem('theme', dark ? 'dark' : 'light');
  }
  const saved = localStorage.getItem('theme');
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  applyTheme(saved ? saved === 'dark' : prefersDark);
  toggle.addEventListener('click', () => applyTheme(html.getAttribute('data-theme') !== 'dark'));
  toggle.addEventListener('keydown', e => {
    if(e.key === 'Enter' || e.key === ' '){ e.preventDefault(); applyTheme(html.getAttribute('data-theme') !== 'dark'); }
  });

  // ── Modal ──
  const backdrop  = document.getElementById('modalBackdrop');
  const openBtn   = document.getElementById('openModal');
  const closeBtn  = document.getElementById('closeModal');
  openBtn.addEventListener('click', () => backdrop.classList.add('open'));
  closeBtn.addEventListener('click', () => backdrop.classList.remove('open'));
  backdrop.addEventListener('click', e => { if(e.target === backdrop) backdrop.classList.remove('open'); });
  document.addEventListener('keydown', e => { if(e.key === 'Escape') backdrop.classList.remove('open'); });

  // ── Dynamic Ras dropdown based on Jenis ──
  const rasAll = <?= json_encode($ras_all) ?>;
  const jenisSelect = document.getElementById('id_jenis');
  const rasSelect   = document.getElementById('id_ras');

  jenisSelect.addEventListener('change', function(){
    const selectedJenis = parseInt(this.value);
    rasSelect.innerHTML = '<option value="">— Select Breed —</option>';
    rasAll
      .filter(r => r.id_jenis == selectedJenis)
      .forEach(r => {
        const opt = document.createElement('option');
        opt.value = r.id_ras;
        opt.textContent = r.nama_ras;
        rasSelect.appendChild(opt);
      });
  });

  // ── Auto-open modal if there was an error ──
  <?php if($error_msg): ?>
  backdrop.classList.add('open');
  <?php endif; ?>
</script>
</body>
</html>