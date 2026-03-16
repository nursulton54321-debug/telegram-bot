<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

function dbConnect(){

    $host = $_ENV['MYSQLHOST'];
    $user = $_ENV['MYSQLUSER'];
    $pass = $_ENV['MYSQLPASSWORD'];
    $db   = $_ENV['MYSQLDATABASE'];
    $port = $_ENV['MYSQLPORT'];

    $conn = new mysqli(
        $host,
        $user,
        $pass,
        $db,
        $port
    );

    if ($conn->connect_error) {
        die("Database connection error: ".$conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

    return $conn;
}
