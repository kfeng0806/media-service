<?php

namespace App\Actions;

use App\Enums\MediaType;
use App\Enums\UploadSessionStatus;
use App\Jobs\GenerateVideoThumbnailsJob;
use App\Jobs\ProcessTemporaryAudioUploadJob;
use App\Jobs\ProcessTemporaryVideoUploadJob;
use App\Models\TemporaryMedia;
use App\Models\UploadSession;
use App\Support\MediaFileMover;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final readonly class CompleteTusUploadAction
{
    public function __construct(
        private MediaFileMover $fileMover,
    ) {}

    /**
     * @param  array{session_id: int, upload_id: string}  $attributes
     */
    public function execute(array $attributes): void
    {
        $uploadSession = UploadSession::query()->findOrFail($attributes['session_id']);

        if ($this->shouldSkipCompletion($uploadSession)) {
            return;
        }

        $this->assertSourceFileExists($attributes['upload_id']);

        $uploadSession->update([
            'tus_upload_id' => $attributes['upload_id'],
            'status' => UploadSessionStatus::Uploaded,
        ]);

        match ($uploadSession->type) {
            MediaType::Attachment => $this->completeAttachmentUpload($uploadSession),
            MediaType::Video, MediaType::Audio => $this->markForProcessing($uploadSession),
        };
    }

    private function completeAttachmentUpload(UploadSession $uploadSession): void
    {
        $fileName = (string) ($uploadSession->metadata['file_name'] ?? 'attachment');
        $extension = $this->resolveExtension($fileName);
        $temporaryMedia = TemporaryMedia::create([
            'user_id' => $uploadSession->user_id,
            'type' => MediaType::Attachment,
            'metadata' => [
                'extension' => $extension,
                'name' => pathinfo($fileName, PATHINFO_FILENAME),
                'file_size' => $uploadSession->metadata['file_size'] ?? 0,
            ],
        ]);

        $targetPath = config('paths.temporary.upload.attachment')."/{$temporaryMedia->id}.{$extension}";
        $wasMoved = $this->fileMover->moveFile(
            'local',
            $this->sourcePath($uploadSession->tus_upload_id ?? ''),
            'local',
            $targetPath,
        );

        if ($wasMoved) {
            // Clear tus metadata file
            Storage::disk('local')->delete($this->sourcePath("{$uploadSession->tus_upload_id}.json"));
        } else {
            $temporaryMedia->delete();

            throw ValidationException::withMessages([
                'upload_id' => 'The uploaded file could not be moved into temporary storage.',
            ]);
        }

        $uploadSession->update([
            'temporary_media_id' => $temporaryMedia->id,
            'status' => UploadSessionStatus::Completed,
        ]);
    }

    private function markForProcessing(UploadSession $uploadSession): void
    {
        $uploadSession->update([
            'status' => UploadSessionStatus::Processing,
        ]);

        match ($uploadSession->type) {
            MediaType::Video => Bus::chain([
                new ProcessTemporaryVideoUploadJob($uploadSession->id),
                new GenerateVideoThumbnailsJob($uploadSession->id),
            ])->onQueue('encoding')->dispatch(),
            MediaType::Audio => ProcessTemporaryAudioUploadJob::dispatch($uploadSession->id),
        };
    }

    private function assertSourceFileExists(string $uploadId): void
    {
        if (Storage::disk('local')->exists($this->sourcePath($uploadId))) {
            return;
        }

        throw ValidationException::withMessages([
            'upload_id' => 'The uploaded file does not exist.',
        ]);
    }

    private function shouldSkipCompletion(UploadSession $uploadSession): bool
    {
        if ($uploadSession->status === UploadSessionStatus::Processing) {
            return true;
        }

        return $uploadSession->status === UploadSessionStatus::Completed
            && $uploadSession->temporary_media_id !== null;
    }

    private function resolveExtension(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : 'bin';
    }

    private function sourcePath(string $uploadId): string
    {
        return rtrim(config('services.tus.upload_directory'), '/')."/{$uploadId}";
    }
}
