<?php

namespace App\Models;

use App\Enums\TicketKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part 4.3 order_items — frozen price snapshot (D-033, D-070). */
class OrderItem extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => TicketKind::class,
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }
}
