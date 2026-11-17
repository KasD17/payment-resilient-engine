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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('gateway', 32)->default('stripe');
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->unsignedInteger('amount'); // In cents (e.g. 1000 = $10.00)
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('created')->index();
            $table->string('payment_method_type', 32)->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
