<?php
// alerts.php - Page dédiée aux alertes en temps réel
require_once 'config.php';

// Verify user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];

// Get all alerts from database
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

// Count alerts by severity
$criticalCount = 0;
$warningCount = 0;
$infoCount = 0;
$unreadCount = 0;

foreach ($allAlerts as $alert) {
    $severity = strtolower($alert['severity'] ?? $alert['type'] ?? 'info');
    if ($severity === 'critical') $criticalCount++;
    elseif ($severity === 'warning') $warningCount++;
    else $infoCount++;
    
    if (($alert['status'] ?? 'unread') === 'unread') $unreadCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EnviroNet · Alerts</title>
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
    
    .btn-action {
      padding: 0.5rem 1.2rem;
      border-radius: 30px;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
      border: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }
    .btn-mark-all { background: var(--primary); color: white; }
    .btn-mark-all:hover { background: var(--primary-dark); }
    .btn-clear-all { background: var(--neutral-200); color: var(--neutral-700); }
    .btn-clear-all:hover { background: #d4dce6; }
    
    .alert-table { width: 100%; border-collapse: collapse; }
    .alert-table th { 
      background: var(--neutral-100); 
      padding: 12px 16px; 
      text-align: left; 
      font-size: 0.85rem; 
      color: var(--neutral-700); 
      font-weight: 600; 
    }
    .alert-table td { 
      padding: 12px 16px; 
      border-bottom: 1px solid var(--neutral-200); 
      font-size: 0.9rem; 
    }
    .alert-table tbody tr { transition: background 0.2s; }
    .alert-table tbody tr:hover { background: var(--neutral-50); }
    .alert-table tbody tr.unread { background: #fef2f2; font-weight: 500; }
    
    .badge-status {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 500;
      display: inline-block;
    }
    .badge-new { background: var(--danger-light); color: var(--danger); }
    .badge-read { background: var(--neutral-200); color: var(--neutral-600); }
    
    .badge-severity {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 500;
      display: inline-block;
      text-transform: uppercase;
    }
    .badge-critical { background: var(--danger-light); color: var(--danger); }
    .badge-warning { background: var(--warning-light); color: var(--warning); }
    .badge-info { background: var(--success-light); color: var(--success); }
    
    .btn-icon {
      background: none;
      border: none;
      cursor: pointer;
      padding: 6px 8px;
      border-radius: 8px;
      transition: all 0.2s;
      font-size: 0.9rem;
    }
    .btn-icon:hover { background: var(--neutral-100); }
    .btn-mark-read { color: var(--primary); }
    .btn-delete { color: var(--danger); }
    
    .stat-card {
      padding: 1.2rem;
      border-radius: 1rem;
      text-align: center;
    }
    .stat-number {
      font-size: 2rem;
      font-weight: 700;
      line-height: 1.2;
    }
    .stat-label {
      font-size: 0.85rem;
      margin-top: 4px;
      font-weight: 500;
    }
    
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
        <i class="fas fa-exclamation-triangle text-[#b6560c]"></i>
        Alerts & Notifications
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

  <!-- ===== STATISTICS ===== -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
    <div class="dashboard-card stat-card bg-[#fee9e9]">
      <div class="stat-number text-[#b91c1c]" id="criticalCount"><?= $criticalCount ?></div>
      <div class="stat-label text-[#b91c1c]">Critical</div>
    </div>
    <div class="dashboard-card stat-card bg-[#fff1e0]">
      <div class="stat-number text-[#b6560c]" id="warningCount"><?= $warningCount ?></div>
      <div class="stat-label text-[#b6560c]">Warnings</div>
    </div>
    <div class="dashboard-card stat-card bg-[#e3f7ed]">
      <div class="stat-number text-[#0d7c4b]" id="infoCount"><?= $infoCount ?></div>
      <div class="stat-label text-[#0d7c4b]">Info</div>
    </div>
    <div class="dashboard-card stat-card bg-[#e6f0fa]">
      <div class="stat-number text-[#1e4a7a]" id="unreadCount"><?= $unreadCount ?></div>
      <div class="stat-label text-[#1e4a7a]">Unread</div>
    </div>
  </div>

  <!-- ===== ACTIONS ===== -->
  <div class="dashboard-card">
    <div class="flex flex-wrap justify-between items-center gap-4">
      <h2 class="text-lg font-semibold flex items-center gap-2">
        <i class="fas fa-bell text-[#1e4a7a]"></i>
        All Alerts
      </h2>
      <div class="flex gap-3">
        <button class="btn-action btn-mark-all" onclick="markAllRead()">
          <i class="fas fa-check-double"></i>
          Mark All Read
        </button>
        <button class="btn-action btn-clear-all" onclick="clearAllAlerts()">
          <i class="fas fa-trash-alt"></i>
          Clear All
        </button>
      </div>
    </div>
  </div>

  <!-- ===== ALERTS TABLE ===== -->
  <div class="dashboard-card">
    <div class="overflow-x-auto">
      <table class="alert-table" id="alertsTable">
        <thead>
          <tr>
            <th>Status</th>
            <th>Severity</th>
            <th>Message</th>
            <th>Location</th>
            <th>Date/Time</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="alertsTableBody">
          <!-- Le contenu est chargé par loadAlerts() au démarrage -->
          <tr>
            <td colspan="6">
              <div class="no-data">
                <i class="fas fa-spinner fa-spin text-[#1e4a7a]"></i>
                <p class="text-lg font-medium">Loading alerts...</p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
// ===== LOAD ALERTS ON PAGE LOAD =====
document.addEventListener('DOMContentLoaded', function() {
    loadAlerts();
});

// ===== AUTO-REFRESH EVERY 10 SECONDS =====
setInterval(loadAlerts, 10000);

function loadAlerts() {
    fetch('get_alerts.php?user_id=<?= $user_id ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateAlertsTable(data.alerts);
                updateStats(data.alerts);
            } else {
                console.error('Failed to load alerts:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading alerts:', error);
        });
}

function updateStats(alerts) {
    let critical = 0, warning = 0, info = 0, unread = 0;
    
    if (alerts && alerts.length > 0) {
        alerts.forEach(a => {
            const severity = (a.severity || a.type || 'info').toLowerCase();
            if (severity === 'critical') critical++;
            else if (severity === 'warning') warning++;
            else info++;
            
            if ((a.status || 'unread') === 'unread') unread++;
        });
    }
    
    document.getElementById('criticalCount').innerText = critical;
    document.getElementById('warningCount').innerText = warning;
    document.getElementById('infoCount').innerText = info;
    document.getElementById('unreadCount').innerText = unread;
}

function updateAlertsTable(alerts) {
    const tbody = document.getElementById('alertsTableBody');
    
    if (!alerts || alerts.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6">
                    <div class="no-data">
                        <i class="fas fa-check-circle text-[#0d7c4b]"></i>
                        <p class="text-lg font-medium">No alerts found</p>
                        <p class="text-sm">System is operating normally</p>
                    </div>
                </td>
            </tr>`;
        return;
    }
    
    tbody.innerHTML = alerts.map(alert => {
        const isRead = (alert.status || 'unread') === 'read';
        const severity = (alert.severity || alert.type || 'info').toLowerCase();
        
        let badgeSeverityClass = 'badge-info';
        if (severity === 'critical') badgeSeverityClass = 'badge-critical';
        else if (severity === 'warning') badgeSeverityClass = 'badge-warning';
        
        const date = new Date(alert.created_at);
        const formattedDate = date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR');
        
        return `
            <tr class="${isRead ? '' : 'unread'}" data-alert-id="${alert.id}">
                <td>
                    <span class="badge-status ${isRead ? 'badge-read' : 'badge-new'}">
                        ${isRead ? 'Read' : 'New'}
                    </span>
                </td>
                <td>
                    <span class="badge-severity ${badgeSeverityClass}">
                        ${severity}
                    </span>
                </td>
                <td>${escapeHtml(alert.message)}</td>
                <td>${escapeHtml(alert.location || 'System')}</td>
                <td class="text-sm">${formattedDate}</td>
                <td>
                    <div class="flex gap-1">
                        ${!isRead ? `
                        <button class="btn-icon btn-mark-read" onclick="markRead(${alert.id})" title="Mark as read">
                            <i class="fas fa-check"></i>
                        </button>` : ''}
                        <button class="btn-icon btn-delete" onclick="deleteAlert(${alert.id})" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
    }).join('');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===== ACTIONS =====
function markRead(alertId) {
    fetch('mark_alert_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'alert_id=' + alertId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAlerts();
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllRead() {
    if (confirm('Mark all alerts as read?')) {
        fetch('mark_all_alerts_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'user_id=<?= $user_id ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadAlerts();
            }
        })
        .catch(error => console.error('Error:', error));
    }
}

function deleteAlert(alertId) {
    if (confirm('Delete this alert?')) {
        fetch('delete_alert.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'alert_id=' + alertId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadAlerts();
            }
        })
        .catch(error => console.error('Error:', error));
    }
}

function clearAllAlerts() {
    if (confirm('Delete ALL alerts? This cannot be undone.')) {
        fetch('delete_all_alerts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'user_id=<?= $user_id ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadAlerts();
            }
        })
        .catch(error => console.error('Error:', error));
    }
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
</script>
</body>
</html>