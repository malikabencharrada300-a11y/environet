<?php
require_once 'config.php';
header('Content-Type: application/json');

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 1;

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Marquer toutes les alertes comme lues
    $stmt = $pdo->prepare("
        UPDATE alerts 
        SET status = 'read', read_at = NOW() 
        WHERE user_id = ? AND status = 'unread'
    ");
    $stmt->execute([$user_id]);
    
    echo json_encode([
        "success" => true,
        "message" => "All alerts marked as read",
        "updated_count" => $stmt->rowCount()
    ]);
    
} catch (Exception $e) {
    error_log("Error in mark_all_alerts_read.php: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Error updating alerts"
    ]);
}
?>