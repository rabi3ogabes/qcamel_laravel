<?php

/*
| Local development router for PHP's built-in server (used by .claude/launch.json
| so opcache can be enabled with -d flags). Production runs behind Nginx/PHP-FPM.
*/
chdir(__DIR__.'/public');

return require __DIR__.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
