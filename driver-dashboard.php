<?php
require_once 'includes/config.php';
requireLogin();
$user = getCurrentUser();
if ($user['role'] !== 'driver') {
    header('Location: passenger-dashboard.php');
    exit;
}
$db = Database::getConnection();
$matatu = null;
if (isset($_SESSION['matatu_id'])) {
    $mStmt = $db->prepare("SELECT m.*, r.route_name, r.route_number, r.origin, r.destination FROM matatus m LEFT JOIN routes r ON m.route_id=r.id WHERE m.id=:id");
    $mStmt->execute(['id' => $_SESSION['matatu_id']]);
    $matatu = $mStmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MatatuTrack — Driver Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
:root {
  --green:#00E676;--amber:#FFB300;--red:#FF3D3D;--blue:#2196F3;
  --ink:#0A0F0D;--ink2:#141A16;--surface:#1A2218;--surface2:#212E22;
  --border:rgba(0,230,118,0.15);--text:#E8F5E9;--muted:#7A9B80;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--ink);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;flex-direction:column;}

/* TOPBAR */
.topbar{
  display:flex;align-items:center;justify-content:space-between;
  padding:1rem 1.5rem;
  background:var(--surface);
  border-bottom:1px solid var(--border);
  position:sticky;top:0;z-index:100;
}
.tl{display:flex;align-items:center;gap:1rem;}
.logo-chip{display:flex;align-items:center;gap:0.5rem;text-decoration:none;}
.logo-chip .ic{width:32px;height:32px;background:var(--green);border-radius:8px;display:grid;place-items:center;color:var(--ink);font-size:0.9rem;}
.logo-chip span{font-family:'Syne',sans-serif;font-weight:800;font-size:1rem;color:var(--text);}
.page-title{font-family:'Syne',sans-serif;font-weight:700;font-size:1.05rem;color:var(--muted);}
.tr{display:flex;align-items:center;gap:0.75rem;}
.driver-info{text-align:right;}
.driver-name{font-weight:600;font-size:0.9rem;}
.driver-plate{font-size:0.75rem;color:var(--green);font-family:'Syne',sans-serif;font-weight:700;}
.btn{padding:0.5rem 1.1rem;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:500;cursor:pointer;transition:all 0.2s;border:none;display:flex;align-items:center;gap:0.4rem;}
.btn-ghost{background:var(--surface2);color:var(--muted);border:1px solid var(--border);}
.btn-ghost:hover{color:var(--text);}

/* LAYOUT */
.layout{display:grid;grid-template-columns:340px 1fr;flex:1;overflow:hidden;height:calc(100vh - 61px);}

/* CONTROL PANEL */
.ctrl-panel{
  background:var(--surface);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  overflow-y:auto;
}
.cp-section{padding:1.25rem;border-bottom:1px solid var(--border);}
.cp-title{font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted);font-weight:600;margin-bottom:1rem;}

/* Tracking toggle */
.tracking-toggle{
  display:flex;flex-direction:column;align-items:center;gap:1rem;
  padding:1.5rem;
}
.big-btn{
  width:120px;height:120px;border-radius:50%;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  font-family:'Syne',sans-serif;font-weight:800;font-size:1rem;
  cursor:pointer;transition:all 0.3s;border:none;
  position:relative;
}
.big-btn.start{background:linear-gradient(135deg,#00E676,#00C853);color:var(--ink);}
.big-btn.start:hover{transform:scale(1.05);box-shadow:0 0 40px rgba(0,230,118,0.4);}
.big-btn.stop{background:linear-gradient(135deg,#FF3D3D,#D32F2F);color:#fff;}
.big-btn.stop:hover{transform:scale(1.05);box-shadow:0 0 40px rgba(255,61,61,0.4);}
.big-btn i{font-size:2rem;margin-bottom:0.4rem;}
.big-btn .pulse-ring{
  position:absolute;
  width:100%;height:100%;border-radius:50%;
  border:3px solid var(--green);
  animation:ripple 2s ease-out infinite;
  display:none;
}
.big-btn.active .pulse-ring{display:block;}
@keyframes ripple{0%{transform:scale(1);opacity:0.8;}100%{transform:scale(1.5);opacity:0;}}

.tracking-status{
  text-align:center;
  padding:0.75rem 1.5rem;
  border-radius:12px;
  font-size:0.9rem;font-weight:500;
  width:100%;
}
.tracking-status.offline{background:rgba(255,61,61,0.1);border:1px solid rgba(255,61,61,0.2);color:#ff6b6b;}
.tracking-status.online{background:rgba(0,230,118,0.1);border:1px solid rgba(0,230,118,0.2);color:var(--green);}

/* Stats grid */
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;}
.stat-card{
  background:var(--ink2);border:1px solid var(--border);
  border-radius:12px;padding:1rem;
}
.sc-val{font-family:'Syne',sans-serif;font-weight:800;font-size:1.5rem;color:var(--green);}
.sc-label{font-size:0.73rem;color:var(--muted);margin-top:0.2rem;}
.sc-val.amber{color:var(--amber);}
.sc-val.blue{color:var(--blue);}
.sc-val.red{color:var(--red);}

/* Passenger counter */
.pax-counter{
  display:flex;align-items:center;justify-content:space-between;
  background:var(--ink2);border:1px solid var(--border);border-radius:12px;padding:1rem;
}
.pax-num{font-family:'Syne',sans-serif;font-weight:800;font-size:2rem;color:var(--text);}
.pax-controls{display:flex;gap:0.5rem;}
.pax-btn{
  width:36px;height:36px;border-radius:9px;border:1px solid var(--border);
  background:var(--surface2);color:var(--text);
  font-size:1.2rem;font-weight:700;cursor:pointer;
  display:grid;place-items:center;transition:all 0.2s;
}
.pax-btn:hover{background:var(--green);color:var(--ink);border-color:var(--green);}
.pax-info{font-size:0.78rem;color:var(--muted);}

/* Stage selector */
.stage-select{
  width:100%;background:var(--ink2);
  border:1px solid var(--border);border-radius:10px;
  padding:0.75rem 1rem;color:var(--text);
  font-family:'DM Sans',sans-serif;font-size:0.9rem;
  outline:none;
}
.stage-select:focus{border-color:var(--green);}

/* Trip info */
.trip-info-row{
  display:flex;justify-content:space-between;align-items:center;
  padding:0.6rem 0;border-bottom:1px solid var(--border);
  font-size:0.85rem;
}
.trip-info-row:last-child{border-bottom:none;}
.trip-info-label{color:var(--muted);}
.trip-info-val{font-weight:600;}

/* MAP */
.map-area{position:relative;flex:1;}
#driverMap{width:100%;height:100%;}

/* Speed display */
.speed-hud{
  position:absolute;bottom:1.5rem;right:1.5rem;
  background:rgba(10,15,13,0.9);
  border:1px solid var(--border);border-radius:16px;
  padding:1.25rem 1.5rem;
  text-align:center;backdrop-filter:blur(10px);
  z-index:400;
  min-width:120px;
}
.speed-val{font-family:'Syne',sans-serif;font-weight:800;font-size:2.5rem;color:var(--green);}
.speed-unit{font-size:0.75rem;color:var(--muted);text-transform:uppercase;}
.heading-val{font-size:0.8rem;color:var(--amber);margin-top:0.25rem;}

/* GPS status bar */
.gps-bar{
  position:absolute;top:1rem;left:50%;transform:translateX(-50%);
  background:rgba(10,15,13,0.9);
  border:1px solid var(--border);border-radius:100px;
  padding:0.4rem 1rem;
  display:flex;align-items:center;gap:0.75rem;
  font-size:0.8rem;backdrop-filter:blur(10px);z-index:400;
}
.gps-dot{width:8px;height:8px;border-radius:50%;background:var(--red);}
.gps-dot.active{background:var(--green);animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:0.5;transform:scale(1.5);}}

/* SOS */
.sos-btn{
  background:rgba(255,61,61,0.15);
  border:1px solid rgba(255,61,61,0.4);
  color:var(--red);border-radius:12px;
  padding:0.75rem;width:100%;
  font-family:'Syne',sans-serif;font-weight:700;
  font-size:1rem;cursor:pointer;transition:all 0.2s;
}
.sos-btn:hover{background:rgba(255,61,61,0.25);}

@media(max-width:768px){.layout{grid-template-columns:1fr;}.ctrl-panel{order:2;max-height:50vh;}}

/* ===================== SIMULATION FEATURE ===================== */
.sim-btn {
  width:100%;padding:0.75rem;border-radius:12px;
  background:linear-gradient(135deg,rgba(33,150,243,0.15),rgba(33,150,243,0.05));
  border:1px solid rgba(33,150,243,0.35);
  color:#64B5F6;font-family:'Syne',sans-serif;font-weight:700;font-size:0.88rem;
  cursor:pointer;transition:all 0.25s;display:flex;align-items:center;justify-content:center;gap:0.5rem;
}
.sim-btn:hover{background:rgba(33,150,243,0.25);border-color:#2196F3;color:#90CAF9;}
.sim-btn.active-sim{
  background:linear-gradient(135deg,rgba(255,179,0,0.2),rgba(255,179,0,0.05));
  border-color:rgba(255,179,0,0.5);color:var(--amber);
  animation:simPulse 2s ease-in-out infinite;
}
@keyframes simPulse{0%,100%{box-shadow:0 0 0 0 rgba(255,179,0,0);}50%{box-shadow:0 0 14px rgba(255,179,0,0.35);}}

/* Simulation drawer */
.sim-drawer {
  position:fixed;bottom:0;left:0;right:0;
  background:var(--surface);border-top:2px solid rgba(33,150,243,0.4);
  border-radius:20px 20px 0 0;
  z-index:600;
  transform:translateY(100%);transition:transform 0.35s cubic-bezier(0.34,1.56,0.64,1);
  padding:1.5rem;
  max-height:85vh;overflow-y:auto;
}
.sim-drawer.open{transform:translateY(0);}
.sim-drawer-handle{
  width:40px;height:4px;border-radius:100px;
  background:var(--border);margin:0 auto 1.25rem;
}
.sim-drawer-title{
  font-family:'Syne',sans-serif;font-weight:800;font-size:1.2rem;
  margin-bottom:0.25rem;display:flex;align-items:center;gap:0.5rem;
}
.sim-badge{
  font-size:0.65rem;font-family:'DM Sans',sans-serif;font-weight:600;
  background:rgba(33,150,243,0.2);border:1px solid rgba(33,150,243,0.4);
  color:#64B5F6;border-radius:100px;padding:0.2rem 0.6rem;text-transform:uppercase;letter-spacing:0.05em;
}
.sim-subtitle{font-size:0.83rem;color:var(--muted);margin-bottom:1.5rem;}

.route-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:0.75rem;margin-bottom:1.25rem;}
.route-card{
  background:var(--ink2);border:2px solid var(--border);border-radius:14px;
  padding:1rem;cursor:pointer;transition:all 0.2s;position:relative;overflow:hidden;
}
.route-card:hover{border-color:rgba(33,150,243,0.5);background:#1a2535;}
.route-card.selected{border-color:#2196F3;background:rgba(33,150,243,0.08);}
.route-card.selected::after{
  content:'✓';position:absolute;top:0.6rem;right:0.75rem;
  color:#2196F3;font-weight:700;font-size:1rem;
}
.rc-number{
  font-family:'Syne',sans-serif;font-weight:800;font-size:0.75rem;
  color:#64B5F6;margin-bottom:0.35rem;letter-spacing:0.05em;
}
.rc-name{font-weight:600;font-size:0.9rem;margin-bottom:0.2rem;}
.rc-detail{font-size:0.75rem;color:var(--muted);}
.rc-stops{
  display:flex;align-items:center;gap:0.35rem;margin-top:0.6rem;
  font-size:0.7rem;color:var(--muted);
}
.rc-dot{width:6px;height:6px;border-radius:50%;background:var(--green);flex-shrink:0;}
.rc-line{flex:1;height:1px;background:var(--border);}

/* Speed selector */
.speed-picker{
  display:flex;gap:0.5rem;margin-bottom:1.25rem;flex-wrap:wrap;
}
.speed-opt{
  padding:0.45rem 1rem;border-radius:9px;font-size:0.83rem;font-weight:600;
  background:var(--ink2);border:1px solid var(--border);color:var(--muted);
  cursor:pointer;transition:all 0.2s;
}
.speed-opt.sel{background:rgba(0,230,118,0.1);border-color:var(--green);color:var(--green);}

.sim-controls{display:flex;gap:0.75rem;}
.sim-start-btn{
  flex:1;padding:0.85rem;border-radius:12px;
  background:linear-gradient(135deg,#2196F3,#1565C0);
  border:none;color:#fff;
  font-family:'Syne',sans-serif;font-weight:700;font-size:0.95rem;
  cursor:pointer;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:0.5rem;
}
.sim-start-btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(33,150,243,0.4);}
.sim-start-btn:disabled{opacity:0.4;transform:none;cursor:not-allowed;}
.sim-cancel-btn{
  padding:0.85rem 1.25rem;border-radius:12px;
  background:var(--ink2);border:1px solid var(--border);color:var(--muted);
  font-family:'DM Sans',sans-serif;font-weight:500;font-size:0.88rem;cursor:pointer;
  transition:all 0.2s;
}
.sim-cancel-btn:hover{color:var(--text);}

/* Simulation HUD overlay on map */
.sim-hud{
  position:absolute;top:1rem;left:1rem;
  background:rgba(10,15,13,0.92);
  border:1px solid rgba(33,150,243,0.4);border-radius:14px;
  padding:1rem 1.25rem;backdrop-filter:blur(12px);z-index:400;
  display:none;min-width:210px;
}
.sim-hud.visible{display:block;}
.sim-hud-title{
  font-size:0.65rem;text-transform:uppercase;letter-spacing:0.1em;
  color:#64B5F6;font-weight:600;margin-bottom:0.75rem;
  display:flex;align-items:center;gap:0.4rem;
}
.sim-hud-route{font-family:'Syne',sans-serif;font-weight:700;font-size:0.95rem;margin-bottom:0.5rem;}
.sim-progress-bar{
  background:var(--ink2);border-radius:100px;height:5px;overflow:hidden;margin-bottom:0.5rem;
}
.sim-progress-fill{height:100%;border-radius:100px;background:linear-gradient(90deg,#2196F3,#00E676);transition:width 0.5s;}
.sim-stop-label{font-size:0.75rem;color:var(--muted);}
.sim-stop-name{font-size:0.82rem;font-weight:600;color:var(--text);}
.sim-stop-next{font-size:0.72rem;color:var(--muted);margin-top:0.2rem;}
.sim-stop-actions{display:flex;gap:0.5rem;margin-top:0.75rem;}
.sim-action-btn{
  flex:1;padding:0.4rem;border-radius:8px;font-size:0.72rem;font-weight:600;
  cursor:pointer;border:none;transition:all 0.2s;font-family:'DM Sans',sans-serif;
}
.sim-pause-btn{background:rgba(255,179,0,0.15);color:var(--amber);border:1px solid rgba(255,179,0,0.3);}
.sim-pause-btn:hover{background:rgba(255,179,0,0.25);}
.sim-stop-sim-btn{background:rgba(255,61,61,0.1);color:var(--red);border:1px solid rgba(255,61,61,0.25);}
.sim-stop-sim-btn:hover{background:rgba(255,61,61,0.2);}

/* Animated matatu trail */
.sim-vehicle-trail{stroke-dasharray:10 5;animation:trailFlow 0.8s linear infinite;}
@keyframes trailFlow{to{stroke-dashoffset:-15;}}

/* Overlay backdrop */
.sim-overlay{
  position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:550;
  opacity:0;pointer-events:none;transition:opacity 0.3s;
}
.sim-overlay.open{opacity:1;pointer-events:all;}
</style>
</head>
<body>
<!-- TOPBAR -->
<header class="topbar">
  <div class="tl">
    <a href="#" class="logo-chip">
      <div class="ic"><i class="fas fa-bus"></i></div>
      <span>MatatuTrack</span>
    </a>
    <span class="page-title">Driver Console</span>
  </div>
  <div class="tr">
    <div class="driver-info">
      <div class="driver-name"><?= htmlspecialchars($user['name']) ?></div>
      <div class="driver-plate"><?= $matatu ? htmlspecialchars($matatu['registration_plate']) : 'No vehicle assigned' ?></div>
    </div>
    <a href="auth.php?action=logout" class="btn btn-ghost"><i class="fas fa-sign-out-alt"></i> Exit</a>
  </div>
</header>

<div class="layout">
  <!-- CONTROL PANEL -->
  <aside class="ctrl-panel">

    <!-- Tracking Toggle -->
    <div class="cp-section" style="border-bottom:1px solid var(--border)">
      <div class="cp-title">GPS Tracking</div>
      <div class="tracking-toggle">
        <button class="big-btn start" id="trackingBtn" onclick="toggleTracking()">
          <div class="pulse-ring"></div>
          <i class="fas fa-satellite-dish" id="trackingIcon"></i>
          <span id="trackingLabel">START</span>
        </button>
        <div class="tracking-status offline" id="trackingStatus">
          <i class="fas fa-circle" style="font-size:0.6rem"></i>
          Tracking Offline
        </div>
      </div>
    </div>

    <!-- Live Stats -->
    <div class="cp-section">
      <div class="cp-title">Live Statistics</div>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="sc-val" id="statSpeed">0</div>
          <div class="sc-label">Speed (km/h)</div>
        </div>
        <div class="stat-card">
          <div class="sc-val amber" id="statDistance">0.0</div>
          <div class="sc-label">Distance (km)</div>
        </div>
        <div class="stat-card">
          <div class="sc-val blue" id="statTrips">0</div>
          <div class="sc-label">Trips Today</div>
        </div>
        <div class="stat-card">
          <div class="sc-val" id="statAccuracy">—</div>
          <div class="sc-label">GPS Accuracy (m)</div>
        </div>
      </div>
    </div>

    <!-- Passenger Count -->
    <div class="cp-section">
      <div class="cp-title">Passenger Count</div>
      <div class="pax-counter">
        <div>
          <div class="pax-num"><span id="paxCount">0</span><span style="font-size:1rem;color:var(--muted)">/ <?= $matatu['capacity'] ?? 14 ?></span></div>
          <div class="pax-info">Current onboard</div>
        </div>
        <div class="pax-controls">
          <button class="pax-btn" onclick="changePax(-1)">−</button>
          <button class="pax-btn" onclick="changePax(1)">+</button>
        </div>
      </div>
      <div style="margin-top:0.75rem">
        <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--muted);margin-bottom:0.35rem">
          <span>Occupancy</span>
          <span id="paxPct">0%</span>
        </div>
        <div style="background:var(--ink2);border-radius:100px;height:6px;overflow:hidden">
          <div id="paxBar" style="height:100%;border-radius:100px;background:var(--green);width:0%;transition:width 0.3s"></div>
        </div>
      </div>
    </div>

    <!-- Current Stage -->
    <div class="cp-section">
      <div class="cp-title">Stage Selection</div>
      <div style="margin-bottom:0.75rem">
        <label style="font-size:0.8rem;color:var(--muted);display:block;margin-bottom:0.35rem">Current Stage</label>
        <select class="stage-select" id="currentStage">
          <option value="">Select your current stage...</option>
          <option value="1">Kencom Stage</option>
          <option value="4">Langata Road Stage</option>
          <option value="5">Galleria Stage</option>
          <option value="3">Rongai Stage</option>
          <option value="7">Pangani Stage</option>
          <option value="6">Eastleigh Stage</option>
          <option value="8">Westlands Stage</option>
          <option value="11">Roysambu Stage</option>
          <option value="10">Githurai 45 Stage</option>
          <option value="13">Kawangware Stage</option>
          <option value="15">Ngong Town Stage</option>
          <option value="17">Thika Town Stage</option>
        </select>
      </div>
    </div>

    <!-- Trip Info -->
    <?php if ($matatu): ?>
    <div class="cp-section">
      <div class="cp-title">Vehicle Info</div>
      <div class="trip-info-row">
        <span class="trip-info-label">Plate</span>
        <span class="trip-info-val" style="color:var(--green)"><?= htmlspecialchars($matatu['registration_plate']) ?></span>
      </div>
      <div class="trip-info-row">
        <span class="trip-info-label">Route</span>
        <span class="trip-info-val"><?= htmlspecialchars($matatu['route_number'] ?? 'N/A') ?></span>
      </div>
      <div class="trip-info-row">
        <span class="trip-info-label">Direction</span>
        <span class="trip-info-val"><?= htmlspecialchars(($matatu['origin'] ?? '') . ' → ' . ($matatu['destination'] ?? '')) ?></span>
      </div>
      <div class="trip-info-row">
        <span class="trip-info-label">SACCO</span>
        <span class="trip-info-val"><?= htmlspecialchars($matatu['sacco_name'] ?? 'N/A') ?></span>
      </div>
      <div class="trip-info-row">
        <span class="trip-info-label">Capacity</span>
        <span class="trip-info-val"><?= $matatu['capacity'] ?? 14 ?> seats</span>
      </div>
    </div>
    <?php endif; ?>

    <!-- Route Simulation -->
    <div class="cp-section">
      <div class="cp-title">Demo Mode</div>
      <button class="sim-btn" id="simLaunchBtn" onclick="openSimDrawer()">
        <i class="fas fa-route"></i> Simulate Route Movement
      </button>
      <p style="font-size:0.72rem;color:var(--muted);margin-top:0.6rem;text-align:center;line-height:1.4;">
        Demo mode — animate the matatu along a real Nairobi route on the map
      </p>
    </div>

    <!-- SOS -->
    <div class="cp-section" style="border-top:1px solid var(--border);margin-top:auto">
      <button class="sos-btn" onclick="sendSOS()">
        <i class="fas fa-exclamation-triangle"></i> REPORT BREAKDOWN / SOS
      </button>
    </div>

  </aside>

  <!-- MAP -->
  <div class="map-area">
    <div id="driverMap"></div>
    <div class="gps-bar">
      <div class="gps-dot" id="gpsDot"></div>
      <span id="gpsStatusText">GPS Inactive</span>
      <span id="gpsCoords" style="color:var(--muted)">—</span>
    </div>
    <div class="speed-hud">
      <div class="speed-val" id="hudSpeed">0</div>
      <div class="speed-unit">km/h</div>
      <div class="heading-val" id="hudHeading">—</div>
    </div>
    <!-- Simulation HUD (shown on map during sim) -->
    <div class="sim-hud" id="simHud">
      <div class="sim-hud-title">
        <i class="fas fa-circle" style="color:#2196F3;font-size:0.5rem;animation:pulse 1.5s infinite"></i>
        SIMULATION ACTIVE
      </div>
      <div class="sim-hud-route" id="simHudRoute">—</div>
      <div class="sim-progress-bar">
        <div class="sim-progress-fill" id="simProgressFill" style="width:0%"></div>
      </div>
      <div class="sim-stop-label">Current Stop</div>
      <div class="sim-stop-name" id="simCurrentStop">—</div>
      <div class="sim-stop-next" id="simNextStop">Next: —</div>
      <div class="sim-stop-actions">
        <button class="sim-action-btn sim-pause-btn" id="simPauseBtn" onclick="toggleSimPause()">
          <i class="fas fa-pause"></i> Pause
        </button>
        <button class="sim-action-btn sim-stop-sim-btn" onclick="stopSimulation()">
          <i class="fas fa-stop"></i> End Sim
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Simulation Overlay Backdrop -->
<div class="sim-overlay" id="simOverlay" onclick="closeSimDrawer()"></div>

<!-- Simulation Drawer -->
<div class="sim-drawer" id="simDrawer">
  <div class="sim-drawer-handle"></div>
  <div class="sim-drawer-title">
    <i class="fas fa-route" style="color:#64B5F6"></i>
    Route Simulator
    <span class="sim-badge">Demo Mode</span>
  </div>
  <p class="sim-subtitle">Choose a Nairobi matatu route to simulate live movement on the map.</p>

  <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted);font-weight:600;margin-bottom:0.75rem;">Select Route</div>
  <div class="route-cards" id="routeCards">
    <!-- Injected by JS -->
  </div>

  <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted);font-weight:600;margin-bottom:0.5rem;">Simulation Speed</div>
  <div class="speed-picker" id="speedPicker">
    <div class="speed-opt" data-speed="1" onclick="selectSpeed(1)">🐢 Slow</div>
    <div class="speed-opt sel" data-speed="2" onclick="selectSpeed(2)">🚐 Normal</div>
    <div class="speed-opt" data-speed="4" onclick="selectSpeed(4)">⚡ Fast</div>
    <div class="speed-opt" data-speed="8" onclick="selectSpeed(8)">🚀 Turbo</div>
  </div>

  <div class="sim-controls">
    <button class="sim-start-btn" id="simStartBtn" onclick="startSimulation()" disabled>
      <i class="fas fa-play"></i> Start Simulation
    </button>
    <button class="sim-cancel-btn" onclick="closeSimDrawer()">Cancel</button>
  </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ============================================================
// DRIVER DASHBOARD STATE
// ============================================================
const MATATU_ID   = <?= json_encode($_SESSION['matatu_id'] ?? null) ?>;
const DRIVER_ID   = <?= json_encode($user['id']) ?>;
const CAPACITY    = <?= $matatu['capacity'] ?? 14 ?>;
const ROUTE_ID    = <?= json_encode($_SESSION['route_id'] ?? null) ?>;

let tracking      = false;
let watchId       = null;
let lastPos       = null;
let passengerCount= 0;
let totalDistance = 0;
let tripsToday    = 0;
let updateInterval= null;
let driverMarker  = null;
let pathPoints    = [];
let routePath     = null;

// ============================================================
// ROUTE SIMULATION ENGINE — data declared early to avoid hoisting issues
// ============================================================
const SIM_ROUTES = [
  {
    id: 'rongai',
    number: '111',
    name: 'Rongai → CBD',
    detail: 'Rongai – Langata – Karen – CBD',
    stops: ['Rongai Stage','Mushrooms','Bomas Jn','Karen Crossroads','Dagoretti Cnr','Kawangware','Westlands','Kencom CBD'],
    waypoints: [
      [-1.3971,36.7456],[-1.3850,36.7500],[-1.3780,36.7582],
      [-1.3621,36.7658],[-1.3503,36.7720],[-1.3299,36.7812],
      [-1.3040,36.8000],[-1.2905,36.8180],[-1.2846,36.8234],
      [-1.2836,36.8247],[-1.2833,36.8263],[-1.2821,36.8219]
    ]
  },
  {
    id: 'thika',
    number: '45',
    name: 'CBD → Thika',
    detail: 'CBD – Pangani – Roysambu – Githurai – Thika',
    stops: ['Kencom CBD','Archives','Pangani','Muthaiga','Roysambu','Githurai 45','Thika Town'],
    waypoints: [
      [-1.2821,36.8219],[-1.2780,36.8250],[-1.2740,36.8300],
      [-1.2621,36.8398],[-1.2500,36.8502],[-1.2350,36.8620],
      [-1.2200,36.8720],[-1.1950,36.8820],[-1.1700,36.8950],
      [-1.1450,36.9050],[-1.0992,36.9667]
    ]
  },
  {
    id: 'eastleigh',
    number: '23',
    name: 'Eastleigh → Westlands',
    detail: 'Eastleigh – Pangani – CBD – Westlands',
    stops: ['Eastleigh Stage','Pangani Jn','Archives','Kencom','University Way','Westlands'],
    waypoints: [
      [-1.2720,36.8490],[-1.2690,36.8450],[-1.2660,36.8400],
      [-1.2620,36.8340],[-1.2780,36.8250],[-1.2821,36.8219],
      [-1.2810,36.8170],[-1.2790,36.8100],[-1.2760,36.8030],
      [-1.2720,36.7950],[-1.2658,36.7898]
    ]
  },
  {
    id: 'ngong',
    number: '126',
    name: 'Ngong → CBD',
    detail: 'Ngong Town – Dagoretti – Kawangware – CBD',
    stops: ['Ngong Town','Kibiko','Dagoretti Cnr','Kawangware 46','Olympic','Kencom CBD'],
    waypoints: [
      [-1.3587,36.6584],[-1.3500,36.6750],[-1.3400,36.6950],
      [-1.3300,36.7200],[-1.3200,36.7400],[-1.3100,36.7550],
      [-1.3000,36.7700],[-1.2900,36.7850],[-1.2850,36.8000],
      [-1.2830,36.8100],[-1.2821,36.8219]
    ]
  }
];

let simState = {
  running: false,
  paused: false,
  route: null,
  speedMultiplier: 2,
  segmentIndex: 0,
  segmentProgress: 0,
  simInterval: null,
  marker: null,
  polyline: null,
  trailPoints: [],
  trailLine: null,
  stopMarkers: []
};

// ============================================================
// MAP INIT
// ============================================================
const map = L.map('driverMap', {
  center: [-1.2921, 36.8219],
  zoom: 14,
  zoomControl: false
});
L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
  attribution: '&copy; OpenStreetMap &copy; CARTO',
  subdomains: 'abcd', maxZoom: 19
}).addTo(map);
L.control.zoom({ position: 'bottomright' }).addTo(map);

const driverIcon = L.divIcon({
  className: '',
  html: `<div style="
    width:44px;height:44px;border-radius:50%;
    background:linear-gradient(135deg,#00E676,#00C853);
    border:3px solid white;
    display:flex;align-items:center;justify-content:center;
    font-size:1.3rem;
    box-shadow:0 4px 15px rgba(0,230,118,0.5);
  ">🚐</div>`,
  iconSize: [44,44], iconAnchor: [22,22]
});

// ============================================================
// TRACKING
// ============================================================
function toggleTracking() {
  if (!tracking) startTracking();
  else stopTracking();
}

function startTracking() {
  if (!navigator.geolocation) {
    alert('Geolocation is not supported by your browser.');
    return;
  }
  tracking = true;
  updateTrackingUI(true);

  watchId = navigator.geolocation.watchPosition(
    onPositionUpdate,
    onPositionError,
    { enableHighAccuracy: true, maximumAge: 3000, timeout: 10000 }
  );

  // Send updates every 4 seconds
  updateInterval = setInterval(sendLocationToServer, 4000);
  tripsToday++;
  document.getElementById('statTrips').textContent = tripsToday;
}

function stopTracking() {
  tracking = false;
  if (watchId !== null) navigator.geolocation.clearWatch(watchId);
  clearInterval(updateInterval);
  watchId = null;
  updateTrackingUI(false);

  // Tell server we're offline
  if (MATATU_ID) {
    fetch('api/tracking.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        action: 'set_offline',
        matatu_id: MATATU_ID,
        driver_id: DRIVER_ID
      })
    });
  }
}

