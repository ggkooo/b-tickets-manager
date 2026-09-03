<?php

namespace App\Http\Requests;

use App\Models\PrinterSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrinterSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $connectionType = $this->input('connection_type');
        $host = trim((string) $this->input('host', ''));
        $sharePath = trim((string) $this->input('share_path', ''));

        if ($connectionType === PrinterSetting::CONNECTION_NETWORK && $host === '') {
            abort(response()->json([
                'message' => 'Host e obrigatorio para impressora de rede.',
            ], 422));
        }

        if ($connectionType === PrinterSetting::CONNECTION_SHARED_WINDOWS && $sharePath === '') {
            abort(response()->json([
                'message' => 'share_path e obrigatorio para impressora compartilhada no Windows.',
            ], 422));
        }
    }

    public function rules(): array
    {
        $location = $this->user()->location;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('printer_settings', 'name')->where(fn ($query) => $query->where('location', $location)),
            ],
            'enabled' => ['required', 'boolean'],
            'connection_type' => [
                'required',
                'string',
                'in:' . implode(',', PrinterSetting::allowedConnectionTypes()),
            ],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'share_path' => ['nullable', 'string', 'max:255'],
            'profile' => ['nullable', 'string', 'max:100'],
            'header' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function locationForUser(): string
    {
        return $this->user()->location;
    }
}
