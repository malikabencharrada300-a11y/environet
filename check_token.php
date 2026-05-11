<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_COOKIE['remember_token'])) {
    echo json_encode(['success' => false]);
    exit;
}

$token = $_COOKIE['remember_token'];

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email 
        FROM remember_tokens rt 
        JOIN users u ON rt.user_id = u.id 
        WHERE rt.token = ? AND rt.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        // Token invalide, on le supprime
        setcookie('remember_token', '', time() - 3600, '/');
        echo json_encode(['success' => false]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false]);
    error_log("Erreur check_token: " . $e->getMessage());
}
?>