<?php
// =============================================
// FILE: bot/backup.php
// MAQSAD: Bazani va rasmlarni arxivlab Adminga yuborish (FAKAT ADMIN UCHUN)
// =============================================

if (session_status() == PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. XAVFSIZLIK TEKSHIRUVI
$is_admin_web = isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;
$is_admin_bot = isset($_GET['key']) && $_GET['key'] === 'MAXFIY_KALIT_2026'; // Bot uchun maxfiy kalit

if (!$is_admin_web && !$is_admin_web) {
    die("Xatolik: Bu amalni bajarish uchun ruxsatingiz yo'q!");
}

define('BOT_TOKEN', '8504597068:AAE3X0K1STed1nVaveY8aqguUBlseEjPUqw');
define('ADMIN_TG_ID', '139619338');

set_time_limit(0);
ini_set('memory_limit', '512M');

function sendTelegramDocument($chat_id, $file_path, $caption) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument";
    $post_fields = [
        'chat_id' => $chat_id,
        'document' => new CURLFile(realpath($file_path)),
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// DATABASE EKSPORT FUNKSIYASI
function exportDatabase($host, $user, $pass, $name) {
    $mysqli = new mysqli($host, $user, $pass, $name);
    $mysqli->set_charset("utf8mb4");
    $tables = [];
    $result = $mysqli->query("SHOW TABLES");
    while ($row = $result->fetch_row()) { $tables[] = $row[0]; }
    $sql_content = "-- Shajara Backup\n-- " . date('Y-m-d H:i') . "\n\nSET FOREIGN_KEY_CHECKS = 0;\n";
    foreach ($tables as $table) {
        $row2 = $mysqli->query("SHOW CREATE TABLE `$table`")->fetch_row();
        $sql_content .= "\n\nDROP TABLE IF EXISTS `$table`;\n" . $row2[1] . ";\n\n";
        $result = $mysqli->query("SELECT * FROM `$table` ");
        while ($row = $result->fetch_row()) {
            $sql_content .= "INSERT INTO `$table` VALUES(";
            for ($j=0; $j<$result->field_count; $j++) {
                $row[$j] = $mysqli->real_escape_string($row[$j]);
                $sql_content .= isset($row[$j]) ? '"'.$row[$j].'"' : 'NULL';
                if ($j<($result->field_count-1)) $sql_content .= ',';
            }
            $sql_content .= ");\n";
        }
    }
    $sql_content .= "\nSET FOREIGN_KEY_CHECKS = 1;";
    $filename = 'db_backup_' . date('Ymd_Hi') . '.sql';
    file_put_contents($filename, $sql_content);
    return $filename;
}

// ZIP FUNKSIYASI
function zipUploads($source, $destination) {
    if (!extension_loaded('zip') || !file_exists($source)) return false;
    $zip = new ZipArchive();
    if (!$zip->open($destination, ZipArchive::CREATE)) return false;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $zip->addFile($filePath, substr($filePath, strlen(realpath($source)) + 1));
        }
    }
    $zip->close();
    return file_exists($destination);
}

// JARAYONNI BOSHLASH
try {
    // 1. SQL
    $sql_file = exportDatabase(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    sendTelegramDocument(ADMIN_TG_ID, $sql_file, "📦 <b>Baza (SQL) Arxivlandi</b>");
    unlink($sql_file);

    // 2. ZIP
    $zip_file = 'uploads_' . date('Ymd_Hi') . '.zip';
    if (zipUploads(__DIR__ . '/../assets/uploads', $zip_file)) {
        sendTelegramDocument(ADMIN_TG_ID, $zip_file, "🖼 <b>Rasmlar (ZIP) Arxivlandi</b>");
        unlink($zip_file);
    }

    if (isset($_GET['web'])) {
        header("Location: ../admin/index.php?backup=success");
    } else {
        echo "OK";
    }
} catch (Exception $e) {
    echo "Xato: " . $e->getMessage();
}