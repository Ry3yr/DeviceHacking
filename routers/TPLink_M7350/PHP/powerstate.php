<?php
/**
 * M7350 Automated Power Control
 * Authenticates automatically on page load.
 */

$host = "192.168.0.1";
$password_file = 'password.txt';

// --- Core Helper Functions ---

function router_request($url, $data, $cookies = []) {
    $ch = curl_init($url);
    $payload = json_encode($data);
    $headers = [
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With: XMLHttpRequest',
        'Referer: http://192.168.0.1/settings.html'
    ];

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    if (!empty($cookies)) {
        $cookie_list = [];
        foreach ($cookies as $k => $v) $cookie_list[] = "$k=$v";
        curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookie_list));
    }

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function perform_auto_login($host, $password_file) {
    if (!file_exists($password_file)) return ['error' => 'password.txt not found'];
    $password = trim(file_get_contents($password_file));

    // Step 1: Get Nonce
    $nonce_data = router_request("http://$host/cgi-bin/auth_cgi", ["module" => "authenticator", "action" => 0]);
    $nonce = $nonce_data['nonce'] ?? null;
    if (!$nonce) return ['error' => 'Failed to retrieve nonce'];

    // Step 2: Login
    $digest = md5($password . ":" . $nonce);
    $login_data = router_request("http://$host/cgi-bin/auth_cgi", ["module" => "authenticator", "action" => 1, "digest" => $digest]);
    
    if (isset($login_data['token'])) {
        return ['token' => $login_data['token'], 'success' => true];
    }
    return ['error' => 'Login failed - check password'];
}

// --- Logic Routing ---

// Handle AJAX command requests from the buttons
if (isset($_GET['execute'])) {
    header('Content-Type: application/json');
    $token = $_POST['token'] ?? '';
    $action = (int)($_POST['action'] ?? 0);

    $res = router_request(
        "http://$host/cgi-bin/web_cgi", 
        ["token" => $token, "module" => "reboot", "action" => $action],
        ["check_cookie" => "check_cookie", "tpweb_token" => $token]
    );

    echo json_encode($res);
    exit;
}

// Perform Auto-Auth for the initial page load
$auth = perform_auto_login($host, $password_file);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>M7350 Auto-Auth Control</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #121212; color: #00ff00; padding: 20px; }
        .container { max-width: 700px; margin: auto; border: 2px solid #00ff00; padding: 25px; background: #0a0a0a; box-shadow: 0 0 15px rgba(0,255,0,0.2); }
        .status-bar { border-bottom: 1px solid #333; padding-bottom: 15px; margin-bottom: 20px; font-size: 0.9em; }
        .card { border: 1px solid #444; padding: 20px; margin-bottom: 20px; border-radius: 4px; }
        .btn { background: transparent; color: #00ff00; border: 1px solid #00ff00; padding: 10px 25px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #00ff00; color: #000; }
        .btn-danger { color: #ff5555; border-color: #ff5555; }
        .btn-danger:hover { background: #ff5555; color: #fff; }
        .error-msg { color: #ff5555; font-weight: bold; }
        .token-val { color: #888; font-family: monospace; }
    </style>
</head>
<body>

<div class="container">
    <h2>M7350 Power Control</h2>

    <div class="status-bar">
        <?php if ($auth['success']): ?>
            <span style="color: #00ff00;">● SYSTEM ONLINE (Authenticated)</span><br>
            <span class="token-val">Session Token: <?php echo substr($auth['token'], 0, 16); ?>...</span>
        <?php else: ?>
            <span class="error-msg">● SYSTEM OFFLINE: <?php echo $auth['error']; ?></span>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>System Reboot</h3>
        <p>Restart the cellular module and WiFi. Takes ~60-90 seconds.</p>
        <button class="btn" onclick="runCommand(0)" <?php echo !$auth['success'] ? 'disabled' : ''; ?>>REBOOT NOW</button>
    </div>

    <div class="card" style="border-color: #ff5555;">
        <h3 style="color: #ff5555;">Shutdown</h3>
        <p>Complete power off. Requires physical button to turn back on.</p>
        <button class="btn btn-danger" onclick="runCommand(1)" <?php echo !$auth['success'] ? 'disabled' : ''; ?>>POWER OFF</button>
    </div>
</div>

<script>
async function runCommand(actionCode) {
    const label = actionCode === 0 ? "Reboot" : "Power Off";
    if (!confirm(`Are you sure you want to ${label}?`)) return;

    const formData = new FormData();
    formData.append('token', '<?php echo $auth['token'] ?? ''; ?>');
    formData.append('action', actionCode);

    try {
        const response = await fetch('?execute=1', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.result === 1) {
            alert(label + " command accepted by router.");
        } else {
            alert("Router rejected the command: " + JSON.stringify(data));
        }
    } catch (err) {
        alert("Connection lost or error occurred.");
    }
}
</script>

</body>
</html>