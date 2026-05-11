<?php
require_once 'config.php';
require_once 'alert_manager.php';

$user_id = isLoggedIn() ? $_SESSION['user_id'] : 1;
$alertManager = new AlertManager($user_id);

// Test avec les valeurs actuelles
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM esp32_cam_data WHERE user_id = ? ORDER BY timestamp DESC LIMIT 1");
$stmt->execute([$user_id]);
$data = $stmt->fetch();

echo "<h2>État actuel du système d'alertes</h2>";

if ($data) {
    echo "<pre>";
    echo "Température: " . ($data['temperature'] ?? 'N/A') . "°C\n";
    echo "Humidité: " . ($data['humidity'] ?? 'N/A') . "%\n";
    echo "Signal: " . ($data['signal_strength'] ?? 'N/A') . "%\n";
    echo "</pre>";
    
    // Afficher l'état précédent
    $stmt = $pdo->prepare("SELECT * FROM alert_state WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $states = $stmt->fetchAll();
    
    echo "<h3>États enregistrés :</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Type</th><th>État</th><th>Dernière valeur</th><th>Vérifié le</th></tr>";
    foreach ($states as $state) {
        echo "<tr>";
        echo "<td>{$state['alert_type']}</td>";
        echo "<td>{$state['current_state']}</td>";
        echo "<td>{$state['last_value']}</td>";
        echo "<td>{$state['last_checked']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucune donnée disponible</p>";
}
?>