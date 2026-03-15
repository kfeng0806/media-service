<?php

namespace App\Http\Requests;

use App\Enums\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateUploadSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, \Closure|string|Rule>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in($this->supportedTypes())],
            'client_upload_key' => ['required', 'string', 'max:255'],
            'file_name' => [
                'required',
                'string',
                'max:255',
                fn (string $attribute, mixed $value, \Closure $fail) => $this->validateFileExtension($value, $fail),
            ],
            'mime_type' => ['required', 'string', 'max:255'],
            'file_size' => [
                'required',
                'integer',
                'min:1',
                fn (string $attribute, mixed $value, \Closure $fail) => $this->validateFileSize($value, $fail),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function supportedTypes(): array
    {
        return [
            MediaType::Video->value,
            MediaType::Audio->value,
            MediaType::Attachment->value,
        ];
    }

    private function validateFileSize(mixed $value, \Closure $fail): void
    {
        $type = $this->input('type');

        if ((int) $value <= $this->maxFileSize($type)) {
            return;
        }

        $maxSize = number_format($this->maxFileSize($type));

        $fail("The {$type} file size may not be greater than {$maxSize} bytes.");
    }

    private function validateFileExtension(mixed $value, \Closure $fail): void
    {
        $type = $this->input('type');

        $extension = strtolower(pathinfo((string) $value, PATHINFO_EXTENSION));
        $allowedExtensions = $this->allowedExtensions($type);

        if (in_array($extension, $allowedExtensions, true)) {
            return;
        }

        $fail("The {$type} file must have one of the following extensions: ".implode(', ', $allowedExtensions).'.');
    }

    private function maxFileSize(string $type): int
    {
        return config("upload.{$type}.max_size");
    }

    /**
     * @return list<string>
     */
    private function allowedExtensions(string $type): array
    {
        return config("upload.{$type}.allowed_extensions", []);
    }
}
