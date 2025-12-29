<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>M7350 System Log Fetcher - Multi-Page</title>
    <style>
        body { 
            font-family: 'Courier New', monospace; 
            background: #121212; 
            color: #00ff00; 
            padding: 20px; 
        }
        .container { 
            max-width: 1000px; 
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
        .log-section { 
            border: 1px solid #333; 
            padding: 15px; 
            margin-bottom: 15px; 
            background: #1a1a1a; 
            max-height: 600px;
            overflow-y: auto;
        }
        .log-header {
            color: #00aaff;
            font-size: 1.2em;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
            position: sticky;
            top: 0;
            background: #1a1a1a;
            z-index: 10;
        }
        .log-entry { 
            margin: 8px 0; 
            padding: 10px; 
            border-bottom: 1px solid #222;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            line-height: 1.4;
            word-wrap: break-word;
        }
        .log-time {
            color: #00ffaa;
            font-weight: bold;
        }
        .log-type {
            color: #ffaa00;
            font-weight: bold;
            margin: 0 5px;
        }
        .log-content {
            color: #ffffff;
            margin-top: 5px;
        }
        .page-header {
            color: #aa55ff;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 2px dashed #444;
            font-size: 1.1em;
        }
        #status { 
            color: #ff8800; 
            margin: 10px 0; 
            font-weight: bold;
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
        .controls {
            margin: 15px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .controls button {
            background: #222;
            color: #00ff00;
            border: 1px solid #00ff00;
            padding: 8px 15px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            transition: all 0.3s;
        }
        .controls button:hover {
            background: #00ff00;
            color: #121212;
        }
        .controls select {
            background: #222;
            color: #00ff00;
            border: 1px solid #00ff00;
            padding: 8px;
            font-family: 'Courier New', monospace;
        }
        .loading {
            color: #ffff00;
            font-style: italic;
        }
        .error-log {
            color: #ff5555;
            border: 1px solid #ff5555;
            padding: 10px;
            background: #2a1111;
            margin: 10px 0;
        }
        .stats {
            color: #888;
            font-size: 0.9em;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #444;
        }
    </style>
</head>
<body>
    <div class="container">
        <h3>TP-Link M7350 System Log Fetcher (Multi-Page)</h3>
        
        <div class="auto-note">✓ Auto-running on page load - Fetching system logs...</div>
        
        <?php
        $host = "192.168.0.1";
        $status = "Initializing...";
        $output = "";
        $n_out = "---";
        $t_out = "---";
        $totalPages = isset($_GET['pages']) ? (int)$_GET['pages'] : 10;
        $totalPages = max(1, min(20, $totalPages)); // Limit between 1-20 pages
        
        try {
            // Read password from password.txt
            if (!file_exists('password.txt')) {
                throw new Exception("Could not find password.txt in directory.");
            }
            $password = trim(file_get_contents('password.txt'));
            
            // Step 1: Get nonce
            $nonceData = json_encode(['module' => 'authenticator', 'action' => 0]);
            
            $ch = curl_init("http://{$host}/cgi-bin/auth_cgi");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $nonceData,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
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
            
            // Step 3: Fetch logs for multiple pages
            $logContainer = '
            <div class="log-section">
                <div class="log-header">=== SYSTEM LOGS ===</div>';
            
            $totalLogs = 0;
            $successfulPages = 0;
            
            for ($i = 1; $i <= $totalPages; $i++) {
                $status = "<span class='loading'>Fetching page {$i}/{$totalPages}...</span>";
                
                try {
                    $logData = json_encode([
                        'token' => $token,
                        'module' => 'log',
                        'action' => 0,
                        'amountPerPage' => 20,
                        'pageNumber' => $i,
                        'type' => 0,
                        'level' => 0
                    ]);
                    
                    $ch = curl_init("http://{$host}/cgi-bin/web_cgi");
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $logData,
                        CURLOPT_HTTPHEADER => [
                            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                            'X-Requested-With: XMLHttpRequest',
                            'Referer: http://' . $host . '/settings.html'
                        ],
                        CURLOPT_TIMEOUT => 10,
                    ]);
                    
                    $response = curl_exec($ch);
                    if (curl_errno($ch)) {
                        throw new Exception("Page fetch failed: " . curl_error($ch));
                    }
                    curl_close($ch);
                    
                    if ($response) {
                        $logContainer .= '<div class="page-header">--- Page ' . $i . ' ---</div>';
                        
                        $jsonData = json_decode($response, true);
                        if ($jsonData && isset($jsonData['logList'])) {
                            $logList = $jsonData['logList'];
                            
                            if (!empty($logList)) {
                                $totalLogs += count($logList);
                                $successfulPages++;
                                
                                foreach ($logList as $log) {
                                    $time = htmlspecialchars($log['time'] ?? 'Unknown time');
                                    $type = htmlspecialchars($log['type'] ?? 'Unknown');
                                    $content = htmlspecialchars($log['content'] ?? 'No content');
                                    
                                    $logContainer .= '
                                    <div class="log-entry">
                                        <div>
                                            <span class="log-time">[' . $time . ']</span>
                                            <span class="log-type">(' . $type . ')</span>
                                        </div>
                                        <div class="log-content">' . $content . '</div>
                                    </div>';
                                }
                            } else {
                                $logContainer .= '
                                <div class="log-entry" style="color: #888; font-style: italic;">
                                    No logs found on this page
                                </div>';
                            }
                        } else {
                            $logContainer .= '
                            <div class="error-log">
                                Failed to parse page ' . $i . ': Invalid JSON format
                            </div>';
                        }
                    }
                    
                    // Small delay between requests
                    usleep(500000); // 500ms delay
                    
                } catch (Exception $pageError) {
                    $logContainer .= '
                    <div class="error-log">
                        Error fetching page ' . $i . ': ' . htmlspecialchars($pageError->getMessage()) . '
                    </div>';
                }
            }
            
            $logContainer .= '</div>';
            
            $stats = '
            <div class="stats">
                ✓ Completed. Fetched ' . $totalLogs . ' log entries from ' . $successfulPages . ' out of ' . $totalPages . ' pages.
            </div>';
            
            $output = $logContainer . $stats;
            $status = '<span style="color:#00ff00">✓ Log fetch completed successfully</span>';
            
        } catch (Exception $e) {
            $status = '<span style="color:#ff5555">Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
            $output = '
            <div class="error-log">
                Fatal error: ' . htmlspecialchars($e->getMessage()) . '
            </div>';
        }
        ?>
        
        <div class="handshake">
            <div class="pass-info">Reading credentials from <i>password.txt</i>...</div>
            <div style="margin-top:10px;">
                Nonce: <span id="n-out" style="color:#d2a8ff"><?php echo $n_out; ?></span> | 
                Token: <span id="t-out" style="color:#d2a8ff"><?php echo $t_out; ?></span>
            </div>
        </div>

        <div class="controls">
            <form method="get" style="display: flex; gap: 10px; align-items: center;">
                <button type="submit">Refresh Logs</button>
                <select name="pages" onchange="this.form.submit()">
                    <option value="5" <?php echo $totalPages == 5 ? 'selected' : ''; ?>>5 Pages</option>
                    <option value="10" <?php echo $totalPages == 10 ? 'selected' : ''; ?>>10 Pages</option>
                    <option value="15" <?php echo $totalPages == 15 ? 'selected' : ''; ?>>15 Pages</option>
                    <option value="20" <?php echo $totalPages == 20 ? 'selected' : ''; ?>>20 Pages</option>
                </select>
                <span style="color:#888; margin-left: 10px; font-size: 0.9em;">
                    Showing: <span id="currentPage">1</span>/<span id="totalPages"><?php echo $totalPages; ?></span>
                </span>
            </form>
        </div>

        <div id="status"><?php echo $status; ?></div>
        <div id="output"><?php echo $output; ?></div>
    </div>
    
    <script>
        function updatePageCount() {
            const select = document.querySelector('select[name="pages"]');
            const form = select.closest('form');
            form.submit();
        }
    </script>
</body>
</html>