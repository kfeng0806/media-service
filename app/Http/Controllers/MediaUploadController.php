<?php

namespace App\Http\Controllers;

use App\Actions\StoreTemporaryImageAction;
use App\Http\Requests\UploadImageRequest;
use App\Http\Resources\TemporaryMediaResource;
use Illuminate\Http\JsonResponse;

class MediaUploadController extends Controller
{
    public function image(
        UploadImageRequest $request,
        StoreTemporaryImageAction $action,
    ): JsonResponse {
        $temporaryMedia = $action->execute(
            $request->file('file'),
            $request->user(),
        );

        return new TemporaryMediaResource($temporaryMedia)
            ->response()
            ->setStatusCode(201);
    }
}
