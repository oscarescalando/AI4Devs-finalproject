<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Result extends Model
{
    use HasPublicUlid;

    protected $fillable = ['ulid', 'entry_id', 'net_ms', 'rank_general', 'rank_gender', 'rank_category', 'points', 'status'];

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
