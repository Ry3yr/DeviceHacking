#!/bin/sh
echo "Content-type: text/plain"
echo ""

# Get parameters from query string
TERM=$(echo "$QUERY_STRING" | sed 's/.*term=//' | sed 's/&.*//' | sed 's/%20/ /g')
TIMEOUT=$(echo "$QUERY_STRING" | sed 's/.*timeout=//' | sed 's/&.*//' | grep -o '[0-9]*')

# Default timeout 10 seconds if not specified
if [ -z "$TIMEOUT" ]; then
    TIMEOUT=10
fi

# Run find with timeout
timeout -t $TIMEOUT find /media/card -iname "*$TERM*" 2>/dev/null
