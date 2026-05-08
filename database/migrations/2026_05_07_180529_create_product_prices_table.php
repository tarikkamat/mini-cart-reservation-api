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
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3)->default('TRY');
            $table->decimal('amount', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_from')->useCurrent();
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'is_active']);
        });

        DB::statement('ALTER TABLE product_prices ADD CONSTRAINT amount_non_negative CHECK (amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
