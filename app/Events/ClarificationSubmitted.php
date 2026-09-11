<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ClarificationRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class ClarificationSubmitted implements ShouldDispatchAfterCommit
{
    public function __construct(public ClarificationRequest $request) {}
}
