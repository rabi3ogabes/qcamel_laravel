<?php

/*
| Every queue a job is dispatched to must be consumed by Horizon — otherwise
| payment confirmations, e-mails, WhatsApp and QR rendering silently never run.
*/

it('has Horizon consume every queue the jobs are sent to', function () {
    $used = ['default'];
    foreach (glob(app_path('Jobs/*.php')) as $file) {
        preg_match_all("/onQueue\\('([a-z_-]+)'\\)|public \\\$queue = '([a-z_-]+)'/", file_get_contents($file), $m);
        $used = array_merge($used, array_filter($m[1]), array_filter($m[2]));
    }
    $consumed = collect(config('horizon.defaults'))->flatMap(fn ($s) => (array) ($s['queue'] ?? []))->all();

    expect(array_values(array_diff(array_unique($used), $consumed)))->toBe([]);
});
