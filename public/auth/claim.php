<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Kézzel beolvassuk a Pterodactyl .env fájlját a biztonságos jelszavakért
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
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DavidGames - Szerver Igénylő Központ</title>
    <style>
        body { background: #0c0c0e !important; color: #e4e4e7 !important; font-family: system-ui, sans-serif !important; display: block !important; margin: 0 !important; padding: 15px !important; box-sizing: border-box !important; width: 100% !important; height: auto !important; }
        .config-box { background: #141417 !important; padding: 20px !important; border-radius: 12px !important; border: 1px solid #27272a !important; width: 100% !important; max-width: 380px !important; box-shadow: 0 20px 40px rgba(0,0,0,0.6) !important; box-sizing: border-box !important; margin: 0 auto !important; }
        h2 { margin-top: 0 !important; margin-bottom: 15px !important; text-align: center !important; color: #34d399 !important; font-size: 1.4rem !important; }
        .user-info { background: #1c1c21 !important; padding: 10px !important; border-radius: 6px !important; border: 1px solid #2a2a32 !important; text-align: center !important; margin-bottom: 15px !important; font-size: 0.85rem !important; color: #a1a1aa !important; }
        .user-info strong { color: #34d399 !important; }
        #claimForm { display: grid !important; grid-template-columns: 1fr !important; gap: 12px !important; width: 100% !important; box-sizing: border-box !important; }
        .form-group { display: grid !important; grid-template-columns: 1fr !important; width: 100% !important; box-sizing: border-box !important; }
        label { display: block !important; margin-bottom: 6px !important; font-size: 0.75rem !important; font-weight: bold !important; color: #a1a1aa !important; text-transform: uppercase !important; }
        select, input[type="text"], input[type="range"] { width: 100% !important; background: #1c1c21 !important; border: 1px solid #2a2a32 !important; border-radius: 6px !important; color: #fff !important; padding: 10px !important; box-sizing: border-box !important; font-size: 0.9rem !important; display: block !important; }
        .range-container { display: flex !important; justify-content: space-between !important; align-items: center !important; background: #1c1c21 !important; padding: 8px 12px !important; border-radius: 6px !important; border: 1px solid #2a2a32 !important; margin-top: 4px !important; }
        .range-value { font-weight: bold !important; color: #34d399 !important; font-family: monospace !important; }
        .disabled-info { font-size: 0.7rem !important; margin-top: 4px !important; font-weight: 500 !important; display: block !important; }
        button { width: 100% !important; padding: 12px !important; background: #34d399 !important; border: none !important; border-radius: 6px !important; color: #064e3b !important; font-weight: bold !important; cursor: pointer !important; font-size: 1rem !important; margin-top: 15px !important; display: block !important; }
        button:hover { background: #059669 !important; color: #fff !important; }
        #log-msg { margin-top: 15px !important; text-align: center !important; font-weight: bold !important; font-size: 0.9rem !important; }
    </style>
</head>
<body>
    <div class="config-box">
        <h2>Szerver Igénylés</h2>
        <div id="user_display" class="user-info">Azonosítás folyamatban...</div>
        <form id="claimForm">
            <div class="form-group">
                <label for="game">Válassz Játékot</label>
                <select id="game" name="game"><option value="minecraft">Minecraft (Java Edition)</option></select>
            </div>
            <div class="form-group">
                <label for="server_type">Szerver Típusa</label>
                <select id="server_type" name="server_type" onchange="toggleLoader()">
                    <option value="vanilla">Minecraft Vanilla (Tiszta gyári)</option>
                    <option value="modded">Modolt Minecraft (Forge / Fabric / NeoForge)</option>
                    <option value="plugins">Pluginolt Minecraft (Paper / Purpur)</option>
                    <option value="bungeecord">BungeeCord / Velocity Proxy</option>
                </select>
            </div>
            <div id="loader_container" class="form-group" style="display: none;">
                <label id="loader_title" for="loader">Szoftver Típusa</label>
                <select id="loader" name="loader">
                    <option value="none">Válassz típust...</option>
                </select>
                <div id="loader_ver_hide" style="margin-top: 10px !important;">
                    <label for="loader_version">Mod Loader Verzió</label>
                    <input type="text" id="loader_version" name="loader_version" value="latest">
                </div>
            </div>
            <div class="form-group">
                <label for="custom_subdomain">Kívánt Aldomain név</label>
                <input type="text" id="custom_subdomain" name="custom_subdomain" placeholder="Pl. uramaram" required>
            </div>
            <div class="form-group">
                <label for="allocation_type">Foglalás Típusa</label>
                <select id="allocation_type" name="allocation_type" onchange="toggleAllocationFields()">
                    <option value="port" selected>Csak Port lefoglalása (Egyedi Port)</option>
                    <option value="full">Teljes Aldomain lefoglalása (Alapértelmezett 25565 port) 🌍</option>
                </select>
            </div>
            <div id="custom_port_container" class="form-group">
                <label for="custom_port">Kívánt Egyedi Port</label>
                <input type="text" id="custom_port" name="custom_port" placeholder="Hagyd üresen automatikus porthoz">
            </div>
            <div class="form-group">
                <label for="mc_version">Minecraft Verzió</label>
                <input type="text" id="mc_version" name="mc_version" value="1.21.1" required>
            </div>
            <div class="form-group">
                <label for="java_version">Java Környezet</label>
                <select id="java_version" name="java_version">
                    <option value="25">Java 25</option>
                    <option value="21" selected>Java 21</option>
                    <option value="17">Java 17</option>
                    <option value="8">Java 8</option>
                </select>
            </div>
            <div class="form-group">
                <label>Rendszermemória (RAM)</label>
                <input type="range" id="ram" min="1024" max="2048" value="1024" step="1024" oninput="updateRamValue(this.value)">
                <div class="range-container"><span>RAM mérete:</span><span id="ram_display" class="range-value">1 GB</span></div>
                <div id="ram_info" class="disabled-info" style="color: #fbbf24 !important;">📦 Alapcsomag (Max 2GB).</div>
            </div>
            <div class="form-group">
                <label>Tárhely (Disk Space)</label>
                <input type="range" id="disk" min="2048" max="5120" value="2048" step="1024" oninput="updateDiskValue(this.value)">
                <div class="range-container"><span>Tárhely mérete:</span><span id="disk_display" class="range-value">2 GB</span></div>
                <div id="disk_info" class="disabled-info" style="color: #fbbf24 !important;">📦 Alapcsomag Tárhely (Max 5GB).</div>
            </div>
            <button type="submit">Szerver Életre Keltése 🚀</button>
        </form>
        <div id="log-msg"></div>
    </div>
    <script>
        let globalUserId = 0; let globalUsername = "";
        function updateRamValue(val) { document.getElementById('ram_display').innerText = (val / 1024) + " GB (" + val + " MB)"; }
        function updateDiskValue(val) { document.getElementById('disk_display').innerText = (val / 1024) + " GB (" + val + " MB)"; }
        function toggleAllocationFields() {
            const type = document.getElementById('allocation_type').value;
            document.getElementById('custom_port_container').style.display = (type === 'full') ? 'none' : 'block';
            if(type === 'full') document.getElementById('custom_port').value = '25565';
        }
        function toggleLoader() {
            const type = document.getElementById('server_type').value;
            const container = document.getElementById('loader_container');
            const loaderSelect = document.getElementById('loader');
            const verHide = document.getElementById('loader_ver_hide');
            if (type === 'modded') {
                container.style.display = 'block'; verHide.style.display = 'block';
                loaderSelect.innerHTML = '<option value="neoforge">NeoForge</option><option value="forge">Forge</option><option value="fabric">Fabric</option>';
            } else if (type === 'plugins') {
                container.style.display = 'block'; verHide.style.display = 'none';
                loaderSelect.innerHTML = '<option value="paper">Paper</option><option value="purpur">Purpur</option>';
            } else { container.style.display = 'none'; }
        }
        async function identifyUser() {
            try {
                const siteConfig = window.parent.SiteConfiguration;
                if (siteConfig && siteConfig.user) {
                    globalUserId = siteConfig.user.id; globalUsername = siteConfig.user.username;
                } else {
                    const metaUser = window.parent.document.querySelector('meta[name="user-id"]');
                    globalUserId = metaUser ? parseInt(metaUser.getAttribute('content')) : 1;
                    globalUsername = metaUser ? metaUser.getAttribute('username') : "DavidAdmin";
                }
                document.getElementById('user_display').innerHTML = "Bejelentkezve: <strong>" + globalUsername + "</strong>";
                checkPromoStatus(globalUserId);
            } catch (e) {
                globalUserId = 1; globalUsername = "DavidAdmin";
                document.getElementById('user_display').innerHTML = "🔧 Teszt üzemmód (ID: 1)";
                setupPromoLimits(true);
            }
        }
        async function checkPromoStatus(uid) {
            try {
                const formData = new FormData(); formData.append('check_uid', uid);
                const res = await fetch('server_claim.php', { method: 'POST', body: formData });
                const json = await res.json();
                setupPromoLimits(json.user_rank === 1);
            } catch(err) { setupPromoLimits(false); }
        }
        function setupPromoLimits(isVIP) {
            const ram = document.getElementById('ram'); const disk = document.getElementById('disk');
            if (isVIP) {
                ram.max = "4096"; ram.value = "4096"; document.getElementById('ram_info').innerHTML = "💎 VIP: 4GB RAM nyitva!";
                disk.max = "10240"; disk.value = "10240"; document.getElementById('disk_info').innerHTML = "💎 VIP: 10GB Tárhely nyitva!";
            } else { ram.max = "2048"; disk.max = "5120"; }
            updateRamValue(ram.value); updateDiskValue(disk.value);
        }
        window.addEventListener('DOMContentLoaded', () => { toggleLoader(); toggleAllocationFields(); identifyUser(); });

        document.getElementById('claimForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const logDiv = document.getElementById('log-msg'); logDiv.style.color = '#fbbf24'; logDiv.innerText = 'Szerver konténer építése folyamatban...';
            const formData = new FormData(this);
            formData.append('user_id', globalUserId); formData.append('username', globalUsername);
            try {
                const response = await fetch('server_claim.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.status === 'success') {
                    logDiv.style.color = '#34d399'; logDiv.innerHTML = "✨ " + result.message + "<br>IP: <strong>" + result.domain + "</strong>";
                } else { logDiv.style.color = '#f87171'; logDiv.innerText = result.message; }
            } catch (err) { logDiv.style.color = '#f87171'; logDiv.innerText = 'Hiba történt.'; }
        });
    </script>
</body>
</html>
