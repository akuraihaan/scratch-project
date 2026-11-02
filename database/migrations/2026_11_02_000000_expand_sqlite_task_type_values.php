<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('type', [
                'scratch',
                'drag_and_drop',
                'decomposition',
                'classification',
                'simulation',
                'multiple_choice',
            ])->default('scratch')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('type', ['scratch', 'drag_and_drop', 'decomposition'])->default('scratch')->change();
        });
    }
};
