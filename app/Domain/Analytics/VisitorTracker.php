<?php

namespace App\Domain\Analytics;

use App\Events\VisitorPresenceChanged;
use App\Models\ActiveVisitor;
use App\Models\PageView;
use App\Settings\AnalyticsSettings;
use GeoIp2\Database\Reader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Server-side visitor tracking (F-PUB-090/091, F-ADM-VIS-013…016, R-179,
 * R-201, D-027, D-082): no browser geo call, correct Android/iOS detection,
 * staff/admin/invoice pages excluded, IP masking, 60 s heartbeat.
 */
class VisitorTracker
{
    public const SESSION_KEY = 'visitor.sid';

    public static function excluded(string $path): bool
    {
        $path = '/'.ltrim(strtolower($path), '/');
        foreach ((array) config('qcamelmc.visitors.excluded_prefixes') as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** Record a page view + upsert presence (throttled 60 s per session for presence). */
    public function track(Request $request, ?string $path = null): void
    {
        try {
            $path = mb_substr($path ?? '/'.ltrim($request->path(), '/'), 0, 255);
            if (self::excluded($path)) {
                return;
            }
            $session = $request->session();
            $sid = $session->get(self::SESSION_KEY);
            $isNew = false;
            if (! $sid) {
                $sid = substr(bin2hex(random_bytes(16)), 0, 32);
                $session->put(self::SESSION_KEY, $sid);
                $isNew = ! $request->cookies->has('qc_returning');
                cookie()->queue(cookie('qc_returning', '1', 60 * 24 * 365));
            }
            $info = $this->classify($request);
            $geo = $this->geo($request->ip());
            $ip = $this->ipForStorage($request->ip());

            $lastPath = $session->get('visitor.last_path');
            if ($lastPath !== $path) {
                PageView::create($info + $geo + ['session_id' => $sid, 'page_path' => $path, 'is_new_visitor' => $isNew, 'ip_address' => $ip, 'viewed_at' => now()]);
                $session->put('visitor.last_path', $path);
            }
            $this->touchPresence($sid, $path, $request, $info, $geo, $ip, $isNew);
        } catch (Throwable) {
            // analytics must never break the app
        }
    }

    /** Heartbeat from the public layout beacon (every 60 s). */
    public function heartbeat(Request $request, ?string $path): void
    {
        $sid = $request->session()->get(self::SESSION_KEY);
        if (! $sid || ($path && self::excluded($path))) {
            return;
        }
        ActiveVisitor::query()->where('session_id', $sid)->update(['last_seen_at' => now(), 'current_page' => mb_substr($path ?: '/', 0, 255)]);
    }

    private function touchPresence(string $sid, string $path, Request $request, array $info, array $geo, ?string $ip, bool $isNew): void
    {
        $existing = ActiveVisitor::query()->where('session_id', $sid)->first();
        if ($existing) {
            $existing->forceFill(['current_page' => $path, 'last_seen_at' => now()])->save();

            return;
        }
        ActiveVisitor::create($info + $geo + [
            'session_id' => $sid, 'ip_address' => $ip, 'current_page' => $path,
            'referrer' => ($r = $request->headers->get('referer')) ? mb_substr($r, 0, 500) : null,
            'is_new_visitor' => $isNew, 'user_agent' => mb_substr((string) $request->userAgent(), 0, 300),
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);
        if (Cache::add('visitors:broadcast', 1, 10)) {
            event(new VisitorPresenceChanged(ActiveVisitor::query()->activeNow()->count()));
        }
    }

    /** @return array{device_type:string, browser:string, os:string, traffic_source:string} */
    public function classify(Request $request): array
    {
        $ua = (string) $request->userAgent();
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        return [
            'device_type' => self::device($ua),
            'browser' => self::browser($ua),
            'os' => self::os($ua),
            'traffic_source' => self::source($host ?: null, $request->getHost()),
        ];
    }

    public static function device(string $ua): string
    {
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    public static function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Browser',
            str_contains($ua, 'OPR') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Trident') => 'IE',
            str_contains($ua, 'Edg') => 'Edge',
            str_contains($ua, 'Chrome') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'Unknown',
        };
    }

    /** Android/iOS checked before Linux/macOS (fixes D-082 mis-detection). */
    public static function os(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            str_contains($ua, 'Win') => 'Windows',
            str_contains($ua, 'Mac') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown',
        };
    }

    public static function source(?string $refHost, ?string $ownHost): string
    {
        if (! $refHost) {
            return 'direct';
        }
        $h = strtolower($refHost);
        if ($ownHost && $h === strtolower($ownHost)) {
            return 'internal';
        }
        $map = ['facebook' => ['facebook', 'fb.com'], 'twitter' => ['twitter', 'x.com', 't.co'], 'instagram' => ['instagram'], 'linkedin' => ['linkedin'], 'tiktok' => ['tiktok'], 'youtube' => ['youtube', 'youtu.be'], 'whatsapp' => ['whatsapp'], 'telegram' => ['telegram', 't.me'], 'snapchat' => ['snapchat'], 'google' => ['google'], 'bing' => ['bing'], 'yahoo' => ['yahoo'], 'duckduckgo' => ['duckduckgo']];
        foreach ($map as $src => $needles) {
            foreach ($needles as $n) {
                if (str_contains($h, $n)) {
                    return $src;
                }
            }
        }

        return 'referral';
    }

    /** @return array{country:?string, country_code:?string, city:?string} — local GeoLite2 DB or CDN header. */
    public function geo(?string $ip): array
    {
        $out = ['country' => null, 'country_code' => null, 'city' => null];
        $cf = request()->header('CF-IPCountry');
        if ($cf && strlen($cf) === 2 && $cf !== 'XX') {
            $out['country_code'] = strtoupper($cf);
        }
        if (! $ip) {
            return $out;
        }

        return Cache::remember('geo:'.sha1($ip), 1800, function () use ($ip, $out) {
            $db = (string) config('qcamelmc.geo.database');
            if ($db !== '' && is_file($db)) {
                try {
                    $rec = (new Reader($db))->city($ip);

                    return ['country' => $rec->country->name, 'country_code' => $rec->country->isoCode, 'city' => $rec->city->name];
                } catch (Throwable) {
                    // private / unknown address
                }
            }

            return $out;
        });
    }

    /** IP truncation (D-082): mask last octet unless disabled. */
    public function ipForStorage(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }
        try {
            $mask = app(AnalyticsSettings::class)->mask_ip;
        } catch (Throwable) {
            $mask = true;
        }

        return $mask ? self::mask($ip) : $ip;
    }

    public static function mask(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);

            return implode(':', array_slice($parts, 0, 3)).':xxxx';
        }
        $p = explode('.', $ip);
        if (count($p) === 4) {
            $p[3] = 'x';
        }

        return implode('.', $p);
    }

    public static function flag(?string $code): string
    {
        $code = strtoupper((string) $code);
        if (strlen($code) !== 2 || $code === 'XX') {
            return '🌍';
        }

        return mb_chr(127397 + ord($code[0])).mb_chr(127397 + ord($code[1]));
    }
}
