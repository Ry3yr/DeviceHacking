<?php
// Define host and file locations
$host = "192.168.0.1";
$passwordFile = "password.txt";

// Function to make a cURL request
function curl_request($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Read password from file
$password = file_get_contents($passwordFile);
if (!$password) {
    echo "Error: Could not read password from $passwordFile.\n";
    exit(1);
}
$password = trim($password);

// 1. Get Nonce (Silent)
$nonce_response = curl_request("http://$host/cgi-bin/auth_cgi", [
    "module" => "authenticator",
    "action" => 0
]);

$nonce_data = json_decode($nonce_response, true);
$nonce = $nonce_data['nonce'] ?? null;

if (!$nonce) {
    echo "Error: Failed to get nonce\n";
    exit(1);
}

// 2. Login (Silent)
$digest = md5("$password:$nonce");
$login_response = curl_request("http://$host/cgi-bin/auth_cgi", [
    "module" => "authenticator",
    "action" => 1,
    "digest" => $digest
]);

$login_data = json_decode($login_response, true);
$token = $login_data['token'] ?? null;

if (!$token) {
    echo "Error: Login failed - check password\n";
    exit(1);
}

// 3. Fetch SMS
$sms_response = curl_request("http://$host/cgi-bin/web_cgi", [
    "token" => $token,
    "module" => "message",
    "action" => 2,
    "pageNumber" => 1,
    "amountPerPage" => 8,
    "box" => 0
]);

// DEBUGGING: Output the raw SMS response for inspection
echo "<pre>Raw SMS Response: " . htmlspecialchars($sms_response) . "</pre>";

$sms_data = json_decode($sms_response, true);

// DEBUGGING: Output the parsed JSON for inspection
echo "<pre>Parsed SMS Data: ";
print_r($sms_data);
echo "</pre>";

$sms_list = $sms_data['messageList'] ?? [];
$total_messages = $sms_data['totalNumber'] ?? 0;

// HTML Structure
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>M7350 SMS Parser</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #121212; color: #00ff00; padding: 20px; }
        .container { max-width: 800px; margin: auto; border: 1px solid #00ff00; padding: 20px; box-shadow: 0 0 15px #00ff0033; }
        .sms-entry { border: 1px solid #333; padding: 10px; margin-bottom: 15px; background: #1a1a1a; position: relative; }
        .label { color: #0088ff; font-weight: bold; }
        .unread-tag { position: absolute; top: 10px; right: 10px; color: red; font-size: 0.7em; border: 1px solid red; padding: 2px 5px; }
        #status { color: #ff8800; margin: 10px 0; }
    </style>
</head>
<body>

    <div class='container'>
        <h3>M7350 SMS Reader</h3>
        <div id='status'>Initializing...</div>
        <div id='output'></div>
    </div>

    <script>
        document.getElementById('status').innerText = 'Fetching SMS...';
    </script>";

if ($sms_list) {
    echo "<div id='status'>Success: $total_messages messages found.</div>";

    foreach ($sms_list as $sms) {
        $from = htmlspecialchars($sms['from']);
        $date = htmlspecialchars($sms['receivedTime']);
        $content = nl2br(htmlspecialchars($sms['content']));
        $unread = isset($sms['unread']) && $sms['unread'] ? '<span class="unread-tag">NEW</span>' : '';
        
        echo "<div class='sms-entry'>
            $unread
            <div><span class='label'>From:</span> $from</div>
            <div><span class='label'>Date:</span> $date</div>
            <div style='margin-top:10px;'>$content</div>
        </div>";
    }
} else {
    echo "<div id='status'>Inbox is empty.</div>";
}

echo "</body></html>";
?>
