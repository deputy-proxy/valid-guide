<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClarificationRequestStatus;
use App\Enums\ClarificationRequestType;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClarificationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id', 'organization_id', 'submitted_by', 'type', 'message', 'response',
        'status', 'submitted_at', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClarificationRequestType::class,
            'status' => ClarificationRequestStatus::class,
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            $status = $request->getOriginal('status');
            if ($status === ClarificationRequestStatus::Closed->value) {
                throw new DomainStateTransitionException('Closed clarification requests are immutable.');
            }
            if ($status === ClarificationRequestStatus::Answered->value && array_diff(array_keys($request->getDirty()), ['status', 'resolved_at', 'resolved_by'])) {
                throw new DomainStateTransitionException('Answered clarification content is immutable.');
            }
        });

        static::deleting(function (self $request): void {
            if ($request->submitted_at !== null) {
                throw new DomainStateTransitionException('Submitted clarification requests cannot be deleted.');
            }
        });
    }

    public function evaluation(): BelongsTo { return $this->belongsTo(Evaluation::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function submittedBy(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
}