function updateTrackingUI(isTracking) {
  const btn = document.getElementById('trackingBtn');
  const label = document.getElementById('trackingLabel');
  const icon = document.getElementById('trackingIcon');
  const status = document.getElementById('trackingStatus');
  const dot = document.getElementById('gpsDot');

  btn.className = `big-btn ${isTracking ? 'stop active' : 'start'}`;
  label.textContent = isTracking ? 'STOP' : 'START';
  icon.className = `fas fa-${isTracking ? 'stop' : 'satellite-dish'}`;
  status.className = `tracking-status ${isTracking ? 'online' : 'offline'}`;
  status.innerHTML = `<i class="fas fa-circle" style="font-size:0.6rem"></i> ${isTracking ? 'Tracking Active' : 'Tracking Offline'}`;
  dot.className = `gps-dot ${isTracking ? 'active' : ''}`;
  document.getElementById('gpsStatusText').textContent = isTracking ? 'GPS Active' : 'GPS Inactive';
}

function onPositionUpdate(position) {
  const { latitude, longitude, speed, heading, accuracy } = position.coords;
  const speedKmh = speed ? Math.round(speed * 3.6) : Math.floor(Math.random() * 50 + 10);

  // Update stats
  document.getElementById('statSpeed').textContent = speedKmh;
  document.getElementById('hudSpeed').textContent = speedKmh;
  document.getElementById('hudHeading').textContent = heading ? headingToCompass(heading) : 'N/A';
  document.getElementById('statAccuracy').textContent = accuracy ? Math.round(accuracy) : '—';
  document.getElementById('gpsCoords').textContent = `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;

  // Distance calculation
  if (lastPos) {
    const dist = haversineDistance(lastPos[0], lastPos[1], latitude, longitude);
    totalDistance += dist;
    document.getElementById('statDistance').textContent = totalDistance.toFixed(1);
  }
  lastPos = [latitude, longitude];

  // Map update
  if (driverMarker) {
    driverMarker.setLatLng([latitude, longitude]);
  } else {
    driverMarker = L.marker([latitude, longitude], { icon: driverIcon })
      .addTo(map)
      .bindPopup('<strong>Your Location</strong>');
  }
  map.setView([latitude, longitude], map.getZoom());

  // Draw path
  pathPoints.push([latitude, longitude]);
  if (routePath) map.removeLayer(routePath);
  if (pathPoints.length > 1) {
    routePath = L.polyline(pathPoints, {
      color: '#00E676', weight: 4, opacity: 0.7,
      dashArray: '8 4'
    }).addTo(map);
  }
}

function onPositionError(err) {
  console.warn('GPS error:', err.message);
  document.getElementById('gpsStatusText').textContent = 'GPS Error: ' + err.message;
  // Simulate movement for demo
  simulateMovement();
}

// Demo simulation when GPS unavailable
let simLat = -1.2921, simLng = 36.8219;
function simulateMovement() {
  simLat += (Math.random() - 0.5) * 0.002;
  simLng += (Math.random() - 0.5) * 0.002;
  onPositionUpdate({
    coords: { latitude: simLat, longitude: simLng, speed: Math.random() * 15, heading: 45, accuracy: 8 }
  });
}

function sendLocationToServer() {
  if (!lastPos || !MATATU_ID) return;
  const speedKmh = parseFloat(document.getElementById('statSpeed').textContent) || 0;

  fetch('api/tracking.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'update_location',
      matatu_id: MATATU_ID,
      driver_id: DRIVER_ID,
      latitude: lastPos[0],
      longitude: lastPos[1],
      speed_kmh: speedKmh,
      passenger_count: passengerCount,
      current_stage_id: document.getElementById('currentStage').value || null,
      status: 'active'
    })
  }).catch(e => console.log('Server update failed (demo mode)'));
}

// ============================================================
// PASSENGER COUNTER
// ============================================================
function changePax(delta) {
  passengerCount = Math.max(0, Math.min(CAPACITY, passengerCount + delta));
  document.getElementById('paxCount').textContent = passengerCount;
  const pct = Math.round(passengerCount / CAPACITY * 100);
  document.getElementById('paxPct').textContent = pct + '%';
  const bar = document.getElementById('paxBar');
  bar.style.width = pct + '%';
  bar.style.background = pct > 90 ? 'var(--red)' : pct > 70 ? 'var(--amber)' : 'var(--green)';
}

// ============================================================
// SOS
// ============================================================
function sendSOS() {
  if (!confirm('Send SOS / Breakdown alert? This will notify dispatch immediately.')) return;
  fetch('api/tracking.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'set_offline',
      matatu_id: MATATU_ID,
      driver_id: DRIVER_ID,
      status: 'breakdown'
    })
  });
  alert('SOS sent! Help is on the way.');
}

// ============================================================
// UTILS
// ============================================================
function haversineDistance(lat1, lon1, lat2, lon2) {
  const R = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function headingToCompass(heading) {
  const dirs = ['N','NE','E','SE','S','SW','W','NW'];
  return dirs[Math.round(heading / 45) % 8];
}

// ============================================================
// INIT - try to get initial position
// ============================================================
navigator.geolocation?.getCurrentPosition(pos => {
  map.setView([pos.coords.latitude, pos.coords.longitude], 15);
}, () => {
  // Default Nairobi CBD
});

// ============================================================
// ROUTE SIMULATION ENGINE
// ============================================================

// ---- Drawer ----
function openSimDrawer() {
  renderRouteCards();
  document.getElementById('simDrawer').classList.add('open');
  document.getElementById('simOverlay').classList.add('open');
}
function closeSimDrawer() {
  document.getElementById('simDrawer').classList.remove('open');
  document.getElementById('simOverlay').classList.remove('open');
}

// ---- Route card rendering ----
function renderRouteCards() {
  const container = document.getElementById('routeCards');
  container.innerHTML = SIM_ROUTES.map(r => `
    <div class="route-card" id="rc_${r.id}" onclick="selectRoute('${r.id}')">
      <div class="rc-number">ROUTE ${r.number}</div>
      <div class="rc-name">${r.name}</div>
      <div class="rc-detail">${r.detail}</div>
      <div class="rc-stops">
        <div class="rc-dot"></div>
        <div class="rc-line"></div>
        ${r.stops.map((s,i) => i===0||i===r.stops.length-1 ? `<span style="font-size:0.7rem;white-space:nowrap">${s}</span>` : '').filter(Boolean).join('<div class="rc-line"></div>')}
        <div class="rc-dot" style="background:var(--red)"></div>
      </div>
    </div>
  `).join('');
}

function selectRoute(id) {
  simState.route = SIM_ROUTES.find(r => r.id === id);
  document.querySelectorAll('.route-card').forEach(c => c.classList.remove('selected'));
  document.getElementById('rc_' + id).classList.add('selected');
  document.getElementById('simStartBtn').disabled = false;
}

function selectSpeed(val) {
  simState.speedMultiplier = val;
  document.querySelectorAll('.speed-opt').forEach(o => o.classList.remove('sel'));
  document.querySelector(`[data-speed="${val}"]`).classList.add('sel');
}

// ---- Simulation core ----
function startSimulation() {
  if (!simState.route) return;
  closeSimDrawer();
  clearSimulation();

  const route = simState.route;
  simState.running = true;
  simState.paused = false;
  simState.segmentIndex = 0;
  simState.segmentProgress = 0;
  simState.trailPoints = [route.waypoints[0]];

  // Update UI
  document.getElementById('simLaunchBtn').classList.add('active-sim');
  document.getElementById('simLaunchBtn').innerHTML = '<i class="fas fa-circle-dot"></i> Simulation Running';
  document.getElementById('simHud').classList.add('visible');
  document.getElementById('simHudRoute').textContent = route.number + ' — ' + route.name;
  updateStopDisplay(0);

  // Draw full route ghost line
  simState.polyline = L.polyline(route.waypoints, {
    color: 'rgba(33,150,243,0.25)',
    weight: 4,
    dashArray: '6 4'
  }).addTo(map);

  // Drop stop markers
  route.stops.forEach((stopName, i) => {
    const wpIdx = Math.round((i / (route.stops.length - 1)) * (route.waypoints.length - 1));
    const latlng = route.waypoints[wpIdx];
    const isFirst = i === 0;
    const isLast = i === route.stops.length - 1;
    const marker = L.circleMarker(latlng, {
      radius: 6,
      fillColor: isFirst ? '#00E676' : isLast ? '#FF3D3D' : '#2196F3',
      color: '#0A0F0D',
      weight: 2,
      fillOpacity: 1
    }).addTo(map).bindPopup(`<strong>${stopName}</strong>`);
    simState.stopMarkers.push(marker);
  });

  // Create sim vehicle marker (distinct from driver marker)
  simState.marker = L.marker(route.waypoints[0], {
    icon: L.divIcon({
      className: '',
      html: `<div style="
        width:42px;height:42px;border-radius:50%;
        background:linear-gradient(135deg,#2196F3,#1565C0);
        border:3px solid white;display:flex;align-items:center;
        justify-content:center;font-size:1.2rem;
        box-shadow:0 0 20px rgba(33,150,243,0.6);
      ">🚌</div>`,
      iconSize:[42,42], iconAnchor:[21,21]
    })
  }).addTo(map).bindPopup(`<strong>${route.name}</strong><br>Simulation active`);

  map.setView(route.waypoints[0], 14);

  // Kick off interval ~60fps feel
  const STEP_MS = 80;
  const BASE_PROGRESS_PER_STEP = 0.012;
  simState.simInterval = setInterval(() => {
    if (simState.paused || !simState.running) return;
    advanceSimulation(BASE_PROGRESS_PER_STEP * simState.speedMultiplier);
  }, STEP_MS);
}

