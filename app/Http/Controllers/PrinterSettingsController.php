<?php

namespace App\Http\Controllers;

use App\Models\PrinterSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrinterSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $location = $request->user()->location;

        $setting = PrinterSetting::query()
            ->where('location', $location)
            ->first();

        if ($setting === null) {
            return response()->json([
                'location' => $location,
                'enabled' => false,
                'connection_type' => PrinterSetting::CONNECTION_NETWORK,
                'host' => null,
                'port' => 9100,
                'share_path' => null,
                'profile' => 'simple',
                'header' => 'SENHA DE ATENDIMENTO',
            ]);
        }

        return response()->json($setting);
    }

    public function store(Request $request): JsonResponse
    {
        $location = $request->user()->location;

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'connection_type' => ['required', 'string', 'in:' . implode(',', PrinterSetting::allowedConnectionTypes())],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'share_path' => ['nullable', 'string', 'max:255'],
            'profile' => ['nullable', 'string', 'max:100'],
            'header' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['connection_type'] === PrinterSetting::CONNECTION_NETWORK) {
            if (empty($validated['host'])) {
                return response()->json([
                    'message' => 'Host e obrigatorio para impressora de rede.',
                ], 422);
            }
        }

        if ($validated['connection_type'] === PrinterSetting::CONNECTION_SHARED_WINDOWS) {
            if (empty($validated['share_path'])) {
                return response()->json([
                    'message' => 'share_path e obrigatorio para impressora compartilhada no Windows.',
                ], 422);
            }
        }

        $setting = PrinterSetting::query()->updateOrCreate(
            ['location' => $location],
            [
                'enabled' => $validated['enabled'],
                'connection_type' => $validated['connection_type'],
                'host' => $validated['connection_type'] === PrinterSetting::CONNECTION_NETWORK
                    ? trim((string) ($validated['host'] ?? ''))
                    : null,
                'port' => $validated['connection_type'] === PrinterSetting::CONNECTION_NETWORK
                    ? (int) ($validated['port'] ?? 9100)
                    : null,
                'share_path' => $validated['connection_type'] === PrinterSetting::CONNECTION_SHARED_WINDOWS
                    ? trim((string) ($validated['share_path'] ?? ''))
                    : null,
                'profile' => trim((string) ($validated['profile'] ?? 'simple')),
                'header' => trim((string) ($validated['header'] ?? 'SENHA DE ATENDIMENTO')),
            ]
        );

        return response()->json([
            'message' => 'Configuracao da impressora salva com sucesso.',
            'data' => $setting,
        ]);
    }
}
