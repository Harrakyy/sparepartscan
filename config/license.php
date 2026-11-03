<?php

return [
    // Penegakan lisensi komputer di edisi offline. Default aktif; dimatikan hanya di lingkungan tes
    // (lewat LICENSE_ENFORCE=false di phpunit.xml) selain tes yang memang menguji penegakan.
    'enforce' => env('LICENSE_ENFORCE', true),
];
