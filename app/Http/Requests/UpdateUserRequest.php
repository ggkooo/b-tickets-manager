<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')?->id;
        $location = $this->user()?->location;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'login' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('users', 'login')
                    ->ignore($userId)
                    ->where(function ($query) use ($location) {
                        if ($location !== null) {
                            $query->where('location', $location);
                        }
                    }),
            ],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}