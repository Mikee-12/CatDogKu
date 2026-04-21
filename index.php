<?php
session_start();

if(!isset($_SESSION['customer_id'])){
    header("Location: cust_login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CatDogKu — Because Your Pets Deserve the Best</title>
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

    /* ─── Reset ─── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      transition: background var(--transition), color var(--transition);
      overflow-x: hidden;
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
      height: 40px;
      width: auto;
    }
    [data-theme="light"] .logo-dark  { display: none; }
    [data-theme="dark"]  .logo-light { display: none; }

    /* Desktop nav links */
    .nav-links {
      display: flex;
      align-items: center;
      gap: 32px;
      list-style: none;
      margin-left: 48px;
    }
    .nav-links a {
      font-size: .88rem;
      font-weight: 500;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: var(--text-sub);
      position: relative;
      transition: color var(--transition);
      white-space: nowrap;
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

    /* ─── Theme Toggle ─── */
    .theme-toggle {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      user-select: none;
    }
    .toggle-label {
      font-size: .75rem;
      font-weight: 600;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--text-sub);
      width: 28px;
      text-align: right;
    }
    .toggle-track {
      position: relative;
      width: 44px;
      height: 24px;
      background: var(--border);
      border-radius: 999px;
      border: 2px solid var(--border);
      cursor: pointer;
      transition: background var(--transition), border-color var(--transition);
    }
    .toggle-track.on {
      background: var(--accent);
      border-color: var(--accent);
    }
    .toggle-knob {
      position: absolute;
      top: 2px; left: 2px;
      width: 16px; height: 16px;
      border-radius: 50%;
      background: #fff;
      box-shadow: 0 1px 4px rgba(0,0,0,.2);
      transition: transform var(--transition);
    }
    .toggle-track.on .toggle-knob { transform: translateX(20px); }

    /* ─── Hamburger Button ─── */
    .hamburger {
      display: none;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      width: 40px;
      height: 40px;
      gap: 5px;
      cursor: pointer;
      background: none;
      border: none;
      padding: 4px;
      margin-left: 12px;
      border-radius: 8px;
      transition: background .2s;
    }
    .hamburger:hover { background: var(--border); }
    .hamburger span {
      display: block;
      width: 22px;
      height: 2px;
      background: var(--text);
      border-radius: 2px;
      transition: transform .3s, opacity .3s, background var(--transition);
    }
    .hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .hamburger.open span:nth-child(2) { opacity: 0; }
    .hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    /* ─── Mobile Menu Drawer ─── */
    .mobile-menu {
      display: none;
      position: fixed;
      top: var(--nav-h);
      left: 0; right: 0;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      box-shadow: 0 8px 32px rgba(0,0,0,.12);
      z-index: 1002;
      padding: 12px 0 20px;
      transform: translateY(-8px);
      opacity: 0;
      transition: transform .3s, opacity .3s, background var(--transition);
    }
    .mobile-menu.open {
      transform: translateY(0);
      opacity: 1;
    }
    .mobile-menu ul {
      list-style: none;
      padding: 0;
    }
    .mobile-menu ul li a {
      display: block;
      padding: 14px 24px;
      font-size: .95rem;
      font-weight: 500;
      letter-spacing: .03em;
      text-transform: uppercase;
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
      font-size: .8rem;
      color: var(--text-sub);
      font-weight: 500;
    }

    /* ─── Hero ─── */
    .hero {
      margin-top: var(--nav-h);
      position: relative;
      height: calc(100vh - var(--nav-h));
      min-height: 480px;
      overflow: hidden;
    }
    .hero-img {
      position: absolute;
      inset: 0;
      width: 100%; height: 100%;
      object-fit: cover;
      object-position: center 30%;
      filter: brightness(.55);
      transition: filter var(--transition);
    }
    [data-theme="dark"] .hero-img { filter: brightness(.38); }
    .hero-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(100deg, rgba(0,0,0,.6) 40%, transparent 78%);
    }
    .hero-content {
      position: relative;
      z-index: 2;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 0 6% 0 8%;
      max-width: 640px;
    }
    .hero-tag {
      display: inline-block;
      padding: 5px 14px;
      border: 1.5px solid var(--accent);
      border-radius: 999px;
      font-size: .75rem;
      font-weight: 600;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 18px;
      width: fit-content;
      animation: fadeUp .7s .1s both;
    }
    .hero-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2.4rem, 7vw, 5rem);
      font-weight: 900;
      color: #fff;
      line-height: 1.08;
      margin-bottom: 16px;
      animation: fadeUp .7s .22s both;
    }
    .hero-desc {
      font-size: clamp(.92rem, 2.5vw, 1.05rem);
      font-weight: 300;
      color: rgba(255,255,255,.82);
      line-height: 1.75;
      max-width: 400px;
      margin-bottom: 32px;
      animation: fadeUp .7s .34s both;
    }
    .hero-buttons {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      animation: fadeUp .7s .46s both;
    }
    .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 13px 28px;
      background: var(--accent);
      color: #fff;
      border-radius: var(--radius);
      font-size: .88rem;
      font-weight: 600;
      letter-spacing: .05em;
      text-transform: uppercase;
      border: none;
      cursor: pointer;
      width: fit-content;
      transition: background var(--transition), transform .2s, box-shadow .2s;
      box-shadow: 0 6px 20px rgba(76,175,80,.35);
      -webkit-tap-highlight-color: transparent;
    }
    .btn-primary:hover {
      background: var(--accent-dk);
      transform: translateY(-2px);
      box-shadow: 0 10px 28px rgba(76,175,80,.45);
    }
    .btn-primary:active { transform: scale(.97); }
    .btn-arrow { display: inline-block; transition: transform .2s; }
    .btn-primary:hover .btn-arrow { transform: translateX(4px); }

    /* ─── Section base ─── */
    section { padding: 80px 8%; }
    .section-tag {
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 12px;
    }
    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.8rem, 4vw, 3rem);
      font-weight: 700;
      line-height: 1.15;
      margin-bottom: 18px;
    }
    .section-desc {
      font-size: .95rem;
      color: var(--text-sub);
      max-width: 520px;
      line-height: 1.75;
    }

    /* ─── About ─── */
    #about {
      background: var(--bg2);
      transition: background var(--transition);
    }
    .about-inner {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 72px;
      align-items: center;
    }
    .about-visual { position: relative; }
    .about-img-placeholder {
      width: 100%;
      aspect-ratio: 4/3;
      background: var(--border);
      border-radius: 20px;
      overflow: hidden;
      transition: background var(--transition);
    }
    .about-img-placeholder img {
      width: 100%; height: 100%;
      object-fit: cover;
      border-radius: 20px;
    }
    .about-badge {
      position: absolute;
      bottom: -20px; right: -16px;
      background: var(--accent);
      color: #fff;
      border-radius: 14px;
      padding: 16px 20px;
      text-align: center;
      box-shadow: 0 8px 24px rgba(76,175,80,.3);
    }
    .about-badge strong { display: block; font-size: 1.8rem; font-weight: 700; line-height: 1; }
    .about-badge span   { font-size: .72rem; font-weight: 500; opacity: .9; }

    /* ─── Services ─── */
    #service {
      background: var(--bg);
      transition: background var(--transition);
    }
    .service-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      margin-top: 48px;
    }
    .service-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 32px 26px;
      position: relative;
      overflow: hidden;
      transition: border-color .3s, box-shadow .3s, transform .3s, background var(--transition);
    }
    .service-card::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, var(--accent-lt), transparent 70%);
      opacity: 0;
      transition: opacity .3s;
    }
    .service-card:hover {
      border-color: var(--accent);
      box-shadow: 0 20px 60px rgba(76,175,80,.2);
      transform: translateY(-6px) scale(1.02);
    }
    .service-card:hover::before { opacity: 1; }
    .service-icon {
      width: 48px; height: 48px;
      background: var(--accent-lt);
      border-radius: 13px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 20px;
      position: relative;
      transition: background .3s, transform .3s;
    }
    .service-icon svg {
      width: 24px; height: 24px;
      stroke: var(--accent);
      fill: none;
      stroke-width: 1.8;
      transition: stroke .3s;
    }
    .service-card:hover .service-icon { background: var(--accent); transform: scale(1.1) rotate(-6deg); }
    .service-card:hover .service-icon svg { stroke: #fff; }
    .service-name {
      font-family: 'Playfair Display', serif;
      font-size: 1.2rem;
      font-weight: 700;
      margin-bottom: 10px;
      color: var(--text);
      transition: color .3s;
      position: relative;
    }
    .service-text {
      font-size: .88rem;
      color: var(--text-sub);
      line-height: 1.7;
      position: relative;
      transition: color .3s;
    }
    .service-card:hover .service-text { color: var(--text); }

    /* ─── Contact ─── */
    #contact {
      background: var(--bg2);
      transition: background var(--transition);
    }
    .contact-inner {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 72px;
      align-items: start;
    }
    .contact-info { padding-top: 8px; }
    .contact-info .section-desc { margin-bottom: 36px; }
    .contact-detail {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      margin-bottom: 18px;
      font-size: .92rem;
      color: var(--text-sub);
      line-height: 1.6;
    }
    .contact-detail-icon {
      width: 38px; height: 38px;
      background: var(--accent-lt);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      margin-top: 2px;
      transition: background var(--transition);
    }
    .contact-detail-icon svg { width: 17px; height: 17px; stroke: var(--accent); fill: none; stroke-width: 1.8; }
    .contact-form { display: flex; flex-direction: column; gap: 16px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-group { display: flex; flex-direction: column; gap: 7px; }
    .form-group label {
      font-size: .76rem;
      font-weight: 600;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--text-sub);
    }
    .form-group input,
    .form-group textarea {
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 12px 15px;
      font-family: 'DM Sans', sans-serif;
      font-size: .95rem;
      color: var(--text);
      outline: none;
      -webkit-appearance: none;
      transition: border-color .2s, background var(--transition), color var(--transition);
      width: 100%;
    }
    .form-group input:focus,
    .form-group textarea:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(76,175,80,.12);
    }
    .form-group textarea { resize: vertical; min-height: 120px; }

    /* ─── Footer ─── */
    footer {
      text-align: center;
      padding: 28px 8%;
      border-top: 1px solid var(--border);
      font-size: .82rem;
      color: var(--text-sub);
      transition: border-color var(--transition), color var(--transition);
    }

    /* ─── Keyframes ─── */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ══════════════════════════════════
       RESPONSIVE BREAKPOINTS
    ══════════════════════════════════ */

    /* ─── Tablet (≤ 960px) ─── */
    @media (max-width: 960px) {
      .nav-links { gap: 20px; margin-left: 24px; }
      .about-inner,
      .contact-inner { grid-template-columns: 1fr; gap: 56px; }
      .service-grid { grid-template-columns: 1fr 1fr; }
      .form-row { grid-template-columns: 1fr 1fr; }
      .about-badge { bottom: -12px; right: 12px; }
    }

    /* ─── Tablet small (≤ 768px) ─── */
    @media (max-width: 768px) {
      :root { --nav-h: 60px; }
      section { padding: 64px 6%; }

      /* Hide desktop nav, show hamburger */
      .nav-links { display: none; }
      .hamburger { display: flex; }
      .mobile-menu { display: block; }

      .hero-content { padding: 0 6%; max-width: 100%; }
      .hero-desc { max-width: 100%; }

      .about-badge { bottom: -10px; right: 8px; }
      .about-badge strong { font-size: 1.5rem; }

      .form-row { grid-template-columns: 1fr; }
    }

    /* ─── Mobile (≤ 480px) ─── */
    @media (max-width: 480px) {
      :root { --nav-h: 56px; }
      section { padding: 56px 5%; }

      .navbar { padding: 0 5%; }

      .hero { min-height: 90svh; }
      .hero-content { padding: 0 5%; }
      .hero-title { font-size: clamp(2rem, 10vw, 3rem); }
      .hero-buttons { flex-direction: column; gap: 12px; width: 100%; }
      .hero-buttons .btn-primary { justify-content: center; width: 100%; padding: 15px 20px; font-size: .9rem; }

      .service-grid { grid-template-columns: 1fr; gap: 16px; }
      .service-card { padding: 26px 22px; }

      .contact-inner { gap: 40px; }

      .about-badge {
        position: static;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin-top: 16px;
        border-radius: 12px;
        padding: 12px 18px;
      }
      .about-badge strong { font-size: 1.4rem; }
      .about-badge span { font-size: .75rem; }

      .toggle-label { display: none; }

      footer { padding: 24px 5%; }
    }

    /* ─── Very small (≤ 360px) ─── */
    @media (max-width: 360px) {
      .hero-title { font-size: 1.9rem; }
      .section-title { font-size: 1.6rem; }
    }

    /* ─── Touch device improvements ─── */
    @media (hover: none) {
      .service-card:hover { transform: none; box-shadow: none; }
      .btn-primary:hover { transform: none; box-shadow: 0 6px 20px rgba(76,175,80,.35); }
    }
  </style>
