<?php

namespace App\Http\Resources;

use App\Models\UploadSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadSessionStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var UploadSession $uploadSession */
        $uploadSession = $this['upload_session'];

        return [
            'status' => $uploadSession->status->value,
            'temporary_media_id' => $uploadSession->temporary_media_id,
            'metadata' => $this['metadata'],
        ];
    }
}
