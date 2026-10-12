<?php

namespace App\Models;

use App\Enums\DeviceKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Part 4.1 kiosk_devices — passcode kiosk sessions (R-141, U-AUTH-02).
 * The browser holds only an opaque device token (HttpOnly cookie);
 * the passcode itself is never stored client-side (D-008).
 */
class KioskDevice extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'kind' => DeviceKind::class,
            'last_seen_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function posUser(): BelongsTo
    {
        return $this->belongsTo(PosUser::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function label(): string
    {
        return $this->name ?: __('auth.kiosk_label');
    }
}
