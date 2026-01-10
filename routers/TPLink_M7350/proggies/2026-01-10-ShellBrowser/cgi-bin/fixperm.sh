#!/bin/sh
echo "Content-type: text/html"
echo ""
echo "<html><head><title>Fix Permissions</title>"
echo "<style>body{font-family:monospace;background:#000;color:#fff;}</style></head>"
echo "<body><pre>"

# Get the script path from query string
SCRIPT_PARAM=$(echo "$QUERY_STRING" | sed -n 's/.*script=\([^&]*\).*/\1/p')

if [ -z "$SCRIPT_PARAM" ]; then
    echo "ERROR: No script specified!"
    echo "Usage: /cgi-bin/fixperm.sh?script=path/to/script.sh"
    echo "</pre></body></html>"
    exit 1
fi

# Decode URL encoding
SCRIPT_PATH=$(echo "$SCRIPT_PARAM" | sed 's/%20/ /g;s/%2F/\//g')

# SECURITY: Remove any dangerous characters
SCRIPT_PATH=$(echo "$SCRIPT_PATH" | tr -d ';|&<>$`')

# Try different possible locations
POSSIBLE_PATHS="
/media/card/ext/$SCRIPT_PATH
/media/card/$SCRIPT_PATH
/media/$SCRIPT_PATH
/card/ext/$SCRIPT_PATH
$SCRIPT_PATH
"

FOUND=""
for TEST_PATH in $POSSIBLE_PATHS; do
    if [ -f "$TEST_PATH" ]; then
        FOUND="$TEST_PATH"
        break
    fi
done

if [ -z "$FOUND" ]; then
    echo "ERROR: Script not found: $SCRIPT_PARAM"
    echo "Tried paths:"
    for TEST_PATH in $POSSIBLE_PATHS; do
        echo "  $TEST_PATH"
    done
else
    echo "Fixing permissions for: $FOUND"
    echo "================================================"
    
    # Show current permissions
    echo "Before:"
    ls -la "$FOUND"
    echo ""
    
    # Try to fix permissions
    echo "Attempting to fix permissions..."
    echo "--------------------------------"
    
    # Try chmod +x
    if chmod +x "$FOUND" 2>/dev/null; then
        echo " chmod +x successful"
    else
        echo " chmod +x failed (trying as root)"
        # Try with sudo if available
        if command -v sudo >/dev/null 2>&1; then
            sudo chmod +x "$FOUND" 2>&1 || echo "  sudo failed: $?"
        fi
    fi
    
    echo ""
    
    # Try chmod 755 for full permissions
    if chmod 755 "$FOUND" 2>/dev/null; then
        echo " chmod 755 successful"
    else
        echo " chmod 755 failed"
    fi
    
    echo ""
    
    # Show final permissions
    echo "After:"
    ls -la "$FOUND"
    echo ""
    
    # Check if executable now
    if [ -x "$FOUND" ]; then
        echo " SUCCESS: File is now executable!"
        echo ""
        echo "You can now run it with the 'Run' button."
    else
        echo "? WARNING: File might still not be executable"
        echo ""
        echo "Current permissions: $(ls -la "$FOUND" | awk '{print $1}')"
    fi
    
    echo ""
    echo "Quick test of first line:"
    head -n 5 "$FOUND"
fi

echo "</pre></body></html>"
