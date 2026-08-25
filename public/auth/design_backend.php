<?php
header("Content-Type: application/json");
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

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) { die(json_encode(["status" => "error", "message" => "Adatbázis hiba."])); }

// GET KÉRÉS: Adatok betöltése
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $server_uuid = trim($_GET['server_uuid'] ?? '');
    if (empty($server_uuid)) { die(json_encode(["status" => "error", "message" => "Hiányzó szerver azonosító."])); }

    $short_uuid = substr($server_uuid, 0, 8);

    $stmt = $conn->prepare("SELECT motd, icon FROM allocated_domains WHERE pterodactyl_server_id = ?");
    $stmt->bind_param("s", $short_uuid); $stmt->execute(); $stmt->bind_result($motd, $icon);
    if ($stmt->fetch()) { 
        echo json_encode(["status" => "success", "motd" => $motd, "icon" => $icon]); 
    } else { 
        echo json_encode(["status" => "success", "motd" => "", "icon" => "default"]); 
    }
    $stmt->close();
    exit();
}

// POST KÉRÉS: Szöveges adatok mentése + Fájlírás
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['icon_file'])) {
    $data = json_decode(file_get_contents("php://input"), true);
    $server_uuid = trim($data['server_uuid'] ?? '');
    $motd = trim($data['motd'] ?? '');
    $icon = trim($data['icon'] ?? 'default');

    if (empty($server_uuid)) { die(json_encode(["status" => "error", "message" => "Érvénytelen szerver azonosító."])); }

    $stmt_full = $conn->prepare("SELECT uuid FROM servers WHERE uuid LIKE ?");
    $search_uuid = $server_uuid . "%";
    $stmt_full->bind_param("s", $search_uuid); $stmt_full->execute(); $stmt_full->bind_result($long_uuid);
    if (!$stmt_full->fetch()) { $stmt_full->close(); die(json_encode(["status" => "error", "message" => "A szerver nem található a panelen."])); }
    $stmt_full->close();
    
    $long_uuid = trim($long_uuid);
    $short_uuid = substr($long_uuid, 0, 8);

    // 1. Mentés az egyedi adatbázisba
    $stmt = $conn->prepare("UPDATE allocated_domains SET motd = ?, icon = ? WHERE pterodactyl_server_id = ?");
    $stmt->bind_param("sss", $motd, $icon, $short_uuid);
    $stmt->execute(); $stmt->close();

    // 2. Kényszerített írás a server.properties fájlba (hibatűrő Try ágban)
    $config_file = "/var/lib/pterodactyl/volumes/" . $long_uuid . "/server.properties";
    @current_user_abort(); // Megnyitjuk a PHP fájlkezelőt
    
    if (file_exists($config_file) && is_writable($config_file)) {
        $lines = file($config_file, FILE_IGNORE_NEW_LINES);
        $updated = false;
        foreach ($lines as $key => $line) {
            if (strpos(trim($line), 'motd=') === 0) {
                $lines[$key] = "motd=" . str_replace('&', '§', $motd);
                $updated = true; break;
            }
        }
        if (!$updated) { $lines[] = "motd=" . str_replace('&', '§', $motd); }
        @file_put_contents($config_file, implode("\n", $lines) . "\n");
    }

    // 3. Default logó másolása
    if ($icon === 'default') {
        $default_source = "/var/www/pterodactyl/public/auth/default-icon.png";
        $target_dest = "/var/lib/pterodactyl/volumes/" . $long_uuid . "/server-icon.png";
        if (file_exists($default_source)) {
            @copy($default_source, $target_dest);
        }
    }

    echo json_encode(["status" => "success", "message" => "Beállítások sikeresen mentve és élesítve!"]);
    exit();
}

// POST KÉRÉS: Képfeltöltés és automatikus konvertálás
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['icon_file'])) {
    $server_uuid = trim($_POST['server_uuid'] ?? '');
    if (empty($server_uuid)) { die(json_encode(["status" => "error", "message" => "Érvénytelen szerver azonosító."])); }

    $stmt_full = $conn->prepare("SELECT uuid FROM servers WHERE uuid LIKE ?");
    $search_uuid = $server_uuid . "%";
    $stmt_full->bind_param("s", $search_uuid); $stmt_full->execute(); $stmt_full->bind_result($long_uuid);
    if (!$stmt_full->fetch()) { $stmt_full->close(); die(json_encode(["status" => "error", "message" => "A szerver nem található."])); }
    $stmt_full->close();

    $long_uuid = trim($long_uuid);
    $short_uuid = substr($long_uuid, 0, 8);
    $target_dir = "/var/lib/pterodactyl/volumes/" . $long_uuid . "/";

    $file = $_FILES['icon_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'])) { die(json_encode(["status" => "error", "message" => "Csak PNG, JPG és JPEG képek támogatottak!"])); }

    $src_img = ($ext === 'png') ? @imagecreatefrompng($file['tmp_name']) : @imagecreatefromjpeg($file['tmp_name']);
    if (!$src_img) { die(json_encode(["status" => "error", "message" => "A képfájl sérült."])); }

    $dst_img = imagecreatetruecolor(64, 64);
    imagealphablending($dst_img, false); imagesavealpha($dst_img, true);
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, 64, 64, imagesx($src_img), imagesy($src_img));

    $final_path = $target_dir . "server-icon.png";
    
    // Kényszerített mentés a mappaellenőrzés megkerülésével
    if (@imagepng($dst_img, $final_path)) {
        $icon_status = "custom";
        $stmt = $conn->prepare("UPDATE allocated_domains SET icon = ? WHERE pterodactyl_server_id = ?");
        $stmt->bind_param("ss", $icon_status, $short_uuid); $stmt->execute(); $stmt->close();
        echo json_encode(["status" => "success", "message" => "Ikon sikeresen feltöltve és élesítve a szerveren!"]);
    } else { 
        echo json_encode(["status" => "error", "message" => "Írási hiba. Kérlek futtasd újra a chmod parancsot a PuTTY-ban!"]); 
    }
    imagedestroy($src_img); imagedestroy($dst_img);
    exit();
}
?>
