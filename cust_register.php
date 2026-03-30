<?php
include "config/koneksi.php";

if(isset($_POST['register'])){

    $nama_depan = $_POST['nama_depan'];
    $nama_belakang = $_POST['nama_belakang'];
    $email = $_POST['email'];

    // HASH PASSWORD
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $no_telepon = $_POST['no_telepon'];
    $alamat = $_POST['alamat'];

    $query = "INSERT INTO customer 
    (nama_depan,nama_belakang,email,password,no_telepon,alamat,tanggal_daftar)
    VALUES
    ('$nama_depan','$nama_belakang','$email','$password','$no_telepon','$alamat',NOW())";

    $result = mysqli_query($conn,$query);

    if($result){
        echo "<script>alert('successful'); window.location='cust_login.php';</script>";
    }else{
        echo "<script>alert('Failed');</script>";
    }

}
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register — CatDogKu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />

  <style>
    /* ─── CSS Variables ─── */
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

    /* ─── Reset ─── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      transition: background var(--transition), color var(--transition);
      overflow-x: hidden;
      min-height: 100vh;
      overflow: hidden;
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
    .nav-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-shrink: 0;
    }
    .nav-logo img {
      height: 42px;
      width: auto;
    }
    [data-theme="light"] .logo-dark  { display: none; }
    [data-theme="dark"]  .logo-light { display: none; }
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
      transition: color var(--transition);
      width: 28px;
      text-align: right;
    }
    .toggle-track {
      position: relative;
      width: 48px;
      height: 26px;
      background: var(--border);
      border-radius: 999px;
      transition: background var(--transition), border-color var(--transition);
      border: 2px solid var(--border);
      cursor: pointer;
    }
    .toggle-track.on {
      background: var(--accent);
      border-color: var(--accent);
    }
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

    /* ─── Page wrapper ─── */
