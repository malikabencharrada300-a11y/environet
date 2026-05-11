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
    
    // Supprimer l'alerte
    $stmt = $pdo->prepare("DELETE FROM alerts WHERE id = ?");
    $stmt->execute([$alert_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "success" => true,
            "message" => "Alert deleted successfully"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Alert not found"
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in delete_alert.php: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Error deleting alert"
    ]);
}
?>