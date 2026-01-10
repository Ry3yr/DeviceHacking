You will need to execute "sdcard\_symlink.sh"

  

Personally I place the "html" and "cgi-bin" into /media/card/ext

  

You will need to set the permissions of  the sh files inside the cgi-bin folder.

You can either use the supplied fixcgiperm.sh (if you fix its own perms and make executable first)

  

Or you run:

#!/bin/sh
chmod +x /media/card/ext/cgi-bin/\*.sh 2>/dev/null

  
---------
After setup is complete you can browser your /ext folder in browser by visitng 192.168.0.1/card/ext/html/browse.html

Incase you made an sh and placed it onto the routers sdcard yourself sh's also have a dedicated "fix Permission" button