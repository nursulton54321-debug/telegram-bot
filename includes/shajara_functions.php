<?php
// =============================================
// FILE: includes/shajara_functions.php
// MAQSAD: Shajara daraxti, qarindoshlik, statistika
// TUZATILDI:
//   - shajara_json() turmush_ortogi_id ni ham qaytaradi
//   - turmush_ortogi_juftliklari() funksiyasi qo'shildi
//   - daraxtni_json_ga_tayyorla() barcha maydonlarni to'liq qaytaradi
//   - Cheksiz rekursiyadan himoya ($visited massiv)
// =============================================

require_once __DIR__ . '/functions.php';

// =============================================
// 1. SHAJARA DARAXTINI YARATISH
// =============================================

/**
 * Shajara daraxtini rekursiv olish
 * @param int   $shaxs_id
 * @param int   $chuqurlik  nechi avlodgacha
 * @param array $visited    cheksiz rekursiyadan himoya
 */
function shajara_oying($shaxs_id, $chuqurlik = 5, $visited = []) {
    $shaxs_id = (int)$shaxs_id;

    if (in_array($shaxs_id, $visited)) return [];
    $visited[] = $shaxs_id;

    $shaxs = shaxs_olish($shaxs_id);
    if (!$shaxs) return [];

    $daraxt = [
        'id'                => (int)$shaxs['id'],
        'ism'               => $shaxs['ism']               ?? '',
        'familiya'          => $shaxs['familiya']          ?? '',
        'otasining_ismi'    => $shaxs['otasining_ismi']    ?? '',
        'jins'              => $shaxs['jins']              ?? 'erkak',
        'tugilgan_sana'     => $shaxs['tugilgan_sana']     ?? null,
        'tirik'             => $shaxs['tirik']             ?? 1,
        'foto'              => $shaxs['foto']              ?? null,
        'kasbi'             => $shaxs['kasbi']             ?? '',
        'telefon'           => $shaxs['telefon']           ?? '',
        'izoh'              => $shaxs['izoh']              ?? '',
        'daraja'            => 0,
        'otasi'             => null,
        'onasi'             => null,
        'turmush_ortogi'    => null,
        'turmush_ortogi_id' => null,
        'farzandlari'       => [],
    ];

    if ($chuqurlik > 0) {
        $ota_ona = ota_ona_olish($shaxs_id);

        if (!empty($ota_ona['ota_id'])) {
            $ota = shajara_oying((int)$ota_ona['ota_id'], $chuqurlik - 1, $visited);
            if ($ota) { $ota['daraja'] = -1; $daraxt['otasi'] = $ota; }
        }

        if (!empty($ota_ona['ona_id'])) {
            $ona = shajara_oying((int)$ota_ona['ona_id'], $chuqurlik - 1, $visited);
            if ($ona) { $ona['daraja'] = -1; $daraxt['onasi'] = $ona; }
        }

        $to_id = turmush_ortogi_olish($shaxs_id);
        if ($to_id) {
            $to = shaxs_olish($to_id);
            if ($to) {
                $daraxt['turmush_ortogi']    = $to;
                $daraxt['turmush_ortogi_id'] = (int)$to_id;
            }
        }

        $farzandlar = farzandlar_olish($shaxs_id);
        foreach ($farzandlar as $f) {
            $fd = shajara_oying((int)$f['id'], $chuqurlik - 1, $visited);
            if ($fd) { $fd['daraja'] = 1; $daraxt['farzandlari'][] = $fd; }
        }
    }

    return $daraxt;
}

/**
 * Shajara daraxtini JSON formatda olish (JavaScript uchun)
 */
function shajara_json($shaxs_id, $chuqurlik = 5) {
    $daraxt = shajara_oying((int)$shaxs_id, $chuqurlik, []);
    if (empty($daraxt)) return null;
    return daraxtni_json_ga_tayyorla($daraxt);
}

