<?php

namespace App\Actions;

use App\Enums\MediaType;
use App\Models\Media;
use App\Support\MediaPathGenerator;
use Illuminate\Support\Facades\Storage;

final class DeleteMediaAction
{
    private const int DELETE_BATCH_SIZE = 500;

    /**
     * @param  array<int, int>  $mediaIds
     */
    public function execute(array $mediaIds): void
    {
        Media::query()
            ->select(['id', 'type', 'extension'])
            ->whereKey($mediaIds)
            ->chunkById(self::DELETE_BATCH_SIZE, function ($mediaItems): void {
                $deletedIds = [];
                $filePaths = [];
                $directoryPaths = [];

                foreach ($mediaItems as $media) {
                    $targets = $this->deletionTargets($media);

                    array_push($filePaths, ...$targets['files']);
                    array_push($directoryPaths, ...$targets['directories']);
                    $deletedIds[] = $media->id;
                }

                $this->deleteFiles($filePaths);
                $this->deleteDirectories($directoryPaths);

                Media::query()
                    ->whereKey($deletedIds)
                    ->delete();
            });
    }

    /**
     * @return array{files: array<int, string>, directories: array<int, string>}
     */
    private function deletionTargets(Media $media): array
    {
        return match ($media->type) {
            MediaType::Image => [
                'files' => [MediaPathGenerator::imagePath($media->id, $media->extension)],
                'directories' => [],
            ],
            MediaType::Video => [
                'files' => [],
                'directories' => [MediaPathGenerator::videoDir($media->id)],
            ],
            MediaType::Audio => [
                'files' => [
                    MediaPathGenerator::audioPath($media->id),
                    MediaPathGenerator::audioArtworkPath($media->id),
                ],
                'directories' => [],
            ],
            MediaType::Attachment => [
                'files' => [MediaPathGenerator::attachmentPath($media->id, $media->extension)],
                'directories' => [],
            ],
        };
    }

    /**
     * @param  array<int, string>  $filePaths
     */
    private function deleteFiles(array $filePaths): void
    {
        $filePaths = array_values(array_unique(array_filter($filePaths)));

        if ($filePaths === []) {
            return;
        }

        Storage::disk('data')->delete($filePaths);
    }

    /**
     * @param  array<int, string>  $directoryPaths
     */
    private function deleteDirectories(array $directoryPaths): void
    {
        $directoryPaths = array_values(array_unique(array_filter($directoryPaths)));

        foreach ($directoryPaths as $directoryPath) {
            Storage::disk('data')->deleteDirectory($directoryPath);
        }
    }
}
