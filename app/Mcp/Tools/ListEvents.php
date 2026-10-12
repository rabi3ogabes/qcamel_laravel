<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ReadOnlyTool;
use App\Models\Event;
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

/** F-API-002 */
#[Name('list_events')]
#[Title('List events')]
#[Description('List the ticketing events (title, date, location, capacity status), newest first.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ListEvents extends Tool
{
    use ReadOnlyTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->deny('events.view')) {
            return $denied;
        }
        $data = $request->validate(['include_archived' => 'required|boolean', 'limit' => 'required|integer|min:1|max:50']);

        $events = Event::query()->with('ticketTypes:id,event_id,type,available_quantity,sold_quantity')
            ->when(! $data['include_archived'], fn ($q) => $q->where('is_archived', false))
            ->orderByDesc('event_date')->limit((int) $data['limit'])->get();

        if ($events->isEmpty()) {
            return Response::text('No events found.');
        }
        $rows = $events->map(fn (Event $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'event_date' => self::qatar($e->event_date),
            'end_date' => self::qatar($e->end_date),
            'location' => $e->location,
            'is_active' => (bool) $e->is_active,
            'is_archived' => (bool) $e->is_archived,
            'capacity' => (int) $e->ticketTypes->sum('available_quantity'),
            'held' => (int) $e->ticketTypes->sum('sold_quantity'),
        ])->all();

        return Response::make(Response::text(collect($rows)->map(fn ($r) => "{$r['title']} — ".($r['event_date'] ?? '?')." ({$r['id']}) · {$r['held']}/{$r['capacity']} held")->implode("\n")))
            ->withStructuredContent(['events' => $rows]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'include_archived' => $schema->boolean()->description('Include archived (past season) events as well.')->required(),
            'limit' => $schema->integer()->min(1)->max(50)->description('Maximum number of events to return (1-50).')->required(),
        ];
    }
}
