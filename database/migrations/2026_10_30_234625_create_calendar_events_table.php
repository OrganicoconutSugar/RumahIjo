<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('category', 20);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('planned');
            $table->timestamps();
            $table->index(['user_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
