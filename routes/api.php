<?php

use App\Http\Controllers\MediaUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'ok';
});

Route::prefix('upload')
    ->middleware('auth:sanctum')
    ->group(function () {

        Route::prefix('media')->group(function () {

            Route::post('image', [MediaUploadController::class, 'image']);

        });

    });