function advanceSimulation(progressStep) {
  const route = simState.route;
  const wps = route.waypoints;
  const seg = simState.segmentIndex;
  if (seg >= wps.length - 1) { finishSimulation(); return; }

  simState.segmentProgress += progressStep;
  if (simState.segmentProgress >= 1) {
    simState.segmentProgress = 0;
    simState.segmentIndex++;
    if (simState.segmentIndex >= wps.length - 1) { finishSimulation(); return; }
    // Check if near a stop
    checkStopProximity(simState.segmentIndex);
  }

  // Interpolate position
  const from = wps[simState.segmentIndex];
  const to = wps[Math.min(simState.segmentIndex + 1, wps.length - 1)];
  const t = simState.segmentProgress;
  const lat = from[0] + (to[0] - from[0]) * t;
  const lng = from[1] + (to[1] - from[1]) * t;

  simState.marker.setLatLng([lat, lng]);
  map.panTo([lat, lng], { animate: true, duration: 0.1 });

  // Update trail
  simState.trailPoints.push([lat, lng]);
  if (simState.trailLine) map.removeLayer(simState.trailLine);
  if (simState.trailPoints.length > 1) {
    simState.trailLine = L.polyline(simState.trailPoints, {
      color: '#2196F3', weight: 3, opacity: 0.7, dashArray: '8 4'
    }).addTo(map);
  }

  // Update stats
  const totalSegs = wps.length - 1;
  const progress = ((simState.segmentIndex + simState.segmentProgress) / totalSegs) * 100;
  document.getElementById('simProgressFill').style.width = progress.toFixed(1) + '%';

  // Simulate speed (25–65 km/h)
  const segLen = haversineDistance(from[0],from[1],to[0],to[1]);
  const simSpeed = Math.round(25 + Math.random() * 40);
  document.getElementById('statSpeed').textContent = simSpeed;
  document.getElementById('hudSpeed').textContent = simSpeed;

  // Heading
  const dLng = to[1] - from[1];
  const dLat = to[0] - from[0];
  const heading = (Math.atan2(dLng, dLat) * 180 / Math.PI + 360) % 360;
  document.getElementById('hudHeading').textContent = headingToCompass(heading);

  // Distance
  if (lastPos) {
    const dist = haversineDistance(lastPos[0], lastPos[1], lat, lng);
    totalDistance += dist;
    document.getElementById('statDistance').textContent = totalDistance.toFixed(1);
  }
  lastPos = [lat, lng];
  document.getElementById('gpsCoords').textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
}

