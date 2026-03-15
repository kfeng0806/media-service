<?php

use App\Enums\MediaType;
use App\Enums\UploadSessionStatus;
use App\Models\UploadSession;
use App\Models\User;

function initUploadPayload(array $overrides = []): array
{
    return array_merge([
        'type' => MediaType::Video->value,
        'client_upload_key' => 'upload-key',
        'file_name' => 'demo.mp4',
        'mime_type' => 'video/mp4',
        'file_size' => 2048,
    ], $overrides);
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

dataset('invalid upload extensions', [
    'video rejects exe' => [MediaType::Video->value, 'demo.exe', 'application/octet-stream'],
    'audio rejects zip' => [MediaType::Audio->value, 'podcast.zip', 'application/zip'],
    'attachment rejects mp4' => [MediaType::Attachment->value, 'archive.mp4', 'video/mp4'],
]);

it('requires authentication to initialize an upload session', function () {
    $this->postJson('/api/upload/media/init')
        ->assertUnauthorized();
});

it('validates the required fields', function () {
    $this->actingAs($this->user)
        ->postJson('/api/upload/media/init')
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'type',
            'client_upload_key',
            'file_name',
            'mime_type',
            'file_size',
        ]);
});

it('validates supported media types', function () {
    $this->actingAs($this->user)
        ->postJson('/api/upload/media/init', initUploadPayload([
            'type' => MediaType::Image->value,
            'file_name' => 'image.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('validates file size based on the selected media type', function () {
    $this->actingAs($this->user)
        ->postJson('/api/upload/media/init', initUploadPayload([
            'type' => MediaType::Attachment->value,
            'file_name' => 'archive.zip',
            'mime_type' => 'application/zip',
            'file_size' => config('upload.attachment.max_size') + 1,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file_size']);
});

it('validates file extensions based on the selected media type', function (
    string $type,
    string $fileName,
    string $mimeType,
) {
    $this->actingAs($this->user)
        ->postJson('/api/upload/media/init', initUploadPayload([
            'type' => $type,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file_name']);
})->with('invalid upload extensions');

it('creates an upload session and returns tus configuration', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/upload/media/init', initUploadPayload([
            'client_upload_key' => 'video-session-key',
        ]))
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'tus' => [
                    'endpoint',
                    'token',
                    'session_id',
                ],
            ],
        ]);

    $uploadSession = UploadSession::query()->sole();

    $response->assertJsonPath('data.tus.endpoint', config('services.tus.endpoint'));
    $response->assertJsonPath('data.tus.session_id', $uploadSession->id);
    $response->assertJsonPath('data.tus.token', fn (string $token) => str_starts_with($token, 'Bearer '));

    $this->assertDatabaseHas('upload_sessions', [
        'id' => $uploadSession->id,
        'user_id' => $this->user->id,
        'type' => MediaType::Video->value,
        'status' => UploadSessionStatus::Pending->value,
        'client_upload_key' => 'video-session-key',
    ]);
});

it('accepts allowed attachment file extensions', function () {
    $this->actingAs($this->user)
        ->postJson('/api/upload/media/init', initUploadPayload([
            'type' => MediaType::Attachment->value,
            'client_upload_key' => 'attachment-session-key',
            'file_name' => 'archive.torrent',
            'mime_type' => 'application/x-bittorrent',
            'file_size' => 4096,
        ]))
        ->assertCreated();
});

it('reuses the existing upload session for the same client upload key', function () {
    $uploadSession = UploadSession::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Audio,
        'status' => UploadSessionStatus::Pending,
        'client_upload_key' => 'audio-session-key',
        'metadata' => [
            'file_name' => 'podcast.wav',
            'mime_type' => 'audio/wav',
            'file_size' => 4096,
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/upload/media/init', initUploadPayload([
            'type' => MediaType::Audio->value,
            'client_upload_key' => 'audio-session-key',
            'file_name' => 'different-name.wav',
            'mime_type' => 'audio/wav',
            'file_size' => 12345,
        ]))
        ->assertSuccessful();

    $response->assertJsonPath('data.tus.endpoint', config('services.tus.endpoint'));
    $response->assertJsonPath('data.tus.session_id', $uploadSession->id);
    $response->assertJsonPath('data.tus.token', fn (string $token) => str_starts_with($token, 'Bearer '));
    expect(UploadSession::count())->toBe(1);
});
