<?php

use App\Support\Schema\Checks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 4.3 Sales: customers, orders, order_items, ticket_holders,
 * ticket_checkins, scan_queue, deleted_tickets, expired_qr_codes, refunds,
 * pos_receipts, order_event_changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('email', 254)->default('');
            $table->string('phone', 32);
            $table->string('phone_e164', 20)->nullable();
            $table->string('country_code', 5)->nullable()->default('+974');
            $table->string('nationality', 60)->nullable();
            $table->string('id_number', 30)->nullable();
            $table->string('person_key', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('phone');
            $table->index('phone_e164');
            $table->index('person_key');
            $table->index('created_at');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUuid('event_id')->constrained('events')->restrictOnDelete();
            $table->string('source', 8)->default('web');
            $table->string('ticket_type', 16);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_method', 16);
            $table->string('payment_status', 16)->default('pending');
            $table->string('payment_note', 255)->nullable();
            $table->string('payment_error_reason', 500)->nullable();
            $table->string('payment_id', 100)->nullable()->unique(); // T-006
            $table->string('booking_reference', 60)->unique();
            $table->string('qr_code', 100)->nullable();
            $table->timestamp('payment_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('pos_user_id')->nullable()->constrained('pos_users')->restrictOnDelete();
            $table->foreignUuid('kiosk_device_id')->nullable()->constrained('kiosk_devices')->nullOnDelete();
            $table->boolean('is_present')->default(false);
            $table->boolean('sadad_manually_verified')->default(false);
            $table->foreignUuid('sadad_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sadad_verified_at')->nullable();
            $table->string('manual_payment_reference', 120)->nullable();
            $table->string('n8n_response_message', 500)->nullable();
            $table->timestamp('n8n_responded_at')->nullable();
            $table->string('n8n_response_status', 16)->nullable();
            $table->string('automation_status', 24)->default('none');
            $table->unsignedTinyInteger('send_attempt_count')->default(0);
            $table->timestamp('invoice_email_sent_at')->nullable();
            $table->timestamp('invoice_downloaded_at')->nullable(); // F-LIVE-015 rebuild
            $table->string('return_origin', 255)->nullable();
            $table->timestamp('last_payment_check_at')->nullable();
            $table->timestamp('review_required_at')->nullable();
            $table->timestamp('refund_handled_at')->nullable();
            $table->foreignUuid('refund_handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 20)->nullable();
            $table->string('locale', 5)->default('ar');
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->foreignUuid('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_id', 'payment_status']);
            $table->index('created_at');
            $table->index(['payment_status', 'payment_expires_at']);
            $table->index('pos_user_id');
            $table->index('sadad_manually_verified');
            $table->index('customer_id');
            $table->index(['payment_method', 'payment_status', 'n8n_response_message', 'created_at'], 'orders_dispatch_idx');
        });
        Checks::in('orders', 'source', ['web', 'pos']);
        Checks::in('orders', 'ticket_type', ['vip', 'normal', 'parking']);
        Checks::in('orders', 'payment_method', ['sadad', 'cash_pos']);
        Checks::in('orders', 'payment_status', ['pending', 'confirmed', 'cancelled']);
        Checks::add('orders', 'orders_quantity_check', 'quantity > 0');
        Checks::add('orders', 'orders_total_check', 'total_amount >= 0');

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('ticket_type_id')->constrained('ticket_types')->restrictOnDelete();
            $table->string('type', 16);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->string('price_source', 10)->default('order');
            $table->timestamps();
            $table->unique(['order_id', 'ticket_type_id']);
        });
        Checks::in('order_items', 'type', ['vip', 'normal', 'parking']);

        Schema::create('ticket_holders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('ticket_type_id')->nullable()->constrained('ticket_types')->restrictOnDelete();
            $table->string('ticket_type', 16);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->string('name', 100);
            $table->string('phone', 32);
            $table->string('phone_e164', 20)->nullable();
            $table->string('country_code', 5)->nullable()->default('+974');
            $table->string('nationality', 60)->default('');
            $table->string('id_number', 30)->nullable();
            $table->string('person_key', 40)->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('qr_code', 100)->nullable()->unique();
            $table->string('qr_image_url', 2048)->nullable();
            $table->boolean('qr_failed')->default(false); // F-QR-009 rebuild: stop re-listing permanent failures
            $table->boolean('is_present')->default(false);
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignUuid('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('checked_in_by_name', 80)->nullable();
            $table->foreignUuid('checked_in_device_id')->nullable()->constrained('kiosk_devices')->nullOnDelete();
            $table->timestamps();
            $table->index('order_id');
            $table->index('is_present');
            $table->index('checked_in_at');
            $table->index('person_key');
            $table->index('phone_e164');
            $table->index('ticket_type_id');
        });
        Checks::in('ticket_holders', 'ticket_type', ['vip', 'normal', 'parking']);

        Schema::create('ticket_checkins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_holder_id')->nullable()->constrained('ticket_holders')->cascadeOnDelete();
            $table->foreignUuid('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('action', 16);
            $table->string('reason', 40)->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label', 80)->nullable();
            $table->foreignUuid('kiosk_device_id')->nullable()->constrained('kiosk_devices')->nullOnDelete();
            $table->string('scanned_code', 100)->nullable();
            $table->string('source', 12)->default('camera');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_holder_id', 'created_at']);
            $table->index(['event_id', 'created_at']);
            $table->index('created_at');
        });
        Checks::in('ticket_checkins', 'action', ['admit', 'reset', 'rejected']);

        Schema::create('scan_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kiosk_device_id')->nullable()->constrained('kiosk_devices')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ticket_code', 100);
            $table->timestamp('scanned_at');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('result', 16)->default('pending');
            $table->string('reason', 40)->nullable();
            $table->foreignUuid('ticket_holder_id')->nullable()->constrained('ticket_holders')->nullOnDelete();
            $table->string('actor_label', 80)->nullable();
            $table->index(['result', 'received_at']);
            $table->index(['kiosk_device_id', 'scanned_at']);
        });
        Checks::in('scan_queue', 'result', ['admitted', 'already_used', 'rejected', 'pending']);

        Schema::create('deleted_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('holder_id');
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->uuid('ticket_type_id')->nullable();
            $table->string('name', 100);
            $table->string('phone', 32);
            $table->string('nationality', 60)->default('');
            $table->string('ticket_type', 16);
            $table->string('qr_code', 100)->nullable();
            $table->string('qr_image_url', 2048)->nullable();
            $table->boolean('is_present')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->uuid('checked_in_by')->nullable();
            $table->string('checked_in_by_name', 80)->nullable();
            $table->string('id_number', 30)->nullable();
            $table->string('country_code', 5)->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->unsignedSmallInteger('position')->nullable();
            $table->timestamp('holder_created_at')->nullable();
            $table->json('context');
            $table->timestamp('deleted_at')->useCurrent();
            $table->foreignUuid('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deleted_by_name', 254)->nullable();
            $table->index('deleted_at');
        });

        Schema::create('expired_qr_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('qr_code', 100)->unique();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->uuid('ticket_holder_id')->nullable();
            $table->string('reason', 40)->default('event_date_changed');
            $table->string('moved_to_event_title', 200)->nullable();
            $table->timestamp('expired_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index('order_id');
        });

        Schema::create('order_event_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('old_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignUuid('new_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('old_event_title', 200)->nullable();
            $table->timestamp('old_event_date')->nullable();
            $table->string('new_event_title', 200)->nullable();
            $table->timestamp('new_event_date')->nullable();
            $table->unsignedSmallInteger('expired_codes_count')->default(0);
            $table->unsignedSmallInteger('new_codes_count')->default(0);
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('reason', 60);
            $table->string('status', 16)->default('requested');
            $table->string('gateway', 16)->default('sadad');
            $table->string('gateway_reference', 100)->nullable();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('order_id');
        });
        Checks::in('refunds', 'status', ['requested', 'processing', 'completed', 'rejected']);

        Schema::create('pos_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->decimal('amount_qar', 10, 2)->nullable();
            $table->string('seq_number', 40)->nullable();
            $table->string('card_number_masked', 32)->nullable();
            $table->string('time', 8)->nullable();
            $table->string('auth_number', 20)->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('image_path', 500)->nullable(); // private disk "receipts"
            $table->json('ocr_raw')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('kiosk_device_id')->nullable()->constrained('kiosk_devices')->nullOnDelete();
            $table->string('ticket_type', 16)->nullable()->default('normal');
            $table->unsignedSmallInteger('num_tickets')->nullable();
            $table->unsignedSmallInteger('normal_tickets')->default(0);
            $table->unsignedSmallInteger('vip_tickets')->default(0);
            $table->unsignedSmallInteger('parking_tickets')->default(0);
            $table->timestamps();
            $table->index('created_at');
            $table->unique(['seq_number', 'auth_number', 'amount_qar'], 'pos_receipts_dedupe_unique');
        });

        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pos_user_id')->constrained('pos_users')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status', 8);
            $table->foreignUuid('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at');
            $table->timestamps();
            $table->unique(['pos_user_id', 'attendance_date']);
            $table->index('attendance_date');
        });
        Checks::in('staff_attendance', 'status', ['present', 'absent']);
    }

    public function down(): void
    {
        foreach (['staff_attendance', 'pos_receipts', 'refunds', 'order_event_changes', 'expired_qr_codes', 'deleted_tickets', 'scan_queue', 'ticket_checkins', 'ticket_holders', 'order_items', 'orders', 'customers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
