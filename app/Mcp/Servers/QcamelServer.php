<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\EventSalesSummary;
use App\Mcp\Tools\FindBooking;
use App\Mcp\Tools\ListEvents;
use App\Mcp\Tools\ListPaymentReviews;
use App\Mcp\Tools\SearchCustomers;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/** F-API-001 — read-only MCP server for AI assistants (POST /mcp, Sanctum token with mcp:read). */
#[Name('qcamelmc')]
#[Version('1.0.0')]
#[Instructions('Read-only tools for the qcamelmc ticketing system. Use `list_events` to find an event id, `event_sales_summary` for sales, revenue and gate attendance of that event, `find_booking` to look up a booking by its reference, `search_customers` to find a customer by name, phone or email, and `list_payment_reviews` for online payments that need a human decision. All data is scoped to the permissions of the account that owns the token. Amounts are in QAR and times in Asia/Qatar.')]
class QcamelServer extends Server
{
    protected array $tools = [
        ListEvents::class,
        EventSalesSummary::class,
        FindBooking::class,
        SearchCustomers::class,
        ListPaymentReviews::class,
    ];
}
