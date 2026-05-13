<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$period = $_GET['period'] ?? '24h';

try {
    $pdo = getDBConnection();

    // PostgreSQL interval
    switch ($period) {
        case '24h':
            $condition = "AND timestamp >= NOW() - INTERVAL '24 hours'";
            break;
        case '7d':
            $condition = "AND timestamp >= NOW() - INTERVAL '7 days'";
            break;
        case '30d':
            $condition = "AND timestamp >= NOW() - INTERVAL '30 days'";
            break;
        default:
            $condition = "";
    }

    $stmt = $pdo->prepare("
        SELECT temperature, humidity, signal_strength, bandwidth, ping, timestamp
        FROM sensor_data
        WHERE user_id = ?
        $condition
        ORDER BY timestamp DESC
    ");

    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll();

    while (ob_get_level()) {
        ob_end_clean();
    }

   header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=history_export_" . date("Y-m-d_H-i-s") . ".csv");

echo "Date;Temperature;Humidity;Signal;Bandwidth;Ping;Status\n";

    foreach ($rows as $row) {

        $status = "Normal";

        if ($row['temperature'] > 28 || $row['humidity'] > 80) {
            $status = "Critical";
        } elseif ($row['temperature'] > 24 || $row['humidity'] > 70) {
            $status = "Warning";
        }

        echo date('d/m/Y H:i:s', strtotime($row['timestamp'])) . ";";
echo ($row['temperature'] ?? '--') . ";";
echo ($row['humidity'] ?? '--') . ";";
echo ($row['signal_strength'] ?? '--') . ";";
echo ($row['bandwidth'] ?? '--') . ";";
echo ($row['ping'] ?? '--') . ";";
echo $status . "\n";
    }

    exit;

} catch (Exception $e) {
    die("Erreur export");
}
?>
