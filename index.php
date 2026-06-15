<?php
require_once 'includes/config.php';
startSecureSession();
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    if ($role === 'driver') header('Location: driver-dashboard.php');
    elseif ($role === 'admin') header('Location: admin-dashboard.php');
    else header('Location: passenger-dashboard.php');
    exit;
}
$error   = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MatatuTrack — Nairobi Live Transit</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  :root {
    --green: #00E676;
    --green-dark: #00C853;
    --amber: #FFB300;
    --red: #FF3D3D;
    --ink: #0A0F0D;
    --ink2: #141A16;
    --surface: #1A2218;
    --surface2: #212E22;
    --border: rgba(0,230,118,0.15);
    --text: #E8F5E9;
    --muted: #7A9B80;
    --card-bg: rgba(26,34,24,0.85);
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  html { scroll-behavior: smooth; }
  body {
    background: var(--ink);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* Background grid */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
      linear-gradient(rgba(0,230,118,0.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(0,230,118,0.04) 1px, transparent 1px);
    background-size: 48px 48px;
    pointer-events: none;
    z-index: 0;
  }

  /* Glow orbs */
  .orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(100px);
    opacity: 0.25;
    pointer-events: none;
    z-index: 0;
  }
  .orb-1 { width:600px; height:600px; background:#00E676; top:-200px; left:-100px; animation: float 12s ease-in-out infinite; }
  .orb-2 { width:400px; height:400px; background:#FFB300; bottom:-150px; right:-100px; animation: float 9s ease-in-out infinite reverse; }
  @keyframes float {
    0%, 100% { transform: translate(0,0); }
    50% { transform: translate(30px, -30px); }
  }

  /* HEADER */
  nav {
    position: fixed; top:0; left:0; right:0; z-index:100;
    padding: 1.25rem 3rem;
    display: flex; align-items:center; justify-content:space-between;
    background: rgba(10,15,13,0.8);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
  }
  .logo {
    display: flex; align-items:center; gap:0.75rem;
    text-decoration: none;
  }
  .logo-icon {
    width:40px; height:40px;
    background: var(--green);
    border-radius: 10px;
    display:grid; place-items:center;
    font-size:1.2rem;
    color: var(--ink);
  }
  .logo-text {
    font-family: 'Syne', sans-serif;
    font-weight:800;
    font-size:1.3rem;
    color: var(--text);
  }
  .logo-text span { color: var(--green); }
  .nav-links { display:flex; gap:1rem; align-items:center; }
  .nav-link {
    color: var(--muted); text-decoration:none;
    font-size:0.9rem; font-weight:500;
    padding:0.4rem 0.8rem; border-radius:6px;
    transition: all 0.2s;
  }
  .nav-link:hover { color:var(--text); background:var(--surface); }
  .btn-nav {
    background: var(--green); color: var(--ink);
    border:none; padding:0.5rem 1.25rem;
    border-radius:8px; font-weight:600;
    cursor:pointer; font-family:'DM Sans',sans-serif;
    transition:all 0.2s; text-decoration:none;
    font-size:0.9rem;
  }
  .btn-nav:hover { background: #33eb91; transform:translateY(-1px); }

  /* HERO */
  .hero {
    position: relative; z-index:1;
    min-height:100vh;
    display:grid;
    grid-template-columns:1fr 1fr;
    align-items:center;
    gap:4rem;
    padding: 8rem 5rem 4rem;
    max-width: 1400px;
    margin: 0 auto;
  }
  .hero-badge {
    display:inline-flex; align-items:center; gap:0.5rem;
    background: rgba(0,230,118,0.1);
    border: 1px solid var(--border);
    padding:0.35rem 0.9rem;
    border-radius:100px;
    font-size:0.8rem; color:var(--green);
    margin-bottom: 1.5rem;
    animation: fadeDown 0.6s ease forwards;
  }
  .hero-badge .dot {
    width:6px;height:6px;
    border-radius:50%;
    background:var(--green);
    animation: pulse 2s infinite;
  }
  @keyframes pulse {
    0%,100%{opacity:1;transform:scale(1);}
    50%{opacity:0.5;transform:scale(1.5);}
  }
  .hero-title {
    font-family: 'Syne', sans-serif;
    font-size: clamp(2.5rem, 5vw, 4.5rem);
    font-weight: 800;
    line-height: 1.05;
    letter-spacing: -0.02em;
    margin-bottom: 1.5rem;
    animation: fadeUp 0.7s 0.1s ease both;
  }
  .hero-title .accent { color: var(--green); display:block; }
  .hero-title .accent2 { color: var(--amber); }
  .hero-desc {
    font-size:1.1rem; line-height:1.7;
    color: var(--muted);
    max-width:520px;
    margin-bottom: 2.5rem;
    animation: fadeUp 0.7s 0.2s ease both;
  }
  .hero-actions {
    display:flex; gap:1rem; flex-wrap:wrap;
    animation: fadeUp 0.7s 0.3s ease both;
  }
  .btn-primary {
    background: var(--green);
    color: var(--ink);
    border:none;
    padding:0.9rem 2rem;
    border-radius:12px;
    font-family:'Syne',sans-serif;
    font-weight:700;
    font-size:1rem;
    cursor:pointer;
    transition:all 0.25s;
    display:flex; align-items:center; gap:0.5rem;
    text-decoration:none;
  }
  .btn-primary:hover { background:#33eb91; transform:translateY(-2px); box-shadow:0 8px 30px rgba(0,230,118,0.35); }
  .btn-ghost {
    background:transparent;
    color:var(--text);
    border:1px solid var(--border);
    padding:0.9rem 2rem;
    border-radius:12px;
    font-family:'DM Sans',sans-serif;
    font-weight:500;
    font-size:1rem;
    cursor:pointer;
    transition:all 0.25s;
    text-decoration:none;
    display:flex; align-items:center; gap:0.5rem;
  }
  .btn-ghost:hover { border-color: var(--green); color:var(--green); background:rgba(0,230,118,0.05); }

  /* Stats strip */
  .stats-strip {
    display:flex; gap:2rem; margin-top:3rem;
    padding-top:2rem; border-top:1px solid var(--border);
    animation: fadeUp 0.7s 0.4s ease both;
  }
  .stat-item { text-align:left; }
  .stat-num { font-family:'Syne',sans-serif; font-size:2rem; font-weight:800; color:var(--green); }
  .stat-label { font-size:0.8rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; }

  /* Hero Visual */
  .hero-visual {
    position:relative;
    animation: fadeLeft 0.8s 0.2s ease both;
  }
  .map-mockup {
    width:100%;
    aspect-ratio:4/3;
    background:var(--surface);
    border-radius:20px;
    border:1px solid var(--border);
    overflow:hidden;
    position:relative;
    box-shadow: 0 40px 80px rgba(0,0,0,0.5);
  }
  .map-mockup iframe, .map-inner {
    width:100%; height:100%;
    border:none;
  }
  /* Map overlay with live cards */
  .live-card {
    position:absolute;
    background: rgba(10,15,13,0.92);
    border:1px solid var(--border);
    border-radius:12px;
    padding:0.75rem 1rem;
    backdrop-filter:blur(10px);
    min-width:180px;
  }
  .live-card.top-left { top:1rem; left:1rem; }
  .live-card.bottom-right { bottom:1rem; right:1rem; }
  .lc-header { display:flex; align-items:center; gap:0.5rem; font-size:0.75rem; color:var(--muted); margin-bottom:0.5rem; }
  .lc-badge { background:rgba(0,230,118,0.15); color:var(--green); border-radius:4px; padding:0.1rem 0.4rem; font-size:0.7rem; font-weight:600; }
  .lc-route { font-family:'Syne',sans-serif; font-size:0.9rem; font-weight:700; }
  .lc-info { font-size:0.75rem; color:var(--muted); margin-top:0.25rem; }
  .lc-eta { color:var(--amber); font-weight:600; }
  /* Animated dots on map */
  .map-bg {
    background:
      radial-gradient(circle at 30% 40%, rgba(0,230,118,0.08) 0%, transparent 50%),
      radial-gradient(circle at 70% 70%, rgba(255,179,0,0.06) 0%, transparent 40%),
      var(--surface);
    width:100%; height:100%;
  }
  .map-road {
    position:absolute;
    background: rgba(0,230,118,0.12);
    border-radius:2px;
  }
  .matatu-dot {
    position:absolute;
    width:12px; height:12px;
    border-radius:50%;
    background:var(--green);
    box-shadow:0 0 0 4px rgba(0,230,118,0.2);
    animation: matatuMove 4s ease-in-out infinite;
  }
  @keyframes matatuMove {
    0%,100%{transform:translate(0,0);}
    33%{transform:translate(15px,-10px);}
    66%{transform:translate(-10px,8px);}
  }

  /* FEATURES */
  .features {
    position:relative;z-index:1;
    padding:6rem 5rem;
    max-width:1400px; margin:0 auto;
  }
  .section-label {
    font-size:0.8rem; color:var(--green); text-transform:uppercase;
    letter-spacing:0.1em; font-weight:600; margin-bottom:1rem;
  }
  .section-title {
    font-family:'Syne',sans-serif;
    font-size:clamp(2rem,3vw,3rem);
    font-weight:800; margin-bottom:1rem;
  }
  .section-sub { color:var(--muted); font-size:1rem; max-width:500px; line-height:1.6; margin-bottom:3rem; }
  .features-grid {
    display:grid; grid-template-columns:repeat(3,1fr); gap:1.5rem;
  }
  .feat-card {
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:16px;
    padding:2rem;
    transition:all 0.3s;
    position:relative;
    overflow:hidden;
  }
  .feat-card::before {
    content:'';
    position:absolute; top:0; left:0; right:0; height:2px;
    background:linear-gradient(90deg,transparent,var(--green),transparent);
    opacity:0; transition:opacity 0.3s;
  }
  .feat-card:hover { border-color:rgba(0,230,118,0.3); transform:translateY(-4px); }
  .feat-card:hover::before { opacity:1; }
  .feat-icon {
    width:48px; height:48px; border-radius:12px;
    background:rgba(0,230,118,0.12);
    display:grid; place-items:center;
    font-size:1.3rem; color:var(--green);
    margin-bottom:1.25rem;
  }
  .feat-title { font-family:'Syne',sans-serif; font-weight:700; font-size:1.1rem; margin-bottom:0.5rem; }
  .feat-desc { color:var(--muted); font-size:0.9rem; line-height:1.6; }

  /* AUTH MODAL */
  .modal-overlay {
    display:none;
    position:fixed; inset:0;
    background:rgba(0,0,0,0.7);
    backdrop-filter:blur(8px);
    z-index:500;
    align-items:center;
    justify-content:center;
    padding:1rem;
  }
  .modal-overlay.open { display:flex; }
  .modal {
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:24px;
    width:100%;
    max-width:460px;
    max-height:90vh;
    overflow-y:auto;
    padding:2.5rem;
    position:relative;
    animation: modalIn 0.3s ease;
  }
  @keyframes modalIn {
    from{transform:translateY(20px);opacity:0;}
    to{transform:translateY(0);opacity:1;}
  }
  .modal-close {
    position:absolute; top:1.25rem; right:1.25rem;
    background:var(--surface2); border:none;
    color:var(--muted); width:32px; height:32px;
    border-radius:8px; cursor:pointer;
    display:grid;place-items:center;
    transition:all 0.2s;
  }
  .modal-close:hover{background:var(--border);color:var(--text);}
  .modal-title {
    font-family:'Syne',sans-serif; font-weight:800;
    font-size:1.75rem; margin-bottom:0.35rem;
  }
  .modal-sub { color:var(--muted); font-size:0.9rem; margin-bottom:2rem; }

  /* Tabs */
  .tab-switcher {
    display:flex; gap:0.5rem;
    background:var(--ink2);
    border-radius:10px; padding:0.3rem;
    margin-bottom:2rem;
  }
  .tab-btn {
    flex:1; padding:0.6rem;
    border:none; border-radius:7px;
    font-family:'DM Sans',sans-serif;
    font-size:0.9rem; font-weight:500;
    cursor:pointer; transition:all 0.2s;
    background:transparent; color:var(--muted);
  }
  .tab-btn.active { background:var(--surface2); color:var(--text); }

  /* Form */
  .form-group { margin-bottom:1.25rem; }
  .form-label { font-size:0.85rem; color:var(--muted); margin-bottom:0.4rem; display:block; font-weight:500; }
  .form-input {
    width:100%;
    background:var(--ink2);
    border:1px solid var(--border);
    border-radius:10px;
    padding:0.75rem 1rem;
    color:var(--text);
    font-family:'DM Sans',sans-serif;
    font-size:0.95rem;
    transition:border-color 0.2s;
    outline:none;
  }
  .form-input:focus { border-color:var(--green); }
  .form-input::placeholder { color:var(--muted); }
  .form-row { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
  .role-selector { display:flex; gap:0.75rem; }
  .role-option {
    flex:1; display:flex; align-items:center; gap:0.6rem;
    padding:0.75rem 1rem;
    border:1px solid var(--border);
    border-radius:10px; cursor:pointer;
    transition:all 0.2s;
    font-size:0.9rem;
  }
  .role-option input[type=radio]{accent-color:var(--green);}
  .role-option:has(input:checked) {
    border-color:var(--green);
    background:rgba(0,230,118,0.08);
    color:var(--green);
  }
  .btn-submit {
    width:100%;
    background:var(--green);
    color:var(--ink);
    border:none;
    padding:0.9rem;
    border-radius:12px;
    font-family:'Syne',sans-serif;
    font-weight:700;
    font-size:1rem;
    cursor:pointer;
    transition:all 0.25s;
    margin-top:0.5rem;
  }
  .btn-submit:hover { background:#33eb91; transform:translateY(-1px); }
  .demo-hint {
    background:rgba(0,230,118,0.06);
    border:1px solid var(--border);
    border-radius:10px;
    padding:0.75rem 1rem;
    font-size:0.8rem;
    color:var(--muted);
    margin-top:1rem;
    line-height:1.5;
  }
  .demo-hint strong { color:var(--green); }

  /* Alert messages */
  .alert {
    padding:0.75rem 1rem;
    border-radius:10px;
    font-size:0.85rem;
    margin-bottom:1rem;
    display:flex; align-items:center; gap:0.5rem;
  }
  .alert-error { background:rgba(255,61,61,0.1); border:1px solid rgba(255,61,61,0.3); color:#ff6b6b; }
  .alert-success { background:rgba(0,230,118,0.1); border:1px solid rgba(0,230,118,0.3); color:var(--green); }

  /* FOOTER */
  footer {
    position:relative;z-index:1;
    text-align:center;
    padding:3rem;
    border-top:1px solid var(--border);
    color:var(--muted);
    font-size:0.85rem;
  }
  footer strong { color:var(--green); }

  /* Animations */
  @keyframes fadeUp {
    from{transform:translateY(20px);opacity:0;}
    to{transform:translateY(0);opacity:1;}
  }
  @keyframes fadeDown {
    from{transform:translateY(-10px);opacity:0;}
    to{transform:translateY(0);opacity:1;}
  }
  @keyframes fadeLeft {
    from{transform:translateX(30px);opacity:0;}
    to{transform:translateX(0);opacity:1;}
  }

  @media(max-width:900px){
    .hero{grid-template-columns:1fr;padding:6rem 2rem 3rem;}
    .features{padding:4rem 2rem;}
    .features-grid{grid-template-columns:1fr;}
    nav{padding:1rem 1.5rem;}
    .nav-links .nav-link{display:none;}
  }
</style>
</head>
<body>

<!-- Background Orbs -->
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<!-- Navigation -->
<nav>
  <a href="#" class="logo">
    <div class="logo-icon"><i class="fas fa-bus"></i></div>
    <span class="logo-text">Matatu<span>Track</span></span>
  </a>
  <div class="nav-links">
    <a href="#features" class="nav-link">Features</a>
    <a href="#routes" class="nav-link">Routes</a>
    <a href="#" class="nav-link">About</a>
    <button class="btn-nav" onclick="openModal('login')">Sign In</button>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">
      <span class="dot"></span>
      <span>Live Tracking Active — Nairobi Metro</span>
    </div>
    <h1 class="hero-title">
      Know Where Your
      <span class="accent">Matatu Is</span>
      Right <span class="accent2">Now.</span>
    </h1>
    <p class="hero-desc">
      Real-time GPS tracking for Nairobi's matatu network. Never wait blindly at a stage again.
      Track routes, check ETAs, and plan your commute smarter.
    </p>
    <div class="hero-actions">
      <button class="btn-primary" onclick="openModal('register')">
        <i class="fas fa-rocket"></i> Get Started Free
      </button>
      <button class="btn-ghost" onclick="openModal('login')">
        <i class="fas fa-sign-in-alt"></i> Sign In
      </button>
    </div>
    <div class="stats-strip">
      <div class="stat-item">
        <div class="stat-num" id="stat-matatus">47</div>
        <div class="stat-label">Active Matatus</div>
      </div>
      <div class="stat-item">
        <div class="stat-num">8</div>
        <div class="stat-label">Routes Covered</div>
      </div>
      <div class="stat-item">
        <div class="stat-num">22</div>
        <div class="stat-label">Stages Mapped</div>
      </div>
    </div>
  </div>

  <!-- Map Visual -->
  <div class="hero-visual">
    <div class="map-mockup">
      <div class="map-bg" style="position:relative;width:100%;height:100%">
        <!-- Roads -->
        <div class="map-road" style="width:80%;height:3px;top:45%;left:10%;transform:rotate(-8deg)"></div>
        <div class="map-road" style="width:3px;height:70%;top:15%;left:50%;transform:rotate(5deg)"></div>
        <div class="map-road" style="width:60%;height:3px;top:65%;left:20%;transform:rotate(3deg)"></div>
        <!-- Matatu dots -->
        <div class="matatu-dot" style="top:42%;left:25%;animation-delay:0s"></div>
        <div class="matatu-dot" style="top:38%;left:58%;background:var(--amber);box-shadow:0 0 0 4px rgba(255,179,0,0.2);animation-delay:1.5s"></div>
        <div class="matatu-dot" style="top:62%;left:40%;background:#2196F3;box-shadow:0 0 0 4px rgba(33,150,243,0.2);animation-delay:0.8s"></div>
        <div class="matatu-dot" style="top:28%;left:70%;background:#9C27B0;box-shadow:0 0 0 4px rgba(156,39,176,0.2);animation-delay:2s"></div>
        <!-- Route lines -->
        <svg style="position:absolute;inset:0;width:100%;height:100%" xmlns="http://www.w3.org/2000/svg">
          <polyline points="80,200 160,180 240,160 320,150 400,160" stroke="rgba(0,230,118,0.3)" stroke-width="2" fill="none" stroke-dasharray="6 4"/>
          <polyline points="300,80 290,150 280,230 300,290" stroke="rgba(255,179,0,0.3)" stroke-width="2" fill="none" stroke-dasharray="6 4"/>
        </svg>
      </div>
      <div class="live-card top-left">
        <div class="lc-header"><span class="lc-badge">LIVE</span> CBD – Rongai</div>
        <div class="lc-route">KDA 123A</div>
        <div class="lc-info">ETA Galleria: <span class="lc-eta">12 min</span></div>
        <div class="lc-info">8/14 seats • 45 km/h</div>
      </div>
      <div class="live-card bottom-right">
        <div class="lc-header"><i class="fas fa-route" style="color:var(--green)"></i> Route 58</div>
        <div class="lc-route">CBD → Githurai 45</div>
        <div class="lc-info">Next departure: <span class="lc-eta">3 min</span></div>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="features" id="features">
  <div class="section-label">Why MatatuTrack</div>
  <h2 class="section-title">Built for Nairobi's<br>Daily Commuters</h2>
  <p class="section-sub">From the chaotic CBD to Rongai, Githurai, and beyond — we make every journey predictable.</p>
  <div class="features-grid">
    <div class="feat-card">
      <div class="feat-icon"><i class="fas fa-map-location-dot"></i></div>
      <div class="feat-title">Live GPS Tracking</div>
      <div class="feat-desc">See every matatu's exact position update every few seconds. Route paths, speed, and direction all visible on the interactive map.</div>
    </div>
    <div class="feat-card">
      <div class="feat-icon"><i class="fas fa-clock"></i></div>
      <div class="feat-title">Real-Time ETAs</div>
      <div class="feat-desc">Intelligent arrival time estimates based on current traffic, matatu speed, and distance from your chosen stage.</div>
    </div>
    <div class="feat-card">
      <div class="feat-icon"><i class="fas fa-route"></i></div>
      <div class="feat-title">All Major Routes</div>
      <div class="feat-desc">Route 111, 23, 44, 58, 33 and more — stages, fares, and schedules for every major Nairobi corridor.</div>
    </div>
    <div class="feat-card">
      <div class="feat-icon"><i class="fas fa-users"></i></div>
      <div class="feat-title">Seat Availability</div>
      <div class="feat-desc">Know how full each matatu is before it arrives. Avoid standing in overcrowded vehicles during rush hour.</div>
    </div>
    <div class="feat-card">
      <div class="feat-icon"><i class="fas fa-bell"></i></div>
      <div class="feat-title">Smart Alerts</div>
      <div class="feat-desc">Get notified about breakdowns, traffic disruptions, route diversions, and matatus approaching your stage.</div>
    </div>
    <div class="feat-card">
      <div class="feat-icon"><i class="fas fa-chart-line"></i></div>
      <div class="feat-title">Driver Analytics</div>
      <div class="feat-desc">Drivers get trip history, performance scores, passenger stats, and route efficiency insights to improve service.</div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <p>MatatuTrack &copy; <?= date('Y') ?> — Transforming Urban Mobility in <strong>Nairobi</strong> &amp; across Kenya</p>
  <p style="margin-top:0.5rem;font-size:0.75rem;">Designed to reduce waiting times and improve public transport predictability</p>
</footer>

<!-- AUTH MODAL -->
<div class="modal-overlay" id="authModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
    <div id="loginForm">
      <div class="modal-title">Welcome back</div>
      <div class="modal-sub">Sign in as passenger or driver</div>

      <?php if ($error === 'invalid_credentials'): ?>
      <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> Invalid email/phone or password.</div>
      <?php elseif ($error): ?>
      <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> An error occurred. Please try again.</div>
      <?php endif; ?>
      <?php if ($success === 'registered'): ?>
      <div class="alert alert-success"><i class="fas fa-check-circle"></i> Account created! Please sign in.</div>
      <?php endif; ?>

      <div class="tab-switcher">
        <button class="tab-btn active" onclick="showTab('login')">Sign In</button>
        <button class="tab-btn" onclick="showTab('register')">Create Account</button>
      </div>

      <form action="auth.php" method="POST">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
          <label class="form-label">Email or Phone Number</label>
          <input type="text" name="identifier" class="form-input" placeholder="e.g. john@gmail.com or +2547..." required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-input" placeholder="Your password" required>
        </div>
        <button type="submit" class="btn-submit">Sign In <i class="fas fa-arrow-right"></i></button>
      </form>
<!--       <div class="demo-hint">
        <strong>Demo Accounts</strong><br>
        Passenger: <a href="/cdn-cgi/l/email-protection" class="__cf_email__" data-cfemail="197875707a7c3777737c6b70597e74787075377a7674">[email&#160;protected]</a> / password<br>
        Driver: <a href="/cdn-cgi/l/email-protection" class="__cf_email__" data-cfemail="2e4441464000454f434f5b6e49434f4742004d4143">[email&#160;protected]</a> / password<br>
        Admin: <a href="/cdn-cgi/l/email-protection" class="__cf_email__" data-cfemail="a5c4c1c8cccbe5c8c4d1c4d1d0d1d7c4c6ce8bc6ca8bcec0">[email&#160;protected]</a> / password
      </div> -->
    </div>

    <div id="registerForm" style="display:none">
      <div class="modal-title">Create Account</div>
      <div class="modal-sub">Join thousands of Nairobi commuters</div>
      <div class="tab-switcher">
        <button class="tab-btn" onclick="showTab('login')">Sign In</button>
        <button class="tab-btn active" onclick="showTab('register')">Create Account</button>
      </div>
      <form action="auth.php" method="POST">
        <input type="hidden" name="action" value="register">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-input" placeholder="e.g. John Kamau" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-input" placeholder="you@email.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-input" placeholder="+254 7XX XXX XXX" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">I am a</label>
          <div class="role-selector">
            <label class="role-option">
              <input type="radio" name="role" value="passenger" checked>
              <i class="fas fa-person"></i> Passenger
            </label>
            <label class="role-option">
              <input type="radio" name="role" value="driver">
              <i class="fas fa-steering-wheel"></i> Driver
            </label>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-input" placeholder="Min 8 chars" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm</label>
            <input type="password" name="confirm_password" class="form-input" placeholder="Repeat password" required>
          </div>
        </div>
        <button type="submit" class="btn-submit">Create Account <i class="fas fa-user-plus"></i></button>
      </form>
    </div>
  </div>
</div>

<script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script>
<script>
function openModal(tab = 'login') {
  document.getElementById('authModal').classList.add('open');
  showTab(tab);
}
function closeModal() {
  document.getElementById('authModal').classList.remove('open');
}
function showTab(tab) {
  document.getElementById('loginForm').style.display = tab === 'login' ? 'block' : 'none';
  document.getElementById('registerForm').style.display = tab === 'register' ? 'block' : 'none';
  document.querySelectorAll('.tab-btn').forEach((b,i) => {
    b.classList.toggle('active', (tab === 'login' && i === 0) || (tab === 'register' && i === 1));
  });
}
document.getElementById('authModal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeModal();
});
// Auto open modal if there's a redirect error/success
<?php if ($error || $success): ?>
window.addEventListener('DOMContentLoaded', () => openModal('<?= $success === "registered" ? "login" : "login" ?>'));
<?php endif; ?>

// Animate counter
function animateCounter(el, target) {
  let current = 0;
  const step = target / 40;
  const interval = setInterval(() => {
    current += step;
    if (current >= target) { el.textContent = target; clearInterval(interval); }
    else el.textContent = Math.floor(current);
  }, 30);
}
window.addEventListener('DOMContentLoaded', () => {
  animateCounter(document.getElementById('stat-matatus'), 47);
});
</script>
</body>
</html>