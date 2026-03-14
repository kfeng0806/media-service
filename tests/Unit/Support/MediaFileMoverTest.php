<?php

use App\Support\MediaFileMover;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('data');
    $this->mover = app(MediaFileMover::class);
});

it('moves a file between disks', function () {
    Storage::disk('local')->put('tmp/upload/images/example.jpg', 'content');

    $wasMoved = $this->mover->moveFile(
        'local',
        'tmp/upload/images/example.jpg',
        'data',
        'media/images/000/000/example.jpg',
    );

    expect($wasMoved)->toBeTrue();

    Storage::disk('local')->assertMissing('tmp/upload/images/example.jpg');
    Storage::disk('data')->assertExists('media/images/000/000/example.jpg');
});

it('returns false when source file does not exist', function () {
    $wasMoved = $this->mover->moveFile(
        'local',
        'tmp/upload/images/missing.jpg',
        'data',
        'media/images/000/000/missing.jpg',
    );

    expect($wasMoved)->toBeFalse();
    Storage::disk('data')->assertMissing('media/images/000/000/missing.jpg');
});

it('moves a directory between disks', function () {
    Storage::disk('local')->put('tmp/upload/videos/example/master.m3u8', 'playlist');
    Storage::disk('local')->put('tmp/upload/videos/example/segments/0001.ts', 'segment');

    $wasMoved = $this->mover->moveDirectory(
        'local',
        'tmp/upload/videos/example',
        'data',
        'media/videos/000/000/example',
    );

    expect($wasMoved)->toBeTrue();

    Storage::disk('local')->assertMissing('tmp/upload/videos/example/master.m3u8');
    Storage::disk('local')->assertMissing('tmp/upload/videos/example/segments/0001.ts');
    Storage::disk('data')->assertExists('media/videos/000/000/example/master.m3u8');
    Storage::disk('data')->assertExists('media/videos/000/000/example/segments/0001.ts');
});

it('returns false when source directory does not exist', function () {
    $wasMoved = $this->mover->moveDirectory(
        'local',
        'tmp/upload/videos/missing',
        'data',
        'media/videos/000/000/missing',
    );

    expect($wasMoved)->toBeFalse();
    Storage::disk('data')->assertMissing('media/videos/000/000/missing/master.m3u8');
});
