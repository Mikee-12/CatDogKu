    <?php
    include "config/koneksi.php";

    if(isset($_POST['reset'])){

        $email       = $_POST['email'];
        $no_telepon  = $_POST['no_telepon'];
        $new_password    = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if($new_password !== $confirm_password){
            echo "<script>alert('Password and Confirm Password do not match.');</script>";
        } else {

            // Verify email + phone match
            $check = mysqli_query($conn, "SELECT * FROM customer WHERE email='$email' AND no_telepon='$no_telepon'");

            if(mysqli_num_rows($check) > 0){
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update = mysqli_query($conn, "UPDATE customer SET password='$hashed' WHERE email='$email' AND no_telepon='$no_telepon'");

                if($update){
                    echo "<script>alert('Password reset successful! Please log in.'); window.location='cust_login.php';</script>";
                } else {
                    echo "<script>alert('Failed to reset password. Please try again.');</script>";
                }
            } else {
                echo "<script>alert('Email or phone number not found.');</script>";
            }

        }

    }
    ?>

    <!DOCTYPE html>
    <html lang="id" data-theme="light">
    <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password — CatDogKu</title>
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

        /* ─── Reset ─── */
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
        width: 48px;
        height: 26px;
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

        /* ─── Step indicator ─── */
        .steps {
        display: flex;
        align-items: center;
        gap: 0;
        margin-bottom: 26px;
        }
        .step {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        }
        .step-circle {
        width: 28px; height: 28px;
        border-radius: 50%;
        background: var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .75rem;
        font-weight: 700;
        color: var(--text-sub);
        flex-shrink: 0;
        transition: background .3s, color .3s;
        }
        .step.active .step-circle {
        background: var(--accent);
        color: #fff;
        }
        .step.done .step-circle {
        background: var(--accent-lt);
        color: var(--accent);
        }
        .step-label {
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--text-sub);
        white-space: nowrap;
        }
        .step.active .step-label { color: var(--accent); }
        .step-line {
        flex: 1;
        height: 1.5px;
        background: var(--border);
        margin: 0 8px;
        }

        /* ─── Form ─── */
        .reset-form {
        display: flex;
        flex-direction: column;
        gap: 14px;
        }
        .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
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
        padding: 11px 44px 11px 40px;
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

        /* password match states */
        .form-group input.pw-match   { border-color: var(--accent); }
        .form-group input.pw-nomatch { border-color: var(--danger); }
        .form-group input.pw-nomatch:focus { box-shadow: 0 0 0 3px rgba(229,57,53,.12); }
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

        /* ─── Divider ─── */
        .section-divider {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 4px 0;
        }
        .section-divider span {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--text-sub);
        white-space: nowrap;
        }
        .section-divider::before,
        .section-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
        }

        /* ─── Password toggle btn ─── */
        /* ─── Password toggle btn ─── */
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
  position: static;
  width: 16px;
  height: 16px;
  stroke: var(--text-sub);
  fill: none;
  stroke-width: 1.8;
  pointer-events: none;
  transition: stroke var(--transition);
}

.pw-toggle:hover svg {
  stroke: var(--accent);
}

.pw-toggle .eye-off {
  display: none;
}

.pw-toggle.active .eye-on {
  display: none;
}

.pw-toggle.active .eye-off {
  display: block;
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

        /* ─── Keyframes ─── */
        @keyframes fadeUp {
        from { opacity: 0; transform: translateY(28px); }
        to   { opacity: 1; transform: translateY(0); }
        }

        /* ─── Responsive ─── */
        @media (max-width: 560px) {
        .reset-card { padding: 32px 20px; }
        .form-row { grid-template-columns: 1fr; }
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
            <p class="card-label">Account Recovery</p>
            <p class="card-subtitle">Verify your identity, then set a new password.</p>
        </div>

        <form class="reset-form" method="POST">

            <!-- ── Identity verification ── -->

            <div class="form-row">
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

            <div class="form-group">
                <label for="no_telepon">Phone Number</label>
                <div class="input-wrap">
                <input type="text" id="no_telepon" name="no_telepon" placeholder="+62 8xx xxxx xxxx"
  maxlength="13" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required />
                <svg class="field-icon" viewBox="0 0 24 24">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                </div>
            </div>
            </div>

            <!-- ── New password ── -->

            <div class="form-group">
            <label for="new_password">New Password</label>
            <div class="input-wrap">
                <input type="password" id="new_password" name="new_password" placeholder="••••••••" required />
                <svg class="field-icon" viewBox="0 0 24 24">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                <button type="button" class="pw-toggle" id="pwToggle1" aria-label="Show password">
                <svg class="eye-on" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-off" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
            </div>

            <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <div class="input-wrap">
                <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required />
                <svg class="field-icon" viewBox="0 0 24 24">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                <button type="button" class="pw-toggle" id="pwToggle2" aria-label="Show confirm password">
                <svg class="eye-on" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-off" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
            <!-- live match hint -->
            <span class="match-hint ok" id="hintOk">✓ Passwords match</span>
            <span class="match-hint err" id="hintErr">✗ Passwords do not match</span>
            </div>

            <button type="submit" name="reset" class="btn-primary">
            Reset Password
            </button>

        </form>

        <p class="card-footer">Remembered your password? <a href="cust_login.php">Log In</a></p>

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

        /* ── Password visibility toggles ── */
        /* ── Password visibility toggles ── */
function makePwToggle(btnId, inputId) {
  const btn   = document.getElementById(btnId);
  const input = document.getElementById(inputId);
  btn.addEventListener('click', () => {
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.classList.toggle('active');
  });
}
makePwToggle('pwToggle1', 'new_password');
makePwToggle('pwToggle2', 'confirm_password');

        /* ── Live password match check ── */
        const newPw     = document.getElementById('new_password');
        const confirmPw = document.getElementById('confirm_password');
        const hintOk    = document.getElementById('hintOk');
        const hintErr   = document.getElementById('hintErr');

        function checkMatch() {
        const a = newPw.value;
        const b = confirmPw.value;
        if (!b) {
            confirmPw.classList.remove('pw-match', 'pw-nomatch');
            hintOk.classList.remove('show');
            hintErr.classList.remove('show');
            return;
        }
        const match = a === b;
        confirmPw.classList.toggle('pw-match',   match);
        confirmPw.classList.toggle('pw-nomatch', !match);
        hintOk.classList.toggle('show',  match);
        hintErr.classList.toggle('show', !match);
        }

        newPw.addEventListener('input', checkMatch);
        confirmPw.addEventListener('input', checkMatch);
    </script>
    </body>
    </html>