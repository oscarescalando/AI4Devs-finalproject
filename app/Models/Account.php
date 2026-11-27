<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Database\Factories\AccountFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Cuenta de acceso (persona ≠ cuenta, ADR-015). Autentica por sesión web
 * (RF-68) y porta los roles/permisos de spatie con teams = evento (RF-69).
 */
final class Account extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;

    use Authorizable;
    /** @use HasFactory<AccountFactory> */
    use HasApiTokens, HasFactory;
    use HasPublicUlid;
    use HasRoles;

    protected $fillable = ['ulid', 'person_id', 'email', 'password', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
