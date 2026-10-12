<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Response;

/** F-PUB-003 robots.txt (R-202) and sitemap.xml. */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Allow: /'];
        foreach (['/admin/', '/staff', '/live-bookings', '/live-visitors', '/checkout', '/confirmation', '/invoice/', '/payment/', '/sadad-callback', '/booking', '/api/'] as $p) {
            $lines[] = 'Disallow: '.$p;
        }
        $lines[] = 'Sitemap: '.url('/sitemap.xml');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $urls = [url('/')];
        foreach (Event::query()->public()->orderBy('event_date')->get(['id', 'event_date', 'end_date', 'sales_cutoff_time', 'is_active', 'is_archived', 'deleted_at']) as $e) {
            if ($e->canPurchaseTickets()) {
                $urls[] = route('tickets.select', $e->id);
            }
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $xml .= '<url><loc>'.e($u).'</loc></url>';
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
    }
}
