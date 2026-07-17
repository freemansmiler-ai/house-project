<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agency_name',
        'license_number',
        'experience_years',
        'is_verified',
        'rating'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'experience_years' => 'integer',
        'rating' => 'float'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