/**
 * Daraxtni D3.js uchun JSON formatiga tayyorlash
 * Barcha maydonlar ikki xil nom bilan (index.php va eski kod uchun)
 */
function daraxtni_json_ga_tayyorla($daraxt) {
    if (empty($daraxt)) return null;

    $node = [
        'id'                 => (int)($daraxt['id'] ?? 0),
        // Ism
        'name'               => trim(($daraxt['ism'] ?? '') . ' ' . ($daraxt['familiya'] ?? '')),
        'ism'                => $daraxt['ism']            ?? '',
        'familiya'           => $daraxt['familiya']       ?? '',
        'otasining_ismi'     => $daraxt['otasining_ismi'] ?? '',
        // Jins
        'gender'             => $daraxt['jins']           ?? 'erkak',
        'jins'               => $daraxt['jins']           ?? 'erkak',
        // Sana
        'dob'                => $daraxt['tugilgan_sana']  ?? null,
        'tugilgan_sana'      => $daraxt['tugilgan_sana']  ?? null,
        // Tirik
        'alive'              => (bool)($daraxt['tirik']   ?? true),
        'tirik'              => (int)($daraxt['tirik']    ?? 1),
        // Foto
        'photo'              => $daraxt['foto']           ?? null,
        'foto'               => $daraxt['foto']           ?? null,
        // Qo'shimcha
        'kasbi'              => $daraxt['kasbi']          ?? '',
        'telefon'            => $daraxt['telefon']        ?? '',
        'izoh'               => $daraxt['izoh']           ?? '',
        'level'              => $daraxt['daraja']         ?? 0,
        // Turmush o'rtog'i
        'turmush_ortogi_id'  => isset($daraxt['turmush_ortogi_id']) && $daraxt['turmush_ortogi_id']
                                    ? (int)$daraxt['turmush_ortogi_id'] : null,
        'spouse_id'          => isset($daraxt['turmush_ortogi_id']) && $daraxt['turmush_ortogi_id']
                                    ? (int)$daraxt['turmush_ortogi_id'] : null,
        // Turmush o'rtog'i ismi (modal uchun)
        'turmush_ortogi_ism' => '',
        // Farzandlar
        'children'           => [],
        'farzandlar'         => [],
    ];

    // Turmush o'rtog'i ismi
    if (!empty($daraxt['turmush_ortogi'])) {
        $to = $daraxt['turmush_ortogi'];
        $node['turmush_ortogi_ism'] = trim(($to['ism'] ?? '') . ' ' . ($to['familiya'] ?? ''));
    }

    // Farzandlar
    if (!empty($daraxt['farzandlari'])) {
        foreach ($daraxt['farzandlari'] as $farzand) {
            $f = daraxtni_json_ga_tayyorla($farzand);
            if ($f) {
                $node['children'][]   = $f;
                $node['farzandlar'][] = $f;
            }
        }
    }

    // Ota-ona (parents) — kerak bo'lsa
    if (!empty($daraxt['otasi']) || !empty($daraxt['onasi'])) {
        $node['parents'] = [
            'father' => !empty($daraxt['otasi']) ? daraxtni_json_ga_tayyorla($daraxt['otasi']) : null,
            'mother' => !empty($daraxt['onasi']) ? daraxtni_json_ga_tayyorla($daraxt['onasi']) : null,
        ];
    }

    return $node;
}

// =============================================
// 2. OTA-ONA VA FARZANDLAR
// =============================================

function ota_ona_olish($shaxs_id) {
    $shaxs_id = (int)$shaxs_id;
    $result   = db_query("SELECT ota_id, ona_id FROM oilaviy_bogliqlik WHERE shaxs_id = $shaxs_id LIMIT 1");
    if ($result && $result->num_rows > 0) return $result->fetch_assoc();
    return ['ota_id' => null, 'ona_id' => null];
}

