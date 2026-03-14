<?php

use App\Actions\DeleteMediaAction;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Support\MediaPathGenerator;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('data');
    $this->user = User::factory()->create();
    $this->action = app(DeleteMediaAction::class);
});

it('deletes image file from data disk and removes media record', function () {
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

    $this->action->execute([$media->id]);

    Storage::disk('data')->assertMissing($path);
    $this->assertDatabaseMissing('media', ['id' => $media->id]);
});

it('deletes media record even when file is already missing', function () {
    $media = Media::create([
        'mediable_type' => Post::class,
        'mediable_id' => 1,
        'user_id' => $this->user->id,
        'name' => 'photo',
        'path' => MediaPathGenerator::imagePath(1, 'jpg'),
        'type' => MediaType::Image,
        'extension' => 'jpg',
        'file_size' => 1024,
        'metadata' => ['width' => 800, 'height' => 600],
    ]);

    $this->action->execute([$media->id]);

    $this->assertDatabaseMissing('media', ['id' => $media->id]);
});

it('skips unknown media ids', function () {
    $this->action->execute([9999]);

    expect(Media::count())->toBe(0);
});

it('deletes multiple image media records', function () {
    $mediaIds = [];

    for ($i = 0; $i < 2; $i++) {
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

    $this->action->execute($mediaIds);

    expect(Media::count())->toBe(0);
});