function checkStopProximity(wpIndex) {
  const route = simState.route;
  const stopWpIndices = route.stops.map((_, i) =>
    Math.round((i / (route.stops.length - 1)) * (route.waypoints.length - 1))
  );
  const stopIdx = stopWpIndices.indexOf(wpIndex);
  if (stopIdx !== -1) updateStopDisplay(stopIdx);
}

function updateStopDisplay(stopIdx) {
  const stops = simState.route.stops;
  document.getElementById('simCurrentStop').textContent = stops[stopIdx] || '—';
  document.getElementById('simNextStop').textContent =
    stopIdx + 1 < stops.length ? 'Next: ' + stops[stopIdx + 1] : 'Final stop reached';
  document.getElementById('currentStage').value = stopIdx + 1;
}

function finishSimulation() {
  clearInterval(simState.simInterval);
  simState.running = false;
  document.getElementById('simProgressFill').style.width = '100%';
  document.getElementById('simCurrentStop').textContent = simState.route.stops.at(-1);
  document.getElementById('simNextStop').textContent = '✅ Route complete';
  document.getElementById('statSpeed').textContent = '0';
  document.getElementById('hudSpeed').textContent = '0';
  setTimeout(() => {
    stopSimulation();
    alert(`Simulation complete!\nRoute ${simState.route?.number || ''} finished.\nTotal distance: ${totalDistance.toFixed(2)} km`);
  }, 1200);
}

