<?php

namespace App\Models;

use App\Enums\AppSettingType;
use App\Support\AppSettingSchemas;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }
}
