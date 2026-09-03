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
            // Real-world .mp4 files aren't all sniffed as exactly
            // "video/mp4" — files exported with a QuickTime container brand
            // (common from Apple devices and some editors, even saved with
            // a .mp4 extension) are detected as video/quicktime, and some
            // encoders produce application/mp4 or video/x-m4v instead.
            // max is in kilobytes: 5GB = 5 * 1024 * 1024 KB.
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,application/mp4,video/x-m4v|max:5242880',
        ];
    }
}
