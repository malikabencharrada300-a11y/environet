<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized',
        'alerts' => []
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'all';

try {
    $pdo = getDBConnection();

    $sql = "
        SELECT 
            TO_CHAR(created_at, 'DD/MM') as day,
            COUNT(*) as total,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning,
            SUM(CASE WHEN severity = 'info' THEN 1 ELSE 0 END) as info,
            SUM(CASE WHEN type = 'temperature' THEN 1 ELSE 0 END) as temperature,
            SUM(CASE WHEN type = 'signal' THEN 1 ELSE 0 END) as signal,
            SUM(CASE WHEN type = 'connection' THEN 1 ELSE 0 END) as connection
        FROM alerts
        WHERE user_id = ?
        AND created_at >= NOW() - INTERVAL '7 days'
    ";

    $params = [$user_id];

    if ($filter !== 'all') {
        $sql .= " AND type = ?";
        $params[] = $filter;
    }

    $sql .= "
        GROUP BY TO_CHAR(created_at, 'DD/MM'), DATE(created_at)
        ORDER BY DATE(created_at) ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $alerts = [];
    $today = date('d/m');

    $todayAlerts = 0;
    $todayCritical = 0;
    $todayWarning = 0;
    $todayInfo = 0;
    $totalAlerts = 0;

    $daysMap = [];

    foreach ($rows as $row) {
        $day = $row['day'];

        $entry = [
            'day' => $day,
            'total' => (int)$row['total'],
            'critical' => (int)$row['critical'],
            'warning' => (int)$row['warning'],
            'info' => (int)$row['info'],
            'temperature' => (int)$row['temperature'],
            'signal' => (int)$row['signal'],
            'connection' => (int)$row['connection']
        ];

        $daysMap[$day] = $entry;
        $totalAlerts += (int)$row['total'];

        if ($day === $today) {
            $todayAlerts = (int)$row['total'];
            $todayCritical = (int)$row['critical'];
            $todayWarning = (int)$row['warning'];
            $todayInfo = (int)$row['info'];
        }
    }

    for ($i = 6; $i >= 0; $i--) {
        $day = date('d/m', strtotime("-$i days"));

        if (isset($daysMap[$day])) {
            $alerts[] = $daysMap[$day];
        } else {
            $alerts[] = [
                'day' => $day,
                'total' => 0,
                'critical' => 0,
                'warning' => 0,
                'info' => 0,
                'temperature' => 0,
                'signal' => 0,
                'connection' => 0
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'alerts' => $alerts,
        'total' => $totalAlerts,
        'today' => $todayAlerts,
        'todayCritical' => $todayCritical,
        'todayWarning' => $todayWarning,
        'todayInfo' => $todayInfo
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'alerts' => []
    ]);
}
?>
