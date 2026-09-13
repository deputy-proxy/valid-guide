<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'website', 'contact_details', 'status',
    ];

    protected function casts(): array
    {
        return ['contact_details' => 'array'];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<EvaluationRequest, $this> */
    public function evaluationRequests(): HasMany
    {
        return $this->hasMany(EvaluationRequest::class);
    }

    /** @return HasMany<ValidationTrustMonitor, $this> */
    public function validationTrustMonitors(): HasMany
    {
        return $this->hasMany(ValidationTrustMonitor::class);
    }

    public function hasMemberWithRole(User $user, OrganizationRole $role): bool
    {
        return $this->users()->whereKey($user->getKey())
            ->wherePivot('role', $role->value)->exists();
    }
}
