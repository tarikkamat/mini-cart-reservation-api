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
        Schema::create('reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('customer_email')->index();
            $table->string('status', 20);
            $table->decimal('subtotal', 12, 2);
            $table->char('currency', 3);
            $table->timestamp('expires_at')->index();
            $table->timestamp('released_at')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });

        DB::statement("ALTER TABLE reservations ADD CONSTRAINT status_valid CHECK (status IN ('active','released','expired','committed'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
