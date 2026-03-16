<?php
// =============================================
// FILE: bot/cron_tabrik.php
// MAQSAD: Har kuni ertalab bazani tekshirib, tug'ilgan kuni bo'lganlarni oilaviy guruhga tabriklash
// =============================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Xatoliklarni ko'rsatish (faqat test uchun, o'xshagandan so'ng 0 qilib qo'yasiz)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Baza ulanishi
if (function_exists('dbConnect')) {
    dbConnect();
}

// ========================================================
// BOT VA GURUH SOZLAMALARI
// ========================================================
define('BOT_TOKEN', '8504597068:AAE3X0K1STed1nVaveY8aqguUBlseEjPUqw'); 

// SIZ BERGAN GURUH ID SI (-100 qo'shilgan holda olinadi, chunki superguruhlar shunday ishlaydi. 
// Agar pastdagi ishlamasa shunchaki '-1053694544' yoki '1053694544' qilib ko'rasiz).
define('GROUP_CHAT_ID', '-1001053694544'); 

// Agar sizning guruhingiz oddiy guruh bo'lsa (superguruh emas), unda ID ni o'zgarishsiz qoldiring:
// define('GROUP_CHAT_ID', '-1053694544');

function sendTelegramMessage($chat_id, $text) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $text,
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

function sendTelegramPhoto($chat_id, $photo_path, $caption) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendPhoto";
    $post_fields = [
        'chat_id' => $chat_id,
        'photo' => new CURLFile($photo_path),
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

// BUGUNGI KUNNI ANIQLAYMIZ
$bugun_oy = date('m');
$bugun_kun = date('d');
$joriy_yil = date('Y');

// BAZADAN BUGUN TUG'ILGAN TIRIK SHAXSLARNI QIDIRAMIZ
$sql = "SELECT s.*, o.ota_id, o.ona_id 
        FROM shaxslar s 
        LEFT JOIN oilaviy_bogliqlik o ON s.id = o.shaxs_id 
        WHERE MONTH(s.tugilgan_sana) = '$bugun_oy' AND DAY(s.tugilgan_sana) = '$bugun_kun' AND s.tirik = 1";

$result = db_query($sql);
$tabrik_soni = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        
        $ism = html_entity_decode($row['ism'], ENT_QUOTES, 'UTF-8');
        $familiya = html_entity_decode($row['familiya'], ENT_QUOTES, 'UTF-8');
        $yosh = $joriy_yil - date('Y', strtotime($row['tugilgan_sana']));
        
        // OTA YOKI ONA ISMINI ANIQLASH (Nurislom Matayevning farzandi... deb chiqarish uchun)
        $ota_ona_matn = "";
        
        if (!empty($row['ota_id'])) {
            $ota_res = db_query("SELECT ism, familiya FROM shaxslar WHERE id = " . $row['ota_id']);
            if ($ota_res && $ota_res->num_rows > 0) {
                $ota = $ota_res->fetch_assoc();
                $ota_ism = html_entity_decode($ota['ism'] . ' ' . $ota['familiya'], ENT_QUOTES, 'UTF-8');
                $ota_ona_matn = "<b>" . $ota_ism . "</b>ning farzandi ";
            }
        } elseif (!empty($row['ona_id'])) {
            $ona_res = db_query("SELECT ism, familiya FROM shaxslar WHERE id = " . $row['ona_id']);
            if ($ona_res && $ona_res->num_rows > 0) {
                $ona = $ona_res->fetch_assoc();
                $ona_ism = html_entity_decode($ona['ism'] . ' ' . $ona['familiya'], ENT_QUOTES, 'UTF-8');
                $ota_ona_matn = "<b>" . $ona_ism . "</b>ning farzandi ";
            }
        }

        // TABRIK MATNINI YARATISH
        if ($ota_ona_matn != "") {
            $tabrik = "🎉 Bugun $ota_ona_matn <b>$ism $familiya</b>ning <b>$yosh yoshga</b> to'lgan kuni!\n\n";
        } else {
            $tabrik = "🎉 Bugun qadrdonimiz <b>$ism $familiya</b>ning <b>$yosh yoshga</b> to'lgan kuni!\n\n";
        }
        
        $tabrik .= "🎈 Oilamiz nomidan chin qalbdan tabriklaymiz! Uzoq umr, sihat-salomatlik va baxt-saodat tilaymiz! 🎊🎁";

        // RASM BOR-YO'QLIGINI TEKSHIRISH VA YUBORISH
        $rasm_yuborildi = false;
        if (!empty($row['foto'])) {
            $photo_path = realpath(__DIR__ . '/../assets/uploads/' . $row['foto']);
            if ($photo_path && file_exists($photo_path)) {
                $tg_javob = sendTelegramPhoto(GROUP_CHAT_ID, $photo_path, $tabrik);
                $javob_dec = json_decode($tg_javob, true);
                if(isset($javob_dec['ok']) && $javob_dec['ok']) {
                    $rasm_yuborildi = true;
                }
            }
        }

        // AGAR RASM YO'Q BO'LSA YOKI RASM YUBORISHDA XATO BO'LSA, FAQAT MATN JO'NATAMIZ
        if (!$rasm_yuborildi) {
            sendTelegramMessage(GROUP_CHAT_ID, $tabrik);
        }
        
        $tabrik_soni++;
    }
    
    echo "$tabrik_soni ta odamga tabrik yuborildi!";
} else {
    echo "Bugun tug'ilgan kunlar yo'q.";
}
?>