<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ReadOnlyTool;
use App\Models\Order;
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

/** Spec 2.2 optional tool — online payments that need a human decision (paid after expiry / after cancel). */
#[Name('list_payment_reviews')]
#[Title('Payments needing review')]
#[Description('List online (Sadad) payments flagged for manual review — for example paid after the seat hold expired or after a staff cancellation — that have not been handled yet.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ListPaymentReviews extends Tool
{
    use ReadOnlyTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->deny('transactions.view')) {
            return $denied;
        }
        $data = $request->validate(['limit' => 'nullable|integer|min:1|max:50']);
        $orders = Order::query()->with(['customer:id,name', 'event:id,title'])
            ->whereNotNull('review_required_at')->whereNull('refund_handled_at')
            ->latest('review_required_at')->limit((int) ($data['limit'] ?? 20))->get();

        if ($orders->isEmpty()) {
            return Response::text('No payments need review.');
        }
        $rows = $orders->map(fn (Order $o) => [
            'booking_reference' => $o->booking_reference,
            'payment_status' => $o->payment_status?->value,
            'payment_id' => $o->payment_id,
            'total_amount' => (float) $o->total_amount,
            'flagged_at' => self::qatar($o->review_required_at),
            'customer' => $o->customer?->name,
            'event' => $o->event?->title,
        ])->all();

        return Response::make(Response::text(collect($rows)->map(fn ($r) => "{$r['booking_reference']} — {$r['total_amount']} QAR — payment {$r['payment_status']}, flagged {$r['flagged_at']}")->implode("\n")))
            ->withStructuredContent(['reviews' => $rows]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->min(1)->max(50)->description('Maximum number of rows (default 20).'),
        ];
    }
}
