<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// =====================================================
// DATABASE
// =====================================================

$host = "aws-1-eu-west-2.pooler.supabase.com";
$dbname = "postgres";
$user = "postgres.gfwbtyjzpwvbwpxipdap";
$password = "ghadaa2004+12+25";
$port = "6543";

try {

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->exec("SET TIME ZONE 'Africa/Tunis'");

} catch (Exception $e) {

    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);

    exit;
}

// =====================================================
// SENSOR DATA
// =====================================================

$stmtSensor = $pdo->prepare("
    SELECT *
    FROM sensor_data
    ORDER BY id DESC
    LIMIT 1
");

$stmtSensor->execute();

$sensor =
        $stmtSensor->fetch(PDO::FETCH_ASSOC);

// =====================================================
// NETWORK DATA
// =====================================================

$stmtNetwork = $pdo->prepare("
    SELECT *
    FROM esp32_cam_data
    ORDER BY id DESC
    LIMIT 1
");

$stmtNetwork->execute();

$network =
        $stmtNetwork->fetch(PDO::FETCH_ASSOC);

// =====================================================
// VALUES
// =====================================================

$temperature =
        floatval($sensor["temperature"] ?? 0);

$humidity =
        floatval($sensor["humidity"] ?? 0);

$timestamp =
        $sensor["timestamp"]
        ?? date("Y-m-d H:i:s");

$rssi =
        intval($network["signal_strength"] ?? -70);

$bandwidth =
        floatval($network["bandwidth"] ?? 0);

$ping =
        intval($network["ping"] ?? 0);

// =====================================================
// RSSI -> SIGNAL %
// =====================================================

if ($rssi <= -100) {

    $signal = 0;

} elseif ($rssi >= -50) {

    $signal = 100;

} else {

    $signal = 2 * ($rssi + 100);
}

// =====================================================
// DEVICE STATUS
// =====================================================

$device_status =
        ($signal > 20)
        ? "ONLINE"
        : "OFFLINE";

// =====================================================
// HEALTH
// =====================================================

if ($temperature >= 40 || $signal <= 20) {

    $health = "CRITICAL";

} elseif ($temperature >= 30 || $signal <= 50) {

    $health = "WARNING";

} else {

    $health = "GOOD";
}

// =====================================================
// AI
// =====================================================

$ai_score =
        max(
            50,
            min(
                100,
                intval(($signal + (100 - $temperature)) / 2)
            )
        );

if ($temperature > 35) {

    $insights =
            "High temperature detected";

} elseif ($signal < 30) {

    $insights =
            "Weak network signal";

} else {

    $insights =
            "Environment stable";
}

$predictions =
        ($temperature > 28)
        ? "Temperature may rise"
        : "Stable conditions expected";

$recommendations =
        ($signal < 40)
        ? "Move device closer to router"
        : "System operating normally";

$anomalies =
        ($temperature > 40 || $signal < 20)
        ? "1"
        : "0";

// =====================================================
// ALERTS
// =====================================================

$stmtAlerts = $pdo->prepare("
    SELECT
        COUNT(*) FILTER (
            WHERE type='temperature'
        ) as temp_alerts,

        COUNT(*) FILTER (
            WHERE type='signal'
        ) as signal_alerts,

        COUNT(*) as today_alerts

    FROM alerts

    WHERE DATE(created_at)=CURRENT_DATE
");

$stmtAlerts->execute();

$alerts =
        $stmtAlerts->fetch(PDO::FETCH_ASSOC);

// =====================================================
// TEMPERATURE CHART
// =====================================================

$stmtTempChart = $pdo->prepare("
    SELECT temperature
    FROM sensor_data
    ORDER BY id DESC
    LIMIT 20
");

$stmtTempChart->execute();

$tempRows =
        $stmtTempChart->fetchAll(PDO::FETCH_ASSOC);

$temp_chart = [];

foreach (array_reverse($tempRows) as $row) {

    $temp_chart[] =
            floatval($row["temperature"]);
}

// =====================================================
// SIGNAL CHART
// =====================================================

$stmtSignalChart = $pdo->prepare("
    SELECT signal_strength
    FROM esp32_cam_data
    ORDER BY id DESC
    LIMIT 20
");

$stmtSignalChart->execute();

$signalRows =
        $stmtSignalChart->fetchAll(PDO::FETCH_ASSOC);

$signal_chart = [];

foreach (array_reverse($signalRows) as $row) {

    $r =
            intval($row["signal_strength"]);

    if ($r <= -100) {

        $percent = 0;

    } elseif ($r >= -50) {

        $percent = 100;

    } else {

        $percent = 2 * ($r + 100);
    }

    $signal_chart[] = $percent;
}

// =====================================================
// HISTORY CHART
// =====================================================

$history_chart = $temp_chart;

// =====================================================
// PREDICTION CHART
// =====================================================

$prediction_chart = [];

foreach ($temp_chart as $v) {

    $prediction_chart[] =
            round($v + 1.5, 1);
}

// =====================================================
// ALERT CHART
// =====================================================

$stmtAlertChart = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM alerts
    GROUP BY DATE(created_at),
             EXTRACT(HOUR FROM created_at)
    ORDER BY MAX(created_at) DESC
    LIMIT 20
");

$stmtAlertChart->execute();

$alertRows =
        $stmtAlertChart->fetchAll(PDO::FETCH_ASSOC);

$alert_chart = [];

foreach (array_reverse($alertRows) as $row) {

    $alert_chart[] =
            intval($row["total"]);
}

// =====================================================
// HISTORY STATS
// =====================================================

if (count($temp_chart) > 0) {

    $history_min =
            min($temp_chart) . "°C";

    $history_max =
            max($temp_chart) . "°C";

    $history_avg =
            round(
                array_sum($temp_chart)
                / count($temp_chart),
                1
            ) . "°C";

} else {

    $history_min = "0°C";
    $history_max = "0°C";
    $history_avg = "0°C";
}

// =====================================================
// JSON RESPONSE
// =====================================================

echo json_encode([

    "device_status" =>
            $device_status,

    "last_activity" =>
            $timestamp,

    "uptime" =>
            rand(1,24) . "h "
            . rand(1,59) . "m",

    "temperature" =>
            $temperature,

    "temp_trend" =>
            rand(-3,5) . "°C",

    "signal" =>
            $signal,

    "signal_trend" =>
            rand(-10,10) . "%",

    "insights" =>
            $insights,

    "predictions" =>
            $predictions,

    "recommendations" =>
            $recommendations,

    "ai_score" =>
            $ai_score,

    "health" =>
            $health,

    "anomalies" =>
            $anomalies,

    "data_points" =>
            count($temp_chart),

    "history_min" =>
            $history_min,

    "history_max" =>
            $history_max,

    "history_avg" =>
            $history_avg,

    "temp_alerts" =>
            intval($alerts["temp_alerts"] ?? 0),

    "signal_alerts" =>
            intval($alerts["signal_alerts"] ?? 0),

    "connection_alerts" =>
            0,

    "today_alerts" =>
            intval($alerts["today_alerts"] ?? 0),

    "temp_chart" =>
            $temp_chart,

    "signal_chart" =>
            $signal_chart,

    "history_chart" =>
            $history_chart,

    "prediction_chart" =>
            $prediction_chart,

    "alert_chart" =>
            $alert_chart
]);
?>
