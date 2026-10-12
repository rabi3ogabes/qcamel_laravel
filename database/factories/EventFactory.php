<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use App\Support\QatarClock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $day = QatarClock::now()->addDays(fake()->numberBetween(2, 20))->startOfDay();

        return [
            'title' => 'مهرجان قطر للإبل — '.fake()->randomElement(['الشوط الأول', 'شوط الحقايق', 'شوط اللقايا', 'الختامي']),
            'description' => 'سباقات الهجن التراثية وفعاليات عائلية في أجواء صحراوية أصيلة.',
            'event_date' => $day->utc(),
            'start_time' => '15:00:00',
            'end_time' => '22:00:00',
            'location' => 'ميدان الشحانية — الدوحة',
            'is_active' => true,
            'is_archived' => false,
            'display_order' => 0,
        ];
    }

    /** Event on a given Qatar date ("Y-m-d"). */
    public function on(string $date): static
    {
        return $this->state(fn () => ['event_date' => QatarClock::at($date, '00:00:00')->utc()]);
    }

    public function today(): static
    {
        return $this->on(QatarClock::today());
    }

    public function past(): static
    {
        return $this->on(QatarClock::now()->subDays(3)->format('Y-m-d'));
    }

    public function archived(): static
    {
        return $this->state(fn () => ['is_archived' => true, 'archived_at' => now()]);
    }

    /** The standard three ticket rows (R-029). */
    public function withTickets(int $normal = 200, int $vip = 50, int $parking = 100, array $prices = ['normal' => '50.00', 'vip' => '150.00', 'parking' => '20.00']): static
    {
        return $this->afterCreating(function (Event $event) use ($normal, $vip, $parking, $prices) {
            foreach (['normal' => $normal, 'vip' => $vip, 'parking' => $parking] as $type => $qty) {
                TicketType::query()->create([
                    'event_id' => $event->id, 'type' => $type, 'price' => $prices[$type],
                    'available_quantity' => $qty, 'sold_quantity' => 0,
                ]);
            }
        });
    }
}
