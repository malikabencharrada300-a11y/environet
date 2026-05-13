<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$user_id = $_SESSION['user_id'];
$period = $_GET['period'] ?? '24h';

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion']);
        exit;
    }

    // Déterminer la période
  switch ($period) {
    case '24h':
        $interval = '24 hours';
        break;
    case '7d':
        $interval = '7 days';
        break;
    case '30d':
        $interval = '30 days';
        break;
    default:
        $interval = '24 hours';
}

$stmt = $pdo->prepare("
    SELECT temperature, humidity, signal_strength, bandwidth, ping, timestamp
    FROM sensor_data
    WHERE timestamp >= NOW() - INTERVAL '$interval'
    ORDER BY timestamp DESC
      LIMIT 50
");
$stmt->execute();
    $history = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'message' => 'Historique récupéré',
        'history' => $history,
        'count' => count($history)
    ]);

} catch (PDOException $e) {
    error_log("Erreur get_history: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur'
    ]);
}
?>
