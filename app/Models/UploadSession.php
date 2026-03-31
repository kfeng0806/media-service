<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\UploadSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'temporary_media_id',
    'type',
    'status',
    'client_upload_key',
    'tus_upload_id',
    'metadata',
])]
class UploadSession extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'status' => UploadSessionStatus::class,
            'metadata' => 'json',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function temporaryMedia(): BelongsTo
    {
        return $this->belongsTo(TemporaryMedia::class);
    }
}
