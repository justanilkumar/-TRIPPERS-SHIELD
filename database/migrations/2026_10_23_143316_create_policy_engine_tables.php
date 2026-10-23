<?php

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
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id', 64)->index();
            $table->string('client_request_id', 128)->index();
            $table->string('customer_name', 255);
            $table->date('date_of_birth');
            $table->string('destination', 32);
            $table->date('trip_start');
            $table->date('trip_end');
            $table->unsignedInteger('travel_days');
            $table->string('plan_code', 32);
            $table->decimal('sum_insured', 12, 2);
            
            // Premium calculation snapshot
            $table->unsignedSmallInteger('age_at_trip_start');
            $table->decimal('base_daily_rate', 10, 2);
            $table->decimal('age_multiplier', 6, 2);
            $table->decimal('destination_multiplier', 6, 2);
            $table->decimal('coverage_multiplier', 6, 2);
            $table->decimal('premium_amount', 10, 2);
            $table->json('pricing_breakdown')->nullable();

            // Workflow status & provider tracking
            $table->string('status', 32)->default('RECEIVED')->index();
            $table->string('policy_number', 64)->nullable()->unique();
            $table->string('provider_reference', 64)->nullable()->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            // Strict composite uniqueness: prevents duplicate logical intake requests
            $table->unique(['agent_id', 'client_request_id'], 'uq_agent_client_request');
        });

        Schema::create('issuance_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->constrained('policy_requests')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('scenario', 64)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('status', 32); // SUCCESS, FAILED, TIMED_OUT, PENDING
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['policy_request_id', 'attempt_number']);
        });

        Schema::create('provider_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 128)->unique(); // Enforces callback idempotency
            $table->string('provider_reference', 64)->index();
            $table->foreignId('policy_request_id')->nullable()->constrained('policy_requests')->nullOnDelete();
            $table->string('event_type', 64);
            $table->string('policy_number', 64)->nullable();
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->text('processing_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('policy_state_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->constrained('policy_requests')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['policy_request_id', 'created_at']);
        });

        Schema::create('mock_insurer_policies', function (Blueprint $table) {
            $table->id();
            $table->string('provider_reference', 64)->unique();
            $table->string('client_request_id', 128)->index();
            $table->string('agent_id', 64);
            $table->string('customer_name', 255);
            $table->string('policy_number', 64)->unique();
            $table->string('status', 32)->default('ISSUED');
            $table->string('scenario', 64)->nullable();
            $table->decimal('premium', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_insurer_policies');
        Schema::dropIfExists('policy_state_history');
        Schema::dropIfExists('provider_events');
        Schema::dropIfExists('issuance_attempts');
        Schema::dropIfExists('policy_requests');
    }
};
