<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'metadata',
    ];

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
}
