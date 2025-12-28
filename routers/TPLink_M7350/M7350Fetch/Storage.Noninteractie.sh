#!/bin/bash
HOST="192.168.0.1"

# Hardcoded password (change this to your actual password)
PASSWORD="your_admin_password_here"

# 1. Get Nonce
NONCE_RESPONSE=$(curl -s -X POST -d '{"module":"authenticator","action":0}' "http://$HOST/cgi-bin/auth_cgi")
NONCE=$(echo "$NONCE_RESPONSE" | sed -n 's/.*"nonce":[[:space:]]*"\([^"]*\)".*/\1/p')

# 2. Login
DIGEST=$(echo -n "${PASSWORD}:${NONCE}" | md5sum | cut -d' ' -f1)

LOGIN_RESPONSE=$(curl -s -X POST -d "{\"module\":\"authenticator\",\"action\":1,\"digest\":\"$DIGEST\"}" "http://$HOST/cgi-bin/auth_cgi")
TOKEN=$(echo "$LOGIN_RESPONSE" | sed -n 's/.*"token":[[:space:]]*"\([^"]*\)".*/\1/p')

if [ -z "$TOKEN" ]; then echo "Login failed"; exit 1; fi

# 3. Fetch Status
FULL_DATA=$(curl -s -X POST \
  -H "Cookie: tpweb_token=$TOKEN" \
  -d "{\"token\":\"$TOKEN\",\"module\":\"status\",\"action\":0}" \
  "http://$HOST/cgi-bin/web_cgi")

# 4. Parse with KB to GB Math (1024 * 1024)
echo "$FULL_DATA" | tr -d '"{}' | tr '\t' ' ' | tr ',' '\n' | awk -F: '
    { gsub(/^[ \t]+|[ \t]+$/, "", $1); gsub(/^[ \t]+|[ \t]+$/, "", $2); }
    
    $1 == "model"    { model=$2 }
    $1 == "ipv4"     { ip=$2 }
    $1 == "voltage"  { bat=$2 }
    $1 == "status"   { st=($2==1?"Ready":"Empty") }
    
    # Logic: Values are in KB. Divide by 1048576 (1024*1024) to get GB.
    $1 == "volume"   { vol=$2/1048576 }
    $1 == "used"     { usd=$2/1048576 }
    $1 == "left"     { lft=$2/1048576 }
    
    END {
        printf "\n=== DEVICE: %s ===\n", model
        printf "IP Address:  %s\n", ip
        printf "Battery:     %s%%\n", bat
        printf "---------------------------\n"
        if (vol > 0) {
            printf "SD Status:   %s\n", st
            printf "Total Size:  %.2f GB\n", vol
            printf "Used Space:  %.2f GB (%.1f%%)\n", usd, (usd/vol)*100
            printf "Free Space:  %.2f GB\n", lft
        } else {
            print "SD Card:     Not Found or Unmounted"
        }
    }
'
echo "Done."
echo "Done."
read -n1 -r -p "Press any key to exit..."