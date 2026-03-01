<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
        'type',
        'extension',
        'file_size',
        'metadata',
        'sort_order',
    ];

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
