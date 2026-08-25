<?php
// ===================================================
// PTERODACTYL SERVER DESIGN BACKEND (GET / POST / MULTIPART)
// ===================================================

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

$log_file = __DIR__ . '/debug.log';

function log_msg($msg) {
    global $log_file;
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

try {
    // 1. KONFIGURÁCIÓK (Állítsd be a saját környezetednek megfelelően)
    $ptero_url  = rtrim(getenv('PTERO_URL') ?: 'https://panel.a-te-domainod.hu', '/');
    $ptero_key  = getenv('PTERO_API_KEY') ?: 'ITT_AZ_API_KULCSOD';

    $db_host    = getenv('DB_HOST') ?: '127.0.0.1';
    $db_name    = getenv('DB_DATABASE') ?: 'panel';
    $db_user    = getenv('DB_USERNAME') ?: 'pterodactyl';
    $db_pass    = getenv('DB_PASSWORD') ?: 'SECRET_PASSWORD';

    // Adatbázis kapcsolat
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $method = $_SERVER['REQUEST_METHOD'];

    // ===================================================
    // A) GET KÉRÉS: ADATOK BETÖLTÉSE A REACT EFFECT-HEZ
    // ===================================================
    if ($method === 'GET') {
        $server_uuid = $_GET['server_uuid'] ?? null;
        if (!$server_uuid) {
            throw new Exception("Hiányzó 'server_uuid' paraméter.");
        }

        log_msg("GET kérés érkezett - UUID: $server_uuid");

        $stmt = $pdo->prepare("SELECT motd, icon FROM allocated_domains WHERE server_uuid = :uuid LIMIT 1");
        $stmt->execute([':uuid' => $server_uuid]);
        $data = $stmt->fetch();

        echo json_encode([
            'status' => 'success',
            'motd'   => $data['motd'] ?? '',
            'icon'   => $data['icon'] ?? 'default'
        ]);
        exit;
    }

    // ===================================================
    // B) POST KÉRÉS: FILE FELTÖLTÉS VAGY FORM MENTÉS
    // ===================================================
    if ($method === 'POST') {

        // --- B1) IKON FILE FELTÖLTÉS (multipart/form-data) ---
        if (isset($_FILES['icon_file'])) {
            $server_uuid = $_POST['server_uuid'] ?? null;
            if (!$server_uuid) {
                throw new Exception("Hiányzó 'server_uuid' az ikon feltöltésnél.");
            }

            log_msg("IKON FELTÖLTÉS indítva - UUID: $server_uuid");

            $file = $_FILES['icon_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Hiba történt a fájl feltöltése során. Kód: " . $file['error']);
            }

            // Aláírt feltöltési URL lekérése Pterodactyl-tól
            $ch = curl_init("$ptero_url/api/client/servers/$server_uuid/files/upload");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer $ptero_key",
                    "Accept: application/json"
                ]
            ]);
            $upload_res  = curl_exec($ch);
            $upload_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $upload_json = json_decode($upload_res, true);
            $signed_url  = $upload_json['attributes']['url'] ?? null;

            if ($upload_code !== 200 || !$signed_url) {
                throw new Exception("Nem sikerült Pterodactyl feltöltési URL-t szerezni. HTTP: $upload_code");
            }

            // Fájl elküldése Pterodactyl SFTP/Wings-re server-icon.png néven
            $cfile = new CURLFile($file['tmp_name'], $file['type'], 'server-icon.png');
            $ch = curl_init($signed_url . "&directory=/");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ['files' => $cfile]
            ]);
            $ptero_upload_res  = curl_exec($ch);
            $ptero_upload_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($ptero_upload_code < 200 || $ptero_upload_code >= 300) {
                throw new Exception("A Pterodactyl elutasította a képfájlt. HTTP: $ptero_upload_code");
            }

            // Adatbázis frissítése ikon státuszra
            $stmt = $pdo->prepare("UPDATE allocated_domains SET icon = 'custom' WHERE server_uuid = :uuid");
            $stmt->execute([':uuid' => $server_uuid]);

            log_msg("IKON FELTÖLTVE SIKERESEN - UUID: $server_uuid");

            echo json_encode([
                'status'  => 'success',
                'message' => 'Egyedi ikon sikeresen feltöltve és beállítva!'
            ]);
            exit;
        }

        // --- B2) FORM MENTÉS (JSON: MOTD & ICON SETTING) ---
        $raw_input = file_get_contents('php://input');
        $json_data = json_decode($raw_input, true);

        if (!$json_data) {
            throw new Exception("Érvénytelen JSON kérés érkezett.");
        }

        $server_uuid = $json_data['server_uuid'] ?? null;
        $motd        = $json_data['motd'] ?? '';
        $icon        = $json_data['icon'] ?? 'default';

        if (!$server_uuid) {
            throw new Exception("Hiányzó 'server_uuid' mentésnél.");
        }

        log_msg("BEÁLLÍTÁSOK MENTÉSE - UUID: $server_uuid | MOTD: $motd | ICON: $icon");

        // 1. Adatbázis frissítés
        $stmt = $pdo->prepare("UPDATE allocated_domains SET motd = :motd, icon = :icon WHERE server_uuid = :uuid");
        $stmt->execute([
            ':motd' => $motd,
            ':icon' => $icon,
            ':uuid' => $server_uuid
        ]);

        // 2. server.properties MOTD cseréje Pterodactyl-on
        $ch = curl_init("$ptero_url/api/client/servers/$server_uuid/files/contents?file=server.properties");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $ptero_key",
                "Accept: application/json"
            ]
        ]);
        $props_content = curl_exec($ch);
        $http_code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $props_content !== false) {
            if (preg_match('/^motd=.*$/m', $props_content)) {
                $new_props = preg_replace('/^motd=.*$/m', 'motd=' . $motd, $props_content);
            } else {
                $new_props = $props_content . "\nmotd=" . $motd;
            }

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
            curl_exec($ch);
            curl_close($ch);
        }

        log_msg("BEÁLLÍTÁSOK MENTVE SIKERESEN - UUID: $server_uuid");

        echo json_encode([
            'status'  => 'success',
            'message' => 'A dizájn beállítások sikeresen elmentve!'
        ]);
        exit;
    }

} catch (Exception $e) {
    log_msg("HIBA: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
