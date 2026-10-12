<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Part 4.4 payment_events — append-only payment audit (R-116). */
class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'checksum_valid' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('payment_events is append-only'));
        static::deleting(fn () => throw new LogicException('payment_events is append-only'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }
}
