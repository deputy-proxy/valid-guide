<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'website', 'contact_details', 'status',
    ];

    protected function casts(): array
    {
        return ['contact_details' => 'array'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->withPivot('role')->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function evaluationRequests(): HasMany
    {
        return $this->hasMany(EvaluationRequest::class);
    }

    public function hasMemberWithRole(User $user, OrganizationRole $role): bool
    {
        return $this->users()->whereKey($user->getKey())
            ->wherePivot('role', $role->value)->exists();
    }
}
