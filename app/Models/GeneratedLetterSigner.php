<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedLetterSigner extends Model
{
    protected $fillable = [
        'generated_letter_id',
        'user_id',
        'approver_name',
        'approver_role',
        'approver_nip',
        'approver_department',
        'qr_position',
        'qr_row',
        'verification_token',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function generatedLetter(): BelongsTo
    {
        return $this->belongsTo(GeneratedLetter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
