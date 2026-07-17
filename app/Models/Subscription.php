<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_name',
        'plan_price',
        'property_limit',
        'featured_limit',
        'starts_at',
        'ends_at',
        'status'
    ];

    protected $casts = [
        'plan_price' => 'float',
        'property_limit' => 'integer',
        'featured_limit' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
