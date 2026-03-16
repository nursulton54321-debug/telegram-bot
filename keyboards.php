<?php
// =============================================
// FILE: bot/keyboards.php
// MAQSAD: Menyular ro'yxati va Viktorina tugmasi
// =============================================

function btn_main_menu($tg_id) {
    $web_app_url = "https://vaccinal-subfoliate-elsa.ngrok-free.dev/shajara2/bot_tree.php";
    
    $keyboard = [
        [['text' => "➕ Yangi shaxs qo'shish"]],
        [['text' => "🔍 Qidiruv"], ['text' => "📋 Barcha shaxslar"]],
        [['text' => "🎮 Oila Viktorinasi"], ['text' => "🎂 Tug'ilgan kunlar"]],
        [['text' => "🌳 Shajara daraxti", 'web_app' => ['url' => $web_app_url]], ['text' => "📊 Statistika"]],
        [['text' => "📂 Mening arizalarim"], ['text' => "🌐 Saytga o'tish"]]
    ];

    if ($tg_id == '139619338') {
        $keyboard[] = [['text' => "📥 Arizalar (Admin)"], ['text' => "👥 Foydalanuvchilar (Admin)"]];
    }

    return json_encode([
        'keyboard' => $keyboard,
        'resize_keyboard' => true
    ]);
}

function btn_gender() {
    return json_encode(['keyboard' => [[['text' => "👨 Erkak"], ['text' => "👩 Ayol"]], [['text' => "❌ Bekor qilish"]]], 'resize_keyboard' => true]);
}

function btn_cancel() {
    return json_encode(['keyboard' => [[['text' => "❌ Bekor qilish"]]], 'resize_keyboard' => true]);
}

function btn_skip_cancel() {
    return json_encode(['keyboard' => [[['text' => "⏭ O'tkazib yuborish"]], [['text' => "❌ Bekor qilish"]]], 'resize_keyboard' => true]);
}

function btn_confirm_person() {
    return json_encode(['inline_keyboard' => [[['text' => "✅ Adminga yuborish", 'callback_data' => "save_person"], ['text' => "❌ Bekor qilish", 'callback_data' => "reject_person"]]]]);
}
?>