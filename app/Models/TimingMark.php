<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property CarbonInterface $captured_at */
final class TimingMark extends Model
{
    use HasPublicUlid;

    protected $fillable = ['ulid', 'event_id', 'entry_id', 'client_mark_id', 'captured_at', 'device_time_ms', 'source'];

    public const UPDATED_AT = null; // append-only (INV-01)

    protected function casts(): array
    {
        return ['captured_at' => 'datetime'];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** @return HasMany<TimingMarkCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(TimingMarkCorrection::class);
    }
}
