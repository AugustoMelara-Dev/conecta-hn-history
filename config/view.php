<?php

$isVercel = (bool) env('VERCEL');
$serverlessViewPath = rtrim((string) env('SERVERLESS_VIEW_PATH', sys_get_temp_dir().'/conecta-views'), '/\\');

return [

    /*
    |--------------------------------------------------------------------------
    | View Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your views. Of course
    | the usual Laravel view path has already been registered for you.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within storage but
    | may be customized using VIEW_COMPILED_PATH in serverless runtime.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        $isVercel
            ? $serverlessViewPath
            : realpath(storage_path('framework/views'))
    ),

];
