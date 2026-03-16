<?php

use App\Enums\MediaType;
use App\Enums\UploadSessionStatus;
use App\Models\TemporaryMedia;
use App\Models\UploadSession;
use App\Models\User;
use App\Support\UploadSessionCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['cache.default' => 'array']);
    $this->user = User::factory()->create();
});

it('requires authentication to view upload session status', function () {
    $uploadSession = UploadSession::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Attachment,
        'status' => UploadSessionStatus::Pending,
        'client_upload_key' => 'session-key',
        'metadata' => [],
    ]);

    $this->getJson("/api/upload/media/sessions/{$uploadSession->id}")
        ->assertUnauthorized();
});

it('returns upload session status and metadata', function () {
    $uploadSession = UploadSession::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Video,
        'status' => UploadSessionStatus::Processing,
        'client_upload_key' => 'video-session-key',
        'metadata' => [
            'file_name' => 'clip.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 4096,
        ],
    ]);

    Cache::put(UploadSessionCache::metadataKey($uploadSession->id), [
        'progress' => 42,
        'stage' => 'transcoding',
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/upload/media/sessions/{$uploadSession->id}")
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                'status' => UploadSessionStatus::Processing->value,
                'temporary_media_id' => null,
                'metadata' => [
                    'file_name' => 'clip.mp4',
                    'mime_type' => 'video/mp4',
                    'file_size' => 4096,
                    'progress' => 42,
                    'stage' => 'transcoding',
                ],
            ],
        ]);
});

it('returns the temporary media id once the upload session is completed', function () {
    $temporaryMedia = TemporaryMedia::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Attachment,
        'metadata' => [
            'extension' => 'zip',
            'name' => 'archive',
            'file_size' => 4096,
        ],
    ]);

    $uploadSession = UploadSession::create([
        'user_id' => $this->user->id,
        'temporary_media_id' => $temporaryMedia->id,
        'type' => MediaType::Attachment,
        'status' => UploadSessionStatus::Completed,
        'client_upload_key' => 'attachment-session-key',
        'metadata' => [
            'file_name' => 'archive.zip',
            'mime_type' => 'application/zip',
            'file_size' => 4096,
        ],
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/upload/media/sessions/{$uploadSession->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.temporary_media_id', $temporaryMedia->id);
});

it('forbids access to another users upload session', function () {
    $owner = User::factory()->create();
    $uploadSession = UploadSession::create([
        'user_id' => $owner->id,
        'type' => MediaType::Attachment,
        'status' => UploadSessionStatus::Pending,
        'client_upload_key' => 'foreign-session-key',
        'metadata' => [],
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/upload/media/sessions/{$uploadSession->id}")
        ->assertForbidden();
});
