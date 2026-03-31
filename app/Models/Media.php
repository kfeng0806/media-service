<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'mediable_type',
    'mediable_id',
    'user_id',
    'name',
    'path',
    'type',
    'extension',
    'file_size',
    'download_count',
    'metadata',
    'sort_order',
])]
class Media extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'file_size' => 'integer',
            'download_count' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'json',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
