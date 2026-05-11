<?php
require_once 'config.php';
header('Content-Type: application/json');

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 1;

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Supprimer toutes les alertes de l'utilisateur
    $stmt = $pdo->prepare("DELETE FROM alerts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    echo json_encode([
        "success" => true,
        "message" => "All alerts deleted",
        "deleted_count" => $stmt->rowCount()
    ]);
    
} catch (Exception $e) {
    error_log("Error in delete_all_alerts.php: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Error deleting alerts"
    ]);
}
?>