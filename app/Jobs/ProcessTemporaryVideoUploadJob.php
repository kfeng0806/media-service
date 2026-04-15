<?php

namespace App\Jobs;

use App\Enums\MediaType;
use App\Enums\UploadSessionStatus;
use App\Models\TemporaryMedia;
use App\Models\UploadSession;
use App\Support\MediaFileMover;
use App\Support\RawVideoFormat;
use App\Support\UploadSessionCache;
use FFMpeg\FFProbe;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Symfony\Component\Process\Process;

class ProcessTemporaryVideoUploadJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public readonly int $uploadSessionId,
    ) {
        $this->onQueue('encoding');
    }

    public function handle(MediaFileMover $fileMover): void
    {
        $session = UploadSession::findOrFail($this->uploadSessionId);
        $sourcePath = $this->tusSourcePath($session->tus_upload_id);
        $absoluteSource = Storage::disk('local')->path($sourcePath);

        $probe = $this->probe($absoluteSource);
        $encodingDir = config('paths.temporary.encoding')."/{$session->id}";

        $this->reportProgress(0);

        $this->transcode($absoluteSource, $encodingDir, $probe);

        $this->reportProgress(50);

        $this->package($encodingDir);

        $this->reportProgress(80);

        $this->extractCover($encodingDir, $probe['duration']);

        $this->reportProgress(90);

        $this->complete($session, $encodingDir, $fileMover, $probe);

        $this->cleanup($session);

        $this->reportProgress(100);
    }

    private function probe(string $absolutePath): array
    {
        $ffprobe = FFProbe::create();
        $streams = $ffprobe->streams($absolutePath);

        $video = $streams->videos()->first();
        $audio = $streams->audios()->first();
        $dimensions = $video->getDimensions();

        return [
            'duration' => (float) $ffprobe->format($absolutePath)->get('duration', 0),
            'width' => $dimensions->getWidth(),
            'height' => $dimensions->getHeight(),
            'video_codec' => $video->get('codec_name'),
            'pix_fmt' => $video->get('pix_fmt'),
            'bit_rate' => (int) ($video->get('bit_rate') ?: 0),
            'bits_per_raw_sample' => $video->get('bits_per_raw_sample'),
            'sample_aspect_ratio' => $video->get('sample_aspect_ratio'),
            'audio_codec' => $audio?->get('codec_name'),
            'audio_profile' => $audio?->get('profile'),
            'audio_channels' => (int) ($audio?->get('channels') ?? 0),
        ];
    }

    private function transcode(string $absoluteSource, string $encodingDir, array $probe): void
    {
        $masterPath = "{$encodingDir}/master.mp4";
        Storage::disk('local')->makeDirectory($encodingDir);

        $parameters = [
            '-movflags', '+faststart',
            '-avoid_negative_ts', 'make_zero',
            '-map', '0:v:0',
        ];

        if ($this->needsTranscoding($probe)) {
            $parameters = config('encoding.video.use_nvenc')
                ? [
                    ...$parameters,
                    '-c:v', 'h264_nvenc',
                    '-cq', '19',
                    '-preset', 'p7',
                    '-pix_fmt', 'yuv420p',
                    '-fps_mode', 'vfr',
                ]
                : [
                    ...$parameters,
                    '-c:v', 'libx264',
                    '-crf', '22',
                    '-preset', 'slow',
                    '-pix_fmt', 'yuv420p',
                    '-fps_mode', 'vfr',
                ];

            $filters = $this->buildVideoFilters($probe);

            if ($filters !== '') {
                $parameters = [...$parameters, '-vf', $filters];
            }
        } else {
            $parameters = [...$parameters, '-c:v', 'copy'];
        }

        if ($probe['audio_codec'] === null) {
            $parameters[] = '-an';
        } elseif (
            $probe['audio_codec'] === 'aac'
            && str_contains(strtolower($probe['audio_profile'] ?? ''), 'lc')
            && $probe['audio_channels'] === 2
        ) {
            $parameters = [...$parameters, '-map', '0:a:0', '-c:a', 'copy'];
        } else {
            $parameters = [...$parameters, '-map', '0:a:0', '-c:a', 'aac', '-b:a', '128k', '-ac', '2'];
        }

        $format = (new RawVideoFormat)->setAdditionalParameters($parameters);

        $format->on('progress', function ($video, $format, $percentage) {
            $this->reportProgress((int) round($percentage * 0.5));
        });

        FFMpeg::openUrl($absoluteSource)
            ->export()
            ->inFormat($format)
            ->save($masterPath);
    }

    private function needsTranscoding(array $probe): bool
    {
        if (strtolower($probe['video_codec']) !== 'h264') {
            return true;
        }

        if ($probe['pix_fmt'] && ! str_contains($probe['pix_fmt'], 'yuv420')) {
            return true;
        }

        if ($probe['bit_rate'] > 5_000_000) {
            return true;
        }

        if ($probe['bits_per_raw_sample'] && (int) $probe['bits_per_raw_sample'] !== 8) {
            return true;
        }

        if ($probe['sample_aspect_ratio'] && $probe['sample_aspect_ratio'] !== '1:1') {
            return true;
        }

        return false;
    }

    private function buildVideoFilters(array $probe): string
    {
        $filters = [];

        $needsParFix = $probe['sample_aspect_ratio'] && $probe['sample_aspect_ratio'] !== '1:1';
        $needsScale = $probe['width'] > 1920 || $probe['height'] > 1080;

        if ($needsParFix && $needsScale) {
            $filters[] = "scale='min(1920,iw)':'min(1080,iw/dar)':force_original_aspect_ratio=decrease";
            $filters[] = 'setsar=1:1';
        } elseif ($needsParFix) {
            $filters[] = "scale='iw':'iw/dar'";
            $filters[] = 'setsar=1:1';
        } elseif ($needsScale) {
            $filters[] = "scale='min(1920,iw)':'min(1080,ih)':force_original_aspect_ratio=decrease";
        }

        if ($filters !== []) {
            $filters[] = 'pad=ceil(iw/2)*2:ceil(ih/2)*2';
        }

        return implode(',', $filters);
    }

    private function package(string $encodingDir): void
    {
        $masterAbsolute = Storage::disk('local')->path("{$encodingDir}/master.mp4");
        $segmentsDir = Storage::disk('local')->path("{$encodingDir}/segments");
        Storage::disk('local')->makeDirectory("{$encodingDir}/segments");

        $binary = config('encoding.video.shaka_binary');
        $segmentDuration = config('encoding.video.segment_duration');

        $command = [
            $binary,
            "in={$masterAbsolute},stream=video,init_segment={$segmentsDir}/h264_default/init.mp4,segment_template={$segmentsDir}/h264_default/\$Number\$.m4s,playlist_name=h264_default.m3u8",
            '--segment_duration', (string) $segmentDuration,
            '--mpd_output', "{$segmentsDir}/vod.mpd",
            '--hls_master_playlist_output', "{$segmentsDir}/vod.m3u8",
        ];

        if ($this->hasAudioStream($masterAbsolute)) {
            Storage::disk('local')->makeDirectory("{$encodingDir}/segments/audio");
            array_splice($command, 2, 0, [
                "in={$masterAbsolute},stream=audio,init_segment={$segmentsDir}/audio/init.mp4,segment_template={$segmentsDir}/audio/\$Number\$.m4s,playlist_name=audio.m3u8",
            ]);
        }

        Storage::disk('local')->makeDirectory("{$encodingDir}/segments/h264_default");

        $process = new Process($command);
        $process->setTimeout(1800);
        $process->mustRun();
    }

    private function extractCover(string $encodingDir, float $duration): void
    {
        $positionPercent = config('encoding.video.cover_position_percent');
        $seconds = max(0.1, $duration * $positionPercent / 100);

        FFMpeg::openUrl(Storage::disk('local')->path("{$encodingDir}/master.mp4"))
            ->getFrameFromSeconds($seconds)
            ->export()
            ->save("{$encodingDir}/cover.jpg");
    }

    private function complete(
        UploadSession $session,
        string $encodingDir,
        MediaFileMover $fileMover,
        array $probe,
    ): void {
        $fileName = (string) ($session->metadata['file_name'] ?? 'video');

        $temporaryMedia = TemporaryMedia::create([
            'user_id' => $session->user_id,
            'type' => MediaType::Video,
            'metadata' => [
                'extension' => 'mp4',
                'name' => pathinfo($fileName, PATHINFO_FILENAME),
                'file_name' => $fileName,
                'width' => $probe['width'],
                'height' => $probe['height'],
                'duration' => $probe['duration'],
                'file_size' => Storage::disk('local')->size("{$encodingDir}/master.mp4"),
            ],
        ]);

        $fileMover->moveDirectory(
            'local',
            $encodingDir,
            'local',
            config('paths.temporary.upload.video')."/{$temporaryMedia->id}",
        );

        $session->update([
            'temporary_media_id' => $temporaryMedia->id,
            'status' => UploadSessionStatus::Completed,
        ]);
    }

    private function cleanup(UploadSession $session): void
    {
        if ($session->tus_upload_id) {
            $tusDir = rtrim(config('services.tus.upload_directory'), '/');
            Storage::disk('local')->delete("{$tusDir}/{$session->tus_upload_id}");
            Storage::disk('local')->delete("{$tusDir}/{$session->tus_upload_id}.json");
        }
    }

    private function hasAudioStream(string $absolutePath): bool
    {
        return FFProbe::create()->streams($absolutePath)->audios()->count() > 0;
    }

    private function tusSourcePath(string $tusUploadId): string
    {
        return rtrim(config('services.tus.upload_directory'), '/')."/{$tusUploadId}";
    }

    private function reportProgress(int $progress): void
    {
        Cache::put(
            UploadSessionCache::metadataKey($this->uploadSessionId),
            ['progress' => $progress],
            3600,
        );
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Video processing failed', [
            'upload_session_id' => $this->uploadSessionId,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);

        $session = UploadSession::find($this->uploadSessionId);

        if ($session) {
            $session->update(['status' => UploadSessionStatus::Failed]);
        }

        Cache::put(
            UploadSessionCache::metadataKey($this->uploadSessionId),
            ['progress' => -1],
            3600,
        );

        $encodingDir = config('paths.temporary.encoding')."/{$this->uploadSessionId}";

        if (Storage::disk('local')->directoryExists($encodingDir)) {
            Storage::disk('local')->deleteDirectory($encodingDir);
        }
    }
}
