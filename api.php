<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer.php';
require 'SMTP.php';
require 'Exception.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

$host = "aws-1-eu-west-2.pooler.supabase.com"; 
$dbname = "postgres";
$user = "postgres.gfwbtyjzpwvbwpxipdap";
$password = "ghadaa2004+12+25";
$port = "6543";

function response($status, $message, $data = null)
{
    echo json_encode([
        "status" => $status,
        "message" => $message,
        "data" => $data
    ]);
    exit;
}

try {

  $pdo = new PDO(
    "pgsql:host=$host;port=$port;dbname=$dbname",
    $user,
    $password
);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   
    $pdo->exec("SET TIME ZONE 'Africa/Tunis'");

} catch (Exception $e) {

    response("error", $e->getMessage());
}

$action = $_GET['action'] ?? '';

if ($action == '') {
    response("error", "No action");
}

//
// ================= LOGIN =================
//
if ($action === "login") {

    $input = json_decode(file_get_contents("php://input"), true);

    $email = trim(strtolower($input['email'] ?? ''));
    $password = trim($input['password'] ?? '');

    if (empty($email) || empty($password)) {
        response("error", "Email and password required");
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1");
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        response("error", "User not found");
    }

    if (
        $password === $user['password'] ||
        password_verify($password, $user['password'])
    ) {

        unset($user['password']);

        echo json_encode([
            "status" => "success",
            "message" => "Login success",
            "user_id" => $user['id'],
            "name" => $user['name'],
            "email" => $user['email']
        ]);
        exit;

    } else {
        response("error", "Wrong password");
    }
}
//
// ================= REGISTER =================
//
elseif ($action === "register") {

    $input = json_decode(file_get_contents("php://input"), true);

    $name = trim($input['name'] ?? '');
    $email = trim(strtolower($input['email'] ?? ''));
    $password = trim($input['password'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        response("error", "All fields required");
    }

    $check = $pdo->prepare("SELECT id FROM users WHERE LOWER(email)=LOWER(?)");
    $check->execute([$email]);

    if ($check->fetch()) {
        response("error", "Email already exists");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users(name,email,password)
        VALUES(?,?,?)
    ");

    if ($stmt->execute([$name, $email, $hash])) {
        response("success", "User registered");
    } else {
        response("error", "Register failed");
    }
}

//
//==========forgot password==========//
//
//
//========== forgot password ==========
//
elseif ($action === "forgot_password") {

    $input = json_decode(file_get_contents("php://input"), true);
    $email = trim($input["email"] ?? "");

    if ($email == "") {
        response("error", "Email required");
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);

    if ($stmt->rowCount() == 0) {
        response("error", "Email not found");
    }

    $code = rand(1000, 9999);
    $expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));

    $pdo->prepare("DELETE FROM password_resets WHERE email=?")
        ->execute([$email]);

    $stmt = $pdo->prepare("
        INSERT INTO password_resets(email, token, expires_at, created_at)
        VALUES (?, ?, ?, NOW())
    ");

    $stmt->execute([$email, $code, $expires]);

    // Temporary: return code directly instead of email
    response("success", "Code sent", [
        "code" => $code
    ]);
}
//
//=========vrify reset code==========//
//
elseif ($action === "verify_reset_code") {

    $input = json_decode(file_get_contents("php://input"), true);

    $email = trim($input["email"] ?? "");
    $code = trim($input["code"] ?? "");
    $new_password = trim($input["new_password"] ?? "");

    $stmt = $pdo->prepare("
    SELECT id
    FROM password_resets
    WHERE email=? AND token=? AND expires_at > NOW()
    LIMIT 1
    ");

    $stmt->execute([$email, $code]);

    if ($stmt->rowCount() == 0) {
        response("error", "Wrong code");
    }

    $hash = password_hash($new_password, PASSWORD_DEFAULT);

    $pdo->prepare("UPDATE users SET password=? WHERE email=?")
        ->execute([$hash, $email]);

    $pdo->prepare("DELETE FROM password_resets WHERE email=?")
        ->execute([$email]);

    response("success", "Password changed");
}
//
// ================= SENSOR =================
//
elseif ($action === "sensor") {

    $stmt = $pdo->prepare("
        SELECT
            temperature,
            humidity,
            timestamp
        FROM sensor_data
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute();

    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {

        response("success", "Sensor loaded", $data);

    } else {

        response("error", "No sensor data");
    }
}

//
// ================= NETWORK =================
//
elseif ($action === "network") {

    $stmt = $pdo->prepare("
        SELECT
            ssid,
            ip_address,
            mac_address,
            signal_strength,
            bandwidth,
            ping,
            timestamp
        FROM esp32_cam_data
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute();

    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {

        response("success", "Network data loaded", [

            "ssid" => $data["ssid"],

            "ip_address" => $data["ip_address"],

            "mac_address" => $data["mac_address"],

            // IMPORTANT
            "signal" => intval($data["signal_strength"]),

            "bandwidth" => floatval($data["bandwidth"]),

            "ping" => intval($data["ping"]),

            "timestamp" => $data["timestamp"]
        ]);

    } else {

        response("error", "No network data found");
    }
}

//
// ================= ALERTS =================
//
elseif ($action === "alerts") {

    $stmt = $pdo->prepare("
        SELECT
            message,
            severity,
            location,
            created_at
        FROM alerts
        ORDER BY created_at DESC
        LIMIT 100
    ");

    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];

    foreach ($rows as $row) {

        $data[] = [
            "message" => $row["message"],
            "level" => $row["severity"],   // Android attend 'level'
            "location" => $row["location"],
            "created_at" => $row["created_at"]
        ];
    }

    response("success", "Alerts loaded", $data);
}

//
// ================= HISTORY =================
//
elseif ($action === "history") {

    $stmt = $pdo->prepare("
        SELECT
            s.temperature,
            s.humidity,
            s.timestamp,

            n.signal_strength,
            n.bandwidth,
            n.ping

        FROM sensor_data s

        LEFT JOIN esp32_cam_data n
        ON n.id = (
            SELECT id
            FROM esp32_cam_data
            ORDER BY id DESC
            LIMIT 1
        )

        ORDER BY s.id DESC
         LIMIT 100
    ");

    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];

    foreach ($rows as $row) {

        $rssi = isset($row["signal_strength"])
                ? intval($row["signal_strength"])
                : -50;

        // RSSI -> %
        if ($rssi <= -100) {

            $signal = 0;

        } elseif ($rssi >= -50) {

            $signal = 100;

        } else {

            $signal = 2 * ($rssi + 100);
        }

        $status = ($signal < 30)
                ? "Weak"
                : "Online";

        $data[] = [

            "created_at" =>
                    $row["timestamp"],

            "temperature" =>
                    floatval($row["temperature"]),

            "humidity" =>
                    floatval($row["humidity"]),

            "signal" =>
                    $signal,

            "bandwidth" =>
                    floatval($row["bandwidth"] ?? 0),

            "ping" =>
                    intval($row["ping"] ?? 0),

            "status" =>
                    $status
        ];
    }

    response(
        "success",
        "History loaded",
        $data
    );
}
//
//=========exported csv==========//
//
elseif ($action === "export_csv") {

    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=history.csv");

    $output = fopen("php://output", "w");

    fputcsv($output, [
        "Date",
        "Temperature",
        "Humidity",
        "Signal",
        "Bandwidth",
        "Ping",
        "Status"
    ]);

    $stmt = $pdo->prepare("
        SELECT
            s.temperature,
            s.humidity,
            s.timestamp,
            n.signal_strength,
            n.bandwidth,
            n.ping
        FROM sensor_data s
        LEFT JOIN esp32_cam_data n
        ON n.id = (
            SELECT id FROM esp32_cam_data
            ORDER BY id DESC
            LIMIT 1
        )
        ORDER BY s.id DESC
        LIMIT 100
    ");

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {

        $rssi = intval($row["signal_strength"] ?? -50);

        if ($rssi <= -100) {
            $signal = 0;
        } elseif ($rssi >= -50) {
            $signal = 100;
        } else {
            $signal = 2 * ($rssi + 100);
        }

        $status = ($signal < 30) ? "Weak" : "Online";

        fputcsv($output, [
            $row["timestamp"],
            $row["temperature"],
            $row["humidity"],
            $signal,
            $row["bandwidth"],
            $row["ping"],
            $status
        ]);
    }

    fclose($output);
    exit;
}    

//
// ================= ROOMS =================
//
elseif ($action === "rooms") {

    response("success", "Rooms count", [
        "count" => 1
    ]);
}

//
// ================= USER =================
//
elseif ($action === "user") {

    $user_id = $_GET['user_id'] ?? 1;

    $stmt = $pdo->prepare("
        SELECT id,name,email
        FROM users
        WHERE id=?
    ");

    $stmt->execute([$user_id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        response("success", "User data", $user);

    } else {

        response("error", "User not found");
    }
}

//
// ================= LAST UPDATE =================
//
elseif ($action === "last_update") {

    $stmt = $pdo->prepare("
        SELECT timestamp
        FROM sensor_data
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    response("success", "Last update", [
        "latest_update" => $row['timestamp'] ?? null
    ]);
}

//
// ================= INSERT SENSOR =================
//
elseif ($action === "insertSensor") {

    $temperature = $_GET['temperature'] ?? '';
    $humidity = $_GET['humidity'] ?? '';

    if ($temperature == '' || $humidity == '') {
        response("error", "Missing values");
    }

    $stmt = $pdo->prepare("
        INSERT INTO sensor_data(
            user_id,
            temperature,
            humidity,
            timestamp
        )
        VALUES(1,?,?,NOW())
    ");

    if ($stmt->execute([$temperature, $humidity])) {

        $temp = floatval($temperature);
        $hum  = floatval($humidity);

        $stmtAlert = $pdo->prepare("
            INSERT INTO alerts(
                type,
                message,
                severity,
                location,
                status,
                created_at
            )
            VALUES(?,?,?,?,?,NOW())
        ");

        // ================= TEMPERATURE =================
        if ($temp >= 35) {
            $sev = "critical";
        } elseif ($temp >= 25) {
            $sev = "warning";
        } else {
            $sev = "info";
        }

        $stmtAlert->execute([
            "temperature",
            "Temperature: " . $temp . "°C",
            $sev,
            "ESP32 Room",
            "active"
        ]);

        // ================= HUMIDITY =================
        if ($hum >= 80) {
            $sev = "critical";
        } elseif ($hum >= 65) {
            $sev = "warning";
        } else {
            $sev = "info";
        }

        $stmtAlert->execute([
            "humidity",
            "Humidity: " . $hum . "%",
            $sev,
            "ESP32 Room",
            "active"
        ]);

        // ================= SIGNAL =================
        $stmtNet = $pdo->prepare("
            SELECT signal_strength
            FROM esp32_cam_data
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmtNet->execute();

        $net = $stmtNet->fetch(PDO::FETCH_ASSOC);

        $rssi = intval($net["signal_strength"] ?? -50);

        if ($rssi <= -100) {
            $signal = 0;
        } elseif ($rssi >= -50) {
            $signal = 100;
        } else {
            $signal = 2 * ($rssi + 100);
        }

        if ($signal <= 20) {
            $sev = "critical";
        } elseif ($signal <= 50) {
            $sev = "warning";
        } else {
            $sev = "info";
        }

        $stmtAlert->execute([
            "signal",
            "Signal: " . $signal . "%",
            $sev,
            "ESP32 Room",
            "active"
        ]);

        response("success", "Inserted");

    } else {

        response("error", "Insert failed");
    }
}
// =====================================================
// NETWORK DATA
// =====================================================
elseif ($action === "network") {

    $stmt = $pdo->prepare("
        SELECT
            ssid,
            ip_address,
            mac_address,
            signal_strength,
            bandwidth,
            ping,
            timestamp
        FROM esp32_cam_data
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute();

    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {

        response("success", "Network data loaded", [
            "ssid" => $data["ssid"],
            "ip_address" => $data["ip_address"],
            "mac_address" => $data["mac_address"],

            // IMPORTANT
            "rssi" => intval($data["signal_strength"]),

            "bandwidth" => floatval($data["bandwidth"]),
            "ping" => intval($data["ping"]),
            "timestamp" => $data["timestamp"]
        ]);

    } else {

        response("error", "No network data found");
    }
}

//
// ================= INVALID =================
//
else {

    response("error", "Invalid action");
}

//
// ================= ANALYTICS =================
//
elseif ($action === "analytics") {

    // =====================================================
    // LAST SENSOR DATA
    // =====================================================

    $stmtSensor = $pdo->prepare("
        SELECT *
        FROM sensor_data
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmtSensor->execute();

    $sensor = $stmtSensor->fetch(PDO::FETCH_ASSOC);

    // =====================================================
    // LAST NETWORK DATA
    // =====================================================

    $stmtNetwork = $pdo->prepare("
        SELECT *
        FROM esp32_cam_data
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmtNetwork->execute();

    $network = $stmtNetwork->fetch(PDO::FETCH_ASSOC);

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
    // RSSI -> %
    // =====================================================

    if ($rssi <= -100) {

        $signal = 0;

    } elseif ($rssi >= -50) {

        $signal = 100;

    } else {

        $signal = 2 * ($rssi + 100);
    }

    // =====================================================
    // STATUS
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
    // AI SCORE
    // =====================================================

    $ai_score =
            max(
                50,
                min(
                    100,
                    intval(($signal + (100 - $temperature)) / 2)
                )
            );

    // =====================================================
    // INSIGHTS
    // =====================================================

    if ($temperature > 35) {

        $insights =
                "High temperature detected";

    } elseif ($signal < 30) {

        $insights =
                "Weak network signal detected";

    } else {

        $insights =
                "Environment stable";
    }

    // =====================================================
    // PREDICTIONS
    // =====================================================

    if ($temperature > 28) {

        $predictions =
                "Temperature may rise soon";

    } else {

        $predictions =
                "Stable conditions expected";
    }

    // =====================================================
    // RECOMMENDATIONS
    // =====================================================

    if ($signal < 40) {

        $recommendations =
                "Move device closer to router";

    } else {

        $recommendations =
                "System operating normally";
    }

    // =====================================================
    // ANOMALIES
    // =====================================================

    $anomalies =
            ($temperature > 40 || $signal < 20)
            ? "1"
            : "0";

    // =====================================================
    // ALERT COUNTS
    // =====================================================

    $stmtAlerts = $pdo->prepare("
        SELECT
            COUNT(*) FILTER (
                WHERE type='temperature'
            ) as temp_alerts,

            COUNT(*) FILTER (
                WHERE type='signal'
            ) as signal_alerts,

            COUNT(*) FILTER (
                WHERE type='connection'
            ) as connection_alerts,

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
    // RESPONSE JSON
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
                intval($alerts["connection_alerts"] ?? 0),

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

    exit;
}

?>