function stopSimulation() {
  clearInterval(simState.simInterval);
  simState.running = false;
  clearSimulation();
  document.getElementById('simLaunchBtn').classList.remove('active-sim');
  document.getElementById('simLaunchBtn').innerHTML = '<i class="fas fa-route"></i> Simulate Route Movement';
  document.getElementById('simHud').classList.remove('visible');
  document.getElementById('statSpeed').textContent = '0';
  document.getElementById('hudSpeed').textContent = '0';
}

function clearSimulation() {
  if (simState.marker) { map.removeLayer(simState.marker); simState.marker = null; }
  if (simState.polyline) { map.removeLayer(simState.polyline); simState.polyline = null; }
  if (simState.trailLine) { map.removeLayer(simState.trailLine); simState.trailLine = null; }
  simState.stopMarkers.forEach(m => map.removeLayer(m));
  simState.stopMarkers = [];
  simState.trailPoints = [];
}

function toggleSimPause() {
  simState.paused = !simState.paused;
  const btn = document.getElementById('simPauseBtn');
  btn.innerHTML = simState.paused
    ? '<i class="fas fa-play"></i> Resume'
    : '<i class="fas fa-pause"></i> Pause';
  btn.style.background = simState.paused
    ? 'rgba(0,230,118,0.1)' : 'rgba(255,179,0,0.15)';
  btn.style.color = simState.paused ? 'var(--green)' : 'var(--amber)';
}

</script>
</body>
</html>
