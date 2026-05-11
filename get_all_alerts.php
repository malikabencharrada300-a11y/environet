<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(false, 'Non autorisé');
}

$user_id = $_GET['user_id'] ?? $_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        jsonResponse(false, 'Erreur de connexion');
    }

    $stmt = $pdo->prepare("
        SELECT * FROM alerts 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 200
    ");
    $stmt->execute([$user_id]);
    $alerts = $stmt->fetchAll();

    // Formater les alertes pour le frontend
    $formattedAlerts = array_map(function($alert) {
        return [
            'id' => $alert['id'],
            'type' => $alert['severity'],
            'message' => $alert['message'],
            'location' => $alert['location'] ?? 'System',
            'is_read' => $alert['status'] === 'read',
            'created_at' => $alert['created_at']
        ];
    }, $alerts);

    jsonResponse(true, 'Alertes récupérées', ['alerts' => $formattedAlerts]);

} catch (PDOException $e) {
    error_log("Erreur get_alerts: " . $e->getMessage());
    jsonResponse(false, 'Erreur serveur');
}
?>