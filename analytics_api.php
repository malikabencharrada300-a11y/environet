<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

// =====================================================
// DATABASE CONFIGURATION
// =====================================================

$host = "aws-1-eu-west-2.pooler.supabase.com";
$dbname = "postgres";
$user = "postgres.gfwbtyjzpwvbwpxipdap";
$password = "ghadaa2004+12+25";
$port = "6543";

// =====================================================
// FUNCTION TO SEND JSON RESPONSE
// =====================================================

function sendJsonResponse($data, $success = true) {
    $response = array_merge(["success" => $success], $data);
    echo json_encode($response);
    exit;
}

function sendErrorResponse($message) {
    echo json_encode([
        "success" => false,
        "device_status" => "OFFLINE",
        "error" => $message,
        "temperature" => 23.5,
        "humidity" => 55,
        "signal" => 85,
        "ai_score" => 85,
        "health" => "Optimal",
        "temp_chart" => [22, 23, 24, 23.5, 22.5, 23, 24.5, 25, 24, 23],
        "humidity_chart" => [55, 56, 54, 55, 53, 54, 55, 56, 55, 54],
        "signal_chart" => [85, 82, 88, 85, 83, 86, 84, 87, 85, 86],
        "alert_chart" => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
    ]);
    exit;
}

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
    sendErrorResponse("Database connection failed: " . $e->getMessage());
}

// =====================================================
// GET PARAMETERS
// =====================================================

$range = $_GET["range"] ?? "Day";
$alert_range = $_GET["alert_range"] ?? "Day";
$generate_report = isset($_GET["generate_report"]) && $_GET["generate_report"] == "true";

// Convert range to SQL interval and limit
function getIntervalAndLimit($range) {
    switch ($range) {
        case "Hour":
            return ["interval" => "1 hour", "limit" => 60];
        case "Day":
            return ["interval" => "24 hours", "limit" => 144];
        case "Week":
            return ["interval" => "7 days", "limit" => 168];
        case "Month":
            return ["interval" => "30 days", "limit" => 720];
        default:
            return ["interval" => "24 hours", "limit" => 144];
    }
}

$sensorConfig = getIntervalAndLimit($range);
$alertConfig = getIntervalAndLimit($alert_range);

$interval = $sensorConfig["interval"];
$sensorLimit = $sensorConfig["limit"];
$alertInterval = $alertConfig["interval"];
$alertLimit = $alertConfig["limit"];

// =====================================================
// GET LATEST SENSOR DATA
// =====================================================

