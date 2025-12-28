#!/bin/bash
HOST="192.168.0.1"
PASSWORD="your_password_here"  # Replace this with your actual password

# 1. Getting nonce
NONCE_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -H "X-Requested-With: XMLHttpRequest" \
  -d '{"module":"authenticator","action":0}' \
  "http://$HOST/cgi-bin/auth_cgi")

NONCE=$(echo "$NONCE_RESPONSE" | sed -n 's/.*"nonce":[[:space:]]*"\([^"]*\)".*/\1/p')

# 2. Generate Digest
# Removed the read -s -p prompt
DIGEST=$(echo -n "${PASSWORD}:${NONCE}" | md5sum | cut -d' ' -f1)

# 3. Logging in
LOGIN_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -H "X-Requested-With: XMLHttpRequest" \
  -d "{\"module\":\"authenticator\",\"action\":1,\"digest\":\"$DIGEST\"}" \
  "http://$HOST/cgi-bin/auth_cgi")

TOKEN=$(echo "$LOGIN_RESPONSE" | sed -n 's/.*"token":[[:space:]]*"\([^"]*\)".*/\1/p')

if [ -z "$TOKEN" ]; then
    echo "Login failed!"
    exit 1
fi

# 4. Fetching status
STATUS_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  -H "X-Requested-With: XMLHttpRequest" \
  -d "{\"token\":\"$TOKEN\",\"module\":\"status\",\"action\":0}" \
  "http://$HOST/cgi-bin/web_cgi")

# 5. Extraction
SIM_NUMBER=$(echo "$STATUS_RESPONSE" | sed -n 's/.*"simNumber":[[:space:]]*"\([^"]*\)".*/\1/p')
IMSI=$(echo "$STATUS_RESPONSE" | sed -n 's/.*"imsi":[[:space:]]*"\([^"]*\)".*/\1/p')
IMEI=$(echo "$STATUS_RESPONSE" | sed -n 's/.*"imei":[[:space:]]*"\([^"]*\)".*/\1/p')

# 6. Final Output
echo "SIM Number: +${SIM_NUMBER:-Not found}"
echo "IMSI:       ${IMSI:-Not found}"
echo "IMEI:       ${IMEI:-Not found}"

read -n1 -r -p "Press any key to continue..."