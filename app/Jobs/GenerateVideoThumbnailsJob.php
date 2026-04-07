<?php

namespace App\Jobs;

use App\Models\UploadSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Filters\TileFactory;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class GenerateVideoThumbnailsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(
        public readonly int $uploadSessionId,
    ) {
        $this->onQueue('encoding');
    }

    public function handle(): void
    {
        $session = UploadSession::findOrFail($this->uploadSessionId);
        $temporaryMedia = $session->temporaryMedia;

        $videoDir = config('paths.temporary.upload.video')."/{$temporaryMedia->id}";
        $masterPath = Storage::disk('local')->path("{$videoDir}/master.mp4");

        $thumbnailDir = "{$videoDir}/thumbnails";
        Storage::disk('local')->makeDirectory($thumbnailDir);

        FFMpeg::openUrl($masterPath)
            ->exportTile(function (TileFactory $factory) use ($thumbnailDir) {
                $factory->interval(5)
                    ->scale(160)
                    ->grid(5, 5)
                    ->generateVTT("{$thumbnailDir}/thumbnails.vtt");
            })
            ->save("{$thumbnailDir}/tile_%05d.jpg");

        $vttPath = "{$thumbnailDir}/thumbnails.vtt";
        $vtt = Storage::disk('local')->get($vttPath);
        $vtt = str_replace("{$thumbnailDir}/", '', $vtt);
        Storage::disk('local')->put($vttPath, $vtt);

        $temporaryMedia->update([
            'metadata' => array_merge($temporaryMedia->metadata ?? [], [
                'has_thumbnails' => true,
            ]),
        ]);
    }
}
