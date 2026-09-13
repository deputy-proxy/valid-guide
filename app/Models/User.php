<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\PlatformRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property PlatformRole|null $platform_role
 * @property array<string, bool>|null $notification_preferences
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'platform_role' => PlatformRole::class,
            'notification_preferences' => 'array',
        ];
    }

    /** @return BelongsToMany<Organization, $this> */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')
            ->withPivot('role')->withTimestamps();
    }

    /** @return HasOne<AuditorProfile, $this> */
    public function auditorProfile(): HasOne
    {
        return $this->hasOne(AuditorProfile::class, 'auditor_id');
    }

    /** @return HasMany<DisputeReviewer, $this> */
    public function disputeReviewerAssignments(): HasMany
    {
        return $this->hasMany(DisputeReviewer::class, 'reviewer_id');
    }

    public function isPlatformAdmin(): bool
    {
        return $this->platform_role === PlatformRole::Admin;
    }

    public function canReceiveNotification(NotificationCategory $category): bool
    {
        return ($this->notification_preferences ?? [])[$category->value] ?? true;
    }

    public function setNotificationPreference(NotificationCategory $category, bool $enabled): void
    {
        $preferences = $this->notification_preferences ?? [];
        $preferences[$category->value] = $enabled;
        $this->notification_preferences = $preferences;
        $this->save();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'auditor') {
            return $this->auditorProfile()->exists();
        }

        return $panel->getId() !== 'admin' || $this->isPlatformAdmin() || $this->organizations()->exists();
    }

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
