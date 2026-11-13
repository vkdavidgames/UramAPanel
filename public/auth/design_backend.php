<?php
// ===================================================
// PTERODACTYL DESIGN BACKEND - AUTO COLUMN DETECTION
// ===================================================

header('Content-Type: application/json');

try {
    // 1. .env FÁJL BEOLVASÁSA
    $envFile = '/var/www/pterodactyl/.env';
    if (!file_exists($envFile)) {
        throw new Exception("A .env fájl nem található.");
    }

    $env = [];
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0 || empty($line)) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            $env[$key] = $val;
        }
    }

    $dbHost = $env['DB_HOST'];
    $dbPort = $env['DB_PORT'];
    $dbName = $env['DB_DATABASE'];
    $dbUser = $env['DB_USERNAME'];
    $dbPass = $env['DB_PASSWORD'];

    $pteroUrl = $env['APP_URL'];
    $pteroKey = $env['API_KEY_SERVER'];

    // 2. TCP/IP ADATBÁZIS KAPCSOLÓDÁS
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 3. TÁBLA OSZLOPAINAK AUTOMATIKUS ANOMÁLIA-DETEKTÁLÁSA
    $colStmt = $pdo->query("SHOW COLUMNS FROM allocated_domains");
    $existingCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);

    // Segédfüggvény a dinamikus WHERE feltételhez
    $buildWhereClause = function($uuid) use ($existingCols) {
        if (in_array('server_uuid', $existingCols)) {
            return ["WHERE server_uuid = :uuid", [':uuid' => $uuid]];
        }
        if (in_array('server_id', $existingCols)) {
            return ["WHERE server_id = (SELECT id FROM servers WHERE uuid = :uuid LIMIT 1)", [':uuid' => $uuid]];
        }
        if (in_array('allocation_id', $existingCols)) {
            return ["WHERE allocation_id IN (SELECT id FROM allocations WHERE server_id = (SELECT id FROM servers WHERE uuid = :uuid LIMIT 1))", [':uuid' => $uuid]];
        }
        
        throw new Exception("Nem található azonosításra alkalmas oszlop (server_uuid, server_id, allocation_id) az allocated_domains táblában. Meglévő oszlopok: " . implode(', ', $existingCols));
    };

    $method = $_SERVER['REQUEST_METHOD'];

    // ===================================================
    // A) GET KÉRÉS: ADATOK BETÖLTÉSE
    // ===================================================
    if ($method === 'GET') {
        $serverUuid = $_GET['server_uuid'] ?? null;
        if (!$serverUuid) {
            throw new Exception("Hiányzó 'server_uuid' paraméter.");
        }

        list($whereSql, $params) = $buildWhereClause($serverUuid);
        $stmt = $pdo->prepare("SELECT motd, icon FROM allocated_domains {$whereSql} LIMIT 1");
        $stmt->execute($params);
        $data = $stmt->fetch();

        echo json_encode([
            'status' => 'success',
            'motd'   => $data['motd'] ?? '',
            'icon'   => $data['icon'] ?? 'default'
        ]);
        exit;
    }

    // ===================================================
    // B) POST KÉRÉS: IKON FELTÖLTÉS VAGY MENTÉS
    // ===================================================
    if ($method === 'POST') {

        // --- B1) IKON FELTÖLTÉS ---
        if (isset($_FILES['icon_file'])) {
            $serverUuid = $_POST['server_uuid'] ?? null;
            if (!$serverUuid) throw new Exception("Hiányzó 'server_uuid' az ikon feltöltésnél.");

            $file = $_FILES['icon_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("Fájlfeltöltési hiba. Kód: " . $file['error']);

            $ch = curl_init("$pteroUrl/api/client/servers/$serverUuid/files/upload");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer $pteroKey", "Accept: application/json"]
            ]);
            $uploadRes  = curl_exec($ch);
            $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $uploadJson = json_decode($uploadRes, true);
            $signedUrl  = $uploadJson['attributes']['url'] ?? null;

            if ($uploadCode !== 200 || !$signedUrl) {
                throw new Exception("Nem sikerült feltöltési URL-t szerezni a paneltől (HTTP $uploadCode). Ellenőrizd a .env API kulcsát!");
            }

            $cfile = new CURLFile($file['tmp_name'], $file['type'], 'server-icon.png');
            $ch = curl_init($signedUrl . "&directory=/");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ['files' => $cfile]
            ]);
            $pteroUploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($pteroUploadCode < 200 || $pteroUploadCode >= 300) {
                throw new Exception("A Wings elutasította a képfájlt (HTTP $pteroUploadCode).");
            }

            list($whereSql, $params) = $buildWhereClause($serverUuid);
            $stmt = $pdo->prepare("UPDATE allocated_domains SET icon = 'custom' {$whereSql}");
            $stmt->execute($params);

            echo json_encode(['status' => 'success', 'message' => 'Egyedi ikon sikeresen feltöltve és beállítva!']);
            exit;
        }

        // --- B2) MOTD ÉS IKON MENTÉSE ---
        $rawData  = file_get_contents('php://input');
        $jsonData = json_decode($rawData, true);

        $serverUuid = $jsonData['server_uuid'] ?? null;
        $motd       = $jsonData['motd'] ?? '';
        $icon       = $jsonData['icon'] ?? 'default';

        if (!$serverUuid) throw new Exception("Hiányzó 'server_uuid' mentésnél.");

        // 1. Adatbázis frissítése
        list($whereSql, $params) = $buildWhereClause($serverUuid);
        $updateParams = array_merge([':motd' => $motd, ':icon' => $icon], $params);
        $stmt = $pdo->prepare("UPDATE allocated_domains SET motd = :motd, icon = :icon {$whereSql}");
        $stmt->execute($updateParams);

        // 2. server.properties MOTD módosítása Wings-en
        $ch = curl_init("$pteroUrl/api/client/servers/$serverUuid/files/contents?file=server.properties");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $pteroKey", "Accept: application/json"]
        ]);
        $propsContent = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $propsContent !== false) {
            if (preg_match('/^motd=.*$/m', $propsContent)) {
                $newProps = preg_replace('/^motd=.*$/m', 'motd=' . $motd, $propsContent);
            } else {
                $newProps = $propsContent . "\nmotd=" . $motd;
            }

            $ch = curl_init("$pteroUrl/api/client/servers/$serverUuid/files/write?file=server.properties");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $newProps,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer $pteroKey", "Content-Type: text/plain"]
            ]);
            curl_exec($ch);
            curl_close($ch);
        }

        echo json_encode(['status' => 'success', 'message' => 'A dizájn beállítások sikeresen elmentve!']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
