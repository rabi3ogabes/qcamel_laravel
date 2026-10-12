<?php

namespace App\Imports;

use App\Models\Event;
use App\Models\TicketType;
use App\Support\QatarClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * F-ADM-SET-035 rebuild: English or Arabic headers, Excel serial dates and
 * day-fraction times converted in Asia/Qatar, optional end date and the three
 * ticket types per row, one transaction per row, every skipped row reported.
 */
class EventsImport implements ToCollection, WithHeadingRow
{
    public const HEADINGS = ['title', 'description', 'event_date', 'end_date', 'location', 'image_url', 'video_url', 'display_order', 'start_time', 'end_time', 'is_active',
        'normal_qty', 'normal_price', 'vip_qty', 'vip_price', 'parking_qty', 'parking_price'];

    private const ALIASES = [
        'title' => ['title', 'اسم_الفعالية', 'اسم الفعالية'], 'description' => ['description', 'وصف_الفعالية', 'وصف الفعالية'],
        'event_date' => ['event_date', 'تاريخ_الفعالية', 'تاريخ الفعالية'], 'end_date' => ['end_date', 'تاريخ_النهاية'],
        'location' => ['location', 'الموقع'], 'image_url' => ['image_url', 'رابط_الصورة'], 'video_url' => ['video_url', 'رابط_الفيديو'],
        'display_order' => ['display_order', 'ترتيب_العرض'], 'start_time' => ['start_time', 'وقت_البداية'], 'end_time' => ['end_time', 'وقت_النهاية'],
        'is_active' => ['is_active', 'نشط'],
    ];

    public int $created = 0;

    /** @var list<string> */
    public array $errors = [];

    /** @return list<array<string,mixed>> */
    public static function templateRows(): array
    {
        return [[
            'title' => 'اسم الفعالية', 'description' => 'وصف الفعالية', 'event_date' => '2026-01-15', 'end_date' => '', 'location' => 'الموقع',
            'image_url' => 'https://example.com/image.jpg', 'video_url' => '', 'display_order' => 1, 'start_time' => '15:00', 'end_time' => '22:00', 'is_active' => true,
            'normal_qty' => 300, 'normal_price' => '50.00', 'vip_qty' => 60, 'vip_price' => '150.00', 'parking_qty' => 150, 'parking_price' => '20.00',
        ]];
    }

    private function pick(Collection $row, string $key): mixed
    {
        foreach (self::ALIASES[$key] ?? [$key] as $alias) {
            $k = str_replace(' ', '_', mb_strtolower($alias));
            if ($row->has($k) && $row[$k] !== null && $row[$k] !== '') {
                return $row[$k];
            }
        }

        return null;
    }

    private static function date(mixed $v): ?CarbonImmutable
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $v))->setTimezone(QatarClock::tz())->startOfDay();
        }

        return QatarClock::local((string) $v)?->startOfDay();
    }

    private static function time(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v) && (float) $v < 1) {
            $m = (int) round((float) $v * 1440);

            return sprintf('%02d:%02d:00', intdiv($m, 60) % 24, $m % 60);
        }

        return preg_match('/^(\d{1,2}):(\d{2})/', (string) $v, $m) ? sprintf('%02d:%02d:00', $m[1], $m[2]) : null;
    }

    private static function cleanUrl(mixed $v): ?string
    {
        $v = trim((string) $v);

        return in_array($v, ['', '#', '-'], true) || ! filter_var($v, FILTER_VALIDATE_URL) ? null : $v;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $line = $i + 2;
            try {
                $title = trim((string) $this->pick($row, 'title'));
                $location = trim((string) $this->pick($row, 'location'));
                $date = self::date($this->pick($row, 'event_date'));
                if ($title === '' || $location === '' || ! $date) {
                    $this->errors[] = __('admin.set.import_row_missing', ['line' => $line]);

                    continue;
                }
                $active = $this->pick($row, 'is_active');
                DB::transaction(function () use ($row, $title, $location, $date, $active) {
                    $event = Event::query()->create([
                        'title' => mb_substr($title, 0, 200),
                        'description' => $this->pick($row, 'description'),
                        'event_date' => $date->utc(),
                        'end_date' => ($end = self::date($this->pick($row, 'end_date'))) ? $end->utc() : null,
                        'location' => mb_substr($location, 0, 200),
                        'image_url' => self::cleanUrl($this->pick($row, 'image_url')),
                        'video_url' => self::cleanUrl($this->pick($row, 'video_url')),
                        'display_order' => (int) ($this->pick($row, 'display_order') ?? 0),
                        'start_time' => self::time($this->pick($row, 'start_time')),
                        'end_time' => self::time($this->pick($row, 'end_time')),
                        'is_active' => $active === null ? true : in_array(strtolower((string) $active), ['1', 'true', 'نعم', 'yes'], true),
                        'is_archived' => false,
                    ]);
                    foreach (['normal', 'vip', 'parking'] as $type) {
                        $qty = $row[$type.'_qty'] ?? null;
                        $price = $row[$type.'_price'] ?? null;
                        if (is_numeric($qty) && is_numeric($price)) {
                            TicketType::query()->create(['event_id' => $event->id, 'type' => $type, 'available_quantity' => max(0, (int) $qty), 'price' => number_format((float) $price, 2, '.', ''), 'sold_quantity' => 0]);
                        }
                    }
                });
                $this->created++;
            } catch (Throwable $e) {
                $this->errors[] = __('admin.set.import_row_error', ['line' => $line, 'error' => mb_substr($e->getMessage(), 0, 120)]);
            }
        }
    }
}
