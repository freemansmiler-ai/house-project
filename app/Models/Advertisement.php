<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Advertisement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'property_id',
        'title',
        'banner_image_path',
        'target_url',
        'placement',
        'price_paid',
        'status',
        'starts_at',
        'ends_at',
        'click_count',
        'impression_count'
    ];

    protected $casts = [
        'price_paid' => 'float',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'click_count' => 'integer',
        'impression_count' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
