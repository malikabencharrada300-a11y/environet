<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(false, 'Non autorisé');
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        jsonResponse(false, 'Erreur de connexion à la base de données');
    }

    $stmt = $pdo->prepare("
        SELECT temperature, humidity, signal_strength, bandwidth, ping, 
               ssid, ip_address, mac_address, timestamp 
        FROM esp32_cam_data 
        WHERE user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $data = $stmt->fetch();

    if ($data) {
        // Formater pour le frontend
        $response = [
            'temperature' => $data['temperature'],
            'humidity' => $data['humidity'],
            'signal_strength' => $data['signal_strength'],
            'bandwidth' => $data['bandwidth'],
            'ping' => $data['ping'],
            'wifi' => [
                'ssid' => $data['ssid'] ?? 'Inconnu',
                'ip' => $data['ip_address'] ?? 'Non disponible',
                'mac' => $data['mac_address'] ?? 'Non disponible',
                'status' => $data['ssid'] ? 'Connected' : 'Disconnected'
            ],
            'timestamp' => $data['timestamp']
        ];
        jsonResponse(true, 'Données récupérées', ['data' => $response]);
    } else {
        jsonResponse(false, 'Aucune donnée disponible');
    }
} catch (PDOException $e) {
    error_log("Erreur get_sensor_data: " . $e->getMessage());
    jsonResponse(false, 'Erreur serveur');
}
?>