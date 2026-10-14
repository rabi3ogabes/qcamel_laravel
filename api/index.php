<?php

/*
| Vercel entrypoint (vercel-php runtime). The deployment filesystem is read-only,
| so every path Laravel writes to is redirected to a writable temp directory
| before the app boots (bootstrap/app.php reads APP_STORAGE_PATH /
| APP_BOOTSTRAP_CACHE). Everything else is the normal public/index.php.
*/

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir().'/qcamelmc';
foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/private/mpdf', 'bootstrap/cache'] as $dir) {
    if (! is_dir("$tmp/$dir")) {
        @mkdir("$tmp/$dir", 0775, true);
    }
}
// The provider list is read from the bootstrap path too.
if (! is_file("$tmp/bootstrap/providers.php")) {
    @copy("$root/bootstrap/providers.php", "$tmp/bootstrap/providers.php");
}
foreach (['APP_STORAGE_PATH' => $tmp, 'APP_BOOTSTRAP_CACHE' => "$tmp/bootstrap", 'VERCEL_RUNTIME' => '1'] as $k => $v) {
    $_ENV[$k] = $_SERVER[$k] = $v;
    putenv("$k=$v");
}

require $root.'/public/index.php';
