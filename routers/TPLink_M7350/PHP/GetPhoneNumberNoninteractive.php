<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>M7350 SIM Info - Auto Run</title>
    <style>
        body { 
            font-family: 'Courier New', monospace; 
            background: #121212; 
            color: #00ff00; 
            padding: 20px; 
        }
        .container { 
            max-width: 800px; 
            margin: auto; 
            border: 1px solid #00ff00; 
            padding: 20px; 
            box-shadow: 0 0 15px #00ff0033; 
        }
        .handshake { 
            color: #888; 
            font-size: 0.8em; 
            margin-bottom: 20px; 
            border-bottom: 1px dashed #444; 
            padding-bottom: 10px; 
        }
        .sim-info-section { 
            border: 1px solid #333; 
            padding: 15px; 
            margin-bottom: 15px; 
            background: #1a1a1a; 
        }
        .info-header {
            color: #00aaff;
            font-size: 1.2em;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .info-item { 
            margin: 10px 0; 
            padding: 8px 0; 
            border-bottom: 1px solid #222;
        }
        .label { 
            color: #0088ff; 
            font-weight: bold; 
            display: inline-block;
            width: 120px;
        }
        .value { 
            color: #fff; 
            font-family: monospace;
            font-size: 1.1em;
        }
        #status { 
            color: #ff8800; 
            margin: 10px 0; 
        }
        .pass-info { 
            font-size: 0.8em; 
            color: #666; 
            margin-top: 5px; 
        }
        .auto-note { 
            color: #ffff00; 
            font-size: 0.9em; 
            margin-bottom: 10px; 
            padding: 5px; 
            background: #222; 
        }
        .done-message {
            margin-top: 20px;
            padding: 10px;
            background: #222;
            color: #888;
            text-align: center;
            border: 1px solid #333;
        }
        .error {
            color: #ff5555;
            border: 1px solid #ff5555;
            padding: 10px;
            background: #2a1111;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h3>M7350 SIM Information Reader</h3>
        
        <div class="auto-note">✓ Auto-running SIM info check on page load...</div>
        
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
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With: XMLHttpRequest'
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
                    'X-Requested-With: XMLHttpRequest'
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
            
            // Step 3: Fetch SIM information
            $status = "Fetching SIM information...";
            $simData = json_encode([
                'token' => $token,
                'module' => 'status',
                'action' => 0
            ]);
            
            $ch = curl_init("http://{$host}/cgi-bin/web_cgi");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $simData,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With: XMLHttpRequest'
                ],
                CURLOPT_TIMEOUT => 10,
            ]);
            
            $rawData = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("Failed to fetch SIM info: " . curl_error($ch));
            }
            curl_close($ch);
            
            // Function to extract values using regex
            function extractValue($pattern, $data) {
                preg_match($pattern, $data, $matches);
                return isset($matches[1]) ? $matches[1] : null;
            }
            
            // Try to extract values using regex patterns
            $simNumber = extractValue('/"simNumber":\s*"([^"]*)"/', $rawData);
            $imsi = extractValue('/"imsi":\s*"([^"]*)"/', $rawData);
            $imei = extractValue('/"imei":\s*"([^"]*)"/', $rawData);
            
            if ($simNumber || $imsi || $imei) {
                $output = '
                <div class="sim-info-section">
                    <div class="info-header">SIM Information</div>
                    
                    <div class="info-item">
                        <span class="label">SIM Number:</span>
                        <span class="value">' . ($simNumber ? '+' . htmlspecialchars($simNumber) : 'Not found') . '</span>
                    </div>
                    
                    <div class="info-item">
                        <span class="label">IMSI:</span>
                        <span class="value">' . htmlspecialchars($imsi ?? 'Not found') . '</span>
                    </div>
                    
                    <div class="info-item">
                        <span class="label">IMEI:</span>
                        <span class="value">' . htmlspecialchars($imei ?? 'Not found') . '</span>
                    </div>
                </div>
                <div class="done-message">Done.</div>';
                
                $status = "✓ SIM information fetched successfully";
                
            } else {
                // Try JSON parsing as fallback
                $jsonData = json_decode($rawData, true);
                if ($jsonData && (isset($jsonData['simNumber']) || isset($jsonData['imsi']) || isset($jsonData['imei']))) {
                    $output = '
                    <div class="sim-info-section">
                        <div class="info-header">SIM Information (JSON Parsed)</div>
                        
                        <div class="info-item">
                            <span class="label">SIM Number:</span>
                            <span class="value">' . 
                                (isset($jsonData['simNumber']) && $jsonData['simNumber'] ? 
                                '+' . htmlspecialchars($jsonData['simNumber']) : 'Not found') . 
                            '</span>
                        </div>
                        
                        <div class="info-item">
                            <span class="label">IMSI:</span>
                            <span class="value">' . htmlspecialchars($jsonData['imsi'] ?? 'Not found') . '</span>
                        </div>
                        
                        <div class="info-item">
                            <span class="label">IMEI:</span>
                            <span class="value">' . htmlspecialchars($jsonData['imei'] ?? 'Not found') . '</span>
                        </div>
                    </div>
                    <div class="done-message">Done.</div>';
                    
                    $status = "✓ SIM information fetched successfully (JSON)";
                } else {
                    $status = "No SIM information found in response";
                    $output = '
                    <div class="error">
                        <strong>Debug - Raw Response:</strong><br>
                        <pre style="font-size:0.8em;color:#aaa;">' . htmlspecialchars($rawData) . '</pre>
                    </div>';
                }
            }
            
        } catch (Exception $e) {
            $status = "Error: " . $e->getMessage();
            $output = '
            <div class="error">' . htmlspecialchars($e->getMessage()) . '</div>';
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