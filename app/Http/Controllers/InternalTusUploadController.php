<?php

namespace App\Http\Controllers;

use App\Actions\CompleteTusUploadAction;
use App\Http\Requests\CompleteTusUploadRequest;
use Illuminate\Http\Response;

class InternalTusUploadController extends Controller
{
    public function complete(
        CompleteTusUploadRequest $request,
        CompleteTusUploadAction $completeTusUploadAction,
    ): Response {
        $completeTusUploadAction->execute($request->validated());

        return response()->noContent();
    }
}
