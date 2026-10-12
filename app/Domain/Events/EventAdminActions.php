<?php

namespace App\Domain\Events;

use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\ActivityType;
use App\Enums\TicketKind;
use App\Events\TicketCapacityIncreased;
use App\Models\Event;
use App\Models\TicketType;
use App\Settings\EventSettings;
use App\Support\Audit;
use App\Support\Money;
use App\Support\PublicSettings;
use App\Support\QatarClock;
use Illuminate\Support\Facades\DB;

/**
 * ADM-EVT domain rules (F-ADM-EVT-011…023): event + ticket types saved in one
 * transaction under row locks, capacity never below held seats, a type with
 * sales is never removed, dates built in Asia/Qatar, every change audited.
 */
class EventAdminActions
{
    private function authorize(Actor $actor, string $permission = 'events.manage'): void
    {
        if (! $actor->can($permission)) {
            throw DomainError::forbidden();
        }
    }

    /**
     * @param  array<string,mixed>  $data  title, description, start_day, end_day, start_time, end_time, location, image_url, video_url,
     *                                     display_order, is_active, sales_cutoff_time, checkin_cutoff_time, limits…, tickets[type]{qty,price,description}
     */
    public function save(?Event $event, array $data, Actor $actor): Event
    {
        $this->authorize($actor);
        $title = trim((string) ($data['title'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        if ($title === '' || $location === '') {
            throw DomainError::validation([__('admin.evt.v_title_location')]);
        }
        $startDay = (string) ($data['start_day'] ?? '');
        $endDay = (string) ($data['end_day'] ?? '') ?: null;
        if (! preg_match('/^\d{4}-\d{2}-\d{2}/', $startDay)) {
            throw DomainError::validation([__('admin.evt.v_dates')]);
        }
        $startDay = substr($startDay, 0, 10);
        $endDay = $endDay ? substr($endDay, 0, 10) : null;
        if ($endDay && $endDay < $startDay) {
            throw DomainError::validation([__('admin.evt.v_end_before_start')]);
        }
        $st = self::time($data['start_time'] ?? null);
        $et = self::time($data['end_time'] ?? null);
        if ($st && $et && $et <= $st && (! $endDay || $endDay === $startDay)) {
            throw DomainError::validation([__('admin.evt.v_end_time')]);
        }
        $tickets = [];
        foreach (TicketKind::cases() as $kind) {
            $t = $data['tickets'][$kind->value] ?? [];
            $qty = $t['qty'] ?? null;
            $price = $t['price'] ?? null;
            if (($qty === null || $qty === '') && ($price === null || $price === '')) {
                $tickets[$kind->value] = null; // remove (when unsold)

                continue;
            }
            if (! is_numeric($qty) || (int) $qty < 0 || (string) (int) $qty !== (string) $qty && ! is_int($qty) || Money::tryOf($price) === null || Money::of((string) $price)->cents < 0) {
                throw DomainError::validation([__('admin.evt.v_ticket', ['type' => $kind->label()])]);
            }
            $tickets[$kind->value] = ['qty' => (int) $qty, 'price' => Money::of((string) $price)->toDecimal(), 'description' => trim((string) ($t['description'] ?? '')) ?: null];
        }

        return DB::transaction(function () use ($event, $data, $title, $location, $startDay, $endDay, $st, $et, $tickets, $actor) {
            $isNew = $event === null;
            $event ??= new Event;
            $before = $isNew ? [] : $event->only(['title', 'event_date', 'end_date', 'location', 'start_time', 'end_time', 'is_active', 'display_order']);
            $event->fill([
                'title' => mb_substr($title, 0, 200),
                'description' => ($d = trim((string) ($data['description'] ?? ''))) === '' ? null : $d,
                'event_date' => QatarClock::at($startDay, $st ?? '00:00:00')->utc(),
                'end_date' => $endDay ? QatarClock::at($endDay, $et ?? '23:59:00')->utc() : null,
                'start_time' => $st,
                'end_time' => $et,
                'location' => mb_substr($location, 0, 200),
                'image_url' => ($data['image_url'] ?? null) ?: null,
                'image_path' => array_key_exists('image_path', $data) ? ($data['image_path'] ?: null) : $event->image_path,
                'video_url' => ($data['video_url'] ?? null) ?: null,
                'display_order' => (int) ($data['display_order'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'sales_cutoff_time' => self::time($data['sales_cutoff_time'] ?? null) ?? ($event->sales_cutoff_time ?: '18:00:00'),
                'checkin_cutoff_time' => self::time($data['checkin_cutoff_time'] ?? null) ?? ($event->checkin_cutoff_time ?: '18:00:00'),
                'max_admission_per_order' => self::nullableInt($data['max_admission_per_order'] ?? null),
                'max_parking_per_order' => self::nullableInt($data['max_parking_per_order'] ?? null),
                'per_person_cap' => self::nullableInt($data['per_person_cap'] ?? null),
                'hold_minutes' => self::nullableInt($data['hold_minutes'] ?? null),
            ]);
            if ($isNew) {
                $event->is_archived = false;
            }
            $event->save();

            $locked = TicketType::query()->where('event_id', $event->id)->orderBy('id')->lockForUpdate()->get()->keyBy(fn ($t) => $t->type->value);
            foreach ($tickets as $type => $t) {
                $row = $locked[$type] ?? null;
                if ($t === null) {
                    if ($row) {
                        if ((int) $row->sold_quantity > 0) {
                            throw new DomainError('type_has_sales', 422, null, ['replace' => ['type' => TicketKind::from($type)->label(), 'sold' => $row->sold_quantity]]);
                        }
                        $row->delete();
                    }

                    continue;
                }
                if ($row) {
                    if ($t['qty'] < (int) $row->sold_quantity) {
                        throw new DomainError('capacity_below_sold_type', 422, null, ['replace' => ['type' => TicketKind::from($type)->label(), 'q' => $t['qty'], 'sold' => $row->sold_quantity]]);
                    }
                    $old = (int) $row->available_quantity;
                    $row->forceFill(['available_quantity' => $t['qty'], 'price' => $t['price'], 'description' => $t['description']])->save();
                    if ($t['qty'] > $old) {
                        event(new TicketCapacityIncreased($event->id, $type, $old, $t['qty']));
                    }
                } else {
                    TicketType::query()->create(['event_id' => $event->id, 'type' => $type, 'available_quantity' => $t['qty'], 'price' => $t['price'], 'description' => $t['description'], 'sold_quantity' => 0]);
                }
            }

            Audit::log(ActivityType::EventUpdated, __($isNew ? 'admin.evt.audit_created' : 'admin.evt.audit_updated', ['title' => $event->title]), $event,
                ['before' => $before, 'after' => $event->only(array_keys($before) ?: ['title', 'event_date', 'location']), 'tickets' => $tickets], $actor);
            DB::afterCommit(fn () => PublicSettings::flush());

            return $event;
        });
    }

    /** F-ADM-EVT-020/021 rebuild: create first, then archive others, then set current — one transaction. */
    public function startNewSeason(array $data, bool $archivePrevious, Actor $actor): Event
    {
        $this->authorize($actor);
        foreach (TicketKind::cases() as $kind) {
            $t = $data['tickets'][$kind->value] ?? [];
            if (! isset($t['qty'], $t['price']) || $t['qty'] === '' || $t['price'] === '') {
                throw DomainError::validation([__('admin.evt.v_all_tickets')]);
            }
        }
        if (empty($data['end_day'])) {
            throw DomainError::validation([__('admin.evt.v_dates')]);
        }

        return DB::transaction(function () use ($data, $archivePrevious, $actor) {
            $event = $this->save(null, $data + ['is_active' => true, 'display_order' => 0], $actor);
            $archived = 0;
            if ($archivePrevious) {
                $archived = Event::query()->whereKeyNot($event->id)->where('is_archived', false)
                    ->update(['is_archived' => true, 'is_active' => false, 'archived_at' => now()]);
            }
            $settings = app(EventSettings::class);
            $settings->current_event_id = $event->id;
            $settings->season_start_date = substr((string) $data['start_day'], 0, 10);
            $settings->save();
            Audit::log(ActivityType::NewEventStarted, __('admin.evt.audit_season', ['title' => $event->title]), $event,
                ['event_id' => $event->id, 'start_day' => $data['start_day'], 'end_day' => $data['end_day'], 'archived_previous' => $archived], $actor);
            DB::afterCommit(fn () => PublicSettings::flush());

            return $event;
        });
    }

    public function setArchived(Event $event, bool $archived, Actor $actor): void
    {
        $this->authorize($actor);
        $event->forceFill($archived ? ['is_archived' => true, 'archived_at' => now(), 'is_active' => false] : ['is_archived' => false, 'archived_at' => null])->save();
        if ($archived) {
            $settings = app(EventSettings::class);
            if ($settings->current_event_id === $event->id) {
                $settings->current_event_id = null;
                $settings->save();
            }
        }
        Audit::log(ActivityType::EventUpdated, __($archived ? 'admin.evt.audit_archived' : 'admin.evt.audit_restored', ['title' => $event->title]), $event, [], $actor);
        PublicSettings::flush();
    }

    public function toggleActive(Event $event, Actor $actor): void
    {
        $this->authorize($actor);
        $event->forceFill(['is_active' => ! $event->is_active])->save();
        Audit::log(ActivityType::EventUpdated, __('admin.evt.audit_active', ['title' => $event->title, 'state' => $event->is_active ? __('admin.evt.active') : __('admin.evt.inactive')]), $event, [], $actor);
        PublicSettings::flush();
    }

    /** Delete only when the event never had bookings (else archive), F-ADM-EVT-016 rebuild. */
    public function delete(Event $event, Actor $actor): void
    {
        $this->authorize($actor, 'events.delete');
        if ($event->orders()->withTrashed()->exists()) {
            throw new DomainError('event_has_orders', 409);
        }
        DB::transaction(function () use ($event, $actor) {
            TicketType::query()->where('event_id', $event->id)->delete();
            $event->delete();
            Audit::log(ActivityType::EventUpdated, __('admin.evt.audit_deleted', ['title' => $event->title]), null, ['event_id' => $event->id], $actor);
        });
        PublicSettings::flush();
    }

    public function setCurrent(?string $eventId, Actor $actor): void
    {
        $this->authorize($actor);
        if ($eventId && ! Event::query()->whereKey($eventId)->where('is_archived', false)->exists()) {
            throw DomainError::validation([__('admin.evt.v_current')]);
        }
        $settings = app(EventSettings::class);
        $settings->current_event_id = $eventId ?: null;
        $settings->save();
        Audit::log(ActivityType::SettingsChanged, __('admin.evt.audit_current'), null, ['current_event_id' => $eventId], $actor);
        PublicSettings::flush();
    }

    private static function time(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', (string) $v, $m) ? sprintf('%02d:%02d:%02d', $m[1], $m[2], $m[3] ?? 0) : null;
    }

    private static function nullableInt(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : max(0, (int) $v);
    }
}