</head>
<body>

  <!-- ══════════════ NAVBAR ══════════════ -->
  <nav class="navbar">
    <a href="#home" class="nav-logo">
      <img src="assets/logolm.png" alt="CatDogKu" class="logo-light" />
      <img src="assets/logodm.png" alt="CatDogKu" class="logo-dark" />
    </a>

    <!-- Desktop Links -->
    <ul class="nav-links">
      <li><a href="#home" class="active">Home</a></li>
      <li><a href="#about">About</a></li>
      <li><a href="#service">Service</a></li>
      <li><a href="reserve.php">Reserve</a></li>
      <li><a href="cust_profile.php">Profile</a></li>
      <li><a href="#contact">Contact</a></li>
    </ul>

    <div class="nav-spacer"></div>

    <!-- Theme Toggle -->
    <div class="theme-toggle" id="themeToggle" role="button" tabindex="0" aria-label="Toggle dark mode">
      <span class="toggle-label" id="toggleLabel">OFF</span>
      <div class="toggle-track" id="toggleTrack">
        <div class="toggle-knob"></div>
      </div>
    </div>

    <!-- Hamburger (mobile only) -->
    <button class="hamburger" id="hamburger" aria-label="Toggle navigation menu" aria-expanded="false">
      <span></span>
      <span></span>
      <span></span>
    </button>
  </nav>

  <!-- ══════════════ MOBILE MENU ══════════════ -->
  <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
    <ul>
      <li><a href="#home" class="active" onclick="closeMenu()">Home</a></li>
      <li><a href="#about" onclick="closeMenu()">About</a></li>
      <li><a href="#service" onclick="closeMenu()">Service</a></li>
      <li><a href="reserve.php" onclick="closeMenu()">Reserve</a></li>
      <li><a href="cust_profile.php" onclick="closeMenu()">Profile</a></li>
      <li><a href="#contact" onclick="closeMenu()">Contact</a></li>
    </ul>
    <div class="mobile-menu-footer">
      <span>Dark Mode</span>
      <div class="theme-toggle" id="themeToggleMobile" role="button" tabindex="0" aria-label="Toggle dark mode">
        <div class="toggle-track" id="toggleTrackMobile">
          <div class="toggle-knob"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════════ HERO ══════════════ -->
  <section id="home" class="hero">
    <img src="assets/heroo.jpg" alt="Hero" class="hero-img" />
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <span class="hero-tag">Welcome</span>
      <h1 class="hero-title">CatDogKu</h1>
      <p class="hero-desc">Because Your Pets Deserve the Best</p>
      <div class="hero-buttons">
        <a href="#service" class="btn-primary">Show More</a>
        <a href="reserve.php" class="btn-primary">Reserve Now <span class="btn-arrow">→</span></a>
      </div>
    </div>
  </section>

  <!-- ══════════════ ABOUT ══════════════ -->
  <section id="about">
    <div class="about-inner">
      <div class="about-visual">
        <div class="about-img-placeholder">
          <img src="assets/img1.jpg" alt="About CatDogKu" />
        </div>
        <div class="about-badge">
          <strong>10+</strong>
          <span>Years of Experience</span>
        </div>
      </div>
      <div>
        <p class="section-tag">About</p>
        <h2 class="section-title">We Care<br> for Your Pets Like Family</h2>
        <p class="section-desc">
          To become a trusted and leading pet care service that provides love, comfort, and the highest quality care for every cat and dog.
        </p>
      </div>
    </div>
  </section>

  <!-- ══════════════ SERVICE ══════════════ -->
  <section id="service">
    <p class="section-tag">SERVICE</p>
    <h2 class="section-title">What We Offer</h2>

    <div class="service-grid">
      <div class="service-card">
        <div class="service-icon">
          <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </div>
        <h3 class="service-name">Medical Check-Up</h3>
        <p class="service-text">Deskripsi singkat layanan pertama Anda. Jelaskan manfaatnya bagi pelanggan.</p>
      </div>

      <div class="service-card">
        <div class="service-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="6" width="20" height="5" rx="1"/>
            <line x1="5" y1="11" x2="5" y2="18"/>
            <line x1="8" y1="11" x2="8" y2="18"/>
            <line x1="11" y1="11" x2="11" y2="18"/>
            <line x1="14" y1="11" x2="14" y2="18"/>
            <line x1="17" y1="11" x2="17" y2="18"/>
            <line x1="20" y1="11" x2="20" y2="18"/>
          </svg>
        </div>
        <h3 class="service-name">Pet Grooming</h3>
        <p class="service-text">Deskripsi singkat layanan kedua Anda. Jelaskan manfaatnya bagi pelanggan.</p>
      </div>

      <div class="service-card">
        <div class="service-icon">
          <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <h3 class="service-name">Pet Boarding</h3>
        <p class="service-text">Deskripsi singkat layanan ketiga Anda. Jelaskan manfaatnya bagi pelanggan.</p>
      </div>
    </div>
  </section>

  <!-- ══════════════ CONTACT ══════════════ -->
  <section id="contact">
    <div class="contact-inner">
      <div class="contact-info">
        <p class="section-tag">Contact Us</p>
        <h2 class="section-title">Any Question</h2>
        <p class="section-desc">Contact us anytime. Our team is ready to serve you wholeheartedly.</p>

        <div class="contact-detail">
          <div class="contact-detail-icon">
            <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </div>
          +62 856 2431 2595
        </div>

        <div class="contact-detail">
          <div class="contact-detail-icon">
            <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </div>
          admin@catdogku.com
        </div>

        <div class="contact-detail">
          <div class="contact-detail-icon">
            <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </div>
          Jl. Kyai Mojo No.02, Ps. Kliwon, Kec. Ps. Kliwon, Kota Surakarta, Jawa Tengah.
        </div>
      </div>

      <div class="contact-form">
        <div class="form-row">
          <div class="form-group">
            <label>Name</label>
            <input type="text" id="wa-name" placeholder="Full Name" autocomplete="name" />
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" id="wa-email" placeholder="example@gmail.com" autocomplete="email" />
          </div>
        </div>
        <div class="form-group">
          <label>Subject</label>
          <input type="text" id="wa-subject" placeholder="Topic" />
        </div>
        <div class="form-group">
          <label>Message</label>
          <textarea id="wa-message" placeholder="Type a message ..."></textarea>
        </div>
        <button class="btn-primary" type="button" onclick="sendToWhatsApp()">
          Send to WhatsApp <span class="btn-arrow">→</span>
        </button>
      </div>
    </div>
  </section>

  <!-- ══════════════ FOOTER ══════════════ -->
  <footer>
    &copy; 2025 CatDogKu. All rights reserved.
  </footer>

  <!-- ══════════════ SCRIPT ══════════════ -->
  <script>
    /* ── Theme toggle (shared logic) ── */
    const html = document.documentElement;
    const track        = document.getElementById('toggleTrack');
    const label        = document.getElementById('toggleLabel');
    const trackMobile  = document.getElementById('toggleTrackMobile');

    function applyTheme(dark) {
      html.setAttribute('data-theme', dark ? 'dark' : 'light');
      track.classList.toggle('on', dark);
      if (trackMobile) trackMobile.classList.toggle('on', dark);
      if (label) label.textContent = dark ? 'ON' : 'OFF';
      localStorage.setItem('theme', dark ? 'dark' : 'light');
    }

    function toggleTheme() {
      applyTheme(html.getAttribute('data-theme') !== 'dark');
    }

    const saved = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(saved ? saved === 'dark' : prefersDark);

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
      hamburger.setAttribute('aria-expanded', menuOpen);
      mobileMenu.classList.toggle('open', menuOpen);
      mobileMenu.setAttribute('aria-hidden', !menuOpen);
    });

    /* Close menu when clicking outside */
    document.addEventListener('click', e => {
      if (menuOpen && !hamburger.contains(e.target) && !mobileMenu.contains(e.target)) {
        closeMenu();
      }
    });

    /* Close menu on ESC */
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && menuOpen) closeMenu();
    });

    /* ── Active nav highlight on scroll ── */
    const sections    = document.querySelectorAll('section[id]');
    const desktopLinks = document.querySelectorAll('.nav-links a');
    const mobileLinks  = document.querySelectorAll('.mobile-menu a');

    function setActive(id) {
      [...desktopLinks, ...mobileLinks].forEach(a => {
        const href = a.getAttribute('href');
        a.classList.toggle('active', href === `#${id}`);
      });
    }

    sections.forEach(s => {
      new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) setActive(e.target.id); });
      }, { threshold: .45 }).observe(s);
    });

    /* ── Send to WhatsApp ── */
    function sendToWhatsApp() {
      const name    = document.getElementById('wa-name').value.trim();
      const email   = document.getElementById('wa-email').value.trim();
      const subject = document.getElementById('wa-subject').value.trim();
      const message = document.getElementById('wa-message').value.trim();

      if (!name || !message) {
        alert('Please fill in your name and message.');
        return;
      }

      const phone = '6285624312595';
      const text = encodeURIComponent(
        `Halo CatDogKu! 🐾\n\n` +
        `*Nama:* ${name}\n` +
        `*Email:* ${email}\n` +
        `*Subjek:* ${subject}\n\n` +
        `*Pesan:*\n${message}`
      );

      window.open(`https://wa.me/${phone}?text=${text}`, '_blank');
    }
  </script>
</body>
</html>
