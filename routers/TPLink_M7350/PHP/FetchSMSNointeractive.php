<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>M7350 SMS Parser - Auto Run</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #121212; color: #00ff00; padding: 20px; }
        .container { max-width: 800px; margin: auto; border: 1px solid #00ff00; padding: 20px; box-shadow: 0 0 15px #00ff0033; }
        .handshake { color: #888; font-size: 0.8em; margin-bottom: 20px; border-bottom: 1px dashed #444; padding-bottom: 10px; }
        .sms-entry { border: 1px solid #333; padding: 10px; margin-bottom: 15px; background: #1a1a1a; position: relative; }
        .label { color: #0088ff; font-weight: bold; }
        .unread-tag { position: absolute; top: 10px; right: 10px; color: red; font-size: 0.7em; border: 1px solid red; padding: 2px 5px; }
        #status { color: #ff8800; margin: 10px 0; }
        .pass-info { font-size: 0.8em; color: #666; margin-top: 5px; }
        .auto-note { color: #ffff00; font-size: 0.9em; margin-bottom: 10px; padding: 5px; background: #222; }
    </style>
</head>
<body>
    <div class="container">
        <h3>M7350 SMS Reader</h3>
        
        <div class="auto-note">✓ Auto-running parser on page load...</div>
        
        <?php
        $host = "192.168.0.1";
        $status = "Initializing...";
        $output = "";
        $n_out = "---";
        $t_out = "---";
        
        try {
            // Read password from password.txt
            $status = "Reading local password.txt...";
            if (!file_exists('password.txt')) {
                throw new Exception("Could not find password.txt in directory.");
            }
            $password = trim(file_get_contents('password.txt'));
            
            // Step 1: Get nonce
            $status = "Connecting to router...";
            $nonceData = json_encode(['module' => 'authenticator', 'action' => 0]);
            
            $ch = curl_init("http://{$host}/cgi-bin/auth_cgi");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $nonceData,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded; charset=UTF-8'],
                CURLOPT_TIMEOUT => 10,
            ]);
            
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("Connection failed: " . curl_error($ch));
            }
            curl_close($ch);
            
            $nonceResult = json_decode($response, true);
            if (!$nonceResult || !isset($nonceResult['nonce'])) {
                throw new Exception("Failed to get nonce from router.");
            }
            
            $nonce = $nonceResult['nonce'];
            $n_out = htmlspecialchars($nonce);
            
            // Step 2: Calculate digest and get token
            $digest = md5($password . ':' . $nonce);
            $loginData = json_encode([
                'module' => 'authenticator',
                'action' => 1,
                'digest' => $digest
            ]);
            
            $ch = curl_init("http://{$host}/cgi-bin/auth_cgi");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $loginData,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded; charset=UTF-8'],
                CURLOPT_TIMEOUT => 10,
            ]);
            
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("Login failed: " . curl_error($ch));
            }
            curl_close($ch);
            
            $loginResult = json_decode($response, true);
            if (!$loginResult || !isset($loginResult['token'])) {
                throw new Exception("Login Failed - check password.txt content.");
            }
            
            $token = $loginResult['token'];
            $t_out = htmlspecialchars($token);
            
            // Step 3: Fetch SMS messages
            $status = "Fetching SMS JSON...";
            $smsData = json_encode([
                'token' => $token,
                'module' => 'message',
                'action' => 2,
                'pageNumber' => 1,
                'amountPerPage' => 8,
                'box' => 0
            ]);
            
            $ch = curl_init("http://{$host}/cgi-bin/web_cgi");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $smsData,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded; charset=UTF-8'],
                CURLOPT_TIMEOUT => 10,
            ]);
            
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("Failed to fetch SMS: " . curl_error($ch));
            }
            curl_close($ch);
            
            $smsResult = json_decode($response, true);
            
            // Process SMS messages
            if (isset($smsResult['messageList']) && count($smsResult['messageList']) > 0) {
                $smsList = $smsResult['messageList'];
                
                foreach ($smsList as $m) {
                    $unreadTag = isset($m['unread']) && $m['unread'] ? '<span class="unread-tag">NEW</span>' : '';
                    $from = htmlspecialchars($m['from'] ?? 'Unknown');
                    $date = htmlspecialchars($m['receivedTime'] ?? 'Unknown');
                    $content = htmlspecialchars($m['content'] ?? '');
                    $index = htmlspecialchars($m['index'] ?? '');
                    
                    $output .= "
                    <div class='sms-entry'>
                        {$unreadTag}
                        <div><span class='label'>From:</span> {$from}</div>
                        <div><span class='label'>Date:</span> {$date}</div>
                        <div style='margin-top:10px; color:#fff;'>{$content}</div>
                        <div style='font-size:0.7em; color:#444; margin-top:5px;'>Index: {$index}</div>
                    </div>
                    ";
                }
                
                $total = $smsResult['totalNumber'] ?? count($smsList);
                $status = "Success: {$total} messages found.";
            } else {
                $status = "Inbox is empty.";
            }
            
        } catch (Exception $e) {
            $status = "Error: " . htmlspecialchars($e->getMessage());
        }
        ?>
        
        <div class="handshake">
            <div class="pass-info">Reading credentials from <i>password.txt</i>...</div>
            <div style="margin-top:10px;">
                Nonce: <span id="n-out" style="color:#d2a8ff"><?php echo $n_out; ?></span> | 
                Token: <span id="t-out" style="color:#d2a8ff"><?php echo $t_out; ?></span>
            </div>
        </div>

        <div id="status"><?php echo htmlspecialchars($status); ?></div>
        <div id="output"><?php echo $output; ?></div>
    </div>
</body>
</html>