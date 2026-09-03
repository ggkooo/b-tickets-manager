<?php

namespace App\Models;

use App\Events\TicketsUpdated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Every ticket mutation — created, called, recalled, completed,
        // canceled — goes through save(), so this single hook covers all of
        // them without the controller needing to know about broadcasting.
        static::saved(fn (Ticket $ticket) => TicketsUpdated::dispatch($ticket->location));
    }

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
