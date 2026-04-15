<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

final class MediaFileMover
{
    public function moveFile(
        string $sourceDisk,
        string $sourceRelativePath,
        string $destinationDisk,
        string $destinationRelativePath,
    ): bool {
        $paths = $this->prepareMove(
            $sourceDisk,
            $sourceRelativePath,
            $destinationDisk,
            $destinationRelativePath,
            'exists',
        );

        if ($paths === null) {
            return false;
        }

        File::move($paths['source'], $paths['destination']);

        return true;
    }

    public function moveDirectory(
        string $sourceDisk,
        string $sourceRelativePath,
        string $destinationDisk,
        string $destinationRelativePath,
    ): bool {
        $paths = $this->prepareMove(
            $sourceDisk,
            $sourceRelativePath,
            $destinationDisk,
            $destinationRelativePath,
            'directoryExists',
        );

        if ($paths === null) {
            return false;
        }

        if (File::moveDirectory($paths['source'], $paths['destination'])) {
            return true;
        }

        if (! File::copyDirectory($paths['source'], $paths['destination'])) {
            return false;
        }

        File::deleteDirectory($paths['source']);

        return true;
    }

    /**
     * @return array{source: string, destination: string}|null
     */
    private function prepareMove(
        string $sourceDisk,
        string $sourceRelativePath,
        string $destinationDisk,
        string $destinationRelativePath,
        string $existsMethod,
    ): ?array {
        $sourceStorage = Storage::disk($sourceDisk);

        if (! $this->sourceExists($sourceStorage, $sourceRelativePath, $existsMethod)) {
            return null;
        }

        $destinationStorage = Storage::disk($destinationDisk);

        $destinationStorage->makeDirectory(dirname($destinationRelativePath));

        return [
            'source' => $sourceStorage->path($sourceRelativePath),
            'destination' => $destinationStorage->path($destinationRelativePath),
        ];
    }

    private function sourceExists(
        FilesystemAdapter $sourceStorage,
        string $sourceRelativePath,
        string $existsMethod,
    ): bool {
        return $sourceStorage->{$existsMethod}($sourceRelativePath);
    }
}
