<?php
include "config/koneksi.php";
session_start();

if(!isset($_SESSION['customer_id'])){
    header("Location: cust_login.php");
    exit;
}

if(isset($_POST['change_phone'])){

    $email       = $_POST['email'];
    $new_phone   = $_POST['new_phone'];
    $confirm_phone = $_POST['confirm_phone'];

    if($new_phone !== $confirm_phone){
        echo "<script>alert('Phone number and Confirm Phone Number do not match.');</script>";
    } else {

        $id = $_SESSION['customer_id'];

        // Verify email matches the logged-in customer
        $check = mysqli_query($conn, "SELECT * FROM customer WHERE id_pelanggan='$id' AND email='$email'");

        if(mysqli_num_rows($check) > 0){

            // Check if new phone is already used by another account
            $phone_check = mysqli_query($conn, "SELECT * FROM customer WHERE no_telepon='$new_phone' AND id_pelanggan != '$id'");
            if(mysqli_num_rows($phone_check) > 0){
                echo "<script>alert('Phone number is already registered to another account.');</script>";
            } else {
                $update = mysqli_query($conn, "UPDATE customer SET no_telepon='$new_phone' WHERE id_pelanggan='$id' AND email='$email'");
                if($update){
                    echo "<script>alert('Phone number updated successfully!'); window.location='cust_profile.php';</script>";
                } else {
                    echo "<script>alert('Failed to update phone number. Please try again.');</script>";
                }
            }

        } else {
            echo "<script>alert('Email does not match your account.');</script>";
        }

    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Change Phone Number — CatDogKu</title>
  <link rel="icon" type="image/png" href="assets/icon.png">
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
      --danger-lt:  #ffebee;
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
      --danger-lt:  #2c1b1b;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      transition: background var(--transition), color var(--transition);
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

    /* ─── Background ─── */
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

    /* ─── Page wrapper ─── */
    .page-wrapper {
      margin-top: var(--nav-h);
      min-height: calc(100vh - var(--nav-h));
      position: relative;
      z-index: 2;
      display: flex;
      align-items: center;
      justify-content: flex-start;
      padding: 20px 8%;
    }

    /* ─── Card ─── */
    .reset-card {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 520px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 24px;
      padding: 36px 34px;
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
      color: var(--accent);
      margin-bottom: 8px;
    }
    .card-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--text);
      line-height: 1.15;
    }
    .card-subtitle {
      margin-top: 8px;
      font-size: .88rem;
      color: var(--text-sub);
      line-height: 1.6;
    }

    /* ─── Form ─── */
    .reset-form {
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
    .input-wrap > svg.field-icon {
      position: absolute;
      left: 14px;
      width: 16px; height: 16px;
      stroke: var(--text-sub);
      fill: none;
      stroke-width: 1.8;
      pointer-events: none;
      transition: stroke var(--transition);
    }
    .input-wrap:focus-within > svg.field-icon { stroke: var(--accent); }

    .form-group input {
      width: 100%;
      background: var(--bg2);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 11px 16px 11px 40px;
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

    /* phone match states */
    .form-group input.ph-match   { border-color: var(--accent); }
    .form-group input.ph-nomatch { border-color: var(--danger); }
    .form-group input.ph-nomatch:focus { box-shadow: 0 0 0 3px rgba(229,57,53,.12); }

    .match-hint {
      font-size: .75rem;
      font-weight: 500;
      display: none;
      gap: 4px;
      align-items: center;
    }
    .match-hint.show { display: flex; }
    .match-hint.ok  { color: var(--accent); }
    .match-hint.err { color: var(--danger); }

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
      margin-top: 4px;
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

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(28px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 560px) {
      .reset-card { padding: 32px 20px; }
      .page-wrapper { justify-content: center; padding: 20px 5%; }
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
    <div class="reset-card">

      <div class="card-header">
        <p class="card-label">Account Settings</p>
        <p class="card-subtitle">Verify your email, then enter your new phone number.</p>
      </div>

      <form class="reset-form" method="POST">

        <!-- ── Email verification ── -->
        <div class="form-group">
          <label for="email">Email</label>
          <div class="input-wrap">
            <input type="email" id="email" name="email" placeholder="example@gmail.com" required />
            <svg class="field-icon" viewBox="0 0 24 24">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
          </div>
        </div>

        <!-- ── New phone number ── -->
        <div class="form-group">
          <label for="new_phone">New Phone Number</label>
          <div class="input-wrap">
            <input type="text" id="new_phone" name="new_phone"
              placeholder="08xx xxxx xxxx"
              maxlength="13"
              oninput="this.value = this.value.replace(/[^0-9]/g, '')"
              required />
            <svg class="field-icon" viewBox="0 0 24 24">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
          </div>
        </div>

        <!-- ── Confirm phone number ── -->
        <div class="form-group">
          <label for="confirm_phone">Confirm Phone Number</label>
          <div class="input-wrap">
            <input type="text" id="confirm_phone" name="confirm_phone"
              placeholder="08xx xxxx xxxx"
              maxlength="13"
              oninput="this.value = this.value.replace(/[^0-9]/g, '')"
              required />
            <svg class="field-icon" viewBox="0 0 24 24">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
          </div>
          <!-- live match hint -->
          <span class="match-hint ok" id="hintOk">✓ Phone numbers match</span>
          <span class="match-hint err" id="hintErr">✗ Phone numbers do not match</span>
        </div>

        <button type="submit" name="change_phone" class="btn-primary">
          Save New Number
        </button>

      </form>

      <p class="card-footer">Changed your mind? <a href="cust_profile.php">Back to Profile</a></p>

    </div>
  </div>

  <!-- ══════════════ SCRIPT ══════════════ -->
  <script>
    /* ── Theme toggle ── */
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
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); applyTheme(html.getAttribute('data-theme') !== 'dark'); }
    });

    /* ── Live phone match check ── */
    const newPhone     = document.getElementById('new_phone');
    const confirmPhone = document.getElementById('confirm_phone');
    const hintOk       = document.getElementById('hintOk');
    const hintErr      = document.getElementById('hintErr');

    function checkMatch() {
      const a = newPhone.value;
      const b = confirmPhone.value;
      if (!b) {
        confirmPhone.classList.remove('ph-match', 'ph-nomatch');
        hintOk.classList.remove('show');
        hintErr.classList.remove('show');
        return;
      }
      const match = a === b;
      confirmPhone.classList.toggle('ph-match',   match);
      confirmPhone.classList.toggle('ph-nomatch', !match);
      hintOk.classList.toggle('show',  match);
      hintErr.classList.toggle('show', !match);
    }

    newPhone.addEventListener('input', checkMatch);
    confirmPhone.addEventListener('input', checkMatch);
  </script>
</body>
</html>