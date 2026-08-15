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

$pterodactyl_url = $_ENV['APP_URL'];
$pterodactyl_api_key = $_ENV['API_KEY_SERVER'];

$cf_email = $_ENV['CLOUDFLARE_EMAIL'];
$cf_api_key = $_ENV['CLOUDFLARE_API_TOKEN'];
$cf_zone_id = $_ENV['CLOUDFLARE_ZONE_ID'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) { die(json_encode(["status" => "error", "message" => "Adatbázis hiba."])); }

if (isset($_POST['check_uid'])) {
    $c_uid = intval($_POST['check_uid']);
    $stmt = $conn->prepare("SELECT has_free_server, `rank` FROM users_security_meta WHERE pterodactyl_user_id = ?");
    $stmt->bind_param("i", $c_uid); $stmt->execute(); $stmt->bind_result($h_free, $u_rank); $stmt->fetch(); $stmt->close();
    echo json_encode(["has_promo" => intval($h_free), "user_rank" => intval($u_rank)]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $game = trim($_POST['game'] ?? 'minecraft');
    $type = trim($_POST['server_type'] ?? 'vanilla');
    $loader = trim($_POST['loader'] ?? 'none');
    $loader_version = trim($_POST['loader_version'] ?? 'latest');
    $mc_version = trim($_POST['mc_version'] ?? '1.21.1');
    $java_version = intval($_POST['java_version'] ?? 21);
    $requested_ram = intval($_POST['ram'] ?? 1024);
    $requested_disk = intval($_POST['disk'] ?? 2048);
    $custom_subdomain = trim($_POST['custom_subdomain'] ?? '');
    $allocation_type = trim($_POST['allocation_type'] ?? 'port');
    $custom_port = intval($_POST['custom_port'] ?? 0);

    if ($user_id === 0 || empty($custom_subdomain)) { die(json_encode(["status" => "error", "message" => "Hiányzó adatok!"])); }
    $clean_subdomain = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $custom_subdomain));

    $user_rank = 0; $has_free_server = 0;
    $stmt = $conn->prepare("SELECT has_free_server, `rank` FROM users_security_meta WHERE pterodactyl_user_id = ?");
    $stmt->bind_param("i", $user_id); $stmt->execute(); $stmt->bind_result($has_free_server, $user_rank); $stmt->fetch(); $stmt->close();

    if ($user_rank !== 1 && $has_free_server !== 1) { die(json_encode(["status" => "error", "message" => "Nincs igénylési jogod!"])); }

    $stmt = $conn->prepare("SELECT id FROM allocated_domains WHERE pterodactyl_user_id = ?");
    $stmt->bind_param("i", $user_id); $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $stmt->close(); die(json_encode(["status" => "error", "message" => "Már van aktív szervered!"])); }
    $stmt->close();

    // GEOROUTING AUTOMATIZÁCIÓ (SZŰRVE)
    $selected_node = 1;
    $stmt = $conn->prepare("SELECT n.id FROM nodes as n WHERE n.maintenance_mode = 0 AND n.public = 1 ORDER BY (SELECT COUNT(*) FROM allocated_domains WHERE allocated_domains.node_id = n.id) ASC LIMIT 1");
    if ($stmt) {
        $stmt->execute(); $stmt->bind_result($best_node);
        if ($stmt->fetch()) { $selected_node = intval($best_node); }
        $stmt->close();
    }
    if ($selected_node === 0) { $selected_node = 1; }

    // PORT KIOSZTÁS ÉS BELSŐ ID LEKÉRDEZÉS
    $assigned_port = 0; $allocation_id = 0;
    if ($allocation_type === 'full') {
        $assigned_port = ($game === 'bedrock') ? 19132 : 25565;
        $stmt = $conn->prepare("SELECT id FROM allocated_domains WHERE port = ? AND node_id = ?");
        $stmt->bind_param("ii", $assigned_port, $selected_node); $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) { $stmt->close(); die(json_encode(["status" => "error", "message" => "A főport ezen a Node-on már foglalt!"])); }
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT id FROM allocations WHERE node_id = ? AND port = ? LIMIT 1");
        $stmt->bind_param("ii", $selected_node, $assigned_port); $stmt->execute(); $stmt->bind_result($alloc_id); $stmt->fetch(); $stmt->close();
        if (!$alloc_id) { die(json_encode(["status" => "error", "message" => "A főport nincs hozzáadva a kiválasztott Node-hoz a panelen!"])); }
        $allocation_id = intval($alloc_id);
    } else {
        if ($custom_port >= 25565 && $custom_port <= 26000) {
            $assigned_port = $custom_port;
            $stmt = $conn->prepare("SELECT id FROM allocated_domains WHERE port = ? AND node_id = ?");
            $stmt->bind_param("ii", $assigned_port, $selected_node); $stmt->execute(); $stmt->store_result();
            if ($stmt->num_rows > 0) { $stmt->close(); die(json_encode(["status" => "error", "message" => "A kért egyedi port ezen a Node-on foglalt!"])); }
            $stmt->close();
            
            $stmt = $conn->prepare("SELECT id FROM allocations WHERE node_id = ? AND port = ? LIMIT 1");
            $stmt->bind_param("ii", $selected_node, $assigned_port); $stmt->execute(); $stmt->bind_result($alloc_id); $stmt->fetch(); $stmt->close();
            if (!$alloc_id) { die(json_encode(["status" => "error", "message" => "A kért port nincs hozzáadva a kiválasztott Node-hoz a panelen!"])); }
            $allocation_id = intval($alloc_id);
        } else {
            $min_p = ($game === 'bedrock') ? 19133 : 25566;
            $max_p = ($game === 'bedrock') ? 19500 : 26000;
            $stmt = $conn->prepare("SELECT a.id, a.port FROM allocations as a WHERE a.node_id = ? AND a.port >= ? AND a.port <= ? AND a.id NOT IN (SELECT id FROM allocated_domains WHERE node_id = ?) LIMIT 1");
            $stmt->bind_param("iiii", $selected_node, $min_p, $max_p, $selected_node); $stmt->execute(); $stmt->bind_result($alloc_id, $found_port); $stmt->fetch(); $stmt->close();
            if (!$alloc_id) { die(json_encode(["status" => "error", "message" => "Nincs szabadon kiosztható port ezen a Node-on!"])); }
            $assigned_port = intval($found_port); $allocation_id = intval($alloc_id);
        }
    }

    // DINAMIKUS FÉSZEK (EGG) ÉS INDÍTÁSI PARANCS MEGHATÁROZÁS
    $nest_id = 1; // Minecraft gyári Nest ID
    $egg_id = 1;  // Alapértelmezett Vanilla
    $startup_cmd = "java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JAR_FILE}}";
    $api_environment = [
        "SERVER_JARFILE" => "server.jar",
        "VANILLA_VERSION" => $mc_version,
        "MINECRAFT_VERSION" => $mc_version,
        "SERVER_VERSION" => $mc_version,
        "MC_VERSION" => $mc_version
    ];

    if ($game === 'bedrock') {
        $nest_id = 2; // Tegyük fel, hogy a Bedrock a 2-es Nestben van (vagy állítsd át a pontosra!)
        $egg_id = 10; // Cseréld ki a te pontos Bedrock Egg ID-dra!
        $startup_cmd = "./bedrock_server";
        $api_environment = ["BEDROCK_VERSION" => $mc_version, "LD_LIBRARY_PATH" => "."];
    } else {
        if ($type === 'modded') {
            if ($loader === 'forge') { $egg_id = 3; }
            elseif ($loader === 'fabric') { $egg_id = 18; $api_environment["FABRIC_VERSION"] = $loader_version; }
            elseif ($loader === 'quilt') { $egg_id = 21; $api_environment["QUILT_VERSION"] = $loader_version; }
            else { $egg_id = 16; $api_environment["NEOFORGE_VERSION"] = $loader_version; }
        } elseif ($type === 'plugins') {
            if ($loader === 'purpur') { $egg_id = 17; $api_environment["PURPUR_VERSION"] = $loader_version; }
            elseif ($loader === 'spigot') { $egg_id = 20; $api_environment["SPIGOT_VERSION"] = $loader_version; }
            else { $egg_id = 2; $api_environment["PAPER_VERSION"] = $loader_version; }
        } elseif ($type === 'bungeecord') {
            if ($loader === 'waterfall') { $egg_id = 19; }
            else { $egg_id = 4; }
            $startup_cmd = "java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JAR_FILE}}";
        }
    }

    $docker_image = "ghcr.io/pterodactyl/yolks:java_" . $java_version;
    if ($game === 'bedrock') { $docker_image = "ghcr.io/pterodactyl/yolks:debian"; }

    $serverData = [
        "name" => $username . " - " . $clean_subdomain, "user" => $user_id, "nest" => $nest_id, "egg" => $egg_id, "node" => $selected_node,
        "docker_image" => $docker_image, "startup" => $startup_cmd,
        "limits" => ["memory" => $requested_ram, "swap" => 0, "disk" => $requested_disk, "io" => 500, "cpu" => 100],
        "environment" => $api_environment,
        "feature_limits" => ["databases" => 1, "allocations" => 5, "backups" => 3], "allocation" => ["default" => $allocation_id]
    ];

    $ch = curl_init($pterodactyl_url . "/api/application/servers");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($serverData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $pterodactyl_api_key, "Content-Type: application/json", "Accept: application/json"]);
    $response = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $responseData = json_decode($response, true);

    if ($http_code === 201 && isset($responseData['attributes']['id'])) {
        $server_uuid = $responseData['attributes']['uuid'];

        $stmt = $conn->prepare("INSERT INTO allocated_domains (pterodactyl_user_id, pterodactyl_server_id, subdomain, port, allocation_type, node_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issisi", $user_id, $server_uuid, $clean_subdomain, $assigned_port, $allocation_type, $selected_node);
        $stmt->execute(); $stmt->close();

        // CLOUDFLARE DNS GENERÁLÁS V4
        $target_host = ($selected_node === 2) ? "node2.davidgames.uk" : "uramapanel.davidgames.uk";
        if ($allocation_type === 'full') {
            $dns_payload = ["type" => "CNAME", "name" => $clean_subdomain . ".davidgames.uk", "content" => $target_host, "ttl" => 1, "proxied" => false];
        } else {
            if ($game === 'bedrock') {
                $dns_payload = ["type" => "CNAME", "name" => $clean_subdomain . ".davidgames.uk", "content" => $target_host, "ttl" => 1, "proxied" => false];
            } else {
                $dns_payload = ["type" => "SRV", "name" => "_minecraft._tcp." . $clean_subdomain . ".davidgames.uk", "data" => ["service" => "_minecraft", "proto" => "_tcp", "name" => $clean_subdomain, "priority" => 0, "weight" => 5, "port" => $assigned_port, "target" => $target_host], "ttl" => 1, "proxied" => false];
            }
        }

        $cf_ch = curl_init("https://cloudflare.com" . $cf_zone_id . "/dns_records");
        curl_setopt($cf_ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($cf_ch, CURLOPT_POST, true);
        curl_setopt($cf_ch, CURLOPT_POSTFIELDS, json_encode($dns_payload));
        curl_setopt($cf_ch, CURLOPT_HTTPHEADER, ["X-Auth-Email: " . $cf_email, "X-Auth-Key: " . $cf_api_key, "Content-Type: application/json"]);
        curl_exec($cf_ch); curl_close($cf_ch);

        $final_ip = ($allocation_type === 'full' || $game === 'bedrock') ? $clean_subdomain . ".davidgames.uk" : $clean_subdomain . ".davidgames.uk:" . $assigned_port;
        echo json_encode(["status" => "success", "message" => "Szerver sikeresen létrehozva!", "domain" => $final_ip]);
    } else {
        if (isset($responseData['errors'])) {
            $err_messages = [];
            foreach ($responseData['errors'] as $err) { $err_messages[] = ($err['source']['field'] ?? 'General') . ': ' . ($err['detail'] ?? 'Hiba'); }
            $details = implode(' | ', $err_messages);
        } else { $details = "HTTP " . $http_code; }
        echo json_encode(["status" => "error", "message" => "Hiba történt: " . $details]);
    }
    exit();
}
?>
