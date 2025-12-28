

#!/bin/bash
# TP-Link M7350 Quick Status
HOST="192.168.0.1"
PASSWORD="YourPasswordHere"

# Get token
NONCE=$(curl -s -X POST -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -H "X-Requested-With: XMLHttpRequest" -H "Referer: http://$HOST/login.html" \
  -d '{"module":"authenticator","action":0}' "http://$HOST/cgi-bin/auth_cgi" | \
  sed -n 's/.*"nonce":[[:space:]]*"\([^"]*\)".*/\1/p')

DIGEST=$(echo -n "${PASSWORD}:${NONCE}" | md5sum | cut -d' ' -f1)

TOKEN=$(curl -s -X POST -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -H "X-Requested-With: XMLHttpRequest" -H "Referer: http://$HOST/login.html" \
  -d "{\"module\":\"authenticator\",\"action\":1,\"digest\":\"$DIGEST\"}" \
  "http://$HOST/cgi-bin/auth_cgi" | sed -n 's/.*"token":[[:space:]]*"\([^"]*\)".*/\1/p')

# Get and parse status
STATUS_JSON=$(curl -s -X POST -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -H "X-Requested-With: XMLHttpRequest" -H "Referer: http://$HOST/settings.html" \
  -d "{\"token\":\"$TOKEN\",\"module\":\"status\",\"action\":0}" \
  "http://$HOST/cgi-bin/web_cgi")

# Extract values
IPV4=$(echo "$STATUS_JSON" | grep '"ipv4"' | cut -d'"' -f4)
SSID=$(echo "$STATUS_JSON" | grep '"ssid"' | cut -d'"' -f4)
CLIENTS=$(echo "$STATUS_JSON" | grep '"number"' | awk '{print $2}' | tr -d ',')
BYTES=$(echo "$STATUS_JSON" | grep '"totalStatistics"' | cut -d'"' -f4 | cut -d'.' -f1)
MODEL=$(echo "$STATUS_JSON" | grep '"model"' | cut -d'"' -f4)
FIRMWARE=$(echo "$STATUS_JSON" | grep '"firmwareVer"' | cut -d'"' -f4)
MAC=$(echo "$STATUS_JSON" | grep '"mac"' | cut -d'"' -f4)

# Convert bytes to GB
GB=$(awk -v b="$BYTES" 'BEGIN {printf "%.2f", b / 1073741824}')

# Display
echo "=== FINAL STATUS ==="
echo "Connection Status: Connected"
echo "Network Type: LTE"
echo "IPv4 Address: $IPV4"
echo "SSID: $SSID"
echo "Current Clients: $CLIENTS"
echo "Monthly Used: ${GB} GB"
echo "  (Raw bytes: $BYTES)"
echo "Model: $MODEL"
echo "Firmware: $FIRMWARE"
echo "MAC: $MAC"


read -n1 -r -p "Press any key to continue..."