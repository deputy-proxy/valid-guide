<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Dispute;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class DisputeSubmitted implements ShouldDispatchAfterCommit
{
    public function __construct(public Dispute $dispute)
    {
    }
}
