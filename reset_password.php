<?php
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$token = $_POST['token'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validation
if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token invalide']);
    exit;
}

if (empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'Nouveau mot de passe requis']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Les mots de passe ne correspondent pas']);
    exit;
}

try {
    // Vérifier le token
    $stmt = $pdo->prepare("
        SELECT user_id, expires_at, used 
        FROM password_reset_tokens 
        WHERE token = ? AND expires_at > NOW() AND used = 0
    ");
    $stmt->execute([$token]);
    $resetToken = $stmt->fetch();
    
    if (!$resetToken) {
        echo json_encode(['success' => false, 'message' => 'Lien de réinitialisation invalide ou expiré']);
        exit;
    }
    
    // Hasher le nouveau mot de passe
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    
    // Mettre à jour le mot de passe
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed_password, $resetToken['user_id']]);
    
    // Marquer le token comme utilisé
    $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
    $stmt->execute([$token]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Mot de passe réinitialisé avec succès! Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    error_log("Erreur reset_password: " . $e->getMessage());
}
?>