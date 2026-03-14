<?php

use App\Actions\StoreMediaAction;
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
    $this->action = app(StoreMediaAction::class);
});

it('moves image temp file to data disk with correct path', function () {
    $temp = TemporaryMedia::create([
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

    $tmpPath = config('paths.temporary.upload.image').'/'.$temp->id.'.jpg';
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

    $this->action->execute([
        ['media_id' => $media->id, 'temporary_media_id' => $temp->id],
    ]);

    $expectedPath = MediaPathGenerator::imagePath($media->id, 'jpg');
    Storage::disk('data')->assertExists($expectedPath);
    Storage::disk('local')->assertMissing($tmpPath);

    $media->refresh();
    expect($media->path)->toBe($expectedPath);
});

it('deletes temporary media record after storing', function () {
    $temp = TemporaryMedia::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Image,
        'metadata' => [
            'extension' => 'png',
            'name' => 'graphic',
            'width' => 400,
            'height' => 400,
            'file_size' => 2048,
        ],
    ]);

    $tmpPath = config('paths.temporary.upload.image').'/'.$temp->id.'.png';
    Storage::disk('local')->put($tmpPath, 'fake-png-content');

    $media = Media::create([
        'mediable_type' => Post::class,
        'mediable_id' => 1,
        'user_id' => $this->user->id,
        'name' => 'graphic',
        'path' => '',
        'type' => MediaType::Image,
        'extension' => 'png',
        'file_size' => 2048,
        'metadata' => ['width' => 400, 'height' => 400],
    ]);

    $this->action->execute([
        ['media_id' => $media->id, 'temporary_media_id' => $temp->id],
    ]);

    $this->assertDatabaseMissing('temporary_media', ['id' => $temp->id]);
});

it('handles mp4 extension for image type media', function () {
    $temp = TemporaryMedia::create([
        'user_id' => $this->user->id,
        'type' => MediaType::Image,
        'metadata' => [
            'extension' => 'mp4',
            'name' => 'animation',
            'width' => 640,
            'height' => 480,
            'file_size' => 5000,
        ],
    ]);

    $tmpPath = config('paths.temporary.upload.image').'/'.$temp->id.'.mp4';
    Storage::disk('local')->put($tmpPath, 'fake-mp4-content');

    $media = Media::create([
        'mediable_type' => Post::class,
        'mediable_id' => 1,
        'user_id' => $this->user->id,
        'name' => 'animation',
        'path' => '',
        'type' => MediaType::Image,
        'extension' => 'mp4',
        'file_size' => 5000,
        'metadata' => ['width' => 640, 'height' => 480],
    ]);

    $this->action->execute([
        ['media_id' => $media->id, 'temporary_media_id' => $temp->id],
    ]);

    $expectedPath = MediaPathGenerator::imagePath($media->id, 'mp4');
    Storage::disk('data')->assertExists($expectedPath);

    $media->refresh();
    expect($media->path)->toBe($expectedPath);
});

it('skips when temporary media not found', function () {
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

    $this->action->execute([
        ['media_id' => $media->id, 'temporary_media_id' => 9999],
    ]);

    $media->refresh();
    expect($media->path)->toBe('');
});
