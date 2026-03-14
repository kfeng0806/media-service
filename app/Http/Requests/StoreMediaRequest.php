<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'media' => ['required', 'array', 'min:1'],
            'media.*.media_id' => ['required', 'integer'],
            'media.*.temporary_media_id' => ['required', 'integer'],
        ];
    }
}
