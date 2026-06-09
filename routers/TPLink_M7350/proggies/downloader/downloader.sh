#!/bin/sh

get_param() {
    echo "$QUERY_STRING" | sed -n "s/.*$1=\([^&]*\).*/\1/p" | sed 's/%20/ /g;s/%2F/\//g;s/%3A/:/g'
}

URL=$(get_param "url")
FILENAME=$(get_param "filename")
DIR=$(get_param "dir")

[ -z "$DIR" ] && DIR="/media/card/download"
[ -z "$URL" ] && { echo "ERROR: No URL provided"; exit 1; }

# Add http:// if missing
echo "$URL" | grep -q '^http://\|^https://' || URL="http://$URL"

mkdir -p "$DIR"
cd "$DIR" || exit 1

if [ -n "$FILENAME" ]; then
    OUTFILE="$FILENAME"
else
    OUTFILE=$(basename "$URL" | sed 's/?.*//')
    [ -z "$OUTFILE" ] && OUTFILE="downloaded_file"
fi

echo "📥 Downloading: $URL"
echo "📂 Saving to: $DIR/$OUTFILE"
echo ""

# Attempt 1: wget with original URL
wget -O "$OUTFILE" "$URL" 2>&1
if [ $? -eq 0 ] && [ -f "$OUTFILE" ] && [ -s "$OUTFILE" ]; then
    SIZE=$(ls -lh "$OUTFILE" | awk '{print $5}')
    echo ""
    echo "✅ Download completed: $DIR/$OUTFILE ($SIZE)"
    exit 0
fi

# Attempt 2: Try HTTP if HTTPS failed
if echo "$URL" | grep -q '^https://'; then
    HTTP_URL=$(echo "$URL" | sed 's/https:/http:/')
    echo ""
    echo "⚠️ HTTPS failed, trying HTTP..."
    wget -O "$OUTFILE" "$HTTP_URL" 2>&1
    if [ $? -eq 0 ] && [ -f "$OUTFILE" ] && [ -s "$OUTFILE" ]; then
        SIZE=$(ls -lh "$OUTFILE" | awk '{print $5}')
        echo ""
        echo "✅ Download completed: $DIR/$OUTFILE ($SIZE)"
        exit 0
    fi
fi

# Attempt 3: Try curl
if command -v curl > /dev/null 2>&1; then
    echo ""
    echo "⚠️ wget failed, trying curl..."
    curl -k -L -o "$OUTFILE" "$URL" 2>&1
    if [ $? -eq 0 ] && [ -f "$OUTFILE" ] && [ -s "$OUTFILE" ]; then
        SIZE=$(ls -lh "$OUTFILE" | awk '{print $5}')
        echo ""
        echo "✅ Download completed: $DIR/$OUTFILE ($SIZE)"
        exit 0
    fi
fi

# Attempt 4: Try OpenSSL with SNI (for HTTPS only)
if echo "$URL" | grep -q '^https://'; then
    echo ""
    echo "⚠️ wget/curl failed, trying OpenSSL with SNI..."
    
    HOST=$(echo "$URL" | sed 's|https://||' | sed 's|/.*||')
    PATH_PART=$(echo "$URL" | sed "s|https://$HOST||")
    [ -z "$PATH_PART" ] && PATH_PART="/"
    
    {
        printf "GET %s HTTP/1.1\r\n" "$PATH_PART"
        printf "Host: %s\r\n" "$HOST"
        printf "User-Agent: Mozilla/5.0\r\n"
        printf "Accept: */*\r\n"
        printf "Connection: close\r\n"
        printf "\r\n"
    } | openssl s_client -connect "$HOST":443 -servername "$HOST" -quiet 2>/dev/null | awk '
        BEGIN { in_body=0 }
        /^\r$/ { in_body=1; next }
        in_body { print }
    ' > "$OUTFILE"
    
    if [ -f "$OUTFILE" ] && [ -s "$OUTFILE" ]; then
        if ! grep -q "421 Misdirected\|400 Bad\|404 Not\|500 Internal" "$OUTFILE" 2>/dev/null; then
            SIZE=$(ls -lh "$OUTFILE" | awk '{print $5}')
            echo ""
            echo "✅ Download completed: $DIR/$OUTFILE ($SIZE)"
            exit 0
        else
            echo "OpenSSL returned error page"
            rm -f "$OUTFILE" 2>/dev/null
        fi
    fi
fi

# All failed
echo ""
echo "❌ Download failed after all attempts"
rm -f "$OUTFILE" 2>/dev/null
exit 1