<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'key',
        'service_type',
        'completed',
        'guiche',
        'called_at',
    ];

    protected $casts = [
        'completed'  => 'boolean',
        'called_at'  => 'datetime',
    ];
}
