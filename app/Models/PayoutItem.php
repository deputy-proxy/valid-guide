<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutItem extends Model
{
    use HasFactory;

    protected $fillable = ['payout_id', 'auditor_compensation_id', 'amount_minor'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function compensation(): BelongsTo
    {
        return $this->belongsTo(AuditorCompensation::class, 'auditor_compensation_id');
    }
}
