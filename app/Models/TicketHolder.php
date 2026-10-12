<?php

namespace App\Models;

use App\Enums\TicketKind;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Part 4.3 ticket_holders — one issued ticket. Check-in columns renamed
 * checked_in_* (D-046). id_number hidden unless policy allows (R-203).
 */
class TicketHolder extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $hidden = ['id_number'];

    protected function casts(): array
    {
        return [
            'ticket_type' => TicketKind::class,
            'unit_price' => 'decimal:2',
            'is_present' => 'boolean',
            'qr_failed' => 'boolean',
            'checked_in_at' => 'immutable_datetime',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TicketHolder $h) {
            $h->phone_e164 = PhoneNumber::e164(PhoneNumber::stripPrefix($h->phone), $h->country_code ?: (PhoneNumber::parsePrefix($h->phone) ?? '+974'));
            $h->person_key = PhoneNumber::personKey($h->id_number, $h->phone);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(TicketCheckin::class)->latest('created_at');
    }

    /** Displayed phone "+974 55512345" without double prefixes. */
    public function displayPhone(): string
    {
        $national = PhoneNumber::stripPrefix($this->phone);
        $cc = $this->country_code ?: (PhoneNumber::parsePrefix($this->phone) ?? '+974');

        return trim($cc.' '.$national);
    }

    /** The scannable code (legacy URL-in-code tolerated, F-QR-018). */
    public function code(): string
    {
        $c = (string) $this->qr_code;
        if (str_contains($c, 'http')) {
            $c = preg_replace('/\.(png|jpg)$/i', '', basename(parse_url($c, PHP_URL_PATH) ?: $c)) ?? $c;
        }

        return $c;
    }
}
