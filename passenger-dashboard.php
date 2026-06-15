<?php
require_once 'includes/config.php';
requireLogin();
$user = getCurrentUser();
if ($user['role'] !== 'passenger') {
    header('Location: ' . ($user['role'] === 'driver' ? 'driver-dashboard.php' : 'admin-dashboard.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>window.__userPhone = '<?= htmlspecialchars($_SESSION['user_phone'] ?? '') ?>';</script>
<title>MatatuTrack — Passenger Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
:root {
  --green:#00E676; --green-dark:#00C853; --amber:#FFB300; --red:#FF3D3D;
  --ink:#0A0F0D; --ink2:#141A16; --surface:#1A2218; --surface2:#212E22;
  --border:rgba(0,230,118,0.15); --text:#E8F5E9; --muted:#7A9B80;
  --sidebar:240px;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--ink);color:var(--text);font-family:'DM Sans',sans-serif;display:flex;min-height:100vh;}
.sidebar{width:var(--sidebar);min-width:var(--sidebar);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;height:100vh;position:sticky;top:0;overflow-y:auto;}
.sidebar-logo{display:flex;align-items:center;gap:0.75rem;padding:1.5rem;border-bottom:1px solid var(--border);text-decoration:none;}
.logo-icon{width:36px;height:36px;background:var(--green);border-radius:8px;display:grid;place-items:center;color:var(--ink);font-size:1rem;}
.logo-text{font-family:'Syne',sans-serif;font-weight:800;font-size:1.1rem;}
.logo-text span{color:var(--green);}
.sidebar-section{padding:1.25rem 0.75rem 0.5rem;font-size:0.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.1em;font-weight:600;}
.nav-item{display:flex;align-items:center;gap:0.75rem;padding:0.65rem 1rem;margin:0 0.5rem;border-radius:10px;cursor:pointer;color:var(--muted);font-size:0.9rem;font-weight:500;text-decoration:none;transition:all 0.2s;}
.nav-item:hover{background:var(--surface2);color:var(--text);}
.nav-item.active{background:rgba(0,230,118,0.12);color:var(--green);}
.nav-item i{width:18px;text-align:center;font-size:0.95rem;}
.nav-badge{margin-left:auto;background:var(--red);color:#fff;border-radius:100px;padding:0.1rem 0.45rem;font-size:0.7rem;font-weight:700;}
.sidebar-bottom{margin-top:auto;padding:1rem;border-top:1px solid var(--border);}
.user-chip{display:flex;align-items:center;gap:0.75rem;padding:0.75rem;background:var(--ink2);border-radius:12px;}
.user-avatar{width:36px;height:36px;border-radius:50%;background:rgba(0,230,118,0.2);display:grid;place-items:center;color:var(--green);font-size:0.9rem;}
.user-name{font-size:0.85rem;font-weight:600;}
.user-role{font-size:0.72rem;color:var(--muted);}
.main{flex:1;display:flex;flex-direction:column;overflow:hidden;}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;background:rgba(10,15,13,0.8);border-bottom:1px solid var(--border);backdrop-filter:blur(10px);position:sticky;top:0;z-index:50;}
.topbar-title{font-family:'Syne',sans-serif;font-weight:700;font-size:1.1rem;}
.topbar-sub{font-size:0.8rem;color:var(--muted);margin-top:0.1rem;}
.topbar-actions{display:flex;gap:0.75rem;align-items:center;}
.icon-btn{width:36px;height:36px;border-radius:9px;background:var(--surface2);border:1px solid var(--border);display:grid;place-items:center;color:var(--muted);cursor:pointer;transition:all 0.2s;position:relative;}
.icon-btn:hover{color:var(--text);border-color:rgba(0,230,118,0.4);}
.notif-dot{position:absolute;top:6px;right:6px;width:6px;height:6px;border-radius:50%;background:var(--red);}
.content{flex:1;display:grid;grid-template-columns:1fr 380px;overflow:hidden;height:calc(100vh - 65px);}
.map-container{position:relative;}
#liveMap{width:100%;height:100%;}
.live-indicator{position:absolute;top:1rem;right:1rem;z-index:400;background:rgba(10,15,13,0.85);border:1px solid var(--border);border-radius:100px;padding:0.4rem 0.9rem;display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;backdrop-filter:blur(8px);}
.live-dot{width:7px;height:7px;border-radius:50%;background:var(--green);animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:0.5;transform:scale(1.5);}}
.detail-panel{display:none;position:absolute;bottom:1.5rem;left:50%;transform:translateX(-50%);width:min(500px,90%);background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.25rem;z-index:500;backdrop-filter:blur(20px);box-shadow:0 20px 60px rgba(0,0,0,0.5);}
.detail-panel.open{display:block;animation:slideUp 0.3s ease;}
@keyframes slideUp{from{transform:translateX(-50%) translateY(20px);opacity:0;}to{transform:translateX(-50%) translateY(0);opacity:1;}}
.dp-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;}
.dp-title{font-family:'Syne',sans-serif;font-weight:800;font-size:1.1rem;}
.dp-close{background:var(--surface2);border:none;color:var(--muted);width:28px;height:28px;border-radius:7px;cursor:pointer;display:grid;place-items:center;}
.dp-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem;margin-bottom:1rem;}
.dp-stat{background:var(--ink2);border-radius:10px;padding:0.75rem;text-align:center;}
.dp-stat-val{font-family:'Syne',sans-serif;font-weight:800;font-size:1.2rem;color:var(--green);}
.dp-stat-label{font-size:0.72rem;color:var(--muted);margin-top:0.2rem;}
.panel{background:var(--surface);border-left:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;}
.panel-tabs{display:flex;border-bottom:1px solid var(--border);}
.panel-tab{flex:1;padding:0.9rem;text-align:center;font-size:0.82rem;font-weight:500;color:var(--muted);cursor:pointer;transition:all 0.2s;border-bottom:2px solid transparent;}
.panel-tab.active{color:var(--green);border-bottom-color:var(--green);}
.panel-content{flex:1;overflow-y:auto;padding:1rem;}
.panel-section-title{font-size:0.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.75rem;font-weight:600;}
.search-box{display:flex;align-items:center;gap:0.5rem;background:var(--ink2);border:1px solid var(--border);border-radius:10px;padding:0.6rem 1rem;margin-bottom:1rem;}
.search-box input{background:none;border:none;color:var(--text);font-family:'DM Sans',sans-serif;font-size:0.9rem;flex:1;outline:none;}
.search-box i{color:var(--muted);}
.matatu-card{background:var(--ink2);border:1px solid var(--border);border-radius:12px;padding:1rem;margin-bottom:0.75rem;cursor:pointer;transition:all 0.2s;}
.matatu-card:hover{border-color:rgba(0,230,118,0.4);transform:translateX(2px);}
.matatu-card.selected{border-color:var(--green);background:rgba(0,230,118,0.06);}
.mc-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:0.6rem;}
.mc-plate{font-family:'Syne',sans-serif;font-weight:700;font-size:0.95rem;}
.mc-status{padding:0.2rem 0.5rem;border-radius:6px;font-size:0.72rem;font-weight:600;}
.status-active{background:rgba(0,230,118,0.15);color:var(--green);}
.status-idle{background:rgba(255,179,0,0.15);color:var(--amber);}
.status-offline{background:rgba(255,61,61,0.15);color:var(--red);}
.mc-route{font-size:0.82rem;color:var(--muted);margin-bottom:0.5rem;}
.mc-route span{color:var(--text);font-weight:500;}
.mc-meta{display:flex;gap:0.75rem;flex-wrap:wrap;}
.mc-meta-item{display:flex;align-items:center;gap:0.3rem;font-size:0.78rem;color:var(--muted);}
.mc-meta-item i{color:var(--green);font-size:0.7rem;}
.mc-eta{color:var(--amber);font-weight:600;font-size:0.85rem;}
.cap-bar{margin-top:0.6rem;}
.cap-label{display:flex;justify-content:space-between;font-size:0.73rem;color:var(--muted);margin-bottom:0.25rem;}
.cap-track{background:var(--surface2);border-radius:100px;height:4px;overflow:hidden;}
.cap-fill{height:100%;border-radius:100px;background:var(--green);transition:width 0.5s ease;}
.cap-fill.busy{background:var(--amber);}
.cap-fill.full{background:var(--red);}
.route-card{background:var(--ink2);border:1px solid var(--border);border-radius:12px;padding:1rem;margin-bottom:0.75rem;cursor:pointer;transition:all 0.2s;}
.route-card:hover{border-color:rgba(0,230,118,0.4);}
.rc-num{display:inline-block;padding:0.2rem 0.6rem;border-radius:6px;font-family:'Syne',sans-serif;font-weight:800;font-size:0.85rem;color:#fff;margin-bottom:0.5rem;}
.rc-name{font-weight:600;font-size:0.9rem;margin-bottom:0.25rem;}
.rc-path{font-size:0.8rem;color:var(--muted);display:flex;align-items:center;gap:0.4rem;}
.rc-fare{margin-top:0.5rem;font-size:0.8rem;color:var(--amber);}
.alert-card{background:var(--ink2);border-radius:10px;padding:0.85rem;margin-bottom:0.6rem;border-left:3px solid var(--amber);}
.alert-card.info{border-left-color:var(--green);}
.alert-title{font-size:0.85rem;font-weight:600;margin-bottom:0.25rem;}
.alert-msg{font-size:0.78rem;color:var(--muted);line-height:1.5;}
.alert-time{font-size:0.72rem;color:var(--muted);margin-top:0.35rem;}
.notif-panel{display:none;position:absolute;top:65px;right:0;width:340px;background:var(--surface);border:1px solid var(--border);border-radius:0 0 16px 16px;z-index:200;box-shadow:0 20px 40px rgba(0,0,0,0.4);max-height:400px;overflow-y:auto;}
.notif-panel.open{display:block;}
.notif-header{padding:1rem;border-bottom:1px solid var(--border);font-family:'Syne',sans-serif;font-weight:700;}
.notif-item{padding:0.85rem 1rem;border-bottom:1px solid var(--border);cursor:pointer;transition:background 0.2s;}
.notif-item:hover{background:var(--surface2);}
.notif-item-title{font-size:0.85rem;font-weight:600;margin-bottom:0.2rem;}
.notif-item-msg{font-size:0.78rem;color:var(--muted);}
.section-page-full{display:none;flex:1;padding:2rem;overflow-y:auto;flex-direction:column;gap:1.5rem;}
.wallet-hero{background:linear-gradient(135deg,#0d2b1a 0%,#1a3a20 100%);border:1px solid var(--border);border-radius:20px;padding:2rem;position:relative;overflow:hidden;}
.wallet-hero::before{content:'';position:absolute;top:-40px;right:-40px;width:200px;height:200px;border-radius:50%;background:rgba(0,230,118,0.06);}
.wallet-balance-label{font-size:0.8rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.4rem;}
.wallet-balance{font-family:'Syne',sans-serif;font-weight:800;font-size:3rem;color:var(--green);line-height:1;}
.wallet-balance span{font-size:1.2rem;color:var(--muted);}
.wallet-stats{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1.5rem;}
.wallet-stat{background:rgba(255,255,255,0.04);border-radius:12px;padding:0.9rem 1rem;}
.wallet-stat-val{font-family:'Syne',sans-serif;font-weight:700;font-size:1.2rem;margin-bottom:0.2rem;}
.wallet-stat-lbl{font-size:0.75rem;color:var(--muted);}
.tx-list{display:flex;flex-direction:column;gap:0.5rem;}
.tx-row{display:flex;align-items:center;gap:0.85rem;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:0.85rem 1rem;}
.tx-icon{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;font-size:0.9rem;flex-shrink:0;}
.tx-icon.credit{background:rgba(0,230,118,0.12);color:var(--green);}
.tx-icon.debit{background:rgba(255,61,61,0.12);color:var(--red);}
.tx-desc{flex:1;}
.tx-title{font-size:0.88rem;font-weight:600;}
.tx-meta{font-size:0.75rem;color:var(--muted);margin-top:0.1rem;}
.tx-amount{font-family:'Syne',sans-serif;font-weight:700;font-size:0.95rem;}
.tx-amount.credit{color:var(--green);}
.tx-amount.debit{color:var(--red);}
.tx-balance{font-size:0.72rem;color:var(--muted);margin-top:0.1rem;text-align:right;}
.fare-chart-wrap{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;}
.fare-chart-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;}
.fare-chart-canvas{padding:1.25rem 1.5rem 1rem;}
.fare-route-pills{display:flex;flex-wrap:wrap;gap:0.5rem;padding:0 1.5rem 1.25rem;}
.fare-pill{padding:0.3rem 0.75rem;border-radius:100px;font-size:0.78rem;font-weight:600;cursor:pointer;border:2px solid transparent;transition:all 0.2s;opacity:0.55;}
.fare-pill.active{opacity:1;border-color:currentColor;}
.fare-now-band{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:0.75rem 1rem;display:flex;align-items:center;gap:0.75rem;font-size:0.85rem;}
.fare-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.75rem;}
.fare-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:1rem;position:relative;overflow:hidden;}
.fare-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--rc);}
.fare-card-route{font-size:0.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:0.25rem;}
.fare-card-amount{font-family:'Syne',sans-serif;font-weight:800;font-size:1.5rem;}
.fare-card-range{font-size:0.72rem;color:var(--muted);margin-top:0.2rem;}
.surge-badge{display:inline-flex;align-items:center;gap:0.3rem;font-size:0.72rem;font-weight:700;padding:0.2rem 0.5rem;border-radius:100px;margin-top:0.35rem;}
.surge-high{background:rgba(255,61,61,0.15);color:var(--red);}
.surge-mid{background:rgba(255,179,0,0.15);color:var(--amber);}
.surge-low{background:rgba(0,230,118,0.12);color:var(--green);}
.pay-btn{background:var(--green);color:var(--ink);border:none;padding:0.45rem 0.85rem;border-radius:8px;font-family:'Syne',sans-serif;font-weight:700;font-size:0.78rem;cursor:pointer;display:inline-flex;align-items:center;gap:0.35rem;transition:all 0.2s;margin-top:0.6rem;width:100%;justify-content:center;}
.pay-btn:hover{background:#33eb91;transform:scale(1.02);}
.pay-btn:disabled{opacity:0.45;cursor:not-allowed;transform:none;}
.pf-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.82);backdrop-filter:blur(12px);z-index:2000;align-items:center;justify-content:center;padding:1rem;}
.pf-overlay.open{display:flex;}
.pf-modal{background:var(--surface);border:1px solid var(--border);border-radius:24px;width:100%;max-width:400px;overflow:hidden;animation:pfIn 0.3s cubic-bezier(.34,1.56,.64,1);}
@keyframes pfIn{from{transform:translateY(20px) scale(0.97);opacity:0;}to{transform:translateY(0) scale(1);opacity:1;}}
.pf-header{padding:1.5rem 1.5rem 0.75rem;text-align:center;}
.pf-icon{width:60px;height:60px;border-radius:50%;background:rgba(0,230,118,0.12);display:grid;place-items:center;margin:0 auto 0.85rem;font-size:1.6rem;}
.pf-title{font-family:'Syne',sans-serif;font-weight:800;font-size:1.15rem;margin-bottom:0.25rem;}
.pf-sub{font-size:0.82rem;color:var(--muted);}
.pf-fare-display{margin:1rem 1.25rem 0.75rem;background:linear-gradient(135deg,#0d2b1a,#1a3a20);border:1px solid var(--border);border-radius:14px;padding:1.25rem;text-align:center;}
.pf-fare-amount{font-family:'Syne',sans-serif;font-weight:800;font-size:2.5rem;color:var(--green);line-height:1;}
.pf-fare-label{font-size:0.75rem;color:var(--muted);margin-top:0.25rem;}
.pf-fare-period{display:inline-flex;align-items:center;gap:0.3rem;margin-top:0.5rem;font-size:0.72rem;font-weight:700;padding:0.2rem 0.55rem;border-radius:100px;background:rgba(0,0,0,0.25);}
.pf-balance-row{display:flex;align-items:center;justify-content:space-between;margin:0 1.25rem 0.75rem;padding:0.8rem 1rem;background:var(--ink2);border-radius:11px;font-size:0.83rem;}
.pf-insufficient{margin:0 1.25rem 0.75rem;background:rgba(255,61,61,0.1);border:1px solid rgba(255,61,61,0.3);border-radius:10px;padding:0.8rem 1rem;font-size:0.82rem;color:var(--red);display:none;line-height:1.5;}
.pf-actions{padding:0.25rem 1.25rem 1.5rem;display:flex;gap:0.65rem;}
.pf-cancel{flex:1;background:var(--surface2);border:1px solid var(--border);color:var(--muted);padding:0.8rem;border-radius:11px;font-family:'DM Sans',sans-serif;font-size:0.88rem;cursor:pointer;transition:all 0.2s;}
.pf-cancel:hover{color:var(--text);}
.pf-confirm{flex:2;background:var(--green);color:var(--ink);border:none;padding:0.8rem;border-radius:11px;font-family:'Syne',sans-serif;font-weight:700;font-size:0.92rem;cursor:pointer;transition:all 0.2s;}
.pf-confirm:hover{background:#33eb91;}
.pf-confirm:disabled{opacity:0.45;cursor:not-allowed;}
.receipt-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.82);backdrop-filter:blur(12px);z-index:2100;align-items:center;justify-content:center;padding:1rem;}
.receipt-overlay.open{display:flex;}
.receipt-card{background:var(--surface);border:1px solid var(--border);border-radius:24px;width:100%;max-width:360px;overflow:hidden;animation:pfIn 0.35s cubic-bezier(.34,1.56,.64,1);}
.receipt-top{background:linear-gradient(135deg,#0d2b1a,#1a3a20);padding:1.75rem;text-align:center;}
.receipt-check{width:58px;height:58px;border-radius:50%;background:var(--green);display:grid;place-items:center;margin:0 auto 0.85rem;font-size:1.4rem;color:var(--ink);}
.receipt-title{font-family:'Syne',sans-serif;font-weight:800;font-size:1.15rem;margin-bottom:0.2rem;}
.receipt-ref{font-size:0.72rem;color:var(--muted);}
.receipt-body{padding:1rem 1.5rem;}
.receipt-row{display:flex;justify-content:space-between;align-items:center;padding:0.55rem 0;border-bottom:1px solid var(--border);font-size:0.83rem;}
.receipt-row:last-child{border-bottom:none;}
.receipt-row-label{color:var(--muted);}
.receipt-row-val{font-weight:600;}
.receipt-close{margin:0 1.5rem 1.5rem;width:calc(100% - 3rem);background:var(--green);color:var(--ink);border:none;padding:0.85rem;border-radius:11px;font-family:'Syne',sans-serif;font-weight:700;font-size:0.92rem;cursor:pointer;display:block;}
@media(max-width:1100px){.content{grid-template-columns:1fr;}.panel{display:none;}}
@media(max-width:768px){.sidebar{display:none;}.main{width:100%;}}
</style>
</head>
<body>

<aside class="sidebar">
  <a href="index.php" class="sidebar-logo">
    <div class="logo-icon"><i class="fas fa-bus"></i></div>
    <span class="logo-text">Matatu<span>Track</span></span>
  </a>
  <div class="sidebar-section">Navigation</div>
  <a href="#" class="nav-item active" id="nav-tracker"  onclick="switchSection('tracker');  return false;"><i class="fas fa-map-location-dot"></i> Live Tracker</a>
  <a href="#" class="nav-item"        id="nav-routes"   onclick="switchSection('routes');   return false;"><i class="fas fa-route"></i> Routes &amp; Stages</a>
  <a href="#" class="nav-item"        id="nav-alerts"   onclick="switchSection('alerts');   return false;"><i class="fas fa-bell"></i> Alerts<span class="nav-badge">2</span></a>
  <div class="sidebar-section">Account</div>
  <a href="#" class="nav-item"        id="nav-wallet"   onclick="switchSection('wallet');   return false;"><i class="fas fa-wallet"></i> My Wallet<span class="nav-badge" id="walletBadge" style="background:var(--green);display:none"></span></a>
  <a href="#" class="nav-item"        id="nav-fares"    onclick="switchSection('fares');    return false;"><i class="fas fa-chart-line"></i> Fare Prices</a>
  <a href="#" class="nav-item"        id="nav-saved"    onclick="switchSection('saved');    return false;"><i class="fas fa-heart"></i> Saved Routes</a>
  <a href="#" class="nav-item"        id="nav-feedback" onclick="switchSection('feedback'); return false;"><i class="fas fa-star"></i> Give Feedback</a>
  <a href="#" class="nav-item"        id="nav-settings" onclick="switchSection('settings'); return false;"><i class="fas fa-gear"></i> Settings</a>
  <div class="sidebar-bottom">
    <div class="user-chip">
      <div class="user-avatar"><i class="fas fa-user"></i></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="user-role">Passenger</div>
      </div>
    </div>
    <a href="auth.php?action=logout" style="display:flex;align-items:center;gap:0.5rem;color:var(--muted);text-decoration:none;font-size:0.82rem;margin-top:0.75rem;padding:0 0.25rem;">
      <i class="fas fa-sign-out-alt"></i> Sign Out
    </a>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <div>
      <div class="topbar-title">Live Matatu Tracker</div>
      <div class="topbar-sub" id="updateTime">Updating every 5 seconds...</div>
    </div>
    <div class="topbar-actions">
      <button class="icon-btn" onclick="locateMe()"><i class="fas fa-location-crosshairs"></i></button>
      <div class="icon-btn" onclick="toggleNotifPanel()" id="notifToggle" style="position:relative">
        <i class="fas fa-bell"></i><div class="notif-dot"></div>
      </div>
      <div class="notif-panel" id="notifPanel">
        <div class="notif-header">Notifications</div>
        <div id="notifList"></div>
      </div>
    </div>
  </div>

  <div class="content" id="mainContent">
    <div class="map-container">
      <div id="liveMap"></div>
      <div class="live-indicator">
        <div class="live-dot"></div><span>LIVE</span>
        <span id="matatuCount" style="color:var(--green);font-weight:600;">0</span>
        <span>matatus</span>
      </div>
      <div class="detail-panel" id="detailPanel">
        <div class="dp-header">
          <div><div class="dp-title" id="dpTitle">—</div><div style="font-size:0.8rem;color:var(--muted)" id="dpRoute">—</div></div>
          <button class="dp-close" onclick="closeDetail()"><i class="fas fa-times"></i></button>
        </div>
        <div class="dp-grid">
          <div class="dp-stat"><div class="dp-stat-val" id="dpSpeed">—</div><div class="dp-stat-label">km/h</div></div>
          <div class="dp-stat"><div class="dp-stat-val" id="dpPassengers">—</div><div class="dp-stat-label">Passengers</div></div>
          <div class="dp-stat"><div class="dp-stat-val" id="dpETA" style="color:var(--amber)">—</div><div class="dp-stat-label">ETA Next Stage</div></div>
        </div>
      </div>
    </div>
    <aside class="panel">
      <div class="panel-tabs">
        <div class="panel-tab active" onclick="showPanelTab('matatus')" id="tab-matatus">Matatus</div>
        <div class="panel-tab" onclick="showPanelTab('routes')"  id="tab-routes">Routes</div>
        <div class="panel-tab" onclick="showPanelTab('alerts')"  id="tab-alerts">Alerts</div>
      </div>
      <div class="panel-content" id="content-matatus">
        <div class="search-box"><i class="fas fa-search"></i><input type="text" placeholder="Search route or plate..." id="matatuSearch" oninput="filterMatatus()"></div>
        <div class="panel-section-title">Active Matatus</div>
        <div id="matatuList"><div style="color:var(--muted);font-size:0.85rem;text-align:center;padding:2rem">Loading...</div></div>
      </div>
      <div class="panel-content" id="content-routes" style="display:none">
        <div class="panel-section-title">All Routes</div>
        <div id="routeList"><div style="color:var(--muted);font-size:0.85rem;text-align:center;padding:2rem">Loading...</div></div>
      </div>
      <div class="panel-content" id="content-alerts" style="display:none">
        <div class="panel-section-title">Service Alerts</div>
        <div id="alertsList"></div>
      </div>
    </aside>
  </div>

  <!-- SAVED -->
  <div id="page-saved" class="section-page-full">
    <div><h2 style="font-family:'Syne',sans-serif;font-weight:800;margin-bottom:0.5rem">Saved Routes</h2><p style="color:var(--muted)">Your bookmarked routes for quick access.</p></div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:3rem;text-align:center;color:var(--muted)">
      <i class="fas fa-heart" style="font-size:2rem;margin-bottom:1rem;display:block;color:var(--red)"></i>
      <div style="font-weight:600;margin-bottom:0.5rem">No saved routes yet</div>
      <div style="font-size:0.85rem">Click the heart icon on any route to save it here.</div>
    </div>
  </div>

  <!-- FEEDBACK -->
  <div id="page-feedback" class="section-page-full">
    <div><h2 style="font-family:'Syne',sans-serif;font-weight:800;margin-bottom:0.5rem">Give Feedback</h2><p style="color:var(--muted)">Rate your recent matatu experience.</p></div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:2rem;max-width:560px">
      <form id="feedbackForm" onsubmit="submitFeedback(event)">
        <div style="margin-bottom:1.25rem">
          <label style="font-size:0.85rem;color:var(--muted);display:block;margin-bottom:0.5rem">Select Matatu</label>
          <select id="fb-matatu" style="width:100%;background:var(--ink2);border:1px solid var(--border);border-radius:10px;padding:0.75rem 1rem;color:var(--text);font-family:'DM Sans',sans-serif;font-size:0.9rem;outline:none">
            <option value="">-- Choose a matatu --</option>
            <option value="1">KDA 123A — Route 111 (CBD–Rongai)</option>
            <option value="2">KDB 456B — Route 23 (CBD–Eastleigh)</option>
            <option value="3">KDC 789C — Route 44 (Westlands–CBD)</option>
            <option value="4">KDD 012D — Route 58 (CBD–Githurai)</option>
          </select>
        </div>
        <div style="margin-bottom:1.25rem">
          <label style="font-size:0.85rem;color:var(--muted);display:block;margin-bottom:0.5rem">Rating</label>
          <div id="starRating" style="display:flex;gap:0.5rem;font-size:1.8rem">
            <?php for($i=1;$i<=5;$i++): ?><span onclick="setRating(<?=$i?>)" style="cursor:pointer;color:var(--muted);transition:color 0.2s">★</span><?php endfor; ?>
          </div>
          <input type="hidden" id="fb-rating" value="0">
        </div>
        <div style="margin-bottom:1.25rem">
          <label style="font-size:0.85rem;color:var(--muted);display:block;margin-bottom:0.5rem">Comment</label>
          <textarea id="fb-comment" rows="4" placeholder="Share your experience..." style="width:100%;background:var(--ink2);border:1px solid var(--border);border-radius:10px;padding:0.75rem 1rem;color:var(--text);font-family:'DM Sans',sans-serif;font-size:0.9rem;outline:none;resize:vertical"></textarea>
        </div>
        <button type="submit" style="background:var(--green);color:var(--ink);border:none;padding:0.85rem 2rem;border-radius:10px;font-family:'Syne',sans-serif;font-weight:700;font-size:0.95rem;cursor:pointer;width:100%"><i class="fas fa-paper-plane"></i> Submit Feedback</button>
        <div id="feedbackMsg" style="margin-top:1rem;display:none"></div>
      </form>
    </div>
  </div>

  <!-- SETTINGS -->
  <div id="page-settings" class="section-page-full">
    <div><h2 style="font-family:'Syne',sans-serif;font-weight:800;margin-bottom:0.5rem">Settings</h2><p style="color:var(--muted)">Manage your account preferences.</p></div>
    <div style="max-width:560px;display:flex;flex-direction:column;gap:1rem">
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.5rem">
        <div style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:1rem">Account Info</div>
        <div style="display:grid;gap:0.85rem">
          <div><label style="font-size:0.8rem;color:var(--muted);display:block;margin-bottom:0.35rem">Full Name</label><input type="text" value="<?= htmlspecialchars($user['name']) ?>" style="width:100%;background:var(--ink2);border:1px solid var(--border);border-radius:10px;padding:0.65rem 0.9rem;color:var(--text);font-family:'DM Sans',sans-serif;font-size:0.9rem;outline:none"></div>
          <div><label style="font-size:0.8rem;color:var(--muted);display:block;margin-bottom:0.35rem">Email</label><input type="email" value="<?= htmlspecialchars($user['email']) ?>" style="width:100%;background:var(--ink2);border:1px solid var(--border);border-radius:10px;padding:0.65rem 0.9rem;color:var(--text);font-family:'DM Sans',sans-serif;font-size:0.9rem;outline:none"></div>
          <div><label style="font-size:0.8rem;color:var(--muted);display:block;margin-bottom:0.35rem">Phone</label><input type="tel" value="<?= htmlspecialchars($user['phone']) ?>" style="width:100%;background:var(--ink2);border:1px solid var(--border);border-radius:10px;padding:0.65rem 0.9rem;color:var(--text);font-family:'DM Sans',sans-serif;font-size:0.9rem;outline:none"></div>
        </div>
        <button style="margin-top:1rem;background:var(--green);color:var(--ink);border:none;padding:0.65rem 1.5rem;border-radius:9px;font-family:'Syne',sans-serif;font-weight:700;cursor:pointer">Save Changes</button>
      </div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.5rem">
        <div style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:1rem">Notifications</div>
        <?php foreach(['Matatu approaching my stage','Service disruption alerts','New route announcements'] as $pref): ?>
        <label style="display:flex;align-items:center;justify-content:space-between;padding:0.6rem 0;border-bottom:1px solid var(--border);cursor:pointer"><span style="font-size:0.88rem"><?= $pref ?></span><input type="checkbox" checked style="accent-color:var(--green);width:16px;height:16px"></label>
        <?php endforeach; ?>
      </div>
      <div style="background:var(--surface);border:1px solid rgba(255,61,61,0.2);border-radius:16px;padding:1.5rem">
        <div style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:0.5rem;color:var(--red)">Danger Zone</div>
        <p style="font-size:0.85rem;color:var(--muted);margin-bottom:1rem">Permanently delete your account and all associated data.</p>
        <button onclick="alert('Contact admin to delete your account.')" style="background:rgba(255,61,61,0.15);color:var(--red);border:1px solid rgba(255,61,61,0.3);padding:0.65rem 1.5rem;border-radius:9px;cursor:pointer">Delete Account</button>
      </div>
    </div>
  </div>

  <!-- WALLET -->
  <div id="page-wallet" class="section-page-full">
    <div><h2 style="font-family:'Syne',sans-serif;font-weight:800;margin-bottom:0.25rem">My Wallet</h2><p style="color:var(--muted);font-size:0.88rem">Your MatatuTrack balance. Ask the admin to top up after sending M-PESA.</p></div>
    <div class="wallet-hero">
      <div class="wallet-balance-label">Available Balance</div>
      <div class="wallet-balance"><span>KES </span><span id="walletAmount">—</span></div>
      <div class="wallet-stats">
        <div class="wallet-stat"><div class="wallet-stat-val" id="walletTopped">—</div><div class="wallet-stat-lbl"><i class="fas fa-arrow-down" style="color:var(--green)"></i> Total Topped Up</div></div>
        <div class="wallet-stat"><div class="wallet-stat-val" id="walletSpent">—</div><div class="wallet-stat-lbl"><i class="fas fa-arrow-up" style="color:var(--red)"></i> Total Spent</div></div>
      </div>
    </div>
    <div>
      <div style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:0.75rem">How to Top Up</div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:1.25rem;font-size:0.88rem;line-height:1.7;color:var(--muted)">
<div class="topup-box" style="background:rgba(255,255,255,0.04);border-radius:14px;padding:1.4rem;margin-top:1.5rem;">
  <div style="font-weight:700;margin-bottom:1rem;font-size:0.95rem;">
    <i class="fas fa-mobile-alt" style="color:var(--green);margin-right:0.5rem;"></i>
    Top Up via M-PESA
  </div>

  <div style="display:flex;gap:0.8rem;flex-wrap:wrap;">
    <input id="topupPhone"  type="tel"    placeholder="Phone (07xx or 01xx)"
           style="flex:1;min-width:140px;background:rgba(255,255,255,0.07);border:1px solid var(--border);
                  border-radius:10px;padding:0.65rem 0.9rem;color:var(--text);font-size:0.9rem;"
           value="">
    <input id="topupAmount" type="number" placeholder="Amount (KES)"
           min="10" max="150000"
           style="flex:1;min-width:120px;background:rgba(255,255,255,0.07);border:1px solid var(--border);
                  border-radius:10px;padding:0.65rem 0.9rem;color:var(--text);font-size:0.9rem;">
    <button id="topupBtn" onclick="initiateTopUp()"
            style="background:var(--green);color:#000;border:none;border-radius:10px;
                   padding:0.65rem 1.4rem;font-weight:700;cursor:pointer;white-space:nowrap;">
      <i class="fas fa-paper-plane"></i> Send STK Push
    </button>
  </div>

  <div id="topupStatus" style="margin-top:0.9rem;display:none;font-size:0.88rem;padding:0.7rem 1rem;
       border-radius:10px;background:rgba(0,230,118,0.1);color:var(--green);">
  </div>
</div>

<script>
// ── Pre-fill phone from session if available ────────────────
(function() {
  const phoneInput = document.getElementById('topupPhone');
  if (phoneInput && !phoneInput.value) {
    // Try to read from the page's user context (set in PHP)
    phoneInput.value = window.__userPhone || '';
  }
})();

let _topupPollTimer = null;

async function initiateTopUp() {
  const phone  = document.getElementById('topupPhone').value.trim();
  const amount = parseInt(document.getElementById('topupAmount').value, 10);
  const btn    = document.getElementById('topupBtn');
  const status = document.getElementById('topupStatus');

  clearInterval(_topupPollTimer);

  // Basic client-side validation
  if (!phone || !/^(?:\+?254|0)[17]\d{8}$/.test(phone)) {
    showTopupStatus('Enter a valid Kenyan number (07xx or 01xx)', 'error');
    return;
  }
  if (!amount || amount < 10) {
    showTopupStatus('Minimum top-up is KES 10', 'error');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
  showTopupStatus('Sending STK Push to ' + phone + '…', 'info');

  try {
    const res  = await fetch('api/mpesa_stk.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ phone, amount }),
    });
    const data = await res.json();

    if (data.error) {
      showTopupStatus(data.message, 'error');
      resetTopupBtn();
      return;
    }

    // STK Push sent — start polling for status
    showTopupStatus(data.message, 'info');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Waiting…';

    const checkoutId = data.checkout_request_id;
    let   attempts   = 0;

    _topupPollTimer = setInterval(async () => {
      attempts++;
      if (attempts > 24) { // ~2 min timeout
        clearInterval(_topupPollTimer);
        showTopupStatus('Payment timed out. Please try again.', 'error');
        resetTopupBtn();
        return;
      }

      const r = await fetch(`api/mpesa_status.php?checkout_request_id=${encodeURIComponent(checkoutId)}`);
      const s = await r.json();

      if (s.status === 'complete') {
        clearInterval(_topupPollTimer);
        showTopupStatus('✅ ' + s.message, 'success');
        resetTopupBtn();
        // Refresh wallet display
        if (typeof loadWallet === 'function') loadWallet();
        if (typeof loadWalletHistory === 'function') loadWalletHistory();
        document.getElementById('topupAmount').value = '';
      } else if (s.status === 'failed') {
        clearInterval(_topupPollTimer);
        showTopupStatus('❌ ' + s.message, 'error');
        resetTopupBtn();
      }
      // else still pending — keep polling
    }, 5000); // poll every 5 seconds

  } catch (err) {
    showTopupStatus('Network error. Please try again.', 'error');
    resetTopupBtn();
  }
}

function showTopupStatus(msg, type) {
  const el = document.getElementById('topupStatus');
  el.style.display = 'block';
  el.textContent   = msg;
  el.style.background = type === 'error'   ? 'rgba(255,70,70,0.12)'  :
                        type === 'success'  ? 'rgba(0,230,118,0.12)'  :
                                             'rgba(100,150,255,0.10)';
  el.style.color      = type === 'error'   ? 'var(--red)'  :
                        type === 'success'  ? 'var(--green)' :
                                             '#8ab4f8';
}

function resetTopupBtn() {
  const btn = document.getElementById('topupBtn');
  btn.disabled  = false;
  btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send STK Push';
}
</script>
      </div>
    </div>
    <div>
      <div style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:0.75rem">Transaction History</div>
      <div class="tx-list" id="walletTxList"><div style="text-align:center;padding:2.5rem;color:var(--muted);font-size:0.88rem"><i class="fas fa-spinner fa-spin" style="margin-bottom:0.5rem;display:block;font-size:1.5rem"></i>Loading transactions...</div></div>
    </div>
  </div>

  <!-- FARES -->
  <div id="page-fares" class="section-page-full">
    <div><h2 style="font-family:'Syne',sans-serif;font-weight:800;margin-bottom:0.25rem">Fare Prices</h2><p style="color:var(--muted);font-size:0.88rem">Live fare fluctuations by time of day. Fares surge during peak hours.</p></div>
    <div>
      <div style="font-family:'Syne',sans-serif;font-weight:700;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.5rem"><i class="fas fa-clock" style="color:var(--green)"></i>Current Fares — <span id="fareCurrentHour" style="color:var(--amber)"></span></div>
      <div class="fare-cards" id="fareCardsGrid"><div style="color:var(--muted);font-size:0.85rem;padding:1rem"><i class="fas fa-spinner fa-spin"></i> Loading...</div></div>
    </div>
    <div class="fare-chart-wrap">
      <div class="fare-chart-header">
        <div><div style="font-family:'Syne',sans-serif;font-weight:700">Fare Fluctuation — 24 Hours</div><div style="font-size:0.78rem;color:var(--muted);margin-top:0.2rem">KES fare per route by hour of day</div></div>
        <div id="fareNowBand" class="fare-now-band" style="display:none"><i class="fas fa-circle" style="color:var(--green);font-size:0.5rem"></i><span id="fareNowText"></span></div>
      </div>
      <div class="fare-chart-canvas"><canvas id="fareChart" height="160"></canvas></div>
      <div class="fare-route-pills" id="fareRoutePills"></div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);font-family:'Syne',sans-serif;font-weight:700;font-size:0.95rem">Pricing Periods</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
        <?php
        $periods=[
          ['Off-Peak','12am–5am','–20% off base fare','#7A9B80','fa-moon'],
          ['Morning Peak','6am–10am','+50% surge','#FF5722','fa-sun'],
          ['Midday Normal','11am–3pm','Standard base fare','#00E676','fa-cloud-sun'],
          ['Afternoon Rise','4pm–6pm','+20–25% above base','#FFB300','fa-cloud'],
          ['Evening Peak','7pm–9pm','+60% peak surge','#FF3D3D','fa-traffic-light'],
          ['Night Discount','10pm–11pm','–10% night discount','#2196F3','fa-stars'],
        ];
        foreach($periods as $p): ?>
        <div style="padding:1rem 1.25rem;border-right:1px solid var(--border);border-bottom:1px solid var(--border)">
          <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem"><i class="fas <?=$p[4]?>" style="color:<?=$p[3]?>;font-size:0.9rem"></i><span style="font-weight:600;font-size:0.88rem"><?=$p[0]?></span></div>
          <div style="font-size:0.8rem;color:var(--muted)"><?=$p[1]?></div>
          <div style="font-size:0.78rem;color:<?=$p[3]?>;margin-top:0.25rem;font-weight:600"><?=$p[2]?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>

<!-- PAY FARE MODAL -->
<div class="pf-overlay" id="payFareOverlay">
  <div class="pf-modal">
    <div class="pf-header"><div class="pf-icon">🚐</div><div class="pf-title">Pay Fare</div><div class="pf-sub" id="pfSub">Loading...</div></div>
    <div class="pf-fare-display">
      <div class="pf-fare-amount" id="pfAmount">—</div>
      <div class="pf-fare-label">Current fare (dynamic pricing)</div>
      <div class="pf-fare-period" id="pfPeriod"></div>
    </div>
    <div class="pf-balance-row"><span style="color:var(--muted)">Your wallet balance</span><span id="pfCurrentBal" style="font-weight:600">KES —</span></div>
    <div class="pf-balance-row" style="margin-top:-0.4rem"><span style="color:var(--muted)">Balance after payment</span><span id="pfBalAfter" style="font-weight:700">KES —</span></div>
    <div class="pf-insufficient" id="pfInsufficient"><i class="fas fa-exclamation-triangle"></i> <strong>Insufficient balance.</strong> You need <span id="pfShortfall"></span> more.</div>
    <div class="pf-actions">
      <button class="pf-cancel" onclick="closePayFare()">Cancel</button>
      <button class="pf-confirm" id="pfConfirmBtn" onclick="confirmPayFare()"><i class="fas fa-check"></i> Confirm Payment</button>
    </div>
  </div>
</div>

<!-- RECEIPT -->
<div class="receipt-overlay" id="receiptOverlay">
  <div class="receipt-card">
    <div class="receipt-top"><div class="receipt-check"><i class="fas fa-check"></i></div><div class="receipt-title">Fare Paid!</div><div class="receipt-ref" id="receiptRef">REF: —</div></div>
    <div class="receipt-body">
      <div class="receipt-row"><span class="receipt-row-label">Matatu</span><span class="receipt-row-val" id="receiptPlate">—</span></div>
      <div class="receipt-row"><span class="receipt-row-label">Route</span><span class="receipt-row-val" id="receiptRoute">—</span></div>
      <div class="receipt-row"><span class="receipt-row-label">Amount Paid</span><span class="receipt-row-val" id="receiptAmount" style="color:var(--green)">KES —</span></div>
      <div class="receipt-row"><span class="receipt-row-label">Pricing Period</span><span class="receipt-row-val" id="receiptPeriod">—</span></div>
      <div class="receipt-row"><span class="receipt-row-label">New Balance</span><span class="receipt-row-val" id="receiptBalance">KES —</span></div>
      <div class="receipt-row"><span class="receipt-row-label">Time</span><span class="receipt-row-val" id="receiptTime">—</span></div>
    </div>
    <button class="receipt-close" onclick="closeReceipt()">Done</button>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// ============================================================
// UTILS
// ============================================================
function formatKES(n){const x=parseFloat(n);return isNaN(x)?'0.00':x.toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2});}
function safeNum(v,fb=0){const n=parseFloat(v);return isNaN(n)?fb:n;}
function formatHour(h){const s=h%12||12;return s+':00 '+(h<12?'AM':'PM');}
function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// ============================================================
// WALLET CACHE — fetched once on load, used everywhere
// This is the key fix: the modal reads from this cache instead
// of making a live fetch that can be blocked by session locking
// or a fares.php error in the same try/catch.
// ============================================================
let cachedBalance = null;

