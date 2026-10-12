<?php

namespace App\Models;

use App\Enums\TicketKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part 4.3 deleted_tickets — recycle bin (R-194, D-047). */
class DeletedTicket extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['id_number'];

    protected function casts(): array
    {
        return [
            'ticket_type' => TicketKind::class,
            'context' => 'array',
            'is_present' => 'boolean',
            'checked_in_at' => 'immutable_datetime',
            'holder_created_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'unit_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
