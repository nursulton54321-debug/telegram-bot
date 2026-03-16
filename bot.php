<?php
// =============================================
// FILE: bot/bot.php
// MAQSAD: Qidiruv, PIN-kod, Moderatsiya, Backup, Tug'ilgan kunlar va Foydalanuvchilar boshqaruvi
// =============================================

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/keyboards.php';
require_once 'keyboards.php';

define('BOT_TOKEN', '8504597068:AAH1Gxh5aoHgls8jVZ3boSVUUmtpLM4cPEw'); 
define('ADMIN_TG_ID', '139619338'); 

function sendTelegram($method, $data) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    $ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $url); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch); curl_close($ch); return json_decode($res, true);
}

function getNameById($id) {
    if (!$id || $id == "NULL" || $id == "") return "Bog'lanmagan";
    $res = db_query("SELECT ism, familiya FROM shaxslar WHERE id = $id");
    if ($res && $row = $res->fetch_assoc()) return $row['ism'] . " " . $row['familiya'];
    return "Noma'lum";
}

function getEmojiProgress($percent) {
    $filled = round($percent / 10); $empty = 10 - $filled;
    if ($filled < 0) $filled = 0; if ($empty < 0) $empty = 0;
    return str_repeat('🟩', $filled) . str_repeat('⬜', $empty);
}

function downloadTelegramPhoto($file_id) {
    if (!$file_id || $file_id == 'NULL') return '';
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getFile?file_id=" . $file_id;
    $res = @file_get_contents($url);
    if ($res) {
        $json = json_decode($res, true);
        if (isset($json['result']['file_path'])) {
            $fp = $json['result']['file_path'];
            $dl = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $fp;
            $ext = pathinfo($fp, PATHINFO_EXTENSION) ?: 'jpg';
            $nn = 'tg_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            if (@file_put_contents(__DIR__ . '/../assets/uploads/' . $nn, file_get_contents($dl))) return $nn;
        }
    }
    return '';
}

dbConnect();
global $db;

// Sozlamalar jadvali va PIN-kod bazasi ishonchli yaratilishi
$chk_sozlama = db_query("SHOW TABLES LIKE 'sozlamalar'");
if ($chk_sozlama && $chk_sozlama->num_rows == 0) {
    db_query("CREATE TABLE sozlamalar (kalit VARCHAR(50) PRIMARY KEY, qiymat VARCHAR(255))");
    db_query("INSERT IGNORE INTO sozlamalar (kalit, qiymat) VALUES ('sayt_pin', '2026')");
}

function getSitePin() {
    $res = db_query("SELECT qiymat FROM sozlamalar WHERE kalit = 'sayt_pin'");
    if ($res && $res->num_rows > 0) return $res->fetch_assoc()['qiymat'];
    return '2026';
}

$chk_add = db_query("SHOW COLUMNS FROM shaxslar LIKE 'added_by_tg_id'");
if ($chk_add && $chk_add->num_rows == 0) db_query("ALTER TABLE shaxslar ADD COLUMN added_by_tg_id BIGINT NULL AFTER foto");

$chk_vaf = db_query("SHOW COLUMNS FROM shaxslar LIKE 'vafot_sana'");
if ($chk_vaf && $chk_vaf->num_rows == 0) db_query("ALTER TABLE shaxslar ADD COLUMN vafot_sana DATE NULL AFTER tugilgan_sana");

function setStep($tg_id, $step, $temp_arr) {
    $json_safe = addslashes(json_encode($temp_arr, JSON_UNESCAPED_UNICODE));
    db_query("UPDATE bot_users SET step = '$step', temp_data = '$json_safe' WHERE tg_id = $tg_id");
}

function startQuiz($chat_id) {
    global $db;
    $sql = "SELECT s.id, s.ism, s.familiya, s.jins, o.ota_id, o.ona_id, o.turmush_ortogi_id 
            FROM shaxslar s LEFT JOIN oilaviy_bogliqlik o ON s.id = o.shaxs_id";
    $res = db_query($sql);
    if(!$res) { sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "⚠️ Bazaga ulanishda xatolik yuz berdi."]); return; }

    $shaxslar = [];
    while($r = $res->fetch_assoc()) { 
        $r['ism'] = html_entity_decode($r['ism'] ?? '', ENT_QUOTES, 'UTF-8');
        $r['familiya'] = html_entity_decode($r['familiya'] ?? '', ENT_QUOTES, 'UTF-8');
        $shaxslar[$r['id']] = $r; 
    }

    $relations = [];
    foreach($shaxslar as $id => $p) {
        $nameA = trim($p['ism'] . " " . $p['familiya']);
        $jinsA = $p['jins'];

        if (!empty($p['ota_id']) && isset($shaxslar[$p['ota_id']])) {
            $p2 = $shaxslar[$p['ota_id']]; $nameB = trim($p2['ism'] . " " . $p2['familiya']);
            $relations[] = ['a' => $nameA, 'b' => $nameB, 'ans' => ($jinsA == 'erkak' ? "O'g'li" : "Qizi")];
            $relations[] = ['a' => $nameB, 'b' => $nameA, 'ans' => "Otasi"];

            foreach($shaxslar as $id3 => $p3) {
                if (!empty($p3['ota_id']) && $p3['ota_id'] == $p['ota_id'] && $id != $id3) {
                    $nameC = trim($p3['ism'] . " " . $p3['familiya']);
                    $relations[] = ['a' => $nameA, 'b' => $nameC, 'ans' => ($jinsA == 'erkak' ? "Akasi (yoki Ukasi)" : "Opasi (yoki Singlisi)")];
                }
            }

            if (!empty($p2['ota_id']) && isset($shaxslar[$p2['ota_id']])) {
                $p3 = $shaxslar[$p2['ota_id']]; $nameC = trim($p3['ism'] . " " . $p3['familiya']);
                $relations[] = ['a' => $nameA, 'b' => $nameC, 'ans' => ($jinsA == 'erkak' ? "Nevarasi (o'g'il)" : "Nevarasi (qiz)")];
                $relations[] = ['a' => $nameC, 'b' => $nameA, 'ans' => ($p3['jins'] == 'erkak' ? "Bobosi" : "Buvisi")];
            }
        }
        if (!empty($p['ona_id']) && isset($shaxslar[$p['ona_id']])) {
            $p2 = $shaxslar[$p['ona_id']]; $nameB = trim($p2['ism'] . " " . $p2['familiya']);
            $relations[] = ['a' => $nameA, 'b' => $nameB, 'ans' => ($jinsA == 'erkak' ? "O'g'li" : "Qizi")];
            $relations[] = ['a' => $nameB, 'b' => $nameA, 'ans' => "Onasi"];
        }
        if (!empty($p['turmush_ortogi_id']) && isset($shaxslar[$p['turmush_ortogi_id']])) {
            $p2 = $shaxslar[$p['turmush_ortogi_id']]; $nameB = trim($p2['ism'] . " " . $p2['familiya']);
            $relations[] = ['a' => $nameA, 'b' => $nameB, 'ans' => ($jinsA == 'erkak' ? "Eri" : "Ayoli")];
        }
    }

    if (empty($relations)) { sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "⚠️ O'yin o'ynash uchun bazada kamida 2 ta bog'langan kishi bo'lishi kerak!"]); return; }

    $relations = array_values(array_map("unserialize", array_unique(array_map("serialize", $relations))));
    $rand_idx = array_rand($relations); $q = $relations[$rand_idx]; $correct = $q['ans'];

    $pool = ["O'g'li", "Qizi", "Otasi", "Onasi", "Bobosi", "Buvisi", "Nevarasi (o'g'il)", "Nevarasi (qiz)", "Akasi (yoki Ukasi)", "Opasi (yoki Singlisi)", "Eri", "Ayoli", "Tog'asi", "Amakisi", "Ammasi", "Xolasi"];
    $pool = array_values(array_diff($pool, [$correct])); shuffle($pool);

    $options = [['text' => $correct, 'cb' => "quiz_1"], ['text' => $pool[0], 'cb' => "quiz_0"], ['text' => $pool[1], 'cb' => "quiz_0"], ['text' => $pool[2], 'cb' => "quiz_0"]];
    shuffle($options); 

    $inline = []; foreach($options as $opt) { $inline[] = [['text' => $opt['text'], 'callback_data' => $opt['cb']]]; }
    $msg = "🎮 <b>OILA VIKTORINASI</b>\n\n❓ <b>{$q['a']}</b>\n<b>{$q['b']}</b> ning kimi bo'ladi?\n\n<i>To'g'ri variantni tanlang: 👇</i>";
    sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['inline_keyboard' => $inline])]);
}