async function fetchAndCacheBalance() {
  try {
    const r = await fetch('api/wallet.php?action=balance');
    const d = await r.json();
    if (d.success) {
      cachedBalance = safeNum(d.data.balance);
      updateBalanceUI(cachedBalance, safeNum(d.data.total_topped), safeNum(d.data.total_spent));
    }
  } catch(e) {
    console.warn('Balance fetch failed:', e);
  }
}

function updateBalanceUI(bal, topped, spent) {
  // Sidebar badge
  const badge = document.getElementById('walletBadge');
  if (badge) { badge.textContent = 'KES '+formatKES(bal); badge.style.display = 'inline-block'; }
  // Wallet page
  const wa = document.getElementById('walletAmount');
  if (wa) wa.textContent = formatKES(bal);
  const wt = document.getElementById('walletTopped');
  if (wt && topped !== undefined) wt.textContent = 'KES '+formatKES(topped);
  const ws = document.getElementById('walletSpent');
  if (ws && spent !== undefined) ws.textContent = 'KES '+formatKES(spent);
}

// ============================================================
// MAP
// ============================================================
const map = L.map('liveMap',{center:[-1.2921,36.8219],zoom:13,zoomControl:false});
L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',{attribution:'&copy; OpenStreetMap &copy; CARTO',subdomains:'abcd',maxZoom:19}).addTo(map);
L.control.zoom({position:'bottomleft'}).addTo(map);
[{name:'Kencom',lat:-1.2841,lng:36.8230},{name:'Westlands',lat:-1.2672,lng:36.8067},{name:'Eastleigh',lat:-1.2744,lng:36.8478},{name:'Rongai',lat:-1.4275,lng:36.7453},{name:'Githurai 45',lat:-1.2178,lng:36.8956},{name:'Kawangware',lat:-1.2836,lng:36.7656}]
.forEach(s=>L.circleMarker([s.lat,s.lng],{radius:6,color:'#FFB300',fillColor:'#FFB300',fillOpacity:0.7,weight:2}).addTo(map).bindPopup(`<strong style="color:#FFB300">${s.name}</strong><br><small>Stage</small>`));

