<?php
require_once 'config.php';
header('Content-Type: application/json');

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Récupérer les dernières données de l'ESP32
    $stmt = $pdo->prepare("
        SELECT * FROM esp32_cam_data 
        WHERE user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $data = $stmt->fetch();
    
    if ($data) {
        echo json_encode([
            "success" => true,
            "data" => [
                "temperature" => $data['temperature'],
                "humidity" => $data['humidity'],
                "signal_strength" => $data['signal_strength'],
                "bandwidth" => $data['bandwidth'],
                "ping" => $data['ping'],
                "ssid" => $data['ssid'],
                "ip_address" => $data['ip_address'],
                "mac_address" => $data['mac_address'],
                "rssi" => $data['signal_strength'] ? round(($data['signal_strength'] / 100) * -30 - 30) : null,
                "timestamp" => $data['timestamp']
            ]
        ]);
    } else {
        echo json_encode([
            "success" => true,
            "data" => null,
            "message" => "No data available yet"
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get_latest_data.php: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Error retrieving data"
    ]);
}
?>