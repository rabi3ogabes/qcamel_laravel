<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 4.1 spatie/laravel-permission tables with UUID morph keys
 * (model_morph_key = model_uuid), Sanctum tokens (uuidMorphs),
 * database notifications and the spatie settings table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->uuid('model_uuid');
            $table->index(['model_uuid', 'model_type'], 'model_has_permissions_model_uuid_model_type_index');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_id', 'model_uuid', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->uuid('model_uuid');
            $table->index(['model_uuid', 'model_type'], 'model_has_roles_model_uuid_model_type_index');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_uuid', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['group', 'name']);
        });

        // Part 4.4 activity_log (spatie v5 columns + ops columns, UUID morphs).
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableUuidMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableUuidMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('actor_type', 10)->nullable();
            $table->string('actor_identifier', 160)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->timestamps();
            $table->index('created_at');
            $table->index('actor_identifier');
        });
    }

    public function down(): void
    {
        foreach (['activity_log', 'settings', 'notifications', 'personal_access_tokens', 'role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
