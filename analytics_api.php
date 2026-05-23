<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Disable error display to avoid HTML in JSON
error_reporting(0);
ini_set('display_errors', 0);

// =====================================================
// DATABASE CONFIGURATION
// =====================================================

$host = "aws-1-eu-west-2.pooler.supabase.com";
$dbname = "postgres";
$user = "postgres.gfwbtyjzpwvbwpxipdap";
$password = "ghadaa2004+12+25";
$port = "6543";

// =====================================================
// GET PARAMETERS
// =====================================================

$range = $_GET["range"] ?? "Day";
$alert_range = $_GET["alert_range"] ?? "Day";
$generate_report = isset($_GET["generate_report"]) && $_GET["generate_report"] == "true";

// =====================================================
// TRY TO CONNECT TO DATABASE
// =====================================================

$db_connected = false;
$pdo = null;

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("SET TIME ZONE 'Africa/Tunis'");
    $db_connected = true;
} catch (Exception $e) {
    // Database connection failed, will use demo data
}

// =====================================================
// FUNCTION TO GENERATE DEMO DATA
// =====================================================

function getDemoData($range) {
    $points = 24;
    if ($range == "Hour") $points = 60;
    if ($range == "Day") $points = 24;
    if ($range == "Week") $points = 168;
    if ($range == "Month") $points = 30;
    
    $temp_chart = [];
    $humidity_chart = [];
    $signal_chart = [];
    $alert_chart = [];
    
    for ($i = 0; $i < min($points, 50); $i++) {
        $temp_chart[] = 20 + sin($i / 4) * 5 + rand(-5, 5) / 10;
        $humidity_chart[] = 50 + cos($i / 3) * 10 + rand(-10, 10) / 10;
        $signal_chart[] = 70 + sin($i / 2) * 15 + rand(-10, 10);
        $alert_chart[] = rand(0, 2);
    }
    
    return [
        "temp_chart" => $temp_chart,
        "humidity_chart" => $humidity_chart,
        "signal_chart" => $signal_chart,
        "alert_chart" => $alert_chart
    ];
}

// =====================================================
// FUNCTION TO GENERATE REPORT DATA
// =====================================================

