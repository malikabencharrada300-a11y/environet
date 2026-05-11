<?php
require_once 'config.php';
requireLogin();

$user_id = $_GET['user_id'] ?? $_SESSION['user_id'];
$period = $_GET['period'] ?? '24h';

// Déterminer l'intervalle
switch ($period) {
    case '24h': $interval = '24 HOUR'; break;
    case '7d': $interval = '7 DAY'; break;
    case '30d': $interval = '30 DAY'; break;
    case 'all': $interval = null; break;
    default: $interval = '24 HOUR';
}

try {
    $pdo = getDBConnection();
    
    // Utiliser la table qui a des données
    $count = $pdo->query("SELECT COUNT(*) FROM sensor_data")->fetchColumn();
    $table = ($count > 0) ? 'sensor_data' : 'esp32_cam_data';
    
    // Requête
    if ($interval) {
        $sql = "SELECT * FROM $table WHERE user_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL $interval) ORDER BY timestamp DESC";
    } else {
        $sql = "SELECT * FROM $table WHERE user_id = ? ORDER BY timestamp DESC";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $data = $stmt->fetchAll();
    
    // Nettoyer le buffer
    while (ob_get_level()) ob_end_clean();
    
    // Headers pour le téléchargement
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="export_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    // BOM UTF-8
    echo "\xEF\xBB\xBF";
    
    // En-têtes
    echo "Date/Heure;Temperature (°C);Humidite (%);Signal WiFi (%);Bande passante (Mbps);Ping (ms);Status\n";
    
    // Données
    foreach ($data as $row) {
        $temp = $row['temperature'] ?? '';
        $hum = $row['humidity'] ?? '';
        $sig = $row['signal_strength'] ?? '';
        $bw = $row['bandwidth'] ?? '';
        $ping = $row['ping'] ?? '';
        
        // Statut
        $status = 'Normal';
        if ($temp > 28 || $hum > 80 || ($sig && $sig < 30)) $status = 'Critical';
        elseif ($temp > 24 || $hum > 70 || ($sig && $sig < 50)) $status = 'Warning';
        
        echo date('d/m/Y H:i:s', strtotime($row['timestamp'])) . ';';
        echo $temp . ';';
        echo $hum . ';';
        echo $sig . ';';
        echo $bw . ';';
        echo $ping . ';';
        echo $status . "\n";
    }
    
    exit;
    
} catch (Exception $e) {
    header('Location: history.php?error=export');
    exit;
}
?>