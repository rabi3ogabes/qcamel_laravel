<?php

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentNote;
use App\Enums\PaymentStatus;
use App\Enums\TicketKind;
use App\Support\QatarClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Part 4.3 orders. Status changes only through Domain actions
 * (CreateOrderAction, ConfirmPaymentAction, FailPaymentAction, …).
 */
class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'source' => OrderSource::class,
            'ticket_type' => TicketKind::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'total_amount' => 'decimal:2',
            'quantity' => 'integer',
            'payment_expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'n8n_responded_at' => 'immutable_datetime',
            'invoice_email_sent_at' => 'immutable_datetime',
            'invoice_downloaded_at' => 'immutable_datetime',
            'last_payment_check_at' => 'immutable_datetime',
            'review_required_at' => 'immutable_datetime',
            'refund_handled_at' => 'immutable_datetime',
            'sadad_verified_at' => 'immutable_datetime',
            'terms_accepted_at' => 'immutable_datetime',
            'is_present' => 'boolean',
            'sadad_manually_verified' => 'boolean',
            'send_attempt_count' => 'integer',
        ];
    }

    // ---- Relations --------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function holders(): HasMany
    {
        return $this->hasMany(TicketHolder::class)->orderBy('position')->orderBy('created_at');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function posUser(): BelongsTo
    {
        return $this->belongsTo(PosUser::class)->withTrashed();
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function kioskDevice(): BelongsTo
    {
        return $this->belongsTo(KioskDevice::class);
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function paymentErrors(): HasMany
    {
        return $this->hasMany(PaymentError::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function emailDeliveryEvents(): HasMany
    {
        return $this->hasMany(EmailDeliveryEvent::class);
    }

    public function automationDispatches(): HasMany
    {
        return $this->hasMany(AutomationDispatch::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function expiredCodes(): HasMany
    {
        return $this->hasMany(ExpiredQrCode::class);
    }

    public function eventChanges(): HasMany
    {
        return $this->hasMany(OrderEventChange::class)->orderBy('created_at');
    }

    public function sadadTransaction(): BelongsTo
    {
        return $this->belongsTo(SadadTransaction::class, 'payment_id', 'transaction_number');
    }

    // ---- Derived state ----------------------------------------------------------

    /** F-PAY-043: cancelled + failed-set note → "failed". */
    public function publicStatus(): string
    {
        if ($this->payment_status === PaymentStatus::Cancelled) {
            return PaymentNote::isFailedNote($this->payment_note) ? 'failed' : 'cancelled';
        }

        return $this->payment_status->value;
    }

    /** R-058: cancelled by staff (not in the failed set) = final. */
    public function isFinalCancellation(): bool
    {
        return $this->payment_status === PaymentStatus::Cancelled && ! PaymentNote::isFailedNote($this->payment_note);
    }

    public function isActive(): bool
    {
        return $this->payment_status->isActive();
    }

    public function isPos(): bool
    {
        return $this->source === OrderSource::Pos;
    }

    public function isSadad(): bool
    {
        return $this->payment_method === PaymentMethod::Sadad;
    }

    public function isCashAtVenue(): bool
    {
        return $this->source === OrderSource::Web && $this->payment_method === PaymentMethod::CashPos;
    }

    public function holdIsValid(): bool
    {
        return $this->payment_expires_at === null || $this->payment_expires_at->isFuture();
    }

    public function noteLabel(): ?string
    {
        return PaymentNote::labelFor($this->payment_note);
    }

    public function isLegacyReference(): bool
    {
        return preg_match('/^(QTR|POS)-[0-9]{1,2}-[0-9]{1,2}-[0-9]{4}-[0-9A-F]{12}$/', $this->booking_reference) !== 1;
    }

    /** Pending automation message (F-ADM-INV-004). */
    public const SENDING_WHATSAPP = 'جاري الإرسال إلى واتساب...';

    public const SENDING_EMAIL = 'جاري إرسال البريد الإلكتروني...';

    public const POS_MARKER = 'طلب من نقطة البيع - POS';

    public const MANUAL_CHECKIN_MARKER = 'تم التسجيل يدوياً ✓';

    public const MARKED_SENT_MARKER = 'تم التحديد كمرسل يدويًا';

    public const AUTO_SENT_MARKER = 'تم الإرسال تلقائياً';

    public function hasPendingAutomationMessage(): bool
    {
        return $this->n8n_response_message === null
            || in_array($this->n8n_response_message, [self::SENDING_WHATSAPP, self::SENDING_EMAIL], true);
    }

    /** R-156 on-hold rule. */
    public function isInvoiceOnHold(int $maxAttempts = 2): bool
    {
        return $this->send_attempt_count >= $maxAttempts && ($this->hasPendingAutomationMessage() || $this->n8n_responded_at === null);
    }

    // ---- Scopes -------------------------------------------------------------------

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('payment_status', [PaymentStatus::Pending->value, PaymentStatus::Confirmed->value]);
    }

    /** R-020/R-053: confirmed OR pending with unexpired (or null) hold. */
    public function scopeHoldingSeats(Builder $q, $now = null): Builder
    {
        $now ??= QatarClock::nowUtc();

        return $q->where(fn (Builder $w) => $w->where('payment_status', PaymentStatus::Confirmed->value)
            ->orWhere(fn (Builder $p) => $p->where('payment_status', PaymentStatus::Pending->value)
                ->where(fn (Builder $e) => $e->whereNull('payment_expires_at')->orWhere('payment_expires_at', '>', $now))));
    }

    public function scopeExpiredHolds(Builder $q, $now = null): Builder
    {
        $now ??= QatarClock::nowUtc();

        return $q->where('payment_status', PaymentStatus::Pending->value)
            ->where('payment_method', PaymentMethod::Sadad->value)
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<', $now);
    }

    public function scopeFinalCancelled(Builder $q): Builder
    {
        return $q->where('payment_status', PaymentStatus::Cancelled->value)
            ->where(fn (Builder $w) => $w->whereNull('payment_note')->orWhereNotIn('payment_note', PaymentNote::FAILED_SET));
    }

    /** F-ADM-ORD-007: event date ≥ Qatar today. */
    public function scopeUpcoming(Builder $q): Builder
    {
        [$start] = QatarClock::dayRange(QatarClock::today());

        return $q->whereHas('event', fn (Builder $e) => $e->where(fn ($w) => $w->where('event_date', '>=', $start)->orWhere('end_date', '>=', $start)));
    }

    public function scopeSeason(Builder $q, bool $archived): Builder
    {
        return $q->whereHas('event', fn (Builder $e) => $e->withTrashed()->where('is_archived', $archived));
    }
}
