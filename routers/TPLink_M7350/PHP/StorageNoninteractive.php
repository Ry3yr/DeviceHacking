<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>M7350 Status Monitor - Auto Run</title>
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
        .status-section { 
            border: 1px solid #333; 
            padding: 15px; 
            margin-bottom: 15px; 
            background: #1a1a1a; 
        }
        .device-header {
            color: #00aaff;
            font-size: 1.2em;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .status-item { 
            margin: 8px 0; 
            padding: 5px 0; 
        }
        .label { 
            color: #0088ff; 
            font-weight: bold; 
            display: inline-block;
            width: 150px;
        }
        .value { 
            color: #fff; 
        }
        .sd-card-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #333;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #222;
            border: 1px solid #333;
            border-radius: 3px;
            margin: 5px 0;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #00aa00, #00ff00);
            transition: width 0.3s ease;
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
        .unit {
            color: #888;
            font-size: 0.9em;
        }
        .raw-data {
            font-size: 0.7em;
            color: #444;
            background: #111;
            padding: 10px;
            margin-top: 20px;
            border: 1px solid #333;
            max-height: 200px;
            overflow: auto;
            white-space: pre-wrap;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h3>M7350 Device Status Monitor</h3>
        
        <div class="auto-note">✓ Auto-running status check on page load...</div>
        
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
            
            // Step 3: Fetch device status
            $status = "Fetching device status...";
            $deviceData = json_encode([
                'token' => $token,
                'module' => 'status',
                'action' => 0
            ]);
            
            $ch = curl_init("http://{$host}/cgi-bin/web_cgi");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $deviceData,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded; charset=UTF-8'],
                CURLOPT_TIMEOUT => 10,
            ]);
            
            $rawData = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("Failed to fetch status: " . curl_error($ch));
            }
            curl_close($ch);
            
            // Parse the raw data
            $debugHtml = '<div class="raw-data">Raw response: ' . htmlspecialchars($rawData) . '</div>';
            
            // Clean and parse the data (same logic as JS version)
            $cleanedData = preg_replace('/["{}]/', '', $rawData);  // Remove quotes and braces
            $cleanedData = str_replace("\t", ' ', $cleanedData);   // Replace tabs with spaces
            $lines = explode(',', $cleanedData);                  // Split by commas
            
            $data = [];
            foreach ($lines as $line) {
                $parts = explode(':', $line, 2);
                if (count($parts) >= 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    $data[$key] = $value;
                }
            }
            
            if (!empty($data)) {
                $model = $data['model'] ?? "Unknown";
                $ip = $data['ipv4'] ?? "N/A";
                $battery = $data['voltage'] ?? "0";
                $sdStatus = isset($data['status']) && $data['status'] == "1" ? "Ready" : "Empty/Not Found";
                
                $totalKB = isset($data['volume']) ? (float)$data['volume'] : 0;
                $usedKB = isset($data['used']) ? (float)$data['used'] : 0;
                $leftKB = isset($data['left']) ? (float)$data['left'] : 0;
                
                $totalGB = $totalKB / 1048576;
                $usedGB = $usedKB / 1048576;
                $freeGB = $leftKB / 1048576;
                $usedPercentage = $totalGB > 0 ? ($usedGB / $totalGB) * 100 : 0;
                
                $statusHtml = '
                <div class="status-section">
                    <div class="device-header">=== DEVICE: ' . htmlspecialchars($model) . ' ===</div>
                    
                    <div class="status-item">
                        <span class="label">IP Address:</span>
                        <span class="value">' . htmlspecialchars($ip) . '</span>
                    </div>
                    
                    <div class="status-item">
                        <span class="label">Battery:</span>
                        <span class="value">' . htmlspecialchars($battery) . '%</span>
                    </div>
                    
                    <div class="sd-card-info">';
                
                if ($totalGB > 0) {
                    $statusHtml .= '
                        <div class="status-item">
                            <span class="label">SD Card Status:</span>
                            <span class="value">' . htmlspecialchars($sdStatus) . '</span>
                        </div>
                        
                        <div class="status-item">
                            <span class="label">Total Size:</span>
                            <span class="value">' . number_format($totalGB, 2) . ' <span class="unit">GB</span></span>
                        </div>
                        
                        <div class="status-item">
                            <span class="label">Used Space:</span>
                            <span class="value">' . number_format($usedGB, 2) . ' <span class="unit">GB</span> (' . number_format($usedPercentage, 1) . '%)</span>
                        </div>
                        
                        <div class="status-item">
                            <span class="label">Free Space:</span>
                            <span class="value">' . number_format($freeGB, 2) . ' <span class="unit">GB</span></span>
                        </div>
                        
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ' . number_format($usedPercentage, 1) . '%"></div>
                        </div>';
                } else {
                    $statusHtml .= '
                        <div class="status-item">
                            <span class="label">SD Card:</span>
                            <span class="value">Not Found or Unmounted</span>
                        </div>';
                }
                
                $statusHtml .= '
                    </div>
                </div>';
                
                $output = $statusHtml . $debugHtml . '
                <div style="margin-top: 20px; color: #888; font-size: 0.9em;">
                    Done.
                </div>';
                
                $status = "✓ Status fetched successfully";
            } else {
                $status = "No status data received or parsing failed";
                $output = $debugHtml;
            }
            
        } catch (Exception $e) {
            $status = "Error: " . $e->getMessage();
            $output = '<div style="color: #ff5555;">' . htmlspecialchars($e->getMessage()) . '</div>';
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