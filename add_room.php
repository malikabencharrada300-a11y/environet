<?php
require_once 'config.php';
header('Content-Type: application/json');

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 1;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$location = isset($_POST['location']) ? trim($_POST['location']) : '';
$position_top = isset($_POST['position_top']) ? $_POST['position_top'] : '25%';
$position_left = isset($_POST['position_left']) ? $_POST['position_left'] : '33%';

if (empty($name)) {
    echo json_encode([
        "success" => false,
        "message" => "Room name is required"
    ]);
    exit;
}

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Ajouter la salle
    $stmt = $pdo->prepare("
        INSERT INTO rooms (user_id, name, location, position_top, position_left, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $name, $location, $position_top, $position_left]);
    $room_id = $pdo->lastInsertId();
    
    // Récupérer la salle créée
    $stmt2 = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt2->execute([$room_id]);
    $room = $stmt2->fetch();
    
    echo json_encode([
        "success" => true,
        "message" => "Room added successfully",
        "room" => $room
    ]);
    
} catch (Exception $e) {
    error_log("Error in add_room.php: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Error adding room"
    ]);
}
?>