.page-wrapper {
  margin-top: var(--nav-h);
  min-height: calc(100vh - var(--nav-h));
  position: relative;
  display: flex;
  align-items: center;
  justify-content: flex-start; /* geser ke kiri */
  padding: 20px 8%;
}

    /* ─── Background image ─── */
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

    /* ─── Register Card ─── */
    .register-card {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 620px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 24px;
      padding: 32px 28px; 
      box-shadow: 0 24px 80px rgba(0,0,0,.25);
      animation: fadeUp .7s .22s both;
      transition: background var(--transition), border-color var(--transition);
    }

    .card-header {
      margin-bottom: 20px;
    }
    .card-label {
      font-size: .78rem;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 8px;
    }
    .card-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text);
      line-height: 1.15;
    }

    /* ─── Form ─── */
    .register-form {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 7px;
    }
    .form-group label {
      font-size: .78rem;
      font-weight: 600;
      letter-spacing: .07em;
      text-transform: uppercase;
      color: var(--text-sub);
      transition: color var(--transition);
    }
    .input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }
    .input-wrap svg {
      position: absolute;
      left: 14px;
      width: 16px; height: 16px;
      stroke: var(--text-sub);
      fill: none;
      stroke-width: 1.8;
      pointer-events: none;
      transition: stroke var(--transition);
      flex-shrink: 0;
    }
    .form-group input,
    .form-group textarea {
      width: 100%;
      background: var(--bg2);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 10px 14px 10px 40px;
      font-family: 'DM Sans', sans-serif;
      font-size: .95rem;
      color: var(--text);
      outline: none;
      transition: border-color .2s, background var(--transition), color var(--transition), box-shadow .2s;
    }
    .form-group textarea {
      padding: 10px 14px 10px 40px;
      resize: none;
      min-height: 60px;
    }
    .form-group input:focus,
    .form-group textarea:focus {
      border-color: var(--accent);
      background: var(--surface);
      box-shadow: 0 0 0 3px rgba(76,175,80,.12);
    }
    .form-group input:focus + svg,
    .form-group textarea:focus + svg {
      stroke: var(--accent);
    }
    .input-wrap:focus-within svg { stroke: var(--accent); }

    /* ─── No-icon inputs (textarea) need different padding ─── */
    .no-icon input,
    .no-icon textarea {
      padding-left: 14px;
    }

    /* ─── Submit button ─── */
    .btn-primary {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 14px 34px;
      background: var(--accent);
      color: #fff;
      border-radius: var(--radius);
      font-family: 'DM Sans', sans-serif;
      font-size: .92rem;
      font-weight: 600;
      letter-spacing: .05em;
      text-transform: uppercase;
      border: none;
      cursor: pointer;
      width: 100%;
      margin-top: 6px;
      transition: background var(--transition), transform .2s, box-shadow .2s;
      box-shadow: 0 6px 20px rgba(76,175,80,.35);
    }
    .btn-primary:hover {
      background: var(--accent-dk);
      transform: translateY(-2px);
      box-shadow: 0 10px 28px rgba(76,175,80,.45);
    }
    .btn-arrow { transition: transform .2s; }
    .btn-primary:hover .btn-arrow { transform: translateX(4px); }

    .card-footer {
      margin-top: 22px;
      text-align: center;
      font-size: .88rem;
      color: var(--text-sub);
    }
    .card-footer a {
      color: var(--accent);
      font-weight: 600;
      border-bottom: 1px solid transparent;
      transition: border-color .2s;
    }
    .card-footer a:hover { border-color: var(--accent); }

    /* ─── Footer ─── */
    footer {
      position: relative;
      z-index: 2;
      text-align: center;
      padding: 20px 8%;
      font-size: .82rem;
      color: rgba(255,255,255,.45);
    }

    /* ─── Keyframes ─── */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(28px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ─── Responsive ─── */
    @media (max-width: 900px) {
      .page-wrapper {
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 48px 5%;
      }
      .hero-copy {
        padding-right: 0;
        padding-bottom: 40px;
        max-width: 520px;
        text-align: center;
      }
    }
    @media (max-width: 560px) {
      .register-card { padding: 36px 24px; }
      .form-row { grid-template-columns: 1fr; }
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
    <div class="nav-spacer"></div>
    <div class="theme-toggle" id="themeToggle" role="button" tabindex="0" aria-label="Toggle dark mode">
      <span class="toggle-label" id="toggleLabel">OFF</span>
      <div class="toggle-track" id="toggleTrack">
        <div class="toggle-knob"></div>
      </div>
    </div>
  </nav>

  <!-- ══════════════ BACKGROUND ══════════════ -->
  <div class="bg-image"></div>
  <div class="bg-overlay"></div>

  <!-- ══════════════ MAIN ══════════════ -->
  <div class="page-wrapper">



    <!-- Register card -->
    <div class="register-card">
      <div class="card-header">
        <p class="card-label">Create Account</p>
      </div>

      <form class="register-form" method="POST">

        <div class="form-row">
          <div class="form-group">
            <label for="nama_depan">First Name</label>
            <div class="input-wrap">
              <input type="text" id="nama_depan" name="nama_depan" placeholder="First Name" required />
              <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
          </div>
          <div class="form-group">
            <label for="nama_belakang">Last Name</label>
            <div class="input-wrap">
              <input type="text" id="nama_belakang" name="nama_belakang" placeholder="Last Name" required />
              <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
          </div>
        </div>

<div class="form-row">

  <div class="form-group">
    <label for="email">Email</label>
    <div class="input-wrap">
      <input type="email" id="email" name="email" placeholder="example@gmail.com" required />
      <svg viewBox="0 0 24 24">
        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
        <polyline points="22,6 12,13 2,6"/>
      </svg>
    </div>
  </div>

  <div class="form-group">
    <label for="password">Password</label>
    <div class="input-wrap">
      <input type="password" id="password" name="password" placeholder="••••••••" required />
      <svg viewBox="0 0 24 24">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
    </div>
  </div>

</div>

        <div class="form-group">
          <label for="no_telepon">No. Telepon</label>
          <div class="input-wrap">
            <input type="text" id="no_telepon" name="no_telepon" placeholder="+62 8xx xxxx xxxx" />
            <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </div>
        </div>

        <div class="form-group">
          <label for="alamat">Alamat</label>
          <div class="input-wrap">
            <textarea id="alamat" name="alamat" placeholder="Jl. ..."></textarea>
            <svg style="top:14px;align-self:flex-start;" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </div>
        </div>

        <button type="submit" name="register" class="btn-primary">
          Register
        </button>

      </form>

      <p class="card-footer">already have an account? <a href="cust_login.php">Log In</a></p>
    </div>

  </div>

  <!-- ══════════════ SCRIPT ══════════════ -->
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