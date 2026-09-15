<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| The admin console and observer PWA are both served by the separate
| "EIP PWA" React app, which talks to this backend purely over the
| /api/v1 JSON API (see routes/api.php). This app no longer serves any
| server-rendered UI of its own.
|
*/

Route::get('/', function () {
    return response()->json([
        'name'    => 'Election Intelligence Platform API',
        'status'  => 'ok',
        'api'     => url('/api/v1'),
    ]);
});
