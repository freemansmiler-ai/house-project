<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Landlord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'tax_id',
        'is_verified',
        'total_properties'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'total_properties' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
