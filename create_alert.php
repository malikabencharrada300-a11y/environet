<?php
require_once 'config.php';
header('Content-Type: application/json');

$user_id = $_POST['user_id'] ?? 1;
$type = $_POST['type'] ?? 'system';
$severity = $_POST['severity'] ?? 'info';
$message = $_POST['message'] ?? '';
$location = $_POST['location'] ?? 'System';

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message required']);
    exit;
}

try {
    $alertId = createAlert($user_id, $type, $message, $severity, $location);
    
    echo json_encode([
        'success' => true,
        'alert_id' => $alertId,
        'message' => 'Alert created'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>