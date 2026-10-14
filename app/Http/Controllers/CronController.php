<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Serverless replacement for `schedule:work` + `horizon` (Vercel cron, every
 * minute). Runs the scheduler, then drains the database queue for the rest of
 * the time budget. Authenticated with the CRON_SECRET bearer that Vercel sends.
 */
class CronController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('qcamelmc.serverless.cron_secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->bearerToken())) {
            throw new HttpException(401, 'Unauthorized');
        }

        $started = microtime(true);
        $budget = max(10, (int) config('qcamelmc.serverless.cron_budget_seconds', 45));
        $lock = Cache::lock('serverless-cron', $budget + 15);
        if (! $lock->get()) {
            return response()->json(['ok' => true, 'skipped' => 'another run is active']);
        }

        try {
            Artisan::call('schedule:run');
            $schedule = trim(Artisan::output());

            $remaining = $budget - (int) (microtime(true) - $started);
            $queue = '';
            if ($remaining > 5) {
                Artisan::call('queue:work', [
                    '--stop-when-empty' => true,
                    '--max-time' => $remaining,
                    '--tries' => 3,
                    '--queue' => 'payments,notifications,automation,qr,exports,default',
                ]);
                $queue = trim(Artisan::output());
            }
        } finally {
            $lock->release();
        }

        return response()->json([
            'ok' => true,
            'seconds' => round(microtime(true) - $started, 1),
            'schedule' => mb_substr($schedule, 0, 2000),
            'queue' => mb_substr($queue, 0, 2000),
        ]);
    }
}
