<?php
// history.php - Page d'historique en temps réel (tableau uniquement)
require_once 'config.php';

// Verify user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];

// Get period from URL
$period = $_GET['period'] ?? '24h';

// Build query based on period
try {
    $pdo = getDBConnection();
    
    switch ($period) {
        case '24h':
            $interval = '24 HOUR';
            break;
        case '7d':
            $interval = '7 DAY';
            break;
        case '30d':
            $interval = '30 DAY';
            break;
        default:
            $interval = '24 HOUR';
            $period = '24h';
    }
    
    $stmt = $pdo->prepare("
    SELECT * FROM esp32_cam_data
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL $interval)
    ORDER BY timestamp DESC
");
$stmt->execute();
$historyData = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log("Error retrieving history: " . $e->getMessage());
    $historyData = [];
}

// Function to determine alert type
function determineAlertType($temperature, $humidity, $signal) {
    if ($temperature > 28 || $humidity > 80 || ($signal !== null && $signal < 30)) {
        return 'Critical';
    } elseif ($temperature > 24 || $humidity > 70 || ($signal !== null && $signal < 50)) {
        return 'Warning';
    }
    return 'Normal';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EnviroNet · History</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    :root {
      --primary-dark: #0f2b4b;
      --primary: #1e4a7a;
      --danger: #b91c1c;
      --danger-light: #fee9e9;
      --warning: #b6560c;
      --warning-light: #fff1e0;
      --success: #0d7c4b;
      --success-light: #e3f7ed;
      --neutral-50: #f9fafc;
      --neutral-100: #f2f5f9;
      --neutral-200: #e9eef4;
      --neutral-600: #4b5a6b;
      --neutral-700: #2f3e4f;
      --neutral-800: #1e2a36;
    }
    body { background: #f6f9fd; font-family: 'Segoe UI', system-ui, sans-serif; }
    
    .dashboard-card {
      background: white;
      border-radius: 1.2rem;
      padding: 1.5rem;
      box-shadow: 0 8px 24px -8px rgba(0,30,60,0.08);
      border: 1px solid var(--neutral-200);
    }
    
    .btn-back {
      background: var(--primary);
      color: white;
      padding: 0.5rem 1.5rem;
      border-radius: 30px;
      font-weight: 500;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s;
    }
    .btn-back:hover { background: var(--primary-dark); transform: translateY(-1px); }
    
    .btn-filter {
      padding: 0.5rem 1.2rem;
      border-radius: 25px;
      border: 1px solid var(--neutral-200);
      background: white;
      cursor: pointer;
      font-size: 0.85rem;
      font-weight: 500;
      transition: all 0.2s;
      color: var(--neutral-700);
      text-decoration: none;
      display: inline-block;
    }
    .btn-filter.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }
    .btn-filter:hover:not(.active) { border-color: var(--primary); color: var(--primary); }
    
    .btn-export {
      background: var(--success);
      color: white;
      padding: 0.5rem 1.5rem;
      border-radius: 30px;
      font-weight: 500;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s;
      font-size: 0.85rem;
    }
    .btn-export:hover { background: #0a5a38; transform: translateY(-1px); }
    
    .history-table { width: 100%; border-collapse: collapse; }
    .history-table th { 
      background: var(--neutral-100); 
      padding: 10px 14px; 
      text-align: left; 
      font-size: 0.8rem; 
      color: var(--neutral-700); 
      font-weight: 600;
      white-space: nowrap;
    }
    .history-table td { 
      padding: 10px 14px; 
      border-bottom: 1px solid var(--neutral-200); 
      font-size: 0.85rem; 
    }
    .history-table tbody tr:hover { background: var(--neutral-50); }
    
    .badge-type {
      padding: 3px 10px;
      border-radius: 15px;
      font-size: 0.7rem;
      font-weight: 500;
      display: inline-block;
    }
    .badge-normal { background: var(--success-light); color: var(--success); }
    .badge-warning { background: var(--warning-light); color: var(--warning); }
    .badge-critical { background: var(--danger-light); color: var(--danger); }
    
    .live-indicator {
      display: inline-flex;
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
    
    .text-temp-critical { color: var(--danger); font-weight: 600; }
    .text-temp-warning { color: var(--warning); }
    .text-temp-normal { color: var(--success); }
    
    .no-data {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--neutral-600);
    }
    .no-data i { font-size: 3rem; margin-bottom: 1rem; display: block; }
    
    .user-badge {
      background: white;
      border-radius: 60px;
      padding: 0.3rem 0.3rem 0.3rem 1.2rem;
      border: 1px solid var(--neutral-200);
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.9rem;
    }
  </style>
</head>
<body class="p-4 md:p-6">

<div class="max-w-[1400px] mx-auto space-y-4 md:space-y-6">

  <!-- ===== HEADER ===== -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="flex items-center gap-4">
      <a href="dashboard.php" class="btn-back" onclick="return goToDashboard(event)">
       <i class="fas fa-arrow-left"></i>
        Dashboard
      </a>
      <h1 class="text-xl md:text-2xl font-bold text-[#1e2a36] flex items-center gap-2">
        <i class="fas fa-history text-[#1e4a7a]"></i>
        Data History
      </h1>
    </div>
    
    <div class="flex items-center gap-3">
      <div class="live-indicator">
        <span class="live-dot"></span>
        <span>Real-time</span>
      </div>
      <div class="user-badge">
        <span class="font-semibold text-[#1e4a7a]"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#1e4a7a] to-[#0f2b4b] flex items-center justify-center text-white">
          <i class="fas fa-user-circle"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== FILTERS + EXPORT ===== -->
  <div class="dashboard-card">
    <div class="flex flex-wrap justify-between items-center gap-4">
      <div class="flex gap-2 flex-wrap">
        <a href="?period=24h" class="btn-filter <?= $period === '24h' ? 'active' : '' ?>">Last 24h</a>
        <a href="?period=7d" class="btn-filter <?= $period === '7d' ? 'active' : '' ?>">Last 7 days</a>
        <a href="?period=30d" class="btn-filter <?= $period === '30d' ? 'active' : '' ?>">Last 30 days</a>
      </div>
      <div class="flex items-center gap-3">
        <span class="text-sm text-[#4b5a6b]">
          <?= count($historyData) ?> records
        </span>
        <button onclick="exportCSV()" class="btn-export">
          <i class="fas fa-download"></i>
          Export CSV
        </button>
      </div>
    </div>
  </div>

  <!-- ===== HISTORY TABLE ===== -->
  <div class="dashboard-card">
    <div class="overflow-x-auto">
      <table class="history-table" id="historyTable">
        <thead>
          <tr>
            <th>Date/Time</th>
            <th>Temp (°C)</th>
            <th>Humidity (%)</th>
            <th>Signal (%)</th>
            <th>Bandwidth (Mbps)</th>
            <th>Ping (ms)</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="historyTableBody">
          <?php if (empty($historyData)): ?>
          <tr>
            <td colspan="7">
              <div class="no-data">
                <i class="fas fa-database text-[#6b7a8c]"></i>
                <p class="text-lg font-medium">No data available</p>
                <p class="text-sm">Waiting for ESP32 data...</p>
              </div>
            </td>
          </tr>
          <?php else: ?>
            <?php foreach ($historyData as $row): 
                $alertType = determineAlertType($row['temperature'], $row['humidity'], $row['signal_strength']);
                $badgeClass = 'badge-normal';
                if ($alertType === 'Critical') $badgeClass = 'badge-critical';
                elseif ($alertType === 'Warning') $badgeClass = 'badge-warning';
                
                $tempClass = 'text-temp-normal';
                if ($row['temperature'] > 28) $tempClass = 'text-temp-critical';
                elseif ($row['temperature'] > 24) $tempClass = 'text-temp-warning';
            ?>
            <tr>
              <td class="text-sm whitespace-nowrap"><?= date('d/m/Y H:i:s', strtotime($row['timestamp'])) ?></td>
              <td class="<?= $tempClass ?>"><?= $row['temperature'] !== null ? number_format($row['temperature'], 1) : '--' ?></td>
              <td><?= $row['humidity'] !== null ? number_format($row['humidity'], 0) . '%' : '--' ?></td>
              <td><?= $row['signal_strength'] !== null ? $row['signal_strength'] . '%' : '--' ?></td>
              <td><?= $row['bandwidth'] !== null ? number_format($row['bandwidth'], 1) . ' Mbps' : '--' ?></td>
              <td><?= $row['ping'] !== null ? $row['ping'] . ' ms' : '--' ?></td>
              <td><span class="badge-type <?= $badgeClass ?>"><?= $alertType ?></span></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
// ===== AUTO-REFRESH =====
function refreshHistory() {
    const currentPeriod = new URLSearchParams(window.location.search).get('period') || '24h';
    
    fetch('get_history.php?user_id=<?= $user_id ?>&period=' + currentPeriod)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.history) {
                updateHistoryTable(data.history);
            }
        })
        .catch(error => console.error('Error refreshing history:', error));
}

