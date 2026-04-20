<?php
include "config/koneksi.php";
session_start();

if(!isset($_SESSION['customer_id'])){
    header("Location: cust_login.php");
    exit;
}

$id = $_SESSION['customer_id'];

// ── Fetch customer info ──
$stmt = $conn->prepare("SELECT nama_depan, nama_belakang, email, no_telepon FROM user WHERE id_user = ?");
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

    $q = "INSERT INTO pets (id_user, nama_pet, id_breed, tgl_lahir, berat_kg, catatan_medis)
          VALUES ('$id','$nama_hewan','$id_ras','$tanggal_lahir','$berat_kg','$catatan_medis')";

    if($conn->query($q)){
        $success_msg = "Pet berhasil ditambahkan!";
    } else {
        $error_msg = "Gagal menambahkan pet. Coba lagi.";
    }
}

// ── Handle Edit / Delete Pet ──
if(isset($_POST['edit_pet']) || isset($_POST['delete_pet'])){
    $pet_id = isset($_POST['pet_id']) ? (int)$_POST['pet_id'] : 0;
    if($pet_id > 0){
        if(isset($_POST['delete_pet'])){
            $q = "DELETE FROM pets WHERE id_pet = '$pet_id' AND id_user = '$id'";
            if($conn->query($q)){
                $success_msg = "Pet berhasil dihapus.";
            } else {
                $error_msg = "Gagal menghapus pet. Coba lagi.";
            }
        } else {
            $berat_kg      = isset($_POST['berat_kg']) ? (float)$_POST['berat_kg'] : 0;
            $catatan_medis = isset($_POST['catatan_medis']) ? $conn->real_escape_string($_POST['catatan_medis']) : '';
            $q = "UPDATE pets SET berat_kg = '$berat_kg', catatan_medis = '$catatan_medis' WHERE id_pet = '$pet_id' AND id_user = '$id'";
            if($conn->query($q)){
                $success_msg = "Pet data saved successfully.";
            } else {
                $error_msg = "Failed to save changes. Please try again.";
            }
        }
    } else {
        $error_msg = "Invalid pet data.";
    }
}

// ── Fetch pets ──
$pets_query = "
    SELECT h.id_pet AS id_hewan, h.nama_pet AS nama_hewan, s.nama_species AS nama_jenis, b.nama_breed AS nama_ras, h.tgl_lahir AS tanggal_lahir, h.berat_kg, h.catatan_medis
    FROM pets h
    LEFT JOIN breeds b ON h.id_breed = b.id_breed
    LEFT JOIN species s ON b.id_species = s.id_species
    WHERE h.id_user = '$id'
    ORDER BY
        CASE WHEN b.nama_breed = 'Other' THEN 1 ELSE 0 END,
        h.id_pet ASC
";
$pets_result = $conn->query($pets_query);
$pets = [];
if($pets_result){
    while($row = $pets_result->fetch_assoc()) $pets[] = $row;
}

// ── Fetch jenis for dropdown ──
$jenis_list = [];
$jr = $conn->query("SELECT id_species AS id_jenis, nama_species AS nama_jenis FROM species ORDER BY nama_species");
if($jr) while($row = $jr->fetch_assoc()) $jenis_list[] = $row;

// ── Fetch ALL ras with jenis mapping (for JS filtering) ──
$ras_all = [];
$rr = $conn->query("SELECT id_breed AS id_ras, id_species AS id_jenis, nama_breed AS nama_ras FROM breeds ORDER BY CASE WHEN nama_breed = 'Other' THEN 1 ELSE 0 END, nama_breed ASC");
if($rr) while($row = $rr->fetch_assoc()) $ras_all[] = $row;

