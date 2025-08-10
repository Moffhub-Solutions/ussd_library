<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ussd_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->index();
            $table->string('session_id')->unique();
            $table->longText('session_data'); // JSON data
            $table->string('current_menu')->nullable()->index();
            $table->enum('status', ['new', 'active', 'grace_period', 'recovered', 'expired'])->default('new')->index();
            $table->integer('step')->default(0);
            $table->integer('access_count')->default(0);
            $table->timestamp('last_access_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            // Indexes for performance
            $table->index(['phone_number', 'status']);
            $table->index(['expires_at', 'status']);
            $table->index('created_at');
        });

        // Create session analytics table for tracking
        Schema::create('ussd_session_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->index();
            $table->string('session_id');
            $table->string('event_type'); // session_start, session_end, menu_interaction, etc.
            $table->string('menu_name')->nullable();
            $table->string('action')->nullable();
            $table->json('metadata')->nullable(); // Additional event data
            $table->timestamp('event_timestamp')->index();
            $table->timestamps();

            // Indexes for analytics queries
            $table->index(['phone_number', 'event_timestamp']);
            $table->index(['event_type', 'event_timestamp']);
            $table->index(['menu_name', 'event_timestamp']);
        });

        // Create session recovery logs table
        Schema::create('ussd_session_recovery_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->index();
            $table->string('old_session_id')->nullable();
            $table->string('new_session_id');
            $table->timestamp('attempted_at');
            $table->string('recovery_type'); // grace_period, intelligent_recovery, context_preservation
            $table->json('recovery_context')->nullable();
            $table->boolean('recovery_successful')->default(false);
            $table->text('recovery_notes')->nullable();
            $table->timestamps();

            $table->index(['phone_number', 'created_at']);
            $table->index('recovery_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ussd_session_recovery_logs');
        Schema::dropIfExists('ussd_session_analytics');
        Schema::dropIfExists('ussd_sessions');
    }
};
