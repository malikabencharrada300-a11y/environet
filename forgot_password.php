<?php
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email valide requis']);
    exit;
}

try {
    // Vérifier si l'utilisateur existe
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Pour des raisons de sécurité, on ne révèle pas si l'email existe
        echo json_encode([
            'success' => true, 
            'message' => 'Si cet email existe, un lien de réinitialisation vous sera envoyé.'
        ]);
        exit;
    }
    
    // Générer un token unique
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Sauvegarder le token dans la base de données
    $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $token, $expires]);
    
    // Créer le lien de réinitialisation
    $resetLink = SITE_URL . "/index.php?token=" . $token;
    
    // Message email HTML
    $emailMessage = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #11324d; color: white; padding: 20px; text-align: center; }
            .content { padding: 30px; background: #f9f9f9; }
            .button { 
                display: inline-block; 
                padding: 15px 30px; 
                background: #11324d; 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 20px 0; 
            }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .code { 
                font-size: 32px; 
                font-weight: bold; 
                color: #11324d; 
                letter-spacing: 5px;
                text-align: center;
                padding: 20px;
                background: white;
                border-radius: 10px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>EnviroNet</h1>
                <h2>Réinitialisation de mot de passe</h2>
            </div>
            <div class='content'>
                <p>Bonjour <strong>{$user['name']}</strong>,</p>
                <p>Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte EnviroNet.</p>
                
                <div class='code'>
                    <p>Code de réinitialisation:</p>
                    <p style='font-size: 28px; letter-spacing: 3px;'>{$token}</p>
                </div>
                
                <p>Ou cliquez sur le bouton ci-dessous pour réinitialiser votre mot de passe :</p>
                
                <center>
                    <a href='{$resetLink}' class='button'>Réinitialiser mon mot de passe</a>
                </center>
                
                <p><strong>Ce lien expire dans 1 heure.</strong></p>
                <p>Si vous n'avez pas demandé cette réinitialisation, ignorez simplement cet email.</p>
            </div>
            <div class='footer'>
                <p>© 2026 EnviroNet. Tous droits réservés.</p>
                <p>WWW.EnviroNet.COM</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Envoyer l'email
    $emailSent = sendEmail($email, "Réinitialisation de mot de passe - EnviroNet", $emailMessage);
    
    if ($emailSent) {
        echo json_encode([
            'success' => true,
            'message' => 'Un email de réinitialisation a été envoyé. Veuillez vérifier votre boîte de réception.'
        ]);
    } else {
        // Si l'email n'a pas pu être envoyé, on supprime le token
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);
        
        echo json_encode([
            'success' => false, 
            'message' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    error_log("Erreur forgot_password: " . $e->getMessage());
}
?>