<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
    use HasFactory;

    public const TYPE_UPLOAD = 'upload';
    public const TYPE_LINK = 'link';

    protected $fillable = [
        'location',
        'type',
        'filename',
        'url',
    ];

    public function scopeForLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    /**
     * The URL the TV screen should actually play: the public storage URL
     * for an uploaded file, or the link as-is for an external one.
     */
    public function getPlaybackUrlAttribute(): ?string
    {
        if ($this->type === self::TYPE_UPLOAD && $this->filename) {
            return Storage::disk('public')->url('videos/' . $this->filename);
        }

        return $this->url;
    }
}
