#!/bin/sh
echo "Content-type: text/plain"
echo ""

# Get the full command parameter
RAW_CMD=$(echo "$QUERY_STRING" | sed 's/.*cmd=//' | sed 's/&.*//')

# Decode URL encoding properly
CMD=$(printf '%b' "$(echo "$RAW_CMD" | sed 's/%/\\x/g')")

NC_HOST="192.168.0.190"
NC_PORT="8023"

if [ -z "$CMD" ]; then
    echo "ERROR: No command specified"
    exit 1
fi

echo "=== Poweramp NC Relay ==="
echo "Target: $NC_HOST:$NC_PORT"
echo "Command: $CMD"
echo ""

# Send command via netcat
(echo "$CMD"; sleep 0.5) | nc "$NC_HOST" "$NC_PORT" -w 2 2>&1

echo ""
echo "Command sent"