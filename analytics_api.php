<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// =====================================================
// DATABASE CONFIGURATION
// =====================================================

$host = "aws-1-eu-west-2.pooler.supabase.com";
$dbname = "postgres";
$user = "postgres.gfwbtyjzpwvbwpxipdap";
$password = "ghadaa2004+12+25";
$port = "6543";

// =====================================================
// CONNECT TO DATABASE
// =====================================================

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $pdo->exec("SET TIME ZONE 'Africa/Tunis'");
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "device_status" => "OFFLINE",
        "message" => $e->getMessage()
    ]);
    exit;
}

// =====================================================
// GET PARAMETERS
// =====================================================

$range = $_GET["range"] ?? "Day";
$alert_range = $_GET["alert_range"] ?? "Day";

// Convert range to SQL interval
function getInterval($range) {
    switch ($range) {
        case "Hour":
            return "1 hour";
        case "Day":
            return "24 hours";
        case "Week":
            return "7 days";
        case "Month":
            return "30 days";
        default:
            return "24 hours";
    }
}

$interval = getInterval($range);
$alertInterval = getInterval($alert_range);

// =====================================================
// GET LATEST SENSOR DATA
// =====================================================

$stmtSensor = $pdo->prepare("
    SELECT temperature, humidity, timestamp
    FROM sensor_data
    ORDER BY id DESC
    LIMIT 1
");
$stmtSensor->execute();
$sensor = $stmtSensor->fetch(PDO::FETCH_ASSOC);

// =====================================================
// GET LATEST NETWORK DATA
// =====================================================

$stmtNetwork = $pdo->prepare("
    SELECT signal_strength, bandwidth, ping
    FROM esp32_cam_data
    ORDER BY id DESC
    LIMIT 1
");
$stmtNetwork->execute();
$network = $stmtNetwork->fetch(PDO::FETCH_ASSOC);

// =====================================================
// DEFAULT VALUES IF NO DATA
// =====================================================

$temperature = floatval($sensor["temperature"] ?? 23.5);
$humidity = floatval($sensor["humidity"] ?? 55.0);
$timestamp = $sensor["timestamp"] ?? date("Y-m-d H:i:s");
$rssi = intval($network["signal_strength"] ?? -65);
$bandwidth = floatval($network["bandwidth"] ?? 0);
$ping = intval($network["ping"] ?? 0);

// =====================================================
// CALCULATE SIGNAL PERCENTAGE
// =====================================================

if ($rssi <= -100) {
    $signal = 0;
} elseif ($rssi >= -50) {
    $signal = 100;
} else {
    $signal = round(2 * ($rssi + 100));
}

// =====================================================
// DEVICE STATUS
// =====================================================

$device_status = ($signal > 20) ? "ONLINE" : "OFFLINE";

// =====================================================
// DHT11 STATE
// =====================================================

$dht_state = ($temperature > 0 && $humidity > 0) ? "Connected" : "Disconnected";

// =====================================================
// TEMPERATURE STATUS
// =====================================================

if ($temperature >= 35) {
    $temp_status = "High";
    $temp_color = "red";
} elseif ($temperature <= 15) {
    $temp_status = "Low";
    $temp_color = "orange";
} else {
    $temp_status = "Normal";
    $temp_color = "green";
}

// =====================================================
// HUMIDITY STATUS
// =====================================================

if ($humidity >= 70) {
    $humidity_status = "High";
} elseif ($humidity <= 35) {
    $humidity_status = "Low";
} else {
    $humidity_status = "Normal";
}

// =====================================================
// SIGNAL STATUS
// =====================================================

if ($signal >= 80) {
    $signal_status = "Excellent";
} elseif ($signal >= 60) {
    $signal_status = "Good";
} else {
    $signal_status = "Poor";
}

// =====================================================
// HEALTH STATUS
// =====================================================

if ($temperature >= 40 || $signal <= 20) {
    $health = "Critical";
} elseif ($temperature >= 33 || $signal <= 50) {
    $health = "Warning";
} else {
    $health = "Optimal";
}

// =====================================================
// AI SCORE
// =====================================================

$ai_score = max(50, min(100, intval(($signal + (100 - abs($temperature - 25))) / 2)));

// =====================================================
// ANOMALY DETECTION
// =====================================================

// Get average temperature from last hour
$stmtAvg = $pdo->prepare("
    SELECT AVG(temperature) as avg_temp
    FROM sensor_data
    WHERE timestamp >= NOW() - INTERVAL '1 hour'
");
$stmtAvg->execute();
$avgResult = $stmtAvg->fetch(PDO::FETCH_ASSOC);
$avgTemp = floatval($avgResult["avg_temp"] ?? $temperature);

$has_anomaly = (abs($temperature - $avgTemp) > 8);
$anomalies = $has_anomaly ? "1" : "0";

// =====================================================
// INSIGHTS
// =====================================================

if ($temperature > 35) {
    $insights = "High temperature detected: $temperature°C";
} elseif ($signal < 30) {
    $insights = "Weak network signal: $signal%";
} elseif ($humidity > 75) {
    $insights = "High humidity level: $humidity%";
} else {
    $insights = "Environment stable. All parameters normal.";
}

// =====================================================
// PREDICTIONS
// =====================================================

if ($temperature > 30) {
    $predictions = "Temperature may continue rising";
} elseif ($temperature < 18) {
    $predictions = "Temperature may drop further";
} else {
    $predictions = "Stable conditions expected for next hour";
}

// =====================================================
// RECOMMENDATIONS
// =====================================================

if ($signal < 40) {
    $recommendations = "Move device closer to router or check antenna";
} elseif ($temperature > 35) {
    $recommendations = "Check cooling system, temperature too high";
} elseif ($humidity > 75) {
    $recommendations = "Consider dehumidification";
} else {
    $recommendations = "System operating normally. No action needed.";
}

// =====================================================
// ALERT COUNTS
// =====================================================

$stmtAlerts = $pdo->prepare("
    SELECT
        COUNT(*) FILTER (WHERE type='temperature') as temp_alerts,
        COUNT(*) FILTER (WHERE type='signal') as signal_alerts,
        COUNT(*) FILTER (WHERE type='connection') as connection_alerts,
        COUNT(*) as today_alerts
    FROM alerts
    WHERE created_at >= NOW() - INTERVAL '$interval'
");
$stmtAlerts->execute();
$alerts = $stmtAlerts->fetch(PDO::FETCH_ASSOC);

// =====================================================
// TEMPERATURE CHART (Last 50 points)
// =====================================================

$stmtTempChart = $pdo->prepare("
    SELECT temperature
    FROM sensor_data
    WHERE timestamp >= NOW() - INTERVAL '$interval'
    ORDER BY timestamp ASC
    LIMIT 50
");
$stmtTempChart->execute();
$tempRows = $stmtTempChart->fetchAll(PDO::FETCH_ASSOC);

$temp_chart = [];
foreach ($tempRows as $row) {
    $temp_chart[] = floatval($row["temperature"]);
}

// =====================================================
// HUMIDITY CHART
// =====================================================

$stmtHumidityChart = $pdo->prepare("
    SELECT humidity
    FROM sensor_data
    WHERE timestamp >= NOW() - INTERVAL '$interval'
    ORDER BY timestamp ASC
    LIMIT 50
");
$stmtHumidityChart->execute();
$humidityRows = $stmtHumidityChart->fetchAll(PDO::FETCH_ASSOC);

$humidity_chart = [];
foreach ($humidityRows as $row) {
    $humidity_chart[] = floatval($row["humidity"]);
}

// =====================================================
// SIGNAL CHART (with percentage conversion)
// =====================================================

$stmtSignalChart = $pdo->prepare("
    SELECT signal_strength
    FROM esp32_cam_data
    WHERE timestamp >= NOW() - INTERVAL '$interval'
    ORDER BY timestamp ASC
    LIMIT 50
");
$stmtSignalChart->execute();
$signalRows = $stmtSignalChart->fetchAll(PDO::FETCH_ASSOC);

$signal_chart = [];
foreach ($signalRows as $row) {
    $r = intval($row["signal_strength"]);
    if ($r <= -100) {
        $percent = 0;
    } elseif ($r >= -50) {
        $percent = 100;
    } else {
        $percent = round(2 * ($r + 100));
    }
    $signal_chart[] = $percent;
}

// =====================================================
// ALERT CHART (Alert levels: 0=Normal, 1=Warning, 2=Critical)
// =====================================================

$stmtAlertChart = $pdo->prepare("
    SELECT 
        created_at,
        type,
        severity
    FROM alerts
    WHERE created_at >= NOW() - INTERVAL '$alertInterval'
    ORDER BY created_at ASC
    LIMIT 50
");
$stmtAlertChart->execute();
$alertRows = $stmtAlertChart->fetchAll(PDO::FETCH_ASSOC);

$alert_chart = [];
foreach ($alertRows as $row) {
    $severity = intval($row["severity"] ?? 1);
    $alert_chart[] = $severity;
}

// If no alerts, return zeros
if (empty($alert_chart)) {
    $alert_chart = [0, 0, 0, 0, 0];
}

// =====================================================
// HISTORY STATS (Temperature)
// =====================================================

if (count($temp_chart) > 0) {
    $history_min = min($temp_chart) . "°C";
    $history_max = max($temp_chart) . "°C";
    $history_avg = round(array_sum($temp_chart) / count($temp_chart), 1) . "°C";
} else {
    $history_min = "0°C";
    $history_max = "0°C";
    $history_avg = "0°C";
}

// =====================================================
// DATA POINTS COUNT
// =====================================================

$data_points = count($temp_chart);

// =====================================================
// TEMP TREND (calculate from last 10 readings)
// =====================================================

$temp_trend = 0;
if (count($temp_chart) >= 10) {
    $recent = array_slice($temp_chart, -5);
    $older = array_slice($temp_chart, -10, 5);
    $temp_trend = round(array_sum($recent) / 5 - array_sum($older) / 5, 1);
}
$temp_trend_str = ($temp_trend >= 0 ? "+" : "") . $temp_trend . "°C";

// =====================================================
// SIGNAL TREND
// =====================================================

$signal_trend = 0;
if (count($signal_chart) >= 10) {
    $recent = array_slice($signal_chart, -5);
    $older = array_slice($signal_chart, -10, 5);
    $signal_trend = round(array_sum($recent) / 5 - array_sum($older) / 5, 1);
}
$signal_trend_str = ($signal_trend >= 0 ? "+" : "") . $signal_trend . "%";

// =====================================================
// UPTIME (random for demo - replace with actual calculation)
// =====================================================

$uptime_hours = rand(1, 720);
$uptime = floor($uptime_hours / 24) . "d " . ($uptime_hours % 24) . "h";

// =====================================================
// LAST ACTIVITY
// =====================================================

$last_activity = date("H:i:s", strtotime($timestamp));

// =====================================================
// RESPONSE
// =====================================================

$response = [
    "success" => true,
    
    // Device Status
    "device_status" => $device_status,
    "dht_state" => $dht_state,
    "last_activity" => $last_activity,
    "uptime" => $uptime,
    
    // Temperature
    "temperature" => round($temperature, 1),
    "temp_status" => $temp_status,
    "temp_trend" => $temp_trend_str,
    
    // Humidity
    "humidity" => round($humidity, 1),
    "humidity_status" => $humidity_status,
    
    // Signal
    "signal" => $signal,
    "signal_status" => $signal_status,
    "signal_trend" => $signal_trend_str,
    
    // AI Analysis
    "ai_score" => $ai_score,
    "health" => $health,
    "has_anomaly" => $has_anomaly,
    "anomalies" => $anomalies,
    "data_points" => $data_points,
    
    // Insights & Predictions
    "insights" => $insights,
    "predictions" => $predictions,
    "recommendations" => $recommendations,
    
    // History Stats
    "history_min" => $history_min,
    "history_max" => $history_max,
    "history_avg" => $history_avg,
    
    // Alert Counts
    "temp_alerts" => intval($alerts["temp_alerts"] ?? 0),
    "signal_alerts" => intval($alerts["signal_alerts"] ?? 0),
    "connection_alerts" => intval($alerts["connection_alerts"] ?? 0),
    "today_alerts" => intval($alerts["today_alerts"] ?? 0),
    
    // Charts Data
    "temp_chart" => $temp_chart,
    "humidity_chart" => $humidity_chart,
    "signal_chart" => $signal_chart,
    "alert_chart" => $alert_chart
];

echo json_encode($response);

?>
