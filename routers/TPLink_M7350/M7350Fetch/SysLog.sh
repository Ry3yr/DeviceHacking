#!/bin/bash
# TP-Link M7350 Multi-Page SysLog Fetcher
HOST="192.168.0.1"
COOKIE_JAR=$(mktemp)
PAGES=10

echo "=== TP-Link M7350 SysLog Fetcher (10 Pages) ==="

# 1. Get Nonce
NONCE_RESPONSE=$(curl -s -X POST \
  -c "$COOKIE_JAR" \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -H "Referer: http://$HOST/login.html" \
  -d '{"module":"authenticator","action":0}' \
  "http://$HOST/cgi-bin/auth_cgi")

NONCE=$(echo "$NONCE_RESPONSE" | sed -n 's/.*"nonce":[[:space:]]*"\([^"]*\)".*/\1/p')

if [ -z "$NONCE" ]; then echo "Failed to get nonce"; exit 1; fi

# 2. Login
read -s -p "Enter password: " PASSWORD
echo >&2 
DIGEST=$(echo -n "${PASSWORD}:${NONCE}" | md5sum | cut -d' ' -f1)

LOGIN_RESPONSE=$(curl -s -X POST \
  -b "$COOKIE_JAR" \
  -c "$COOKIE_JAR" \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -H "Referer: http://$HOST/login.html" \
  -d "{\"module\":\"authenticator\",\"action\":1,\"digest\":\"$DIGEST\"}" \
  "http://$HOST/cgi-bin/auth_cgi")

TOKEN=$(echo "$LOGIN_RESPONSE" | sed -n 's/.*"token":[[:space:]]*"\([^"]*\)".*/\1/p')

if [ -z "$TOKEN" ]; then echo "Login failed"; exit 1; fi

# Inject required cookies found in your logs
echo "$HOST	FALSE	/	FALSE	0	tpweb_token	$TOKEN" >> "$COOKIE_JAR"
echo "$HOST	FALSE	/	FALSE	0	check_cookie	check_cookie" >> "$COOKIE_JAR"

echo "✓ Login successful. Fetching $PAGES pages of logs..."
echo -e "\n=== SYSTEM LOGS ==="

# 3. Loop through 10 pages
for (( i=1; i<=$PAGES; i++ ))
do
    echo "--- Page $i ---" >&2
    LOG_JSON=$(curl -s -X POST \
      -b "$COOKIE_JAR" \
      -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
      -H "X-Requested-With: XMLHttpRequest" \
      -H "Referer: http://$HOST/settings.html" \
      -d "{\"token\":\"$TOKEN\",\"module\":\"log\",\"action\":0,\"amountPerPage\":20,\"pageNumber\":$i,\"type\":0,\"level\":0}" \
      "http://$HOST/cgi-bin/web_cgi")

    # Parse and Print
    echo "$LOG_JSON" | sed 's/[{},]/ \n/g' | sed 's/"//g' | awk '
        $1 ~ /^time:/ { time=$2 " " $3 }
        $1 ~ /^type:/ { type=$2 }
        $1 ~ /^content:/ { 
            msg=substr($0, index($0,$2))
            print "[" time "] (" type ") " msg
        }
    '
    
    # Optional: Short delay to prevent overwhelming the router
    sleep 0.5
done

rm -f "$COOKIE_JAR"
echo -e "\nDone fetching all pages."
read -n1 -r -p "Press any key to exit..."