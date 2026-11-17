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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('gateway', 32)->default('stripe');
            $table->string('gateway_subscription_id')->nullable()->index();
            $table->string('plan_code');
            $table->string('status', 32)->default('incomplete')->index();
            $table->unsignedInteger('price');
            $table->string('currency', 3)->default('USD');
            $table->string('billing_interval', 16)->default('monthly');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
