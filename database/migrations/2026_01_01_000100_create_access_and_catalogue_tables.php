<?php

use App\Support\Schema\Checks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 4.1 (login_attempts, kiosk_devices) and 4.2 (events, ticket_types,
 * popup_banners) + pos_users (4.3, needed early for FKs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('identifier', 160);
            $table->string('kind', 16)->default('account');
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['identifier', 'attempted_at']);
            $table->index('attempted_at');
        });
        Checks::in('login_attempts', 'kind', ['account', 'passcode']);

        Schema::create('pos_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->string('icon', 16)->nullable()->default('⭐');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('pos_user_id')->references('id')->on('pos_users')->nullOnDelete();
        });

        Schema::create('kiosk_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->string('kind', 16)->default('scanner');
            $table->string('token_hash', 64)->unique();
            $table->foreignUuid('pos_user_id')->nullable()->constrained('pos_users')->nullOnDelete();
            $table->string('session_id', 191)->nullable()->index();
            $table->string('user_agent', 300)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Checks::in('kiosk_devices', 'kind', ['scanner', 'pos', 'board']);

        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestamp('event_date');
            $table->timestamp('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location', 200);
            $table->string('image_url', 2048)->nullable();
            $table->string('image_path', 500)->nullable(); // uploaded cover on the public disk
            $table->string('video_url', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->integer('display_order')->default(0);
            $table->time('sales_cutoff_time')->default('18:00:00');   // R-004
            $table->time('checkin_cutoff_time')->default('18:00:00'); // R-004
            // A10 per-event limits (null = global default from config/settings)
            $table->unsignedTinyInteger('max_admission_per_order')->nullable();
            $table->unsignedTinyInteger('max_parking_per_order')->nullable();
            $table->unsignedTinyInteger('per_person_cap')->nullable();
            $table->decimal('min_online_amount', 10, 2)->nullable();
            $table->unsignedSmallInteger('hold_minutes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('display_order');
            $table->index('is_archived');
            $table->index(['is_active', 'is_archived']);
            $table->index('event_date');
        });
        Checks::add('events', 'events_times_check', 'end_time IS NULL OR start_time IS NULL OR end_time > start_time');

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->restrictOnDelete();
            $table->string('type', 16);
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('available_quantity'); // total capacity (R-045)
            $table->unsignedInteger('sold_quantity')->default(0); // held seats (pending + confirmed)
            $table->string('description', 500)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'type']);
        });
        Checks::in('ticket_types', 'type', ['vip', 'normal', 'parking']);
        Checks::add('ticket_types', 'ticket_types_price_check', 'price >= 0');

        Schema::create('popup_banners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->text('message');
            $table->string('image_url', 2048)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('starts_at')->nullable(); // C4 announcement window
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popup_banners');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('events');
        Schema::dropIfExists('kiosk_devices');
        Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['pos_user_id']));
        Schema::dropIfExists('pos_users');
        Schema::dropIfExists('login_attempts');
    }
};
