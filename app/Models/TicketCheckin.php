<?php

namespace App\Models;

use App\Enums\CheckinAction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part 4.3 ticket_checkins — admission/reset/refusal history. */
class TicketCheckin extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['action' => CheckinAction::class, 'created_at' => 'immutable_datetime'];
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(TicketHolder::class, 'ticket_holder_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
