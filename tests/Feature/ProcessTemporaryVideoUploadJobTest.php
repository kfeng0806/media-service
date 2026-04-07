<?php

use App\Enums\MediaType;
use App\Enums\UploadSessionStatus;
use App\Jobs\ProcessTemporaryVideoUploadJob;
use App\Models\TemporaryMedia;
use App\Models\UploadSession;
use App\Models\User;
use App\Support\MediaFileMover;
use App\Support\UploadSessionCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function createVideoUploadSession(int $userId): UploadSession
{
    return UploadSession::create([
        'user_id' => $userId,
        'type' => MediaType::Video,
        'status' => UploadSessionStatus::Processing,
        'client_upload_key' => 'video-test-key',
        'tus_upload_id' => 'tus-upload-123',
        'metadata' => [
            'file_name' => 'test-clip.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 1048576,
        ],
    ]);
}

it('processes a video upload and creates temporary media', function () {
    Storage::fake('local');

    $session = createVideoUploadSession($this->user->id);

    $tusDir = rtrim((string) config('services.tus.upload_directory'), '/');
    $sampleVideo = base_path('tests/fixtures/sample.mp4');

    if (! file_exists($sampleVideo)) {
        $this->markTestSkipped('tests/fixtures/sample.mp4 not found — skipping integration test');
    }

    Storage::disk('local')->put("{$tusDir}/tus-upload-123", file_get_contents($sampleVideo));

    (new ProcessTemporaryVideoUploadJob($session->id))->handle(app(MediaFileMover::class));

    $session->refresh();
    expect($session->status)->toBe(UploadSessionStatus::Completed)
        ->and($session->temporary_media_id)->not->toBeNull();

    $temporaryMedia = TemporaryMedia::find($session->temporary_media_id);
    expect($temporaryMedia)->not->toBeNull()
        ->and($temporaryMedia->type)->toBe(MediaType::Video)
        ->and($temporaryMedia->metadata['extension'])->toBe('mp4')
        ->and($temporaryMedia->metadata['width'])->toBeInt()
        ->and($temporaryMedia->metadata['height'])->toBeInt()
        ->and($temporaryMedia->metadata['duration'])->toBeFloat();

    $videoDir = config('paths.temporary.upload.video')."/{$temporaryMedia->id}";
    Storage::disk('local')->assertExists("{$videoDir}/master.mp4");
    Storage::disk('local')->assertExists("{$videoDir}/cover.jpg");
    Storage::disk('local')->assertDirectoryExists("{$videoDir}/segments");

    Storage::disk('local')->assertMissing("{$tusDir}/tus-upload-123");
    Storage::disk('local')->assertMissing("{$tusDir}/tus-upload-123.json");
});

it('reports progress during processing', function () {
    Storage::fake('local');

    $session = createVideoUploadSession($this->user->id);

    $tusDir = rtrim((string) config('services.tus.upload_directory'), '/');
    $sampleVideo = base_path('tests/fixtures/sample.mp4');

    if (! file_exists($sampleVideo)) {
        $this->markTestSkipped('tests/fixtures/sample.mp4 not found — skipping integration test');
    }

    Storage::disk('local')->put("{$tusDir}/tus-upload-123", file_get_contents($sampleVideo));

    (new ProcessTemporaryVideoUploadJob($session->id))->handle(app(MediaFileMover::class));

    $cached = Cache::get(UploadSessionCache::metadataKey($session->id));
    expect($cached)->toBe(['progress' => 100]);
});

it('marks session as failed and cleans up on error', function () {
    Storage::fake('local');

    $session = createVideoUploadSession($this->user->id);

    $job = new ProcessTemporaryVideoUploadJob($session->id);
    $job->failed(new RuntimeException('FFmpeg crashed'));

    $session->refresh();
    expect($session->status)->toBe(UploadSessionStatus::Failed);

    $cached = Cache::get(UploadSessionCache::metadataKey($session->id));
    expect($cached)->toBe(['progress' => -1]);
});

it('is dispatched on the encoding queue', function () {
    $job = new ProcessTemporaryVideoUploadJob(1);
    expect($job->queue)->toBe('encoding');
});

it('has a timeout of 3600 seconds', function () {
    $job = new ProcessTemporaryVideoUploadJob(1);
    expect($job->timeout)->toBe(3600);
});
