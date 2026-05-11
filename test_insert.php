<?php
require_once 'config.php';

// Simuler l'envoi de données comme le fait le dashboard
$data = json_encode([
    'user_id' => 1,
    'temperature' => 30,
    'humidity' => 85,
    'signal_strength' => 25,
    'bandwidth' => 50,
    'ping' => 20
]);

// Appeler insert.php via HTTP
$ch = curl_init(APP_URL . '/insert.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h2>Réponse HTTP: $httpCode</h2>";
$result = json_decode($response, true);
echo "<pre>";
print_r($result);
echo "</pre>";

// Vérifier les alertes créées
echo "<h2>Alertes en base :</h2>";
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT * FROM alerts ORDER BY created_at DESC LIMIT 10");
$alerts = $stmt->fetchAll();
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Type</th><th>Severity</th><th>Message</th><th>Status</th><th>Date</th></tr>";
foreach ($alerts as $alert) {
    echo "<tr>";
    echo "<td>{$alert['id']}</td>";
    echo "<td>{$alert['type']}</td>";
    echo "<td>{$alert['severity']}</td>";
    echo "<td>{$alert['message']}</td>";
    echo "<td>{$alert['status']}</td>";
    echo "<td>{$alert['created_at']}</td>";
    echo "</tr>";
}
echo "</table>";

// Vérifier l'état des alertes
echo "<h2>État des alertes :</h2>";
$stmt = $pdo->query("SELECT * FROM alert_state");
$states = $stmt->fetchAll();
echo "<pre>"; print_r($states); echo "</pre>";
?>