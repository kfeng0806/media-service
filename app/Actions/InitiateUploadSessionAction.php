<?php

namespace App\Actions;

use App\Enums\MediaType;
use App\Enums\UploadSessionStatus;
use App\Models\UploadSession;
use App\Models\User;

final class InitiateUploadSessionAction
{
    /**
     * @param  array{
     *     type: string,
     *     client_upload_key: string,
     *     file_name: string,
     *     mime_type: string,
     *     file_size: int
     * }  $attributes
     */
    public function execute(array $attributes, User $user): array
    {
        $uploadSession = UploadSession::firstOrCreate(
            [
                'user_id' => $user->id,
                'client_upload_key' => $attributes['client_upload_key'],
            ],
            [
                'type' => MediaType::from($attributes['type']),
                'status' => UploadSessionStatus::Pending,
                'metadata' => $this->buildMetadata($attributes),
            ],
        );

        return [
            'upload_session' => $uploadSession,
            'upload_token' => $this->generateUploadToken($uploadSession),
            'was_created' => $uploadSession->wasRecentlyCreated,
        ];
    }

    private function buildMetadata(array $attributes): array
    {
        return [
            'file_name' => $attributes['file_name'],
            'mime_type' => $attributes['mime_type'],
            'file_size' => $attributes['file_size'],
        ];
    }

    private function generateUploadToken(UploadSession $uploadSession): string
    {
        $payload = json_encode([
            'upload_session_id' => $uploadSession->id,
            'user_id' => $uploadSession->user_id,
            'type' => $uploadSession->type->value,
            'issued_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR);

        $encodedPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encodedPayload, (string) config('app.key'));

        return "{$encodedPayload}.{$signature}";
    }
}
