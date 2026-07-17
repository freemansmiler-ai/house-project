<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role_requested',
        'document_type',
        'document_number',
        'document_file_path',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'business_license_path',
        'national_id_path',
        'selfie_path',
        'business_address'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
