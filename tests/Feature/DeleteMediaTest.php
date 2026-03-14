<?php

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Support\MediaPathGenerator;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('data');
    $this->user = User::factory()->create();
    $this->internalHeaders = ['x-internal-key' => config('internal.api_key')];
});

it('rejects delete requests without internal api key', function () {
    $this->postJson('/api/internal/delete-media', [])
        ->assertUnauthorized();
});

it('rejects delete requests with invalid internal api key', function () {
    $this->postJson('/api/internal/delete-media', [], ['x-internal-key' => 'wrong-key'])
        ->assertUnauthorized();
});

it('validates required media ids', function () {
    $this->postJson('/api/internal/delete-media', [], $this->internalHeaders)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mediaIds']);
});

it('validates media ids array structure', function () {
    $this->postJson('/api/internal/delete-media', [
        'mediaIds' => ['invalid'],
    ], $this->internalHeaders)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mediaIds.0']);
});

it('deletes image media file and media record', function () {
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

    $path = MediaPathGenerator::imagePath($media->id, 'jpg');

    $media->update(['path' => $path]);

    Storage::disk('data')->put($path, 'image-content');

    $this->postJson('/api/internal/delete-media', [
        'mediaIds' => [$media->id],
    ], $this->internalHeaders)->assertNoContent();

    Storage::disk('data')->assertMissing($path);
    $this->assertDatabaseMissing('media', ['id' => $media->id]);
});

it('deletes multiple media records in a single request', function () {
    $mediaIds = [];

    for ($i = 0; $i < 3; $i++) {
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

        $path = MediaPathGenerator::imagePath($media->id, 'jpg');

        $media->update(['path' => $path]);

        Storage::disk('data')->put($path, "image-content-{$i}");

        $mediaIds[] = $media->id;
    }

    $this->postJson('/api/internal/delete-media', [
        'mediaIds' => $mediaIds,
    ], $this->internalHeaders)->assertNoContent();

    foreach ($mediaIds as $mediaId) {
        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
    }
});
