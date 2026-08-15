<?php
header("Content-Type: application/json");
ini_set('display_errors', 0);

// FIX ADATBÁZIS ADATOK A GYÁRI PTERODACTYLHOZ
$db_host = "127.0.0.1";
$db_user = "pterodactyl";
$db_pass = "4(gnJ\"IhAp]uw5d8>q.^yT|lk\"]W_tb\"da~EY#.<._\"62I`^v`mPCn}x*p[=()SP";
$db_name = "panel";
$custom_db_name = "cracked_auth";

$pterodactyl_url = "https://davidgames.uk";
$pterodactyl_api_key = "ptla_geRgLj7ysmMyBG5e4nSgk18SMa9hyFT9T8LlrAXpcfZ";

// CLOUDFLARE CONFIG
$cf_email = "vkdg0410@gmail.com"; 
$cf_api_key = "904b73b5df5bdae61c5df6da2f85412356789"; 
$cf_zone_id = "df059cf3b1e3e7f4112e4df8b5123456";    

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die(json_encode(["status" => "error", "message" => "Panel adatbázis hiba."])); }

$custom_conn = new mysqli($db_host, $db_user, $db_pass, $custom_db_name);
if ($custom_conn->connect_error) { die(json_encode(["status" => "error", "message" => "Egyedi adatbázis hiba."])); }

