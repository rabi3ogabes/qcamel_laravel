<?php

namespace App\Models;

use App\Enums\PaymentErrorSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part 4.4 payment_errors (server-side writes only, D-024/D-032). */
class PaymentError extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'error_source' => PaymentErrorSource::class,
            'amount' => 'decimal:2',
            'raw' => 'array',
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
