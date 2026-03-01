<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostArchive extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_completed',
        'file_path',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'file_size' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
