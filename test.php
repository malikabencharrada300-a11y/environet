<?php

$host = "db.gfwbtyjzpwvbwpxipdap.supabase.co";
$dbname = "postgres";
$user = "postgres";
$password = "ghadaa2004+12+25";
$port = "5432";

try {

    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {

    die("Erreur : " . $e->getMessage());

}

?>
