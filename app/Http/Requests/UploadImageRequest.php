<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
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
        $maxKb = (int) (config('upload.image.max_size') / 1024);

        return [
            'file' => ['required', 'file', "max:{$maxKb}", 'mimes:jpg,jpeg,png,gif,mp4'],
        ];
    }
}