let allMatatus=[],matatuMarkers={},selectedMatatuId=null,userMarker=null;

function createMatatuIcon(color,status){return L.divIcon({className:'',html:`<div style="width:32px;height:32px;border-radius:50%;background:${color};border:3px solid rgba(255,255,255,0.8);display:flex;align-items:center;justify-content:center;font-size:14px;box-shadow:0 3px 12px rgba(0,0,0,0.4);opacity:${status==='idle'?0.6:1}">🚐</div>`,iconSize:[32,32],iconAnchor:[16,16]});}

async function fetchMatatus(){
  try{const r=await fetch('api/tracking.php?action=get_active');const d=await r.json();if(d.success){allMatatus=d.matatus;renderMatatuList(allMatatus);updateMapMarkers(allMatatus);document.getElementById('matatuCount').textContent=allMatatus.length;document.getElementById('updateTime').textContent='Last updated: '+new Date().toLocaleTimeString();}}
  catch(e){loadDemoData();}
}

function loadDemoData(){
  allMatatus=[
    {id:1,plate:'KDA 123A',route_id:1,route_number:'Route 111',route_name:'CBD – Rongai',      fare_min:70, fare_max:100,lat:-1.3317,lng:36.7877,speed:45.5,passengers:8, capacity:14,status:'active',color:'#FF5722',driver:'John Kamau',    current_stage:'Langata Road',eta_min:12},
    {id:2,plate:'KDB 456B',route_id:2,route_number:'Route 23', route_name:'CBD – Eastleigh',   fare_min:30, fare_max:50, lat:-1.2750,lng:36.8400,speed:22.0,passengers:25,capacity:33,status:'active',color:'#2196F3',driver:'Peter Mwangi',   current_stage:'Pangani',     eta_min:8},
    {id:3,plate:'KDC 789C',route_id:3,route_number:'Route 44', route_name:'Westlands – CBD',   fare_min:30, fare_max:50, lat:-1.2650,lng:36.8058,speed:35.0,passengers:6, capacity:14,status:'active',color:'#4CAF50',driver:'Grace Wanjiru',  current_stage:'Sarit Center',eta_min:15},
    {id:4,plate:'KDD 012D',route_id:4,route_number:'Route 58', route_name:'CBD – Githurai 45', fare_min:50, fare_max:80, lat:-1.2267,lng:36.8756,speed:50.0,passengers:11,capacity:14,status:'active',color:'#9C27B0',driver:'Samuel Odhiambo',current_stage:'Roysambu',    eta_min:20},
  ];
  renderMatatuList(allMatatus);updateMapMarkers(allMatatus);
  document.getElementById('matatuCount').textContent=allMatatus.length;
  document.getElementById('updateTime').textContent='Demo mode — '+new Date().toLocaleTimeString();
}

