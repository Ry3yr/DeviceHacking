#!/bin/bash
# TP-Link M7350 Minimal SMS Parser - HARDCODED PASSWORD VERSION
HOST="192.168.0.1"
PASSWORD="password"  # Hardcoded password - CHANGE THIS!

# 1. Get Nonce (Silent)
NONCE_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -d '{"module":"authenticator","action":0}' \
  "http://$HOST/cgi-bin/auth_cgi")

NONCE=$(echo "$NONCE_RESPONSE" | sed -n 's/.*"nonce":[[:space:]]*"\([^"]*\)".*/\1/p')

if [ -z "$NONCE" ]; then 
    echo "Failed to get nonce" >&2
    exit 1
fi

# 2. Login (Silent)
DIGEST=$(echo -n "${PASSWORD}:${NONCE}" | md5sum | cut -d' ' -f1)

LOGIN_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -d "{\"module\":\"authenticator\",\"action\":1,\"digest\":\"$DIGEST\"}" \
  "http://$HOST/cgi-bin/auth_cgi")

TOKEN=$(echo "$LOGIN_RESPONSE" | sed -n 's/.*"token":[[:space:]]*"\([^"]*\)".*/\1/p')

if [ -z "$TOKEN" ]; then
    echo "Login failed - check password" >&2
    exit 1
fi

# 3. Fetch SMS
SMS_JSON=$(curl -s -X POST \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -d "{\"token\":\"$TOKEN\",\"module\":\"message\",\"action\":2,\"pageNumber\":1,\"amountPerPage\":8,\"box\":0}" \
  "http://$HOST/cgi-bin/web_cgi")

echo "=== SMS MESSAGES ==="

# Updated awk labels: from and receivedTime
echo "$SMS_JSON" | sed 's/[{},]/ \n/g' | sed 's/"//g' | awk '
    $1 ~ /^from:/ { from=$2 }
    $1 ~ /^receivedTime:/ { date=$2 " " $3 }
    $1 ~ /^content:/ { 
        msg=substr($0, index($0,$2))
        print "From: " from
        print "Date: " date
        print "Msg:  " msg
        print "-------------------------"
    }
'


echo -e "\nDone."
read -n1 -r -p "Press any key to exit..."