if (isset($_POST['check_uid'])) {
    $c_uid = intval($_POST['check_uid']);
    $stmt = $custom_conn->prepare("SELECT has_free_server, `rank` FROM users_security_meta WHERE pterodactyl_user_id = ?");
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
    $stmt = $custom_conn->prepare("SELECT has_free_server, `rank` FROM users_security_meta WHERE pterodactyl_user_id = ?");
    $stmt->bind_param("i", $user_id); $stmt->execute(); $stmt->bind_result($has_free_server, $user_rank); $stmt->fetch(); $stmt->close();

    if ($user_rank !== 1 && $has_free_server !== 1) { die(json_encode(["status" => "error", "message" => "Nincs igénylési jogod!"])); }

    $stmt = $custom_conn->prepare("SELECT id FROM allocated_domains WHERE pterodactyl_user_id = ?");
    $stmt->bind_param("i", $user_id); $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $stmt->close(); die(json_encode(["status" => "error", "message" => "Már van aktív szervered!"])); }
    $stmt->close();

    $selected_node = 1;
    $stmt = $custom_conn->prepare("SELECT node_id, COUNT(*) as cnt FROM (SELECT 1 as node_id UNION SELECT 2 as node_id) as n LEFT JOIN allocated_domains ON n.node_id = allocated_domains.node_id GROUP BY n.node_id ORDER BY cnt ASC LIMIT 1");
    $stmt->execute(); $stmt->bind_result($best_node, $count); $stmt->fetch(); $stmt->close();
    if($best_node > 0) { $selected_node = $best_node; }

    $assigned_port = 0;
    if ($allocation_type === 'full') {
        $assigned_port = 25565;
        $stmt = $custom_conn->prepare("SELECT id FROM allocated_domains WHERE port = 25565 AND node_id = ?");
        $stmt->bind_param("i", $selected_node); $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) { $stmt->close(); die(json_encode(["status" => "error", "message" => "A 25565 főport ezen a Node-on már foglalt!"])); }
        $stmt->close();
    } else {
        if ($custom_port >= 25565 && $custom_port <= 26000) {
            $assigned_port = $custom_port;
            $stmt = $custom_conn->prepare("SELECT id FROM allocated_domains WHERE port = ? AND node_id = ?");
            $stmt->bind_param("ii", $assigned_port, $selected_node); $stmt->execute(); $stmt->store_result();
            if ($stmt->num_rows > 0) { $stmt->close(); die(json_encode(["status" => "error", "message" => "A kért egyedi port ezen a Node-on foglalt!"])); }
            $stmt->close();
        } else {
            $assigned_port = 25566;
            while (true) {
                $stmt = $custom_conn->prepare("SELECT id FROM allocated_domains WHERE port = ? AND node_id = ?");
                $stmt->bind_param("ii", $assigned_port, $selected_node); $stmt->execute(); $stmt->store_result();
                if ($stmt->num_rows === 0) { $stmt->close(); break; }
                $stmt->close(); $assigned_port++;
            }
        }
    }
    $egg_id = 1; $startup_cmd = "java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JAR_FILE}}";
    if ($type === 'modded') { $egg_id = ($loader === 'neoforge') ? 15 : 2; }
    elseif ($type === 'plugins') { $egg_id = ($loader === 'purpur') ? 16 : 1; }
    elseif ($type === 'bungeecord') { $egg_id = 3; $docker_image = "ghcr.io/pterodactyl/yolks:java_11"; }

    $docker_image = "ghcr.io/pterodactyl/yolks:java_" . $java_version;

    $serverData = [
        "name" => $username . " - " . $clean_subdomain, "user" => $user_id, "nest" => 1, "egg" => $egg_id, "node" => $selected_node,
        "docker_image" => $docker_image, "startup" => $startup_cmd,
        "limits" => ["memory" => $requested_ram, "swap" => 0, "disk" => $requested_disk, "io" => 500, "cpu" => 100],
        "environment" => ["SERVER_JAR_FILE" => "server.jar", "MINECRAFT_VERSION" => $mc_version, "MOD_VERSION" => $loader_version, "FABRIC_VERSION" => $loader_version],
        "feature_limits" => ["databases" => 0, "allocations" => 1, "backups" => 1], "allocation" => ["default" => $assigned_port]
    ];

    $ch = curl_init($pterodactyl_url . "/api/application/servers");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($serverData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $pterodactyl_api_key, "Content-Type: application/json", "Accept: application/json"]);
    $response = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $responseData = json_decode($response, true);

    if ($http_code === 201 && isset($responseData['attributes']['id'])) {
        $server_uuid = $responseData['attributes']['uuid'];

        $stmt = $custom_conn->prepare("INSERT INTO allocated_domains (pterodactyl_user_id, pterodactyl_server_id, subdomain, port, allocation_type, node_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issisi", $user_id, $server_uuid, $clean_subdomain, $assigned_port, $allocation_type, $selected_node);
        $stmt->execute(); $stmt->close();

        $target_host = ($selected_node === 2) ? "node2.davidgames.uk" : "uramapanel.davidgames.uk";
        if ($allocation_type === 'full') {
            $dns_payload = ["type" => "CNAME", "name" => $clean_subdomain . ".davidgames.uk", "content" => $target_host, "ttl" => 1, "proxied" => false];
        } else {
            $dns_payload = ["type" => "SRV", "name" => "_minecraft._tcp." . $clean_subdomain . ".davidgames.uk", "data" => ["service" => "_minecraft", "proto" => "_tcp", "name" => $clean_subdomain, "priority" => 0, "weight" => 5, "port" => $assigned_port, "target" => $target_host], "ttl" => 1, "proxied" => false];
        }

        $cf_ch = curl_init("https://cloudflare.com" . $cf_zone_id . "/dns_records");
        curl_setopt($cf_ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($cf_ch, CURLOPT_POST, true);
        curl_setopt($cf_ch, CURLOPT_POSTFIELDS, json_encode($dns_payload));
        curl_setopt($cf_ch, CURLOPT_HTTPHEADER, ["X-Auth-Email: " . $cf_email, "X-Auth-Key: " . $cf_api_key, "Content-Type: application/json"]);
        curl_exec($cf_ch); curl_close($cf_ch);

        $final_ip = ($allocation_type === 'full') ? $clean_subdomain . ".davidgames.uk" : $clean_subdomain . ".davidgames.uk:" . $assigned_port;
        echo json_encode(["status" => "success", "message" => "Szerver sikeresen létrehozva!", "domain" => $final_ip]);
    } else {
        $details = $responseData['errors']['detail'] ?? "API Hiba.";
        echo json_encode(["status" => "error", "message" => "Hiba történt: " . $details]);
    }
    exit();
}
?>
