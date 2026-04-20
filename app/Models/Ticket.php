<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'location',
        'service_type',
        'completed',
        'guiche',
        'attended_by_user_id',
        'called_at',
        'completed_at',
        'completion_type',
    ];

    protected $casts = [
        'completed'  => 'boolean',
        'attended_by_user_id' => 'integer',
        'called_at'  => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function attendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by_user_id');
    }
}
