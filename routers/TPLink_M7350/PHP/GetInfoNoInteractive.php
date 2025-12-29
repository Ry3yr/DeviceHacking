<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>M7350 Quick Status - Auto Run</title>
    <style>
        body { 
            font-family: 'Courier New', monospace; 
            background: #121212; 
            color: #00ff00; 
            padding: 20px; 
            line-height: 1.6;
        }
        .container { 
            max-width: 800px; 
            margin: auto; 
            border: 2px solid #00ff00; 
            padding: 25px; 
            box-shadow: 0 0 20px #00ff0044;
            background: #0a0a0a;
        }
        .handshake { 
            color: #888; 
            font-size: 0.8em; 
            margin-bottom: 25px; 
            border-bottom: 1px dashed #444; 
            padding-bottom: 15px; 
        }
        .status-container { 
            border: 1px solid #333; 
            padding: 20px; 
            margin-bottom: 20px; 
            background: #1a1a1a; 
            border-radius: 5px;
        }
        .status-header {
            color: #00ff00;
            font-size: 1.3em;
            border-bottom: 2px solid #00ff00;
            padding-bottom: 10px;
            margin-bottom: 20px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .status-row { 
            margin: 12px 0; 
            padding: 8px 0; 
            display: flex;
            align-items: center;
            border-bottom: 1px solid #222;
            transition: all 0.2s;
        }
        .status-row:hover {
            background: rgba(0, 255, 0, 0.05);
            padding-left: 10px;
        }
        .label { 
            color: #00aaff; 
            font-weight: bold; 
            width: 180px;
            flex-shrink: 0;
        }
        .value { 
            color: #ffffff; 
            font-family: 'Courier New', monospace;
            flex-grow: 1;
        }
        .value-highlight {
            color: #00ffaa;
            font-weight: bold;
        }
        .value-note {
            color: #888;
            font-size: 0.9em;
            font-style: italic;
        }
        #status { 
            color: #ffaa00; 
            margin: 15px 0; 
            font-weight: bold;
            padding: 10px;
            background: rgba(255, 170, 0, 0.1);
            border-left: 3px solid #ffaa00;
        }
        .pass-info { 
            font-size: 0.8em; 
            color: #666; 
            margin-top: 5px; 
        }
        .auto-note { 
            color: #ffff00; 
            font-size: 0.9em; 
            margin-bottom: 15px; 
            padding: 10px; 
            background: #222; 
            border-radius: 3px;
            border-left: 3px solid #ffff00;
        }
        .refresh-btn {
            background: #222;
            color: #00ff00;
            border: 2px solid #00ff00;
            padding: 10px 20px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            transition: all 0.3s;
            margin-top: 15px;
            display: inline-block;
        }
        .refresh-btn:hover {
            background: #00ff00;
            color: #121212;
            transform: translateY(-2px);
            box-shadow: 0 0 15px #00ff00;
        }
        .error {
            color: #ff5555;
            border: 1px solid #ff5555;
            padding: 15px;
            background: rgba(255, 85, 85, 0.1);
            margin: 15px 0;
            border-radius: 3px;
        }
        .success {
            color: #00ff00;
            padding: 10px;
            text-align: center;
            font-weight: bold;
        }
        .bytes-info {
            font-size: 0.85em;
            color: #888;
            margin-top: 5px;
            padding-left: 180px;
        }
        .connection-status {
            display: inline-block;
            padding: 3px 8px;
            background: #00aa00;
            color: white;
            border-radius: 3px;
            font-size: 0.9em;
            margin-left: 10px;
        }
        .network-type {
            display: inline-block;
            padding: 3px 8px;
            background: #0055aa;
            color: white;
            border-radius: 3px;
            font-size: 0.9em;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h3 style="text-align: center; color: #00ff00; margin-bottom: 10px;">TP-Link M7350 Quick Status</h3>
        <h4 style="text-align: center; color: #888; margin-top: 0; margin-bottom: 20px;">Auto-run on page load</h4>
        
        <div class="auto-note">⚠️ Fetching quick status from router...</div>
        
        <?php
        $host = "192.168.0.1";
        $status = "Initializing connection to router...";
        $output = "";
        $n_out = "---";
        $t_out = "---";
        $success = false;
        
        try {
            // Read password from password.txt
            $status = '<span style="color:#ffff00">⚠️ Reading password from password.txt...</span>';
            if (!file_exists('password.txt')) {
                throw new Exception("Could not find password.txt in directory.");
            }
            $password = trim(file_get_contents('password.txt'));
            
            // Step 1: Get nonce
            $status = '<span style="color:#ffff00">⚠️ Getting nonce from router...</span>';
            $nonceData = json_encode(['module' => 'authenticator', 'action' => 0]);
            
            $ch = curl_init("http://{$host}/cgi-bin/auth_cgi");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $nonceData,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With: XMLHttpRequest',
                    'Referer: http://' . $host . '/login.html'
                ],
                CURLOPT_TIMEOUT => 10,
            ]);
            
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("Connection failed: " . curl_error($ch));
            }
            curl_close($ch);
            
            $nonceResult = json_decode($response, true);
            if (!$nonceResult || !isset($nonceResult['nonce'])) {
                throw new Exception("Failed to get nonce from router");
            }
            
            $nonce = $nonceResult['nonce'];
            $n_out = htmlspecialchars($nonce);
            
            // Step 2: Calculate digest and get token
            $status = '<span style="color:#ffff00">⚠️ Logging in...</span>';
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
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With: XMLHttpRequest',
                    'Referer: http://' . $host . '/login.html'
                ],
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
            
            // Step 3: Fetch status information
            $status = '<span style="color:#ffff00">⚠️ Fetching status information...</span>';
            $statusData = json_encode([
                'token' => $token,
                'module' => 'status',
                'action' => 0
            ]);
            
            $ch = curl_init("http://{$host}/cgi-bin/web_cgi");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $statusData,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With: XMLHttpRequest',
                    'Referer: http://' . $host . '/settings.html'
                ],
                CURLOPT_TIMEOUT => 10,
            ]);
            
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("Failed to fetch status: " . curl_error($ch));
            }
            curl_close($ch);
            
            // Extract values from the JSON response
            function extractValue($data, $key) {
                $pattern = '/"' . preg_quote($key, '/') . '"\s*:\s*"([^"]*)"/';
                preg_match($pattern, $data, $matches);
                return $matches[1] ?? null;
            }
            
            // Extract values
            $ipv4 = extractValue($response, 'ipv4');
            $ssid = extractValue($response, 'ssid');
            $model = extractValue($response, 'model');
            $firmware = extractValue($response, 'firmwareVer');
            $mac = extractValue($response, 'mac');
            
            // Extract number of clients
            preg_match('/"number"\s*:\s*(\d+)/', $response, $clientsMatch);
            $clients = $clientsMatch[1] ?? '0';
            
            // Extract total statistics
            preg_match('/"totalStatistics"\s*:\s*"([^"]*)"/', $response, $bytesMatch);
            $bytesStr = $bytesMatch[1] ?? '0';
            $bytes = isset($bytesStr) ? intval(explode('.', $bytesStr)[0]) : 0;
            
            // Convert bytes to GB
            $gb = number_format($bytes / 1073741824, 2);
            
            // Build the output
            $output = '
            <div class="status-container">
                <div class="status-header">=== FINAL STATUS ===</div>
                
                <div class="status-row">
                    <span class="label">Connection Status:</span>
                    <span class="value">
                        Connected
                        <span class="connection-status">✓</span>
                    </span>
                </div>
                
                <div class="status-row">
                    <span class="label">Network Type:</span>
                    <span class="value">
                        LTE
                        <span class="network-type">4G</span>
                    </span>
                </div>
                
                <div class="status-row">
                    <span class="label">IPv4 Address:</span>
                    <span class="value value-highlight">' . htmlspecialchars($ipv4 ?? 'N/A') . '</span>
                </div>
                
                <div class="status-row">
                    <span class="label">SSID:</span>
                    <span class="value value-highlight">' . htmlspecialchars($ssid ?? 'N/A') . '</span>
                </div>
                
                <div class="status-row">
                    <span class="label">Current Clients:</span>
                    <span class="value value-highlight">' . htmlspecialchars($clients) . '</span>
                </div>
                
                <div class="status-row">
                    <span class="label">Monthly Used:</span>
                    <span class="value value-highlight">' . $gb . ' GB</span>
                </div>
                <div class="bytes-info">(Raw bytes: ' . number_format($bytes) . ')</div>
                
                <div class="status-row">
                    <span class="label">Model:</span>
                    <span class="value">' . htmlspecialchars($model ?? 'Unknown') . '</span>
                </div>
                
                <div class="status-row">
                    <span class="label">Firmware:</span>
                    <span class="value">' . htmlspecialchars($firmware ?? 'Unknown') . '</span>
                </div>
                
                <div class="status-row">
                    <span class="label">MAC Address:</span>
                    <span class="value">' . htmlspecialchars($mac ?? 'Unknown') . '</span>
                </div>
            </div>
            <div class="success">✓ Status fetched successfully!<br>Done.</div>';
            
            $status = '<span style="color:#00ff00">✓ Status fetched successfully!</span>';
            $success = true;
            
        } catch (Exception $e) {
            $status = '<span style="color:#ff5555">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
            
            $output = '
            <div class="error">
                <strong>Error Details:</strong><br>
                ' . htmlspecialchars($e->getMessage()) . '<br><br>
                <small>Check console for more details.</small>
            </div>';
        }
        ?>
        
        <div class="handshake">
            <div class="pass-info">Reading credentials from <i>password.txt</i>...</div>
            <div style="margin-top:12px; display: flex; gap: 20px; flex-wrap: wrap;">
                <div>Nonce: <span id="n-out" style="color:#d2a8ff; font-weight: bold;"><?php echo $n_out; ?></span></div>
                <div>Token: <span id="t-out" style="color:#d2a8ff; font-weight: bold;"><?php echo $t_out; ?></span></div>
            </div>
        </div>

        <div id="status"><?php echo $status; ?></div>
        <div id="output"><?php echo $output; ?></div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button class="refresh-btn" onclick="location.reload()">⟳ Refresh Status</button>
        </div>
    </div>
</body>
</html>