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
//
// ================= ALERTS =================
//
elseif ($action === "alerts") {

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

    $alerts = [];

    foreach ($rows as $row) {

        $temperature =
                floatval($row["temperature"]);

        $humidity =
                floatval($row["humidity"]);

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

        $timestamp = $row["timestamp"];

        // ================= TEMPERATURE =================

        if ($temperature >= 30) {

            $alerts[] = [

                "message" =>
                        "Temperature reached "
                        . $temperature . "°C",

                "level" => "critical",

                "location" => "ESP32 Room",

                "created_at" => $timestamp
            ];

        } elseif ($temperature >= 25) {

            $alerts[] = [

                "message" =>
                        "Temperature is "
                        . $temperature . "°C",

                "level" => "warning",

                "location" => "ESP32 Room",

                "created_at" => $timestamp
            ];
        }

        // ================= HUMIDITY =================

        if ($humidity >= 80) {

            $alerts[] = [

                "message" =>
                        "Humidity reached "
                        . $humidity . "%",

                "level" => "critical",

                "location" => "ESP32 Room",

                "created_at" => $timestamp
            ];

        } elseif ($humidity >= 65) {

            $alerts[] = [

                "message" =>
                        "Humidity is "
                        . $humidity . "%",

                "level" => "warning",

                "location" => "ESP32 Room",

                "created_at" => $timestamp
            ];
        }

        // ================= WIFI SIGNAL =================

        if ($signal <= 20) {

            $alerts[] = [

                "message" =>
                        "Signal dropped to "
                        . $signal . "%",

                "level" => "critical",

                "location" => "ESP32 Room",
                "created_at" => $timestamp
            ];

        } elseif ($signal <= 50) {

            $alerts[] = [

                "message" =>
                        "Signal is "
                        . $signal . "%",

                "level" => "warning",

                "location" => "ESP32 Room",

                "created_at" => $timestamp
            ];
        }
    }

    response(
        "success",
        "Alerts loaded",
        $alerts
    );
        echo json_encode([
        "status" => "success",
        "message" => "Alerts loaded",
        "data" => $alerts
    ]);
    exit;
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

    if ($stmt->execute([$temperature,$humidity])) {

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
