<?php
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée');
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Email valide requis');
}

try {
    $pdo = getDBConnection(); // <-- important

    // vérifier email
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(true, 'Si cet email existe, un lien sera envoyé');
    }

    // générer token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // table token
    $stmt = $pdo->prepare("
        INSERT INTO password_reset_tokens (user_id, token, expires_at)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $token, $expires]);

    // pour test (sans mail)
    jsonResponse(true, 'Token créé', [
        'token' => $token
    ]);

} catch (Exception $e) {
    error_log("forgot error: " . $e->getMessage());
    jsonResponse(false, 'Erreur serveur');
}
?>
