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

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
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