function farzandlar_olish($shaxs_id) {
    $shaxs_id = (int)$shaxs_id;
    $sql      = "SELECT s.* FROM shaxslar s
                 INNER JOIN oilaviy_bogliqlik ob ON s.id = ob.shaxs_id
                 WHERE ob.ota_id = $shaxs_id OR ob.ona_id = $shaxs_id
                 ORDER BY s.tugilgan_sana ASC";
    $result   = db_query($sql);
    $list     = [];
    if ($result) while ($row = $result->fetch_assoc()) $list[] = $row;
    return $list;
}

function turmush_ortogi_olish($shaxs_id) {
    $shaxs_id = (int)$shaxs_id;

    $result = db_query("SELECT turmush_ortogi_id FROM oilaviy_bogliqlik WHERE shaxs_id = $shaxs_id LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!empty($row['turmush_ortogi_id'])) return (int)$row['turmush_ortogi_id'];
    }

    $result = db_query("SELECT shaxs_id FROM oilaviy_bogliqlik WHERE turmush_ortogi_id = $shaxs_id LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (int)$row['shaxs_id'];
    }

    return null;
}

/**
 * Barcha turmush o'rtoq juftliklarini olish
 * D3.js turmush o'rtog'i chiziqlarini chizish uchun
 */
function turmush_ortogi_juftliklari($shaxs_id = null) {
    $sql    = "SELECT
                   LEAST(shaxs_id, turmush_ortogi_id)    AS id1,
                   GREATEST(shaxs_id, turmush_ortogi_id) AS id2
               FROM oilaviy_bogliqlik
               WHERE turmush_ortogi_id IS NOT NULL
               GROUP BY id1, id2";
    $result = db_query($sql);
    $list   = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $list[] = ['id1' => (int)$row['id1'], 'id2' => (int)$row['id2']];
        }
    }
    return $list;
}

function ota_ona_qoshish($shaxs_id, $ota_id = null, $ona_id = null) {
    $shaxs_id = (int)$shaxs_id;
    $ota_val  = $ota_id ? (int)$ota_id : 'NULL';
    $ona_val  = $ona_id ? (int)$ona_id : 'NULL';

    $result = db_query("SELECT id FROM oilaviy_bogliqlik WHERE shaxs_id = $shaxs_id LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $sql = "UPDATE oilaviy_bogliqlik SET ota_id = $ota_val, ona_id = $ona_val WHERE shaxs_id = $shaxs_id";
    } else {
        $sql = "INSERT INTO oilaviy_bogliqlik (shaxs_id, ota_id, ona_id) VALUES ($shaxs_id, $ota_val, $ona_val)";
    }
    return db_query($sql) ? true : false;
}

function turmush_ortogi_qoshish($shaxs1_id, $shaxs2_id) {
    $shaxs1_id = (int)$shaxs1_id;
    $shaxs2_id = (int)$shaxs2_id;
    if ($shaxs1_id === $shaxs2_id) return false;
    if (!shaxs_olish($shaxs1_id) || !shaxs_olish($shaxs2_id)) return false;

    $r1 = db_query("INSERT INTO oilaviy_bogliqlik (shaxs_id, turmush_ortogi_id) VALUES ($shaxs1_id, $shaxs2_id) ON DUPLICATE KEY UPDATE turmush_ortogi_id = $shaxs2_id");
    $r2 = db_query("INSERT INTO oilaviy_bogliqlik (shaxs_id, turmush_ortogi_id) VALUES ($shaxs2_id, $shaxs1_id) ON DUPLICATE KEY UPDATE turmush_ortogi_id = $shaxs1_id");
    return $r1 && $r2;
}

function turmush_ortogi_ochirish($shaxs_id) {
    $shaxs_id = (int)$shaxs_id;
    $to_id    = turmush_ortogi_olish($shaxs_id);
    if ($to_id) db_query("UPDATE oilaviy_bogliqlik SET turmush_ortogi_id = NULL WHERE shaxs_id = $to_id");
    return db_query("UPDATE oilaviy_bogliqlik SET turmush_ortogi_id = NULL WHERE shaxs_id = $shaxs_id") ? true : false;
}

