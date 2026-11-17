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
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hall_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row');
            $table->unsignedInteger('col');
            $table->string('seat_number');
            $table->string('section')->default('center');
            $table->string('color', 20)->nullable();
            $table->unsignedBigInteger('price')->default(90);
            $table->boolean('is_active')->default(true);
            $table->foreignId('reserved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hall_id', 'row', 'col']);
            $table->unique(['hall_id', 'seat_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
