<?php
require_once 'config.php';
header('Content-Type: application/json');

$room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 1;

if ($room_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid room ID"
    ]);
    exit;
}

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Vérifier qu'il reste au moins une salle
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rooms WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $count = $stmt->fetch()['count'];
    
    if ($count <= 1) {
        echo json_encode([
            "success" => false,
            "message" => "Cannot delete the last room"
        ]);
        exit;
    }
    
    // Supprimer la salle
    $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ? AND user_id = ?");
    $stmt->execute([$room_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "success" => true,
            "message" => "Room deleted successfully"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Room not found"
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in delete_room.php: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Error deleting room"
    ]);
}
?>