function renderMatatuList(matatus){
  const list=document.getElementById('matatuList');
  if(!matatus.length){list.innerHTML='<div style="color:var(--muted);text-align:center;padding:2rem;font-size:0.85rem">No active matatus found</div>';return;}
  list.innerHTML=matatus.map(m=>{
    const pct=Math.round(m.passengers/m.capacity*100);
    const cc=pct>90?'full':pct>70?'busy':'';
    const sc=m.status==='active'?'status-active':m.status==='idle'?'status-idle':'status-offline';
    return `<div class="matatu-card ${selectedMatatuId===m.id?'selected':''}" onclick="selectMatatu(${m.id})" id="mc-${m.id}">
      <div class="mc-header"><div class="mc-plate">${m.plate}</div><div class="mc-status ${sc}">${m.status.toUpperCase()}</div></div>
      <div class="mc-route">${m.route_number} — <span>${m.route_name}</span></div>
      <div class="mc-meta">
        <div class="mc-meta-item"><i class="fas fa-tachometer-alt"></i> ${m.speed} km/h</div>
        <div class="mc-meta-item"><i class="fas fa-users"></i> ${m.passengers}/${m.capacity}</div>
        <div class="mc-meta-item mc-eta"><i class="fas fa-clock"></i> ~${m.eta_min||'?'} min</div>
      </div>
      <div class="cap-bar">
        <div class="cap-label"><span>${m.current_stage||'En route'}</span><span>${pct}% full</span></div>
        <div class="cap-track"><div class="cap-fill ${cc}" style="width:${pct}%"></div></div>
      </div>
      <button class="pay-btn" onclick="openPayFare(${m.id},event)"><i class="fas fa-wallet"></i> Pay Fare</button>
    </div>`;
  }).join('');
}

