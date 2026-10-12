<?php

namespace App\Models;

use App\Enums\TicketKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Part 4.2 ticket_types (was `tickets`). available_quantity = total capacity;
 * sold_quantity = seats held by pending + confirmed orders (R-045).
 * sold_quantity may only change through StockLedger (guard flag).
 */
class TicketType extends Model
{
    use HasFactory, HasUuids;

    /** Set by StockLedger while it writes sold_quantity. */
    public static bool $stockGuard = false;

    protected $fillable = ['event_id', 'type', 'price', 'available_quantity', 'sold_quantity', 'description'];

    protected function casts(): array
    {
        return [
            'type' => TicketKind::class,
            'price' => 'decimal:2',
            'available_quantity' => 'integer',
            'sold_quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (TicketType $t) {
            if ($t->isDirty('sold_quantity') && ! static::$stockGuard) {
                throw new LogicException('sold_quantity may only be changed by StockLedger');
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function holders(): HasMany
    {
        return $this->hasMany(TicketHolder::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getCapacityAttribute(): int
    {
        return (int) $this->available_quantity;
    }

    /** remaining = capacity − held (R-045, D-049 single definition). */
    public function getRemainingAttribute(): int
    {
        return (int) $this->available_quantity - (int) $this->sold_quantity;
    }

    public function soldPercent(): int
    {
        return $this->available_quantity <= 0 ? 100 : (int) round($this->sold_quantity / $this->available_quantity * 100);
    }

    public function description(): string
    {
        return trim((string) $this->description) !== '' ? (string) $this->description : $this->type->defaultDescription();
    }
}