// =============================================
// 3. QARINDOSHLIK
// =============================================

function qarindoshlik_aniqla($shaxs1_id, $shaxs2_id) {
    if ($shaxs1_id == $shaxs2_id) return "O'zi";
    $m = togri_munosabat($shaxs1_id, $shaxs2_id);   if ($m) return $m;
    $m = bir_ota_ona($shaxs1_id, $shaxs2_id);        if ($m) return $m;
    if (turmush_ortogi_olish($shaxs1_id) == $shaxs2_id) return "Turmush o'rtog'i";
    $m = qaynchilik_munosabati($shaxs1_id, $shaxs2_id); if ($m) return $m;
    return "Uzoq qarindosh";
}

function togri_munosabat($shaxs1_id, $shaxs2_id) {
    $oo1 = ota_ona_olish($shaxs1_id);
    if ($oo1['ota_id'] == $shaxs2_id) return "Otasi";
    if ($oo1['ona_id'] == $shaxs2_id) return "Onasi";
    $oo2 = ota_ona_olish($shaxs2_id);
    if ($oo2['ota_id'] == $shaxs1_id) return "O'g'li";
    if ($oo2['ona_id'] == $shaxs1_id) return "Qizi";
    return null;
}

function bir_ota_ona($shaxs1_id, $shaxs2_id) {
    $oo1 = ota_ona_olish($shaxs1_id);
    $oo2 = ota_ona_olish($shaxs2_id);
    if (!empty($oo1['ota_id']) && $oo1['ota_id'] == $oo2['ota_id']) return jinsga_qarab_aka_uka($shaxs1_id, $shaxs2_id);
    if (!empty($oo1['ona_id']) && $oo1['ona_id'] == $oo2['ona_id']) return jinsga_qarab_aka_uka($shaxs1_id, $shaxs2_id);
    return null;
}

function jinsga_qarab_aka_uka($shaxs1_id, $shaxs2_id) {
    $s1 = shaxs_olish($shaxs1_id);
    $s2 = shaxs_olish($shaxs2_id);
    if (!$s1 || !$s2) return "Qarindosh";
    $y1 = yosh_hisoblash($s1['tugilgan_sana']);
    $y2 = yosh_hisoblash($s2['tugilgan_sana']);
    $katta = is_numeric($y1) && is_numeric($y2) && $y1 > $y2;
    if ($s2['jins'] == 'erkak') return $katta ? "Ukasi" : "Akasi";
    return $katta ? "Singlisi" : "Opasi";
}

function qaynchilik_munosabati($shaxs1_id, $shaxs2_id) {
    $to = turmush_ortogi_olish($shaxs1_id);
    if (!$to) return null;
    $oo = ota_ona_olish($to);
    if ($oo['ota_id'] == $shaxs2_id) return "Qaynota";
    if ($oo['ona_id'] == $shaxs2_id) return "Qaynona";
    foreach (farzandlar_olish($to) as $f) {
        if ($f['id'] == $shaxs2_id) return "O'gay farzand";
    }
    return null;
}

// =============================================
// 4. STATISTIKA
// =============================================

