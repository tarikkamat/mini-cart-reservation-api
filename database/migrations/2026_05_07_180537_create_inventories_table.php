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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('version')->default(0);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE inventories ADD CONSTRAINT quantity_non_negative CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT reserved_non_negative CHECK (reserved_quantity >= 0)');
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT reserved_lte_quantity CHECK (reserved_quantity <= quantity)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
