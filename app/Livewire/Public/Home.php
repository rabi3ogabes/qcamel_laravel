<?php

namespace App\Livewire\Public;

use App\Models\Event;
use App\Support\Formats;
use App\Support\PublicSettings;
use App\Support\QatarClock;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * F-PUB-014…022: event home. Server-rendered list cached 60 s (busted with
 * the public settings), re-filtered by the 18:00 cut-off on every read.
 */
#[Layout('components.layouts.public', ['header' => 'home', 'whatsapp' => true, 'beforeFooter' => true, 'surface' => 'plain'])]
class Home extends Component
{
    public const PER_PAGE = 9;

    #[Url(except: 1)]
    public int $page = 1;

    /** @return list<array<string,mixed>> */
    public static function listing(): array
    {
        $rows = Cache::remember('public.events', (int) config('qcamelmc.cache.events_seconds', 60), function () {
            $current = PublicSettings::get('current_event_id');
            $q = Event::query()->public()->with('ticketTypes:id,event_id,type,price');
            if ($current) {
                $q->whereKey($current);
            }

            return $q->orderBy('event_date')->orderBy('display_order')->orderBy('start_time')->get()
                ->map(fn (Event $e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'description' => $e->description,
                    'event_date' => $e->event_date?->toIso8601String(),
                    'end_date' => $e->end_date?->toIso8601String(),
                    'start_time' => $e->start_time,
                    'end_time' => $e->end_time,
                    'location' => $e->location,
                    'cover' => $e->coverUrl(),
                    'cutoff' => $e->cutoffAt()->toIso8601String(),
                    'from_price' => $e->ticketTypes->where('type.value', '!=', 'parking')->min('price') ?? $e->ticketTypes->min('price'),
                ])->all();
        });

        $now = now();

        // F-PUB-016: the cut-off is evaluated at read time, not cache time.
        return array_values(array_filter($rows, fn ($r) => $now->lt($r['cutoff'])));
    }

    public function goTo(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function render()
    {
        $all = self::listing();
        $pages = max(1, (int) ceil(count($all) / self::PER_PAGE));
        $this->page = min(max(1, $this->page), $pages);
        $events = array_slice($all, ($this->page - 1) * self::PER_PAGE, self::PER_PAGE);

        return view('livewire.public.home', [
            'events' => array_map(fn ($e) => $e + [
                'date_label' => Formats::longDate($e['event_date']),
                'day' => Formats::dayMonth($e['event_date']),
                'weekday' => Formats::weekday($e['event_date']),
                'start' => Formats::time12($e['start_time']),
                'end' => Formats::time12($e['end_time']),
                'is_today' => QatarClock::dateOf($e['event_date']) === QatarClock::today(),
            ], $events),
            'pages' => $pages,
            'total' => count($all),
        ]);
    }
}
