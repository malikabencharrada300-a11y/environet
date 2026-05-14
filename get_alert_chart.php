<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

try {
    $pdo = getDBConnection();
    
    // Requête de base
    $sql = "
        SELECT 
            DATE(created_at) as day, 
            COUNT(*) as total,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning,
            SUM(CASE WHEN severity = 'info' THEN 1 ELSE 0 END) as info
        FROM alerts
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ";
    
    // Ajouter le filtre si nécessaire
    if ($filter !== 'all') {
        $sql .= " AND type = ?";
    }
    
    $sql .= " GROUP BY DATE(created_at) ORDER BY day ASC";
    
    $stmt = $pdo->prepare($sql);
    
    if ($filter !== 'all') {
        $stmt->execute([$user_id, $filter]);
    } else {
        $stmt->execute([$user_id]);
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les données pour le graphique
    $alerts = [];
    $totalAlerts = 0;
    
    foreach ($rows as $row) {
        $day = date('d/m', strtotime($row['day']));
        $total = (int)$row['total'];
        $totalAlerts += $total;
        
        $alerts[] = [
            'day' => $day,
            'total' => $total,
            'critical' => (int)$row['critical'],
            'warning' => (int)$row['warning'],
            'info' => (int)$row['info']
        ];
    }
    
    // Si pas de données, retourner un tableau vide
    if (empty($alerts)) {
        // Générer des données de démonstration pour les 7 derniers jours
        for ($i = 6; $i >= 0; $i--) {
            $date = date('d/m', strtotime("-$i days"));
            $alerts[] = [
                'day' => $date,
                'total' => 0,
                'critical' => 0,
                'warning' => 0,
                'info' => 0
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'alerts' => $alerts,
        'total' => $totalAlerts,
        'filter' => $filter
    ]);
    
} catch(Exception $e) {
    error_log("Error in get_alert_chart.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'alerts' => []
    ]);
}
?>
