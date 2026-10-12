<?php

namespace App\Domain\Payments\Sadad;

use App\Enums\SadadLiveStatus;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * F-PAY-028…032, F-PAY-057 merchant API client (R-108…R-110, T-080, T-081).
 * One credential strategy (dedicated API-login secret, D-090), env-aware,
 * 8 s timeouts, token cached per environment (10 min). Never throws.
 */
class SadadApiClient
{
    private const SAFE_REF = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,59}$/';

    private const SAFE_TXN = '/^[A-Za-z0-9_-]{1,64}$/';

    public function __construct(private readonly SadadConfig $config) {}

    /** @return list<string> */
    private function environments(?string $env = null): array
    {
        $env ??= $this->config->environment();

        return $env === 'auto' ? ['live', 'sandbox'] : [$env];
    }

    /**
     * @return array{token?:string, error?:string}
     */
    public function login(string $env, bool $useCache = true): array
    {
        $merchant = (string) $this->config->merchantId();
        $secret = (string) $this->config->apiLoginSecret();
        $domain = (string) $this->config->websiteDomain();
        $cacheKey = 'sadad:token:'.$env.':'.sha1($merchant.'|'.$domain.'|'.$secret);

        if ($useCache && ($token = Cache::get($cacheKey))) {
            return ['token' => $token];
        }
        try {
            $res = Http::timeout($this->config->timeout())
                ->acceptJson()
                ->withHeaders(['Origin' => 'https://'.$domain])
                ->post($this->config->apiBase($env).'/api/userbusinesses/login', [
                    'sadadId' => is_numeric($merchant) ? (int) $merchant : $merchant,
                    'secretKey' => $secret,
                    'domain' => $domain,
                ]);
            if (! $res->successful()) {
                return ['error' => "login_{$env}_{$res->status()}"];
            }
            $token = $res->json('accessToken');
            if (! is_string($token) || $token === '') {
                return ['error' => "login_{$env}_no_token"];
            }
            Cache::put($cacheKey, $token, now()->addMinutes(10));

            return ['token' => $token];
        } catch (Throwable $e) {
            return ['error' => "login_{$env}_".$e->getMessage()];
        }
    }

    /**
     * F-PAY-029 / T-081.
     *
     * @return array{ok:bool, environment?:string, errors?:list<string>}
     */
    public function detectEnvironment(?string $env = null): array
    {
        $errors = [];
        foreach ($this->environments($env) as $e) {
            $s = $this->login($e, useCache: false);
            if (isset($s['token'])) {
                return ['ok' => true, 'environment' => $e];
            }
            $errors[] = $s['error'];
        }

        return ['ok' => false, 'errors' => $errors];
    }

    /** F-PAY-030/031: lookup by reference with single-transaction fallback. */
    public function lookupTransaction(string $orderRef, ?string $transactionNumber = null, ?string $env = null): ApiResult
    {
        if (! $this->config->isConfigured()) {
            return ApiResult::unavailable('sadad_not_configured');
        }
        if (preg_match(self::SAFE_REF, $orderRef) !== 1) {
            return ApiResult::unavailable('invalid_order_ref');
        }
        $errors = [];
        foreach ($this->environments($env) as $e) {
            $session = $this->login($e);
            if (! isset($session['token'])) {
                $errors[] = $session['error'];

                continue;
            }
            try {
                $list = $this->get($e, $session['token'], '/api/transactions/listTransactions', ['website_ref_no' => $orderRef, 'skip' => 0, 'limit' => 50]);
                if (! $list->successful()) {
                    return ApiResult::unavailable("list_{$e}_{$list->status()}");
                }
                $rows = $list->json();
                if (! is_array($rows) || ! array_is_list($rows)) {
                    return ApiResult::unavailable("list_{$e}_unexpected_response");
                }
                $verdict = self::summarise($rows, $orderRef, $e === 'sandbox');
                if ($verdict->kind !== 'not_found' || ! $transactionNumber || preg_match(self::SAFE_TXN, $transactionNumber) !== 1) {
                    return $verdict;
                }
                $single = $this->get($e, $session['token'], '/api/transactions/getTransaction', ['transactionno' => $transactionNumber]);
                if ($single->status() === 404) {
                    return ApiResult::notFound();
                }
                if (! $single->successful()) {
                    return ApiResult::unavailable("single_{$e}_{$single->status()}");
                }
                $row = $single->json();

                return self::summarise(is_array($row) ? [$row] : [], $orderRef, $e === 'sandbox');
            } catch (Throwable $ex) {
                return ApiResult::unavailable("{$e}_".$ex->getMessage());
            }
        }

        return ApiResult::unavailable($errors !== [] ? implode(',', $errors) : 'no_environment');
    }

    private function get(string $env, string $token, string $path, array $query): Response
    {
        return Http::timeout($this->config->timeout())
            ->withHeaders(['Authorization' => $token, 'Accept' => 'application/json', 'Content-Type' => 'application/json'])
            ->get($this->config->apiBase($env).$path, $query);
    }

    /**
     * R-110: 3 success; 2/4/7 failed; 1/5/6 in progress; success wins; a
     * non-numeric amount is ignored; rows of other references dropped.
     *
     * @param  list<array<string,mixed>>  $rows
     */
    public static function summarise(array $rows, string $orderRef, bool $sandbox): ApiResult
    {
        $statusOf = fn (array $r) => (int) ($r['transactionstatusId'] ?? ($r['transactionstatus']['id'] ?? 0));
        $refOf = function (array $r): ?string {
            $ref = $r['website_ref_no'] ?? null;

            return is_string($ref) && trim($ref) !== '' ? trim($ref) : null;
        };
        $amountOf = function (array $r): ?float {
            $a = $r['amount'] ?? null;
            if ($a === null || $a === '' || is_array($a) || ! is_numeric($a)) {
                return null;
            }

            return (float) $a;
        };
        $mine = array_values(array_filter($rows, fn ($r) => is_array($r) && (($ref = $refOf($r)) === null || $ref === $orderRef)));

        foreach ($mine as $r) {
            if ($statusOf($r) === 3 && $amountOf($r) !== null) {
                return ApiResult::success((string) ($r['invoicenumber'] ?? ''), $amountOf($r), $refOf($r), $sandbox);
            }
        }
        foreach ($mine as $r) {
            if (in_array($statusOf($r), [1, 5, 6], true)) {
                return ApiResult::inProgress();
            }
        }
        foreach ($mine as $r) {
            if (in_array($statusOf($r), [2, 4, 7], true)) {
                return ApiResult::failed(! empty($r['invoicenumber']) ? (string) $r['invoicenumber'] : null);
            }
        }

        return ApiResult::notFound();
    }

    /**
     * F-PAY-056…058 transaction details (admin). GET first, POST fallback.
     *
     * @return array{success:bool, code?:string, error?:string, status?:int, transaction?:array<string,mixed>}
     */
    public function transactionDetails(string $transactionNumber): array
    {
        if ($this->config->merchantId() === null || $this->config->apiLoginSecret() === null || $this->config->websiteDomain() === null) {
            return ['success' => false, 'code' => 'SADAD_API_LOGIN_NOT_CONFIGURED', 'error' => __('sadad.details_not_configured')];
        }
        $cacheKey = 'sadad:txn:'.$transactionNumber;
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        foreach ($this->environments() as $env) {
            $session = $this->login($env);
            if (! isset($session['token'])) {
                continue;
            }
            try {
                $res = $this->get($env, $session['token'], '/api/transactions/getTransaction', ['transactionno' => $transactionNumber]);
                if (! $res->successful() && $res->status() !== 404) {
                    $res = Http::timeout($this->config->timeout())
                        ->withHeaders(['Authorization' => $session['token'], 'Origin' => 'https://'.$this->config->websiteDomain()])
                        ->acceptJson()
                        ->post($this->config->apiBase($env).'/api/transactions/getTransaction', ['transactionno' => $transactionNumber]);
                }
                $tx = $res->json();
                if (! $res->successful() || ! is_array($tx) || isset($tx['error'])) {
                    $msg = is_array($tx) ? (data_get($tx, 'error.message') ?: null) : null;
                    if ($res->status() === 404 && count($this->environments()) > 1 && $env === 'live') {
                        continue;
                    }

                    return ['success' => false, 'status' => $res->status() === 404 ? 404 : 502, 'error' => $msg ?: __('sadad.details_failed')];
                }
                $result = ['success' => true, 'environment' => $env, 'transaction' => self::mapTransaction($tx, $transactionNumber)];
                Cache::put($cacheKey, $result, now()->addSeconds(60));

                return $result;
            } catch (Throwable $e) {
                return ['success' => false, 'status' => 502, 'error' => $e->getMessage() ?: __('sadad.unexpected')];
            }
        }

        return ['success' => false, 'code' => 'SADAD_API_LOGIN_REJECTED', 'error' => __('sadad.details_login_rejected', ['domain' => $this->config->websiteDomain()])];
    }

    /** F-PAY-058 mapping (net with 2 decimals, brick-style cents). */
    public static function mapTransaction(array $tx, string $fallbackTxn): array
    {
        $num = fn ($v) => is_numeric($v) ? (float) $v : 0.0;
        $status = strtoupper(trim((string) data_get($tx, 'transactionstatus.name', '')));
        $status = $status === '' ? 'UNKNOWN' : $status;
        $amount = $num($tx['amount'] ?? 0);
        $commission = $num($tx['servicecharge'] ?? 0);
        $refund = $num($tx['refundcharge'] ?? 0);

        return [
            'transactionno' => (string) ($tx['invoicenumber'] ?? $fallbackTxn),
            'status' => $status,
            'statusAr' => SadadLiveStatus::label($status),
            'isRefund' => (bool) ($tx['isRefund'] ?? $tx['is_refund'] ?? in_array($status, ['REFUND', 'REFUNDED'], true)),
            'amount' => round($amount, 2),
            'commission' => round($commission, 2),
            'refundCharge' => round($refund, 2),
            'netAmount' => round(($amount * 100 - $commission * 100 - $refund * 100) / 100, 2),
            'mode' => data_get($tx, 'transactionmode.name'),
            'entity' => data_get($tx, 'transactionentity.name'),
            'transactiondate' => $tx['transactiondate'] ?? null,
            'websiteRefNo' => $tx['website_ref_no'] ?? null,
        ];
    }
}
