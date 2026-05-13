<?php
// dashboard.php - Main dashboard page
require_once 'config.php';

// Verify user is logged in
requireLogin();

// Ensure all session variables are set
if (!isset($_SESSION['user_name']) || empty($_SESSION['user_name'])) {
    $_SESSION['user_name'] = 'User';
}
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    $_SESSION['user_email'] = 'user@environet.com';
}
if (!isset($_SESSION['user_role']) || empty($_SESSION['user_role'])) {
    $_SESSION['user_role'] = 'user';
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Get latest ESP32-CAM sensor data from insert file (ESP32 data)
$sensorData = getLatestSensorData($user_id);



// Get network data
$networkData = null;
try {
    $pdo = getDBConnection();
    if ($pdo) {
        $stmt = $pdo->prepare("
            SELECT * FROM esp32_cam_data 
            WHERE user_id = ? 
            ORDER BY timestamp DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $networkData = $stmt->fetch();
        
        if (!$networkData) {
            $networkData = [
                'signal_strength' => null,
                'bandwidth' => null,
                'ping' => null,
                'ssid' => null,
                'ip_address' => null,
                'mac_address' => null
            ];
        }
    }
} catch(PDOException $e) {
    error_log("Error retrieving network data: " . $e->getMessage());
    $networkData = [
        'signal_strength' => null,
        'bandwidth' => null,
        'ping' => null,
        'ssid' => null,
        'ip_address' => null,
        'mac_address' => null
    ];
}

// Get user rooms
$stmt = $pdo->prepare("
    SELECT * FROM rooms
    ORDER BY created_at DESC
");
$stmt->execute();
$rooms = $stmt->fetchAll();

// Get all alerts
$allAlerts = [];
try {
    $pdo = getDBConnection();
    if ($pdo) {
        $stmt = $pdo->prepare("
            SELECT * FROM alerts 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 100
        ");
        $stmt->execute([$user_id]);
        $allAlerts = $stmt->fetchAll();
    }
} catch(PDOException $e) {
    error_log("Error retrieving alerts: " . $e->getMessage());
}

// Get data history
$historyData = [];
try {
    $pdo = getDBConnection();
    if ($pdo) {
        $stmt = $pdo->prepare("
            SELECT * FROM sensor_data 
            WHERE user_id = ? 
            ORDER BY timestamp DESC 
            LIMIT 500
        ");
        $stmt->execute([$user_id]);
        $historyData = array_reverse($stmt->fetchAll());
    }
} catch(PDOException $e) {
    error_log("Error retrieving history: " . $e->getMessage());
    $historyData = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EnviroNet · ESP32 Network Monitor</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
  <style>
    :root {
      --primary-dark: #0f2b4b;
      --primary: #1e4a7a;
      --primary-light: #3a6b9e;
      --primary-soft: #e6f0fa;
      --success: #0d7c4b;
      --success-light: #e3f7ed;
      --warning: #b6560c;
      --warning-light: #fff1e0;
      --danger: #b91c1c;
      --danger-light: #fee9e9;
      --neutral-50: #f9fafc;
      --neutral-100: #f2f5f9;
      --neutral-200: #e9eef4;
      --neutral-600: #4b5a6b;
      --neutral-700: #2f3e4f;
      --neutral-800: #1e2a36;
      --neutral-900: #121c26;
    }

    .dashboard-card {
      background: white;
      border-radius: 1.2rem;
      padding: 1.5rem;
      box-shadow: 0 8px 24px -8px rgba(0,30,60,0.08);
      border: 1px solid var(--neutral-200);
      transition: all 0.2s ease;
    }
    
    .dashboard-card:hover {
      box-shadow: 0 12px 28px -8px rgba(0,40,80,0.12);
    }
    
    .badge-opt {
      background: var(--success-light);
      color: var(--success);
      font-size: 0.7rem;
      padding: 0.2rem 0.8rem;
      border-radius: 30px;
      font-weight: 500;
      display: inline-block;
    }
    
    .badge-warning {
      background: var(--warning-light);
      color: var(--warning);
      font-size: 0.7rem;
      padding: 0.2rem 0.8rem;
      border-radius: 30px;
      font-weight: 500;
      display: inline-block;
    }
    
    .badge-danger {
      background: var(--danger-light);
      color: var(--danger);
      font-size: 0.7rem;
      padding: 0.2rem 0.8rem;
      border-radius: 30px;
      font-weight: 500;
      display: inline-block;
    }
    
    .status-dot { 
      width: 10px; 
      height: 10px; 
      border-radius: 20px; 
      display: inline-block; 
      margin-right: 6px; 
    }
    .dot-green { background: var(--success); }
    .dot-yellow { background: var(--warning); }
    .dot-red { background: var(--danger); }
    
    .logo-environet {
      background: white;
      border-radius: 20px;
      padding: 0.3rem 1.2rem 0.3rem 0.8rem;
      box-shadow: 0 8px 16px -10px rgba(30,74,122,0.25);
      border: 1px solid var(--neutral-200);
    }
    
    .hidden { display: none; }
    
    .chart-container {
      height: 200px;
      position: relative;
      width: 100%;
    }
    .chart-container-small {
      height: 150px;
      position: relative;
      width: 100%;
    }
    .chart-container-extra-small {
      height: 120px;
      position: relative;
      width: 100%;
    }
    
    .user-profile-badge {
      background: white;
      border-radius: 60px;
      padding: 0.3rem 0.3rem 0.3rem 1.2rem;
      border: 1px solid var(--neutral-200);
      box-shadow: 0 4px 12px rgba(0,30,50,0.04);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .logout-footer {
      display: flex;
      justify-content: flex-end;
      margin-top: 2rem;
      margin-bottom: 1rem;
    }
    .logout-btn-bottom {
      background: var(--danger-light);
      color: var(--danger);
      border-radius: 40px;
      padding: 0.7rem 2.2rem;
      font-size: 1rem;
      font-weight: 500;
      transition: 0.2s;
      border: 1px solid rgba(185,28,28,0.1);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.02);
      text-decoration: none;
    }
    .logout-btn-bottom:hover {
      background: #fdd8d8;
      transform: translateY(-1px);
      box-shadow: 0 6px 12px rgba(185,28,28,0.1);
    }

    .network-status {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 30px;
      font-size: 0.8rem;
      font-weight: 500;
    }
    
    .status-stable {
      background: var(--success-light);
      color: var(--success);
    }
    
    .status-degraded {
      background: var(--warning-light);
      color: var(--warning);
    }
    
    .status-critical {
      background: var(--danger-light);
      color: var(--danger);
    }

    .live-indicator {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.8rem;
      color: var(--success);
    }
    
    .live-dot {
      width: 8px;
      height: 8px;
      background: var(--success);
      border-radius: 50%;
      animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
      0% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.5; transform: scale(1.2); }
      100% { opacity: 1; transform: scale(1); }
    }

    .notification-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
      max-width: 350px;
      width: 100%;
      pointer-events: none;
    }

    .notification {
      background: white;
      border-radius: 12px;
      box-shadow: 0 10px 30px -5px rgba(0,0,0,0.2);
      padding: 16px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      transform: translateX(120%);
      animation: slideIn 0.3s ease forwards;
      cursor: pointer;
      pointer-events: auto;
      border-left: 4px solid transparent;
      transition: all 0.2s ease;
    }

    .notification:hover {
      transform: translateX(0) scale(1.02);
      box-shadow: 0 15px 35px -5px rgba(0,0,0,0.25);
    }

    .notification.critical {
      border-left-color: var(--danger);
    }

    .notification.warning {
      border-left-color: var(--warning);
    }

    .notification.info {
      border-left-color: var(--success);
    }

    .notification-icon {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .notification.critical .notification-icon {
      background: var(--danger-light);
      color: var(--danger);
    }

    .notification.warning .notification-icon {
      background: var(--warning-light);
      color: var(--warning);
    }

    .notification.info .notification-icon {
      background: var(--success-light);
      color: var(--success);
    }

    .notification-content {
      flex: 1;
    }

    .notification-title {
      font-weight: 600;
      font-size: 0.95rem;
      margin-bottom: 4px;
    }

    .notification-message {
      font-size: 0.85rem;
      color: var(--neutral-700);
      margin-bottom: 6px;
    }

    .notification-location {
      font-size: 0.75rem;
      color: var(--neutral-600);
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .notification-time {
      font-size: 0.7rem;
      color: var(--neutral-600);
      margin-top: 4px;
    }

    .notification-close {
      color: var(--neutral-600);
      font-size: 0.8rem;
      padding: 4px;
      border-radius: 4px;
      transition: all 0.2s ease;
      flex-shrink: 0;
    }

    .notification-close:hover {
      background: var(--neutral-100);
      color: var(--neutral-900);
    }

    @keyframes slideIn {
      to { transform: translateX(0); }
    }

    @keyframes slideOut {
      to { transform: translateX(120%); opacity: 0; }
    }

    .notification.removing {
      animation: slideOut 0.3s ease forwards;
    }

    .notification-badge {
      position: relative;
      cursor: pointer;
      margin-left: 10px;
    }

    .notification-badge i {
      font-size: 1.2rem;
      color: var(--neutral-700);
    }

    .notification-badge .count {
      position: absolute;
      top: -8px;
      right: -8px;
      background: var(--danger);
      color: white;
      font-size: 0.65rem;
      font-weight: 600;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .map-marker {
      transition: transform 0.2s ease;
      cursor: pointer;
      position: absolute;
    }
    .map-marker:hover {
      transform: scale(1.05);
      box-shadow: 0 12px 24px -8px rgba(0,0,0,0.2) !important;
    }
    
    @keyframes pulseMap {
      0% { box-shadow: 0 0 0 0 rgba(185,28,28,0.4); }
      70% { box-shadow: 0 0 0 10px rgba(185,28,28,0); }
      100% { box-shadow: 0 0 0 0 rgba(185,28,28,0); }
    }
    
    .map-highlight {
      animation: pulseMap 1.5s infinite;
      border: 2px solid var(--danger);
      z-index: 10;
    }

    .sensor-value {
      font-size: 1.5rem;
      font-weight: 600;
    }
    
    @media (max-width: 640px) {
      .sensor-value {
        font-size: 1.2rem;
      }
    }
    
    .text-muted {
      color: var(--neutral-600);
    }
    
    .tabs-list {
      display: flex;
      background: white;
      border-radius: 40px;
      padding: 0.5rem;
      gap: 0.25rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.02);
      border: 1px solid var(--neutral-200);
      width: fit-content;
      flex-wrap: wrap;
    }
    
    @media (max-width: 768px) {
      .tabs-list {
        border-radius: 30px;
        padding: 0.4rem;
        width: 100%;
        justify-content: center;
      }
      .tabs-trigger {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
      }
      .dashboard-card {
        padding: 1.2rem;
      }
      .chart-container {
        height: 160px;
      }
    }
    
    .tabs-trigger {
      padding: 0.6rem 1.6rem;
      border-radius: 32px;
      font-size: 0.9rem;
      font-weight: 500;
      color: var(--neutral-700);
      transition: all 0.15s ease;
      cursor: pointer;
      background: transparent;
      border: none;
      display: flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
    }
    
    .tabs-trigger i {
      font-size: 0.9rem;
      color: var(--neutral-600);
      transition: color 0.15s ease;
    }
    
    .tabs-trigger.active {
      background: var(--primary);
      color: white;
      box-shadow: 0 6px 12px rgba(30,74,122,0.2);
    }
    
    .tabs-trigger.active i {
      color: white;
    }
    
    .stat-value {
      font-size: 2.5rem;
      font-weight: 700;
      line-height: 1.2;
      color: var(--neutral-900);
    }
    
    @media (max-width: 640px) {
      .stat-value {
        font-size: 2rem;
      }
    }

    .connection-status {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 30px;
      font-size: 0.8rem;
      font-weight: 500;
    }
    
    .connection-status.connected {
      background: var(--success-light);
      color: var(--success);
    }
    
    .connection-status.disconnected {
      background: var(--danger-light);
      color: var(--danger);
    }
    
    .connection-status.connecting {
      background: var(--warning-light);
      color: var(--warning);
    }
    
    .connection-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      animation: pulse 1.5s infinite;
    }
    
    .connection-status.connected .connection-dot {
      background: var(--success);
    }
    
    .connection-status.disconnected .connection-dot {
      background: var(--danger);
    }
    
    .connection-status.connecting .connection-dot {
      background: var(--warning);
    }
    
    .last-update {
      font-size: 0.7rem;
      color: var(--neutral-600);
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .room-card {
      background: var(--neutral-50);
      border-radius: 1rem;
      padding: 1rem;
      transition: all 0.2s ease;
      cursor: pointer;
      border: 1px solid var(--neutral-200);
    }
    
    .room-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    }
    
    .room-card.active {
      border-color: var(--primary);
      background: var(--primary-soft);
    }
    
    .room-name {
      font-weight: 600;
      font-size: 1rem;
    }
    
    .room-form {
      background: var(--neutral-100);
      border-radius: 1rem;
      padding: 1rem;
    }

    .waiting-card {
      background: linear-gradient(135deg, var(--primary-soft) 0%, white 100%);
      border: 1px dashed var(--primary);
    }
    
    .pulse-warning {
      animation: pulseWarning 2s infinite;
    }
    
    @keyframes pulseWarning {
      0% { opacity: 0.6; }
      50% { opacity: 1; }
      100% { opacity: 0.6; }
    }

    .loading-spinner {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 2px solid rgba(30,74,122,0.3);
      border-radius: 50%;
      border-top-color: var(--primary);
      animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .data-waiting {
      color: var(--neutral-600);
      font-style: italic;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    /* Bouton View All Alerts */
    .btn-view-all-alerts {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      background: #dc2626;
      color: white;
      padding: 10px 20px;
      border-radius: 30px;
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s ease;
      box-shadow: 0 4px 12px rgba(220,38,38,0.3);
    }
    
    .btn-view-all-alerts:hover {
      background: #b91c1c;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(220,38,38,0.4);
      text-decoration: none;
      color: white;
    }
    
    .btn-view-all-history {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      background: #1e4a7a;
      color: white;
      padding: 10px 20px;
      border-radius: 30px;
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.2s ease;
      box-shadow: 0 4px 12px rgba(30,74,122,0.3);
    }
    
    .btn-view-all-history:hover {
      background: #0f2b4b;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(30,74,122,0.4);
      text-decoration: none;
      color: white;
    }
  </style>
</head>
<body class="bg-[#f6f9fd] p-4 md:p-5 font-sans antialiased">

<!-- Notification Container -->
<div id="notificationContainer" class="notification-container"></div>

<div class="max-w-[1600px] mx-auto space-y-4 md:space-y-6">

  <!-- ===== HEADER ===== -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 md:gap-4">
      <div class="logo-environet flex items-center gap-2 self-start sm:self-auto">
        <div class="w-10 h-10 bg-gradient-to-br from-[#1e4a7a] to-[#0f2b4b] rounded-xl flex items-center justify-center text-white text-xl shadow-md overflow-hidden">
          <img src="logo.png" alt="EnviroNet logo" onerror="this.src='https://via.placeholder.com/70x70?text=EN'" class="w-full h-full object-cover">
        </div>
        <span class="font-bold text-xl md:text-2xl text-[#0f2b4b]">EnviroNet</span>
      </div>
      <div>
        <h1 class="text-xl md:text-2xl font-bold text-[#1e2a36]">ESP32 Network Monitor</h1>
        <p class="text-[#4b5a6b] text-sm" id="dateDisplay"></p>
      </div>
    </div>
    
    <div class="flex items-center gap-3 self-end md:self-auto">
      <div id="connectionStatus" class="connection-status connecting">
        <span class="connection-dot"></span>
        <span id="connectionText">Waiting for ESP32...</span>
      </div>
      
      <div class="notification-badge" onclick="showDemoNotifications()">
        <i class="fas fa-bell"></i>
        <span class="count" id="notificationCount">0</span>
      </div>
      <div class="user-profile-badge">
        <div class="flex flex-col items-end text-sm">
          <span class="font-semibold text-[#1e4a7a]"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
          <span class="text-xs text-[#6b7a8c]"><?= htmlspecialchars($_SESSION['user_email']) ?></span>
        </div>
        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-[#1e4a7a] to-[#0f2b4b] flex items-center justify-center text-white font-bold shadow-sm">
          <i class="fas fa-user-circle text-xl"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Last update timestamp -->
  <div class="last-update">
    <i class="fas fa-sync-alt" id="refreshIcon"></i>
    <span id="lastUpdateTime">Waiting for ESP32 data...</span>
  </div>

  <!-- ===== TABS (SANS ALERTS ET HISTORY) ===== -->
  <div class="tabs-list" id="tabContainer">
    <button class="tabs-trigger active" data-tab="overview"><i class="fas fa-chart-pie"></i> <span>Overview</span></button>
    <button class="tabs-trigger" data-tab="network"><i class="fas fa-wifi"></i> <span>WiFi Network</span></button>
    <button class="tabs-trigger" data-tab="environment"><i class="fas fa-temperature-low"></i> <span>Environment</span></button>
    <button class="tabs-trigger" data-tab="rooms"><i class="fas fa-door-open"></i> <span>Rooms</span></button>
    <button class="tabs-trigger" data-tab="map"><i class="fas fa-map-marked-alt"></i> <span>Map</span></button>
  </div>

  <!-- ############### OVERVIEW TAB ############### -->
  <div id="overview-content" class="tab-content space-y-4 md:space-y-6">
    <!-- ESP32-CAM waiting message -->
    <div id="waitingMessage" class="dashboard-card waiting-card text-center hidden">
      <div class="flex flex-col items-center gap-3">
        <i class="fas fa-microchip text-4xl text-[#1e4a7a]"></i>
        <h3 class="font-semibold text-lg">Waiting for ESP32 connection</h3>
        <p class="text-sm text-muted">The ESP32 module is not yet connected. Data will be displayed automatically once the connection is established.</p>
        <div class="flex gap-2 mt-2">
          <span class="badge-opt"><i class="fas fa-circle-notch fa-spin mr-1"></i> MQTT connection in progress...</span>
          <span class="badge-opt"><i class="fas fa-wifi"></i> Waiting for data</span>
        </div>
      </div>
    </div>

    <!-- First row: Network stats + ESP32-CAM info + ALERTS CARD -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 md:gap-5">
      <div class="dashboard-card flex flex-col">
        <span class="text-xs md:text-sm text-muted">WiFi Signal</span>
        <div class="stat-value mt-1" id="overviewSignal">--</div>
        <div class="flex items-center mt-2">
          <span class="status-dot dot-yellow" id="overviewSignalDot"></span>
          <span class="text-xs font-medium" id="overviewSignalStatus">Waiting</span>
        </div>
      </div>
      
      <div class="dashboard-card flex flex-col">
        <span class="text-xs md:text-sm text-muted">Bandwidth</span>
        <div class="stat-value mt-1" id="overviewBandwidth">--</div>
        <div class="text-xs flex items-center gap-1 mt-2" id="overviewBandwidthTrend">
          <span>--</span>
        </div>
      </div>
      
      <div class="dashboard-card flex flex-col">
        <span class="text-xs md:text-sm text-muted">Temperature</span>
        <div class="stat-value mt-1" id="overviewTemp">--°C</div>
        <span class="text-xs font-medium mt-2" id="overviewTempStatus">Waiting</span>
      </div>

      <div class="dashboard-card flex flex-col">
        <span class="text-xs md:text-sm text-muted">Humidity</span>
        <div class="stat-value mt-1" id="overviewHum">--%</div>
        <span class="text-xs font-medium mt-2" id="overviewHumStatus">Waiting</span>
      </div>

      <!-- Alerts Card avec BOUTON View All -->
      <div class="dashboard-card flex flex-col bg-gradient-to-br from-red-50 to-white border-l-4 border-red-500">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs md:text-sm text-muted">Alerts</span>
          <i class="fas fa-exclamation-triangle text-red-500 text-lg"></i>
        </div>
        <div class="flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <span class="text-xs text-muted">Critical</span>
            <span class="text-lg font-bold text-red-600" id="overviewCriticalCount">0</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-xs text-muted">Warning</span>
            <span class="text-lg font-bold text-orange-600" id="overviewWarningCount">0</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-xs text-muted">Info</span>
            <span class="text-lg font-bold text-green-600" id="overviewInfoCount">0</span>
          </div>
        </div>
        <div class="mt-3 pt-2 border-t border-gray-200">
          <a href="alerts.php" class="btn-view-all-alerts">
            <i class="fas fa-arrow-right"></i>
            <span>View All Alerts</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Second row: Real-time charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
      <div class="dashboard-card">
        <h3 class="font-semibold mb-2 text-sm flex items-center gap-2">
          <i class="fas fa-chart-line text-[#1e4a7a]"></i>
          <span>Signal Strength (real-time)</span>
        </h3>
        <div class="chart-container">
          <canvas id="signalRealtimeChart"></canvas>
        </div>
        <div class="flex justify-between text-xs text-muted mt-2" id="signalTimeLabels">
          <span>--</span><span>--</span><span>--</span><span>--</span><span>--</span><span>--</span><span>--</span>
        </div>
        <div class="text-right text-xs text-muted mt-1" id="signalValue">Signal: --</div>
      </div>
      
      <div class="dashboard-card">
        <h3 class="font-semibold mb-2 text-sm flex items-center gap-2">
          <i class="fas fa-chart-line text-[#b6560c]"></i>
          <span>Temperature (real-time)</span>
        </h3>
        <div class="chart-container">
          <canvas id="tempRealtimeChart"></canvas>
        </div>
        <div class="flex justify-between text-xs text-muted mt-2" id="tempTimeLabels">
          <span>--</span><span>--</span><span>--</span><span>--</span><span>--</span><span>--</span><span>--</span>
        </div>
        <div class="text-right text-xs text-muted mt-1" id="tempValue">Temperature: --</div>
      </div>
    </div>

    <!-- Third row: Bandwidth + Humidity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
      <div class="dashboard-card">
        <h3 class="font-semibold mb-2 text-sm flex items-center gap-2">
          <i class="fas fa-tachometer-alt text-[#b6560c]"></i>
          <span>Bandwidth Usage (real-time)</span>
        </h3>
        <div class="chart-container">
          <canvas id="bandwidthOverviewChart"></canvas>
        </div>
        <div class="flex justify-between text-xs text-muted mt-2" id="bandwidthTimeLabels">
          <span>--</span><span>--</span><span>--</span><span>--</span><span>--</span><span>--</span><span>--</span>
        </div>
      </div>
      
      <div class="dashboard-card">
        <h3 class="font-semibold mb-2 text-sm">Humidity (real-time)</h3>
        <div class="chart-container-extra-small">
          <canvas id="humidityGaugeChart"></canvas>
        </div>
        <div class="flex justify-around mt-3 text-sm">
          <div><span class="font-bold text-[#1e2a36]" id="currentHumidity">--</span> <span class="text-xs text-muted">%</span></div>
          <div><span class="font-bold text-[#1e2a36]" id="humidityStatus">--</span></div>
        </div>
      </div>
    </div>

    <!-- Fourth row: Network Info + Environmental stats -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
      <div class="dashboard-card">
        <h3 class="font-semibold mb-3">WiFi Network Info (ESP32)</h3>
        <div class="bg-[#f2f5f9] p-4 md:p-5 rounded-2xl border border-[#e9eef4]">
          <div class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-sm text-muted">SSID</span>
              <span class="font-medium text-[#1e2a36]" id="networkSSID">
                <span class="data-waiting"><i class="fas fa-circle-notch fa-spin"></i> Waiting...</span>
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-muted">IP Address</span>
              <span class="font-medium text-[#1e2a36]" id="networkIP">
                <span class="data-waiting"><i class="fas fa-circle-notch fa-spin"></i> Waiting...</span>
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-muted">MAC Address</span>
              <span class="font-mono text-xs text-[#4b5a6b]" id="networkMAC">
                <span class="data-waiting"><i class="fas fa-circle-notch fa-spin"></i> Waiting...</span>
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-muted">RSSI</span>
              <span class="font-medium" id="networkRSSI">-- dBm</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-muted">Status</span>
              <span class="badge-opt" id="networkStatusBadge">
                <i class="fas fa-check-circle mr-1"></i> Connected
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="dashboard-card">
        <h3 class="font-semibold mb-3">Environmental Conditions (ESP32)</h3>
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-[#f2f5f9] p-3 rounded-xl">
            <div class="text-xs text-muted mb-1">Temperature</div>
            <div class="text-2xl md:text-3xl font-bold text-[#1e2a36]" id="envTemperature">--°C</div>
            <span class="badge-opt mt-2" id="envTempBadge">Waiting</span>
          </div>
          <div class="bg-[#f2f5f9] p-3 rounded-xl">
            <div class="text-xs text-muted mb-1">Humidity</div>
            <div class="text-2xl md:text-3xl font-bold text-[#1e2a36]" id="envHumidity">--%</div>
            <span class="badge-opt mt-2" id="envHumBadge">Waiting</span>
          </div>
          <div class="bg-[#f2f5f9] p-3 rounded-xl">
            <div class="text-xs text-muted mb-1">Rooms</div>
            <div class="text-2xl md:text-3xl font-bold text-[#1e2a36]" id="envRooms">1</div>
            <span class="text-xs text-muted" id="envRoomsDetail">1 active room</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Fifth row: Current room and sensor data -->
    <div class="dashboard-card">
      <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <h3 class="font-semibold text-lg">ESP32 Sensor Data (Real-time)</h3>
        <div class="live-indicator">
          <span class="live-dot"></span>
          <span>Live MQTT</span>
        </div>
      </div>

      <div class="bg-gradient-to-br from-[#f9fafc] to-white p-4 md:p-5 rounded-2xl border border-[#e9eef4]">
        <div class="flex flex-col lg:flex-row gap-4 lg:gap-6">
          <div class="min-w-[180px]">
            <div class="font-bold text-xl text-[#1e2a36]" id="sensorRoomName">Main Room</div>
            <div class="text-sm text-muted" id="sensorRoomLocation">ESP32-CAM Location</div>
          </div>
          
          <div class="flex-1">
            <div class="flex flex-wrap items-center gap-2 mb-3">
              <i class="fas fa-microchip text-[#1e4a7a]"></i>
              <span class="font-medium">ESP32 Sensor</span>
              <span class="text-xs bg-[#e9eef4] text-[#4b5a6b] px-2 py-0.5 rounded-full">MQTT real-time</span>
            </div>
            
            <div class="flex flex-wrap gap-4 md:gap-6">
              <div>
                <div class="text-xs text-muted">Temperature</div>
                <div class="sensor-value font-bold text-[#b6560c]" id="sensorTemp">--°C</div>
              </div>
              <div>
                <div class="text-xs text-muted">Humidity</div>
                <div class="sensor-value font-bold text-[#1e4a7a]" id="sensorHum">--%</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- History Button -->
    <div class="dashboard-card text-center">
      <a href="history.php" class="btn-view-all-history">
        <i class="fas fa-history"></i>
        <span>View Full History</span>
      </a>
    </div>
  </div>

  <!-- ############### NETWORK TAB ############### -->
  <div id="network-content" class="tab-content hidden space-y-4 md:space-y-6">
    <div class="dashboard-card">
      <div class="flex flex-wrap justify-between items-center mb-4">
        <h2 class="text-lg md:text-xl font-semibold flex items-center gap-2">
          <i class="fas fa-wifi text-[#1e4a7a]"></i>
          <span>ESP32 Network – Real-time Monitoring</span>
        </h2>
        <div class="live-indicator">
          <span class="live-dot"></span>
          <span>LIVE MQTT</span>
        </div>
      </div>
      
      <div class="mb-4 p-3 bg-[#fff1e0] rounded-xl text-sm">
        <i class="fas fa-info-circle text-[#b6560c] mr-2"></i>
        <span>Real-time network data from ESP32</span>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-[#f2f5f9] p-4 rounded-xl">
          <div class="text-sm text-muted mb-1">Network Health</div>
          <div class="text-2xl font-bold text-[#1e2a36]" id="networkHealth">--</div>
          <div class="network-status mt-2" id="networkStatus">Waiting</div>
        </div>
        <div class="bg-[#f2f5f9] p-4 rounded-xl">
          <div class="text-sm text-muted mb-1">Network Load</div>
          <div class="text-2xl font-bold text-[#1e2a36]" id="networkLoad">--</div>
          <div class="w-full bg-[#e9eef4] h-2 rounded-full mt-2">
            <div class="bg-[#1e4a7a] h-2 rounded-full" style="width: 0%" id="loadBar"></div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        <div class="bg-[#f2f5f9] p-3 rounded-xl">
          <div class="text-xs text-muted">Signal Strength</div>
          <div class="text-xl md:text-2xl font-bold text-[#1e2a36]" id="avgSignal">--</div>
        </div>
        <div class="bg-[#f2f5f9] p-3 rounded-xl">
          <div class="text-xs text-muted">Bandwidth</div>
          <div class="text-xl md:text-2xl font-bold text-[#1e2a36]" id="bandwidthValue">--</div>
        </div>
        <div class="bg-[#f2f5f9] p-3 rounded-xl">
          <div class="text-xs text-muted">Latency</div>
          <div class="text-xl md:text-2xl font-bold text-[#1e2a36]" id="pingValue">--</div>
        </div>
      </div>

      <div class="grid lg:grid-cols-1 gap-6">
        <div>
          <h3 class="font-medium text-sm mb-2">Signal Strength (24h history)</h3>
          <div class="chart-container">
            <canvas id="networkSignalHist"></canvas>
          </div>
        </div>
      </div>

      <div class="grid lg:grid-cols-2 gap-6 mt-6">
        <div>
          <h3 class="font-medium text-sm mb-2">Bandwidth (24h history)</h3>
          <div class="chart-container">
            <canvas id="networkBandwidthHist"></canvas>
          </div>
        </div>
        <div>
          <h3 class="font-medium text-sm mb-2">Latency (24h history)</h3>
          <div class="chart-container">
            <canvas id="networkLatencyHist"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- History Button -->
    <div class="dashboard-card text-center">
      <a href="history.php" class="btn-view-all-history">
        <i class="fas fa-history"></i>
        <span>View Full History</span>
      </a>
    </div>
  </div>

  <!-- ############### ENVIRONMENT TAB ############### -->
  <div id="environment-content" class="tab-content hidden space-y-4 md:space-y-6">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-5">
      <div class="dashboard-card">
        <i class="fas fa-temperature-high text-[#b6560c] text-xl mb-2"></i>
        <div class="text-sm text-muted">Temperature</div>
        <div class="text-2xl md:text-3xl font-bold text-[#1e2a36]" id="envTempStat">--°C</div>
        <span class="badge-opt mt-2" id="envTempStatBadge">--</span>
      </div>
      <div class="dashboard-card">
        <i class="fas fa-tint text-[#1e4a7a] text-xl mb-2"></i>
        <div class="text-sm text-muted">Humidity</div>
        <div class="text-2xl md:text-3xl font-bold text-[#1e2a36]" id="envHumStat">--%</div>
        <span class="badge-opt mt-2" id="envHumStatBadge">--</span>
      </div>
      <div class="dashboard-card">
        <i class="fas fa-door-open text-[#6b7a8c] text-xl mb-2"></i>
        <div class="text-sm text-muted">Rooms</div>
        <div class="text-2xl md:text-3xl font-bold text-[#1e2a36]" id="roomsCount">1</div>
        <span class="text-xs text-muted">1 room</span>
      </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 md:gap-6">
      <div class="dashboard-card">
        <h3 class="text-sm font-semibold mb-2">Temperature (24h history)</h3>
        <div class="chart-container">
          <canvas id="temp24Chart"></canvas>
        </div>
        <div class="flex justify-between text-[0.55rem] text-muted mt-2" id="temp24Labels">
          <span>0h</span><span>2h</span><span>4h</span><span>6h</span><span>8h</span><span>10h</span><span>12h</span><span>14h</span><span>16h</span><span>18h</span><span>20h</span><span>22h</span>
        </div>
      </div>
      <div class="dashboard-card">
        <h3 class="text-sm font-semibold mb-2">Humidity (24h history)</h3>
        <div class="chart-container">
          <canvas id="hum24Chart"></canvas>
        </div>
        <div class="flex justify-between text-[0.55rem] text-muted mt-2" id="hum24Labels">
          <span>0h</span><span>2h</span><span>4h</span><span>6h</span><span>8h</span><span>10h</span><span>12h</span><span>14h</span><span>16h</span><span>18h</span><span>20h</span><span>22h</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ############### ROOMS TAB ############### -->
  <div id="rooms-content" class="tab-content hidden space-y-4 md:space-y-6">
    <div class="dashboard-card">
      <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
        <h2 class="text-lg md:text-xl font-semibold flex items-center gap-2">
          <i class="fas fa-door-open text-[#1e4a7a]"></i>
          <span>Room Management</span>
        </h2>
        <button id="addRoomBtn" class="bg-[#1e4a7a] text-white px-5 py-2 rounded-full text-sm font-medium hover:bg-[#0f2b4b] transition flex items-center gap-2 shadow-md">
          <i class="fas fa-plus-circle"></i>
          <span>Add Room</span>
        </button>
      </div>

      <div id="roomFormContainer" class="room-form mb-6 hidden">
        <h3 class="font-semibold mb-3">Add New Room</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-[#4b5a6b] mb-1">Room Name</label>
            <input type="text" id="roomNameInput" class="w-full px-4 py-2 border border-[#e9eef4] rounded-xl focus:outline-none focus:border-[#1e4a7a]" placeholder="e.g., Conference Room">
          </div>
          <div>
            <label class="block text-sm font-medium text-[#4b5a6b] mb-1">Location</label>
            <input type="text" id="roomLocationInput" class="w-full px-4 py-2 border border-[#e9eef4] rounded-xl focus:outline-none focus:border-[#1e4a7a]" placeholder="e.g., Floor 2, Building A">
          </div>
        </div>
        <div class="flex gap-3">
          <button id="saveRoomBtn" class="bg-[#0d7c4b] text-white px-5 py-2 rounded-full text-sm font-medium hover:bg-[#0a5a38] transition">Save Room</button>
          <button id="cancelRoomBtn" class="bg-[#e9eef4] text-[#4b5a6b] px-5 py-2 rounded-full text-sm font-medium hover:bg-[#d4e3f5] transition">Cancel</button>
        </div>
      </div>

      <div>
        <h3 class="font-semibold mb-3">Rooms List</h3>
        <div id="roomsList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <?php foreach($rooms as $room): ?>
          <div class="room-card" data-room-id="<?= $room['id'] ?>">
            <div class="flex justify-between items-start mb-2">
              <div>
                <div class="room-name"><?= htmlspecialchars($room['name']) ?></div>
                <div class="text-xs text-muted"><?= htmlspecialchars($room['location']) ?></div>
              </div>
              <span class="text-xs bg-[#e3f7ed] text-[#0d7c4b] px-2 py-1 rounded-full">Active</span>
            </div>
            <div class="flex justify-between items-center mt-3 pt-2 border-t border-[#e9eef4]">
              <div>
                <div class="text-xs text-muted">Temperature</div>
                <div class="font-semibold text-sm" id="roomTemp_<?= $room['id'] ?>">--°C</div>
              </div>
              <div>
                <div class="text-xs text-muted">Humidity</div>
                <div class="font-semibold text-sm" id="roomHum_<?= $room['id'] ?>">--%</div>
              </div>
              <button class="text-[#b91c1c] text-xs hover:text-[#7f1a1a]" onclick="deleteRoom('<?= $room['id'] ?>')">
                <i class="fas fa-trash-alt"></i>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="mt-4 p-3 bg-[#f2f5f9] rounded-xl text-center">
        <i class="fas fa-info-circle text-[#1e4a7a] mr-2"></i>
        <span class="text-sm text-muted">Real-time ESP32 data over MQTT</span>
      </div>
    </div>
  </div>

  <!-- ############### MAP TAB ############### -->
  <div id="map-content" class="tab-content hidden space-y-4 md:space-y-6">
    <div class="dashboard-card">
      <h2 class="text-lg md:text-xl font-semibold mb-3">Room Mapping</h2>
      
      <div class="flex flex-wrap gap-4 text-sm mb-4">
        <span class="flex items-center"><span class="status-dot dot-green"></span> Normal (18-24°C)</span>
        <span class="flex items-center"><span class="status-dot dot-yellow"></span> Warning (24-28°C)</span>
        <span class="flex items-center"><span class="status-dot dot-red"></span> Critical (>28°C)</span>
      </div>

      <div id="mapContainer" class="relative bg-[#d9e2ec] h-[350px] md:h-[400px] rounded-2xl overflow-hidden border border-[#cbd5e1]">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, #94a3b8 1px, transparent 1px); background-size: 30px 30px;"></div>
        <div id="mapMarkersContainer" class="absolute inset-0">
        </div>
      </div>
      
      <div id="selectedAlertInfo" class="mt-4 p-3 bg-[#f2f5f9] rounded-xl hidden">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="fas fa-info-circle text-[#1e4a7a]"></i>
            <span id="selectedAlertMessage" class="font-medium"></span>
          </div>
          <button onclick="hideAlertInfo()" class="text-muted hover:text-[#1e2a36]">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- History Button -->
    <div class="dashboard-card text-center">
      <a href="history.php" class="btn-view-all-history">
        <i class="fas fa-history"></i>
        <span>View Full History</span>
      </a>
    </div>
  </div>

  <!-- ===== LOGOUT BUTTON ===== -->
  <div class="logout-footer">
    <a href="logout.php" class="logout-btn-bottom" style="text-decoration: none;">
      <i class="fas fa-sign-out-alt"></i>
      <span>Logout</span>
    </a>
  </div>

  <div class="text-center text-xs md:text-sm text-muted border-t border-[#e9eef4] pt-6 mt-6">
    ESP32 Network Monitor · Real-time data over MQTT
  </div>
</div>

<script>
// Pass PHP data to JavaScript
const phpData = {
    sensorData: <?= json_encode($sensorData) ?>,
    networkData: <?= json_encode($networkData) ?>,
    rooms: <?= json_encode($rooms) ?>,
    historyData: <?= json_encode($historyData) ?>,
    alerts: <?= json_encode($allAlerts) ?>,
    userId: <?= $user_id ?>
};

// Initialize JavaScript variables
let envData = {
    temperature: phpData.sensorData && phpData.sensorData.temperature ? parseFloat(phpData.sensorData.temperature) : null,
    humidity: phpData.sensorData && phpData.sensorData.humidity ? parseFloat(phpData.sensorData.humidity) : null,
    timestamp: null
};

let networkDataJS = {
    signal: phpData.networkData && phpData.networkData.signal_strength ? parseInt(phpData.networkData.signal_strength) : null,
    bandwidth: phpData.networkData && phpData.networkData.bandwidth ? parseFloat(phpData.networkData.bandwidth) : null,
    ping: phpData.networkData && phpData.networkData.ping ? parseInt(phpData.networkData.ping) : null,
    timestamp: null
};

let rooms = phpData.rooms || [];
let allAlerts = phpData.alerts || [];
let notificationCount = 0;

// Initialize history
let history = {
    signal: [],
    bandwidth: [],
    ping: [],
    temperature: [],
    humidity: [],
    timestamps: []
};

if (phpData.historyData && phpData.historyData.length > 0) {
    phpData.historyData.forEach(item => {
        const time = new Date(item.timestamp).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        history.timestamps.push(time);
        if (item.temperature) history.temperature.push(parseFloat(item.temperature));
        if (item.humidity) history.humidity.push(parseFloat(item.humidity));
        if (item.signal_strength) history.signal.push(parseInt(item.signal_strength));
        if (item.bandwidth) history.bandwidth.push(parseFloat(item.bandwidth));
        if (item.ping) history.ping.push(parseInt(item.ping));
    });
}

// Variables MQTT
const MQTT_CONFIG = {
    broker: 'wss://broker.hivemq.com:8884/mqtt',
    options: {
        clientId: 'environet_' + Math.random().toString(16).substr(2, 8),
        clean: true,
        connectTimeout: 4000,
        reconnectPeriod: 5000
    },
    topics: {
        temperature: "esp32cam/temperature",
        humidity: "esp32cam/humidity",
        signal: "esp32cam/signal",
        bandwidth: "esp32cam/bandwidth",
        ping: "esp32cam/ping",
        status: "esp32cam/status",
        ssid: "esp32cam/ssid",
        ip: "esp32cam/ip",
        mac: "esp32cam/mac"
    }
};

let mqttClient = null;
let esp32CamConnected = false;

// Variables pour les graphiques
let signalChart, tempChart, bandwidthChart, humidityGaugeChart;
let networkSignalHist, networkBandwidthHist, networkLatencyHist, temp24Chart, hum24Chart;

// Variables pour le throttle d'insertion
let insertThrottleTimer = null;
let pendingInsertData = {};

let currentTemp = null;
let currentHum = null;

// ===== FONCTIONS UTILITAIRES =====

function updateDateTime() {
    const now = new Date();
    const dateDisplay = document.getElementById('dateDisplay');
    if(dateDisplay) {
        dateDisplay.innerText = now.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

function updateElementText(id, value) {
    const el = document.getElementById(id);
    if(el) el.innerText = value;
}

function safeUpdate(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    
    const waitingSpan = el.querySelector('.data-waiting');
    if (waitingSpan) {
        el.textContent = value;
    } else {
        el.textContent = value;
    }
}

function escapeHtml(str) {
    if(!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// ===== FONCTIONS D'INSERTION DE DONNÉES =====

function throttledInsertSensorData(data) {
    Object.assign(pendingInsertData, data);
    
    if (insertThrottleTimer) {
        clearTimeout(insertThrottleTimer);
    }
    
    insertThrottleTimer = setTimeout(() => {
        insertSensorData(pendingInsertData);
        pendingInsertData = {};
        insertThrottleTimer = null;
    }, 2000);
}

function insertSensorData(data) {
    if (!data || Object.keys(data).length === 0) return;
    
    const payload = {
        user_id: phpData.userId,
        temperature: data.temperature !== undefined ? data.temperature : null,
        humidity: data.humidity !== undefined ? data.humidity : null,
        signal_strength: data.signal !== undefined ? data.signal : null,
        bandwidth: data.bandwidth !== undefined ? data.bandwidth : null,
        ping: data.ping !== undefined ? data.ping : null,
        ssid: data.ssid || null,
        ip_address: data.ip || null,
        mac_address: data.mac || null
    };
    
    if (payload.temperature === null && payload.humidity === null && 
        payload.signal_strength === null && payload.bandwidth === null && payload.ping === null) {
        return;
    }
    
    fetch('insert.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(result => {
        if(result.success) {
            console.log('Data inserted successfully');
        } else {
            console.error('Error inserting data:', result.message);
        }
    })
    .catch(error => {
        console.error('Network Error:', error);
    });
}

// ===== FONCTIONS DE MISE À JOUR DES AFFICHAGES =====

function updateSignalDisplay(value) {
    updateElementText('overviewSignal', value + '%');
    updateElementText('signalValue', `Signal: ${value}%`);
    updateElementText('avgSignal', value + '%');
    
    const rssi = Math.round((value / 100) * -30 - 30);
    updateElementText('networkRSSI', rssi + ' dBm');
    
    const signalDot = document.getElementById('overviewSignalDot');
    const signalStatus = document.getElementById('overviewSignalStatus');
    
    if(signalDot && signalStatus) {
        if(value >= 70) {
            signalDot.className = 'status-dot dot-green';
            signalStatus.innerText = 'Excellent';
        } else if(value >= 40) {
            signalDot.className = 'status-dot dot-yellow';
            signalStatus.innerText = 'Average';
        } else {
            signalDot.className = 'status-dot dot-red';
            signalStatus.innerText = 'Poor';
        }
    }
    
    updateElementText('networkHealth', value >= 70 ? '98%' : (value >= 40 ? '75%' : '45%'));
    
    const healthStatus = document.getElementById('networkStatus');
    if(healthStatus) {
        if(value >= 70) {
            healthStatus.className = 'network-status status-stable';
            healthStatus.innerText = 'Stable';
        } else if(value >= 40) {
            healthStatus.className = 'network-status status-degraded';
            healthStatus.innerText = 'Degraded';
        } else {
            healthStatus.className = 'network-status status-critical';
            healthStatus.innerText = 'Critical';
        }
    }
    
    const loadBar = document.getElementById('loadBar');
    const networkLoad = document.getElementById('networkLoad');
    if(loadBar && networkLoad) {
        const loadPercent = 100 - value;
        loadBar.style.width = loadPercent + '%';
        networkLoad.innerText = loadPercent + '%';
    }
}

function updateTemperatureDisplay(value) {
    updateElementText('overviewTemp', value.toFixed(1) + '°C');
    updateElementText('sensorTemp', value.toFixed(1) + '°C');
    updateElementText('envTemperature', value.toFixed(1) + '°C');
    updateElementText('envTempStat', value.toFixed(1) + '°C');
    updateElementText('tempValue', `Temperature: ${value.toFixed(1)}°C`);
    
    rooms.forEach(room => {
        updateElementText(`roomTemp_${room.id}`, value.toFixed(1) + '°C');
    });
    
    let status = 'Optimal';
    let badgeText = 'Optimal (18-24°C)';
    let badgeClass = 'badge-opt';
    
    if(value < 18) {
        status = 'Cold';
        badgeText = 'Cold (<18°C)';
    } else if(value > 24 && value <= 28) {
        status = 'High';
        badgeText = 'High (24-28°C)';
        badgeClass = 'bg-[#fff1e0] text-[#b6560c]';
    } else if(value > 28) {
        status = 'Critical';
        badgeText = 'Critical (>28°C)';
        badgeClass = 'bg-[#fee9e9] text-[#b91c1c]';
    }
    
    const tempStatus = document.getElementById('overviewTempStatus');
    if(tempStatus) tempStatus.innerText = status;
    
    const tempBadge = document.getElementById('envTempBadge');
    if(tempBadge) {
        tempBadge.innerText = badgeText;
        tempBadge.className = badgeClass + ' mt-2';
    }
    
    const tempStatBadge = document.getElementById('envTempStatBadge');
    if(tempStatBadge) tempStatBadge.innerText = status;
}

function updateHumidityDisplay(value) {
    updateElementText('overviewHum', value.toFixed(0) + '%');
    updateElementText('sensorHum', value.toFixed(0) + '%');
    updateElementText('envHumidity', value.toFixed(0) + '%');
    updateElementText('envHumStat', value.toFixed(0) + '%');
    updateElementText('currentHumidity', value.toFixed(0));
    
    rooms.forEach(room => {
        updateElementText(`roomHum_${room.id}`, value.toFixed(0) + '%');
    });
    
    let status = 'Normal';
    let badgeText = 'Normal (40-60%)';
    let badgeClass = 'badge-opt';
    
    if(value < 30) {
        status = 'Dry';
        badgeText = 'Dry (<30%)';
        badgeClass = 'bg-[#fff1e0] text-[#b6560c]';
    } else if(value > 60 && value <= 70) {
        status = 'Humid';
        badgeText = 'Humid (60-70%)';
        badgeClass = 'bg-[#fff1e0] text-[#b6560c]';
    } else if(value > 70) {
        status = 'Critical';
        badgeText = 'Critical (>70%)';
        badgeClass = 'bg-[#fee9e9] text-[#b91c1c]';
    }
    
    updateElementText('humidityStatus', status);
    
    const humBadge = document.getElementById('envHumBadge');
    if(humBadge) {
        humBadge.innerText = badgeText;
        humBadge.className = badgeClass + ' mt-2';
    }
    
    const humStatBadge = document.getElementById('envHumStatBadge');
    if(humStatBadge) humStatBadge.innerText = status;
    
    if(humidityGaugeChart) {
        humidityGaugeChart.data.datasets[0].data = [value, 100 - value];
        humidityGaugeChart.update();
    }
}

function updateBandwidthDisplay(value) {
    updateElementText('overviewBandwidth', value.toFixed(1) + ' Mbps');
    updateElementText('bandwidthValue', value.toFixed(1) + ' Mbps');
    
    const bwTrend = document.getElementById('overviewBandwidthTrend');
    if(bwTrend) {
        bwTrend.innerHTML = `<i class="fas fa-arrow-up text-[#0d7c4b]"></i>
            <span class="font-medium text-[#0d7c4b]">${value.toFixed(1)} Mbps</span>`;
    }
}

function updatePingDisplay(value) {
    updateElementText('pingValue', value + ' ms');
}

function updateLastUpdateTime(source) {
    const now = new Date();
    const timeElement = document.getElementById('lastUpdateTime');
    if(timeElement) {
        timeElement.innerText = `${source}: ${now.toLocaleTimeString('en-US')}`;
    }
    
    const icon = document.getElementById('refreshIcon');
    if(icon) {
        icon.style.transform = 'rotate(360deg)';
        icon.style.transition = 'transform 0.5s';
        setTimeout(() => {
            icon.style.transform = 'rotate(0deg)';
        }, 500);
    }
}

// ===== FONCTIONS DE MISE À JOUR DES ALERTES =====

function updateOverviewAlerts(alerts) {
    const criticalCount = document.getElementById('overviewCriticalCount');
    const warningCount = document.getElementById('overviewWarningCount');
    const infoCount = document.getElementById('overviewInfoCount');
    
    let critical = 0, warning = 0, info = 0, unread = 0;
    
    // ✅ Vérifier que alerts existe et est un tableau
    if (alerts && Array.isArray(alerts) && alerts.length > 0) {
        alerts.forEach(a => {
            const severity = (a.severity || a.type || 'info').toLowerCase();
            if (severity === 'critical') critical++;
            else if (severity === 'warning') warning++;
            else info++;
            
            // Compter les non lus
            if ((a.status || 'unread') === 'unread') unread++;
        });
    }
    
    // Mettre à jour les compteurs
    if (criticalCount) criticalCount.innerText = critical;
    if (warningCount) warningCount.innerText = warning;
    if (infoCount) infoCount.innerText = info;
    
    // Mettre à jour le badge de notification
    notificationCount = unread;
    const countElement = document.getElementById('notificationCount');
    if (countElement) {
        countElement.innerText = unread;
        countElement.style.display = unread > 0 ? 'flex' : 'none';
    }
}

function checkAndGenerateAlerts(temp, hum, signal) {
    const alerts = [];
    const now = new Date().toISOString();
    
    // Vérifier température
    if (temp !== null) {
        if (temp > 28) {
            alerts.push({
                type: 'temperature',
                severity: 'critical',
                message: `⚠️ ALERTE CRITIQUE - Température trop élevée : ${temp}°C (seuil: 28°C)`,
                location: 'ESP32 Sensor'
            });
        } else if (temp > 24) {
            alerts.push({
                type: 'temperature',
                severity: 'warning',
                message: `⚠️ ATTENTION - Température élevée : ${temp}°C (seuil: 24°C)`,
                location: 'ESP32 Sensor'
            });
        }
    }
    
    // Vérifier humidité
    if (hum !== null) {
        if (hum > 80) {
            alerts.push({
                type: 'humidity',
                severity: 'critical',
                message: `⚠️ ALERTE CRITIQUE - Humidité trop élevée : ${hum}% (seuil: 80%)`,
                location: 'ESP32 Sensor'
            });
        } else if (hum > 70) {
            alerts.push({
                type: 'humidity',
                severity: 'warning',
                message: `⚠️ ATTENTION - Humidité élevée : ${hum}% (seuil: 70%)`,
                location: 'ESP32 Sensor'
            });
        }
    }
    
    // Vérifier signal
    if (signal !== null) {
        if (signal < 30) {
            alerts.push({
                type: 'signal',
                severity: 'critical',
                message: `⚠️ ALERTE CRITIQUE - Signal WiFi très faible : ${signal}% (seuil: 30%)`,
                location: 'ESP32 Sensor'
            });
        } else if (signal < 50) {
            alerts.push({
                type: 'signal',
                severity: 'warning',
                message: `⚠️ ATTENTION - Signal WiFi faible : ${signal}% (seuil: 50%)`,
                location: 'ESP32 Sensor'
            });
        }
    }
    
    // Envoyer les alertes au serveur
    alerts.forEach(alert => {
        fetch('create_alert.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `type=${alert.type}&severity=${alert.severity}&message=${encodeURIComponent(alert.message)}&location=${alert.location}&user_id=${phpData.userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Alerte créée:', alert.message);
                // Mettre à jour l'affichage
                updateOverviewAlerts(allAlerts);
            }
        });
    });
    
    return alerts;
}

// ===== FONCTIONS DE NOTIFICATION =====

function showNotification(type, title, message, location) {
    const container = document.getElementById('notificationContainer');
    if(!container) return;
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    let icon = 'fa-circle-exclamation';
    if(type === 'warning') icon = 'fa-exclamation-triangle';
    if(type === 'info') icon = 'fa-info-circle';
    
    const now = new Date();
    const timeStr = now.getHours() + ':' + now.getMinutes().toString().padStart(2, '0');
    
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas ${icon}"></i>
        </div>
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
            <div class="notification-location">
                <i class="fas fa-map-marker-alt"></i>
                <span>${location}</span>
            </div>
            <div class="notification-time">${timeStr}</div>
        </div>
        <div class="notification-close" onclick="this.parentElement.remove();">
            <i class="fas fa-times"></i>
        </div>
    `;
    
    container.appendChild(notification);
    
    setTimeout(() => {
        if(notification.parentNode) {
            notification.remove();
        }
    }, 8000);
}

function showDemoNotifications() {
    showNotification('info', 'EnviroNet', 'System is operational', 'System');
}

function showNotificationFromMap(location, type) {
    let title, message;
    
    if(type === 'critical') {
        title = 'Critical Alert';
        message = 'High temperature detected';
    } else if(type === 'warning') {
        title = 'Warning';
        message = 'Monitoring recommended';
    } else {
        title = 'Information';
        message = 'System operational';
    }
    
    showNotification(type, title, message, location);
}

function hideAlertInfo() {
    const alertInfo = document.getElementById('selectedAlertInfo');
    if(alertInfo) alertInfo.classList.add('hidden');
    
    document.querySelectorAll('.map-marker').forEach(marker => {
        marker.classList.remove('map-highlight');
    });
}

// ===== FONCTIONS D'HISTORIQUE =====

function addToHistory(type, value, timestamp) {
    if(!history[type]) history[type] = [];
    if(!history.timestamps) history.timestamps = [];
    
    history[type].push(value);
    history.timestamps.push(timestamp);
    
    if(history[type].length > 20) {
        history[type].shift();
        history.timestamps.shift();
    }
    
    updateCharts(type);
}

function updateCharts(type) {
    const timeLabels = history.timestamps.slice(-7);
    
    if(type === 'signal' && signalChart) {
        signalChart.data.labels = timeLabels;
        signalChart.data.datasets[0].data = history.signal.slice(-7);
        signalChart.update();
        
        const signalLabels = document.getElementById('signalTimeLabels');
        if(signalLabels) {
            signalLabels.innerHTML = timeLabels.map(t => `<span>${t}</span>`).join('');
        }
    }
    
    if(type === 'temperature' && tempChart) {
        tempChart.data.labels = timeLabels;
        tempChart.data.datasets[0].data = history.temperature.slice(-7);
        tempChart.update();
        
        const tempLabels = document.getElementById('tempTimeLabels');
        if(tempLabels) {
            tempLabels.innerHTML = timeLabels.map(t => `<span>${t}</span>`).join('');
        }
    }
    
    if(type === 'bandwidth' && bandwidthChart) {
        bandwidthChart.data.labels = timeLabels;
        bandwidthChart.data.datasets[0].data = history.bandwidth.slice(-7);
        bandwidthChart.update();
        
        const bwLabels = document.getElementById('bandwidthTimeLabels');
        if(bwLabels) {
            bwLabels.innerHTML = timeLabels.map(t => `<span>${t}</span>`).join('');
        }
    }
    
    const now = new Date();
    const hour = now.getHours();
    
    if(type === 'temperature' && temp24Chart && envData.temperature) {
        const newData = [...(temp24Chart.data.datasets[0].data || [])];
        newData[hour] = envData.temperature;
        temp24Chart.data.datasets[0].data = newData;
        temp24Chart.update();
    }
    
    if(type === 'humidity' && hum24Chart && envData.humidity) {
        const newData = [...(hum24Chart.data.datasets[0].data || [])];
        newData[hour] = envData.humidity;
        hum24Chart.data.datasets[0].data = newData;
        hum24Chart.update();
    }
    
    if(type === 'signal' && networkSignalHist && networkDataJS.signal) {
        const newData = [...(networkSignalHist.data.datasets[0].data || [])];
        newData[hour] = networkDataJS.signal;
        networkSignalHist.data.datasets[0].data = newData;
        networkSignalHist.update();
    }
    
    if(type === 'bandwidth' && networkBandwidthHist && networkDataJS.bandwidth) {
        const newData = [...(networkBandwidthHist.data.datasets[0].data || [])];
        newData[hour] = networkDataJS.bandwidth;
        networkBandwidthHist.data.datasets[0].data = newData;
        networkBandwidthHist.update();
    }
    
    if(type === 'ping' && networkLatencyHist && networkDataJS.ping) {
        const newData = [...(networkLatencyHist.data.datasets[0].data || [])];
        newData[hour] = networkDataJS.ping;
        networkLatencyHist.data.datasets[0].data = newData;
        networkLatencyHist.update();
    }
}

// ===== FONCTIONS DE MISE À JOUR DES DONNÉES =====

function updateEnvironmentalData(data) {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    
    if(data.temperature !== undefined) {
        envData.temperature = data.temperature;
        updateTemperatureDisplay(data.temperature);
        addToHistory('temperature', data.temperature, timeStr);
    }
    if(data.humidity !== undefined) {
        envData.humidity = data.humidity;
        updateHumidityDisplay(data.humidity);
        addToHistory('humidity', data.humidity, timeStr);
    }
    
    envData.timestamp = now;
    updateMapMarkers();
    
    insertSensorData({
        temperature: data.temperature !== undefined ? data.temperature : envData.temperature,
        humidity: data.humidity !== undefined ? data.humidity : envData.humidity,
        signal: networkDataJS.signal,
        bandwidth: networkDataJS.bandwidth,
        ping: networkDataJS.ping
    });
}

function updateNetworkData(data) {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    
    networkDataJS = { ...networkDataJS, ...data, timestamp: now };
    
    if(data.signal !== undefined) {
        updateSignalDisplay(data.signal);
        addToHistory('signal', data.signal, timeStr);
    }
    if(data.bandwidth !== undefined) {
        updateBandwidthDisplay(data.bandwidth);
        addToHistory('bandwidth', data.bandwidth, timeStr);
    }
    if(data.ping !== undefined) {
        updatePingDisplay(data.ping);
        addToHistory('ping', data.ping, timeStr);
    }
    
    insertSensorData({
        temperature: envData.temperature,
        humidity: envData.humidity,
        signal: networkDataJS.signal,
        bandwidth: networkDataJS.bandwidth,
        ping: networkDataJS.ping,
        ssid: data.ssid || null,
        ip: data.ip || null,
        mac: data.mac || null
    });
}

// ===== FONCTIONS DE GESTION DES SALLES =====

function updateRoomsDisplay() {
    const roomsList = document.getElementById('roomsList');
    if(!roomsList) return;
    
    roomsList.innerHTML = '';
    
    rooms.forEach(room => {
        const roomCard = document.createElement('div');
        roomCard.className = 'room-card';
        roomCard.setAttribute('data-room-id', room.id);
        
        roomCard.innerHTML = `
            <div class="flex justify-between items-start mb-2">
                <div>
                    <div class="room-name">${escapeHtml(room.name)}</div>
                    <div class="text-xs text-muted">${escapeHtml(room.location)}</div>
                </div>
                <span class="text-xs bg-[#e3f7ed] text-[#0d7c4b] px-2 py-1 rounded-full">Active</span>
            </div>
            <div class="flex justify-between items-center mt-3 pt-2 border-t border-[#e9eef4]">
                <div>
                    <div class="text-xs text-muted">Temperature</div>
                    <div class="font-semibold text-sm" id="roomTemp_${room.id}">${envData.temperature ? envData.temperature.toFixed(1) + '°C' : '--°C'}</div>
                </div>
                <div>
                    <div class="text-xs text-muted">Humidity</div>
                    <div class="font-semibold text-sm" id="roomHum_${room.id}">${envData.humidity ? envData.humidity.toFixed(0) + '%' : '--%'}</div>
                </div>
                <button class="text-[#b91c1c] text-xs hover:text-[#7f1a1a]" onclick="deleteRoom('${room.id}')">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `;
        
        roomsList.appendChild(roomCard);
    });
}

function updateRoomCounters() {
    updateElementText('envRooms', rooms.length);
    updateElementText('roomsCount', rooms.length);
    updateElementText('envRoomsDetail', `${rooms.length} active ${rooms.length > 1 ? 'rooms' : 'room'}`);
}

function updateMapMarkers() {
    const markersContainer = document.getElementById('mapMarkersContainer');
    if(!markersContainer) return;
    
    markersContainer.innerHTML = '';
    
    rooms.forEach(room => {
        const temp = envData.temperature;
        const status = temp ? (temp > 28 ? 'critical' : (temp > 24 ? 'warning' : 'info')) : 'info';
        const borderColorClass = status === 'critical' ? 'border-[#b91c1c]' : (status === 'warning' ? 'border-[#b6560c]' : 'border-[#0d7c4b]');
        const dotColorClass = status === 'critical' ? 'dot-red' : (status === 'warning' ? 'dot-yellow' : 'dot-green');
        const tempDisplay = temp ? `${temp.toFixed(1)}°C` : '--°C';
        const humDisplay = envData.humidity ? `${envData.humidity.toFixed(0)}%` : '--%';
        
        const marker = document.createElement('div');
        marker.className = `map-marker bg-white px-3 py-2 md:px-4 md:py-3 rounded-xl shadow-lg border-l-4 ${borderColorClass}`;
        marker.style.top = room.position_top || '25%';
        marker.style.left = room.position_left || '33%';
        marker.setAttribute('data-location', room.name);
        marker.setAttribute('data-status', status);
        marker.onclick = () => showNotificationFromMap(room.name, status);
        
        marker.innerHTML = `
            <div class="flex items-center gap-2">
                <span class="status-dot ${dotColorClass}"></span>
                <div>
                    <div class="font-semibold text-sm">${escapeHtml(room.name)}</div>
                    <div class="text-xs text-muted">${tempDisplay} / ${humDisplay}</div>
                </div>
            </div>
        `;
        
        markersContainer.appendChild(marker);
    });
}

window.deleteRoom = function(roomId) {
    if(rooms.length === 1) {
        showNotification('warning', 'Unable', 'You cannot delete the last room', 'System');
        return;
    }
    
    fetch('delete_room.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'room_id=' + roomId
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            const roomToDelete = rooms.find(r => r.id == roomId);
            if(roomToDelete) {
                rooms = rooms.filter(r => r.id != roomId);
                updateRoomsDisplay();
                updateRoomCounters();
                updateMapMarkers();
                showNotification('info', 'Room deleted', `Room "${roomToDelete.name}" has been deleted`, 'System');
            }
        } else {
            showNotification('warning', 'Error', data.message, 'System');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('warning', 'Error', 'Unable to delete room', 'System');
    });
};

// ===== GESTION DES ONGLETS =====

function setActiveTab(tabId) {
    const tabs = document.querySelectorAll('.tabs-trigger');
    const contents = {
        overview: document.getElementById('overview-content'),
        network: document.getElementById('network-content'),
        environment: document.getElementById('environment-content'),
        rooms: document.getElementById('rooms-content'),
        map: document.getElementById('map-content')
    };
    
    tabs.forEach(btn => btn.classList.remove('active'));
    const activeTab = document.querySelector(`.tabs-trigger[data-tab="${tabId}"]`);
    if(activeTab) activeTab.classList.add('active');
    
    Object.values(contents).forEach(c => {
        if(c) c.classList.add('hidden');
    });
    
    if(contents[tabId]) contents[tabId].classList.remove('hidden');
    
    if(tabId === 'rooms') {
        updateRoomsDisplay();
    }
    if(tabId === 'map') {
        updateMapMarkers();
    }
}

// ===== FONCTIONS MQTT =====

function handleMQTTMessage(topic, value) {
    console.log(`📨 MQTT: ${topic} = ${value}`);
    
    switch(topic) {
        case MQTT_CONFIG.topics.temperature:
            currentTemp = parseFloat(value);
            updateEnvironmentalData({ temperature: currentTemp });
            break;

        case MQTT_CONFIG.topics.humidity:
            currentHum = parseFloat(value);
            updateEnvironmentalData({ humidity: currentHum });
            break;

        case MQTT_CONFIG.topics.signal:
            const signalValue = Math.round(parseFloat(value));
            updateNetworkData({ signal: signalValue });
            safeUpdate('networkRSSI', Math.round((signalValue / 100) * -30 - 30) + ' dBm');
            break;

        case MQTT_CONFIG.topics.bandwidth:
            updateNetworkData({ bandwidth: parseFloat(value) });
            break;

        case MQTT_CONFIG.topics.ping:
            updateNetworkData({ ping: Math.round(parseFloat(value)) });
            break;

        case MQTT_CONFIG.topics.ssid:
            safeUpdate('networkSSID', value);
            break;

        case MQTT_CONFIG.topics.ip:
            safeUpdate('networkIP', value);
            break;

        case MQTT_CONFIG.topics.mac:
            safeUpdate('networkMAC', value);
            break;

        case MQTT_CONFIG.topics.status:
            if(value === 'online' || value === 'connected') {
                updateElementText('sensorRoomStatus', 'Active');
            }
            break;
    }

    // Cacher le message d'attente
    if (currentTemp !== null || networkDataJS.signal !== null) {
        const waitingMsg = document.getElementById('waitingMessage');
        if (waitingMsg) waitingMsg.classList.add('hidden');
    }

    // ✅ GÉNÉRER DES ALERTES EN TEMPS RÉEL ET LES ENVOYER À LA BASE
    if (currentTemp !== null && currentHum !== null) {
        const newAlerts = [];
        const now = new Date().toISOString();

        // Vérifier température
        if (currentTemp > 28) {
            newAlerts.push({
                type: 'temperature',
                severity: 'critical',
                message: `⚠️ ALERTE CRITIQUE - Température trop élevée : ${currentTemp}°C (seuil: 28°C)`,
                location: 'ESP32 Sensor'
            });
        } else if (currentTemp >= 24) {
            newAlerts.push({
                type: 'temperature',
                severity: 'warning',
                message: `⚠️ ATTENTION - Température élevée : ${currentTemp}°C (seuil: 24°C)`,
                location: 'ESP32 Sensor'
            });
        }

        // Vérifier humidité
        if (currentHum > 80) {
            newAlerts.push({
                type: 'humidity',
                severity: 'critical',
                message: `⚠️ ALERTE CRITIQUE - Humidité trop élevée : ${currentHum}% (seuil: 80%)`,
                location: 'ESP32 Sensor'
            });
        } else if (currentHum >= 70) {
            newAlerts.push({
                type: 'humidity',
                severity: 'warning',
                message: `⚠️ ATTENTION - Humidité élevée : ${currentHum}% (seuil: 70%)`,
                location: 'ESP32 Sensor'
            });
        }

        // Vérifier signal WiFi
        if (networkDataJS.signal !== null) {
            if (networkDataJS.signal < 30) {
                newAlerts.push({
                    type: 'signal',
                    severity: 'critical',
                    message: `⚠️ ALERTE CRITIQUE - Signal WiFi très faible : ${networkDataJS.signal}% (seuil: 30%)`,
                    location: 'ESP32 Sensor'
                });
            } else if (networkDataJS.signal < 50) {
                newAlerts.push({
                    type: 'signal',
                    severity: 'warning',
                    message: `⚠️ ATTENTION - Signal WiFi faible : ${networkDataJS.signal}% (seuil: 50%)`,
                    location: 'ESP32 Sensor'
                });
            }
        }

        // Envoyer chaque alerte à la base de données
        newAlerts.forEach(alert => {
            // Envoyer à create_alert.php pour stockage en base
            fetch('create_alert.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `type=${encodeURIComponent(alert.type)}&severity=${encodeURIComponent(alert.severity)}&message=${encodeURIComponent(alert.message)}&location=${encodeURIComponent(alert.location)}&user_id=${phpData.userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('✅ Alerte sauvegardée:', alert.message);
                }
            })
            .catch(error => console.error('Erreur sauvegarde alerte:', error));

            // Ajouter à la liste locale pour affichage immédiat
            allAlerts.unshift({
                id: Date.now() + Math.random(),
                type: alert.type,
                severity: alert.severity,
                message: alert.message,
                location: alert.location,
                status: 'unread',
                created_at: now
            });

            // Afficher notification
            showNotification(
                alert.severity,
                alert.severity === 'critical' ? '🚨 Critical Alert' : '⚠️ Warning',
                alert.message,
                alert.location
            );
        });

        // Mettre à jour l'affichage des alertes dans l'overview
        if (newAlerts.length > 0) {
            updateOverviewAlerts(allAlerts);
            
            // Mettre à jour le compteur de notifications
            const unreadCount = allAlerts.filter(a => a.status === 'unread').length;
            document.getElementById('notificationCount').innerText = unreadCount;
        }
    }

    updateLastUpdateTime('ESP32');
}

function connectMQTT() {
    const connectionStatus = document.getElementById('connectionStatus');
    const connectionText = document.getElementById('connectionText');
    
    setTimeout(fetchLatestDataFromDB, 2000);
    const pollingInterval = setInterval(fetchLatestDataFromDB, 5000);
    
    try {
        mqttClient = mqtt.connect("wss://broker.hivemq.com:8884/mqtt");
        
        mqttClient.on('connect', () => {
            console.log('✅ MQTT Broker connected');

            clearInterval(pollingInterval);
            setInterval(fetchLatestDataFromDB, 30000);
            
            Object.values(MQTT_CONFIG.topics).forEach(topic => {
                mqttClient.subscribe(topic);
            });

            showNotification('info', 'MQTT Connected', 'Waiting for ESP32 data', 'Broker');
        });
        
        mqttClient.on('message', (topic, message) => {
            try {
                const value = message.toString();
                
                if (!esp32CamConnected) {
                    esp32CamConnected = true;

                    if(connectionStatus) connectionStatus.className = 'connection-status connected';
                    if(connectionText) connectionText.innerText = 'ESP32 Connected (MQTT)';

                    const waitingMsg = document.getElementById('waitingMessage');
                    if(waitingMsg) waitingMsg.classList.add('hidden');

                    showNotification('info', 'ESP32 Connected', 'MQTT active', 'System');
                }
                
                handleMQTTMessage(topic, value);

            } catch(e) {
                console.error('MQTT parsing error:', e);
            }
        });
        
        mqttClient.on('error', (err) => {
            console.error('❌ MQTT Error:', err);
        });
        
        mqttClient.on('close', () => {
            console.log("⚠️ MQTT closed");
        });
        
    } catch(err) {
        console.error('MQTT connection error:', err);
    }
}

function fetchLatestDataFromDB() {
    fetch(`get_latest_data.php?user_id=${phpData.userId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success && result.data) {
                const data = result.data;
                
                const waitingMsg = document.getElementById('waitingMessage');
                if (waitingMsg) {
                    waitingMsg.classList.add('hidden');
                }
                
                const connStatus = document.getElementById('connectionStatus');
                if (connStatus) {
                    connStatus.className = 'connection-status connected';
                }
                const connText = document.getElementById('connectionText');
                if (connText) {
                    connText.innerText = 'ESP32 Connected (HTTP)';
                }
                
                if (data.temperature !== undefined || data.humidity !== undefined) {
                    updateEnvironmentalData({ 
                        temperature: data.temperature || envData.temperature,
                        humidity: data.humidity || envData.humidity
                    });
                }
                
                const networkUpdate = {};
                if (data.signal_strength !== undefined) networkUpdate.signal = data.signal_strength;
                if (data.bandwidth !== undefined) networkUpdate.bandwidth = data.bandwidth;
                if (data.ping !== undefined) networkUpdate.ping = data.ping;
                if (data.ssid !== undefined) networkUpdate.ssid = data.ssid;
                if (data.ip_address !== undefined) networkUpdate.ip = data.ip_address;
                if (data.mac_address !== undefined) networkUpdate.mac = data.mac_address;
                
                if (Object.keys(networkUpdate).length > 0) {
                    updateNetworkData(networkUpdate);
                }
                
                if (data.ssid !== undefined) safeUpdate('networkSSID', data.ssid);
                if (data.ip_address !== undefined) safeUpdate('networkIP', data.ip_address);
                if (data.mac_address !== undefined) safeUpdate('networkMAC', data.mac_address);
                
                updateLastUpdateTime('ESP32 HTTP');
            }
        })
        .catch(error => {
            console.error('Error fetching latest data:', error);
        });
}

// ===== FONCTIONS D'INITIALISATION DES GRAPHIQUES =====

function initCharts() {
    const signalCtx = document.getElementById('signalRealtimeChart')?.getContext('2d');
    if(signalCtx) {
        signalChart = new Chart(signalCtx, {
            type: 'line',
            data: { labels: [], datasets: [{ label: 'Signal (%)', data: [], borderColor: '#1e4a7a', backgroundColor: '#1e4a7a20', tension: 0.3, fill: true, pointRadius: 3 }] },
            options: { responsive: true, maintainAspectRatio: false, animation: { duration: 0 }, scales: { y: { beginAtZero: true, max: 100 } } }
        });
    }

    const tempCtx = document.getElementById('tempRealtimeChart')?.getContext('2d');
    if(tempCtx) {
        tempChart = new Chart(tempCtx, {
            type: 'line',
            data: { labels: [], datasets: [{ label: 'Temperature (°C)', data: [], borderColor: '#b6560c', backgroundColor: '#b6560c20', tension: 0.3, fill: true, pointRadius: 3 }] },
            options: { responsive: true, maintainAspectRatio: false, animation: { duration: 0 }, scales: { y: { beginAtZero: false, min: 15, max: 35 } } }
        });
    }

    const bandwidthCtx = document.getElementById('bandwidthOverviewChart')?.getContext('2d');
    if(bandwidthCtx) {
        bandwidthChart = new Chart(bandwidthCtx, {
            type: 'line',
            data: { labels: [], datasets: [{ label: 'Mbps', data: [], borderColor: '#b6560c', backgroundColor: '#b6560c20', tension: 0.3, fill: true, pointRadius: 3 }] },
            options: { responsive: true, maintainAspectRatio: false, animation: { duration: 0 }, scales: { y: { beginAtZero: true } } }
        });
    }

    const humidityCtx = document.getElementById('humidityGaugeChart')?.getContext('2d');
    if(humidityCtx) {
        humidityGaugeChart = new Chart(humidityCtx, {
            type: 'doughnut',
            data: { labels: ['Humidity', 'Remaining'], datasets: [{ data: [0, 100], backgroundColor: ['#1e4a7a', '#e9eef4'], borderWidth: 0, circumference: 180, rotation: 270 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false } } }
        });
    }

    const netSignalCtx = document.getElementById('networkSignalHist')?.getContext('2d');
    if(netSignalCtx) {
        networkSignalHist = new Chart(netSignalCtx, {
            type: 'line',
            data: { labels: Array.from({length: 24}, (_, i) => i + 'h'), datasets: [{ label: 'Signal (%)', data: Array(24).fill(0), borderColor: '#1e4a7a', backgroundColor: '#1e4a7a20', tension: 0.3, fill: true }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 100 } } }
        });
    }

    const netBandwidthCtx = document.getElementById('networkBandwidthHist')?.getContext('2d');
    if(netBandwidthCtx) {
        networkBandwidthHist = new Chart(netBandwidthCtx, {
            type: 'line',
            data: { labels: Array.from({length: 24}, (_, i) => i + 'h'), datasets: [{ label: 'Bandwidth (Mbps)', data: Array(24).fill(0), borderColor: '#b6560c', backgroundColor: '#b6560c20', tension: 0.3, fill: true }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    const netLatencyCtx = document.getElementById('networkLatencyHist')?.getContext('2d');
    if(netLatencyCtx) {
        networkLatencyHist = new Chart(netLatencyCtx, {
            type: 'line',
            data: { labels: Array.from({length: 24}, (_, i) => i + 'h'), datasets: [{ label: 'Latency (ms)', data: Array(24).fill(0), borderColor: '#0d7c4b', backgroundColor: '#0d7c4b20', tension: 0.3, fill: true }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    const temp24Ctx = document.getElementById('temp24Chart')?.getContext('2d');
    if(temp24Ctx) {
        temp24Chart = new Chart(temp24Ctx, {
            type: 'line',
            data: { labels: Array.from({length: 24}, (_, i) => i + 'h'), datasets: [{ label: 'Temperature (°C)', data: Array(24).fill(0), borderColor: '#b6560c', backgroundColor: '#b6560c20', tension: 0.3, fill: true }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    const hum24Ctx = document.getElementById('hum24Chart')?.getContext('2d');
    if(hum24Ctx) {
        hum24Chart = new Chart(hum24Ctx, {
            type: 'line',
            data: { labels: Array.from({length: 24}, (_, i) => i + 'h'), datasets: [{ label: 'Humidity (%)', data: Array(24).fill(0), borderColor: '#1e4a7a', backgroundColor: '#1e4a7a20', tension: 0.3, fill: true }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
}

function load24HourHistory() {
    const temp24Data = new Array(24).fill(0);
    const hum24Data = new Array(24).fill(0);
    const signal24Data = new Array(24).fill(0);
    const bandwidth24Data = new Array(24).fill(0);
    const ping24Data = new Array(24).fill(0);
    
    for (let i = 0; i < 24; i++) {
        temp24Data[i] = 22 + 4 * Math.cos((i - 14) * Math.PI / 12) + (Math.random() * 2 - 1);
        hum24Data[i] = 50 + 10 * Math.sin((i - 14) * Math.PI / 12) + (Math.random() * 10 - 5);
        signal24Data[i] = 75 + 10 * Math.sin(i * Math.PI / 12) + (Math.random() * 10 - 5);
        bandwidth24Data[i] = 45 + 20 * Math.sin(i * Math.PI / 12) + (Math.random() * 10 - 5);
        ping24Data[i] = 25 + 15 * Math.cos((i - 14) * Math.PI / 12) + (Math.random() * 10 - 5);
        
        temp24Data[i] = Math.max(15, Math.min(32, temp24Data[i]));
        hum24Data[i] = Math.max(20, Math.min(80, hum24Data[i]));
        signal24Data[i] = Math.max(30, Math.min(100, signal24Data[i]));
        bandwidth24Data[i] = Math.max(10, Math.min(100, bandwidth24Data[i]));
        ping24Data[i] = Math.max(5, Math.min(80, ping24Data[i]));
    }
    
    if (temp24Chart) { temp24Chart.data.datasets[0].data = temp24Data; temp24Chart.update(); }
    if (hum24Chart) { hum24Chart.data.datasets[0].data = hum24Data; hum24Chart.update(); }
    if (networkSignalHist) { networkSignalHist.data.datasets[0].data = signal24Data; networkSignalHist.update(); }
    if (networkBandwidthHist) { networkBandwidthHist.data.datasets[0].data = bandwidth24Data; networkBandwidthHist.update(); }
    if (networkLatencyHist) { networkLatencyHist.data.datasets[0].data = ping24Data; networkLatencyHist.update(); }
}

// ===== INITIALISATION AU CHARGEMENT =====

document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
    initCharts();
    
    // Load 24-hour history data
    load24HourHistory();
    
    updateRoomsDisplay();
    updateRoomCounters();
    updateMapMarkers();
    
    // Initialiser les alertes dans l'overview
    updateOverviewAlerts(allAlerts);
    
    // MQTT connection
    connectMQTT();
    
    // Tab handling
    const tabs = document.querySelectorAll('.tabs-trigger');
    tabs.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            setActiveTab(btn.dataset.tab);
        });
    });
    
    // Room addition handling
    const addRoomBtn = document.getElementById('addRoomBtn');
    const roomFormContainer = document.getElementById('roomFormContainer');
    const saveRoomBtn = document.getElementById('saveRoomBtn');
    const cancelRoomBtn = document.getElementById('cancelRoomBtn');
    const roomNameInput = document.getElementById('roomNameInput');
    const roomLocationInput = document.getElementById('roomLocationInput');
    
    if(addRoomBtn) {
        addRoomBtn.addEventListener('click', () => {
            roomFormContainer.classList.remove('hidden');
        });
    }
    
    if(cancelRoomBtn) {
        cancelRoomBtn.addEventListener('click', () => {
            roomFormContainer.classList.add('hidden');
            roomNameInput.value = '';
            roomLocationInput.value = '';
        });
    }
    
    if(saveRoomBtn) {
        saveRoomBtn.addEventListener('click', () => {
            const name = roomNameInput.value.trim();
            const location = roomLocationInput.value.trim();
            
            if(name === '') {
                showNotification('warning', 'Error', 'Please enter a room name', 'System');
                return;
            }
            
            fetch('add_room.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'name=' + encodeURIComponent(name) + '&location=' + encodeURIComponent(location)
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    rooms.push(data.room);
                    updateRoomsDisplay();
                    updateRoomCounters();
                    updateMapMarkers();
                    roomFormContainer.classList.add('hidden');
                    roomNameInput.value = '';
                    roomLocationInput.value = '';
                    showNotification('info', 'Room added', `Room "${name}" has been added`, 'System');
                } else {
                    showNotification('warning', 'Error', data.message, 'System');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('warning', 'Error', 'Unable to add room', 'System');
            });
        });
    }
});
</script>
</body>
</html>