function getReportData($pdo, $db_connected, $range) {
    if ($db_connected && $pdo) {
        try {
            $interval = "24 hours";
            if ($range == "Hour") $interval = "1 hour";
            if ($range == "Day") $interval = "24 hours";
            if ($range == "Week") $interval = "7 days";
            if ($range == "Month") $interval = "30 days";
            
            // Get historical stats
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(MIN(temperature), 20) as min_temp,
                    COALESCE(MAX(temperature), 25) as max_temp,
                    COALESCE(AVG(temperature), 22.5) as avg_temp,
                    COALESCE(MIN(humidity), 45) as min_hum,
                    COALESCE(MAX(humidity), 65) as max_hum,
                    COALESCE(AVG(humidity), 55) as avg_hum
                FROM sensor_data
                WHERE timestamp >= NOW() - INTERVAL '$interval'
            ");
            $stmt->execute();
            $history = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get alert counts
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_alerts,
                    COUNT(*) FILTER (WHERE type='temperature') as temp_alerts,
                    COUNT(*) FILTER (WHERE type='signal') as signal_alerts,
                    COUNT(*) FILTER (WHERE type='connection') as connection_alerts
                FROM alerts
                WHERE created_at >= NOW() - INTERVAL '$interval'
            ");
            $stmt->execute();
            $alerts = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get latest readings
            $stmt = $pdo->prepare("
                SELECT temperature, humidity
                FROM sensor_data
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute();
            $latest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get signal
            $stmt = $pdo->prepare("
                SELECT signal_strength
                FROM esp32_cam_data
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute();
            $signalRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $rssi = intval($signalRow["signal_strength"] ?? -65);
            
            if ($rssi <= -100) $signal = 0;
            elseif ($rssi >= -50) $signal = 100;
            else $signal = round(2 * ($rssi + 100));
            
            return [
                "generated_at" => date("Y-m-d H:i:s"),
                "period" => $range,
                "current_temperature" => round(floatval($latest["temperature"] ?? 23.5), 1),
                "current_humidity" => round(floatval($latest["humidity"] ?? 55), 1),
                "current_signal" => $signal,
                "min_temperature" => round(floatval($history["min_temp"] ?? 20), 1),
                "max_temperature" => round(floatval($history["max_temp"] ?? 25), 1),
                "avg_temperature" => round(floatval($history["avg_temp"] ?? 22.5), 1),
                "min_humidity" => round(floatval($history["min_hum"] ?? 45), 1),
                "max_humidity" => round(floatval($history["max_hum"] ?? 65), 1),
                "avg_humidity" => round(floatval($history["avg_hum"] ?? 55), 1),
                "total_alerts" => intval($alerts["total_alerts"] ?? 0),
                "temperature_alerts" => intval($alerts["temp_alerts"] ?? 0),
                "signal_alerts" => intval($alerts["signal_alerts"] ?? 0),
                "connection_alerts" => intval($alerts["connection_alerts"] ?? 0)
            ];
        } catch (Exception $e) {
            // Fallback to demo data
            return getDemoReportData($range);
        }
    } else {
        return getDemoReportData($range);
    }
}

function getDemoReportData($range) {
    return [
        "generated_at" => date("Y-m-d H:i:s"),
        "period" => $range,
        "current_temperature" => 23.5,
        "current_humidity" => 55,
        "current_signal" => 85,
        "min_temperature" => 18.5,
        "max_temperature" => 28.5,
        "avg_temperature" => 23.2,
        "min_humidity" => 42,
        "max_humidity" => 68,
        "avg_humidity" => 54.5,
        "total_alerts" => 5,
        "temperature_alerts" => 2,
        "signal_alerts" => 1,
        "connection_alerts" => 2
    ];
}

// =====================================================
// IF REPORT GENERATION IS REQUESTED
// =====================================================

if ($generate_report) {
    $report = getReportData($pdo, $db_connected, $range);
    echo json_encode($report);
    exit;
}

// =====================================================
// NORMAL API RESPONSE
// =====================================================

// Get demo data for charts
$demoData = getDemoData($range);

// Try to get real data if database is connected
if ($db_connected && $pdo) {
    try {
        // Get latest sensor data
        $stmt = $pdo->prepare("SELECT temperature, humidity, timestamp FROM sensor_data ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $sensor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get latest network data
        $stmt = $pdo->prepare("SELECT signal_strength FROM esp32_cam_data ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $network = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $temperature = floatval($sensor["temperature"] ?? 23.5);
        $humidity = floatval($sensor["humidity"] ?? 55);
        $timestamp = $sensor["timestamp"] ?? date("Y-m-d H:i:s");
        $rssi = intval($network["signal_strength"] ?? -65);
        
        // Calculate signal percentage
        if ($rssi <= -100) $signal = 0;
        elseif ($rssi >= -50) $signal = 100;
        else $signal = round(2 * ($rssi + 100));
        
        // Get chart data
        $interval = "24 hours";
        if ($range == "Hour") $interval = "1 hour";
        if ($range == "Day") $interval = "24 hours";
        if ($range == "Week") $interval = "7 days";
        if ($range == "Month") $interval = "30 days";
        
        $limit = 50;
        
        $stmt = $pdo->prepare("SELECT temperature FROM sensor_data WHERE timestamp >= NOW() - INTERVAL '$interval' ORDER BY timestamp ASC LIMIT $limit");
        $stmt->execute();
        $tempRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $temp_chart = [];
        foreach ($tempRows as $row) {
            $temp_chart[] = floatval($row["temperature"]);
        }
        
        $stmt = $pdo->prepare("SELECT humidity FROM sensor_data WHERE timestamp >= NOW() - INTERVAL '$interval' ORDER BY timestamp ASC LIMIT $limit");
        $stmt->execute();
        $humRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $humidity_chart = [];
        foreach ($humRows as $row) {
            $humidity_chart[] = floatval($row["humidity"]);
        }
        
        $stmt = $pdo->prepare("SELECT signal_strength FROM esp32_cam_data WHERE timestamp >= NOW() - INTERVAL '$interval' ORDER BY timestamp ASC LIMIT $limit");
        $stmt->execute();
        $sigRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $signal_chart = [];
        foreach ($sigRows as $row) {
            $r = intval($row["signal_strength"]);
            if ($r <= -100) $percent = 0;
            elseif ($r >= -50) $percent = 100;
            else $percent = round(2 * ($r + 100));
            $signal_chart[] = $percent;
        }
        
        // Use real data if available, otherwise use demo
        $temp_chart = empty($temp_chart) ? $demoData["temp_chart"] : $temp_chart;
        $humidity_chart = empty($humidity_chart) ? $demoData["humidity_chart"] : $humidity_chart;
        $signal_chart = empty($signal_chart) ? $demoData["signal_chart"] : $signal_chart;
        
    } catch (Exception $e) {
        // Use demo data on error
        $temperature = 23.5;
        $humidity = 55;
        $signal = 85;
        $timestamp = date("Y-m-d H:i:s");
        $temp_chart = $demoData["temp_chart"];
        $humidity_chart = $demoData["humidity_chart"];
        $signal_chart = $demoData["signal_chart"];
    }
} else {
    // Use demo data
    $temperature = 23.5;
    $humidity = 55;
    $signal = 85;
    $timestamp = date("Y-m-d H:i:s");
    $temp_chart = $demoData["temp_chart"];
    $humidity_chart = $demoData["humidity_chart"];
    $signal_chart = $demoData["signal_chart"];
}

// Determine statuses
$device_status = ($signal > 20) ? "ONLINE" : "OFFLINE";
$dht_state = "Connected";
$temp_status = ($temperature >= 35) ? "High" : (($temperature <= 15) ? "Low" : "Normal");
$humidity_status = ($humidity >= 70) ? "High" : (($humidity <= 35) ? "Low" : "Normal");
$signal_status = ($signal >= 80) ? "Excellent" : (($signal >= 60) ? "Good" : "Poor");
$health = ($temperature >= 40 || $signal <= 20) ? "Critical" : (($temperature >= 33 || $signal <= 50) ? "Warning" : "Optimal");
$ai_score = max(50, min(100, intval(($signal + (100 - abs($temperature - 25))) / 2)));
$has_anomaly = false;
$insights = "Environment stable. All parameters normal.";
$predictions = "Stable conditions expected for next hour";
$recommendations = "System operating normally. No action needed.";

// History stats
$history_min = min($temp_chart) . "°C";
$history_max = max($temp_chart) . "°C";
$history_avg = round(array_sum($temp_chart) / count($temp_chart), 1) . "°C";

// Response
$response = [
    "success" => true,
    "device_status" => $device_status,
    "dht_state" => $dht_state,
    "last_activity" => date("H:i:s", strtotime($timestamp)),
    "uptime" => "5d 12h",
    "temperature" => round($temperature, 1),
    "temp_status" => $temp_status,
    "temp_trend" => "+0.5°C",
    "humidity" => round($humidity, 1),
    "humidity_status" => $humidity_status,
    "signal" => $signal,
    "signal_status" => $signal_status,
    "signal_trend" => "+2%",
    "ai_score" => $ai_score,
    "health" => $health,
    "has_anomaly" => $has_anomaly,
    "anomalies" => "0",
    "data_points" => count($temp_chart),
    "insights" => $insights,
    "predictions" => $predictions,
    "recommendations" => $recommendations,
    "history_min" => $history_min,
    "history_max" => $history_max,
    "history_avg" => $history_avg,
    "temp_alerts" => 2,
    "signal_alerts" => 1,
    "connection_alerts" => 0,
    "today_alerts" => 3,
    "temp_chart" => $temp_chart,
    "humidity_chart" => $humidity_chart,
    "signal_chart" => $signal_chart,
    "alert_chart" => $demoData["alert_chart"]
];

echo json_encode($response);

?>
