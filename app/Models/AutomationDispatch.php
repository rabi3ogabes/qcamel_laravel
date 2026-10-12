<?php

namespace App\Models;

use App\Enums\AutomationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part 4.4 automation_dispatches — every n8n / WhatsApp send. */
class AutomationDispatch extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => AutomationStatus::class, 'created_at' => 'immutable_datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }
}
