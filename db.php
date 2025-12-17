<?php
$host = "localhost";
$db   = "webtech_2025A_soukouratou_souleymane";
$user = "soukouratou.souleymane";
$pass = "@!!!karim3083";

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_PERSISTENT => false
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
