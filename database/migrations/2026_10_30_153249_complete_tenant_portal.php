<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('avatar_path')->nullable();
            $t->boolean('email_invoice_notifications')->default(true);
            $t->boolean('email_ticket_notifications')->default(true);
        });
        Schema::table('payments', function (Blueprint $t) {
            $t->string('proof_path')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('review_note', 500)->nullable();
            $t->boolean('is_simulation')->default(false);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('changed_fields');
            $t->timestamp('created_at');
        });
        Schema::create('notification_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('event_key');
            $t->unique(['user_id', 'event_key']);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('audit_logs');
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['proof_path', 'submitted_at', 'reviewed_at', 'review_note', 'is_simulation']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['avatar_path', 'email_invoice_notifications', 'email_ticket_notifications']));
    }
};
