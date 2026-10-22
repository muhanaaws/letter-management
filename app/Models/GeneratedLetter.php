<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneratedLetter extends Model
{
    protected $fillable = [
        'submission_id',
        'letter_number',
        'file_path',
        'qr_token',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** Signers for each generated letter, each with a unique verification token. */
    public function signers(): HasMany
    {
        return $this->hasMany(GeneratedLetterSigner::class);
    }
}
