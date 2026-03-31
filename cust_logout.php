<?php
session_start();
include "config/koneksi.php";

if(isset($_POST['logout'])){
    $email      = $_POST['email'];
    $no_telepon = $_POST['no_telepon'];
    $password   = $_POST['password'];

    // Cari customer berdasarkan email dan no_telepon
    $query  = "SELECT * FROM customer WHERE email='$email' AND no_telepon='$no_telepon' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0){
        $row = mysqli_fetch_assoc($result);

        // Verifikasi password
        if(password_verify($password, $row['password'])){
            // Hancurkan sesi
            session_unset();
            session_destroy();

            echo "<script>
                    alert('logout successful.');
                    window.location='cust_login.php';
                  </script>";
            exit;
        } else {
            $error = "Password salah.";
        }
    } else {
        $error = "Email atau nomor telepon tidak ditemukan.";
    }
}
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Logout — CatDogKu</title>
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
      --danger:     #e53935;
      --danger-dk:  #b71c1c;
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
      --danger:     #ef5350;
      --danger-dk:  #c62828;
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
    .nav-logo img { height: 42px; width: auto; }
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
      width: 48px; height: 26px;
      background: var(--border);
      border-radius: 999px;
      transition: background var(--transition), border-color var(--transition);
      border: 2px solid var(--border);
      cursor: pointer;
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

    /* ─── Page wrapper ─── */
    .page-wrapper {
      margin-top: var(--nav-h);
      min-height: calc(100vh - var(--nav-h));
      position: relative;
      display: flex;
      align-items: center;
      justify-content: flex-start;
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

    /* ─── Logout Card ─── */
    .logout-card {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 480px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 24px;
      padding: 36px 32px;
      box-shadow: 0 24px 80px rgba(0,0,0,.25);
      animation: fadeUp .7s .22s both;
      transition: background var(--transition), border-color var(--transition);
    }

    .card-header { margin-bottom: 24px; }
    .card-label {
      font-size: .78rem;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: var(--danger);
      margin-bottom: 8px;
    }
    .card-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--text);
      line-height: 1.15;
    }
    .card-subtitle {
      margin-top: 8px;
      font-size: .88rem;
      color: var(--text-sub);
      line-height: 1.5;
    }

    /* ─── Alert error ─── */
    .alert-error {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 16px;
      background: rgba(229,57,53,.1);
      border: 1px solid rgba(229,57,53,.25);
      border-radius: 10px;
      color: var(--danger);
      font-size: .88rem;
      font-weight: 500;
      margin-bottom: 18px;
      animation: fadeUp .4s both;
    }
    .alert-error svg {
      width: 16px; height: 16px;
      stroke: var(--danger);
      fill: none;
      stroke-width: 2;
      flex-shrink: 0;
    }

    /* ─── Form ─── */
    .logout-form {
      display: flex;
      flex-direction: column;
      gap: 14px;
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
    .input-wrap svg.field-icon {
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
    .input-wrap:focus-within svg.field-icon { stroke: var(--accent); }
    .form-group input {
      width: 100%;
      background: var(--bg2);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 11px 14px 11px 42px;
      font-family: 'DM Sans', sans-serif;
      font-size: .95rem;
      color: var(--text);
      outline: none;
      transition: border-color .2s, background var(--transition), color var(--transition), box-shadow .2s;
    }
    .form-group input:focus {
      border-color: var(--accent);
      background: var(--surface);
      box-shadow: 0 0 0 3px rgba(76,175,80,.12);
    }

    /* ─── Password toggle ─── */
    .pw-toggle {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1;
    }
    .pw-toggle svg {
      width: 16px; height: 16px;
      stroke: var(--text-sub);
      fill: none;
      stroke-width: 1.8;
      pointer-events: none;
      transition: stroke var(--transition);
    }
    .pw-toggle:hover svg { stroke: var(--accent); }
    .pw-toggle .eye-off { display: none; }
    .pw-toggle.active .eye-on  { display: none; }
    .pw-toggle.active .eye-off { display: block; }

    /* ─── Divider ─── */
    .divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 4px 0;
      color: var(--text-sub);
      font-size: .78rem;
      letter-spacing: .06em;
      text-transform: uppercase;
    }
    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    /* ─── Logout button ─── */
    .btn-danger {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 14px 34px;
      background: var(--danger);
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
      margin-top: 4px;
      transition: background var(--transition), transform .2s, box-shadow .2s;
      box-shadow: 0 6px 20px rgba(229,57,53,.32);
    }
    .btn-danger:hover {
      background: var(--danger-dk);
      transform: translateY(-2px);
      box-shadow: 0 10px 28px rgba(229,57,53,.45);
    }
    .btn-danger svg {
      width: 16px; height: 16px;
      stroke: #fff;
      fill: none;
      stroke-width: 2;
      transition: transform .2s;
    }
    .btn-danger:hover svg { transform: translateX(3px); }

    .card-footer {
      margin-top: 20px;
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

    /* ─── Keyframes ─── */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(28px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ─── Responsive ─── */
    @media (max-width: 900px) {
      .page-wrapper {
        justify-content: center;
        padding: 48px 5%;
      }
    }
    @media (max-width: 560px) {
      .logout-card { padding: 32px 20px; }
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
    <div class="logout-card">

      <div class="card-header">
        <p class="card-label">LogOut Confirmation</p>
        <h1 class="card-title">Goodbye!</h1>
        <p class="card-subtitle">Verify your identity before logging out of your account.</p>
      </div>

      <?php if(isset($error)): ?>
      <div class="alert-error">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form class="logout-form" method="POST">

        <div class="form-group">
          <label for="email">Email</label>
          <div class="input-wrap">
            <input type="email" id="email" name="email"
                   placeholder="example@gmail.com"
                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                   required />
            <svg class="field-icon" viewBox="0 0 24 24">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
          </div>
        </div>

        <div class="form-group">
          <label for="no_telepon">Phone Number</label>
          <div class="input-wrap">
            <input type="text" id="no_telepon" name="no_telepon"
                   placeholder="+62 8xx xxxx xxxx"
                   maxlength="13"
                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                   value="<?= isset($_POST['no_telepon']) ? htmlspecialchars($_POST['no_telepon']) : '' ?>"
                   required />
            <svg class="field-icon" viewBox="0 0 24 24">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <input type="password" id="password" name="password" placeholder="••••••••" required />
            <svg class="field-icon" viewBox="0 0 24 24">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password">
              <svg class="eye-on" viewBox="0 0 24 24">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg class="eye-off" viewBox="0 0 24 24">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" name="logout" class="btn-danger">
          Logout
          <svg viewBox="0 0 24 24">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </button>

      </form>

      <p class="card-footer">Cancel? <a href="index.php">Back to Home</a></p>

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

    // Password toggle
    const pwToggle      = document.getElementById('pwToggle');
    const passwordInput = document.getElementById('password');
    pwToggle.addEventListener('click', function () {
      passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
      pwToggle.classList.toggle('active');
    });
  </script>
</body>
</html>