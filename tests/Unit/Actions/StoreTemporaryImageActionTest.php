<?php

use App\Actions\StoreTemporaryImageAction;
use App\Enums\MediaType;
use App\Models\TemporaryMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->action = app(StoreTemporaryImageAction::class);
});

it('stores a jpeg image and returns temporary media', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

    $result = $this->action->execute($file, $this->user);

    expect($result)->toBeInstanceOf(TemporaryMedia::class);
    expect($result->type)->toBe(MediaType::Image);
    expect($result->user_id)->toBe($this->user->id);
    expect($result->metadata)->toHaveKeys(['extension', 'name', 'width', 'height', 'file_size']);
    expect($result->metadata['extension'])->toBeIn(['jpg', 'png']);
    expect($result->metadata['name'])->toBe('photo');
    expect($result->metadata['width'])->toBe(800);
    expect($result->metadata['height'])->toBe(600);
    expect($result->metadata['file_size'])->toBeGreaterThan(0);

    $ext = $result->metadata['extension'];
    $path = config('paths.temporary.upload.image').'/'.$result->id.'.'.$ext;
    Storage::disk('local')->assertExists($path);
});

it('stores a png image and returns temporary media', function () {
    $file = UploadedFile::fake()->image('graphic.png', 400, 400);

    $result = $this->action->execute($file, $this->user);

    expect($result->metadata['name'])->toBe('graphic');
    expect($result->metadata['width'])->toBe(400);
    expect($result->metadata['height'])->toBe(400);
});

it('scales down oversized vertical images', function () {
    $maxWidth = config('encoding.image.media.vertical.max_width');

    $file = UploadedFile::fake()->image('tall.jpg', 3000, 4000);

    $result = $this->action->execute($file, $this->user);

    expect($result->metadata['width'])->toBeLessThanOrEqual($maxWidth);
    expect($result->metadata['width'])->toBeLessThan(3000);
});

it('scales down oversized horizontal images', function () {
    $maxWidth = config('encoding.image.media.horizontal.max_width');

    $file = UploadedFile::fake()->image('wide.jpg', 6000, 2000);

    $result = $this->action->execute($file, $this->user);

    expect($result->metadata['width'])->toBeLessThanOrEqual($maxWidth);
    expect($result->metadata['width'])->toBeLessThan(6000);
});

it('does not upscale small images', function () {
    $file = UploadedFile::fake()->image('tiny.jpg', 100, 100);

    $result = $this->action->execute($file, $this->user);

    expect($result->metadata['width'])->toBe(100);
    expect($result->metadata['height'])->toBe(100);
});

it('preserves the original filename without extension in metadata', function () {
    $file = UploadedFile::fake()->image('my-awesome-photo.jpeg', 200, 200);

    $result = $this->action->execute($file, $this->user);

    expect($result->metadata['name'])->toBe('my-awesome-photo');
});
