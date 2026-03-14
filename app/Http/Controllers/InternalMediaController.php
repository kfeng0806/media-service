<?php

namespace App\Http\Controllers;

use App\Actions\DeleteMediaAction;
use App\Actions\StoreMediaAction;
use App\Http\Requests\DeleteMediaRequest;
use App\Http\Requests\StoreMediaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class InternalMediaController extends Controller
{
    public function store(
        StoreMediaRequest $request,
        StoreMediaAction $storeMediaAction,
    ): JsonResponse {
        $storeMediaAction->execute($request->validated('media'));

        return response()->json(['message' => 'ok']);
    }

    public function delete(
        DeleteMediaRequest $request,
        DeleteMediaAction $deleteMediaAction,
    ): Response {
        $deleteMediaAction->execute($request->validated('mediaIds'));

        return response()->noContent();
    }
}
