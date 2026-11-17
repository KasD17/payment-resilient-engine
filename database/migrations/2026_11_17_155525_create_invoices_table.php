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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('tax_amount')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('paid')->index();
            $table->string('pdf_path')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
