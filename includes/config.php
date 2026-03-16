<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

function dbConnect(){

    $host = $_ENV['MYSQLHOST'] ?? '';
    $user = $_ENV['MYSQLUSER'] ?? '';
    $pass = $_ENV['MYSQLPASSWORD'] ?? '';
    $db   = $_ENV['MYSQLDATABASE'] ?? '';
    $port = isset($_ENV['MYSQLPORT']) ? (int)$_ENV['MYSQLPORT'] : 3306;

    if(!$host || !$user || !$db){
        die("Database ENV variables topilmadi");
    }

    $conn = new mysqli(
        $host,
        $user,
        $pass,
        $db,
        $port
    );

    if ($conn->connect_error) {
        die("Database error: ".$conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

    return $conn;
}