$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chat_id = $callback['message']['chat']['id'];
    $data = $callback['data'];
    $message_id = $callback['message']['message_id'];

    $user_res = db_query("SELECT * FROM bot_users WHERE tg_id = $chat_id");
    $user = ($user_res && $user_res->num_rows > 0) ? $user_res->fetch_assoc() : null;
    $temp = $user ? json_decode($user['temp_data'], true) : [];

    // FOYDALANUVCHIGA RUXSAT BERISH
    if (strpos($data, 'appruser_') === 0 && $chat_id == ADMIN_TG_ID) {
        $u_id = (int)str_replace('appruser_', '', $data);
        db_query("UPDATE bot_users SET status = 'approved' WHERE tg_id = $u_id");
        $pin = getSitePin();
        sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "✅ Foydalanuvchiga ruxsat berildi!"]);
        sendTelegram('sendMessage', ['chat_id' => $u_id, 'text' => "🎉 *Tabriklaymiz!* Admin sizga shajaradan foydalanishga ruxsat berdi.\n\n🔐 *Saytga kirish uchun PIN-kod:* `$pin`\n\nMarhamat, bot menyularidan foydalaning 👇", 'parse_mode' => 'Markdown', 'reply_markup' => btn_main_menu($u_id)]);
        exit;
    }

    if (strpos($data, 'rejuser_') === 0 && $chat_id == ADMIN_TG_ID) {
        $u_id = (int)str_replace('rejuser_', '', $data);
        db_query("UPDATE bot_users SET status = 'rejected' WHERE tg_id = $u_id");
        sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "❌ Foydalanuvchi rad etildi."]);
        sendTelegram('sendMessage', ['chat_id' => $u_id, 'text' => "❌ Kechirasiz, admin sizning so'rovingizni rad etdi."]);
        exit;
    }

    if (strpos($data, 'quiz_') === 0) {
        if ($data == 'quiz_1') {
            $msg = "🎉 <b>BARAKALLA!</b>\n\n✅ To'g'ri topdingiz! Siz shajarani a'lo darajada bilasiz 👏";
            $kb = json_encode(['inline_keyboard' => [[['text' => "🔄 Yana savol berish", 'callback_data' => "quiz_next"]]]]);
            sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => $kb]);
        } elseif ($data == 'quiz_0') {
            sendTelegram('answerCallbackQuery', ['callback_query_id' => $callback['id'], 'text' => "❌ Noto'g'ri javob! Yana o'ylab ko'ring.", 'show_alert' => true]);
        } elseif ($data == 'quiz_next') {
            sendTelegram('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
            startQuiz($chat_id);
        }
        exit;
    }

    // FOYDALANUVCHILARNI BOSHQARISH (WARN/BAN/UNBAN)
    if (strpos($data, 'u_warn_') === 0 && $chat_id == ADMIN_TG_ID) {
        $target_id = str_replace('u_warn_', '', $data);
        setStep($chat_id, 'admin_input_warn', ['target_id' => $target_id]);
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "⚠️ <b>ID: $target_id</b> ga yuboriladigan ogohlantirish matnini yozing:", 'parse_mode' => 'HTML', 'reply_markup' => btn_cancel()]);
        exit;
    }
    
    if (strpos($data, 'u_ban_') === 0 && $chat_id == ADMIN_TG_ID) {
        $target_id = str_replace('u_ban_', '', $data);
        $curr = db_query("SELECT status FROM bot_users WHERE tg_id = $target_id")->fetch_assoc();
        
        if ($curr['status'] == 'rejected') {
            db_query("UPDATE bot_users SET status = 'approved' WHERE tg_id = $target_id");
            sendTelegram('sendMessage', ['chat_id' => $target_id, 'text' => "✅ Botdan foydalanish huquqingiz tiklandi."]);
            sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "✅ Foydalanuvchi (ID: $target_id) bandan chiqarildi."]);
        } else {
            db_query("UPDATE bot_users SET status = 'rejected' WHERE tg_id = $target_id");
            sendTelegram('sendMessage', ['chat_id' => $target_id, 'text' => "🚫 <b>Sizning botdan foydalanish huquqingiz admin tomonidan bekor qilindi.</b>", 'parse_mode' => 'HTML']);
            sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "✅ Foydalanuvchi (ID: $target_id) ban qilindi."]);
        }
        exit;
    }

    // "ORQAGA" tugmasi ishlashi uchun
    if ($data == 'back_to_list') {
        sendTelegram('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
        exit;
    }

    if (strpos($data, 'manage_') === 0) {
        $shaxs_id = (int)str_replace('manage_', '', $data);
        $s_data = db_query("SELECT * FROM shaxslar WHERE id = $shaxs_id")->fetch_assoc();
        if ($s_data) {
            $msg = "⚙️ <b>SHAXSNI BOSHQARISH</b>\n\n👤 <b>F.I.SH:</b> {$s_data['familiya']} {$s_data['ism']} {$s_data['otasining_ismi']}\n📅 <b>Tug'ilgan:</b> " . date('d.m.Y', strtotime($s_data['tugilgan_sana'])) . "\n\nNimani amalga oshiramiz?";
            $kb = json_encode(['inline_keyboard' => [
                [['text' => "✏️ Ismni tahrirlash", 'callback_data' => "edit_ism_".$shaxs_id], ['text' => "✏️ Familiyani tahrirlash", 'callback_data' => "edit_familiya_".$shaxs_id]],
                [['text' => "✏️ Sanani tahrirlash", 'callback_data' => "edit_tugilgan_sana_".$shaxs_id]],
                [['text' => "👨‍👦 Ota biriktirish", 'callback_data' => "asklink_ota_".$shaxs_id], ['text' => "👩‍👦 Ona biriktirish", 'callback_data' => "asklink_ona_".$shaxs_id]],
                [['text' => "💍 Juft biriktirish", 'callback_data' => "asklink_juft_".$shaxs_id]],
                [['text' => "🗑 Butunlay o'chirish", 'callback_data' => "del_main_".$shaxs_id]],
                [['text' => "🔙 Orqaga", 'callback_data' => "back_to_list"]]
            ]]);
            sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => $kb]);
        }
        exit;
    }

    if (strpos($data, 'edit_') === 0 && strpos($data, 'edit_ariza_') === false) {
        $parts = explode('_', $data);
        if (count($parts) >= 3) {
            $shaxs_id = array_pop($parts); array_shift($parts); $field = implode('_', $parts);
            setStep($chat_id, "editmain_{$field}_{$shaxs_id}", []);
            $field_name = ($field == 'ism') ? 'Yangi ismni' : (($field == 'familiya') ? 'Yangi familiyani' : 'Yangi sanani (kk.oo.yyyy)');
            sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "✏️ <b>$field_name kiriting:</b>", 'parse_mode' => 'HTML', 'reply_markup' => btn_cancel()]);
        }
        exit;
    }

    if (strpos($data, 'asklink_') === 0) {
        $parts = explode('_', $data); $tur = $parts[1]; $shaxs_id = $parts[2];
        setStep($chat_id, "dolink_{$tur}_{$shaxs_id}", []);
        $rol = $tur == 'ota' ? 'Otasining' : ($tur == 'ona' ? 'Onasining' : 'Turmush o\'rtog\'ining');
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "🔍 <b>$rol ismi qanday?</b>\n<i>(Baza ichidan qidirish uchun 3 ta harf yozib yuboring)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_cancel()]);
        exit;
    }

    if (strpos($data, 'setlink_') === 0) {
        $parts = explode('_', $data); $tur = $parts[1]; $shaxs_id = $parts[2]; $target_id = $parts[3];
        $col = $tur == 'ota' ? 'ota_id' : ($tur == 'ona' ? 'ona_id' : 'turmush_ortogi_id');
        $chk = db_query("SELECT id FROM oilaviy_bogliqlik WHERE shaxs_id = $shaxs_id");
        if ($chk->num_rows > 0) db_query("UPDATE oilaviy_bogliqlik SET $col = $target_id WHERE shaxs_id = $shaxs_id");
        else db_query("INSERT INTO oilaviy_bogliqlik (shaxs_id, $col) VALUES ($shaxs_id, $target_id)");
        sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "✅ Muvaffaqiyatli biriktirildi!"]);
        setStep($chat_id, 'none', []);
        exit;
    }

    if (strpos($data, 'del_main_') === 0) {
        $shaxs_id = (int)str_replace('del_main_', '', $data);
        db_query("DELETE FROM shaxslar WHERE id = $shaxs_id"); db_query("DELETE FROM oilaviy_bogliqlik WHERE shaxs_id = $shaxs_id");
        sendTelegram('answerCallbackQuery', ['callback_query_id' => $callback['id'], 'text' => "Shaxs butunlay o'chirildi!", 'show_alert' => true]);
        sendTelegram('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
        exit;
    }

    if ($user && $user['status'] != 'approved' && $chat_id != ADMIN_TG_ID) {
        sendTelegram('answerCallbackQuery', ['callback_query_id' => $callback['id'], 'text' => "Sizda huquq yo'q!", 'show_alert' => true]);
        exit;
    }

    if (strpos($data, 'approve_') === 0 && $chat_id == ADMIN_TG_ID) {
        $ariza_id = (int)str_replace('approve_', '', $data);
        $ariza = db_query("SELECT * FROM shaxslar_kutilmoqda WHERE id = $ariza_id AND status = 'kutilmoqda'")->fetch_assoc();
        if ($ariza) {
            $i = addslashes($ariza['ism']); $f = addslashes($ariza['familiya']); $oi = addslashes($ariza['otasining_ismi'] ?? '');
            $j = addslashes($ariza['jins']); $s = addslashes($ariza['tugilgan_sana']); 
            $t = addslashes($ariza['telefon'] ?? ''); $k = addslashes($ariza['kasbi'] ?? '');
            $added_by = $ariza['added_by_tg_id']; $p = downloadTelegramPhoto($ariza['foto']); 

            $o = ($ariza['ota_id'] && $ariza['ota_id'] != 'NULL' && $ariza['ota_id'] != 0) ? (int)$ariza['ota_id'] : "NULL"; 
            $on = ($ariza['ona_id'] && $ariza['ona_id'] != 'NULL' && $ariza['ona_id'] != 0) ? (int)$ariza['ona_id'] : "NULL"; 
            $tur = ($ariza['turmush_ortogi_id'] && $ariza['turmush_ortogi_id'] != 'NULL' && $ariza['turmush_ortogi_id'] != 0) ? (int)$ariza['turmush_ortogi_id'] : "NULL";

            if (db_query("INSERT INTO shaxslar (ism, familiya, otasining_ismi, jins, tugilgan_sana, telefon, kasbi, foto, added_by_tg_id, created_at) VALUES ('$i', '$f', '$oi', '$j', '$s', '$t', '$k', '$p', '$added_by', NOW())")) {
                $y_res = db_query("SELECT LAST_INSERT_ID() as id"); $yangi_id = $y_res->fetch_assoc()['id'];
                if ($o != "NULL" || $on != "NULL" || $tur != "NULL") db_query("INSERT INTO oilaviy_bogliqlik (shaxs_id, ota_id, ona_id, turmush_ortogi_id) VALUES ($yangi_id, $o, $on, $tur)");
                db_query("UPDATE shaxslar_kutilmoqda SET status = 'tasdiqlangan' WHERE id = $ariza_id");
                sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "✅ $i $f shajaraga qo'shildi!"]);
                sendTelegram('sendMessage', ['chat_id' => $added_by, 'text' => "🎉 Xushxabar! Siz yuborgan <b>$i $f</b> admin tomonidan tasdiqlandi!", 'parse_mode' => 'HTML']);
            }
        } else { sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "⚠️ Bu ariza ko'rib chiqilgan."]); }
        exit;
    }

    if (strpos($data, 'reject_') === 0) {
        if ($data == 'reject_person') {
            setStep($chat_id, 'none', []);
            sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "❌ Bekor qilindi."]);
            sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "Bosh menyu:", 'reply_markup' => btn_main_menu($chat_id)]);
        } else {
            $ariza_id = (int)str_replace('reject_', '', $data);
            db_query("UPDATE shaxslar_kutilmoqda SET status = 'rad_etilgan' WHERE id = $ariza_id");
            sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "❌ Ariza rad etildi."]);
        }
        exit;
    }

    if (strpos($data, 'apprevent_') === 0 && $chat_id == ADMIN_TG_ID) {
        $v_id = (int)str_replace('apprevent_', '', $data);
        $v_data = db_query("SELECT * FROM shaxs_voqealar_kutilmoqda WHERE id = $v_id AND status = 'kutilmoqda'")->fetch_assoc();
        
        if ($v_data) {
            $sid = $v_data['shaxs_id'];
            $harakat = $v_data['harakat'];
            $target_vid = $v_data['voqea_id'];
            $sana = addslashes($v_data['sana']);
            $sarlavha = addslashes($v_data['sarlavha']);
            $matn = addslashes($v_data['matn']);
            
            if ($harakat == 'add') {
                db_query("INSERT INTO shaxs_voqealar (shaxs_id, sana, sarlavha, matn, icon, color) VALUES ($sid, '$sana', '$sarlavha', '$matn', 'fa-star', '#667eea')");
                $harakat_txt = "qo'shildi";
            } elseif ($harakat == 'edit') {
                db_query("UPDATE shaxs_voqealar SET sana='$sana', sarlavha='$sarlavha', matn='$matn' WHERE id=$target_vid");
                $harakat_txt = "tahrirlandi";
            } elseif ($harakat == 'delete') {
                db_query("DELETE FROM shaxs_voqealar WHERE id=$target_vid");
                $harakat_txt = "o'chirildi";
            }
            
            db_query("UPDATE shaxs_voqealar_kutilmoqda SET status = 'tasdiqlandi' WHERE id = $v_id");
            
            sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "✅ Voqea muvaffaqiyatli $harakat_txt va Timeline yangilandi!"]);
        } else {
            sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "⚠️ Bu ariza allaqachon ko'rib chiqilgan yoki topilmadi."]);
        }
        exit;
    }

    if (strpos($data, 'rejevent_') === 0 && $chat_id == ADMIN_TG_ID) {
        $v_id = (int)str_replace('rejevent_', '', $data);
        db_query("UPDATE shaxs_voqealar_kutilmoqda SET status = 'rad_etildi' WHERE id = $v_id");
        sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "❌ Voqea amaliyoti rad etildi."]);
        exit;
    }

    if (strpos($data, 'del_ariza_') === 0) {
        $del_id = (int)str_replace('del_ariza_', '', $data);
        db_query("DELETE FROM shaxslar_kutilmoqda WHERE id = $del_id AND status = 'kutilmoqda'");
        sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "🗑 Arizangiz o'chirildi."]);
        exit;
    }
    if (strpos($data, 'edit_ariza_') === 0) {
        $del_id = (int)str_replace('edit_ariza_', '', $data);
        db_query("DELETE FROM shaxslar_kutilmoqda WHERE id = $del_id AND status = 'kutilmoqda'");
        sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "✏️ Tahrirlash uchun eski ariza bekor qilindi.\nQaytadan kiriting."]);
        setStep($chat_id, 'add_ism', []);
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "✍️ <b>1. Ismni kiriting:</b>\n<i>(Masalan: Nurislom)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_cancel()]);
        exit;
    }

    if (strpos($data, 'set_ota_') === 0 || $data === 'skip_ota') {
        $temp['ota_id'] = ($data === 'skip_ota') ? null : (int)str_replace('set_ota_', '', $data); setStep($chat_id, 'ask_ona', $temp);
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "👩‍👦 <b>10. Onasining ismi qanday?</b>\n<i>(Baza ichidan qidirish uchun 3 ta harf yozib yuboring)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_skip_cancel()]);
    }
    elseif (strpos($data, 'set_ona_') === 0 || $data === 'skip_ona') {
        $temp['ona_id'] = ($data === 'skip_ona') ? null : (int)str_replace('set_ona_', '', $data); setStep($chat_id, 'ask_turmush', $temp);
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "💍 <b>11. Turmush o'rtog'ining ismi qanday?</b>\n<i>(Bor bo'lsa, 3 ta harf yozing. Yo'q bo'lsa o'tkazib yuboring)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_skip_cancel()]);
    }
    elseif (strpos($data, 'set_turmush_') === 0 || $data === 'skip_turmush') {
        $temp['turmush_ortogi_id'] = ($data === 'skip_turmush') ? null : (int)str_replace('set_turmush_', '', $data); setStep($chat_id, 'confirm', $temp);
        $msg = "📋 <b>TEKSHIRISH:</b>\n\n👤 <b>F.I.SH:</b> {$temp['familiya']} {$temp['ism']} ".($temp['otasining_ismi'] ?? '')."\n🚻 <b>Jins:</b> " . ($temp['jins'] == 'erkak' ? 'Erkak' : 'Ayol') . "\n📅 <b>Sana:</b> ".date('d.m.Y', strtotime($temp['sana']))."\n👨‍👦 <b>Ota:</b> ".getNameById($temp['ota_id'])."\n👩‍👦 <b>Ona:</b> ".getNameById($temp['ona_id'])."\n💍 <b>Jufti:</b> ".getNameById($temp['turmush_ortogi_id'])."\n\nMa'lumotlar to'g'rimi?";
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "Klaviatura yopildi 👇", 'reply_markup' => json_encode(['remove_keyboard' => true])]); sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => btn_confirm_person()]);
    }
    elseif ($data == 'save_person') {
        $i = addslashes($temp['ism']); $f = addslashes($temp['familiya']); $oi = addslashes($temp['otasining_ismi'] ?? '');
        $j = addslashes($temp['jins']); $s = addslashes($temp['sana']); 
        $t = addslashes($temp['telefon'] ?? ''); $k = addslashes($temp['kasb'] ?? ''); $p = addslashes($temp['foto'] ?? '');
        $ota = $temp['ota_id'] ?: "NULL"; $ona = $temp['ona_id'] ?: "NULL"; $tur = $temp['turmush_ortogi_id'] ?: "NULL";

        db_query("INSERT INTO shaxslar_kutilmoqda (added_by_tg_id, ism, familiya, otasining_ismi, jins, tugilgan_sana, telefon, kasbi, foto, ota_id, ona_id, turmush_ortogi_id, status) VALUES ($chat_id, '$i', '$f', '$oi', '$j', '$s', '$t', '$k', '$p', $ota, $ona, $tur, 'kutilmoqda')");
        $new_id = db_query("SELECT LAST_INSERT_ID() as id")->fetch_assoc()['id'];
        
        sendTelegram('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "✅ Adminga yuborildi!"]);
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "Yangi amalni tanlang:", 'reply_markup' => btn_main_menu($chat_id)]);
        sendTelegram('sendMessage', ['chat_id' => ADMIN_TG_ID, 'text' => "🆕 <b>Yangi shaxs:</b>\n$f $i $oi\nTasdiqlaysizmi?", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "✅ Tasdiqlash", 'callback_data' => "approve_$new_id"], ['text' => "❌ Rad etish", 'callback_data' => "reject_$new_id"]]]])]);
        setStep($chat_id, 'none', []);
    }
    exit;
}

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';
    
    $user_res = db_query("SELECT * FROM bot_users WHERE tg_id = $chat_id");
    if ($user_res && $user_res->num_rows > 0) {
        $user = $user_res->fetch_assoc();
        $status = $user['status']; $step = $user['step'];
        $temp = json_decode($user['temp_data'] ?? '{}', true) ?: [];
    } else {
        $status = ($chat_id == ADMIN_TG_ID) ? 'approved' : 'pending';
        db_query("INSERT INTO bot_users (tg_id, status, step, temp_data) VALUES ($chat_id, '$status', 'none', '{}')"); 
        if ($status == 'pending') {
            $name = addslashes($update['message']['from']['first_name'] ?? 'Foydalanuvchi');
            $admin_kb = json_encode(['inline_keyboard' => [[['text' => "✅ Ruxsat berish", 'callback_data' => "appruser_$chat_id"], ['text' => "❌ Rad etish", 'callback_data' => "rejuser_$chat_id"]]]]);
            sendTelegram('sendMessage', ['chat_id' => ADMIN_TG_ID, 'text' => "👤 <b>Yangi shaxs ruxsat so'ramoqda:</b>\n<a href='tg://user?id=$chat_id'>$name</a>", 'parse_mode' => 'HTML', 'reply_markup' => $admin_kb]);
            sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "⏳ <b>Kuting...</b> Ruxsat berilgandan so'ng foydalana olasiz.", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['remove_keyboard' => true])]);
            exit;
        }
        $step = 'none'; $temp = [];
    }

    if ($status == 'pending' && $chat_id != ADMIN_TG_ID) { sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "⏳ Iltimos, admin ruxsat berishini kuting."]); exit; }
    if ($status == 'rejected' && $chat_id != ADMIN_TG_ID) { sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Sizga huquq berilmagan."]); exit; }

    // ADMIN OGOHLANTIRISH YUBORISHI
    if ($step == 'admin_input_warn' && $chat_id == ADMIN_TG_ID) {
        $target = $temp['target_id'];
        sendTelegram('sendMessage', ['chat_id' => $target, 'text' => "⚠️ <b>ADMIN XABARI:</b>\n\n$text", 'parse_mode' => 'HTML']);
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Xabar ID: $target ga yuborildi.", 'reply_markup' => btn_main_menu($chat_id)]);
        setStep($chat_id, 'none', []);
        exit;
    }

    // ==========================================
    // PIN-KODNI O'ZGARTIRISH BUYRUG'I (Faqat Admin uchun)
    // ==========================================
    if (strpos($text, '/pin ') === 0 && $chat_id == ADMIN_TG_ID) {
        $new_pin = trim(str_replace('/pin ', '', $text));
        $new_pin = addslashes($new_pin);
        db_query("INSERT INTO sozlamalar (kalit, qiymat) VALUES ('sayt_pin', '$new_pin') ON DUPLICATE KEY UPDATE qiymat='$new_pin'");
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Sayt himoya PIN-kodi muvaffaqiyatli o'zgartirildi!\n\n🔑 **Yangi PIN-kod:** `$new_pin`", 'parse_mode' => 'Markdown']);
        exit;
    }

    // ==========================================
    // BACKUP BUYRUG'I (Tugma bosilganda yoki yozilganda)
    // ==========================================
    if (($text == '/backup' || strpos($text, 'Zaxira') !== false || strpos($text, 'Backup') !== false) && $chat_id == ADMIN_TG_ID) {
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "⏳ Shajara nusxalanmoqda (Zip va SQL)..."]);
        $protocol = (isset($_SERVER['HTTPS']) ? "https" : "http");
        $url = $protocol . "://" . $_SERVER['HTTP_HOST'] . str_replace('bot.php', 'backup.php', $_SERVER['REQUEST_URI']) . "?key=MAXFIY_KALIT_2026";
        @file_get_contents($url);
        exit;
    }

    $is_main_menu = false;
    if (
        strpos($text, 'Viktorina') !== false || 
        strpos($text, 'Barcha shaxs') !== false || 
        strpos($text, 'Statistika') !== false || 
        strpos($text, "Tug'ilgan kun") !== false || 
        strpos($text, 'Qidiruv') !== false || 
        strpos($text, 'Mening ariza') !== false || 
        strpos($text, 'Yangi shaxs') !== false || 
        strpos($text, 'Saytga') !== false || 
        strpos($text, 'Foydalanuvchilar (Admin)') !== false ||
        strpos($text, 'Zaxira') !== false ||
        strpos($text, 'Bekor qilish') !== false || 
        $text == '/start' || $text == '/quiz'
    ) {
        $is_main_menu = true;
    }

    if ($is_main_menu) {
        setStep($chat_id, 'none', []);
        $step = 'none';
    }

    if (strpos($text, '/m_') === 0) {
        $shaxs_id = (int)str_replace('/m_', '', $text);
        $s_data = db_query("SELECT * FROM shaxslar WHERE id = $shaxs_id")->fetch_assoc();
        if ($s_data && ($chat_id == ADMIN_TG_ID || $s_data['added_by_tg_id'] == $chat_id)) {
            $msg = "⚙️ <b>SHAXSNI BOSHQARISH</b>\n\n👤 <b>F.I.SH:</b> {$s_data['familiya']} {$s_data['ism']} {$s_data['otasining_ismi']}\n📅 <b>Tug'ilgan:</b> " . date('d.m.Y', strtotime($s_data['tugilgan_sana'])) . "\n\nNimani amalga oshiramiz?";
            $kb = json_encode(['inline_keyboard' => [
                [['text' => "✏️ Ismni tahrir", 'callback_data' => "edit_ism_".$shaxs_id], ['text' => "✏️ Familiyani tahrir", 'callback_data' => "edit_familiya_".$shaxs_id]],
                [['text' => "✏️ Sanani tahrir", 'callback_data' => "edit_tugilgan_sana_".$shaxs_id]],
                [['text' => "👨‍👦 Ota biriktirish", 'callback_data' => "asklink_ota_".$shaxs_id], ['text' => "👩‍👦 Ona biriktirish", 'callback_data' => "asklink_ona_".$shaxs_id]],
                [['text' => "💍 Juft biriktirish", 'callback_data' => "asklink_juft_".$shaxs_id]],
                [['text' => "🗑 Butunlay o'chirish", 'callback_data' => "del_main_".$shaxs_id]],
                [['text' => "🔙 Orqaga", 'callback_data' => "back_to_list"]]
            ]]);
            sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => $kb]);
        } else { sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Sizda bu shaxsni boshqarish huquqi yo'q."]); }
        exit;
    }

    if ($text == "/start" || strpos($text, 'Bekor qilish') !== false) {
        $pin = getSitePin();
        $welcome = "🌟 <b>«OILAMIZ SHAJARASIGA XUSH KELIBSIZ!»</b> 🌟\n\n";
        $welcome .= "📖 <b>BU BOT NIMA UCHUN?</b>\n";
        $welcome .= "<i>Ota-bobolarimiz va qarindoshlarimizning yagona raqamli shajara daraxtini yaratamiz!</i>\n\n";
        $welcome .= "🔐 <b>Maxfiy PIN-kod:</b> <code>$pin</code>\n";
        $welcome .= "<i>(Bu kod saytga kirish uchun kerak bo'ladi)</i>\n\n";
        $welcome .= "🛠 <b>QANDAY FOYDALANILADI?</b>\n";
        $welcome .= "1️⃣ <b>➕ Yangi shaxs qo'shish</b> — Oila a'zolaringizni birma-bir kiriting.\n";
        $welcome .= "2️⃣ Shajarani to'g'ri bog'lash uchun avval <b>eng katta ota-bobolarni</b> kiritish tavsiya etiladi.\n";
        $welcome .= "3️⃣ <b>📋 Barcha shaxslar</b> menyusi orqali o'zingiz kiritgan shaxslarni tahrirlashingiz, ota-onasini bog'lashingiz mumkin.\n\n";
        $welcome .= "👇 <b>Quyidagi menyulardan birini tanlang:</b>";
        
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => $welcome, 'parse_mode' => 'HTML', 'reply_markup' => btn_main_menu($chat_id)]);
        exit;
    }

    if ($step == 'none') {
        
        if (strpos($text, "Saytga o'tish") !== false) {
            $site_url = "https://vaccinal-subfoliate-elsa.ngrok-free.dev/shajara2/index.php"; 
            $pin = getSitePin();
            $msg = "🌐 *Shajara Sayti*\n\n🔐 Saytga kirish uchun PIN-kod: `$pin`\n\nQuyidagi tugmani bosish orqali saytga o'tishingiz mumkin:";
            $inline_kb = json_encode([
                'inline_keyboard' => [
                    [['text' => "🌍 Saytni ochish", 'url' => $site_url]]
                ]
            ]);
            sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'Markdown', 'reply_markup' => $inline_kb]);
            exit;
        }

        if (strpos($text, 'Viktorina') !== false || $text == "/quiz") {
            startQuiz($chat_id);
            exit;
        }

        // FOYDALANUVCHILAR (ADMIN) BOSILGANDA
        if (strpos($text, 'Foydalanuvchilar (Admin)') !== false && $chat_id == ADMIN_TG_ID) {
            $res = db_query("SELECT * FROM bot_users ORDER BY id DESC LIMIT 20");
            sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "👥 <b>Bot foydalanuvchilari ro'yxati:</b>", 'parse_mode' => 'HTML']);
            while($u = $res->fetch_assoc()) {
                $st = ($u['status'] == 'approved') ? "✅ Ruxsat berilgan" : (($u['status'] == 'rejected') ? "🚫 Ban qilingan" : "⏳ Kutilmoqda");
                $icon = ($u['status'] == 'approved') ? "✅" : (($u['status'] == 'rejected') ? "🚫" : "⏳");
                
                $user_info = sendTelegram('getChat', ['chat_id' => $u['tg_id']]);
                $username = isset($user_info['result']['username']) ? "@" . $user_info['result']['username'] : "Mavjud emas";
                $first_name = isset($user_info['result']['first_name']) ? $user_info['result']['first_name'] : "Mavjud emas";

                $msg_text = "$icon <b>Foydalanuvchi:</b> $first_name\n";
                $msg_text .= "👤 <b>Username:</b> $username\n";
                $msg_text .= "🆔 <b>ID:</b> <code>{$u['tg_id']}</code>\n";
                $msg_text .= "📊 <b>Holat:</b> $st\n";
                $msg_text .= "👣 <b>Qadam:</b> {$u['step']}";

                $kb = json_encode(['inline_keyboard' => [
                    [
                        ['text' => "⚠️ Ogohlantirish", 'callback_data' => "u_warn_".$u['tg_id']],
                        ['text' => "🚫 Ban / Ruxsat", 'callback_data' => "u_ban_".$u['tg_id']]
                    ],
                    [
                        ['text' => "💬 Xabar yozish", 'url' => "tg://user?id={$u['tg_id']}"]
                    ]
                ]]);
                
                sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => $msg_text, 'parse_mode' => 'HTML', 'reply_markup' => $kb]);
            }
            exit;
        }

        if (strpos($text, 'Barcha shaxs') !== false) {
            $sql = "SELECT s.id, s.ism, s.familiya, s.tugilgan_sana, s.added_by_tg_id, o.ota_id, o.ona_id, o.turmush_ortogi_id 
                    FROM shaxslar s LEFT JOIN oilaviy_bogliqlik o ON s.id=o.shaxs_id 
                    ORDER BY CASE WHEN s.tugilgan_sana IS NULL OR s.tugilgan_sana = '0000-00-00' THEN 1 ELSE 0 END ASC, s.tugilgan_sana ASC";
            $res = db_query($sql);
            
            $barcha = []; $children = []; $roots = []; $visited = [];
            while($r = $res->fetch_assoc()) {
                $barcha[$r['id']] = $r;
                if ($r['ota_id']) { $children[$r['ota_id']][] = $r['id']; }
                elseif ($r['ona_id']) { $children[$r['ona_id']][] = $r['id']; }
            }
            
            foreach($barcha as $id => $p) {
                if (empty($p['ota_id']) && empty($p['ona_id'])) {
                    $is_spouse = false;
                    foreach($barcha as $other) { 
                        if ($other['turmush_ortogi_id'] == $id && (!empty($other['ota_id']) || !empty($other['ona_id']))) { 
                            $is_spouse = true; break; 
                        } 
                    }
                    if (!$is_spouse) $roots[] = $id;
                }
            }
            
            $buildTreeText = function($id, $level) use (&$buildTreeText, &$barcha, &$children, &$visited, $chat_id) {
                if (isset($visited[$id])) return "";
                $visited[$id] = true;
                
                $p = $barcha[$id];
                $prefix = str_repeat("   ", $level);
                $icon = ($level > 0) ? "┣ 👤" : "👑";
                $yil = ($p['tugilgan_sana'] && $p['tugilgan_sana'] != '0000-00-00') ? date('Y', strtotime($p['tugilgan_sana'])) : '?';
                
                $t = "{$prefix}{$icon} <b>{$p['ism']} {$p['familiya']}</b> ({$yil})";
                if ($chat_id == ADMIN_TG_ID || $p['added_by_tg_id'] == $chat_id) { $t .= " 👉 /m_{$p['id']}"; }
                $t .= "\n";
                
                $spouse_id = $p['turmush_ortogi_id'];
                if ($spouse_id && isset($barcha[$spouse_id]) && !isset($visited[$spouse_id])) {
                    $visited[$spouse_id] = true; 
                    $sp = $barcha[$spouse_id];
                    $syil = ($sp['tugilgan_sana'] && $sp['tugilgan_sana'] != '0000-00-00') ? date('Y', strtotime($sp['tugilgan_sana'])) : '?';
                    $t .= "{$prefix}💍 <i>{$sp['ism']} {$sp['familiya']}</i> ({$syil})";
                    if ($chat_id == ADMIN_TG_ID || $sp['added_by_tg_id'] == $chat_id) { $t .= " 👉 /m_{$sp['id']}"; }
                    $t .= "\n";
                    if (isset($children[$spouse_id])) { 
                        foreach($children[$spouse_id] as $ch_id) { $t .= $buildTreeText($ch_id, $level + 1); } 
                    }
                }
                
                if (isset($children[$id])) { 
                    foreach($children[$id] as $ch_id) { $t .= $buildTreeText($ch_id, $level + 1); } 
                }
                return $t;
            };
            
            $final_text = "📋 <b>UMUMIY SHAJARA:</b>\n<i>Tahrirlash uchun ism yonidagi ko'k yozuvni bosing</i>\n\n";
            foreach($roots as $rid) { $final_text .= $buildTreeText($rid, 0); }
            foreach($barcha as $id => $p) { if(!isset($visited[$id])) $final_text .= $buildTreeText($id, 0); }
            
            $lines = explode("\n", $final_text); $msg = "";
            foreach($lines as $line) {
                if(mb_strlen($msg . $line) > 3800) { sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'HTML']); $msg = ""; }
                $msg .= $line . "\n";
            }
            if($msg !== "") sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'HTML']);
            exit;
        }

        if (strpos($text, 'Statistika') !== false) {
            $jami = db_query("SELECT COUNT(*) as c FROM shaxslar")->fetch_assoc()['c'];
            if ($jami > 0) {
                $erkak = db_query("SELECT COUNT(*) as c FROM shaxslar WHERE jins='erkak'")->fetch_assoc()['c'];
                $ayol = db_query("SELECT COUNT(*) as c FROM shaxslar WHERE jins='ayol'")->fetch_assoc()['c'];
                $kutilmoqda = db_query("SELECT COUNT(*) as c FROM shaxslar_kutilmoqda WHERE status='kutilmoqda'")->fetch_assoc()['c'];
                $vafot_etgan = db_query("SELECT COUNT(*) as c FROM shaxslar WHERE vafot_sana IS NOT NULL AND vafot_sana != '0000-00-00'")->fetch_assoc()['c'];
                $tirik = $jami - $vafot_etgan;
                $oy_row = db_query("SELECT AVG(TIMESTAMPDIFF(YEAR, tugilgan_sana, CURDATE())) as avg_yosh FROM shaxslar WHERE tugilgan_sana IS NOT NULL AND tugilgan_sana != '0000-00-00' AND (vafot_sana IS NULL OR vafot_sana = '0000-00-00')")->fetch_assoc();
                $ortacha_yosh = round($oy_row['avg_yosh'] ?? 0);
                $res_av = db_query("SELECT s.id, o.ota_id FROM shaxslar s LEFT JOIN oilaviy_bogliqlik o ON s.id=o.shaxs_id");
                $barcha_ota = []; while($r = $res_av->fetch_assoc()) { $barcha_ota[$r['id']] = $r['ota_id']; }
                $max_depth = 1;
                foreach ($barcha_ota as $id => $ota_id) {
                    $depth = 1; $curr = $id;
                    while (!empty($barcha_ota[$curr])) { $curr = $barcha_ota[$curr]; $depth++; if ($depth > 20) break; }
                    if ($depth > $max_depth) $max_depth = $depth;
                }
                $e_foiz = round(($erkak / $jami) * 100); $a_foiz = round(($ayol / $jami) * 100);

                $msg = "📊 <b>SHAJARA STATISTIKASI</b>\n\n👥 <b>Umumiy a'zolar:</b> $jami ta\n🌳 <b>Avlodlar zanjiri:</b> $max_depth ta avlod\n🧬 <b>O'rtacha yosh:</b> $ortacha_yosh yosh\n\n🌟 <b>Tiriklar:</b> $tirik ta\n🥀 <b>Vafot etganlar:</b> $vafot_etgan ta\n\n👨 <b>Erkaklar:</b> $erkak ta ($e_foiz%)\n" . getEmojiProgress($e_foiz) . "\n\n👩 <b>Ayollar:</b> $ayol ta ($a_foiz%)\n" . getEmojiProgress($a_foiz) . "\n\n";
                if ($chat_id == ADMIN_TG_ID) $msg .= "⏳ Kutilayotgan arizalar: <b>$kutilmoqda ta</b>";
            } else { $msg = "Baza hozircha bo'sh."; }
            sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'HTML']);
            exit;
        }

        if (strpos($text, "Tug'ilgan kun") !== false) {
            $oylar_uz = [1=>'Yanvar', 2=>'Fevral', 3=>'Mart', 4=>'Aprel', 5=>'May', 6=>'Iyun', 7=>'Iyul', 8=>'Avgust', 9=>'Sentabr', 10=>'Oktabr', 11=>'Noyabr', 12=>'Dekabr'];
            $oylar_qisqa = [1=>'Yan', 2=>'Fev', 3=>'Mar', 4=>'Apr', 5=>'May', 6=>'Iyun', 7=>'Iyul', 8=>'Avg', 9=>'Sen', 10=>'Okt', 11=>'Noy', 12=>'Dek'];

            $joriy_oy = (int)date('m');
            $bugun_kun = (int)date('d');
            $joriy_yil = (int)date('Y');
            
            $res = db_query("SELECT ism, familiya, tugilgan_sana FROM shaxslar WHERE MONTH(tugilgan_sana) = $joriy_oy ORDER BY DAY(tugilgan_sana) ASC");
            
            $msg = "🎂 <b>{$oylar_uz[$joriy_oy]} oyida tug'ilgan kunlar:</b>\n\n";
            
            if ($res && $res->num_rows > 0) {
                while($r = $res->fetch_assoc()) {
                    $d = new DateTime($r['tugilgan_sana']);
                    $kun = (int)$d->format('d');
                    $oy_idx = (int)$d->format('m');
                    $t_yil = (int)$d->format('Y');
                    $yosh = $joriy_yil - $t_yil;
                    
                    $sana_str = ($kun < 10 ? "0$kun" : $kun) . "-" . $oylar_qisqa[$oy_idx];
                    
                    if ($kun < $bugun_kun) {
                        $holat = "($yosh yoshga to'ldi)";
                    } elseif ($kun == $bugun_kun) {
                        $holat = "($yosh yoshga to'lmoqda - BUGUN! 🎉)";
                    } else {
                        $holat = "($yosh yoshga to'ladi)";
                    }
                    
                    $msg .= "🎈 <b>{$r['ism']} {$r['familiya']}</b> — $sana_str $holat\n";
                }
            } else {
                $msg .= "Bu oyda hech kimda tug'ilgan kunlar yo'q.";
            }
            sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'HTML']);
            exit;
        }

        if (strpos($text, "Qidiruv") !== false) {
            setStep($chat_id, 'search_person_main', []); 
            sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "🔍 <b>Qidirmoqchi bo'lgan shaxsning ismini kiriting:</b>\n<i>(Ismidan yoki familiyasidan 3 ta harf yozib yuboring)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_cancel()]); 
            exit;
        }

        if (strpos($text, "Mening ariza") !== false) {
            $res = db_query("SELECT * FROM shaxslar_kutilmoqda WHERE added_by_tg_id = $chat_id AND status = 'kutilmoqda'");
            if ($res->num_rows == 0) { sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "Sizda kutilayotgan arizalar yo'q."]); }
            while($a = $res->fetch_assoc()) {
                $kb = json_encode(['inline_keyboard' => [[['text' => "✏️ Tahrirlash", 'callback_data' => "edit_ariza_".$a['id']], ['text' => "🗑 O'chirish", 'callback_data' => "del_ariza_".$a['id']]]]]);
                sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "⏳ Kutilmoqda: {$a['ism']} {$a['familiya']}", 'reply_markup' => $kb]);
            }
            exit;
        }
    }

    if ($step == 'search_person_main') {
        $s = addslashes($text);
        $res = db_query("SELECT * FROM shaxslar WHERE ism LIKE '%$s%' OR familiya LIKE '%$s%' LIMIT 15");
        if ($res && $res->num_rows > 0) {
            sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "🔍 <b>Qidiruv natijalari:</b>", 'parse_mode' => 'HTML', 'reply_markup' => btn_main_menu($chat_id)]);
            while($r = $res->fetch_assoc()) {
                $msg = "👤 <b>{$r['ism']} {$r['familiya']}</b> (" . date('Y', strtotime($r['tugilgan_sana'])) . ")";
                $kb = ($chat_id == ADMIN_TG_ID) ? json_encode(['inline_keyboard' => [[['text' => "⚙️ Boshqarish", 'callback_data' => "manage_".$r['id']]]]]) : "";
                sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => $kb]);
            }
        } else { sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Topilmadi.", 'reply_markup' => btn_main_menu($chat_id)]); }
        setStep($chat_id, 'none', []);
        exit;
    }

    if (strpos($text, "Yangi shaxs") !== false) {
        setStep($chat_id, 'add_ism', []); 
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "✍️ <b>1. Ismni kiriting:</b>\n<i>(Masalan: Nurislom)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_cancel()]);
    }
    elseif ($step == 'add_ism') {
        $temp['ism'] = $text; setStep($chat_id, 'add_familiya', $temp); 
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "✍️ <b>2. Familiyani kiriting:</b>\n<i>(Masalan: Matayev)</i>", 'parse_mode' => 'HTML']);
    }
    elseif ($step == 'add_familiya') {
        $temp['familiya'] = $text; setStep($chat_id, 'add_ota_ismi', $temp); 
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "✍️ <b>3. Otasining ismi (Sharifi):</b>\n<i>(Masalan: Elyorovich)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_skip_cancel()]);
    }
    elseif ($step == 'add_ota_ismi') {
        $temp['otasining_ismi'] = (strpos($text, 'tkazib') !== false) ? "" : $text; setStep($chat_id, 'add_jins', $temp); 
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "🚻 <b>4. Jinsini tanlang:</b>", 'parse_mode' => 'HTML', 'reply_markup' => btn_gender()]);
    }
    elseif ($step == 'add_jins') {
        $temp['jins'] = (strpos($text, 'Erkak') !== false) ? 'erkak' : 'ayol'; setStep($chat_id, 'add_sana', $temp); 
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "📅 <b>5. Tug'ilgan sana (kk.oo.yyyy):</b>\n<i>(Masalan: 01.03.2013)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_cancel()]);
    }
    elseif ($step == 'add_sana') {
        if (preg_match('/^(0[1-9]|[12][0-9]|3[01])\.(0[1-9]|1[012])\.(19|20)\d\d$/', $text)) {
            $p = explode('.', $text); $db_sana = "$p[2]-$p[1]-$p[0]";
            $i = addslashes($temp['ism']); $f = addslashes($temp['familiya']);
            if (db_query("SELECT id FROM shaxslar WHERE ism='$i' AND familiya='$f' AND tugilgan_sana='$db_sana'")->num_rows > 0) {
                sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "⚠️ Bu inson shajarada bor!", 'reply_markup' => btn_main_menu($chat_id)]); setStep($chat_id, 'none', []);
            } else {
                $temp['sana'] = $db_sana; setStep($chat_id, 'add_tel', $temp); 
                sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "📞 <b>6. Telefon raqami:</b>\n<i>(Masalan: +998901234567)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_skip_cancel()]);
            }
        } else { sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Xato format! Namunadagidek yozing: 01.03.2013"]); }
    }
    elseif ($step == 'add_tel') {
        $temp['telefon'] = (strpos($text, 'tkazib') !== false) ? "" : $text; setStep($chat_id, 'add_kasb', $temp); 
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "💼 <b>7. Kasbi yoki Mutaxassisligi:</b>\n<i>(Masalan: Nafaqada, o'qituvchi, talaba, uy bekasi va h.k.)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_skip_cancel()]);
    }
    elseif ($step == 'add_kasb') {
        $temp['kasb'] = (strpos($text, 'tkazib') !== false) ? "" : $text; setStep($chat_id, 'add_foto', $temp); 
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "📸 <b>8. Rasmini yuboring:</b>", 'parse_mode' => 'HTML', 'reply_markup' => btn_skip_cancel()]);
    }
    
    elseif ($step == 'add_foto') {
        $temp['foto'] = isset($update['message']['photo']) ? end($update['message']['photo'])['file_id'] : ""; setStep($chat_id, 'ask_ota', $temp); 
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "👨‍👦 <b>9. Otasining ismi qanday?</b>\n<i>(Baza ichidan qidirish uchun 3 ta harf yozib yuboring)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_skip_cancel()]);
    }
    
    // Ota/Ona/Juft biriktirishdagi qidiruv va skip xatoligi tuzatilgan qism
    $is_ask = in_array($step, ['ask_ota', 'ask_ona', 'ask_turmush']);
    $is_dolink = (strpos($step, 'dolink_') === 0);

    if ($is_ask || $is_dolink) {
        $tur = '';
        $target_shaxs_id = 0;
        
        if ($is_ask) {
            $tur = str_replace('ask_', '', $step);
        } else {
            $parts = explode('_', $step);
            $tur = $parts[1]; // ota, ona, turmush
            $target_shaxs_id = $parts[2];
        }

        if (strpos($text, 'tkazib') !== false && $is_ask) {
            if ($step == 'ask_ota') { 
                $temp['ota_id'] = null; setStep($chat_id, 'ask_ona', $temp); 
                sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "👩‍👦 <b>10. Onasining ismi qanday?</b>\n<i>(Baza ichidan qidirish uchun 3 ta harf yozib yuboring)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_skip_cancel()]); 
            }
            elseif ($step == 'ask_ona') { 
                $temp['ona_id'] = null; setStep($chat_id, 'ask_turmush', $temp); 
                sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "💍 <b>11. Turmush o'rtog'ining ismi qanday?</b>\n<i>(Bor bo'lsa, 3 ta harf yozing. Yo'q bo'lsa o'tkazib yuboring)</i>", 'parse_mode' => 'HTML', 'reply_markup' => btn_skip_cancel()]); 
            }
            elseif ($step == 'ask_turmush') {
                $temp['turmush_ortogi_id'] = null; setStep($chat_id, 'confirm', $temp);
                $msg = "📋 <b>TEKSHIRISH:</b>\n\n👤 <b>F.I.SH:</b> {$temp['familiya']} {$temp['ism']} ".($temp['otasining_ismi'] ?? '')."\n🚻 <b>Jins:</b> " . ($temp['jins'] == 'erkak' ? 'Erkak' : 'Ayol') . "\n📅 <b>Sana:</b> ".date('d.m.Y', strtotime($temp['sana']))."\n👨‍👦 <b>Ota:</b> ".getNameById($temp['ota_id'])."\n👩‍👦 <b>Ona:</b> ".getNameById($temp['ona_id'])."\n💍 <b>Jufti:</b> ".getNameById($temp['turmush_ortogi_id'])."\n\nMa'lumotlar to'g'rimi?";
                sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "Klaviatura yopildi 👇", 'reply_markup' => json_encode(['remove_keyboard' => true])]); 
                sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'HTML', 'reply_markup' => btn_confirm_person()]);
            }
            exit;
        }

        $search = addslashes($text);
        $filter = ($tur == 'ota') ? "AND jins='erkak'" : (($tur == 'ona') ? "AND jins='ayol'" : "");
        $res = db_query("SELECT id, ism, familiya, tugilgan_sana FROM shaxslar WHERE (ism LIKE '%$search%' OR familiya LIKE '%$search%') $filter LIMIT 8");
        $btns = [];
        
        if ($res && $res->num_rows > 0) {
            while($r = $res->fetch_assoc()) {
                $y = $r['tugilgan_sana'] ? date('Y', strtotime($r['tugilgan_sana'])) : '?';
                if ($is_ask) {
                    $cb = "set_{$tur}_".$r['id'];
                } else {
                    $cb = "setlink_{$tur}_{$target_shaxs_id}_".$r['id'];
                }
                $btns[] = [['text' => "👤 {$r['ism']} {$r['familiya']} ($y)", 'callback_data' => $cb]];
            }
        }
        
        if ($is_ask) {
            $btns[] = [['text' => "⏭ Topilmadi (O'tkazish)", 'callback_data' => "skip_{$tur}"]];
        } else {
            $btns[] = [['text' => "❌ Bekor qilish", 'callback_data' => "manage_{$target_shaxs_id}"]];
        }
        
        sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "🔍 Qidiruv natijalari:", 'reply_markup' => json_encode(['inline_keyboard' => $btns])]);
        exit;
    }
}
?>
