<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // max is in kilobytes: 5GB = 5 * 1024 * 1024 KB.
            'video' => 'required|file|mimetypes:video/mp4|max:5242880',
        ];
    }
}
