<?php

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo "Endpoint launcher lama sudah dinonaktifkan.\nSilakan gunakan form pendataan inventaris terbaru agar launcher BAT dibuat melalui token yang aman.\n";
