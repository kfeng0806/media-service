<?php

namespace App\Http\Resources;

use App\Models\UploadSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UploadSession $uploadSession */
        $uploadSession = $this['upload_session'];

        return [
            'tus' => [
                'endpoint' => config('services.tus.endpoint'),
                'token' => "Bearer {$this['upload_token']}",
                'session_id' => $uploadSession->id,
            ],
        ];
    }
}
