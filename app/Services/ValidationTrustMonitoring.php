<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ValidationStatus;
use App\Enums\ValidationTrustMonitorCadence;
use App\Enums\ValidationTrustMonitorEventType;
use App\Enums\ValidationTrustMonitorStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\Validation;
use App\Models\ValidationTrustMonitor;
use App\Models\ValidationTrustMonitorEvent;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ValidationTrustMonitoring
{
    public function configure(
        Validation $validation,
        User $actor,
        ValidationTrustMonitorCadence $cadence = ValidationTrustMonitorCadence::Daily,
    ): ValidationTrustMonitor {
        $organization = $this->organizationFor($validation);
        Gate::forUser($actor)->authorize('create', [ValidationTrustMonitor::class, $organization]);

        if (in_array($validation->status, [ValidationStatus::Revoked, ValidationStatus::Superseded], true)) {
            throw new ValidationTrustMonitoringException('Terminal validations cannot be monitored.');
        }

        return DB::transaction(function () use ($validation, $actor, $cadence, $organization): ValidationTrustMonitor {
            $monitor = ValidationTrustMonitor::query()
                ->where('validation_id', $validation->getKey())
                ->lockForUpdate()
                ->first();

            if ($monitor === null) {
                $monitor = ValidationTrustMonitor::query()->create([
                    'organization_id' => $organization->getKey(),
                    'validation_id' => $validation->getKey(),
                    'cadence' => $cadence,
                    'status' => ValidationTrustMonitorStatus::Active,
                    'next_check_at' => now(),
                    'created_by' => $actor->getKey(),
                ]);

                AuditLogger::record(
                    event: 'validation_trust_monitor.configured',
                    auditable: $monitor,
                    after: ['cadence' => $cadence->value, 'status' => ValidationTrustMonitorStatus::Active->value],
                    actor: $actor,
                );

                return $monitor;
            }

            if ((int) $monitor->organization_id !== (int) $organization->getKey()) {
                throw new AuthorizationException('The validation trust monitor belongs to another organization.');
            }

            $before = ['cadence' => $monitor->cadence->value, 'status' => $monitor->status->value];
            $monitor->forceFill([
                'cadence' => $cadence,
                'status' => ValidationTrustMonitorStatus::Active,
                'next_check_at' => now(),
                'failure_fingerprint' => null,
                'failure_reason' => null,
                'cancelled_at' => null,
                'updated_by' => $actor->getKey(),
            ])->save();

            $event = $before['status'] === ValidationTrustMonitorStatus::Cancelled->value
                ? 'validation_trust_monitor.resumed'
                : ($before['cadence'] !== $cadence->value ? 'validation_trust_monitor.cadence_changed' : null);

            if ($event !== null) {
                AuditLogger::record(
                    event: $event,
                    auditable: $monitor,
                    before: $before,
                    after: ['cadence' => $cadence->value, 'status' => ValidationTrustMonitorStatus::Active->value],
                    actor: $actor,
                );
            }

            return $monitor;
        });
    }

    public function cancel(ValidationTrustMonitor $monitor, User $actor, string $reason): ValidationTrustMonitor
    {
        Gate::forUser($actor)->authorize('cancel', $monitor);

        if (trim($reason) === '') {
            throw new ValidationTrustMonitoringException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($monitor, $actor, $reason): ValidationTrustMonitor {
            $monitor = ValidationTrustMonitor::query()->whereKey($monitor->getKey())->lockForUpdate()->firstOrFail();

            if ($monitor->status === ValidationTrustMonitorStatus::Cancelled) {
                return $monitor;
            }

            $before = ['status' => $monitor->status->value];
            $monitor->forceFill([
                'status' => ValidationTrustMonitorStatus::Cancelled,
                'cancelled_at' => now(),
                'updated_by' => $actor->getKey(),
            ])->save();

            $fingerprint = hash('sha256', $monitor->getKey().'|cancelled|'.$reason);
            if ($this->recordEvent($monitor, ValidationTrustMonitorEventType::MonitoringCancelled, $fingerprint, ['reason' => $reason]) !== null) {
                AuditLogger::record(
                    event: 'validation_trust_monitor.cancelled',
                    auditable: $monitor,
                    before: $before,
                    after: ['status' => ValidationTrustMonitorStatus::Cancelled->value, 'reason' => $reason],
                    actor: $actor,
                );
            }

            return $monitor;
        });
    }

    /** @return array{processed:int,changed:int,failed:int,recovered:int,skipped:int} */
    public function runDue(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $stats = ['processed' => 0, 'changed' => 0, 'failed' => 0, 'recovered' => 0, 'skipped' => 0];

        foreach (ValidationTrustMonitor::query()
            ->where('status', '!=', ValidationTrustMonitorStatus::Cancelled->value)
            ->where('next_check_at', '<=', $now)
            ->orderBy('id')
            ->lazyById(50) as $monitor) {
            $result = $this->runOne($monitor, $now);
            $stats['processed']++;
            $stats['changed'] += $result['changed'] ? 1 : 0;
            $stats['failed'] += $result['failed'] ? 1 : 0;
            $stats['recovered'] += $result['recovered'] ? 1 : 0;
            $stats['skipped'] += $result['skipped'] ? 1 : 0;
        }

        return $stats;
    }

    /** @return array{changed:bool,failed:bool,recovered:bool,skipped:bool} */
    public function runOne(ValidationTrustMonitor $monitor, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return DB::transaction(function () use ($monitor, $now): array {
            $monitor = ValidationTrustMonitor::query()->whereKey($monitor->getKey())->lockForUpdate()->firstOrFail();

            if ($monitor->status === ValidationTrustMonitorStatus::Cancelled || $monitor->next_check_at->isAfter($now)) {
                return ['changed' => false, 'failed' => false, 'recovered' => false, 'skipped' => true];
            }

            $previousStatus = $monitor->status;
            $previousFingerprint = $monitor->observed_fingerprint;
            $recoverable = in_array($previousStatus, [
                ValidationTrustMonitorStatus::Failed,
                ValidationTrustMonitorStatus::Stale,
                ValidationTrustMonitorStatus::Invalid,
            ], true);

            if ($monitor->last_checked_at !== null
                && $monitor->last_checked_at->addMinutes($monitor->cadence->intervalMinutes() * 2)->isBefore($now)
                && $previousStatus === ValidationTrustMonitorStatus::Active) {
                $monitor->forceFill(['status' => ValidationTrustMonitorStatus::Stale, 'updated_at' => $now])->save();
                AuditLogger::record(
                    event: 'validation_trust_monitor.stale',
                    auditable: $monitor,
                    before: ['status' => ValidationTrustMonitorStatus::Active->value],
                    after: ['status' => ValidationTrustMonitorStatus::Stale->value],
                );
                $recoverable = true;
            }

            try {
                $state = $this->observedState($monitor->validation()->firstOrFail(), (int) $monitor->organization_id);
                $fingerprint = $this->fingerprint($state);
                $changed = $previousFingerprint !== null && $previousFingerprint !== $fingerprint;

                $monitor->forceFill([
                    'status' => ValidationTrustMonitorStatus::Active,
                    'last_checked_at' => $now,
                    'next_check_at' => $now->addMinutes($monitor->cadence->intervalMinutes()),
                    'observed_fingerprint' => $fingerprint,
                    'failure_fingerprint' => null,
                    'failure_reason' => null,
                    'updated_at' => $now,
                ])->save();

                if ($previousFingerprint === null) {
                    $this->recordEvent($monitor, ValidationTrustMonitorEventType::BaselineRecorded, $fingerprint, $state);
                } elseif ($changed) {
                    $this->recordEvent($monitor, ValidationTrustMonitorEventType::TrustStateChanged, $fingerprint, [
                        'previous_fingerprint' => $previousFingerprint,
                        'state' => $state,
                    ]);
                    AuditLogger::record(
                        event: 'validation_trust_monitor.trust_state_changed',
                        auditable: $monitor,
                        before: ['fingerprint' => $previousFingerprint],
                        after: ['fingerprint' => $fingerprint, 'state' => $state],
                    );
                }

                if ($recoverable) {
                    $recoveryFingerprint = hash('sha256', $fingerprint.'|recovered');
                    $this->recordEvent($monitor, ValidationTrustMonitorEventType::MonitoringRecovered, $recoveryFingerprint, ['fingerprint' => $fingerprint]);
                    AuditLogger::record(
                        event: 'validation_trust_monitor.recovered',
                        auditable: $monitor,
                        before: ['status' => $previousStatus->value],
                        after: ['status' => ValidationTrustMonitorStatus::Active->value, 'fingerprint' => $fingerprint],
                    );
                }

                return ['changed' => $changed, 'failed' => false, 'recovered' => $recoverable, 'skipped' => false];
            } catch (ValidationTrustMonitoringException $exception) {
                $this->markInvalid($monitor, $exception->getMessage(), $now);

                return ['changed' => false, 'failed' => true, 'recovered' => false, 'skipped' => false];
            } catch (Throwable $exception) {
                $this->markFailed($monitor, $exception, $now);

                return ['changed' => false, 'failed' => true, 'recovered' => false, 'skipped' => false];
            }
        });
    }

    /** @return array<string,mixed> */
    private function observedState(Validation $validation, int $expectedOrganizationId): array
    {
        $validation->loadMissing(['productRelease.product.organization', 'publicVerificationRecord', 'evaluation.report.currentVersion']);
        $release = $validation->productRelease;
        $organizationId = $release?->product?->organization_id;

        if ($release === null || $release->product === null || $organizationId === null || (int) $organizationId !== $expectedOrganizationId) {
            throw new ValidationTrustMonitoringException('Validation provenance is incomplete or crosses the monitor tenant boundary.');
        }

        $publicRecord = $validation->publicVerificationRecord;
        if ($publicRecord === null || $publicRecord->published_at === null || $publicRecord->snapshot === null) {
            throw new ValidationTrustMonitoringException('Published public verification snapshot is missing or incomplete.');
        }

        $report = $validation->evaluation?->report;
        $reportVersion = $report?->currentVersion;

        return [
            'validation' => [
                'id' => $validation->getKey(),
                'status' => $validation->status->value,
                'status_reason' => $validation->status_reason,
                'issued_at' => $validation->issued_at?->toIso8601String(),
                'suspended_at' => $validation->suspended_at?->toIso8601String(),
                'revoked_at' => $validation->revoked_at?->toIso8601String(),
                'superseded_at' => $validation->superseded_at?->toIso8601String(),
                'updated_at' => $validation->updated_at?->toIso8601String(),
            ],
            'product_release' => [
                'id' => $release->getKey(),
                'product_id' => $release->product_id,
                'edition' => $release->edition,
                'version' => $release->version,
                'published_at' => $release->published_at?->toIso8601String(),
                'release_identifier' => $release->release_identifier,
                'product_url_snapshot' => $release->product_url_snapshot,
                'title_snapshot' => $release->title_snapshot,
                'quantitative_metadata' => $release->quantitative_metadata,
                'material_change_notes' => $release->material_change_notes,
                'status' => $release->status->value,
                'updated_at' => $release->updated_at?->toIso8601String(),
            ],
            'report' => $report === null ? null : [
                'id' => $report->getKey(),
                'current_version_id' => $report->current_version_id,
                'public_visible_at' => $report->public_visible_at?->toIso8601String(),
                'delivered_at' => $report->delivered_at?->toIso8601String(),
                'updated_at' => $report->updated_at?->toIso8601String(),
                'current_version' => $reportVersion === null ? null : [
                    'id' => $reportVersion->getKey(),
                    'version_number' => $reportVersion->version_number,
                    'published_at' => $reportVersion->published_at?->toIso8601String(),
                    'change_reason' => $reportVersion->change_reason,
                ],
            ],
            'public_verification' => [
                'id' => $publicRecord->getKey(),
                'public_slug' => $publicRecord->public_slug,
                'directory_visible' => $publicRecord->directory_visible,
                'full_report_visible' => $publicRecord->full_report_visible,
                'published_at' => $publicRecord->published_at?->toIso8601String(),
                'snapshot' => $publicRecord->snapshot,
                'updated_at' => $publicRecord->updated_at?->toIso8601String(),
            ],
        ];
    }

    /** @param array<string,mixed> $state */
    private function fingerprint(array $state): string
    {
        return hash('sha256', json_encode($this->canonicalize($state), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
            }

            ksort($value);
            foreach ($value as $key => $item) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }

    private function organizationFor(Validation $validation): Organization
    {
        $organization = $validation->productRelease?->product?->organization;

        if ($organization instanceof Organization) {
            return $organization;
        }

        throw new ValidationTrustMonitoringException('Validation does not belong to a creator organization.');
    }

    /** @param array<string,mixed> $payload */
    private function recordEvent(
        ValidationTrustMonitor $monitor,
        ValidationTrustMonitorEventType $type,
        string $fingerprint,
        array $payload,
    ): ?ValidationTrustMonitorEvent {
        $existing = ValidationTrustMonitorEvent::query()
            ->where('validation_trust_monitor_id', $monitor->getKey())
            ->where('type', $type->value)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($existing !== null) {
            return null;
        }

        return ValidationTrustMonitorEvent::query()->create([
            'validation_trust_monitor_id' => $monitor->getKey(),
            'organization_id' => $monitor->organization_id,
            'type' => $type,
            'fingerprint' => $fingerprint,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }

    private function markInvalid(ValidationTrustMonitor $monitor, string $reason, CarbonImmutable $now): void
    {
        $fingerprint = hash('sha256', 'invalid|'.$reason);
        $monitor->forceFill([
            'status' => ValidationTrustMonitorStatus::Invalid,
            'last_checked_at' => $now,
            'next_check_at' => $now->addMinutes($monitor->cadence->intervalMinutes()),
            'failure_fingerprint' => $fingerprint,
            'failure_reason' => $reason,
            'updated_at' => $now,
        ])->save();

        if ($this->recordEvent($monitor, ValidationTrustMonitorEventType::MonitoringFailed, $fingerprint, [
            'reason' => $reason,
            'status' => ValidationTrustMonitorStatus::Invalid->value,
        ]) !== null) {
            AuditLogger::record(
                event: 'validation_trust_monitor.invalid',
                auditable: $monitor,
                after: ['status' => ValidationTrustMonitorStatus::Invalid->value, 'reason' => $reason],
            );
        }
    }

    private function markFailed(ValidationTrustMonitor $monitor, Throwable $exception, CarbonImmutable $now): void
    {
        $reason = $exception::class.': '.$exception->getMessage();
        $fingerprint = hash('sha256', $reason);
        $monitor->forceFill([
            'status' => ValidationTrustMonitorStatus::Failed,
            'last_checked_at' => $now,
            'next_check_at' => $now->addMinutes($monitor->cadence->intervalMinutes()),
            'failure_fingerprint' => $fingerprint,
            'failure_reason' => $reason,
            'updated_at' => $now,
        ])->save();

        if ($this->recordEvent($monitor, ValidationTrustMonitorEventType::MonitoringFailed, $fingerprint, [
            'reason' => $reason,
            'status' => ValidationTrustMonitorStatus::Failed->value,
        ]) !== null) {
            AuditLogger::record(
                event: 'validation_trust_monitor.failed',
                auditable: $monitor,
                after: ['status' => ValidationTrustMonitorStatus::Failed->value, 'reason' => $reason],
            );
        }
    }
}
