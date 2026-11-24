<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property Event $event
 * @property Category $category
 * @property Race $race
 * @property Wave $wave
 */
final class Entry extends Model
{
    use HasPublicUlid;

    protected $fillable = ['ulid', 'event_id', 'category_id', 'race_id', 'wave_id', 'person_id', 'team_id', 'bib', 'status'];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Race, $this> */
    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    /** @return BelongsTo<Wave, $this> */
    public function wave(): BelongsTo
    {
        return $this->belongsTo(Wave::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<EntryAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(EntryAdjustment::class);
    }

    /** @return HasMany<TimingMark, $this> */
    public function timingMarks(): HasMany
    {
        return $this->hasMany(TimingMark::class);
    }

    /** @return HasOne<Result, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForBib(Builder $query, int $eventId, int $bib): Builder
    {
        return $query->where('event_id', $eventId)->where('bib', $bib);
    }
}
