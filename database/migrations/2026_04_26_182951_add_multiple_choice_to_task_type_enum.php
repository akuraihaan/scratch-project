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
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE tasks MODIFY COLUMN type ENUM('scratch', 'drag_and_drop', 'decomposition', 'classification', 'simulation', 'multiple_choice') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE tasks MODIFY COLUMN type ENUM('scratch', 'drag_and_drop', 'decomposition', 'classification', 'simulation') NOT NULL");
    }
};
