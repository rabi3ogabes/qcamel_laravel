<?php

namespace App\Models;

use App\Enums\ScanQueueResult;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part 4.3 scan_queue — offline scanner sync (client UUID = id). */
class ScanQueueItem extends Model
{
    use HasUuids;

    protected $table = 'scan_queue';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'result' => ScanQueueResult::class,
            'scanned_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(TicketHolder::class, 'ticket_holder_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(KioskDevice::class, 'kiosk_device_id');
    }
}
