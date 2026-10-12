<?php

namespace App\Models;

use App\Support\QatarClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Part 4.2 events. Business dates/cut-offs in Asia/Qatar (R-003…R-005).
 */
class Event extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'title', 'description', 'event_date', 'end_date', 'start_time', 'end_time', 'location',
        'image_url', 'image_path', 'video_url', 'is_active', 'is_archived', 'archived_at', 'display_order',
        'sales_cutoff_time', 'checkin_cutoff_time', 'max_admission_per_order', 'max_parking_per_order',
        'per_person_cap', 'min_online_amount', 'hold_minutes',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'immutable_datetime',
            'end_date' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
            'display_order' => 'integer',
            'min_online_amount' => 'decimal:2',
        ];
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('type');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function paymentErrors(): HasMany
    {
        return $this->hasMany(PaymentError::class);
    }

    // ---- Scopes (Part 4.2) ----------------------------------------------------

    public function scopePublic(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('is_archived', false);
    }

    public function scopeCurrentSeason(Builder $q): Builder
    {
        return $q->where('is_archived', false);
    }

    public function scopeArchived(Builder $q): Builder
    {
        return $q->where('is_archived', true);
    }

    /** Events whose Qatar date range [event_date, end_date] contains $date (R-005). */
    public function scopeOnQatarDate(Builder $q, string $date): Builder
    {
        [$start, $end] = QatarClock::dayRange($date);

        return $q->where('event_date', '<', $end)
            ->where(fn (Builder $w) => $w->where(fn ($x) => $x->whereNull('end_date')->where('event_date', '>=', $start))
                ->orWhere('end_date', '>=', $start));
    }

    // ---- Business time rules ---------------------------------------------------

    public function firstDay(): string
    {
        return QatarClock::dateOf($this->event_date);
    }

    /** R-004: last day = Qatar date of end_date ?? event_date. */
    public function lastDay(): string
    {
        return QatarClock::dateOf($this->end_date ?? $this->event_date);
    }

    public function cutoffAt(string $kind = 'sales'): CarbonImmutable
    {
        $time = $kind === 'checkin' ? ($this->checkin_cutoff_time ?: config('qcamelmc.cutoff_time')) : ($this->sales_cutoff_time ?: config('qcamelmc.cutoff_time'));

        return QatarClock::at($this->lastDay(), substr((string) $time, 0, 8));
    }

    /** R-003/R-004 expired after the cut-off on the last day. */
    public function isExpired(string $kind = 'sales', ?CarbonImmutable $now = null): bool
    {
        $now ??= QatarClock::nowUtc();

        return $now->greaterThanOrEqualTo($this->cutoffAt($kind));
    }

    public function canPurchaseTickets(?CarbonImmutable $now = null): bool
    {
        return ! $this->isExpired('sales', $now);
    }

    /** R-005: Qatar today ∈ [first day, last day]. */
    public function isToday(?string $today = null): bool
    {
        $today ??= QatarClock::today();

        return $today >= $this->firstDay() && $today <= $this->lastDay();
    }

    public function isPublicSellable(): bool
    {
        return $this->is_active && ! $this->is_archived && ! $this->trashed() && $this->canPurchaseTickets();
    }

    public function coverUrl(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        return $this->image_url ?: null;
    }

    // ---- Per-event limits (Tier A10) with config fallback ----------------------

    public function limit(string $key): int|string
    {
        return match ($key) {
            'admission' => $this->max_admission_per_order ?? config('qcamelmc.orders.max_admission_per_web_order'),
            'parking' => $this->max_parking_per_order ?? config('qcamelmc.orders.max_parking_per_web_order'),
            'person' => $this->per_person_cap ?? config('qcamelmc.orders.per_person_cap'),
            'min_online' => $this->min_online_amount ?? config('qcamelmc.orders.min_online_amount'),
            'hold' => $this->hold_minutes ?? config('qcamelmc.orders.hold_minutes'),
        };
    }
}
