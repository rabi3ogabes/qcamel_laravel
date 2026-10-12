<?php

use App\Support\Schema\Checks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 4.4 payments, messaging and audit + analytics + site assets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('booking_reference', 60)->nullable();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('source', 10);
            $table->json('payload')->nullable();
            $table->boolean('checksum_valid')->nullable();
            $table->string('api_result', 16)->nullable();
            $table->string('api_error', 255)->nullable();      // NEW
            $table->string('decision', 10)->nullable();
            $table->string('decision_reason', 60)->nullable(); // NEW
            $table->string('outcome', 40)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['booking_reference', 'created_at']);
            $table->index(['decision', 'created_at']);
        });
        Checks::in('payment_events', 'source', ['callback', 'webhook', 'poll', 'admin', 'reconcile']);

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('txn_date');
            $table->string('signature_hash', 64);
            $table->string('return_base', 255)->nullable();
            $table->string('client_ip', 64)->nullable();
            $table->string('kind', 12)->default('create'); // create | restart
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('sadad_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transaction_number', 100)->unique();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('website_ref_no', 60)->nullable();
            $table->string('status', 20)->nullable();
            $table->boolean('is_refund')->default(false);
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('commission', 10, 2)->nullable();
            $table->decimal('refund_charge', 10, 2)->nullable();
            $table->decimal('net_amount', 10, 2)->nullable();
            $table->string('mode', 60)->nullable();
            $table->string('entity', 60)->nullable();
            $table->string('transaction_date', 60)->nullable();
            $table->string('environment', 8)->nullable();
            $table->string('last_error', 255)->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('order_id');
        });

        Schema::create('payment_errors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('booking_reference', 60)->nullable();
            $table->foreignUuid('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('customer_name', 100)->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('payment_id', 100)->nullable();
            $table->string('error_source', 8)->default('site');
            $table->string('error_code', 40)->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->json('raw');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolved_note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
            $table->index('event_id');
            $table->index(['error_source', 'created_at']);
        });
        Checks::in('payment_errors', 'error_source', ['sadad', 'bank', 'site']);

        Schema::create('email_delivery_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->string('booking_reference', 60)->nullable();
            $table->string('recipient', 254)->nullable();
            $table->string('recipient_normalized', 254)->nullable(); // lower(recipient), set in the model (portable)
            $table->string('template', 40)->default('booking-invoice');
            $table->string('provider_message_id', 191)->nullable();
            $table->string('provider_event_id', 191)->nullable();
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('detail', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
            $table->index('recipient_normalized');
            $table->unique(['provider_message_id', 'status']);
            $table->unique('provider_event_id');
        });

        // NEW (F-NTF-001 rebuild): idempotent sends + suppression list.
        Schema::create('mail_sends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 191)->unique();
            $table->string('template', 40);
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->string('recipient', 254)->nullable();
            $table->string('provider_message_id', 191)->nullable()->index();
            $table->string('status', 16)->default('queued');
            $table->timestamps();
        });

        Schema::create('mail_suppressions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 254)->unique();
            $table->string('reason', 30);
            $table->timestamps();
        });

        Schema::create('automation_dispatches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('action', 30);
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('target_host', 191)->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->string('response_excerpt', 500)->nullable();
            $table->foreignUuid('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('active_visitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_id', 64)->unique();
            $table->string('ip_address', 64)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('current_page', 255)->default('/');
            $table->string('referrer', 500)->nullable();
            $table->string('traffic_source', 20)->default('direct');
            $table->string('device_type', 8)->default('desktop');
            $table->string('browser', 40)->nullable();
            $table->string('os', 40)->nullable();
            $table->boolean('is_new_visitor')->default(true);
            $table->string('user_agent', 300)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->index('last_seen_at');
        });

        Schema::create('page_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_id', 64);
            $table->string('page_path', 255);
            $table->string('country', 80)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('device_type', 8)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('os', 40)->nullable();
            $table->string('traffic_source', 20)->nullable();
            $table->boolean('is_new_visitor')->default(true);
            $table->string('ip_address', 64)->nullable();
            $table->timestamp('viewed_at');
            $table->date('viewed_on_qatar');
            $table->index('page_path');
            $table->index('viewed_at');
            $table->index('country');
            $table->index('session_id');
            $table->index('viewed_on_qatar');
        });

        // Replaces medialibrary collections for branding images (D-100).
        Schema::create('site_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 40)->unique(); // logo | hero | before_footer | header_bg | og_image
            $table->string('disk', 20)->default('branding');
            $table->string('path', 500);
            $table->string('mime', 60)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['site_assets', 'page_views', 'active_visitors', 'automation_dispatches', 'mail_suppressions', 'mail_sends', 'email_delivery_events', 'payment_errors', 'sadad_transactions', 'payment_attempts', 'payment_events'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
