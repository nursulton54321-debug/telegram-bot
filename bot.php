<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// BOT TOKEN
$token = "YANGI_BOT_TOKEN";

// Telegram update olish
$update = json_decode(file_get_contents("php://input"), true);

// Agar message bo‘lsa
if(isset($update["message"])){

    $chat_id = $update["message"]["chat"]["id"];
    $text = $update["message"]["text"];

    // Javob matni
    $reply = "Bot Railway serverda ishlayapti ✅";

    // Telegramga javob yuborish
    file_get_contents("https://api.telegram.org/bot".$token."/sendMessage?chat_id=".$chat_id."&text=".urlencode($reply));
}

?>