function updateMapMarkers(matatus){
  matatus.forEach(m=>{
    const lat=parseFloat(m.lat||m.latitude),lng=parseFloat(m.lng||m.longitude);
    if(isNaN(lat)||isNaN(lng))return;
    if(matatuMarkers[m.id]){matatuMarkers[m.id].setLatLng([lat,lng]);return;}
    const mk=L.marker([lat,lng],{icon:createMatatuIcon(m.color||'#00E676',m.status)}).addTo(map);
    mk.bindPopup(`<div style="background:#1A2218;color:#E8F5E9;padding:0.75rem;border-radius:10px;min-width:180px;font-family:'DM Sans',sans-serif">
      <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;margin-bottom:0.2rem">${m.plate}</div>
      <div style="font-size:0.8rem;color:#7A9B80;margin-bottom:0.75rem">${m.route_number} — ${m.route_name}</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.75rem">
        <div style="background:#141A16;border-radius:8px;padding:0.5rem;text-align:center"><div style="font-size:1.1rem;font-weight:700;color:#00E676">${m.speed}</div><div style="font-size:0.7rem;color:#7A9B80">km/h</div></div>
        <div style="background:#141A16;border-radius:8px;padding:0.5rem;text-align:center"><div style="font-size:1.1rem;font-weight:700;color:#FFB300">${m.eta_min||'?'}m</div><div style="font-size:0.7rem;color:#7A9B80">ETA</div></div>
      </div>
      <button onclick="selectMatatu(${m.id})" style="width:100%;background:#00E676;color:#0A0F0D;border:none;padding:0.5rem;border-radius:8px;font-weight:700;cursor:pointer;font-size:0.85rem">View Details</button>
    </div>`,{className:'dark-popup'});
    mk.on('click',()=>selectMatatu(m.id));
    matatuMarkers[m.id]=mk;
  });
}

