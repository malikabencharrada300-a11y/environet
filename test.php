<?php

$host = "aws-0-eu-west-2.pooler.supabase.com";
$dbname = "postgres";
$user = "postgres.gfwbtyjzpwvbwpxipdap";
$password = "MET_ICI_TON_PASSWORD";
$port = "6543";

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
