<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrinterSetting extends Model
{
    public const CONNECTION_NETWORK = 'network';
    public const CONNECTION_SHARED_WINDOWS = 'shared_windows';

    protected $fillable = [
        'location',
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

    /**
     * @return array<int, string>
     */
    public static function allowedConnectionTypes(): array
    {
        return [
            self::CONNECTION_NETWORK,
            self::CONNECTION_SHARED_WINDOWS,
        ];
    }
}
