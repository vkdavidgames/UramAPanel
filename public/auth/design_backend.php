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
    $server_id = intval($_GET['server_id'] ?? 0);
    if ($server_id === 0) { die(json_encode(["status" => "error", "message" => "Hiányzó szerver ID."])); }

    $stmt_uuid = $conn->prepare("SELECT uuid FROM servers WHERE id = ?");
    $stmt_uuid->bind_param("i", $server_id); $stmt_uuid->execute(); $stmt_uuid->bind_result($server_uuid);
    if (!$stmt_uuid->fetch()) { $stmt_uuid->close(); die(json_encode(["status" => "success", "motd" => "", "icon" => "default"])); }
    $stmt_uuid->close();

    $stmt = $conn->prepare("SELECT motd, icon FROM allocated_domains WHERE pterodactyl_server_id = ?");
    $stmt->bind_param("s", $server_uuid); $stmt->execute(); $stmt->bind_result($motd, $icon);
    if ($stmt->fetch()) { echo json_encode(["status" => "success", "motd" => $motd, "icon" => $icon]); } 
    else { echo json_encode(["status" => "success", "motd" => "", "icon" => "default"]); }
    $stmt->close();
    exit();
}

// POST KÉRÉS: Szöveges adatok mentése + MINECRAFT FÁJL KÖZVETLEN ÁTÍRÁSA!
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['icon_file'])) {
    $data = json_decode(file_get_contents("php://input"), true);
    $server_id = intval($data['server_id'] ?? 0);
    $motd = trim($data['motd'] ?? '');
    $icon = trim($data['icon'] ?? 'default');

    if ($server_id === 0) { die(json_encode(["status" => "error", "message" => "Érvénytelen adatok."])); }

    $stmt_uuid = $conn->prepare("SELECT uuid FROM servers WHERE id = ?");
    $stmt_uuid->bind_param("i", $server_id); $stmt_uuid->execute(); $stmt_uuid->bind_result($server_uuid);
    if (!$stmt_uuid->fetch()) { $stmt_uuid->close(); die(json_encode(["status" => "error", "message" => "Szerver nem található."])); }
    $stmt_uuid->close();
    $server_uuid = trim($server_uuid);

    // 1. LÉPÉS: Mentés a mi egyedi adatbázisunkba a tartomány-kezeléshez
    $stmt = $conn->prepare("UPDATE allocated_domains SET motd = ?, icon = ? WHERE pterodactyl_server_id = ?");
    $stmt->bind_param("sss", $motd, $icon, $server_uuid);
    $stmt->execute();
    $stmt->close();

    // 2. LÉPÉS: KÖZVETLEN MINECRAFT CONFIG FÁJL MÓDOSÍTÁS (AZ ÉLESÍTÉSHEZ!)
    $config_file = "/var/lib/pterodactyl/volumes/" . $server_uuid . "/server.properties";
    if (file_exists($config_file)) {
        $lines = file($config_file, FILE_IGNORE_NEW_LINES);
        $updated = false;
        foreach ($lines as $key => $line) {
            if (strpos(trim($line), 'motd=') === 0) {
                // A Minecraft a belső színeket § jellel kéri, ezért az & jeleket automatikusan átkonvertáljuk a fájlba írás előtt!
                $clean_motd = str_replace('&', '§', $motd);
                $lines[$key] = "motd=" . $clean_motd;
                $updated = true;
                break;
            }
        }
        // Ha nem volt benne motd= sor, hozzáfűzzük a végére
        if (!$updated) {
            $clean_motd = str_replace('&', '§', $motd);
            $lines[] = "motd=" . $clean_motd;
        }
        file_put_contents($config_file, implode("\n", $lines) . "\n");
        chown($config_file, "pterodactyl");
    }

    // 3. LÉPÉS: Default logó szinkronizálása, ha szükséges
    if ($icon === 'default') {
        $default_source = "/var/www/pterodactyl/public/auth/default-icon.png";
        $target_dest = "/var/lib/pterodactyl/volumes/" . $server_uuid . "/server-icon.png";
        if (file_exists($default_source) && is_dir("/var/lib/pterodactyl/volumes/" . $server_uuid)) {
            copy($default_source, $target_dest);
            chown($target_dest, "pterodactyl");
        }
    }

    echo json_encode(["status" => "success", "message" => "A dizájn sikeresen mentve és a server.properties frissítve!"]);
    exit();
}

// POST KÉRÉS: Ikon feltöltés
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['icon_file'])) {
    $server_id = intval($_POST['server_id'] ?? 0);
    if ($server_id === 0) { die(json_encode(["status" => "error", "message" => "Érvénytelen szerver ID."])); }

    $stmt = $conn->prepare("SELECT uuid FROM servers WHERE id = ?");
    $stmt->bind_param("i", $server_id); $stmt->execute(); $stmt->bind_result($server_uuid);
    if (!$stmt->fetch()) { $stmt->close(); die(json_encode(["status" => "error", "message" => "Szerver nem található."])); }
    $stmt->close();

    $server_uuid = trim($server_uuid);
    $target_dir = "/var/lib/pterodactyl/volumes/" . $server_uuid . "/";
    if (!is_dir($target_dir)) { die(json_encode(["status" => "error", "message" => "A szerver gyökérmappája nem elérhető."])); }

    $file = $_FILES['icon_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'])) { die(json_encode(["status" => "error", "message" => "Csak PNG, JPG és JPEG képek tölthetők fel!"])); }

    $src_img = ($ext === 'png') ? @imagecreatefrompng($file['tmp_name']) : @imagecreatefromjpeg($file['tmp_name']);
    if (!$src_img) { die(json_encode(["status" => "error", "message" => "A képfájl sérült."])); }

    $dst_img = imagecreatetruecolor(64, 64);
    imagealphablending($dst_img, false); imagesavealpha($dst_img, true);
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, 64, 64, imagesx($src_img), imagesy($src_img));

    $final_path = $target_dir . "server-icon.png";
    if (imagepng($dst_img, $final_path)) {
        chown($final_path, "pterodactyl");
        $icon_status = "custom";
        $stmt = $conn->prepare("UPDATE allocated_domains SET icon = ? WHERE pterodactyl_server_id = ?");
        $stmt->bind_param("ss", $icon_status, $server_uuid); $stmt->execute(); $stmt->close();
        echo json_encode(["status" => "success", "message" => "Ikon sikeresen feltöltve és 64x64 méretre alakítva!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Nem sikerült elmenteni a képet."]);
    }
    imagedestroy($src_img); imagedestroy($dst_img);
    exit();
}
?>
