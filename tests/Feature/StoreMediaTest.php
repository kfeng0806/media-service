<?php

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Post;
use App\Models\TemporaryMedia;
use App\Models\User;
use App\Support\MediaPathGenerator;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('data');
    $this->user = User::factory()->create();
    $this->internalHeaders = ['x-internal-key' => config('internal.api_key')];
});

it('rejects requests without internal api key', function () {
    $this->postJson('/api/internal/store-media', [])
        ->assertUnauthorized();
});

it('rejects requests with invalid internal api key', function () {
    $this->postJson('/api/internal/store-media', [], ['x-internal-key' => 'wrong-key'])
        ->assertUnauthorized();
});

it('validates required fields', function () {
    $this->postJson('/api/internal/store-media', [], $this->internalHeaders)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media']);
});

it('validates media array structure', function () {
    $this->postJson('/api/internal/store-media', [
        'media' => [
            ['media_id' => 'not-int'],
        ],
    ], $this->internalHeaders)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media.0.media_id', 'media.0.temporary_media_id']);
});

it('stores image media by moving temp file to data disk and updates path', function () {
    $temporaryMedia = TemporaryMedia::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Image,
        'metadata' => [
            'extension' => 'jpg',
            'name' => 'photo',
            'width' => 800,
            'height' => 600,
            'file_size' => 1024,
        ],
    ]);

    $tmpPath = config('paths.temporary.upload.image').'/'.$temporaryMedia->id.'.jpg';
    Storage::disk('local')->put($tmpPath, 'fake-image-content');

    $media = Media::create([
        'mediable_type' => Post::class,
        'mediable_id' => 1,
        'user_id' => $this->user->id,
        'name' => 'photo',
        'path' => '',
        'type' => MediaType::Image,
        'extension' => 'jpg',
        'file_size' => 1024,
        'metadata' => ['width' => 800, 'height' => 600],
    ]);

    $response = $this->postJson('/api/internal/store-media', [
        'media' => [
            [
                'media_id' => $media->id,
                'temporary_media_id' => $temporaryMedia->id,
            ],
        ],
    ], $this->internalHeaders);

    $response->assertSuccessful();

    $expectedPath = MediaPathGenerator::imagePath($media->id, 'jpg');
    Storage::disk('data')->assertExists($expectedPath);
    Storage::disk('local')->assertMissing($tmpPath);

    $media->refresh();
    expect($media->path)->toBe($expectedPath);

    $this->assertDatabaseMissing('temporary_media', ['id' => $temporaryMedia->id]);
});

it('stores multiple image media in a single request', function () {
    $records = [];

    for ($i = 0; $i < 3; $i++) {
        $temp = TemporaryMedia::create([
            'user_id' => $this->user->id,
            'type' => MediaType::Image,
            'metadata' => [
                'extension' => 'jpg',
                'name' => "photo-{$i}",
                'width' => 800,
                'height' => 600,
                'file_size' => 1024,
            ],
        ]);

        $tmpPath = config('paths.temporary.upload.image').'/'.$temp->id.'.jpg';
        Storage::disk('local')->put($tmpPath, "fake-content-{$i}");

        $media = Media::create([
            'mediable_type' => Post::class,
            'mediable_id' => 1,
            'user_id' => $this->user->id,
            'name' => "photo-{$i}",
            'path' => '',
            'type' => MediaType::Image,
            'extension' => 'jpg',
            'file_size' => 1024,
            'metadata' => ['width' => 800, 'height' => 600],
        ]);

        $records[] = [
            'media_id' => $media->id,
            'temporary_media_id' => $temp->id,
        ];
    }

    $this->postJson('/api/internal/store-media', [
        'media' => $records,
    ], $this->internalHeaders)->assertSuccessful();

    foreach ($records as $record) {
        $media = Media::find($record['media_id']);
        expect($media->path)->not->toBeEmpty();

        $expectedPath = MediaPathGenerator::imagePath($media->id, 'jpg');
        Storage::disk('data')->assertExists($expectedPath);

        $this->assertDatabaseMissing('temporary_media', ['id' => $record['temporary_media_id']]);
    }
});

it('stores attachment media by moving temp file to data disk and updates path', function () {
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

    $tmpPath = config('paths.temporary.upload.attachment').'/'.$temporaryMedia->id.'.zip';
    Storage::disk('local')->put($tmpPath, 'fake-attachment-content');

    $media = Media::create([
        'mediable_type' => Post::class,
        'mediable_id' => 1,
        'user_id' => $this->user->id,
        'name' => 'archive',
        'path' => '',
        'type' => MediaType::Attachment,
        'extension' => 'zip',
        'file_size' => 4096,
        'metadata' => ['mime_type' => 'application/zip'],
    ]);

    $response = $this->postJson('/api/internal/store-media', [
        'media' => [
            [
                'media_id' => $media->id,
                'temporary_media_id' => $temporaryMedia->id,
            ],
        ],
    ], $this->internalHeaders);

    $response->assertSuccessful();

    $expectedPath = MediaPathGenerator::attachmentPath($media->id, 'zip');
    Storage::disk('data')->assertExists($expectedPath);
    Storage::disk('local')->assertMissing($tmpPath);

    $media->refresh();
    expect($media->path)->toBe($expectedPath);

    $this->assertDatabaseMissing('temporary_media', ['id' => $temporaryMedia->id]);
});

it('stores video media by moving temp directory to data disk and updates path', function () {
    $temporaryMedia = TemporaryMedia::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Video,
        'metadata' => [
            'extension' => 'mp4',
            'name' => 'clip',
            'width' => 1920,
            'height' => 1080,
            'duration' => 60.0,
            'file_size' => 10485760,
        ],
    ]);

    $tmpDir = config('paths.temporary.upload.video').'/'.$temporaryMedia->id;
    Storage::disk('local')->put("{$tmpDir}/master.mp4", 'fake-video');
    Storage::disk('local')->put("{$tmpDir}/cover.jpg", 'fake-cover');
    Storage::disk('local')->put("{$tmpDir}/segments/vod.m3u8", 'fake-manifest');

    $media = Media::create([
        'mediable_type' => Post::class,
        'mediable_id' => 1,
        'user_id' => $this->user->id,
        'name' => 'clip',
        'path' => '',
        'type' => MediaType::Video,
        'extension' => 'mp4',
        'file_size' => 10485760,
        'metadata' => ['width' => 1920, 'height' => 1080, 'duration' => 60.0],
    ]);

    $response = $this->postJson('/api/internal/store-media', [
        'media' => [
            [
                'media_id' => $media->id,
                'temporary_media_id' => $temporaryMedia->id,
            ],
        ],
    ], $this->internalHeaders);

    $response->assertSuccessful();

    $expectedPath = MediaPathGenerator::videoDir($media->id);
    Storage::disk('data')->assertExists("{$expectedPath}/master.mp4");
    Storage::disk('data')->assertExists("{$expectedPath}/cover.jpg");
    Storage::disk('data')->assertExists("{$expectedPath}/segments/vod.m3u8");
    Storage::disk('local')->assertMissing($tmpDir);

    $media->refresh();
    expect($media->path)->toBe($expectedPath);

    $this->assertDatabaseMissing('temporary_media', ['id' => $temporaryMedia->id]);
});