$conn->close();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Pet — CatDogKu</title>
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
      color: var(--text);
      background: var(--bg);
      transition: background var(--transition), color var(--transition);
      min-height: 100vh;
    }
    a { text-decoration: none; color: inherit; }
    img { display: block; max-width: 100%; }

    /* ══════════════════════════════════════
       NAVBAR
    ══════════════════════════════════════ */
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
      z-index: 1002;
      padding: 12px 0 20px;
      transform: translateY(-8px); opacity: 0;
      pointer-events: none;
      transition: transform .3s, opacity .3s, background var(--transition);
    }
    .mobile-menu.open {
      transform: translateY(0); opacity: 1;
      pointer-events: auto;
    }
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

    /* ══════════════════════════════════════
       BACKGROUND
    ══════════════════════════════════════ */
    .hero-bg {
      position: fixed;
      top: var(--nav-h); left: 0; right: 0; bottom: 0;
      background: url('assets/heroo.jpg') center 30% / cover no-repeat;
      filter: brightness(.52);
      transition: filter var(--transition);
      z-index: 1;
      pointer-events: none;
    }
    [data-theme="dark"] .hero-bg { filter: brightness(.32); }
    .hero-overlay {
      position: fixed;
      top: var(--nav-h); left: 0; right: 0; bottom: 0;
      background: linear-gradient(100deg, rgba(0,0,0,.58) 38%, rgba(0,0,0,.22) 100%);
      z-index: 1;
      pointer-events: none;
    }

    /* ══════════════════════════════════════
       PAGE
    ══════════════════════════════════════ */
    .page-hero {
      margin-top: var(--nav-h);
      position: relative;
      z-index: 3;
      min-height: calc(100vh - var(--nav-h));
      display: flex;
      flex-direction: column;
    }

    .page-content {
      position: relative;
      z-index: 3;
      padding: 36px 5% 48px;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    /* ── Page header ── */
    .page-header {
      margin-bottom: 20px;
      animation: fadeUp .6s .1s both;
    }
    .page-tag {
      font-size: .76rem; font-weight: 700;
      letter-spacing: .14em; text-transform: uppercase;
      color: var(--accent); margin-bottom: 6px;
    }
    .page-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.8rem, 3.5vw, 2.6rem);
      font-weight: 700; color: #fff; line-height: 1.15;
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

    /* ── Two-column layout ── */
    .main-layout {
      display: grid;
      grid-template-columns: 280px 1fr;
      gap: 24px;
      align-items: start;
    }

    /* ── Glass card ── */
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
      padding: 24px 20px;
      animation: fadeUp .6s .2s both;
    }
    .user-info-list { display: flex; flex-direction: column; gap: 12px; }
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

    /* ── RIGHT: Pets card ── */
    .pets-card {
      padding: 0;
      animation: fadeUp .6s .3s both;
      overflow: hidden;
    }

    .pets-card-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 22px 24px 18px;
      border-bottom: 1px solid var(--border);
      transition: border-color var(--transition);
      gap: 12px;
    }
    .pets-header-left {}
    .pets-tag {
      font-size: .72rem; font-weight: 700;
      letter-spacing: .14em; text-transform: uppercase;
      color: var(--accent); margin-bottom: 3px;
    }
    .pets-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.2rem; font-weight: 700; color: var(--text);
    }

    .btn-add-pet {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 9px 16px;
      background: var(--accent); color: #fff;
      border-radius: var(--radius);
      font-family: 'DM Sans', sans-serif;
      font-size: .83rem; font-weight: 600;
      letter-spacing: .04em; text-transform: uppercase;
      border: none; cursor: pointer;
      box-shadow: 0 4px 16px rgba(76,175,80,.32);
      transition: background var(--transition), transform .2s, box-shadow .2s;
      flex-shrink: 0;
      white-space: nowrap;
    }
    .btn-add-pet svg { width: 14px; height: 14px; stroke: #fff; fill: none; stroke-width: 2.2; }
    .btn-add-pet:hover { background: var(--accent-dk); transform: translateY(-2px); box-shadow: 0 8px 22px rgba(76,175,80,.42); }

    /* ── Pets list ── */
    .pets-body {
      padding: 18px 20px 22px;
      max-height: 420px;
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
      padding: 44px 20px;
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
      gap: 12px;
      align-items: center;
      padding: 13px 14px;
      border-radius: 14px;
      border: 1px solid var(--border);
      background: var(--bg2);
      margin-bottom: 10px;
      cursor: pointer;
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
    .pet-name {
      font-family: 'Playfair Display', serif;
      font-size: .97rem; font-weight: 700;
      color: var(--text); margin-bottom: 4px;
    }
    .pet-meta {
      display: flex; flex-wrap: wrap; gap: 5px;
    }
    .pet-tag {
      font-size: .7rem; font-weight: 600;
      padding: 2px 9px; border-radius: 999px;
      background: var(--surface); border: 1px solid var(--border);
      color: var(--text-sub);
      transition: background var(--transition), border-color var(--transition);
    }
    .pet-tag.accent { background: var(--accent-lt); border-color: rgba(76,175,80,.25); color: var(--accent-dk); }
    .pet-age {
      font-size: .76rem; font-weight: 600;
      color: var(--text-sub); text-align: right; white-space: nowrap;
    }

    /* ══════════════════════════════════════
       MODAL
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
      max-height: 90vh;
      overflow-y: auto;
    }
    .modal-backdrop.open .modal { transform: translateY(0) scale(1); }

    .modal-header {
      display: flex; align-items: flex-start; justify-content: space-between;
      padding: 24px 24px 18px;
      border-bottom: 1px solid var(--border);
      transition: border-color var(--transition);
      position: sticky; top: 0;
      background: var(--surface); z-index: 1;
    }
    .modal-tag {
      font-size: .72rem; font-weight: 700;
      letter-spacing: .14em; text-transform: uppercase;
      color: var(--accent); margin-bottom: 4px;
    }
    .modal-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.2rem; font-weight: 700; color: var(--text);
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

    .modal-body { padding: 20px 24px 24px; }

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

    /* ── Edit Modal ── */
    .edit-modal-body {
      padding: 20px 24px 24px;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .edit-pet-header {
      display: flex; align-items: center; gap: 14px; margin-bottom: 6px;
    }
    .edit-pet-icon {
      width: 54px; height: 54px; border-radius: 18px;
      display: grid; place-items: center;
      font-size: 1.35rem;
      background: var(--accent-lt); color: var(--accent-dk);
      flex-shrink: 0;
    }
    .edit-pet-title { font-size: 1.1rem; font-weight: 700; margin: 0; }
    .edit-pet-subtitle { margin: 4px 0 0; color: var(--text-sub); font-size: .9rem; }
    .edit-modal-form { display: flex; flex-direction: column; gap: 14px; }
    .edit-modal-footer { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 4px; }
    .btn-save, .btn-delete {
      min-width: 130px;
      border: none; border-radius: var(--radius);
      padding: 12px 20px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 600; letter-spacing: .05em; text-transform: uppercase;
      cursor: pointer;
      transition: background var(--transition), transform .2s, box-shadow .2s;
      display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-save {
      background: var(--accent); color: #fff;
      box-shadow: 0 6px 20px rgba(76,175,80,.32);
    }
    .btn-save:hover { background: var(--accent-dk); transform: translateY(-1px); box-shadow: 0 10px 28px rgba(76,175,80,.42); }
    .btn-delete {
      background: rgba(229,57,53,.08); color: #b71c1c;
      border: 1px solid rgba(229,57,53,.2);
    }
    .btn-delete:hover { background: rgba(229,57,53,.14); transform: translateY(-1px); }
    .btn-delete svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; }
    [data-theme="dark"] .btn-delete { color: #ef5350; }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ══════════════════════════════════════
       RESPONSIVE
    ══════════════════════════════════════ */
    @media (max-width: 768px) {
      :root { --nav-h: 60px; }

      /* Show hamburger, hide desktop links */
      .nav-links { display: none; }
      .hamburger { display: flex; }
      .mobile-menu { display: block; }

      /* Stack layout vertically */
      .main-layout {
        grid-template-columns: 1fr;
        gap: 16px;
      }

      /* User card horizontal on tablet */
      .user-card {
        padding: 18px 16px;
      }
      .user-info-list {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
      }

      .pets-body { max-height: 340px; }

      /* Pet item adjust */
      .pet-item { grid-template-columns: 40px 1fr; }
      .pet-age { display: none; }

      /* Form 2-col stays on tablet, collapse on mobile */
      .form-row-2 { grid-template-columns: 1fr 1fr; }

      .page-content { padding: 24px 4% 40px; }
    }

    @media (max-width: 540px) {
      :root { --nav-h: 56px; }

      .user-info-list { grid-template-columns: 1fr; }

      .form-row-2 { grid-template-columns: 1fr; }

      .pets-card-header { padding: 16px 16px 14px; flex-wrap: wrap; }
      .pets-body { padding: 14px 14px 18px; max-height: 300px; }

      .modal { border-radius: 20px; }
      .modal-header, .modal-body { padding-left: 16px; padding-right: 16px; }
      .edit-modal-body { padding: 16px; }

      .edit-modal-footer { flex-direction: column; }
      .btn-save, .btn-delete { width: 100%; min-width: unset; }

      .toggle-label { display: none; }
    }

    @media (max-width: 380px) {
      .btn-add-pet span { display: none; }
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

<!-- ── Mobile Menu ── -->
<div class="mobile-menu" id="mobileMenu" aria-hidden="true">
  <ul>
    <li><a href="index.php#home"    onclick="closeMenu()">Home</a></li>
    <li><a href="index.php#about"   onclick="closeMenu()">About</a></li>
    <li><a href="index.php#service" onclick="closeMenu()">Service</a></li>
    <li><a href="reserve.php"       onclick="closeMenu()">Reserve</a></li>
    <li><a href="cust_profile.php"  onclick="closeMenu()">Profile</a></li>
    <li><a href="index.php#contact" onclick="closeMenu()">Contact</a></li>
  </ul>
  <div class="mobile-menu-footer">
    <span>Dark Mode</span>
    <div class="theme-toggle" id="themeToggleMobile" role="button" tabindex="0" aria-label="Toggle dark mode">
      <div class="toggle-track" id="toggleTrackMobile"><div class="toggle-knob"></div></div>
    </div>
  </div>
</div>

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
            <div class="uil-value" style="word-break:break-all;font-size:.84rem;"><?= htmlspecialchars($email) ?></div>
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
            <p class="pets-title">Pet List</p>
          </div>
          <button class="btn-add-pet" id="openModal">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Add Pet</span>
          </button>
        </div>

        <div class="pets-body">
          <?php if(empty($pets)): ?>
          <div class="pet-empty">
            <p class="pet-empty-title">No pets found</p>
            <p class="pet-empty-sub">Tap "Add Pet" to register your first pet.</p>
          </div>
          <?php else: ?>
            <?php foreach($pets as $i => $pet):
              $jenis_lower = strtolower($pet['nama_jenis'] ?? '');
              $emoji = in_array($jenis_lower, ['dog','anjing']) ? '🐶'
                     : (in_array($jenis_lower, ['cat','kucing']) ? '🐱' : '🐾');
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
            <div class="pet-item"
                 style="animation-delay:<?= $i * .06 ?>s;"
                 data-id="<?= $pet['id_hewan'] ?>"
                 data-name="<?= htmlspecialchars($pet['nama_hewan']) ?>"
                 data-jenis="<?= htmlspecialchars($pet['nama_jenis'] ?? '') ?>"
                 data-ras="<?= htmlspecialchars($pet['nama_ras'] ?? '') ?>"
                 data-berat="<?= $pet['berat_kg'] ?>"
                 data-catatan="<?= htmlspecialchars($pet['catatan_medis'] ?? '') ?>"
                 data-emoji="<?= $emoji ?>">
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

<!-- ══════════════ ADD PET MODAL ══════════════ -->
<div class="modal-backdrop" id="modalBackdrop">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-header">
      <div>
        <p class="modal-tag">New Registration</p>
        <h2 class="modal-title" id="modalTitle">Add a Pet</h2>
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
            <input type="text" id="nama_hewan" name="nama_hewan" placeholder="e.g. Buddy" required />
          </div>
          <div class="form-group">
            <label for="tanggal_lahir">Date of Birth</label>
            <input type="date" id="tanggal_lahir" name="tanggal_lahir" max="<?= date('Y-m-d') ?>" />
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label for="id_jenis">Type</label>
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
            <label for="id_ras">Breed</label>
            <div class="select-wrap">
              <select id="id_ras" name="id_ras" required>
                <option value="">— Select Breed —</option>
              </select>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label for="berat_kg">Weight (kg)</label>
          <input type="number" id="berat_kg" name="berat_kg" placeholder="e.g. 4.5" step="0.01" min="0" max="999" />
        </div>
        <div class="form-group">
          <label for="catatan_medis">Medical Records</label>
          <textarea id="catatan_medis" name="catatan_medis" placeholder="Allergies, vaccine history, special conditions..."></textarea>
        </div>
        <button type="submit" name="add_pet" class="btn-submit">Add Pet</button>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════ EDIT PET MODAL ══════════════ -->
<div class="modal-backdrop" id="editModalBackdrop">
  <div class="modal" role="dialog" aria-modal="true">
    <div class="modal-header">
      <div>
        <p class="modal-tag">Edit Pet</p>
        <h2 class="modal-title">Update Details</h2>
      </div>
      <button class="modal-close" id="closeEditModal" aria-label="Close">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="edit-modal-body">
      <div class="edit-pet-header">
        <div class="edit-pet-icon" id="editPetIcon">🐾</div>
        <div>
          <p class="edit-pet-title" id="editPetName">—</p>
          <p class="edit-pet-subtitle" id="editPetMeta">—</p>
        </div>
      </div>
      <form class="edit-modal-form" method="POST">
        <input type="hidden" name="pet_id" id="editPetId" />
        <div class="form-group">
          <label for="editBerat">Weight (kg)</label>
          <input type="number" id="editBerat" name="berat_kg" placeholder="e.g. 4.5" step="0.01" min="0" max="999" />
        </div>
        <div class="form-group">
          <label for="editCatatan">Medical Records</label>
          <textarea id="editCatatan" name="catatan_medis" placeholder="Allergies, vaccine history, special conditions..."></textarea>
        </div>
        <div class="edit-modal-footer">
          <button type="submit" name="edit_pet" class="btn-save">Save Changes</button>
          <button type="submit" name="delete_pet" class="btn-delete"
                  onclick="return confirm('Delete this pet?')">
            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            Delete
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════ SCRIPT ══════════════ -->
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
    if (label) label.textContent = dark ? 'ON' : 'OFF';
    localStorage.setItem('theme', dark ? 'dark' : 'light');
  }
  function toggleTheme() { applyTheme(html.getAttribute('data-theme') !== 'dark'); }

  const saved = localStorage.getItem('theme');
  applyTheme(saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches);

  document.getElementById('themeToggle').addEventListener('click', toggleTheme);
  document.getElementById('themeToggle').addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleTheme(); }
  });
  if (trackMobile) {
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
    hamburger.setAttribute('aria-expanded', String(menuOpen));
    mobileMenu.classList.toggle('open', menuOpen);
    mobileMenu.setAttribute('aria-hidden', String(!menuOpen));
  });

  document.addEventListener('click', e => {
    if (menuOpen && !hamburger.contains(e.target) && !mobileMenu.contains(e.target)) closeMenu();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeMenu();
      backdrop.classList.remove('open');
      editBackdrop.classList.remove('open');
    }
  });

  /* ── Add Pet Modal ── */
  const backdrop = document.getElementById('modalBackdrop');
  document.getElementById('openModal').addEventListener('click', () => backdrop.classList.add('open'));
  document.getElementById('closeModal').addEventListener('click', () => backdrop.classList.remove('open'));
  backdrop.addEventListener('click', e => { if (e.target === backdrop) backdrop.classList.remove('open'); });

  /* ── Dynamic Breed dropdown ── */
  const rasAll      = <?= json_encode($ras_all) ?>;
  const jenisSelect = document.getElementById('id_jenis');
  const rasSelect   = document.getElementById('id_ras');

  jenisSelect.addEventListener('change', function() {
    const selected = parseInt(this.value);
    rasSelect.innerHTML = '<option value="">— Select Breed —</option>';
    rasAll.filter(r => r.id_jenis == selected).forEach(r => {
      const opt = document.createElement('option');
      opt.value = r.id_ras;
      opt.textContent = r.nama_ras;
      rasSelect.appendChild(opt);
    });
  });

  /* ── Edit Pet Modal ── */
  const editBackdrop = document.getElementById('editModalBackdrop');
  document.getElementById('closeEditModal').addEventListener('click', () => editBackdrop.classList.remove('open'));
  editBackdrop.addEventListener('click', e => { if (e.target === editBackdrop) editBackdrop.classList.remove('open'); });

  document.querySelectorAll('.pet-item').forEach(item => {
    item.addEventListener('click', function() {
      document.getElementById('editPetId').value         = this.dataset.id;
      document.getElementById('editPetName').textContent = this.dataset.name;
      document.getElementById('editPetMeta').textContent = this.dataset.jenis + ' · ' + this.dataset.ras;
      document.getElementById('editPetIcon').textContent = this.dataset.emoji;
      document.getElementById('editBerat').value         = this.dataset.berat;
      document.getElementById('editCatatan').value       = this.dataset.catatan;
      editBackdrop.classList.add('open');
    });
  });

  /* ── Auto-open add modal on error ── */
  <?php if($error_msg): ?>
  backdrop.classList.add('open');
  <?php endif; ?>
</script>
</body>
</html>
