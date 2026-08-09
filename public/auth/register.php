<?php
ini_set('display_errors', 0);
header("Content-Type: application/json");

// Beolvassuk a Pterodactyl .env fájlját a gyári adatokkal
$env_file = '/var/www/pterodactyl/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Gyári Pterodactyl adatbázis adatok kiszedése a .env-ből
$db_host = $_ENV['DB_HOST'];
$db_port = $_ENV['DB_PORT'];
$db_user = $_ENV['DB_USERNAME'];
$db_pass = $_ENV['DB_PASSWORD'];
$db_name = $_ENV['DB_DATABASE'];

// Első kapcsolat: A gyári panel adatbázis
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) { die(json_encode(["status" => "error", "message" => "Panel adatbázis hiba."])); }

// Második kapcsolat: Az egyedi cracked_auth tábla (ugyanazzal a felhasználóval és jelszóval)
$custom_conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($custom_conn->connect_error) { die(json_encode(["status" => "error", "message" => "Egyedi adatbázis hiba."])); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($username) || empty($password)) {
        die(json_encode(["status" => "error", "message" => "Minden mező kitöltése kötelező!"]));
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(["status" => "error", "message" => "Érvénytelen email formátum!"]));
    }

    // Felhasználó létezésének ellenőrzése
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $email, $username); $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $stmt->close(); die(json_encode(["status" => "error", "message" => "Ez a felhasználónév vagy email már foglalt!"])); }
    $stmt->close();

    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $uuid = bin2hex(random_bytes(16)); 

    // Mentés a gyári felhasználók közé
    $stmt = $conn->prepare("INSERT INTO users (uuid, username, email, password, root_admin, created_at, updated_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())");
    $stmt->bind_param("ssss", $uuid, $username, $email, $hashed_password);
    
    if ($stmt->execute()) {
        $pterodactyl_user_id = $stmt->insert_id;
        $stmt->close();

        // Mentés az egyedi szerverigénylési meta táblába (Alapcsomag jogokkal)
        $stmt_custom = $custom_conn->prepare("INSERT INTO users_security_meta (pterodactyl_user_id, has_free_server, `rank`) VALUES (?, 1, 0)");
        $stmt_custom->bind_param("i", $pterodactyl_user_id);
        $stmt_custom->execute(); $stmt_custom->close();

        echo json_encode(["status" => "success", "message" => "Fiók sikeresen létrehozva! Most már bejelentkezhetsz!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Hiba történt a regisztráció során. Próbáld újra!"]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>DavidGames - Regisztráció</title>
    <style>
        body { background: #0c0c0e; color: #e4e4e7; font-family: system-ui, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .reg-box { background: #141417; padding: 30px; border-radius: 12px; border: 1px solid #27272a; width: 100%; max-width: 360px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        h2 { margin-top: 0; text-align: center; color: #34d399; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        label { font-size: 0.75rem; font-weight: bold; color: #a1a1aa; text-transform: uppercase; margin-bottom: 6px; }
        input[type="text"], input[type="email"], input[type="password"] { background: #1c1c21; border: 1px solid #2a2a32; border-radius: 6px; color: #fff; padding: 12px; font-size: 0.9rem; }
        input:focus { border-color: #34d399; outline: none; }
        button { width: 100%; padding: 12px; background: #34d399; border: none; border-radius: 6px; color: #064e3b; font-weight: bold; cursor: pointer; font-size: 1rem; margin-top: 10px; }
        button:hover { background: #059669; color: #fff; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #a1a1aa; text-decoration: none; font-size: 0.85rem; }
        .back-link:hover { color: #fff; }
        #msg { margin-top: 15px; text-align: center; font-weight: bold; }
    </style>
</head>
<body>
    <div class="reg-box">
        <h2>Regisztráció</h2>
        <form id="regForm">
            <div class="form-group"><label>Felhasználónév</label><input type="text" id="username" name="username" required></div>
            <div class="form-group"><label>Email cím</label><input type="email" id="email" name="email" required></div>
            <div class="form-group"><label>Jelszó</label><input type="password" id="password" name="password" required></div>
            <button type="submit">Fiók Létrehozása 🔑</button>
        </form>
        <a href="/auth/login" class="back-link">Vissza a bejelentkezéshez</a>
        <div id="msg"></div>
    </div>
    <script>
    document.getElementById('regForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const m = document.getElementById('msg'); m.style.color = '#fbbf24'; m.innerText = 'Fiók rögzítése az adatbázisban...';
        try {
            const res = await fetch('register.php', { method: 'POST', body: new FormData(this) });
            const json = await res.json();
            if (json.status === 'success') { m.style.color = '#34d399'; m.innerText = json.message; setTimeout(() => window.location = '/auth/login', 2000); }
            else { m.style.color = '#f87171'; m.innerText = json.message; }
        } catch(e) { m.style.color = '#f87171'; m.innerText = 'Hiba történt.'; }
    });
    </script>
</body>
</html>