function selectMatatu(id){
  selectedMatatuId=id;const m=allMatatus.find(x=>x.id===id);if(!m)return;
  document.querySelectorAll('.matatu-card').forEach(c=>c.classList.remove('selected'));
  const card=document.getElementById(`mc-${id}`);if(card){card.classList.add('selected');card.scrollIntoView({behavior:'smooth',block:'nearest'});}
  const lat=parseFloat(m.lat||m.latitude),lng=parseFloat(m.lng||m.longitude);
  if(!isNaN(lat)){map.flyTo([lat,lng],15,{animate:true,duration:1});if(matatuMarkers[id])matatuMarkers[id].openPopup();}
  document.getElementById('dpTitle').textContent=m.plate;
  document.getElementById('dpRoute').textContent=`${m.route_number} | ${m.route_name}`;
  document.getElementById('dpSpeed').textContent=m.speed||0;
  document.getElementById('dpPassengers').textContent=`${m.passengers}/${m.capacity}`;
  document.getElementById('dpETA').textContent=(m.eta_min||'?')+'m';
  document.getElementById('detailPanel').classList.add('open');
}
function closeDetail(){document.getElementById('detailPanel').classList.remove('open');selectedMatatuId=null;document.querySelectorAll('.matatu-card').forEach(c=>c.classList.remove('selected'));}

async function fetchRoutes(){
  try{const r=await fetch('api/routes.php?action=list');const d=await r.json();if(d.success)renderRouteList(d.routes);}
  catch(e){renderRouteList([
    {id:1,route_number:'Route 111',route_name:'CBD – Rongai',      origin:'Kencom',   destination:'Rongai',      fare_min:70, fare_max:100,color_code:'#FF5722'},
    {id:2,route_number:'Route 23', route_name:'CBD – Eastleigh',   origin:'Kencom',   destination:'Eastleigh',   fare_min:30, fare_max:50, color_code:'#2196F3'},
    {id:3,route_number:'Route 44', route_name:'Westlands – CBD',   origin:'Westlands',destination:'Kencom',      fare_min:30, fare_max:50, color_code:'#4CAF50'},
    {id:4,route_number:'Route 58', route_name:'CBD – Githurai 45', origin:'Kencom',   destination:'Githurai 45', fare_min:50, fare_max:80, color_code:'#9C27B0'},
    {id:5,route_number:'Route 33', route_name:'CBD – Kawangware',  origin:'Kencom',   destination:'Kawangware',  fare_min:40, fare_max:60, color_code:'#FF9800'},
    {id:6,route_number:'Route 9',  route_name:'CBD – Ngong',       origin:'Archives', destination:'Ngong Town',  fare_min:60, fare_max:90, color_code:'#00BCD4'},
    {id:7,route_number:'Route 45', route_name:'CBD – Thika',       origin:'Kencom',   destination:'Thika Town',  fare_min:80, fare_max:120,color_code:'#F44336'},
    {id:8,route_number:'Route 14', route_name:'CBD – South B/C',   origin:'Kencom',   destination:'South C',     fare_min:35, fare_max:55, color_code:'#607D8B'},
  ]);}
}
function renderRouteList(routes){
  document.getElementById('routeList').innerHTML=routes.map(r=>`
    <div class="route-card" onclick="showRouteOnMap(${r.id})">
      <span class="rc-num" style="background:${r.color_code}">${r.route_number}</span>
      <div class="rc-name">${r.route_name}</div>
      <div class="rc-path"><i class="fas fa-circle" style="font-size:0.5rem;color:var(--green)"></i> ${r.origin} <i class="fas fa-arrow-right" style="font-size:0.7rem"></i> ${r.destination}</div>
      <div class="rc-fare"><i class="fas fa-money-bill-wave"></i> KES ${r.fare_min}–${r.fare_max}</div>
    </div>`).join('');
}

