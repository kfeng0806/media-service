<?php

use App\Http\Controllers\InternalMediaController;
use App\Http\Controllers\InternalTusUploadController;
use App\Http\Controllers\UploadMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'ok';
});

Route::prefix('upload')
    ->middleware('auth:sanctum')
    ->group(function () {

        Route::prefix('media')->group(function () {

            Route::post('init', [UploadMediaController::class, 'init']);

            Route::get('sessions/{uploadSession}', [UploadMediaController::class, 'show']);

            Route::post('image', [UploadMediaController::class, 'image']);

        });

    });

Route::prefix('internal')
    ->middleware('internal')
    ->group(function () {

        Route::post('delete-media', [InternalMediaController::class, 'delete']);

        Route::post('store-media', [InternalMediaController::class, 'store']);

        Route::post('tus/complete', [InternalTusUploadController::class, 'complete']);

    });
