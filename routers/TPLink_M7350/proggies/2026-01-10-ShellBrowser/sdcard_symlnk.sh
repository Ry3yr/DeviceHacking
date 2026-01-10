# Create symlink from web root to /media/card
ln -sf /media/card /WEBSERVER/www/card

# Enable directory listing globally in Lighttpd config
echo 'dir-listing.activate = "enable"' >> /etc/lighttpd.conf
echo 'dir-listing.encoding = "utf-8"' >> /etc/lighttpd.conf

# Restart Lighttpd
killall lighttpd 2>/dev/null
/usr/sbin/lighttpd -f /etc/lighttpd.conf
