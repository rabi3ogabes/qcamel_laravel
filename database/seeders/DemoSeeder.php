<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\PosUser;
use App\Settings\BrandingSettings;
use App\Support\PublicSettings;
use App\Support\QatarClock;
use Illuminate\Database\Seeder;

/**
 * Local/demo data: a festival week with the three ticket types and the
 * venue cashiers. Never runs in production (DatabaseSeeder guards it).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Festival identity (qcamelmc.org) unless an admin already named it.
        $brand = app(BrandingSettings::class);
        if (blank($brand->festival_name)) {
            $brand->festival_name = 'مهرجان التحدي للمفاريد';
            $brand->season_label = '2026';
            $brand->save();
            PublicSettings::flush();
        }

        if (Event::query()->exists()) {
            return;
        }
        $titles = [
            ['الافتتاح الكبير', 'حفل الافتتاح وأشواط الحقايق مع عروض تراثية وفرق العرضة.'],
            ['أشواط اللقايا', 'منافسات اللقايا على مضمار الشحانية مع جوائز يومية للجمهور.'],
            ['يوم العائلة', 'أنشطة للأطفال، قرية تراثية، ومأكولات قطرية أصيلة.'],
            ['أشواط الثنايا', 'سباقات الثنايا والحول، ومسابقات التصوير للزوار.'],
            ['ليلة الهجن', 'أشواط مسائية تحت الأضواء مع عروض الصقور.'],
            ['الختام الملكي', 'الأشواط الختامية وتتويج الفائزين بالسيوف الذهبية.'],
        ];
        foreach ($titles as $i => [$title, $desc]) {
            Event::factory()
                ->on(QatarClock::now()->addDays($i)->format('Y-m-d'))
                ->withTickets(normal: 300 - $i * 20, vip: 60, parking: 150)
                ->create([
                    'title' => $title,
                    'description' => $desc,
                    'display_order' => $i,
                    'start_time' => $i === 4 ? '18:30:00' : '15:00:00',
                    'end_time' => $i === 4 ? '23:30:00' : '22:00:00',
                    'sales_cutoff_time' => '23:59:00',
                    'checkin_cutoff_time' => '23:59:00',
                ]);
        }

        foreach (['أحمد', 'مريم', 'خالد', 'نورة'] as $n => $name) {
            PosUser::query()->firstOrCreate(['name' => $name], ['icon' => PosUser::ICONS[$n % count(PosUser::ICONS)], 'is_active' => true]);
        }
    }
}
