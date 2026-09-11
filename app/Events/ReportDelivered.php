<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Report;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class ReportDelivered implements ShouldDispatchAfterCommit
{
    public function __construct(public Report $report) {}
}
