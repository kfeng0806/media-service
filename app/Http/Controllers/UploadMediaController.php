<?php

namespace App\Http\Controllers;

use App\Actions\InitiateUploadSessionAction;
use App\Actions\ShowUploadSessionStatusAction;
use App\Actions\StoreTemporaryImageAction;
use App\Http\Requests\InitiateUploadSessionRequest;
use App\Http\Requests\UploadImageRequest;
use App\Http\Resources\TemporaryMediaResource;
use App\Http\Resources\UploadSessionResource;
use App\Http\Resources\UploadSessionStatusResource;
use App\Models\UploadSession;
use Illuminate\Http\JsonResponse;

class UploadMediaController extends Controller
{
    public function init(
        InitiateUploadSessionRequest $request,
        InitiateUploadSessionAction $initiateUploadSessionAction,
    ): JsonResponse {
        $payload = $initiateUploadSessionAction->execute(
            $request->validated(),
            $request->user(),
        );

        $statusCode = $payload['was_created'] ? 201 : 200;

        return new UploadSessionResource($payload)
            ->response()
            ->setStatusCode($statusCode);
    }

    public function show(
        UploadSession $uploadSession,
        ShowUploadSessionStatusAction $showUploadSessionStatusAction,
    ): JsonResponse {
        $payload = $showUploadSessionStatusAction->execute(
            $uploadSession,
            request()->user(),
        );

        return new UploadSessionStatusResource($payload)
            ->response();
    }

    public function image(
        UploadImageRequest $request,
        StoreTemporaryImageAction $storeTemporaryImageAction,
    ): JsonResponse {
        $temporaryMedia = $storeTemporaryImageAction->execute(
            $request->file('file'),
            $request->user(),
        );

        return new TemporaryMediaResource($temporaryMedia)
            ->response()
            ->setStatusCode(201);
    }
}
