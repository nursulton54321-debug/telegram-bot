<?php
// =============================================
// FILE: includes/config.php
// MAQSAD: Ma'lumotlar bazasi va sayt sozlamalari
// =============================================

// Xatoliklarni ko'rsatish (ishlab chiqish vaqtida)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =============================================
// BAZA SOZLAMALARI
// Railway bo'lsa ENV ishlaydi
// Local bo'lsa localhost ishlaydi
// =============================================

$DB_HOST = getenv('MYSQLHOST') ?: 'localhost';
$DB_USER = getenv('MYSQLUSER') ?: 'root';
$DB_PASS = getenv('MYSQLPASSWORD') ?: '';
$DB_NAME = getenv('MYSQLDATABASE') ?: 'shajara_db';
$DB_PORT = getenv('MYSQLPORT') ?: 3306;


// =============================================
// SAYT SOZLAMALARI
// =============================================

define('SITE_URL', 'https://telegram-bot-production-48ea.up.railway.app/');
define('SITE_NAME', 'Oila Shajarasi');


// =============================================
// MYSQLI ULANISH
// =============================================

function dbConnect() {

    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT;

    $conn = new mysqli(
        $DB_HOST,
        $DB_USER,
        $DB_PASS,
        $DB_NAME,
        $DB_PORT
    );

    if ($conn->connect_error) {
        die("❌ Bazaga ulanishda xatolik: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

    return $conn;
}


// =============================================
// PDO ULANISH (agar kerak bo'lsa)
// =============================================

function dbPDO() {

    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT;

    try {

        $pdo = new PDO(
            "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
            $DB_USER,
            $DB_PASS
        );

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;

    } catch(PDOException $e) {

        die("PDO ulanish xatolik: " . $e->getMessage());

    }
}


// =============================================
// SESSION
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
