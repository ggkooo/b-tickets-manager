<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'login' => ['sometimes', 'string', 'max:100', Rule::unique('users', 'login')->ignore($userId)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}