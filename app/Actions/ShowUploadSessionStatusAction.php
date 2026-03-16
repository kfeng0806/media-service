<?php

namespace App\Actions;

use App\Models\UploadSession;
use App\Models\User;
use App\Support\UploadSessionCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;

final class ShowUploadSessionStatusAction
{
    public function execute(UploadSession $uploadSession, User $user): array
    {
        if ($uploadSession->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        return [
            'upload_session' => $uploadSession,
            'metadata' => $this->metadata($uploadSession),
        ];
    }

    private function metadata(UploadSession $uploadSession): array
    {
        $sessionMetadata = $uploadSession->metadata ?? [];
        $cachedMetadata = Cache::get(UploadSessionCache::metadataKey($uploadSession->id), []);

        if (! is_array($cachedMetadata)) {
            $cachedMetadata = [];
        }

        return array_merge($sessionMetadata, $cachedMetadata);
    }
}