function updateHistoryTable(historyData) {
    const tbody = document.getElementById('historyTableBody');
    
    if (!historyData || historyData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7">
                    <div class="no-data">
                        <i class="fas fa-database text-[#6b7a8c]"></i>
                        <p class="text-lg font-medium">No data available</p>
                        <p class="text-sm">Waiting for ESP32 data...</p>
                    </div>
                </td>
            </tr>`;
        return;
    }
    
    tbody.innerHTML = historyData.map(row => {
        const alertType = determineAlertTypeStatic(row.temperature, row.humidity, row.signal_strength);
        let badgeClass = 'badge-normal';
        if (alertType === 'Critical') badgeClass = 'badge-critical';
        else if (alertType === 'Warning') badgeClass = 'badge-warning';
        
        let tempClass = 'text-temp-normal';
        if (row.temperature > 28) tempClass = 'text-temp-critical';
        else if (row.temperature > 24) tempClass = 'text-temp-warning';
        
        const date = new Date(row.timestamp);
        const formattedDate = date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR');
        
        return `
            <tr>
                <td class="text-sm whitespace-nowrap">${formattedDate}</td>
                <td class="${tempClass}">${row.temperature !== null ? parseFloat(row.temperature).toFixed(1) : '--'}</td>
                <td>${row.humidity !== null ? parseFloat(row.humidity).toFixed(0) + '%' : '--'}</td>
                <td>${row.signal_strength !== null ? row.signal_strength + '%' : '--'}</td>
                <td>${row.bandwidth !== null ? parseFloat(row.bandwidth).toFixed(1) + ' Mbps' : '--'}</td>
                <td>${row.ping !== null ? row.ping + ' ms' : '--'}</td>
                <td><span class="badge-type ${badgeClass}">${alertType}</span></td>
            </tr>`;
    }).join('');
}

function determineAlertTypeStatic(temp, hum, signal) {
    if (temp > 28 || hum > 80 || (signal !== null && signal < 30)) return 'Critical';
    if (temp > 24 || hum > 70 || (signal !== null && signal < 50)) return 'Warning';
    return 'Normal';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function goToDashboard(e) {
    // Si le dashboard est déjà ouvert dans l'historique, y retourner
    if (document.referrer && document.referrer.includes('dashboard.php')) {
        e.preventDefault();
        window.history.back();
        return false;
    }
    // Sinon, charger normalement
    return true;
}

function exportCSV() {
    const period = new URLSearchParams(window.location.search).get('period') || '24h';
    const userId = <?= $user_id ?>;
    
    // Créer un lien temporaire et cliquer dessus
    const link = document.createElement('a');
    link.href = 'export_history.php?user_id=' + userId + '&period=' + period;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Refresh every 15 seconds
setInterval(refreshHistory, 15000);
</script>
</body>
</html>
