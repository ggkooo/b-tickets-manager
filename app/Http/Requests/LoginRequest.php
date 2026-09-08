<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('login')) {
            $normalized['login'] = Str::lower(trim((string) $this->input('login')));
        }

        if ($this->has('location')) {
            $normalized['location'] = Str::lower(trim((string) $this->input('location')));
        }

        if (!empty($normalized)) {
            $this->merge($normalized);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => 'required|string|max:255',
            'password' => 'required|string',
            'location' => 'required|string|in:' . implode(',', User::allowedLocations()),
        ];
    }
}
