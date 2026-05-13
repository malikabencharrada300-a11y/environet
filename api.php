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

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        $mail->Username = 'malikabencharrada300@gmail.com';
        $mail->Password = 'lvue zevd qtvu blmr';

        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('malikabencharrada300@gmail.com', 'Environet');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Environet Reset Code';
        $mail->Body = "<h2>Your code: $code</h2>";

        $mail->send();

        response("success", "Code sent");

    } catch (Exception $e) {

        response("error", $mail->ErrorInfo);
    }
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

?>
