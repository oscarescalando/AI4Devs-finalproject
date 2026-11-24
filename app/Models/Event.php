<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Event extends Model
{
    use HasPublicUlid;

    protected $fillable = ['ulid', 'name', 'slug', 'date', 'timezone', 'status'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /** @return HasMany<Race, $this> */
    public function races(): HasMany
    {
        return $this->hasMany(Race::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /** @return HasMany<TimingMark, $this> */
    public function timingMarks(): HasMany
    {
        return $this->hasMany(TimingMark::class);
    }
}
