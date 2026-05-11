<?php

// ==================== CONFIGURATION ====================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==================== SUPABASE DATABASE ====================

define('DB_HOST', 'db.gfwbtyjzpwvbwpxipdap.supabase.co');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres');
define('DB_PASS', 'YOUR_SUPABASE_PASSWORD');
define('DB_PORT', 5432);

// ==================== PDO CONNECTION ====================

$pdo = null;

function getDBConnection() {
    global $pdo;

    if ($pdo !== null) {
        return $pdo;
    }

    try {

        $dsn = "pgsql:host=" . DB_HOST .
               ";port=" . DB_PORT .
               ";dbname=" . DB_NAME;

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;

    } catch (PDOException $e) {

        die("Erreur connexion Supabase : " . $e->getMessage());

    }
}

// ==================== TEST CONNECTION ====================

$pdo = getDBConnection();

if ($pdo) {
    echo "✅ Connected to Supabase successfully";
}

?>
