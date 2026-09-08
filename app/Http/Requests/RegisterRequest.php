<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (!$this->has('login')) {
            return;
        }

        $this->merge([
            'login' => Str::lower(trim((string) $this->input('login'))),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $location = $this->user()?->location;

        return [
            'name' => 'required|string|max:255',
            'login' => [
                'required',
                'string',
                'max:100',
                Rule::unique('users', 'login')->where(function ($query) use ($location) {
                    if ($location !== null) {
                        $query->where('location', $location);
                    }
                }),
            ],
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
