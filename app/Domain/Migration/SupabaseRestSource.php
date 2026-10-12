<?php

namespace App\Domain\Migration;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Legacy source over the Supabase HTTP APIs (secret / service-role key), for
 * when no direct Postgres connection is available. Exposes the same
 * `table($name)->get()` shape the importer reads from a database connection.
 *
 * - `public` tables: PostgREST `/rest/v1/{table}`, paged 1 000 rows at a time.
 * - `auth.users`: Auth admin API `/auth/v1/admin/users` (no password hashes —
 *   every imported account must reset its password).
 */
class SupabaseRestSource
{
    private const PAGE = 1000;

    public function __construct(private readonly string $url, private readonly string $secretKey)
    {
        if (! preg_match('#^https://#', $url) || $secretKey === '') {
            throw new RuntimeException('Set LEGACY_SUPABASE_URL (https) and LEGACY_SUPABASE_SECRET_KEY.');
        }
    }

    public function table(string $name): object
    {
        $source = $this;

        return new class($source, $name)
        {
            public function __construct(private readonly SupabaseRestSource $source, private readonly string $name) {}

            /** @return Collection<int, object> */
            public function get(): Collection
            {
                return $this->name === 'auth.users' ? $this->source->authUsers() : $this->source->rows($this->name);
            }
        };
    }

    /** @return Collection<int, object> */
    public function rows(string $table): Collection
    {
        /** @var list<object> $all */
        $all = [];
        for ($offset = 0; ; $offset += self::PAGE) {
            $res = $this->http()->get(rtrim($this->url, '/').'/rest/v1/'.rawurlencode($table), [
                'select' => '*', 'order' => 'id.asc', 'limit' => self::PAGE, 'offset' => $offset,
            ]);
            if (! $res->successful()) {
                throw new RuntimeException("Supabase REST {$table}: HTTP {$res->status()} ".mb_substr((string) $res->body(), 0, 200));
            }
            $page = (array) $res->json();
            foreach ($page as $row) {
                $all[] = (object) $row;
            }
            if (count($page) < self::PAGE) {
                break;
            }
        }

        return collect($all);
    }

    /** @return Collection<int, object> */
    public function authUsers(): Collection
    {
        /** @var list<object> $all */
        $all = [];
        for ($page = 1; ; $page++) {
            $res = $this->http()->get(rtrim($this->url, '/').'/auth/v1/admin/users', ['page' => $page, 'per_page' => self::PAGE]);
            if (! $res->successful()) {
                throw new RuntimeException("Supabase Auth users: HTTP {$res->status()}");
            }
            $users = (array) ($res->json('users') ?? []);
            foreach ($users as $u) {
                $all[] = (object) [
                    'id' => $u['id'] ?? null,
                    'email' => $u['email'] ?? null,
                    'encrypted_password' => null, // not exposed by the API
                    'email_confirmed_at' => $u['email_confirmed_at'] ?? null,
                    'raw_user_meta_data' => $u['user_metadata'] ?? null,
                    'created_at' => $u['created_at'] ?? null,
                ];
            }
            if (count($users) < self::PAGE) {
                break;
            }
        }

        return collect($all);
    }

    private function http(): PendingRequest
    {
        $headers = ['apikey' => $this->secretKey, 'Accept' => 'application/json'];
        // Legacy service-role keys are JWTs and also go in Authorization; new sb_secret_ keys use apikey only.
        if (! str_starts_with($this->secretKey, 'sb_')) {
            $headers['Authorization'] = 'Bearer '.$this->secretKey;
        }

        return Http::withHeaders($headers)->timeout(60)->retry(3, 1000, throw: false);
    }
}
