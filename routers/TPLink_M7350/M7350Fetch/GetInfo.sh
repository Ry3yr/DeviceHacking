#!/bin/bash
# Fixed TP-Link M7350 Status Parser
HOST="192.168.0.1"

echo "=== TP-Link M7350 Status ==="

# Login (same as before)
echo -e "\n1. Getting nonce..."
NONCE_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: http://$HOST/login.html" \
  -d '{"module":"authenticator","action":0}' \
  "http://$HOST/cgi-bin/auth_cgi")

NONCE=$(echo "$NONCE_RESPONSE" | sed -n 's/.*"nonce":[[:space:]]*"\([^"]*\)".*/\1/p')
echo "Nonce: $NONCE"

read -s -p "Enter password: " PASSWORD
echo

DIGEST=$(echo -n "${PASSWORD}:${NONCE}" | md5sum | cut -d' ' -f1)
echo "Digest: $DIGEST"

echo -e "\n2. Logging in..."
LOGIN_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: http://$HOST/login.html" \
  -d "{\"module\":\"authenticator\",\"action\":1,\"digest\":\"$DIGEST\"}" \
  "http://$HOST/cgi-bin/auth_cgi")

TOKEN=$(echo "$LOGIN_RESPONSE" | sed -n 's/.*"token":[[:space:]]*"\([^"]*\)".*/\1/p')

if [ -z "$TOKEN" ]; then
    echo "✗ Login failed!"
    exit 1
fi

echo "✓ Login successful!"
echo "Token: $TOKEN"

# Get status
echo -e "\n3. Fetching status..."
STATUS_JSON=$(curl -s -X POST \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Referer: http://$HOST/settings.html" \
  -d "{\"token\":\"$TOKEN\",\"module\":\"status\",\"action\":0}" \
  "http://$HOST/cgi-bin/web_cgi")

# Save raw JSON
echo "$STATUS_JSON" > /tmp/status_debug.json

# Debug: Show the actual JSON around wlan
echo -e "\n=== DEBUG: Looking for SSID in JSON ==="
echo "Searching for 'wlan' and surrounding lines:"
echo "$STATUS_JSON" | grep -n -A5 -B2 '"wlan"'

echo -e "\nSearching for 'ssid' anywhere:"
echo "$STATUS_JSON" | grep -n -i '"ssid"'

# Better parsing function
parse_json_value() {
    local json="$1"
    local key="$2"
    
    # Method 1: Look for "key": "value" pattern
    echo "$json" | grep -o "\"$key\":\"[^\"]*\"" | head -1 | cut -d'"' -f4
    
    # If not found, try "key": value (without quotes)
    if [ -z "$result" ]; then
        echo "$json" | grep -o "\"$key\":[^,}]*" | head -1 | sed 's/.*://' | tr -d '[:space:]"'
    fi
}

# Parse all values
echo -e "\n=== PARSING VALUES ==="

# WAN section
WAN_SECTION=$(echo "$STATUS_JSON" | sed -n '/"wan":/,/^[[:space:]]*}/p')
echo "WAN section found: $(echo "$WAN_SECTION" | wc -l) lines"

CONNECT_STATUS=$(echo "$WAN_SECTION" | grep '"connectStatus"' | awk '{print $2}' | tr -d ',')
NETWORK_TYPE=$(echo "$WAN_SECTION" | grep '"networkType"' | awk '{print $2}' | tr -d ',')
IPV4=$(echo "$WAN_SECTION" | grep '"ipv4"' | cut -d'"' -f4)
TOTAL_STATS=$(echo "$WAN_SECTION" | grep '"totalStatistics"' | cut -d'"' -f4)

# Show what we found
echo "Found totalStatistics: '$TOTAL_STATS'"

# WLAN section  
WLAN_SECTION=$(echo "$STATUS_JSON" | sed -n '/"wlan":/,/^[[:space:]]*}/p')
echo "WLAN section found: $(echo "$WLAN_SECTION" | wc -l) lines"

SSID=$(echo "$WLAN_SECTION" | grep '"ssid"' | cut -d'"' -f4)

# If still not found, try different approach
if [ -z "$SSID" ]; then
    echo "Trying alternative SSID search..."
    SSID=$(echo "$STATUS_JSON" | grep -o '"ssid":"[^"]*"' | head -1 | cut -d'"' -f4)
fi

# Connected devices
DEVICES_SECTION=$(echo "$STATUS_JSON" | sed -n '/"connectedDevices":/,/^[[:space:]]*}/p')
CLIENTS=$(echo "$DEVICES_SECTION" | grep '"number"' | awk '{print $2}' | tr -d ',')

