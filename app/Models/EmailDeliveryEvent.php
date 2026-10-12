<?php

namespace App\Models;

use App\Enums\EmailDeliveryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part 4.4 email_delivery_events (R-164, D-087). */
class EmailDeliveryEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => EmailDeliveryStatus::class, 'created_at' => 'immutable_datetime', 'attempt' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (EmailDeliveryEvent $e) {
            $e->recipient_normalized = $e->recipient === null ? null : mb_strtolower(trim($e->recipient));
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }
}
