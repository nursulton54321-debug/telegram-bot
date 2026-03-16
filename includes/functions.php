<?php
// =============================================
// FILE: includes/functions.php
// MAQSAD: Barcha umumiy funksiyalar
// =============================================

// config.php ni ulash
require_once __DIR__ . '/config.php';

// =============================================
// 1. BAZA BILAN ISHLASH FUNKSIYALARI
// =============================================

/**
 * Bazaga ulanish olish
 * @return mysqli
 */
function db_connect() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Bazaga ulanishda xatolik: " . $conn->connect_error);
            die("Ma'lumotlar bazasiga ulanishda xatolik yuz berdi.");
        }
        
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

/**
 * SQL so'rovni xavfsiz bajarish
 * @param string $sql
 * @return mixed
 */
function db_query($sql) {
    $conn = db_connect();
    $result = $conn->query($sql);
    
    if (!$result) {
        error_log("SQL xatolik: " . $conn->error . " | SQL: " . $sql);
        return false;
    }
    
    return $result;
}

/**
 * Ma'lumotlarni tozalash (SQL injection dan himoya)
 * @param mixed $data
 * @return string
 */
function sanitize($data) {
    $conn = db_connect();
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize($value);
        }
        return $data;
    }
    
    return $conn->real_escape_string(trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8')));
}

// =============================================
// 2. SHAXSLAR BILAN ISHLASH
// =============================================

/**
 * Yangi shaxs qo'shish
 * @param array $data
 * @return int|false
 */
function shaxs_qoshish($data) {
    $conn = db_connect();
    
    // Ma'lumotlarni tozalash
    $ism = sanitize($data['ism']);
    $familiya = sanitize($data['familiya']);
    $otasining_ismi = isset($data['otasining_ismi']) ? sanitize($data['otasining_ismi']) : '';
    $jins = sanitize($data['jins']);
    $tugilgan_sana = isset($data['tugilgan_sana']) ? sanitize($data['tugilgan_sana']) : null;
    $vafot_sana = isset($data['vafot_sana']) ? sanitize($data['vafot_sana']) : null;
    $tirik = isset($data['tirik']) ? (int)$data['tirik'] : 1;
    $tugilgan_joy = isset($data['tugilgan_joy']) ? sanitize($data['tugilgan_joy']) : '';
    $kasbi = isset($data['kasbi']) ? sanitize($data['kasbi']) : '';
    $telefon = isset($data['telefon']) ? sanitize($data['telefon']) : '';
    $foto = isset($data['foto']) ? sanitize($data['foto']) : ''; // MUHIM: foto qo'shildi
    
    $sql = "INSERT INTO shaxslar (ism, familiya, otasining_ismi, jins, tugilgan_sana, vafot_sana, tirik, tugilgan_joy, kasbi, telefon, foto) 
            VALUES ('$ism', '$familiya', '$otasining_ismi', '$jins', " . 
            ($tugilgan_sana ? "'$tugilgan_sana'" : "NULL") . ", " .
            ($vafot_sana ? "'$vafot_sana'" : "NULL") . ", 
            $tirik, '$tugilgan_joy', '$kasbi', '$telefon', '$foto')";
    
    if (db_query($sql)) {
        return $conn->insert_id;
    }
    
    return false;
}

/**
 * Shaxs ma'lumotlarini olish
 * @param int $id
 * @return array|null
 */
function shaxs_olish($id) {
    $id = (int)$id;
    $sql = "SELECT * FROM shaxslar WHERE id = $id";
    $result = db_query($sql);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Barcha shaxslar ro'yxati
 * @param string $order
 * @return array
 */
function shaxslar_roixati($order = 'familiya, ism') {
    $sql = "SELECT * FROM shaxslar ORDER BY $order";
    $result = db_query($sql);
    
    $shaxslar = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $shaxslar[] = $row;
        }
    }
    
    return $shaxslar;
}

/**
 * Shaxs ma'lumotlarini yangilash
 * @param int $id
 * @param array $data
 * @return bool
 */
function shaxs_yangilash($id, $data) {
    $id = (int)$id;
    
    $fields = [];
    foreach ($data as $key => $value) {
        if ($key != 'id') {
            $value = sanitize($value);
            $fields[] = "$key = '$value'";
        }
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $sql = "UPDATE shaxslar SET " . implode(', ', $fields) . " WHERE id = $id";
    return db_query($sql) ? true : false;
}

/**
 * Shaxsni o'chirish
 * @param int $id
 * @return bool
 */
function shaxs_ochirish($id) {
    $id = (int)$id;
    $sql = "DELETE FROM shaxslar WHERE id = $id";
    return db_query($sql) ? true : false;
}

// =============================================
// 3. QIDIRUV FUNKSIYALARI
// =============================================

/**
 * Shaxslarni qidirish
 * @param string $qidiruv_sozi
 * @return array
 */
function qidiruv($qidiruv_sozi) {
    $qidiruv_sozi = sanitize($qidiruv_sozi);
    
    $sql = "SELECT * FROM shaxslar 
            WHERE ism LIKE '%$qidiruv_sozi%' 
               OR familiya LIKE '%$qidiruv_sozi%' 
               OR otasining_ismi LIKE '%$qidiruv_sozi%'
               OR kasbi LIKE '%$qidiruv_sozi%'
            ORDER BY familiya, ism";
    
    $result = db_query($sql);
    
    $shaxslar = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $shaxslar[] = $row;
        }
    }
    
    return $shaxslar;
}

