<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'type',
    'metadata',
])]
class TemporaryMedia extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'metadata' => 'json',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uploadSession(): HasOne
    {
        return $this->hasOne(UploadSession::class);
    }
}