function loadAlerts(){
  const a=[
    {title:'Traffic Advisory',message:'Heavy traffic on Mombasa Road. Route 111 delays of 15–25 minutes.',type:'disruption',time:'2 hours ago'},
    {title:'Service Resumed',message:'Route 45 now operating normally after earlier breakdown near Roysambu.',type:'info',time:'4 hours ago'}
  ];
  document.getElementById('alertsList').innerHTML=a.map(x=>`<div class="alert-card ${x.type==='info'?'info':''}"><div class="alert-title">${x.title}</div><div class="alert-msg">${x.message}</div><div class="alert-time"><i class="fas fa-clock"></i> ${x.time}</div></div>`).join('');
  document.getElementById('notifList').innerHTML=a.map(x=>`<div class="notif-item"><div class="notif-item-title">${x.title}</div><div class="notif-item-msg">${x.message}</div></div>`).join('');
}

function showPanelTab(t){['matatus','routes','alerts'].forEach(x=>{document.getElementById(`content-${x}`).style.display=x===t?'block':'none';document.getElementById(`tab-${x}`)?.classList.toggle('active',x===t);});}
function filterMatatus(){const q=document.getElementById('matatuSearch').value.toLowerCase();renderMatatuList(allMatatus.filter(m=>m.plate?.toLowerCase().includes(q)||m.route_number?.toLowerCase().includes(q)||m.route_name?.toLowerCase().includes(q)));}
function toggleNotifPanel(){document.getElementById('notifPanel').classList.toggle('open');}
document.addEventListener('click',e=>{if(!e.target.closest('#notifToggle')&&!e.target.closest('#notifPanel'))document.getElementById('notifPanel').classList.remove('open');});
function locateMe(){if(!navigator.geolocation)return;navigator.geolocation.getCurrentPosition(p=>{const ll=[p.coords.latitude,p.coords.longitude];map.flyTo(ll,15);if(userMarker)userMarker.setLatLng(ll);else userMarker=L.circleMarker(ll,{radius:10,color:'#00E676',fillColor:'#00E676',fillOpacity:0.3,weight:2}).addTo(map).bindPopup('You are here');});}
function showRouteOnMap(id){switchSection('tracker');const m=allMatatus.find(x=>x.route_id===id)||allMatatus[0];if(m){const lat=parseFloat(m.lat||m.latitude);if(!isNaN(lat))map.flyTo([lat,parseFloat(m.lng||m.longitude)],13);}}

// ============================================================
// PAY FARE — reads cachedBalance, fetches fare independently
// ============================================================
let pfState={matatuId:null,routeId:null,routeNumber:null,routeName:null,plate:null,fare:0,period:'',periodColor:'',currentBalance:0};

async function openPayFare(matatuId, e) {
  e.stopPropagation();
  const m=allMatatus.find(x=>x.id===matatuId);if(!m)return;

  pfState.matatuId    = matatuId;
  pfState.routeId     = m.route_id ?? m.id;
  pfState.routeNumber = m.route_number;
  pfState.routeName   = m.route_name;
  pfState.plate       = m.plate;

  // Show modal immediately
  document.getElementById('pfSub').textContent       = `${m.plate} · ${m.route_number}`;
  document.getElementById('pfAmount').textContent    = '...';
  document.getElementById('pfCurrentBal').textContent= 'KES ...';
  document.getElementById('pfBalAfter').textContent  = 'KES ...';
  document.getElementById('pfInsufficient').style.display='none';
  document.getElementById('pfConfirmBtn').disabled   = true;
  document.getElementById('payFareOverlay').classList.add('open');

  // ── STEP 1: Use cached balance (already fetched on page load).
  // This is completely independent of the fare fetch — no shared
  // try/catch means a fares.php error can never zero out the balance.
  if (cachedBalance !== null) {
    pfState.currentBalance = cachedBalance;
  } else {
    // Cache miss (very first load, fetch failed) — try once more
    try {
      const br  = await fetch('api/wallet.php?action=balance');
      const bd  = await br.json();
      pfState.currentBalance = bd.success ? safeNum(bd.data.balance) : 0;
      if (bd.success) cachedBalance = pfState.currentBalance;
    } catch(e) {
      pfState.currentBalance = 0;
    }
  }

  // ── STEP 2: Fetch fare in its own independent try/catch.
  // An error here CANNOT affect pfState.currentBalance.
  try {
    const fr   = await fetch(`api/fares.php?action=current&route_id=${pfState.routeId}`);
    const text = await fr.text();
    const fd   = JSON.parse(text); // throws if PHP returned an error page
    pfState.fare        = fd.success && fd.fare != null ? safeNum(fd.fare, 50) : Math.round((safeNum(m.fare_min,40)+safeNum(m.fare_max,80))/2);
    pfState.period      = fd.period_label || 'Standard';
    pfState.periodColor = fd.period_color || '#00E676';
  } catch(e) {
    // fares.php failed — use route midpoint as fallback fare
    pfState.fare        = Math.round((safeNum(m.fare_min,40)+safeNum(m.fare_max,80))/2);
    pfState.period      = 'Standard';
    pfState.periodColor = '#00E676';
  }

  // ── STEP 3: Render modal with both values now correctly set
  const balAfter = pfState.currentBalance - pfState.fare;
  const canPay   = pfState.currentBalance >= pfState.fare;

  document.getElementById('pfAmount').textContent     = 'KES '+formatKES(pfState.fare);
  document.getElementById('pfCurrentBal').textContent = 'KES '+formatKES(pfState.currentBalance);
  document.getElementById('pfBalAfter').textContent   = balAfter>=0 ? 'KES '+formatKES(balAfter) : '− KES '+formatKES(Math.abs(balAfter));
  document.getElementById('pfBalAfter').style.color   = canPay?'var(--green)':'var(--red)';
  document.getElementById('pfPeriod').textContent     = pfState.period;
  document.getElementById('pfPeriod').style.color     = pfState.periodColor;

  if (!canPay) {
    document.getElementById('pfInsufficient').style.display='block';
    document.getElementById('pfShortfall').textContent='KES '+formatKES(pfState.fare-pfState.currentBalance);
    document.getElementById('pfConfirmBtn').disabled=true;
  } else {
    document.getElementById('pfInsufficient').style.display='none';
    document.getElementById('pfConfirmBtn').disabled=false;
  }
}

function closePayFare(){document.getElementById('payFareOverlay').classList.remove('open');}

async function confirmPayFare(){
  const btn=document.getElementById('pfConfirmBtn');
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Processing...';
  try{
    const r=await fetch('api/wallet.php?action=debit',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'debit',amount:pfState.fare,description:`Fare: ${pfState.routeNumber} (${pfState.routeName}) · ${pfState.plate}`,reference:`FARE-${pfState.matatuId}-${Date.now()}`})});
    const d=await r.json();
    if(d.success){
      cachedBalance = safeNum(d.new_balance); // update cache after payment
      closePayFare();showReceipt(d.new_balance);updateBalanceUI(cachedBalance);
    }else{
      document.getElementById('pfInsufficient').style.display='block';
      document.getElementById('pfInsufficient').innerHTML=`<i class="fas fa-exclamation-triangle"></i> ${d.message||'Payment failed. Please try again.'}`;
      btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i> Confirm Payment';
    }
  }catch(err){
    document.getElementById('pfInsufficient').style.display='block';
    document.getElementById('pfInsufficient').innerHTML='<i class="fas fa-exclamation-triangle"></i> Could not connect. Please try again.';
    btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i> Confirm Payment';
  }
}

function showReceipt(nb){
  const ref='MT'+Date.now().toString().slice(-8).toUpperCase();
  document.getElementById('receiptRef').textContent    ='REF: '+ref;
  document.getElementById('receiptPlate').textContent  =pfState.plate;
  document.getElementById('receiptRoute').textContent  =`${pfState.routeNumber} · ${pfState.routeName}`;
  document.getElementById('receiptAmount').textContent ='KES '+formatKES(pfState.fare);
  document.getElementById('receiptPeriod').textContent =pfState.period;
  document.getElementById('receiptBalance').textContent='KES '+formatKES(safeNum(nb));
  document.getElementById('receiptTime').textContent   =new Date().toLocaleString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
  document.getElementById('receiptOverlay').classList.add('open');
}
function closeReceipt(){
  document.getElementById('receiptOverlay').classList.remove('open');
  const btn=document.getElementById('pfConfirmBtn');btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i> Confirm Payment';
}

document.getElementById('payFareOverlay').addEventListener('click',e=>{if(e.target===document.getElementById('payFareOverlay'))closePayFare();});
document.getElementById('receiptOverlay').addEventListener('click',e=>{if(e.target===document.getElementById('receiptOverlay'))closeReceipt();});

// ============================================================
// WALLET PAGE
// ============================================================
async function loadWallet(){
  await fetchAndCacheBalance();
  // totals already updated inside fetchAndCacheBalance → updateBalanceUI
}

