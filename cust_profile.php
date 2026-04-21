<?php
include "config/koneksi.php";
session_start();

if(!isset($_SESSION['customer_id'])){
    header("Location: cust_login.php");
    exit;
}

$id = $_SESSION['customer_id'];
$stmt = $conn->prepare("SELECT nama_depan, nama_belakang, email, no_telepon, alamat, created_at FROM user WHERE id_user = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($nama_depan, $nama_belakang, $email, $no_telepon, $alamat, $created_at);
$stmt->fetch();
$stmt->close();
$conn->close();

$initials  = strtoupper(substr($nama_depan, 0, 1) . substr($nama_belakang, 0, 1));
$full_name = htmlspecialchars($nama_depan . ' ' . $nama_belakang);
$join_date = $created_at ? date("d M Y", strtotime($created_at)) : '-';
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profile — CatDogKu</title>
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
      z-index: 1002;
      pointer-events: none;
      padding: 12px 0 20px;
      transform: translateY(-8px); opacity: 0;
      transition: transform .3s, opacity .3s, background var(--transition);
    }
    .mobile-menu.open { transform: translateY(0); opacity: 1; pointer-events: auto; }
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
      pointer-events: none;
      background: url('assets/heroo.jpg') center 30% / cover no-repeat;
      filter: brightness(.52);
      transition: filter var(--transition);
    }
    [data-theme="dark"] .bg-image { filter: brightness(.32); }
    .bg-overlay {
      position: fixed;
      top: var(--nav-h); left: 0; right: 0; bottom: 0;
      z-index: 0;
      pointer-events: none;
      background: linear-gradient(100deg, rgba(0,0,0,.55) 35%, rgba(0,0,0,.15) 75%);
    }

    /* ── Page wrapper ── */
    .page-wrapper {
      position: relative;
      z-index: 2;
      padding: calc(var(--nav-h) + 32px) 5% 64px;
      max-width: 1100px;
      margin: 0 auto;
      min-height: 100vh;
    }

    /* ── Page header ── */
    .page-header {
      margin-bottom: 28px;
      animation: fadeUp .6s both;
    }
    .page-label {
      font-size: .76rem; font-weight: 700; letter-spacing: .14em;
      text-transform: uppercase; color: var(--accent); margin-bottom: 6px;
    }
    .page-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.8rem, 4vw, 2.6rem); font-weight: 700;
      color: #fff; line-height: 1.2;
    }

    /* ── Profile layout ── */
    .profile-layout {
      display: grid;
      grid-template-columns: 280px 1fr;
      gap: 24px;
      align-items: start;
      animation: fadeUp .6s .1s both;
    }

    /* ── Sidebar ── */
    .profile-sidebar {
      background: rgba(255,255,255,.88);
      border: 1px solid rgba(255,255,255,.5);
      border-radius: 20px;
      padding: 30px 22px;
      text-align: center;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      transition: background var(--transition);
      position: sticky;
       z-index: 4;
      top: calc(var(--nav-h) + 24px);
    }
    [data-theme="dark"] .profile-sidebar {
      background: rgba(30,39,30,.88);
      border-color: rgba(76,175,80,.18);
    }

    .avatar {
      width: 78px; height: 78px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent-dk));
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 1.8rem; font-weight: 700; color: #fff;
      margin: 0 auto 14px;
      box-shadow: 0 8px 24px rgba(76,175,80,.4);
    }
    .sidebar-name {
      font-family: 'Playfair Display', serif;
      font-size: 1.1rem; font-weight: 700;
      margin-bottom: 4px; color: var(--text);
    }
    .sidebar-email {
      font-size: .79rem; color: var(--text-sub);
      margin-bottom: 4px; word-break: break-all;
    }
    .sidebar-join {
      font-size: .74rem; color: var(--text-sub); margin-bottom: 20px;
    }
    .sidebar-join span { font-weight: 600; color: var(--accent); }
    .sidebar-divider {
      height: 1px; background: var(--border); margin-bottom: 18px;
      transition: background var(--transition);
    }
    .sidebar-actions { display: flex; flex-direction: column; gap: 8px; }

    .btn-action {
      display: flex; align-items: center; gap: 9px;
      padding: 10px 14px; border-radius: var(--radius);
      font-family: 'DM Sans', sans-serif;
      font-size: .84rem; font-weight: 600; letter-spacing: .03em;
      cursor: pointer; border: 1.5px solid transparent;
      transition: background .25s, border-color .25s, color .25s, transform .2s, box-shadow .2s;
      text-align: left; text-decoration: none;
    }
    .btn-action svg {
      width: 15px; height: 15px;
      stroke: currentColor; fill: none; stroke-width: 1.8; flex-shrink: 0;
    }
    .btn-green {
      background: var(--accent); color: #fff;
      box-shadow: 0 4px 14px rgba(76,175,80,.3);
    }
    .btn-green:hover {
      background: var(--accent-dk); transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(76,175,80,.4);
    }
    .btn-outline {
      background: transparent; border-color: var(--border); color: var(--text-sub);
    }
    .btn-outline:hover {
      border-color: var(--accent); color: var(--accent);
      background: var(--accent-lt); transform: translateY(-2px);
    }
    .btn-danger {
      background: transparent; border-color: var(--border); color: #e57373;
    }
    .btn-danger:hover {
      background: #ffebee; border-color: #e57373; transform: translateY(-2px);
    }
    [data-theme="dark"] .btn-danger:hover { background: #2d1b1b; border-color: #e57373; }

    /* ── Main info card ── */
    .info-card {
      background: rgba(255,255,255,.88);
      border: 1px solid rgba(255,255,255,.5);
      border-radius: 20px;
      padding: 30px 28px;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      transition: background var(--transition);
    }
    [data-theme="dark"] .info-card {
      background: rgba(30,39,30,.88);
      border-color: rgba(76,175,80,.18);
    }
    .card-header {
      margin-bottom: 24px; padding-bottom: 16px;
      border-bottom: 1px solid var(--border);
      transition: border-color var(--transition);
    }
    .card-tag {
      font-size: .72rem; font-weight: 700; letter-spacing: .14em;
      text-transform: uppercase; color: var(--accent); margin-bottom: 4px;
    }
    .card-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.25rem; font-weight: 700; color: var(--text);
    }
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px 28px;
    }
    .info-label {
      font-size: .7rem; font-weight: 700; letter-spacing: .1em;
      text-transform: uppercase; color: var(--text-sub); margin-bottom: 6px;
    }
    .info-value {
      font-size: .93rem; font-weight: 500; color: var(--text);
      padding: 10px 13px;
      background: var(--bg2); border: 1px solid var(--border); border-radius: 10px;
      transition: background var(--transition), border-color var(--transition), color var(--transition);
    }
    .info-value.empty { color: var(--text-sub); font-style: italic; font-weight: 400; }
    .info-value.address { line-height: 1.65; }
    .info-full { grid-column: 1 / -1; }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Responsive ── */
    @media (max-width: 860px) {
      .profile-layout {
        grid-template-columns: 1fr;
      }
      .profile-sidebar {
        position: static;
        /* horizontal layout on tablet */
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 0 20px;
        text-align: left;
        align-items: start;
        padding: 22px 20px;
      }
      .avatar { margin: 0 0 8px 0; }
      .sidebar-name { font-size: 1rem; }
      .sidebar-divider { grid-column: 1 / -1; margin: 12px 0; }
      .sidebar-actions { grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    }

    @media (max-width: 768px) {
      :root { --nav-h: 60px; }
      .nav-links { display: none; }
      .hamburger { display: flex; }
      .mobile-menu { display: block; }
      .info-grid { grid-template-columns: 1fr; }
      .info-full { grid-column: auto; }
    }

    @media (max-width: 540px) {
      .profile-sidebar {
        grid-template-columns: 1fr;
        text-align: center;
      }
      .avatar { margin: 0 auto 8px; }
      .sidebar-actions { grid-template-columns: 1fr; }
      .info-card { padding: 20px 16px; }
      .page-wrapper { padding-left: 4%; padding-right: 4%; }
    }

    @media (max-width: 480px) {
      :root { --nav-h: 56px; }
      .toggle-label { display: none; }
    }
  </style>
</head>
<body>

<!-- ── NAVBAR ── -->
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
    <li><a href="cust_profile.php" class="active">Profile</a></li>
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

<!-- ── MOBILE MENU ── -->
<div class="mobile-menu" id="mobileMenu" aria-hidden="true">
  <ul>
    <li><a href="index.php#home"    onclick="closeMenu()">Home</a></li>
    <li><a href="index.php#about"   onclick="closeMenu()">About</a></li>
    <li><a href="index.php#service" onclick="closeMenu()">Service</a></li>
    <li><a href="reserve.php"       onclick="closeMenu()">Reserve</a></li>
    <li><a href="cust_profile.php"  class="active" onclick="closeMenu()">Profile</a></li>
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

<!-- ── PAGE CONTENT ── -->
<div class="page-wrapper">

  <div class="page-header">
    <p class="page-label">Account</p>
  </div>

  <div class="profile-layout">

    <!-- ── Sidebar ── -->
    <aside class="profile-sidebar">
      <div>
        <p class="sidebar-name"><?= $full_name ?></p>
        <p class="sidebar-email"><?= htmlspecialchars($email) ?></p>
        <p class="sidebar-join">Member since <span><?= $join_date ?></span></p>
      </div>
      <div class="sidebar-divider"></div>
      <div class="sidebar-actions">
        <a href="show_pet.php" class="btn-action btn-green">
          <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
          Show Pet
        </a>
        <a href="cust_resetpw.php" class="btn-action btn-outline">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Reset Password
        </a>
        <a href="cust_resetphone.php" class="btn-action btn-outline">
          <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          Change Phone
        </a>
        <a href="cust_logout.php" class="btn-action btn-danger">
          <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Logout
        </a>
      </div>
    </aside>

    <!-- ── Main info ── -->
    <div class="profile-main">
      <div class="info-card">
        <div class="card-header">
          <p class="card-tag">Personal Information</p>
          <h2 class="card-title">Account Details</h2>
        </div>
        <div class="info-grid">

          <div class="info-item">
            <p class="info-label">First Name</p>
            <div class="info-value <?= empty($nama_depan) ? 'empty' : '' ?>">
              <?= !empty($nama_depan) ? htmlspecialchars($nama_depan) : '—' ?>
            </div>
          </div>

          <div class="info-item">
            <p class="info-label">Last Name</p>
            <div class="info-value <?= empty($nama_belakang) ? 'empty' : '' ?>">
              <?= !empty($nama_belakang) ? htmlspecialchars($nama_belakang) : '—' ?>
            </div>
          </div>

          <div class="info-item">
            <p class="info-label">Email</p>
            <div class="info-value <?= empty($email) ? 'empty' : '' ?>">
              <?= !empty($email) ? htmlspecialchars($email) : '—' ?>
            </div>
          </div>

          <div class="info-item">
            <p class="info-label">Phone Number</p>
            <div class="info-value <?= empty($no_telepon) ? 'empty' : '' ?>">
              <?= !empty($no_telepon) ? htmlspecialchars($no_telepon) : '—' ?>
            </div>
          </div>

          <div class="info-item info-full">
            <p class="info-label">Address</p>
            <div class="info-value address <?= empty($alamat) ? 'empty' : '' ?>">
              <?= !empty($alamat) ? htmlspecialchars($alamat) : 'No address provided.' ?>
            </div>
          </div>

        </div>
      </div>
    </div>

  </div><!-- /.profile-layout -->
</div><!-- /.page-wrapper -->

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
</script>
</body>
</html>
