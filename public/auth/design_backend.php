<?php
// ==========================================
// PTERODACTYL MOTD & ICON DESIGN BACKEND
// ==========================================

ini_set('display_errors', 0);
error_reporting(E_ALL);

$log_file = __DIR__ . '/debug.log';

function log_message($msg) {
    global $log_file;
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

log_message("=== ÚJ MENTÉSI KÉRÉS ===");

header('Content-Type: application/json');

try {
    // 1. RAW JSON BEOLVASÁSA (Axios fix)
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    if (!$data) {
        throw new Exception("Érvénytelen JSON formátum érkezett a frontendről.");
    }

    $server_uuid = $data['server_uuid'] ?? null;
    $motd        = $data['motd'] ?? null;
    $icon_base64 = $data['icon'] ?? null;

    if (!$server_uuid) {
        throw new Exception("Hiányzó 'server_uuid' paraméter!");
    }

    log_message("Szerver UUID: $server_uuid");

    // 2. KONFIGURÁCIÓK (Cseréld ki a saját adataidra, ha nem env-ből jön!)
    $ptero_url  = getenv('APP_URL');
    $ptero_key  = getenv('API_KEY_SERVER');

    $db_host    = getenv('DB_HOST');
    $db_name    = getenv('DB_DATABASE');
    $db_user    = getenv('DB_USERNAME');
    $db_pass    = getenv('DB_PASSWORD');

    // 3. ADATBÁZIS MENTÉS (PDO)
    log_message("Adatbázis frissítése...");
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $stmt = $pdo->prepare("UPDATE allocated_domains SET motd = :motd, icon = :icon WHERE server_uuid = :uuid");
    $stmt->execute([
        ':motd' => $motd,
        ':icon' => $icon_base64,
        ':uuid' => $server_uuid
    ]);
    log_message("Adatbázis rekord frissítve.");

    // 4. PTERODACTYL API: server.properties MÓDOSÍTÁSA
    if ($motd !== null) {
        log_message("server.properties olvasása...");
        
        $ch = curl_init("$ptero_url/api/client/servers/$server_uuid/files/contents?file=server.properties");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $ptero_key",
                "Accept: application/json"
            ]
        ]);
        $props_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $props_content !== false) {
            // MOTD sora cseréje
            if (preg_match('/^motd=.*$/m', $props_content)) {
                $new_props = preg_replace('/^motd=.*$/m', 'motd=' . $motd, $props_content);
            } else {
                $new_props = $props_content . "\nmotd=" . $motd;
            }

            // server.properties kiírása
            $ch = curl_init("$ptero_url/api/client/servers/$server_uuid/files/write?file=server.properties");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $new_props,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer $ptero_key",
                    "Content-Type: text/plain"
                ]
            ]);
            $write_res = curl_exec($ch);
            $write_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($write_code >= 200 && $write_code < 300) {
                log_message("server.properties elmentve!");
            } else {
                log_message("HIBA: server.properties írás sikertelen! HTTP: $write_code");
            }
        } else {
            log_message("HIBA: server.properties nem olvasható! HTTP: $http_code");
        }
    }

    // 5. PTERODACTYL API: server-icon.png FELTÖLTÉSE
    if (!empty($icon_base64) && strpos($icon_base64, 'data:image') === 0) {
        log_message("server-icon.png feltöltése...");

        $image_parts = explode(";base64,", $icon_base64);
        $image_binary = base64_decode($image_parts[1] ?? '');

        if ($image_binary) {
            // Feltöltési aláírt URL igénylése
            $ch = curl_init("$ptero_url/api/client/servers/$server_uuid/files/upload");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer $ptero_key",
                    "Accept: application/json"
                ]
            ]);
            $upload_res = curl_exec($ch);
            $upload_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $upload_json = json_decode($upload_res, true);
            $signed_upload_url = $upload_json['attributes']['url'] ?? null;

            if ($upload_code === 200 && $signed_upload_url) {
                // Temp fájl generálása a curl feltöltéshez
                $tmp_file = sys_get_temp_dir() . '/icon_' . $server_uuid . '.png';
                file_put_contents($tmp_file, $image_binary);

                $cfile = new CURLFile($tmp_file, 'image/png', 'server-icon.png');
                $ch = curl_init($signed_upload_url . "&directory=/");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => ['files' => $cfile]
                ]);
                $final_upload_res = curl_exec($ch);
                $final_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                @unlink($tmp_file);

                if ($final_code >= 200 && $final_code < 300) {
                    log_message("server-icon.png sikeresen feltöltve!");
                } else {
                    log_message("HIBA: Kép feltöltés elhasalt! HTTP: $final_code");
                }
            } else {
                log_message("HIBA: Nem sikerült feltöltési URL-t szerezni! HTTP: $upload_code");
            }
        }
    }

    log_message("=== MENTÉS SIKERES ===");
    echo json_encode(['success' => true, 'message' => 'Minden elmentve!']);

} catch (Exception $e) {
    log_message("KRITIKUS HIBA: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
