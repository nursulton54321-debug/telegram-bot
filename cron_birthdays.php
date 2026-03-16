<?php
// =============================================
// FILE: bot/cron_birthdays.php
// MAQSAD: Tug'ilgan kun, Xotira kuni va To'y yubileylarini avtomat eslatish
// =============================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

define('BOT_TOKEN', '8504597068:AAE3X0K1STed1nVaveY8aqguUBlseEjPUqw'); 

function sendTelegramMessage($chat_id, $text) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    $ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $url); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch); curl_close($ch);
}

dbConnect();

// XAVFSIZLIK: Bazada "nikoh_sana" ustuni yo'q bo'lsa, avtomat yaratib olamiz
$chk_nikoh = db_query("SHOW COLUMNS FROM shaxslar LIKE 'nikoh_sana'");
if ($chk_nikoh && $chk_nikoh->num_rows == 0) {
    db_query("ALTER TABLE shaxslar ADD COLUMN nikoh_sana DATE NULL AFTER vafot_sana");
}

$msg = "";

// =========================================
// 1. TUG'ILGAN KUNLARNI TEKSHIRISH
// =========================================
$sql_b = "SELECT ism, familiya, tugilgan_sana FROM shaxslar 
          WHERE tugilgan_sana IS NOT NULL AND tugilgan_sana != '0000-00-00' 
          AND (vafot_sana IS NULL OR vafot_sana = '0000-00-00') 
          AND MONTH(tugilgan_sana) = MONTH(CURRENT_DATE()) 
          AND DAY(tugilgan_sana) = DAY(CURRENT_DATE())";
$res_b = db_query($sql_b);
if ($res_b && $res_b->num_rows > 0) {
    $msg .= "🎉 <b>BUGUN TUG'ILGAN KUN!</b> 🎉\n\n";
    while ($r = $res_b->fetch_assoc()) {
        $yosh = date('Y') - date('Y', strtotime($r['tugilgan_sana']));
        $msg .= "🎁 <b>{$r['ism']} {$r['familiya']}</b> — {$yosh} yoshga to'ldi!\n";
    }
    $msg .= "\n";
}

// =========================================
// 2. XOTIRA KUNLARINI TEKSHIRISH (Vafot etganlar)
// =========================================
$sql_v = "SELECT ism, familiya, vafot_sana FROM shaxslar 
          WHERE vafot_sana IS NOT NULL AND vafot_sana != '0000-00-00' 
          AND MONTH(vafot_sana) = MONTH(CURRENT_DATE()) 
          AND DAY(vafot_sana) = DAY(CURRENT_DATE())";
$res_v = db_query($sql_v);
if ($res_v && $res_v->num_rows > 0) {
    $msg .= "🕯 <b>XOTIRA KUNI</b> 🕯\n\nBugun quyidagi ajdodlarimizni xotirlaymiz:\n";
    while ($r = $res_v->fetch_assoc()) {
        $yillar = date('Y') - date('Y', strtotime($r['vafot_sana']));
        $msg .= "🥀 <b>{$r['ism']} {$r['familiya']}</b> — Olamdan o'tganlariga {$yillar} yil bo'ldi. Oxiratlari obod bo'lsin.\n";
    }
    $msg .= "\n";
}

// =========================================
// 3. TO'Y YUBILEYLARINI TEKSHIRISH
// =========================================
$sql_n = "SELECT ism, familiya, nikoh_sana FROM shaxslar 
          WHERE nikoh_sana IS NOT NULL AND nikoh_sana != '0000-00-00' 
          AND MONTH(nikoh_sana) = MONTH(CURRENT_DATE()) 
          AND DAY(nikoh_sana) = DAY(CURRENT_DATE())";
$res_n = db_query($sql_n);
if ($res_n && $res_n->num_rows > 0) {
    $msg .= "💍 <b>TO'Y YUBILEYI!</b> 💍\n\n";
    while ($r = $res_n->fetch_assoc()) {
        $yillar = date('Y') - date('Y', strtotime($r['nikoh_sana']));
        $msg .= "🥂 <b>{$r['ism']} {$r['familiya']}</b> — Oila qurganlariga {$yillar} yil to'ldi!\n";
    }
    $msg .= "\n";
}

// =========================================
// XABARNI BARCHA A'ZOLARGA YUBORISH
// =========================================
if ($msg !== "") {
    $final_msg = "🔔 <b>SHAJARA ESLATMASI</b> 🔔\n\n" . $msg;
    
    // Ruxsat berilgan (approved) barcha bot a'zolarini topish
    $users_res = db_query("SELECT tg_id FROM bot_users WHERE status = 'approved'");
    while ($user = $users_res->fetch_assoc()) {
        sendTelegramMessage($user['tg_id'], $final_msg);
    }
    echo "<h2 style='color:green;'>✅ Eslatmalar muvaffaqiyatli yuborildi!</h2>";
    echo nl2br($final_msg);
} else {
    echo "<h3 style='color:gray;'>Bugungi sana uchun tug'ilgan kun, xotira kuni yoki yubileylar topilmadi.</h3>";
}
?>