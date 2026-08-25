<?php
header("Content-Type: application/json");
ignore_user_abort(true);
ini_set('display_errors', 0);

$env_file = '/var/www/pterodactyl/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, " '\"");
    }
}

$db_host = $_ENV['DB_HOST'];
$db_port = $_ENV['DB_PORT'];
$db_user = $_ENV['DB_USERNAME'];
$db_pass = $_ENV['DB_PASSWORD'];
$db_name = $_ENV['DB_DATABASE'];

$panel_url = rtrim($_ENV['APP_URL'] ?? '', '/');
$api_key = $_ENV['API_KEY_SERVER'] ?? ''; 

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) { die(json_encode(["status" => "error", "message" => "Adatbázis hiba."])); }

// GET KÉRÉS: Adatok betöltése
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $server_uuid = trim($_GET['server_uuid'] ?? '');
    if (empty($server_uuid)) { die(json_encode(["status" => "error", "message" => "Hiányzó szerver azonosító."])); }
    $short_uuid = substr($server_uuid, 0, 8);

    $stmt = $conn->prepare("SELECT motd, icon FROM allocated_domains WHERE pterodactyl_server_id = ?");
    $stmt->bind_param("s", $short_uuid); $stmt->execute(); $stmt->bind_result($motd, $icon);
    if ($stmt->fetch()) { echo json_encode(["status" => "success", "motd" => $motd, "icon" => $icon]); } 
    else { echo json_encode(["status" => "success", "motd" => "", "icon" => "default"]); }
    $stmt->close();
    exit();
}

// POST KÉRÉS: Szöveges adatok mentése + API élesítés
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['icon_file'])) {
    $data = json_decode(file_get_contents("php://input"), true);
    $server_uuid = trim($data['server_uuid'] ?? '');
    $motd = trim($data['motd'] ?? '');
    $icon = trim($data['icon'] ?? 'default');

    if (empty($server_uuid)) { die(json_encode(["status" => "error", "message" => "Érvénytelen szerver azonosító."])); }

    $stmt_full = $conn->prepare("SELECT id, uuid, user_id, node_id FROM servers WHERE uuid LIKE ?");
    $search_uuid = $server_uuid . "%";
    $stmt_full->bind_param("s", $search_uuid); $stmt_full->execute(); $stmt_full->bind_result($internal_id, $long_uuid, $user_id, $node_id);
    if (!$stmt_full->fetch()) { $stmt_full->close(); die(json_encode(["status" => "error", "message" => "A szerver nem található."])); }
    $stmt_full->close();
    
    $long_uuid = trim($long_uuid);
    $short_uuid = substr($long_uuid, 0, 8);

    $stmt_port = $conn->prepare("SELECT port FROM allocations WHERE server_id = ? LIMIT 1");
    $stmt_port->bind_param("i", $internal_id); $stmt_port->execute(); $stmt_port->bind_result($fallback_port);
    if (!$stmt_port->fetch()) { $fallback_port = 25565; }
    $stmt_port->close();

    $stmt_check = $conn->prepare("SELECT id FROM allocated_domains WHERE pterodactyl_server_id = ?");
    $stmt_check->bind_param("s", $short_uuid); $stmt_check->execute(); $stmt_check->store_result();
    $exists = ($stmt_check->num_rows > 0); $stmt_check->close();

    if ($exists) {
        $stmt = $conn->prepare("UPDATE allocated_domains SET motd = ?, icon = ? WHERE pterodactyl_server_id = ?");
        $stmt->bind_param("sss", $motd, $icon, $short_uuid);
    } else {
        $default_sub = "server-" . $internal_id;
        $default_type = "port";
        $stmt = $conn->prepare("INSERT INTO allocated_domains (id, pterodactyl_user_id, pterodactyl_server_id, subdomain, port, allocation_type, node_id, motd, icon) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssisss", $internal_id, $user_id, $short_uuid, $default_sub, $fallback_port, $default_type, $node_id, $motd, $icon);
    }
    
    if (!$stmt->execute()) {
        $error_msg = $stmt->error; $stmt->close();
        die(json_encode(["status" => "error", "message" => "Adatbázis hiba: " . $error_msg]));
    }
    $stmt->close();

    if (!empty($api_key)) {
        $file_url = "{$panel_url}/api/application/servers/{$internal_id}/files/contents?file=server.properties";
        $ch = curl_init($file_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$api_key}", "Accept: application/json"]);
        $raw_content = curl_exec($ch); curl_close($ch);

        if ($raw_content && strpos($raw_content, 'motd=') !== false) {
            $lines = explode("\n", $raw_content);
            foreach ($lines as $key => $line) {
                if (strpos(trim($line), 'motd=') === 0) {
                    $lines[$key] = "motd=" . str_replace('&', '§', $motd);
                    break;
                }
            }
            $new_content = implode("\n", $lines);

            $write_url = "{$panel_url}/api/application/servers/{$internal_id}/files/write?file=server.properties";
            $ch_write = curl_init($write_url);
            curl_setopt($ch_write, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_write, CURLOPT_POST, true);
            curl_setopt($ch_write, CURLOPT_POSTFIELDS, $new_content);
            curl_setopt($ch_write, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$api_key}", "Content-Type: text/plain"]);
            curl_exec($ch_write); curl_close($ch_write);
        }

        if ($icon === 'default') {
            $default_source = "/var/www/pterodactyl/public/auth/default-icon.png";
            $upload_url = "{$panel_url}/api/application/servers/{$internal_id}/files/write?file=server-icon.png";
            if (file_exists($default_source)) {
                $ch_def = curl_init($upload_url);
                curl_setopt($ch_def, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_def, CURLOPT_POST, true);
                curl_setopt($ch_def, CURLOPT_POSTFIELDS, file_get_contents($default_source));
                curl_setopt($ch_def, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$api_key}", "Content-Type: application/octet-stream"]);
                curl_exec($ch_def); curl_close($ch_def);
            }
        }
    }

    echo json_encode(["status" => "success", "message" => "Dizájn sikeresen mentve és szinkronizálva!"]);
    exit();
}

