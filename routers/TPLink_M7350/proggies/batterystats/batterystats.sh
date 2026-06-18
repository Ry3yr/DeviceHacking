#!/bin/sh
echo "Content-type: text/plain"
echo ""

NC_HOST="192.168.0.190"
NC_PORT="8023"

# Default command: battery stats summary
DEFAULT_CMD='adb shell dumpsys batterystats | head -n 200'

# Get the command parameter if provided
RAW_CMD=$(echo "$QUERY_STRING" | sed 's/.*cmd=//' | sed 's/&.*//')
CMD=$(printf '%b' "$(echo "$RAW_CMD" | sed 's/%/\\x/g')")

# Use default if no command given
if [ -z "$CMD" ]; then
    CMD="$DEFAULT_CMD"
fi

echo "=== Battery Stats Relay ==="
echo "Target: $NC_HOST:$NC_PORT"
echo "Command: $CMD"
echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Send command via netcat to the Android device running adb server
(echo "$CMD"; sleep 1) | nc "$NC_HOST" "$NC_PORT" -w 3 2>&1

echo ""
echo "=== End ==="