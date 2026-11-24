<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Carbon\CarbonInterface;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonInterface $birthdate
 */
final class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected $table = 'persons'; // el plural del modelo de datos es persons, no people

    protected $fillable = ['ulid', 'document', 'first_name', 'last_name', 'birthdate', 'gender'];

    protected function casts(): array
    {
        return ['birthdate' => 'date'];
    }

    /** @return HasOne<Account, $this> */
    public function account(): HasOne
    {
        return $this->hasOne(Account::class);
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user');
    }

    public function isMinor(\DateTimeInterface $at): bool
    {
        return $this->birthdate->diff($at)->y < 18;
    }

    /** Nombre público según INV-06: los menores salen con iniciales. */
    public function publicName(): string
    {
        if ($this->isMinor(now())) {
            return strtoupper(substr($this->first_name, 0, 1)).'. '.ucfirst($this->last_name);
        }

        return trim($this->first_name.' '.$this->last_name);
    }
}
