<?php

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

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
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

        response("success", "Login success", $user);

    } else {

        response("error", "Wrong password");
    }
}

//
// ================= REGISTER =================
//
elseif ($action === "register") {

    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $check->execute([$email]);

    if ($check->rowCount() > 0) {
        response("error", "Email already exists");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users(name,email,password)
        VALUES(?,?,?)
    ");

    if ($stmt->execute([$name,$email,$hash])) {

        response("success", "User registered");

    } else {

        response("error", "Register failed");
    }
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

?>
