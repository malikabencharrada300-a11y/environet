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
    }

    // Utiliser sensor_data pour l'historique
   $stmt = $pdo->prepare("
    SELECT temperature, humidity, signal_strength, bandwidth, created_at
    FROM sensor_data
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)
    ORDER BY created_at DESC
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
