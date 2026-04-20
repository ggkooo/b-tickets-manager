<?php

namespace App\Http\Controllers;

use App\Models\PrinterSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrinterSettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $location = $request->user()->location;

        $settings = PrinterSetting::query()
            ->forLocation($location)
            ->orderByDesc('enabled')
            ->orderBy('name')
            ->get();

        return response()->json([
            'location' => $location,
            'data' => $settings,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $location = $request->user()->location;

        $validated = $this->validatePayload($request, $location);
        $attributes = $this->buildAttributes($validated);

        $setting = PrinterSetting::query()->create([
            'location' => $location,
            ...$attributes,
        ]);

        return response()->json([
            'message' => 'Impressora cadastrada com sucesso.',
            'data' => $setting,
        ], 201);
    }

    public function update(Request $request, int $printerSetting): JsonResponse
    {
        $location = $request->user()->location;
        $setting = PrinterSetting::query()
            ->forLocation($location)
            ->whereKey($printerSetting)
            ->first();

        if ($setting === null) {
            return response()->json([
                'message' => 'Impressora nao encontrada.',
            ], 404);
        }

        $validated = $this->validatePayload($request, $location, $setting);
        $merged = array_merge(
            $setting->only([
                'name',
                'enabled',
                'connection_type',
                'host',
                'port',
                'share_path',
                'profile',
                'header',
            ]),
            $validated
        );

        $setting->fill($this->buildAttributes($merged));
        $setting->save();

        return response()->json([
            'message' => 'Configuracao da impressora atualizada com sucesso.',
            'data' => $setting,
        ]);
    }

    private function validatePayload(Request $request, string $location, ?PrinterSetting $printerSetting = null): array
    {
        $validated = $request->validate([
            'name' => [
                $printerSetting === null ? 'required' : 'sometimes',
                'string',
                'max:120',
                Rule::unique('printer_settings', 'name')
                    ->where(fn ($query) => $query->where('location', $location))
                    ->ignore($printerSetting?->id),
            ],
            'enabled' => [$printerSetting === null ? 'required' : 'sometimes', 'boolean'],
            'connection_type' => [
                $printerSetting === null ? 'required' : 'sometimes',
                'string',
                'in:' . implode(',', PrinterSetting::allowedConnectionTypes()),
            ],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'share_path' => ['nullable', 'string', 'max:255'],
            'profile' => ['nullable', 'string', 'max:100'],
            'header' => ['nullable', 'string', 'max:255'],
        ]);

        $connectionType = $validated['connection_type'] ?? $printerSetting?->connection_type;
        $host = trim((string) ($validated['host'] ?? $printerSetting?->host ?? ''));
        $sharePath = trim((string) ($validated['share_path'] ?? $printerSetting?->share_path ?? ''));

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

        return $validated;
    }

    private function buildAttributes(array $validated): array
    {
        $connectionType = $validated['connection_type'];

        return [
            'name' => trim((string) $validated['name']),
            'enabled' => (bool) $validated['enabled'],
            'connection_type' => $connectionType,
            'host' => $connectionType === PrinterSetting::CONNECTION_NETWORK
                ? trim((string) ($validated['host'] ?? ''))
                : null,
            'port' => $connectionType === PrinterSetting::CONNECTION_NETWORK
                ? (int) ($validated['port'] ?? 9100)
                : null,
            'share_path' => $connectionType === PrinterSetting::CONNECTION_SHARED_WINDOWS
                ? trim((string) ($validated['share_path'] ?? ''))
                : null,
            'profile' => trim((string) ($validated['profile'] ?? 'simple')),
            'header' => trim((string) ($validated['header'] ?? 'SENHA DE ATENDIMENTO')),
        ];
    }
}
