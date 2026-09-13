<?php

declare(strict_types=1);

namespace App\Enums;

enum ValidationTrustMonitorEventType: string
{
    case BaselineRecorded = 'baseline_recorded';
    case TrustStateChanged = 'trust_state_changed';
    case MonitoringFailed = 'monitoring_failed';
    case MonitoringRecovered = 'monitoring_recovered';
    case MonitoringCancelled = 'monitoring_cancelled';
}
