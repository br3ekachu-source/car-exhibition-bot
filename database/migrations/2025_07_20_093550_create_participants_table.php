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
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_id')->unique(); // Добавлено это поле
            $table->string('name')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('phone')->nullable();
            $table->string('car_model')->nullable();
            $table->string('license_plate')->nullable();
            $table->enum('participation_days', ['9', '10', 'both'])->nullable();
            $table->string('current_step')->nullable();
            $table->string('status')->default('pending');
            $table->text('moderator_comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
