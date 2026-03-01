<?php

use App\Enums\MediaType;
use App\Models\TemporaryMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('requires authentication to upload an image', function () {
    $this->postJson('/api/upload/media/image')
        ->assertUnauthorized();
});

it('validates that a file is required', function () {
    $this->actingAs($this->user)
        ->postJson('/api/upload/media/image')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('validates file mime types', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->actingAs($this->user)
        ->postJson('/api/upload/media/image', ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('validates file max size', function () {
    $file = UploadedFile::fake()->create('large.jpg', 21000, 'image/jpeg');

    $this->actingAs($this->user)
        ->postJson('/api/upload/media/image', ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('uploads a jpeg image and creates temporary media', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

    $response = $this->actingAs($this->user)
        ->postJson('/api/upload/media/image', ['file' => $file])
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'metadata' => [
                    'extension',
                    'name',
                    'width',
                    'height',
                    'file_size',
                ],
            ],
        ]);

    $data = $response->json('data');
    expect($data['type'])->toBe(MediaType::Image->value);
    expect($data['metadata']['extension'])->toBe('jpg');
    expect($data['metadata']['name'])->toBe('photo');
    expect($data['metadata']['width'])->toBeInt();
    expect($data['metadata']['height'])->toBeInt();
    expect($data['metadata']['file_size'])->toBeGreaterThan(0);

    $this->assertDatabaseHas('temporary_media', [
        'id' => $data['id'],
        'user_id' => $this->user->id,
        'type' => MediaType::Image->value,
    ]);

    $temporaryMedia = TemporaryMedia::find($data['id']);
    $ext = $temporaryMedia->metadata['extension'];
    $path = config('paths.temporary.upload.image').'/'.$temporaryMedia->id.'.'.$ext;
    Storage::disk('local')->assertExists($path);
});

it('uploads a png image and creates temporary media', function () {
    $file = UploadedFile::fake()->image('graphic.png', 400, 400);

    $response = $this->actingAs($this->user)
        ->postJson('/api/upload/media/image', ['file' => $file])
        ->assertCreated();

    $data = $response->json('data');
    expect($data['type'])->toBe(MediaType::Image->value);
    expect($data['metadata']['name'])->toBe('graphic');
});

it('returns correct resource structure for temporary media', function () {
    $file = UploadedFile::fake()->image('test.jpg', 200, 200);

    $this->actingAs($this->user)
        ->postJson('/api/upload/media/image', ['file' => $file])
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'metadata',
                'created_at',
            ],
        ]);
});