try {
    $stmtSensor = $pdo->prepare("
        SELECT temperature, humidity, timestamp
        FROM sensor_data
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtSensor->execute();
    $sensor = $stmtSensor->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sensor = null;
}

// =====================================================
// GET LATEST NETWORK DATA
// =====================================================

try {
    $stmtNetwork = $pdo->prepare("
        SELECT signal_strength, bandwidth, ping
        FROM esp32_cam_data
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtNetwork->execute();
    $network = $stmtNetwork->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $network = null;
}

// =====================================================
// DEFAULT VALUES IF NO DATA
// =====================================================

$temperature = floatval($sensor["temperature"] ?? 23.5);
$humidity = floatval($sensor["humidity"] ?? 55.0);
$timestamp = $sensor["timestamp"] ?? date("Y-m-d H:i:s");
$rssi = intval($network["signal_strength"] ?? -65);
$bandwidth = floatval($network["bandwidth"] ?? 100);
$ping = intval($network["ping"] ?? 50);

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
} elseif ($temperature <= 15) {
    $temp_status = "Low";
} else {
    $temp_status = "Normal";
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

try {
    $stmtAvg = $pdo->prepare("
        SELECT AVG(temperature) as avg_temp
        FROM sensor_data
        WHERE timestamp >= NOW() - INTERVAL '1 hour'
    ");
    $stmtAvg->execute();
    $avgResult = $stmtAvg->fetch(PDO::FETCH_ASSOC);
    $avgTemp = floatval($avgResult["avg_temp"] ?? $temperature);
} catch (Exception $e) {
    $avgTemp = $temperature;
}

$has_anomaly = (abs($temperature - $avgTemp) > 8);
$anomalies = $has_anomaly ? "1" : "0";

// =====================================================
// INSIGHTS
// =====================================================

if ($temperature > 35) {
    $insights = "High temperature detected: " . round($temperature, 1) . "°C";
} elseif ($signal < 30) {
    $insights = "Weak network signal: " . $signal . "%";
} elseif ($humidity > 75) {
    $insights = "High humidity level: " . round($humidity, 1) . "%";
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

try {
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
} catch (Exception $e) {
    $alerts = ["temp_alerts" => 0, "signal_alerts" => 0, "connection_alerts" => 0, "today_alerts" => 0];
}

// =====================================================
// TEMPERATURE CHART
// =====================================================

try {
    $stmtTempChart = $pdo->prepare("
        SELECT temperature
        FROM sensor_data
        WHERE timestamp >= NOW() - INTERVAL '$interval'
        ORDER BY timestamp ASC
        LIMIT $sensorLimit
    ");
    $stmtTempChart->execute();
    $tempRows = $stmtTempChart->fetchAll(PDO::FETCH_ASSOC);
    
    $temp_chart = [];
    foreach ($tempRows as $row) {
        $temp_chart[] = floatval($row["temperature"]);
    }
} catch (Exception $e) {
    $temp_chart = [];
}

if (empty($temp_chart)) {
    for ($i = 0; $i < min(24, $sensorLimit); $i++) {
        $temp_chart[] = 20 + rand(0, 150) / 10;
    }
}

// =====================================================
// HUMIDITY CHART
// =====================================================

try {
    $stmtHumidityChart = $pdo->prepare("
        SELECT humidity
        FROM sensor_data
        WHERE timestamp >= NOW() - INTERVAL '$interval'
        ORDER BY timestamp ASC
        LIMIT $sensorLimit
    ");
    $stmtHumidityChart->execute();
    $humidityRows = $stmtHumidityChart->fetchAll(PDO::FETCH_ASSOC);
    
    $humidity_chart = [];
    foreach ($humidityRows as $row) {
        $humidity_chart[] = floatval($row["humidity"]);
    }
} catch (Exception $e) {
    $humidity_chart = [];
}

if (empty($humidity_chart)) {
    for ($i = 0; $i < min(24, $sensorLimit); $i++) {
        $humidity_chart[] = 45 + rand(0, 100) / 2;
    }
}

// =====================================================
// SIGNAL CHART
// =====================================================

try {
    $stmtSignalChart = $pdo->prepare("
        SELECT signal_strength
        FROM esp32_cam_data
        WHERE timestamp >= NOW() - INTERVAL '$interval'
        ORDER BY timestamp ASC
        LIMIT $sensorLimit
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
} catch (Exception $e) {
    $signal_chart = [];
}

if (empty($signal_chart)) {
    for ($i = 0; $i < min(24, $sensorLimit); $i++) {
        $signal_chart[] = 60 + rand(0, 80);
    }
}

// =====================================================
// ALERT CHART
// =====================================================

try {
    $stmtAlertChart = $pdo->prepare("
        SELECT 
            CASE 
                WHEN severity IS NULL THEN 1
                ELSE severity
            END as severity
        FROM alerts
        WHERE created_at >= NOW() - INTERVAL '$alertInterval'
        ORDER BY created_at ASC
        LIMIT $alertLimit
    ");
    $stmtAlertChart->execute();
    $alertRows = $stmtAlertChart->fetchAll(PDO::FETCH_ASSOC);
    
    $alert_chart = [];
    foreach ($alertRows as $row) {
        $severity = intval($row["severity"] ?? 1);
        $alert_chart[] = $severity;
    }
} catch (Exception $e) {
    $alert_chart = [];
}

if (empty($alert_chart)) {
    $sampleSize = min(20, $alertLimit);
    for ($i = 0; $i < $sampleSize; $i++) {
        $rand = rand(1, 100);
        if ($rand <= 5) {
            $alert_chart[] = 2;
        } elseif ($rand <= 20) {
            $alert_chart[] = 1;
        } else {
            $alert_chart[] = 0;
        }
    }
}

// =====================================================
// HISTORY STATS
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
// TEMP TREND
// =====================================================

$temp_trend = 0;
if (count($temp_chart) >= 10) {
    $recent = array_slice($temp_chart, -5);
    $older = array_slice($temp_chart, -10, 5);
    if (count($recent) > 0 && count($older) > 0) {
        $temp_trend = round(array_sum($recent) / count($recent) - array_sum($older) / count($older), 1);
    }
}
$temp_trend_str = ($temp_trend >= 0 ? "+" : "") . $temp_trend . "°C";

// =====================================================
// SIGNAL TREND
// =====================================================

$signal_trend = 0;
if (count($signal_chart) >= 10) {
    $recent = array_slice($signal_chart, -5);
    $older = array_slice($signal_chart, -10, 5);
    if (count($recent) > 0 && count($older) > 0) {
        $signal_trend = round(array_sum($recent) / count($recent) - array_sum($older) / count($older), 1);
    }
}
$signal_trend_str = ($signal_trend >= 0 ? "+" : "") . $signal_trend . "%";

// =====================================================
// UPTIME
// =====================================================

try {
    $stmtUptime = $pdo->prepare("
        SELECT EXTRACT(EPOCH FROM (NOW() - MIN(timestamp))) as uptime_seconds
        FROM sensor_data
    ");
    $stmtUptime->execute();
    $uptimeResult = $stmtUptime->fetch(PDO::FETCH_ASSOC);
    $uptimeSeconds = intval($uptimeResult["uptime_seconds"] ?? 3600);
} catch (Exception $e) {
    $uptimeSeconds = 3600;
}

$uptimeHours = floor($uptimeSeconds / 3600);
$uptimeDays = floor($uptimeHours / 24);
$uptimeRemainingHours = $uptimeHours % 24;
$uptimeMinutes = floor(($uptimeSeconds % 3600) / 60);

if ($uptimeDays > 0) {
    $uptime = $uptimeDays . "d " . $uptimeRemainingHours . "h";
} else {
    $uptime = $uptimeHours . "h " . $uptimeMinutes . "m";
}

// =====================================================
// LAST ACTIVITY
// =====================================================

$last_activity = date("H:i:s", strtotime($timestamp));

// =====================================================
// GENERATE REPORT FUNCTION
// =====================================================

function generateReport($pdo, $range, $alert_range, $interval) {
    try {
        $stmtHistory = $pdo->prepare("
            SELECT 
                MIN(temperature) as min_temp,
                MAX(temperature) as max_temp,
                AVG(temperature) as avg_temp,
                MIN(humidity) as min_hum,
                MAX(humidity) as max_hum,
                AVG(humidity) as avg_hum
            FROM sensor_data
            WHERE timestamp >= NOW() - INTERVAL '$interval'
        ");
        $stmtHistory->execute();
        $history = $stmtHistory->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $history = ["min_temp" => 0, "max_temp" => 0, "avg_temp" => 0, "min_hum" => 0, "max_hum" => 0, "avg_hum" => 0];
    }
    
    try {
        $stmtAlertSummary = $pdo->prepare("
            SELECT 
                COUNT(*) as total_alerts,
                COUNT(*) FILTER (WHERE type='temperature') as temp_alerts,
                COUNT(*) FILTER (WHERE type='signal') as signal_alerts,
                COUNT(*) FILTER (WHERE type='connection') as connection_alerts
            FROM alerts
            WHERE created_at >= NOW() - INTERVAL '$interval'
        ");
        $stmtAlertSummary->execute();
        $alertSummary = $stmtAlertSummary->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $alertSummary = ["total_alerts" => 0, "temp_alerts" => 0, "signal_alerts" => 0, "connection_alerts" => 0];
    }
    
    try {
        $stmtLatest = $pdo->prepare("
            SELECT temperature, humidity
            FROM sensor_data
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmtLatest->execute();
        $latest = $stmtLatest->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $latest = ["temperature" => 0, "humidity" => 0];
    }
    
    try {
        $stmtSignal = $pdo->prepare("
            SELECT signal_strength
            FROM esp32_cam_data
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmtSignal->execute();
        $signalRow = $stmtSignal->fetch(PDO::FETCH_ASSOC);
        $rssi = intval($signalRow["signal_strength"] ?? -65);
    } catch (Exception $e) {
        $rssi = -65;
    }
    
    if ($rssi <= -100) {
        $signal = 0;
    } elseif ($rssi >= -50) {
        $signal = 100;
    } else {
        $signal = round(2 * ($rssi + 100));
    }
    
    return [
        "generated_at" => date("Y-m-d H:i:s"),
        "period" => $range,
        "current_temperature" => floatval($latest["temperature"] ?? 0),
        "current_humidity" => floatval($latest["humidity"] ?? 0),
        "current_signal" => $signal,
        "min_temperature" => floatval($history["min_temp"] ?? 0),
        "max_temperature" => floatval($history["max_temp"] ?? 0),
        "avg_temperature" => round(floatval($history["avg_temp"] ?? 0), 1),
        "min_humidity" => floatval($history["min_hum"] ?? 0),
        "max_humidity" => floatval($history["max_hum"] ?? 0),
        "avg_humidity" => round(floatval($history["avg_hum"] ?? 0), 1),
        "total_alerts" => intval($alertSummary["total_alerts"] ?? 0),
        "temperature_alerts" => intval($alertSummary["temp_alerts"] ?? 0),
        "signal_alerts" => intval($alertSummary["signal_alerts"] ?? 0),
        "connection_alerts" => intval($alertSummary["connection_alerts"] ?? 0)
    ];
}

// =====================================================
// CHECK IF REPORT GENERATION IS REQUESTED
// =====================================================

if ($generate_report) {
    $report = generateReport($pdo, $range, $alert_range, $interval);
    sendJsonResponse($report, true);
}

// =====================================================
// RESPONSE FOR NORMAL API CALL
// =====================================================

$response = [
    "device_status" => $device_status,
    "dht_state" => $dht_state,
    "last_activity" => $last_activity,
    "uptime" => $uptime,
    "temperature" => round($temperature, 1),
    "temp_status" => $temp_status,
    "temp_trend" => $temp_trend_str,
    "humidity" => round($humidity, 1),
    "humidity_status" => $humidity_status,
    "signal" => $signal,
    "signal_status" => $signal_status,
    "signal_trend" => $signal_trend_str,
    "ai_score" => $ai_score,
    "health" => $health,
    "has_anomaly" => $has_anomaly,
    "anomalies" => $anomalies,
    "data_points" => $data_points,
    "insights" => $insights,
    "predictions" => $predictions,
    "recommendations" => $recommendations,
    "history_min" => $history_min,
    "history_max" => $history_max,
    "history_avg" => $history_avg,
    "temp_alerts" => intval($alerts["temp_alerts"] ?? 0),
    "signal_alerts" => intval($alerts["signal_alerts"] ?? 0),
    "connection_alerts" => intval($alerts["connection_alerts"] ?? 0),
    "today_alerts" => intval($alerts["today_alerts"] ?? 0),
    "temp_chart" => $temp_chart,
    "humidity_chart" => $humidity_chart,
    "signal_chart" => $signal_chart,
    "alert_chart" => $alert_chart
];

sendJsonResponse($response, true);

?>
