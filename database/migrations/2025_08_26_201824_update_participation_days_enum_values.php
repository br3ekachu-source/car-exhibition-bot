<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Временно меняем на строку
        Schema::table('participants', function (Blueprint $table) {
            $table->string('participation_days_temp')->nullable();
        });

        // 2. Копируем данные во временную колонку
        DB::table('participants')->update([
            'participation_days_temp' => DB::raw('participation_days')
        ]);

        // 3. Удаляем старую колонку
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('participation_days');
        });

        // 4. Создаем новую колонку с правильными значениями
        Schema::table('participants', function (Blueprint $table) {
            $table->enum('participation_days', ['20', '21', 'both'])->nullable();
        });

        // 5. Переносим данные с преобразованием значений
        DB::table('participants')->update([
            'participation_days' => DB::raw('
                CASE 
                    WHEN participation_days_temp = "9" THEN "20"
                    WHEN participation_days_temp = "10" THEN "21" 
                    ELSE participation_days_temp
                END
            ')
        ]);

        // 6. Удаляем временную колонку
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('participation_days_temp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Временно меняем на строку для отката
        Schema::table('participants', function (Blueprint $table) {
            $table->string('participation_days_temp')->nullable();
        });

        // 2. Копируем данные во временную колонку
        DB::table('participants')->update([
            'participation_days_temp' => DB::raw('participation_days')
        ]);

        // 3. Удаляем новую колонку
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('participation_days');
        });

        // 4. Восстанавливаем старую колонку
        Schema::table('participants', function (Blueprint $table) {
            $table->enum('participation_days', ['9', '10', 'both'])->nullable();
        });

        // 5. Переносим данные с обратным преобразованием
        DB::table('participants')->update([
            'participation_days' => DB::raw('
                CASE 
                    WHEN participation_days_temp = "20" THEN "9"
                    WHEN participation_days_temp = "21" THEN "10" 
                    ELSE participation_days_temp
                END
            ')
        ]);

        // 6. Удаляем временную колонку
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('participation_days_temp');
        });
    }
};