// =============================================
// 4. YORDAMCHI FUNKSIYALAR
// =============================================

/**
 * Yoshni hisoblash
 * @param string $tugilgan_sana
 * @param string $sana (ixtiyoriy, agar bo'lmasa bugun)
 * @return int|string
 */
function yosh_hisoblash($tugilgan_sana, $sana = null) {
    if (!$tugilgan_sana || $tugilgan_sana == '0000-00-00') {
        return "Noma'lum";
    }
    
    $tugilgan = new DateTime($tugilgan_sana);
    $bugun = $sana ? new DateTime($sana) : new DateTime();
    $farq = $bugun->diff($tugilgan);
    
    return $farq->y;
}

/**
 * Sana formatini o'zgartirish
 * @param string $sana
 * @param string $format
 * @return string
 */
function sana_format($sana, $format = 'd.m.Y') {
    if (!$sana || $sana == '0000-00-00') {
        return '';
    }
    
    $date = new DateTime($sana);
    return $date->format($format);
}

/**
 * Jinsni o'zbek tilida qaytarish
 * @param string $jins
 * @return string
 */
function jins_uz($jins) {
    return $jins == 'erkak' ? 'Erkak' : 'Ayol';
}

/**
 * Xatoliklarni log faylga yozish
 * @param string $xato
 * @param array $malumot
 */
function xato_log($xato, $malumot = []) {
    $log_fayl = __DIR__ . '/../logs/xatolik.log';
    $papka = dirname($log_fayl);
    
    if (!is_dir($papka)) {
        mkdir($papka, 0777, true);
    }
    
    $vaqt = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Noma\'lum';
    $url = $_SERVER['REQUEST_URI'] ?? 'Noma\'lum';
    
    $log = "[$vaqt] IP: $ip | URL: $url | Xatolik: $xato";
    
    if (!empty($malumot)) {
        $log .= " | Ma'lumot: " . json_encode($malumot, JSON_UNESCAPED_UNICODE);
    }
    
    file_put_contents($log_fayl, $log . PHP_EOL, FILE_APPEND);
}

/**
 * JSON javob qaytarish
 * @param mixed $data
 * @param int $status
 */
function json_chiqar($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Redirect qilish
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

// =============================================
// 5. VALIDATSIYA FUNKSIYALARI
// =============================================

/**
 * Telefon raqamni tekshirish
 * @param string $telefon
 * @return bool
 */
function telefon_tekshir($telefon) {
    // O'zbekiston telefon raqamlari uchun: +998 XX XXX XX XX
    return preg_match('/^\+998[0-9]{9}$/', $telefon);
}

/**
 * Email manzilni tekshirish
 * @param string $email
 * @return bool
 */
function email_tekshir($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanani tekshirish
 * @param string $sana
 * @return bool
 */
function sana_tekshir($sana) {
    if (empty($sana)) return true;
    
    $d = DateTime::createFromFormat('Y-m-d', $sana);
    return $d && $d->format('Y-m-d') === $sana;
}

// =============================================
// 6. RASMLARNI WEBP FORMATIDA SIQISH FUNKSIYASI (YANGI)
// =============================================

/**
 * Rasmni qabul qilib, WebP qilib siqib saqlaydi
 * @param array $file (Masalan: $_FILES['foto'])
 * @param string $upload_dir (Masalan: __DIR__ . '/../assets/uploads')
 * @param int $sifat (Sifat darajasi 0-100)
 * @return string|false (Yangi fayl nomi yoki xato bo'lsa false)
 */
function rasm_yuklash_webp($file, $upload_dir, $sifat = 80) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }

    $info = @getimagesize($file['tmp_name']);
    if (!$info) return false;

    $mime = $info['mime'];
    $image = null;

    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($file['tmp_name']);
            if ($image) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($file['tmp_name']);
            if ($image) imagepalettetotruecolor($image);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($file['tmp_name']);
            break;
        default:
            return false;
    }

    if (!$image) return false;

    $yangi_nom = 'foto_' . time() . '_' . rand(1000, 9999) . '.webp';
    $manzil = rtrim($upload_dir, '/') . '/' . $yangi_nom;

    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $saqlandi = imagewebp($image, $manzil, $sifat);
    imagedestroy($image);

    if ($saqlandi) {
        return $yangi_nom;
    }
    
    return false;
}

?>
