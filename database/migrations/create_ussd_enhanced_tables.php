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
        Schema::create('ussd_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp');
            $table->string('action');
            $table->string('category')->index();
            $table->string('phone_number')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->string('ip_address')->nullable();
            $table->json('details')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('severity', ['low', 'medium', 'high'])->default('low')->index();
            $table->timestamps();

            // Indexes for performance
            $table->index(['timestamp', 'category']);
            $table->index(['phone_number', 'timestamp']);
            $table->index(['action', 'timestamp']);
        });

        Schema::create('ussd_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index();
            $table->string('phone_number')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->json('data');
            $table->timestamp('timestamp')->index();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['event_type', 'timestamp']);
            $table->index(['phone_number', 'timestamp']);
            $table->index(['session_id', 'timestamp']);
        });

        Schema::create('ussd_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('action')->index();
            $table->string('menu_name')->nullable()->index();
            $table->integer('duration_ms');
            $table->integer('memory_usage')->nullable();
            $table->string('phone_number')->nullable()->index();
            $table->string('session_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('timestamp')->index();
            $table->timestamps();

            // Performance analysis indexes
            $table->index(['action', 'timestamp']);
            $table->index(['menu_name', 'timestamp']);
            $table->index(['duration_ms', 'timestamp']);
        });

        Schema::create('ussd_user_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('phone_number')->index();
            $table->string('current_menu')->nullable();
            $table->json('session_data')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_activity');
            $table->timestamp('ended_at')->nullable();
            $table->integer('total_interactions')->default(0);
            $table->json('user_journey')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamps();

            // Session analysis indexes
            $table->index(['phone_number', 'started_at']);
            $table->index(['last_activity']);
            $table->index(['completed', 'ended_at']);
        });

        Schema::create('ussd_rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->index();
            $table->string('action')->default('request');
            $table->json('request_timestamps');
            $table->timestamp('blocked_until')->nullable();
            $table->integer('violation_count')->default(0);
            $table->timestamp('last_violation')->nullable();
            $table->timestamps();

            // Rate limiting indexes
            $table->unique(['phone_number', 'action']);
            $table->index(['blocked_until']);
            $table->index(['last_violation']);
        });

        Schema::create('ussd_security_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index();
            $table->string('phone_number')->nullable()->index();
            $table->string('session_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->index();
            $table->json('event_data');
            $table->string('status')->default('open'); // open, investigating, resolved
            $table->timestamp('detected_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            // Security monitoring indexes
            $table->index(['event_type', 'detected_at']);
            $table->index(['severity', 'status']);
            $table->index(['phone_number', 'detected_at']);
        });

        Schema::create('ussd_menu_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('menu_name')->index();
            $table->string('option_selected')->nullable();
            $table->integer('access_count')->default(1);
            $table->integer('completion_count')->default(0);
            $table->integer('drop_off_count')->default(0);
            $table->decimal('avg_time_spent', 8, 2)->default(0);
            $table->json('user_segments')->nullable();
            $table->date('date')->index();
            $table->timestamps();

            // Menu analytics indexes
            $table->unique(['menu_name', 'option_selected', 'date']);
            $table->index(['menu_name', 'date']);
            $table->index(['completion_count', 'date']);
        });

        Schema::create('ussd_business_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_name')->index();
            $table->string('metric_category')->index();
            $table->decimal('metric_value', 15, 2);
            $table->string('phone_number')->nullable()->index();
            $table->string('session_id')->nullable();
            $table->json('dimensions')->nullable(); // Additional context like location, time_of_day, etc.
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            // Business intelligence indexes
            $table->index(['metric_name', 'recorded_at']);
            $table->index(['metric_category', 'recorded_at']);
            $table->index(['metric_value', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ussd_business_metrics');
        Schema::dropIfExists('ussd_menu_statistics');
        Schema::dropIfExists('ussd_security_events');
        Schema::dropIfExists('ussd_rate_limits');
        Schema::dropIfExists('ussd_user_sessions');
        Schema::dropIfExists('ussd_performance_metrics');
        Schema::dropIfExists('ussd_analytics');
        Schema::dropIfExists('ussd_audit_logs');
    }
};
