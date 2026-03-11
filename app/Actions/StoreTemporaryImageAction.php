<?php

namespace App\Actions;

use App\Enums\MediaType;
use App\Models\TemporaryMedia;
use App\Models\User;
use App\Support\ImageAnalyzer;
use FFMpeg\FFProbe;
use FFMpeg\Format\Video\X264;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

final class StoreTemporaryImageAction
{
    public function execute(UploadedFile $file, User $user): TemporaryMedia
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'gif' && ImageAnalyzer::isAnimatedGif($file)) {
            return $this->handleAnimatedGif($file, $user, $originalName);
        }

        if ($ext === 'mp4') {
            return $this->handleMp4($file, $user, $originalName);
        }

        return $this->handleStaticImage($file, $user, $originalName);
    }

    private function handleStaticImage(UploadedFile $file, User $user, string $originalName): TemporaryMedia
    {
        $image = Image::read($file->path());
        $this->scaleIfNeeded($image);

        $ext = ImageAnalyzer::determineOutputFormat($image);
        $encoded = (string) match ($ext) {
            'png' => $image->toPng(),
            default => $image->toJpeg(),
        };

        $temporaryMedia = $this->createTemporaryMedia($user, [
            'extension' => $ext,
            'name' => $originalName,
            'width' => $image->width(),
            'height' => $image->height(),
            'file_size' => strlen($encoded),
        ]);

        $this->storeFile($temporaryMedia->id, $ext, $encoded);

        return $temporaryMedia;
    }

    private function handleAnimatedGif(UploadedFile $file, User $user, string $originalName): TemporaryMedia
    {
        $ffprobe = FFProbe::create();
        $dimensions = $ffprobe->streams($file->path())->videos()->first()->getDimensions();

        $sourceWidth = $dimensions->getWidth();
        $sourceHeight = $dimensions->getHeight();

        $width = min($sourceWidth, config('encoding.image.media.animation.max_width'));
        $height = (int) round($sourceHeight * ($width / $sourceWidth));

        $format = (new X264)->setAdditionalParameters([
            '-movflags', 'faststart',
            '-pix_fmt', 'yuv420p',
            '-crf', '20',
            '-vf', "scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad=ceil(iw/2)*2:ceil(ih/2)*2",
            '-an',
        ]);

        return $this->processVideoFile($file, $format, $user, $originalName);
    }

    private function handleMp4(UploadedFile $file, User $user, string $originalName): TemporaryMedia
    {
        $format = (new X264)->setAdditionalParameters([
            '-movflags', '+faststart',
            '-avoid_negative_ts', 'make_zero',
            '-map', '0:v:0',
            '-c:v', 'copy',
            '-an',
        ]);

        return $this->processVideoFile($file, $format, $user, $originalName);
    }

    private function processVideoFile(UploadedFile $file, X264 $format, User $user, string $originalName): TemporaryMedia
    {
        $outputName = uniqid('media_').'.mp4';
        FFMpeg::open($file)
            ->export()
            ->inFormat($format)
            ->save($outputName);

        $outputPath = sys_get_temp_dir().'/'.$outputName;
        $ffprobe = FFProbe::create();
        $dimensions = $ffprobe->streams($outputPath)->videos()->first()->getDimensions();
        $fileContent = File::get($outputPath);
        File::delete($outputPath);

        $temporaryMedia = $this->createTemporaryMedia($user, [
            'extension' => 'mp4',
            'name' => $originalName,
            'width' => $dimensions->getWidth(),
            'height' => $dimensions->getHeight(),
            'file_size' => strlen($fileContent),
        ]);

        $this->storeFile($temporaryMedia->id, 'mp4', $fileContent);

        return $temporaryMedia;
    }

    private function scaleIfNeeded(ImageInterface $image): void
    {
        $isHorizontal = $image->width() >= $image->height();
        $limits = $isHorizontal
            ? config('encoding.image.media.horizontal')
            : config('encoding.image.media.vertical');

        $image->scaleDown(
            width: $limits['max_width'],
            height: $limits['max_height'],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createTemporaryMedia(User $user, array $metadata): TemporaryMedia
    {
        return TemporaryMedia::create([
            'user_id' => $user->id,
            'type' => MediaType::Image,
            'metadata' => $metadata,
        ]);
    }

    private function storeFile(int $id, string $extension, string $content): void
    {
        Storage::disk('local')->put(
            config('paths.temporary.upload.image').'/'.$id.'.'.$extension,
            $content,
        );
    }
}
