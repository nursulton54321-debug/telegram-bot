<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

$DB_HOST = getenv('MYSQLHOST');
$DB_USER = getenv('MYSQLUSER');
$DB_PASS = getenv('MYSQLPASSWORD');
$DB_NAME = getenv('MYSQLDATABASE');
$DB_PORT = getenv('MYSQLPORT') ?: 3306;

function dbConnect(){

    global $DB_HOST,$DB_USER,$DB_PASS,$DB_NAME,$DB_PORT;

    if(!$DB_HOST){
        die("Database ENV variables topilmadi");
    }

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
