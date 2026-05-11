<?php
require_once 'config.php';
header('Content-Type: application/json');

$alert_id = isset($_POST['alert_id']) ? intval($_POST['alert_id']) : 0;

if ($alert_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid alert ID"
    ]);
    exit;
}

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Marquer l'alerte comme lue
    $stmt = $pdo->prepare("
        UPDATE alerts 
        SET status = 'read', read_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$alert_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "success" => true,
            "message" => "Alert marked as read"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Alert not found"
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in mark_alert_read.php: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Error updating alert"
    ]);
}
?>