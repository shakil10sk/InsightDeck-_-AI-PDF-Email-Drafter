<?php

use Illuminate\Http\Request;

// Hide PHP 8.5 deprecation notices from upstream Laravel/PHP libraries — they
// will be patched in a future Laravel point release. They must be silenced
// before any Laravel code runs because they would otherwise be rendered as
// HTML before our JSON responses.
error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
