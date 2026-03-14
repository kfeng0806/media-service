<?php

namespace App\Actions;

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\TemporaryMedia;
use App\Support\MediaFileMover;
use App\Support\MediaPathGenerator;

final readonly class StoreMediaAction
{
    public function __construct(
        private MediaFileMover $fileMover,
    ) {}

    /**
     * @param  array<int, array{media_id: int, temporary_media_id: int}>  $mediaRecords
     */
    public function execute(array $mediaRecords): void
    {
        foreach ($mediaRecords as $record) {
            $this->processRecord($record['media_id'], $record['temporary_media_id']);
        }
    }

    private function processRecord(int $mediaId, int $temporaryMediaId): void
    {
        $temporaryMedia = TemporaryMedia::find($temporaryMediaId);
        $media = Media::find($mediaId);

        if (! $temporaryMedia || ! $media) {
            return;
        }

        $this->storeByType($media, $temporaryMedia);

        $temporaryMedia->delete();
    }

    private function storeByType(Media $media, TemporaryMedia $temporaryMedia): void
    {
        match ($media->type) {
            MediaType::Image => $this->storeImage($media, $temporaryMedia),
            default => null,
        };
    }

    private function storeImage(Media $media, TemporaryMedia $temporaryMedia): void
    {
        $extension = $temporaryMedia->metadata['extension'] ?? $media->extension;
        $temporaryPath = config('paths.temporary.upload.image').'/'.$temporaryMedia->id.'.'.$extension;
        $finalRelativePath = MediaPathGenerator::imagePath($media->id, $media->extension);

        $wasMoved = $this->fileMover->moveFile(
            'local',
            $temporaryPath,
            'data',
            $finalRelativePath,
        );

        if (! $wasMoved) {
            return;
        }

        $media->update(['path' => $finalRelativePath]);
    }
}
