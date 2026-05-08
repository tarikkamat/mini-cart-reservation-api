<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->integer('quantity_delta');
            $table->integer('quantity_after');
            $table->integer('reserved_after');
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            $table->index(['reservation_id']);
        });

        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT type_valid CHECK (type IN ('reserved','released','expired','committed','adjusted'))");
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT quantity_after_non_negative CHECK (quantity_after >= 0)');
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT reserved_after_non_negative CHECK (reserved_after >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
