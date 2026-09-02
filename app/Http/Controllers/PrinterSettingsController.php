<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrinterSettingRequest;
use App\Http\Requests\UpdatePrinterSettingRequest;
use App\Models\PrinterSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(StorePrinterSettingRequest $request): JsonResponse
    {
        $attributes = PrinterSetting::attributesFromValidated($request->validated());

        $setting = PrinterSetting::query()->create([
            'location' => $request->locationForUser(),
            ...$attributes,
        ]);

        return response()->json([
            'message' => 'Impressora cadastrada com sucesso.',
            'data' => $setting,
        ], 201);
    }

    public function update(UpdatePrinterSettingRequest $request): JsonResponse
    {
        $setting = $request->printerSetting();

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
            $request->validated()
        );

        $setting->fill(PrinterSetting::attributesFromValidated($merged));
        $setting->save();

        return response()->json([
            'message' => 'Configuracao da impressora atualizada com sucesso.',
            'data' => $setting,
        ]);
    }
}
