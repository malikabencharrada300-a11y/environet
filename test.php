<?php

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

    echo "Connexion réussie 🎉";

} catch(PDOException $e) {

    die($e->getMessage());

}

?>