function oila_statistikasi() {
    $stats = [];

    $r = db_query("SELECT COUNT(*) as soni FROM shaxslar");
    $stats['jami'] = $r ? (int)$r->fetch_assoc()['soni'] : 0;

    $stats['jins'] = ['erkak' => 0, 'ayol' => 0];
    $r = db_query("SELECT jins, COUNT(*) as soni FROM shaxslar GROUP BY jins");
    if ($r) while ($row = $r->fetch_assoc()) $stats['jins'][$row['jins']] = (int)$row['soni'];

    $stats['tirik'] = 0; $stats['vafot'] = 0;
    $r = db_query("SELECT tirik, COUNT(*) as soni FROM shaxslar GROUP BY tirik");
    if ($r) while ($row = $r->fetch_assoc()) {
        if ($row['tirik'] == 1) $stats['tirik'] = (int)$row['soni'];
        else                    $stats['vafot'] = (int)$row['soni'];
    }

    $r = db_query("SELECT AVG(YEAR(CURDATE()) - YEAR(tugilgan_sana)) as ortacha FROM shaxslar WHERE tirik=1 AND tugilgan_sana IS NOT NULL");
    $stats['ortacha_yosh'] = $r ? round($r->fetch_assoc()['ortacha'] ?? 0) : 0;

    $r = db_query("SELECT ism, familiya, tugilgan_sana, YEAR(CURDATE())-YEAR(tugilgan_sana) as yosh FROM shaxslar WHERE tirik=1 AND tugilgan_sana IS NOT NULL ORDER BY tugilgan_sana ASC LIMIT 1");
    $stats['eng_keksa'] = $r ? $r->fetch_assoc() : null;

    $r = db_query("SELECT ism, familiya, tugilgan_sana, YEAR(CURDATE())-YEAR(tugilgan_sana) as yosh FROM shaxslar WHERE tirik=1 AND tugilgan_sana IS NOT NULL ORDER BY tugilgan_sana DESC LIMIT 1");
    $stats['eng_yosh'] = $r ? $r->fetch_assoc() : null;

    $stats['avlodlar'] = avlodlar_soni();
    return $stats;
}

function avlodlar_soni() {
    $sql = "WITH RECURSIVE avlodlar AS (
        SELECT s.id, 1 AS daraja FROM shaxslar s
        WHERE s.id NOT IN (SELECT ob.shaxs_id FROM oilaviy_bogliqlik ob WHERE ob.ota_id IS NOT NULL OR ob.ona_id IS NOT NULL)
        UNION ALL
        SELECT s.id, a.daraja+1 FROM shaxslar s
        INNER JOIN oilaviy_bogliqlik ob ON s.id=ob.shaxs_id
        INNER JOIN avlodlar a ON ob.ota_id=a.id OR ob.ona_id=a.id
    )
    SELECT MAX(daraja) AS maks FROM avlodlar";
    $r = db_query($sql);
    return $r ? (int)($r->fetch_assoc()['maks'] ?? 1) : 1;
}

function qarindoshlik_zanjiri($shaxs_id) {
    $oo  = ota_ona_olish($shaxs_id);
    $zanjir = [
        'shaxs'          => shaxs_olish($shaxs_id),
        'ota'            => !empty($oo['ota_id']) ? shaxs_olish($oo['ota_id']) : null,
        'ona'            => !empty($oo['ona_id']) ? shaxs_olish($oo['ona_id']) : null,
        'bobolari'       => [],
        'momolari'       => [],
        'farzandlar'     => farzandlar_olish($shaxs_id),
        'turmush_ortogi' => null,
    ];

    if (!empty($oo['ota_id'])) {
        $oo2 = ota_ona_olish($oo['ota_id']);
        if (!empty($oo2['ota_id'])) $zanjir['bobolari'][] = shaxs_olish($oo2['ota_id']);
        if (!empty($oo2['ona_id'])) $zanjir['momolari'][] = shaxs_olish($oo2['ona_id']);
    }
    if (!empty($oo['ona_id'])) {
        $oo3 = ota_ona_olish($oo['ona_id']);
        if (!empty($oo3['ota_id'])) $zanjir['bobolari'][] = shaxs_olish($oo3['ota_id']);
        if (!empty($oo3['ona_id'])) $zanjir['momolari'][] = shaxs_olish($oo3['ona_id']);
    }

    $to_id = turmush_ortogi_olish($shaxs_id);
    if ($to_id) $zanjir['turmush_ortogi'] = shaxs_olish($to_id);

    return $zanjir;
}
