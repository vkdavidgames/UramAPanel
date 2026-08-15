<?php
header("Content-Type: application/json");
ini_set('display_errors', 0);

// Biztonságos .env beolvasás
$env_file = '/var/www/pterodactyl/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$db_host = $_ENV['DB_HOST'];
$db_user = $_ENV['DB_USERNAME'];
$db_pass = $_ENV['DB_PASSWORD'];
$db_name = $_ENV['DB_DATABASE'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die(json_encode(["status" => "error", "message" => "Adatbázis hiba."])); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $server_id = preg_replace('/[^a-zA-Z0-9-]/', '', $_POST['server_id'] ?? '');
    $ext_type = trim($_POST['ext_type'] ?? 'mod');
    $ext_name = strtolower(trim($_POST['ext_name'] ?? ''));
    $ext_version = trim($_POST['ext_version'] ?? 'latest');

    if (empty($server_id) || empty($ext_name)) {
        die(json_encode(["status" => "error", "message" => "Minden mező kitöltése kötelező!"]));
    }

    $target_folder = "mods";
    if ($ext_type === 'plugin') $target_folder = "plugins";
    if ($ext_type === 'datapack') $target_folder = "world/datapacks";

    $server_base_path = "/var/lib/pterodactyl/volumes/" . $server_id . "/";
    $full_target_path = $server_base_path . $target_folder;

    if (!file_exists($full_target_path)) {
        mkdir($full_target_path, 0755, true);
    }

    // MODRINTH API LEKÉRÉS
    $api_url = "https://modrinth.com" . urlencode($ext_name) . "/version";
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: DavidGames/Pterodactyl-ExtensionManager/1.0 (vkdg0410@gmail.com)']);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($response)) {
        die(json_encode(["status" => "error", "message" => "A kiterjesztés nem található a Modrinth-en! Ellenőrizd a slugot."]));
    }
    $versions = json_decode($response, true);
    $selected_version = null;
    if ($ext_version === 'latest') {
        $selected_version = $versions[0];
    } else {
        foreach ($versions as $v) {
            if ($v['version_number'] === $ext_version || $v['id'] === $ext_version) {
                $selected_version = $v;
                break;
            }
        }
    }

    if (!$selected_version || empty($selected_version['files'])) {
        die(json_encode(["status" => "error", "message" => "A kért verzió ($ext_version) nem található meg a Modrinth-en!"]));
    }

    $primary_file = $selected_version['files'][0];
    $download_url = $primary_file['url'];
    $filename = $primary_file['filename'];
    $destination = $full_target_path . "/" . $filename;

    // ÉLES FÁJL LETÖLTÉS
    $fp = fopen($destination, 'w+');
    $dl_ch = curl_init($download_url);
    curl_setopt($dl_ch, CURLOPT_FILE, $fp);
    curl_setopt($dl_ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($dl_ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($dl_ch, CURLOPT_HTTPHEADER, ['User-Agent: DavidGames/Pterodactyl-ExtensionManager/1.0']);
    curl_exec($dl_ch);
    $dl_code = curl_getinfo($dl_ch, CURLINFO_HTTP_CODE);
    curl_close($dl_ch);
    fclose($fp);

    if ($dl_code === 200 && file_exists($destination)) {
        chown($destination, "www-data");

        // AUTOMATA ZIP KICSOMAGOLÓ ENGINE A DATAPACKEKHEZ
        if ($ext_type === 'datapack' && pathinfo($destination, PATHINFO_EXTENSION) === 'zip') {
            $zip = new ZipArchive;
            if ($zip->open($destination) === TRUE) {
                $datapack_dir = $full_target_path . "/" . pathinfo($filename, PATHINFO_FILENAME);
                if (!file_exists($datapack_dir)) mkdir($datapack_dir, 0755, true);
                
                $zip->extractTo($datapack_dir);
                $zip->close();
                unlink($destination); // Töröljük a nyers zip-et
                
                exec("chown -R www-data:www-data " . escapeshellarg($datapack_dir));
                die(json_encode(["status" => "success", "message" => "A datapack sikeresen letöltve és kibontva a `/world/datapacks/" . pathinfo($filename, PATHINFO_FILENAME) . "` mappába!"]));
            }
        }
        
        echo json_encode(["status" => "success", "message" => "A(z) $filename sikeresen telepítve a `/" . $target_folder . "` mappába!"]);
    } else {
        @unlink($destination);
        echo json_encode(["status" => "error", "message" => "Hiba történt a fájl letöltése közben."]);
    }
    exit();
}
?>
