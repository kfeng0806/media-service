<?php

use App\Enums\MediaType;
use App\Enums\UploadSessionStatus;
use App\Jobs\ProcessTemporaryAudioUploadJob;
use App\Jobs\ProcessTemporaryVideoUploadJob;
use App\Models\TemporaryMedia;
use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->internalHeaders = ['x-internal-key' => config('internal.api_key')];
});

it('rejects complete requests without internal api key', function () {
    $this->postJson('/api/internal/tus/complete', [])
        ->assertUnauthorized();
});

it('rejects complete requests with invalid internal api key', function () {
    $this->postJson('/api/internal/tus/complete', [], ['x-internal-key' => 'wrong-key'])
        ->assertUnauthorized();
});

it('validates required complete payload fields', function () {
    $this->postJson('/api/internal/tus/complete', [], $this->internalHeaders)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session_id', 'upload_id']);
});

it('validates that the upload session exists', function () {
    $this->postJson('/api/internal/tus/complete', [
        'session_id' => 9999,
        'upload_id' => 'upload-1',
    ], $this->internalHeaders)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session_id']);
});

it('completes an attachment upload and creates temporary media', function () {
    $uploadSession = UploadSession::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Attachment,
        'status' => UploadSessionStatus::Pending,
        'client_upload_key' => 'attachment-session-key',
        'metadata' => [
            'file_name' => 'archive.zip',
            'mime_type' => 'application/zip',
            'file_size' => 4096,
        ],
    ]);

    $sourcePath = rtrim((string) config('services.tus.upload_directory'), '/').'/upload-1';
    Storage::disk('local')->put($sourcePath, 'attachment-content');

    $response = $this->postJson('/api/internal/tus/complete', [
        'session_id' => $uploadSession->id,
        'upload_id' => 'upload-1',
    ], $this->internalHeaders)
        ->assertNoContent();

    $temporaryMediaId = TemporaryMedia::query()->value('id');
    $temporaryMedia = TemporaryMedia::query()->find($temporaryMediaId);
    $targetPath = config('paths.temporary.upload.attachment')."/{$temporaryMediaId}.zip";

    expect($temporaryMedia)->not->toBeNull()
        ->and($temporaryMedia->type)->toBe(MediaType::Attachment)
        ->and($temporaryMedia->metadata)->toBe([
            'extension' => 'zip',
            'name' => 'archive',
            'file_size' => 4096,
            'mime_type' => 'application/zip',
        ]);

    Storage::disk('local')->assertExists($targetPath);
    Storage::disk('local')->assertMissing($sourcePath);

    $uploadSession->refresh();
    expect($uploadSession->status)->toBe(UploadSessionStatus::Completed)
        ->and($uploadSession->tus_upload_id)->toBe('upload-1')
        ->and($uploadSession->temporary_media_id)->toBe($temporaryMediaId);
});

it('treats repeated attachment complete callbacks as idempotent', function () {
    $temporaryMedia = TemporaryMedia::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Attachment,
        'metadata' => [
            'extension' => 'zip',
            'name' => 'archive',
            'file_size' => 4096,
            'mime_type' => 'application/zip',
        ],
    ]);

    $uploadSession = UploadSession::create([
        'user_id' => $this->user->id,
        'temporary_media_id' => $temporaryMedia->id,
        'type' => MediaType::Attachment,
        'status' => UploadSessionStatus::Completed,
        'client_upload_key' => 'attachment-session-key',
        'tus_upload_id' => 'upload-1',
        'metadata' => [
            'file_name' => 'archive.zip',
            'mime_type' => 'application/zip',
            'file_size' => 4096,
        ],
    ]);

    $this->postJson('/api/internal/tus/complete', [
        'session_id' => $uploadSession->id,
        'upload_id' => 'upload-1',
    ], $this->internalHeaders)
        ->assertNoContent();

    expect(TemporaryMedia::count())->toBe(1);
});

