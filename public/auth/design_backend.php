<?php
header("Content-Type: application/json");
ini_set('display_errors', 0);

// Tiszta .env beolvasás
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

// GET KÉRÉS: Adatok betöltése a frontendnek
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $server_id = intval($_GET['server_id'] ?? 0);
    if ($server_id === 0) { die(json_encode(["status" => "error", "message" => "Hiányzó szerver ID."])); }

    $stmt = $conn->prepare("SELECT motd, icon FROM allocated_domains WHERE id = ?");
    $stmt->bind_param("i", $server_id);
    $stmt->execute();
    $stmt->bind_result($motd, $icon);
    if ($stmt->fetch()) {
        echo json_encode(["status" => "success", "motd" => $motd, "icon" => $icon]);
    } else {
        echo json_encode(["status" => "success", "motd" => "", "icon" => "default"]);
    }
    $stmt->close();
    exit();
}

// POST KÉRÉS: Szöveges adatok és Ikon típus mentése
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['icon_file'])) {
    $data = json_decode(file_get_contents("php://input"), true);
    $server_id = intval($data['server_id'] ?? 0);
    $motd = trim($data['motd'] ?? '');
    $icon = trim($data['icon'] ?? 'default');

    $stmt = $conn->prepare("INSERT INTO allocated_domains (id, motd, icon) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE motd = ?, icon = ?");
    $stmt->bind_param("issss", $server_id, $motd, $icon, $motd, $icon);
    if ($stmt->execute()) {
        $stmt->close();
        
        // HA A GYÁRI LOGÓT VÁLASZTOTTA, AZONNAL ÁTMÁSOLJUK A SZERVER GYÖKÉRBE
        if ($icon === 'default') {
            $stmt_uuid = $conn->prepare("SELECT uuid FROM servers WHERE id = ?");
            $stmt_uuid->bind_param("i", $server_id);
            $stmt_uuid->execute();
            $stmt_uuid->bind_result($s_uuid);
            if ($stmt_uuid->fetch()) {
                $s_uuid = trim($s_uuid);
                $default_source = "/var/www/pterodactyl/public/auth/default-icon.png";
                $target_dest = "/var/lib/pterodactyl/volumes/" . $s_uuid . "/server-icon.png";
                
                if (file_exists($default_source) && is_dir("/var/lib/pterodactyl/volumes/" . $s_uuid)) {
                    copy($default_source, $target_dest);
                    chown($target_dest, "pterodactyl");
                }
            }
            $stmt_uuid->close();
        }
        
        echo json_encode(["status" => "success", "message" => "Beállítások sikeresen mentve és az ikon szinkronizálva!"]);
    } else {
        $stmt->close();
        echo json_encode(["status" => "error", "message" => "Nem sikerült menteni az adatbázisba."]);
    }
    exit();
}

// POST KÉRÉS: Képfeltöltés, automatikus konvertálás és mozgatás a Pterodactyl szerver mappájába
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['icon_file'])) {
    $server_id = intval($_POST['server_id'] ?? 0);
    if ($server_id === 0) { die(json_encode(["status" => "error", "message" => "Érvénytelen szerver ID."])); }

    // Megkeressük a szerver UUID-ját a gyári Pterodactyl táblából, hogy tudjuk a fizikai mappa útvonalát
    $stmt = $conn->prepare("SELECT uuid FROM servers WHERE id = ?");
    $stmt->bind_param("i", $server_id);
    $stmt->execute();
    $stmt->bind_result($server_uuid);
    if (!$stmt->fetch()) { $stmt->close(); die(json_encode(["status" => "error", "message" => "Szerver nem található."])); }
    $stmt->close();

    // A Pterodactyl alapértelmezett szervermappa útvonala
    $target_dir = "/var/lib/pterodactyl/volumes/" . $server_uuid . "/";
    if (!is_dir($target_dir)) { die(json_encode(["status" => "error", "message" => "A szerver gyökérmappája nem elérhető."])); }

    $file = $_FILES['icon_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Támogatott formátumok ellenőrzése
    if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
        die(json_encode(["status" => "error", "message" => "Csak PNG, JPG és JPEG képek tölthetők fel!"]));
    }

    // Kép beolvasása formátumtól függően
    if ($ext === 'png') {
        $src_img = @imagecreatefrompng($file['tmp_name']);
    } else {
        $src_img = @imagecreatefromjpeg($file['tmp_name']);
    }

    if (!$src_img) { die(json_encode(["status" => "error", "message" => "A képfájl sérült vagy hibás."])); }

    // Átméretezés pontosan 64x64 pixelre a Minecraft szabvány szerint
    $dst_img = imagecreatetruecolor(64, 64);
    imagealphablending($dst_img, false);
    imagesavealpha($dst_img, true);
    
    $width = imagesx($src_img);
    $height = imagesy($src_img);
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, 64, 64, $width, $height);

    // Mentés közvetlenül a Pterodactyl szerver gyökerébe, felülírva a régit
    $final_path = $target_dir . "server-icon.png";
    if (imagepng($dst_img, $final_path)) {
        // Beállítjuk a helyes Pterodactyl fájljogosultságot (szigorúan a Pterodactyl belső felhasználója)
        chown($final_path, "pterodactyl");
        
        // Frissítjük az állapotot a mi adatbázisunkban is
        $icon_status = "custom";
        $stmt = $conn->prepare("UPDATE allocated_domains SET icon = ? WHERE id = ?");
        $stmt->bind_param("si", $icon_status, $server_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(["status" => "success", "message" => "Ikon sikeresen átalakítva és feltöltve a szerverre!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Nem sikerült elmenteni a képet a szervermappába."]);
    }

    imagedestroy($src_img);
    imagedestroy($dst_img);
    exit();
}
?>
