<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Race $race
 * @property CarbonInterface|null $scheduled_start_at
 * @property CarbonInterface|null $actual_start_at
 */
final class Wave extends Model
{
    use HasPublicUlid;

    protected $dateFormat = 'Y-m-d H:i:s.v'; // INV-10: precision de milisegundos

    protected $fillable = ['ulid', 'race_id', 'name', 'scheduled_start_at', 'actual_start_at'];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'actual_start_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Race, $this> */
    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }
}
