<?php

namespace App\Http\Requests;

use App\Models\PrinterSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrinterSettingRequest extends FormRequest
{
    private PrinterSetting $printerSetting;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $location = $this->user()->location;

        $setting = PrinterSetting::query()
            ->forLocation($location)
            ->whereKey($this->route('printerSetting'))
            ->first();

        if ($setting === null) {
            abort(response()->json([
                'message' => 'Impressora nao encontrada.',
            ], 404));
        }

        $this->printerSetting = $setting;

        $connectionType = $this->input('connection_type', $setting->connection_type);
        $host = trim((string) $this->input('host', $setting->host ?? ''));
        $sharePath = trim((string) $this->input('share_path', $setting->share_path ?? ''));

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

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $location = $this->user()->location;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:120',
                Rule::unique('printer_settings', 'name')
                    ->where(fn ($query) => $query->where('location', $location))
                    ->ignore($this->printerSetting->id),
            ],
            'enabled' => ['sometimes', 'boolean'],
            'connection_type' => [
                'sometimes',
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

    public function printerSetting(): PrinterSetting
    {
        return $this->printerSetting;
    }
}
