<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('tenant');
            $t->string('phone')->nullable();
            $t->string('locale')->default('id');
            $t->string('theme')->default('system');
            $t->boolean('is_active')->default(true);
            $t->boolean('must_change_password')->default(false);
            $t->timestamp('last_login_at')->nullable();
        });
        Schema::create('room_types', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->unsignedInteger('price_monthly');
            $t->json('facilities')->nullable();
            $t->timestamps();
        });
        Schema::create('rooms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_type_id')->constrained();
            $t->string('number')->unique();
            $t->unsignedInteger('floor');
            $t->string('status')->default('available');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('tenancies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('room_id')->constrained();
            $t->date('start_date');
            $t->date('end_date');
            $t->unsignedTinyInteger('due_day');
            $t->unsignedInteger('monthly_price');
            $t->unsignedInteger('deposit_amount')->default(0);
            $t->string('status');
            $t->timestamps();
        });
        Schema::create('tenant_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained();
            $t->string('full_name')->nullable();
            $t->text('nik')->nullable();
            foreach (['gender', 'birth_place', 'occupation', 'institution', 'emergency_name', 'emergency_relation', 'emergency_phone', 'ktp_path'] as $c) {
                $t->string($c)->nullable();
            }$t->date('birth_date')->nullable();
            $t->text('origin_address')->nullable();
            $t->timestamp('consent_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenancy_id')->constrained();
            $t->string('number')->unique();
            $t->string('type');
            $t->date('period_start');
            $t->date('period_end');
            $t->unsignedInteger('amount');
            $t->date('due_date');
            $t->string('status')->default('unpaid');
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->unique(['tenancy_id', 'type', 'period_start']);
            $t->index(['status', 'due_date']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained();
            $t->string('gateway');
            $t->string('order_id')->unique();
            $t->text('snap_token')->nullable();
            $t->unsignedInteger('gross_amount');
            foreach (['payment_type', 'transaction_id', 'transaction_status', 'fraud_status'] as $c) {
                $t->string($c)->nullable();
            }$t->string('status')->default('pending');
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->json('raw_notification')->nullable();
            $t->timestamps();
        });
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('subject');
            $t->string('category');
            $t->string('priority');
            $t->string('status')->default('open');
            $t->timestamp('last_message_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status']);
        });
        Schema::create('ticket_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained();
            $t->foreignId('sender_id')->constrained('users');
            $t->text('body');
            $t->json('attachments')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['ticket_id', 'created_at']);
        });
        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('author_id')->constrained('users');
            $t->string('title');
            $t->text('body');
            $t->timestamp('published_at');
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['announcements', 'ticket_messages', 'tickets', 'payments', 'invoices', 'tenant_profiles', 'tenancies', 'rooms', 'room_types'] as $table) {
            Schema::dropIfExists($table);
        }Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'phone', 'locale', 'theme', 'is_active', 'must_change_password', 'last_login_at']));
    }
};
