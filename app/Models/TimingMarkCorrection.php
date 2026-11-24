<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TimingMarkCorrection extends Model
{
    use HasPublicUlid;

    protected $fillable = ['ulid', 'timing_mark_id', 'operation', 'reason', 'account_id', 'before_after'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['before_after' => 'array'];
    }

    /** @return BelongsTo<TimingMark, $this> */
    public function timingMark(): BelongsTo
    {
        return $this->belongsTo(TimingMark::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