# Device info
DEVICE_SECTION=$(echo "$STATUS_JSON" | sed -n '/"deviceInfo":/,/^[[:space:]]*}/p')
MODEL=$(echo "$DEVICE_SECTION" | grep '"model"' | cut -d'"' -f4)
FIRMWARE=$(echo "$DEVICE_SECTION" | grep '"firmwareVer"' | cut -d'"' -f4)
MAC=$(echo "$DEVICE_SECTION" | grep '"mac"' | cut -d'"' -f4)
IMEI=$(echo "$DEVICE_SECTION" | grep '"imei"' | cut -d'"' -f4)

# Monthly data - try different field names
MONTHLY_DATA="N/A"
for field in totalStatistics totalData dataUsed monthlyData; do
    value=$(echo "$STATUS_JSON" | grep -i "\"$field\"" | head -1 | cut -d'"' -f4)
    if [ -n "$value" ] && [ "$value" != "null" ]; then
        MONTHLY_DATA="$value"
        break
    fi
done

# === FIXED BYTE CONVERSION ===
# Convert bytes to GB properly
if [[ "$MONTHLY_DATA" =~ ^[0-9]+(\.[0-9]+)?$ ]]; then
    # Remove decimal part to get integer bytes
    MONTHLY_BYTES=$(echo "$MONTHLY_DATA" | cut -d'.' -f1)
    
    # Convert bytes to GB using awk (works even if bc is not available)
    # 1 GB = 1024 * 1024 * 1024 = 1073741824 bytes
    if command -v awk >/dev/null 2>&1; then
        MONTHLY_GB=$(awk -v bytes="$MONTHLY_BYTES" 'BEGIN {printf "%.2f", bytes / 1073741824}')
        MONTHLY_DISPLAY="${MONTHLY_GB} GB"
    else
        # Fallback: simple division
        MONTHLY_GB=$((MONTHLY_BYTES / 1073741824))
        MONTHLY_MB=$(( (MONTHLY_BYTES % 1073741824) / 1048576 ))
        MONTHLY_DISPLAY="${MONTHLY_GB}.${MONTHLY_MB} GB"
    fi
else
    MONTHLY_DISPLAY="$MONTHLY_DATA"
    MONTHLY_BYTES="N/A"
fi
# === END FIX ===

# Convert connection status
case $CONNECT_STATUS in
    4) CONNECT_TEXT="Connected" ;;
    1) CONNECT_TEXT="Disconnected" ;;
    2) CONNECT_TEXT="Connecting" ;;
    3) CONNECT_TEXT="Disconnecting" ;;
    *) CONNECT_TEXT="Unknown ($CONNECT_STATUS)" ;;
esac

# Convert network type
case $NETWORK_TYPE in
    3) NETWORK_TEXT="LTE" ;;
    2) NETWORK_TEXT="WCDMA" ;;
    1) NETWORK_TEXT="GSM" ;;
    0) NETWORK_TEXT="No Service" ;;
    *) NETWORK_TEXT="Unknown ($NETWORK_TYPE)" ;;
esac

# Display results
echo -e "\n=== FINAL STATUS ==="
echo "Connection Status: $CONNECT_TEXT"
echo "Network Type: $NETWORK_TEXT"
echo "IPv4 Address: ${IPV4:-N/A}"
echo "SSID: ${SSID:-N/A}"
echo "Current Clients: ${CLIENTS:-N/A}"
echo "Monthly Used: ${MONTHLY_DISPLAY}"
if [[ "$MONTHLY_BYTES" =~ ^[0-9]+$ ]]; then
    echo "  (Raw bytes: $MONTHLY_BYTES)"
fi
echo "Model: ${MODEL:-N/A}"
echo "Firmware: ${FIRMWARE:-N/A}"
echo "MAC: ${MAC:-N/A}"

# Save to file
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
cat > "status_final_${TIMESTAMP}.txt" << EOF
=== TP-Link M7350 Status ===
Timestamp: $TIMESTAMP
Connection Status: $CONNECT_TEXT
Network Type: $NETWORK_TEXT
IPv4 Address: ${IPV4}
SSID: ${SSID}
Current Clients: ${CLIENTS}
Monthly Used: ${MONTHLY_DISPLAY}
Monthly Used (bytes): ${MONTHLY_BYTES}
Model: ${MODEL}
Firmware: ${FIRMWARE}
MAC: ${MAC}
EOF

echo -e "\n✓ Saved to: status_final_${TIMESTAMP}.txt"
echo -e "\n=== RAW JSON EXCERPT ==="
echo "Showing JSON around key sections:"
echo "$STATUS_JSON" | head -c 800
echo "..."

read -n1 -r -p "Press any key to continue..."