<?php

// ==================== CONFIGURATION ====================


error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// ==================== SUPABASE POSTGRES ====================

define('DB_HOST', 'aws-1-eu-west-2.pooler.supabase.com');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres.gfwbtyjzpwvbwpxipdap');
define('DB_PASS', 'ghadaa2004+12+25'); // remplace si tu changes le mot de passe
define('DB_PORT', '6543');

// ==================== APP ====================

define('APP_NAME', 'EnviroNet');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Europe/Paris');
define('APP_ROOT', __DIR__);

// ==================== ALERT THRESHOLDS ====================

// Température (°C)
define('DEFAULT_TEMP_WARNING_LOW', 18);
define('DEFAULT_TEMP_WARNING_HIGH', 30);
define('DEFAULT_TEMP_CRITICAL_LOW', 10);
define('DEFAULT_TEMP_CRITICAL_HIGH', 35);

// Humidité (%)
define('DEFAULT_HUM_WARNING_LOW', 30);
define('DEFAULT_HUM_WARNING_HIGH', 70);
define('DEFAULT_HUM_CRITICAL_LOW', 20);
define('DEFAULT_HUM_CRITICAL_HIGH', 80);

// Signal WiFi (%)
define('DEFAULT_SIGNAL_WARNING', 40);
define('DEFAULT_SIGNAL_CRITICAL', 20);

// ==================== PDO CONNECTION ====================

$pdo = null;

function getDBConnection()
{
    global $pdo;

    if ($pdo !== null) {
        return $pdo;
    }

    try {

        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;

        $pdo = new PDO(
            $dsn,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        return $pdo;

    } catch (PDOException $e) {
        error_log($e->getMessage());
        die("DB failed");
    }
}

// ==================== JSON RESPONSE ====================

function jsonResponse($success, $message, $data = [])
{
    header('Content-Type: application/json');

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);

    exit;
}

// ==================== USER FUNCTIONS ====================

function getUserByEmail($email)
{
    $pdo = getDBConnection();

    try {

        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        return $stmt->fetch();

    } catch (PDOException $e) {

        error_log($e->getMessage());

        return false;
    }
}

// ==================== SENSOR FUNCTIONS ====================

function saveSensorData(
    $user_id,
    $temperature,
    $humidity,
    $signal_strength = null,
    $bandwidth = null
) {

    $pdo = getDBConnection();

    try {

        $stmt = $pdo->prepare("
            INSERT INTO sensor_data
            (
                user_id,
                temperature,
                humidity,
                signal_strength,
                bandwidth,
                timestamp
            )
            VALUES
            (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $user_id,
            $temperature,
            $humidity,
            $signal_strength,
            $bandwidth
        ]);

        return true;

    } catch (PDOException $e) {

        error_log($e->getMessage());

        return false;
    }
}

function getLatestSensorData($user_id)
{
    $pdo = getDBConnection();

    try {

        $stmt = $pdo->prepare("
            SELECT *
            FROM sensor_data
            WHERE user_id = ?
            ORDER BY timestamp DESC
            LIMIT 1
        ");

        $stmt->execute([$user_id]);

        return $stmt->fetch();

    } catch (PDOException $e) {

        error_log($e->getMessage());

        return false;
    }
}

function getSensorHistory($user_id)
{
    $pdo = getDBConnection();

    try {

        $stmt = $pdo->prepare("
            SELECT *
            FROM sensor_data
            WHERE user_id = ?
            ORDER BY timestamp DESC
        ");

        $stmt->execute([$user_id]);

        return $stmt->fetchAll();

    } catch (PDOException $e) {

        error_log($e->getMessage());

        return [];
    }
}

// ==================== ALERT FUNCTIONS ====================

function createAlert(
    $user_id,
    $type,
    $message,
    $severity = 'warning'
) {

    $pdo = getDBConnection();

    try {

        $stmt = $pdo->prepare("
            INSERT INTO alerts
            (
                user_id,
                type,
                message,
                severity,
                created_at
            )
            VALUES
            (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $user_id,
            $type,
            $message,
            $severity
        ]);

        return true;

    } catch (PDOException $e) {

        error_log($e->getMessage());

        return false;
    }
}

function getAlerts($user_id)
{
    $pdo = getDBConnection();

    try {

        $stmt = $pdo->prepare("
            SELECT *
            FROM alerts
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");

        $stmt->execute([$user_id]);

        return $stmt->fetchAll();

    } catch (PDOException $e) {

        error_log($e->getMessage());

        return [];
    }
}

// ==================== TEST DATABASE CONNECTION ====================

// Uncomment this to test connection

/*
$pdo = getDBConnection();

if ($pdo) {

    echo json_encode([
        "success" => true,
        "message" => "Connected to Supabase successfully"
    ]);

}
*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: index.html");
        exit;
    }
}

function addRoom($user_id, $room_name, $location)
{
    $pdo = getDBConnection();

    try {

        $stmt = $pdo->prepare("
            INSERT INTO rooms
            (
                user_id,
                room_name,
                location,
                created_at
            )
            VALUES
            (?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $user_id,
            $room_name,
            $location
        ]);

        return true;

    } catch (PDOException $e) {

        error_log($e->getMessage());

        return false;
    }
}

?>
