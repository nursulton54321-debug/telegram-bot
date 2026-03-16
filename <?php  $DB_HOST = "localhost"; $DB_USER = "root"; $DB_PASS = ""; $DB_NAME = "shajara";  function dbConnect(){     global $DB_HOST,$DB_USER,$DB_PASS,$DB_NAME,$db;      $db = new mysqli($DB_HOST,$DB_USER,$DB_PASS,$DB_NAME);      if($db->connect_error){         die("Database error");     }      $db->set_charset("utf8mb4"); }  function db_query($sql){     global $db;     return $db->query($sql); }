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
// =============================================
define('DB_HOST', 'localhost');     // Server
define('DB_USER', 'root');          // Foydalanuvchi nomi
define('DB_PASS', '');              // Parol (xamppda bo'sh)
define('DB_NAME', 'shajara_db');    // Database nomi

// =============================================
// SAYT SOZLAMALARI (FAQAT 1 MARTA TA'RIFLANADI)
// =============================================
define('SITE_URL', 'http://localhost/shajara2/');
define('SITE_NAME', 'Oila Shajarasi');

// =============================================
// BAZAGA ULANISH FUNKSIYASI
// =============================================
function dbConnect() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Ulanishni tekshirish
    if ($conn->connect_error) {
        die("❌ Bazaga ulanishda xatolik: " . $conn->connect_error);
    }
    
    // UTF-8 ni o'rnatish
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

// PDO ulanish (agar kerak bo'lsa)
function dbPDO() {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET NAMES utf8mb4");
        return $pdo;
    } catch(PDOException $e) {
        die("PDO ulanish xatolik: " . $e->getMessage());
    }
}

// Sessiyani boshlash
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
