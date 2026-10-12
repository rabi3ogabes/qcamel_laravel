<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part 4.3 refunds (Tier A5). Recorded manually after acting in Sadad. */
class Refund extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount' => 'decimal:2',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
