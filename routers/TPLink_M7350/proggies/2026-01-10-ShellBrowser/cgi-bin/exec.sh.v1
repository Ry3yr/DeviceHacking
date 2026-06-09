#!/bin/sh
echo "Content-type: text/plain"
echo ""

# Get the script path from query string
SCRIPT_PARAM=$(echo "$QUERY_STRING" | sed -n 's/.*script=\([^&]*\).*/\1/p')

if [ -z "$SCRIPT_PARAM" ]; then
    echo "ERROR: No script specified!"
    echo "Usage: /cgi-bin/exec.sh?script=path/to/script.sh"
    exit 1
fi

# Decode URL encoding
SCRIPT_PATH=$(echo "$SCRIPT_PARAM" | sed 's/%20/ /g;s/%2F/\//g')
SCRIPT_PATH=$(echo "$SCRIPT_PATH" | tr -d ';|&<>$`')

# Find the script
FOUND=""
for TEST_PATH in "/media/card/ext/$SCRIPT_PATH" "/media/card/$SCRIPT_PATH" "/media/$SCRIPT_PATH" "/card/ext/$SCRIPT_PATH" "$SCRIPT_PATH"; do
    if [ -f "$TEST_PATH" ]; then
        FOUND="$TEST_PATH"
        break
    fi
done

if [ -z "$FOUND" ]; then
    echo "ERROR: Script not found: $SCRIPT_PARAM"
    exit 1
fi

# Get script directory
SCRIPT_DIR=$(dirname "$FOUND")
SCRIPT_FILE=$(basename "$FOUND")

echo "=== Executing: $SCRIPT_FILE ==="
echo "Location: $FOUND"
echo "Directory: $SCRIPT_DIR"
echo "========================================"

# Change to script directory and execute there
cd "$SCRIPT_DIR" || {
    echo "ERROR: Cannot change to directory: $SCRIPT_DIR"
    exit 1
}

# Make sure script is executable
if [ ! -x "$SCRIPT_FILE" ]; then
    echo "WARNING: Script not executable, trying with sh..."
    echo "----------------------------------------"
    sh "$SCRIPT_FILE"
else
    ./"$SCRIPT_FILE"
fi

EXIT_CODE=$?
echo ""
echo "----------------------------------------"
echo "Exit code: $EXIT_CODE"
echo "Current dir: $(pwd)"