// POST KÉRÉS: Ikon feltöltés
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['icon_file'])) {
    $server_uuid = trim($_POST['server_uuid'] ?? '');
    if (empty($server_uuid)) { die(json_encode(["status" => "error", "message" => "Érvénytelen szerver azonosító."])); }

    $stmt_full = $conn->prepare("SELECT id, uuid, user_id, node_id FROM servers WHERE uuid LIKE ?");
    $search_uuid = $server_uuid . "%";
    $stmt_full->bind_param("s", $search_uuid); $stmt_full->execute(); $stmt_full->bind_result($internal_id, $long_uuid, $user_id, $node_id);
    if (!$stmt_full->fetch()) { $stmt_full->close(); die(json_encode(["status" => "error", "message" => "A szerver nem található."])); }
    $stmt_full->close();

    if (empty($api_key)) { die(json_encode(["status" => "error", "message" => "Az API_KEY_SERVER hiányzik a .env fájlból!"])); }

    $long_uuid = trim($long_uuid);
    $short_uuid = substr($long_uuid, 0, 8);

    $stmt_port = $conn->prepare("SELECT port FROM allocations WHERE server_id = ? LIMIT 1");
    $stmt_port->bind_param("i", $internal_id); $stmt_port->execute(); $stmt_port->bind_result($fallback_port);
    if (!$stmt_port->fetch()) { $fallback_port = 25565; }
    $stmt_port->close();

    $file = $_FILES['icon_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'])) { die(json_encode(["status" => "error", "message" => "Csak PNG, JPG és JPEG képek támogatottak!"])); }

    $src_img = ($ext === 'png') ? @imagecreatefrompng($file['tmp_name']) : @imagecreatefromjpeg($file['tmp_name']);
    if (!$src_img) { die(json_encode(["status" => "error", "message" => "A képfájl sérült."])); }

    $dst_img = imagecreatetruecolor(64, 64);
    imagealphablending($dst_img, false); imagesavealpha($dst_img, true);
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, 64, 64, imagesx($src_img), imagesy($src_img));

    $tmp_path = "/tmp/server-icon-temp.png";
    imagepng($dst_img, $tmp_path);
    imagedestroy($src_img); imagedestroy($dst_img);

    $upload_url = "{$panel_url}/api/application/servers/{$internal_id}/files/write?file=server-icon.png";
    $ch_upload = curl_init($upload_url);
    curl_setopt($ch_upload, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_upload, CURLOPT_POST, true);
    curl_setopt($ch_upload, CURLOPT_POSTFIELDS, file_get_contents($tmp_path));
    curl_setopt($ch_upload, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$api_key}", "Content-Type: application/octet-stream"]);
    curl_exec($ch_upload); $http_code = curl_getinfo($ch_upload, CURLINFO_HTTP_CODE); curl_close($ch_upload);
    
    unlink($tmp_path);

    if ($http_code === 204 || $http_code === 200) {
        $icon_status = "custom";
        
        $stmt_check = $conn->prepare("SELECT id FROM allocated_domains WHERE pterodactyl_server_id = ?");
        $stmt_check->bind_param("s", $short_uuid); $stmt_check->execute(); $stmt_check->store_result();
        $exists = ($stmt_check->num_rows > 0); $stmt_check->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE allocated_domains SET icon = ? WHERE pterodactyl_server_id = ?");
            $stmt->bind_param("ss", $icon_status, $short_uuid);
        } else {
            // Ha nem létezik a sor, egy TŰPONTOS, minden kötelező mezőt tartalmazó rekordot szúrunk be az éles adatokkal
            $default_sub = "server-" . $internal_id;
            $default_type = "port";
            $stmt = $conn->prepare("INSERT INTO allocated_domains (id, pterodactyl_user_id, pterodactyl_server_id, subdomain, port, allocation_type, node_id, icon) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssis", $internal_id, $user_id, $short_uuid, $default_sub, $fallback_port, $default_type, $node_id, $icon_status);
        }
        $stmt->execute(); 
        $stmt->close();
        
        echo json_encode(["status" => "success", "message" => "Ikon sikeresen feltöltve és aktiválva!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Az API elutasította a képírást. Kód: {$http_code}"]);
    }
    exit();
}
?>