it('deletes the upload session when the linked temporary media is deleted', function () {
    $temporaryMedia = TemporaryMedia::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Attachment,
        'metadata' => [
            'extension' => 'zip',
            'name' => 'archive',
            'file_size' => 4096,
            'mime_type' => 'application/zip',
        ],
    ]);

    $uploadSession = UploadSession::create([
        'user_id' => $this->user->id,
        'temporary_media_id' => $temporaryMedia->id,
        'type' => MediaType::Attachment,
        'status' => UploadSessionStatus::Completed,
        'client_upload_key' => 'linked-session-key',
        'tus_upload_id' => 'upload-1',
        'metadata' => [
            'file_name' => 'archive.zip',
            'mime_type' => 'application/zip',
            'file_size' => 4096,
        ],
    ]);

    $temporaryMedia->delete();

    $this->assertDatabaseMissing('upload_sessions', ['id' => $uploadSession->id]);
});

it('queues the video processing job after upload completion', function () {
    Queue::fake();

    $uploadSession = UploadSession::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Video,
        'status' => UploadSessionStatus::Pending,
        'client_upload_key' => 'video-session-key',
        'metadata' => [
            'file_name' => 'clip.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 4096,
        ],
    ]);

    $sourcePath = rtrim((string) config('services.tus.upload_directory'), '/').'/video-upload-1';
    Storage::disk('local')->put($sourcePath, 'video-content');

    $this->postJson('/api/internal/tus/complete', [
        'session_id' => $uploadSession->id,
        'upload_id' => 'video-upload-1',
    ], $this->internalHeaders)->assertNoContent();

    Queue::assertPushed(ProcessTemporaryVideoUploadJob::class, function (ProcessTemporaryVideoUploadJob $job) use ($uploadSession) {
        return $job->uploadSessionId === $uploadSession->id;
    });

    $uploadSession->refresh();
    expect($uploadSession->status)->toBe(UploadSessionStatus::Processing)
        ->and($uploadSession->tus_upload_id)->toBe('video-upload-1')
        ->and($uploadSession->temporary_media_id)->toBeNull();
});

it('queues the audio processing job after upload completion', function () {
    Queue::fake();

    $uploadSession = UploadSession::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Audio,
        'status' => UploadSessionStatus::Pending,
        'client_upload_key' => 'audio-session-key',
        'metadata' => [
            'file_name' => 'track.flac',
            'mime_type' => 'audio/flac',
            'file_size' => 4096,
        ],
    ]);

    $sourcePath = rtrim((string) config('services.tus.upload_directory'), '/').'/audio-upload-1';
    Storage::disk('local')->put($sourcePath, 'audio-content');

    $this->postJson('/api/internal/tus/complete', [
        'session_id' => $uploadSession->id,
        'upload_id' => 'audio-upload-1',
    ], $this->internalHeaders)->assertNoContent();

    Queue::assertPushed(ProcessTemporaryAudioUploadJob::class, function (ProcessTemporaryAudioUploadJob $job) use ($uploadSession) {
        return $job->uploadSessionId === $uploadSession->id;
    });

    $uploadSession->refresh();
    expect($uploadSession->status)->toBe(UploadSessionStatus::Processing)
        ->and($uploadSession->tus_upload_id)->toBe('audio-upload-1')
        ->and($uploadSession->temporary_media_id)->toBeNull();
});

it('does not queue duplicate processing jobs for sessions already being processed', function () {
    Queue::fake();

    $uploadSession = UploadSession::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Video,
        'status' => UploadSessionStatus::Processing,
        'client_upload_key' => 'processing-video-key',
        'tus_upload_id' => 'video-upload-1',
        'metadata' => [
            'file_name' => 'clip.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 4096,
        ],
    ]);

    $this->postJson('/api/internal/tus/complete', [
        'session_id' => $uploadSession->id,
        'upload_id' => 'video-upload-1',
    ], $this->internalHeaders)->assertNoContent();

    Queue::assertNothingPushed();
});

it('returns a validation error when the uploaded file is missing', function () {
    $uploadSession = UploadSession::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Attachment,
        'status' => UploadSessionStatus::Pending,
        'client_upload_key' => 'missing-file-key',
        'metadata' => [
            'file_name' => 'archive.zip',
            'mime_type' => 'application/zip',
            'file_size' => 4096,
        ],
    ]);

    $this->postJson('/api/internal/tus/complete', [
        'session_id' => $uploadSession->id,
        'upload_id' => 'missing-upload',
    ], $this->internalHeaders)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['upload_id']);
});
