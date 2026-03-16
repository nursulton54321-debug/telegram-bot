<?php

$DB_HOST = getenv('MYSQLHOST');
$DB_USER = getenv('MYSQLUSER');
$DB_PASS = getenv('MYSQLPASSWORD');
$DB_NAME = getenv('MYSQLDATABASE');
$DB_PORT = getenv('MYSQLPORT');

function dbConnect(){

    global $DB_HOST,$DB_USER,$DB_PASS,$DB_NAME,$DB_PORT;

    $conn = new mysqli(
        $DB_HOST,
        $DB_USER,
        $DB_PASS,
        $DB_NAME,
        $DB_PORT
    );

    if($conn->connect_error){
        die("DB error: ".$conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

    return $conn;
}