async function loadWalletHistory(){
  const list=document.getElementById('walletTxList');
  try{
    const r=await fetch('api/wallet.php?action=history&limit=30');const d=await r.json();
    if(!d.success||!d.data.length){list.innerHTML=`<div style="text-align:center;padding:2.5rem;color:var(--muted);font-size:0.88rem"><i class="fas fa-receipt" style="font-size:2rem;margin-bottom:0.75rem;display:block;opacity:0.4"></i>No transactions yet.</div>`;return;}
    list.innerHTML=d.data.map(tx=>{
      const isC=tx.type==='credit';
      const date=new Date(tx.created_at).toLocaleString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
      return `<div class="tx-row">
        <div class="tx-icon ${tx.type}"><i class="fas fa-${isC?'arrow-down':'arrow-up'}"></i></div>
        <div class="tx-desc"><div class="tx-title">${escH(tx.description)}</div><div class="tx-meta">${date}${tx.mpesa_code?' · <strong style="color:var(--green)">'+escH(tx.mpesa_code)+'</strong>':''}${tx.performed_by_name?' · by '+escH(tx.performed_by_name):''}</div></div>
        <div style="text-align:right;flex-shrink:0"><div class="tx-amount ${tx.type}">${isC?'+':'−'}KES ${formatKES(safeNum(tx.amount))}</div><div class="tx-balance">Bal: KES ${formatKES(safeNum(tx.balance_after))}</div></div>
      </div>`;
    }).join('');
  }catch(e){list.innerHTML='<div style="text-align:center;padding:2rem;color:var(--muted)">Could not load transactions.</div>';}
}

// ============================================================
// FARE CHART
// ============================================================
let fareChartInst=null,fareData=null,activeRoutes=new Set();

async function loadFareChart(){
  document.getElementById('fareCardsGrid').innerHTML='<div style="color:var(--muted);font-size:0.85rem;padding:1rem"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
  try{
    const r=await fetch('api/fares.php?action=chart_data');
    const text=await r.text();
    let d;
    try{d=JSON.parse(text);}catch(pe){
      console.error('fares chart_data non-JSON:',text.substring(0,300));
      document.getElementById('fareCardsGrid').innerHTML='<div style="color:var(--red);font-size:0.85rem;padding:1rem"><i class="fas fa-exclamation-triangle"></i> Could not load fares — check browser console for PHP error.</div>';
      return;
    }
    if(!d.success){document.getElementById('fareCardsGrid').innerHTML=`<div style="color:var(--red);font-size:0.85rem;padding:1rem"><i class="fas fa-exclamation-triangle"></i> ${escH(d.message||'Error')}</div>`;return;}
    fareData=d.data;fareData.datasets.forEach(ds=>activeRoutes.add(ds.route_id));
    renderFareCurrentCards();renderFareChart();renderFareRoutePills();updateFareNowBand();
  }catch(e){document.getElementById('fareCardsGrid').innerHTML='<div style="color:var(--red);font-size:0.85rem;padding:1rem"><i class="fas fa-exclamation-triangle"></i> Network error loading fares.</div>';}
}

function renderFareCurrentCards(){
  const now=fareData.current_hour;document.getElementById('fareCurrentHour').textContent=formatHour(now);
  document.getElementById('fareCardsGrid').innerHTML=fareData.datasets.map(ds=>{
    const fare=safeNum(ds.current_fare),mult=safeNum(ds.current_multiplier,1);
    const sc=mult>=1.4?'surge-high':mult>=1.1?'surge-mid':'surge-low';
    const sl=mult>=1.4?`<i class="fas fa-fire"></i> Peak +${Math.round((mult-1)*100)}%`:mult>=1.1?`<i class="fas fa-arrow-up"></i> +${Math.round((mult-1)*100)}%`:mult<1.0?`<i class="fas fa-tag"></i> Off-peak`:`<i class="fas fa-check"></i> Standard`;
    return `<div class="fare-card" style="--rc:${ds.color}"><div class="fare-card-route">${escH(ds.route_number)}</div><div class="fare-card-amount" style="color:${ds.color}">KES ${formatKES(fare)}</div><div class="fare-card-range">Base: ${formatKES(safeNum(ds.fare_min))}–${formatKES(safeNum(ds.fare_max))}</div><div class="surge-badge ${sc}">${sl}</div></div>`;
  }).join('');
}
function renderFareChart(){
  const labels=Array.from({length:24},(_,i)=>formatHour(i));
  const datasets=fareData.datasets.filter(ds=>activeRoutes.has(ds.route_id)).map(ds=>({label:ds.route_number,data:ds.hourly_fares.map(v=>safeNum(v)),borderColor:ds.color,backgroundColor:ds.color+'18',borderWidth:2,pointRadius:3,pointHoverRadius:6,tension:0.4,fill:false}));
  const nowH=fareData.current_hour,GRID='rgba(0,230,118,0.07)';
  if(fareChartInst){fareChartInst.destroy();fareChartInst=null;}
  const ctx=document.getElementById('fareChart');if(!ctx)return;
  fareChartInst=new Chart(ctx,{type:'line',data:{labels,datasets},options:{responsive:true,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>` ${c.dataset.label}: KES ${c.parsed.y}`,title:i=>`🕐 ${i[0].label}`}}},scales:{x:{grid:{color:GRID},ticks:{color:'#7A9B80',font:{size:10},maxTicksLimit:12}},y:{grid:{color:GRID},beginAtZero:false,ticks:{color:'#7A9B80',font:{size:10},callback:v=>'KES '+v}}}},plugins:[{id:'nowLine',afterDraw:chart=>{const x=chart.scales.x.getPixelForValue(nowH),c2=chart.ctx;c2.save();c2.strokeStyle='rgba(0,230,118,0.6)';c2.lineWidth=1.5;c2.setLineDash([4,4]);c2.beginPath();c2.moveTo(x,chart.chartArea.top);c2.lineTo(x,chart.chartArea.bottom);c2.stroke();c2.fillStyle='#00E676';c2.font='bold 10px "DM Sans",sans-serif';c2.fillText('NOW',x+4,chart.chartArea.top+12);c2.restore();}}]});
}
function renderFareRoutePills(){document.getElementById('fareRoutePills').innerHTML=fareData.datasets.map(ds=>`<span class="fare-pill ${activeRoutes.has(ds.route_id)?'active':''}" style="background:${ds.color}22;color:${ds.color}" onclick="toggleFareRoute(${ds.route_id},this)">${escH(ds.route_number)}</span>`).join('');}
function toggleFareRoute(id,el){if(activeRoutes.has(id)){if(activeRoutes.size<=1)return;activeRoutes.delete(id);el.classList.remove('active');}else{activeRoutes.add(id);el.classList.add('active');}renderFareChart();}
function updateFareNowBand(){const now=fareData.current_hour;const b=(fareData.bands||[]).find(x=>x.hour_start<=now&&x.hour_end>=now);if(b){document.getElementById('fareNowBand').style.display='flex';document.getElementById('fareNowText').textContent=`Now: ${b.label} (${formatHour(b.hour_start)}–${formatHour(b.hour_end)})`;}}

// ============================================================
// FEEDBACK
// ============================================================
function setRating(v){document.getElementById('fb-rating').value=v;document.querySelectorAll('#starRating span').forEach((s,i)=>{s.style.color=i<v?'var(--amber)':'var(--muted)';});}
function submitFeedback(e){
  e.preventDefault();
  const mat=document.getElementById('fb-matatu').value,rat=document.getElementById('fb-rating').value,com=document.getElementById('fb-comment').value,msg=document.getElementById('feedbackMsg');
  if(!mat||rat==0){msg.style.display='block';msg.innerHTML='<div style="background:rgba(255,61,61,0.1);border:1px solid rgba(255,61,61,0.3);color:#ff6b6b;padding:0.75rem;border-radius:10px;font-size:0.85rem"><i class="fas fa-exclamation-circle"></i> Please select a matatu and a star rating.</div>';return;}
  fetch('api/feedback.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({matatu_id:mat,rating:rat,comment:com,user_id:<?= $user['id'] ?>})}).catch(()=>{});
  msg.style.display='block';msg.innerHTML='<div style="background:rgba(0,230,118,0.1);border:1px solid rgba(0,230,118,0.3);color:var(--green);padding:0.75rem;border-radius:10px;font-size:0.85rem"><i class="fas fa-check-circle"></i> Thank you! Feedback submitted.</div>';
  document.getElementById('fb-comment').value='';setRating(0);document.getElementById('fb-matatu').value='';
}

// ============================================================
// SECTION SWITCHING
// ============================================================
const ALL_PAGES=['saved','feedback','settings','wallet','fares'];
function switchSection(s){
  document.querySelectorAll('.nav-item').forEach(el=>el.classList.remove('active'));
  const nav=document.getElementById('nav-'+s);if(nav)nav.classList.add('active');
  const mc=document.getElementById('mainContent');
  if(ALL_PAGES.includes(s)){
    mc.style.display='none';
    ALL_PAGES.forEach(p=>{const el=document.getElementById('page-'+p);if(el)el.style.display=p===s?'flex':'none';});
    if(s==='wallet'){loadWallet();loadWalletHistory();}
    if(s==='fares'&&!fareData){loadFareChart();}
  }else{
    mc.style.display='grid';
    ALL_PAGES.forEach(p=>{const el=document.getElementById('page-'+p);if(el)el.style.display='none';});
    if(s==='routes')showPanelTab('routes');
    else if(s==='alerts')showPanelTab('alerts');
    else showPanelTab('matatus');
  }
}

// ============================================================
// INIT — fetch balance once on page load and cache it
// ============================================================
fetchMatatus();
fetchRoutes();
loadAlerts();
setInterval(fetchMatatus,5000);
// Refresh balance cache every 30s to stay current
fetchAndCacheBalance();
setInterval(fetchAndCacheBalance,30000);
</script>
</body>
</html>