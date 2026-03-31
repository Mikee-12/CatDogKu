<?php
include "config/koneksi.php";
session_start();

if(!isset($_SESSION['customer_id'])){
    header("Location: cust_login.php");
    exit;
}

$id = $_SESSION['customer_id'];
$stmt = $conn->prepare("SELECT nama_depan, nama_belakang, email, no_telepon, alamat, tanggal_daftar FROM customer WHERE id_pelanggan = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($nama_depan, $nama_belakang, $email, $no_telepon, $alamat, $tanggal_daftar);
$stmt->fetch();
$stmt->close();
$conn->close();

$initials = strtoupper(substr($nama_depan, 0, 1) . substr($nama_belakang, 0, 1));
$full_name = htmlspecialchars($nama_depan . ' ' . $nama_belakang);
$join_date = $tanggal_daftar ? date("d M Y", strtotime($tanggal_daftar)) : '-';
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile — CatDogKu</title>
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
      overflow: hidden;
      min-height: 100vh;
    }
    a { text-decoration: none; color: inherit; }
    img { display: block; max-width: 100%; }

    /* ─── Navbar ─── */
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

    .theme-toggle {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      user-select: none;
    }
    .toggle-label {
      font-size: .78rem;
      font-weight: 600;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--text-sub);
      width: 28px;
      text-align: right;
    }
    .toggle-track {
      position: relative;
      width: 48px; height: 26px;
      background: var(--border);
      border-radius: 999px;
      border: 2px solid var(--border);
      cursor: pointer;
      transition: background var(--transition), border-color var(--transition);
    }
    .toggle-track.on { background: var(--accent); border-color: var(--accent); }
    .toggle-knob {
      position: absolute;
      top: 2px; left: 2px;
      width: 18px; height: 18px;
      border-radius: 50%;
      background: #fff;
      box-shadow: 0 1px 4px rgba(0,0,0,.2);
      transition: transform var(--transition);
    }
    .toggle-track.on .toggle-knob { transform: translateX(22px); }

    /* ─── Hero wrapper (same style as index) ─── */
    .page-hero {
      margin-top: var(--nav-h);
      position: relative;
      min-height: calc(100vh - var(--nav-h));
      display: flex;
      flex-direction: column;
    }
    .hero-bg {
      position: absolute;
      inset: 0;
      width: 100%; height: 100%;
      object-fit: cover;
      object-position: center 30%;
      filter: brightness(.55);
      transition: filter var(--transition);
    }
    [data-theme="dark"] .hero-bg { filter: brightness(.38); }
    .hero-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(100deg, rgba(0,0,0,.62) 38%, rgba(0,0,0,.25) 100%);
    }

    /* ─── Profile content ─── */
    .profile-content {
      position: relative;
      z-index: 2;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 60px 8%;
    }

    .page-header {
      margin-bottom: 40px;
      animation: fadeUp .6s .1s both;
    }
    .page-tag {
      font-size: .78rem;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 10px;
    }
    .page-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2rem, 4vw, 2.8rem);
      font-weight: 700;
      color: #fff;
      line-height: 1.15;
    }

    /* ─── Two-column layout, vertically centered ─── */
    .profile-layout {
      display: grid;
      grid-template-columns: 290px 1fr;
      gap: 28px;
      align-items: center;
    }

    /* ─── Sidebar ─── */
    .profile-sidebar {
      background: rgba(255,255,255,.9);
      border: 1px solid rgba(255,255,255,.55);
      border-radius: 20px;
      padding: 34px 26px;
      text-align: center;
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      animation: fadeUp .6s .2s both;
      transition: background var(--transition);
    }
    [data-theme="dark"] .profile-sidebar {
      background: rgba(30,39,30,.9);
      border-color: rgba(76,175,80,.2);
    }
    .avatar {
      width: 84px; height: 84px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent-dk));
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 1.9rem;
      font-weight: 700;
      color: #fff;
      margin: 0 auto 16px;
      box-shadow: 0 8px 24px rgba(76,175,80,.4);
    }
    .sidebar-name {
      font-family: 'Playfair Display', serif;
      font-size: 1.15rem;
      font-weight: 700;
      margin-bottom: 5px;
      color: var(--text);
    }
    .sidebar-email {
      font-size: .81rem;
      color: var(--text-sub);
      margin-bottom: 4px;
      word-break: break-all;
    }
    .sidebar-join {
      font-size: .75rem;
      color: var(--text-sub);
      margin-bottom: 22px;
    }
    .sidebar-join span { font-weight: 600; color: var(--accent); }

    .sidebar-divider {
      height: 1px;
      background: var(--border);
      margin: 0 0 22px;
      transition: background var(--transition);
    }
    .sidebar-actions { display: flex; flex-direction: column; gap: 9px; }

    .btn-action {
      display: flex;
      align-items: center;
      gap: 9px;
      padding: 11px 15px;
      border-radius: var(--radius);
      font-size: .85rem;
      font-weight: 600;
      letter-spacing: .03em;
      cursor: pointer;
      border: 1.5px solid transparent;
      transition: background .25s, border-color .25s, color .25s, transform .2s, box-shadow .2s;
      text-align: left;
    }
    .btn-action svg {
      width: 16px; height: 16px;
      stroke: currentColor;
      fill: none;
      stroke-width: 1.8;
      flex-shrink: 0;
    }
    .btn-green {
      background: var(--accent);
      color: #fff;
      box-shadow: 0 4px 14px rgba(76,175,80,.3);
    }
    .btn-green:hover {
      background: var(--accent-dk);
      transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(76,175,80,.4);
    }
    .btn-outline {
      background: transparent;
      border-color: var(--border);
      color: var(--text-sub);
    }
    .btn-outline:hover {
      border-color: var(--accent);
      color: var(--accent);
      background: var(--accent-lt);
      transform: translateY(-2px);
    }
    .btn-danger {
      background: transparent;
      border-color: var(--border);
      color: #e57373;
    }
    .btn-danger:hover {
      background: #ffebee;
      border-color: #e57373;
      transform: translateY(-2px);
    }
    [data-theme="dark"] .btn-danger:hover {
      background: #2d1b1b;
      border-color: #e57373;
    }

    /* ─── Main info card ─── */
    .profile-main { animation: fadeUp .6s .3s both; }
    .info-card {
      background: rgba(255,255,255,.9);
      border: 1px solid rgba(255,255,255,.55);
      border-radius: 20px;
      padding: 34px;
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      transition: background var(--transition);
    }
    [data-theme="dark"] .info-card {
      background: rgba(30,39,30,.9);
      border-color: rgba(76,175,80,.2);
    }
    .card-header {
      margin-bottom: 28px;
      padding-bottom: 18px;
      border-bottom: 1px solid var(--border);
      transition: border-color var(--transition);
    }
    .card-tag {
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 4px;
    }
    .card-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text);
    }
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 22px 32px;
    }
    .info-label {
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: var(--text-sub);
      margin-bottom: 6px;
    }
    .info-value {
      font-size: .96rem;
      font-weight: 500;
      color: var(--text);
      padding: 11px 14px;
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 10px;
      transition: background var(--transition), border-color var(--transition), color var(--transition);
    }
    .info-value.empty { color: var(--text-sub); font-style: italic; font-weight: 400; }
    .info-value.address { line-height: 1.65; }
    .info-full { grid-column: 1 / -1; }

    /* ─── Footer ─── */
    footer {
      position: relative;
      z-index: 2;
      text-align: center;
      padding: 26px 8%;
      border-top: 1px solid rgba(255,255,255,.12);
      font-size: .84rem;
      color: rgba(255,255,255,.45);
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(22px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 900px) {
      .profile-layout { grid-template-columns: 1fr; align-items: start; }
      .info-grid { grid-template-columns: 1fr; }
      .info-full { grid-column: auto; }
    }
    @media (max-width: 620px) {
      .nav-links { display: none; }
      .profile-content { padding: 36px 5% 48px; }
      .info-card, .profile-sidebar { padding: 22px 16px; }
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
  <li><a href="index.php #about">About</a></li>
  <li><a href="index.php #service">Service</a></li>
  <li><a href="reserve.php">Reserve</a></li>
  <li><a href="Profile"class="active">Profile</a></li>
  <li><a href="index.php #contact">Contact</a></li>
</ul>
    <div class="nav-spacer"></div>
    <div class="theme-toggle" id="themeToggle" role="button" tabindex="0" aria-label="Toggle dark mode">
      <span class="toggle-label" id="toggleLabel">OFF</span>
      <div class="toggle-track" id="toggleTrack">
        <div class="toggle-knob"></div>
      </div>
    </div>
  </nav>

  <!-- ══════════════ HERO + CONTENT ══════════════ -->
  <div class="page-hero">
    <img src="assets/heroo.jpg" alt="Background" class="hero-bg" />
    <div class="hero-overlay"></div>

    <div class="profile-content">

      <div class="profile-layout">

        <!-- ── Sidebar ── -->
        <aside class="profile-sidebar">
          <p class="sidebar-name"><?= $full_name ?></p>
          <p class="sidebar-email"><?= htmlspecialchars($email) ?></p>
          <p class="sidebar-join">Member since <span><?= $join_date ?></span></p>
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
              Change Phone Number
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
    </div><!-- /.profile-content -->

    <footer>
      &copy; 2025 CatDogKu. All rights reserved.
    </footer>
  </div><!-- /.page-hero -->

  <script>
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

    toggle.addEventListener('click', () => {
      applyTheme(html.getAttribute('data-theme') !== 'dark');
    });
    toggle.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        applyTheme(html.getAttribute('data-theme') !== 'dark');
      }
    });
  </script>
</body>
</html>