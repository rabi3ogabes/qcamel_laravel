<?php

namespace App\Models;

use App\Enums\SadadLiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part 4.4 sadad_transactions — cached gateway view (D-067). */
class SadadTransaction extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'commission' => 'decimal:2',
            'refund_charge' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'is_refund' => 'boolean',
            'raw' => 'array',
            'synced_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function statusLabel(): string
    {
        return SadadLiveStatus::label($this->status);
    }

    public function statusColor(): string
    {
        return SadadLiveStatus::color($this->status);
    }

    /** commission + refund charge (F-ADM-TRX-012). */
    public function fees(): string
    {
        return number_format((float) $this->commission + (float) $this->refund_charge, 2, '.', '');
    }
}
