<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrinterSetting extends Model
{
    public const CONNECTION_NETWORK = 'network';
    public const CONNECTION_SHARED_WINDOWS = 'shared_windows';

    protected $fillable = [
        'location',
        'name',
        'enabled',
        'connection_type',
        'host',
        'port',
        'share_path',
        'profile',
        'header',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'port' => 'integer',
    ];

    public function scopeForLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    public static function allowedConnectionTypes(): array
    {
        return [
            self::CONNECTION_NETWORK,
            self::CONNECTION_SHARED_WINDOWS,
        ];
    }

    public static function attributesFromValidated(array $validated): array
    {
        $connectionType = $validated['connection_type'];

        return [
            'name' => trim((string) $validated['name']),
            'enabled' => (bool) $validated['enabled'],
            'connection_type' => $connectionType,
            'host' => $connectionType === self::CONNECTION_NETWORK
                ? trim((string) ($validated['host'] ?? ''))
                : null,
            'port' => $connectionType === self::CONNECTION_NETWORK
                ? (int) ($validated['port'] ?? 9100)
                : null,
            'share_path' => $connectionType === self::CONNECTION_SHARED_WINDOWS
                ? trim((string) ($validated['share_path'] ?? ''))
                : null,
            'profile' => trim((string) ($validated['profile'] ?? 'simple')),
            'header' => trim((string) ($validated['header'] ?? 'SENHA DE ATENDIMENTO')),
        ];
    }
}
