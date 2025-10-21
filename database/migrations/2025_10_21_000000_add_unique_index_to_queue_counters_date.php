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
        Schema::table('queue_counters', function (Blueprint $table) {
            // Ensure only one row per date to prevent duplicates under concurrency
            $table->unique('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queue_counters', function (Blueprint $table) {
            $table->dropUnique('queue_counters_date_unique');
        });
    }
};
