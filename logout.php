<?php
require_once 'config.php';

// Détruire la session
$_SESSION = [];

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Supprimer le cookie remember_token
if (isset($_COOKIE['remember_token'])) {
    // Supprimer de la base de données
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?");
            $stmt->execute([$_COOKIE['remember_token']]);
        }
    } catch (PDOException $e) {
        error_log("Erreur logout: " . $e->getMessage());
    }
    
    setcookie('remember_token', '', time() - 3600, '/');
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header('Location: index.php');
exit;
?>
