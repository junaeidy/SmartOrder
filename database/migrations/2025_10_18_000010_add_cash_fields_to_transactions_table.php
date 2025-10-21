<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'amount_received')) {
                $table->decimal('amount_received', 12, 2)->nullable()->after('total_amount');
            }
            if (!Schema::hasColumn('transactions', 'change_amount')) {
                $table->decimal('change_amount', 12, 2)->nullable()->after('amount_received');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'change_amount')) {
                $table->dropColumn('change_amount');
            }
            if (Schema::hasColumn('transactions', 'amount_received')) {
                $table->dropColumn('amount_received');
            }
        });
    }
};
