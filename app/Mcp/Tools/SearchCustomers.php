<?php

namespace App\Mcp\Tools;

use App\Domain\Shared\Actor;
use App\Mcp\Tools\Concerns\ReadOnlyTool;
use App\Models\Customer;
use App\Support\Text;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** F-API-005 rebuild — escaped LIKE (% and _), one row per customer, PII by permission. */
#[Name('search_customers')]
#[Title('Search customers')]
#[Description('Search customers by part of their name, phone number or email address, and see how many bookings each one has.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class SearchCustomers extends Tool
{
    use ReadOnlyTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->deny('customers.view')) {
            return $denied;
        }
        $data = $request->validate(['query' => 'required|string|min:2|max:100', 'limit' => 'required|integer|min:1|max:25']);
        $pii = Actor::current()->can('customers.view_pii');
        $term = trim(str_replace(['%', ','], ' ', $data['query']));
        if (mb_strlen($term) < 2) {
            return Response::error('query must be at least 2 characters.');
        }
        $like = self::like($term);
        $digits = preg_replace('/\D+/', '', $term);

        $customers = Customer::query()->withCount('orders')
            ->where(function ($q) use ($like, $digits, $pii) {
                $q->where('name', 'like', $like);
                if ($pii) {
                    $q->orWhere('email', 'like', $like);
                }
                if (strlen($digits) >= 4) {
                    $q->orWhere('phone', 'like', self::like($digits));
                }
            })
            ->latest()->limit((int) $data['limit'])->get();

        if ($customers->isEmpty()) {
            return Response::text('No matching customers.');
        }
        $rows = $customers->map(fn (Customer $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $pii ? $c->phone : Text::maskPhone($c->phone),
            'email' => $pii ? $c->email : null,
            'nationality' => $c->nationality ?: null,
            'bookings' => (int) $c->orders_count,
        ])->all();

        return Response::make(Response::text(collect($rows)->map(fn ($r) => "{$r['name']} — {$r['phone']} ({$r['bookings']} bookings)")->implode("\n")))
            ->withStructuredContent(['customers' => $rows]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->min(2)->description('Part of the name, phone number (4+ digits) or e-mail address.')->required(),
            'limit' => $schema->integer()->min(1)->max(25)->description('Maximum number of customers to return (1-25).')->required(),
        ];
    }
}
