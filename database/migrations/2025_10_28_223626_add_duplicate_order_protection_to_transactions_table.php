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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('order_hash', 64)->nullable()->after('customer_notes')->index();
            $table->timestamp('last_attempt_at')->nullable()->after('order_hash');
            $table->string('idempotency_key', 64)->nullable()->after('last_attempt_at')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['order_hash', 'last_attempt_at', 'idempotency_key']);
        });
    }
};
