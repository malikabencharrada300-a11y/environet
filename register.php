<?php
// register.php - Création de compte
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée');
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

if (empty($name) || empty($email) || empty($password)) {
    jsonResponse(false, 'Veuillez remplir tous les champs');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Email invalide');
}

if (strlen($password) < 6) {
    jsonResponse(false, 'Le mot de passe doit contenir au moins 6 caractères');
}

if ($password !== $confirm_password) {
    jsonResponse(false, 'Les mots de passe ne correspondent pas');
}

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'Cet email est déjà utilisé');
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'user', NOW())");
    $stmt->execute([$name, $email, $hashedPassword]);
    
    $userId = $pdo->lastInsertId();
    addRoom($userId, 'Main Room', 'ESP32-CAM Location');
    
    jsonResponse(true, 'Compte créé avec succès');
    
} catch(PDOException $e) {
    error_log("Erreur register: " . $e->getMessage());
    jsonResponse(false, 'Erreur lors de la création du compte');
}
?>