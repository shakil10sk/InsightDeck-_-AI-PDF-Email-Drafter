<?php

use Illuminate\Support\Facades\Route;

// Root → SPA.
Route::redirect('/', '/spa/');

// In production, the built Vue SPA lives at public/spa/. Static assets under
// public/spa/assets/* are served directly by the web server before this route
// fires. This route catches the SPA's HTML5-history client-side routes
// (e.g. /spa/login, /spa/documents/3) and returns the SPA shell so Vue Router
// can take over.
Route::get('/spa/{any?}', function () {
    $index = public_path('spa/index.html');
    if (! file_exists($index)) {
        return response(
            'Frontend bundle not found. Build it with `cd frontend && npm install && npm run build`.',
            503
        );
    }
    return response(file_get_contents($index), 200, ['Content-Type' => 'text/html']);
})->where('any', '.*');
