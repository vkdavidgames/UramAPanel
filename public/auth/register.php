<?php
ini_set('display_errors', 0);

// 1. Detect visitor browser language (defaults to English unless browser prefers Hungarian)
$accept_lang = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en');
$lang = (strpos($accept_lang, 'hu') === 0 || strpos($accept_lang, ',hu') !== false) ? 'hu' : 'en';

// 2. Translation dictionary (UI + JSON API messages)
$t = [
    'hu' => [
        'title' => 'DavidGames - Regisztráció',
        'heading' => 'Regisztráció',
        'username' => 'Felhasználónév',
        'email' => 'Email cím',
        'password' => 'Jelszó',
        'submit' => 'Fiók Létrehozása 🔑',
        'back' => 'Vissza a bejelentkezéshez',
        'checking' => 'Csalásvédelem ellenőrzése...',
        'generic_err' => 'Hiba történt.',
        'db_err' => 'Adatbázis hiba.',
        'req_fields' => 'Minden mező kitöltése kötelező!',
        'bad_email' => 'Érvénytelen email formátum!',
        'cookie_dup' => 'Csalás észlelve! Erről a számítógépről már regisztráltak egy fiókot!',
        'ip_dup' => 'Erről az IP címről már regisztráltak egy fiókot!',
        'fp_dup' => 'Csalásvédelem: Ez a számítógép már össze van kapcsolva egy másik fiókkal!',
        'user_dup' => 'Ez a felhasználónév vagy email már foglalt!',
        'success' => 'Fiók sikeresen létrehozva! Most már bejelentkezhetsz!',
        'reg_fail' => 'Hiba történt a regisztráció során. Próbáld újra!',
    ],
    'en' => [
        'title' => 'DavidGames - Register',
        'heading' => 'Create Account',
        'username' => 'Username',
        'email' => 'Email Address',
        'password' => 'Password',
        'submit' => 'Create Account 🔑',
        'back' => 'Back to Login',
        'checking' => 'Checking anti-fraud protection...',
        'generic_err' => 'An error occurred.',
        'db_err' => 'Database error.',
        'req_fields' => 'All fields are required!',
        'bad_email' => 'Invalid email format!',
        'cookie_dup' => 'Fraud detected! An account has already been registered from this computer!',
        'ip_dup' => 'An account has already been registered from this IP address!',
        'fp_dup' => 'Anti-fraud: This computer is already linked to another account!',
        'user_dup' => 'This username or email is already taken!',
        'success' => 'Account created successfully! You can now log in!',
        'reg_fail' => 'An error occurred during registration. Please try again!',
    ],
][$lang];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Content-Type: application/json");

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    if ($conn->connect_error) { die(json_encode(["status" => "error", "message" => $t['db_err']])); }

    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $browser_fp = trim($_POST['browser_fp'] ?? '');

    if (empty($email) || empty($username) || empty($password)) {
        die(json_encode(["status" => "error", "message" => $t['req_fields']]));
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(["status" => "error", "message" => $t['bad_email']]));
    }

    // 1. COOKIE ALAPÚ MULTIACCOUNT ELLENŐRZÉS
    if (isset($_COOKIE['dg_secure_reg_token'])) {
        die(json_encode(["status" => "error", "message" => $t['cookie_dup']]));
    }

    $user_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

    // 2. IP CÍM ALAPÚ MULTIACCOUNT ELLENŐRZÉS
    $stmt = $conn->prepare("SELECT id FROM users_security_meta WHERE last_ip = ?");
    $stmt->bind_param("s", $user_ip); $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $stmt->close(); die(json_encode(["status" => "error", "message" => $t['ip_dup']])); }
    $stmt->close();

    // 3. BÖNGÉSZŐ UJJLENYOMAT ALAPÚ ELLENŐRZÉS
    if (!empty($browser_fp)) {
        $stmt = $conn->prepare("SELECT id FROM users_security_meta WHERE browser_hash = ?");
        $stmt->bind_param("s", $browser_fp); $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) { $stmt->close(); die(json_encode(["status" => "error", "message" => $t['fp_dup']])); }
        $stmt->close();
    }

    // Gyári felhasználónév és email duplikáció ellenőrzés
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $email, $username); $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $stmt->close(); die(json_encode(["status" => "error", "message" => $t['user_dup']])); }
    $stmt->close();

    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $uuid = bin2hex(random_bytes(16));

    // Also sets Pterodactyl's user language column to match their browser ($lang)
    $stmt = $conn->prepare("INSERT INTO users (uuid, username, email, password, language, root_admin, use_totp, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, 0, NOW(), NOW())");
    $stmt->bind_param("sssss", $uuid, $username, $email, $hashed_password, $lang);

    if ($stmt->execute()) {
        $pterodactyl_user_id = $stmt->insert_id;
        $stmt->close();

        $stmt_custom = $conn->prepare("INSERT INTO users_security_meta (pterodactyl_user_id, has_free_server, `rank`, last_ip, browser_hash) VALUES (?, 1, 0, ?, ?)");
        $stmt_custom->bind_param("iss", $pterodactyl_user_id, $user_ip, $browser_fp);
        $stmt_custom->execute(); $stmt_custom->close();

        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        setcookie('dg_secure_reg_token', md5($username . time()), time() + (10 * 365 * 24 * 60 * 60), '/', '', $is_https, true);

        echo json_encode(["status" => "success", "message" => $t['success']]);
    } else {
        echo json_encode(["status" => "error", "message" => $t['reg_fail']]);
    }
    exit();
}
?>
</head>
<body>
    <div class="reg-box">
        <h2><?= htmlspecialchars($t['heading']) ?></h2>
        <form id="regForm">
            <input type="hidden" id="browser_fp" name="browser_fp">
            <div class="form-group"><label><?= htmlspecialchars($t['username']) ?></label><input type="text" id="username" name="username" autocomplete="username" required></div>
            <div class="form-group"><label><?= htmlspecialchars($t['email']) ?></label><input type="email" id="email" name="email" autocomplete="email" required></div>
            <div class="form-group"><label><?= htmlspecialchars($t['password']) ?></label><input type="password" id="password" name="password" autocomplete="new-password" required></div>
            <button type="submit"><?= htmlspecialchars($t['submit']) ?></button>
        </form>
        <a href="/auth/login" class="back-link"><?= htmlspecialchars($t['back']) ?></a>
        <div id="msg"></div>
    </div>
    <script>
    const TEXT_CHECKING = <?= json_encode($t['checking']) ?>;
    const TEXT_GENERIC_ERR = <?= json_encode($t['generic_err']) ?>;

    function generateFingerprint() {
        const fp = [window.screen.width, window.screen.height, window.screen.colorDepth, navigator.language, navigator.hardwareConcurrency, new Date().getTimezoneOffset()].join('|');
        let hash = 0;
        for (let i = 0; i < fp.length; i++) { hash = (hash << 5) - hash + fp.charCodeAt(i); hash |= 0; }
        document.getElementById('browser_fp').value = Math.abs(hash);
    }
    window.addEventListener('DOMContentLoaded', generateFingerprint);

    document.getElementById('regForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const m = document.getElementById('msg');
        m.style.color = '#fbbf24';
        m.innerText = TEXT_CHECKING;
        try {
            const res = await fetch('register.php', { method: 'POST', body: new FormData(this) });
            const json = await res.json();
            if (json.status === 'success') {
                m.style.color = '#34d399';
                m.innerText = json.message;
                setTimeout(() => { window.location = '/auth/login'; }, 2000);
            } else {
                m.style.color = '#f87171';
                m.innerText = json.message;
            }
        } catch (err) {
            m.style.color = '#f87171';
            m.innerText = TEXT_GENERIC_ERR;
        }
    });
    </script>
</body>
</html>
