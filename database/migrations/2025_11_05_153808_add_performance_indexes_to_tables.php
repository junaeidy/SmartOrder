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
        // High Priority - Transactions table
        Schema::table('transactions', function (Blueprint $table) {
            if (!$this->indexExists('transactions', 'idx_transactions_customer_email')) {
                $table->index('customer_email', 'idx_transactions_customer_email');
            }
            if (!$this->indexExists('transactions', 'idx_transactions_status')) {
                $table->index('status', 'idx_transactions_status');
            }
            if (!$this->indexExists('transactions', 'idx_transactions_created_at')) {
                $table->index('created_at', 'idx_transactions_created_at');
            }
            if (!$this->indexExists('transactions', 'idx_transactions_payment_method')) {
                $table->index('payment_method', 'idx_transactions_payment_method');
            }
            if (!$this->indexExists('transactions', 'idx_transactions_status_created')) {
                $table->index(['status', 'created_at'], 'idx_transactions_status_created');
            }
        });

        // Medium Priority - Products table
        Schema::table('products', function (Blueprint $table) {
            if (!$this->indexExists('products', 'idx_products_closed')) {
                $table->index('closed', 'idx_products_closed');
            }
        });

        // Medium Priority - Device tokens table (if exists)
        if (Schema::hasTable('device_tokens')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                if (!$this->indexExists('device_tokens', 'idx_device_tokens_customer_active')) {
                    $table->index(['customer_id', 'revoked_at'], 'idx_device_tokens_customer_active');
                }
            });
        }

        // Discount usages already has indexes in migration

        // Low Priority - Customers table
        Schema::table('customers', function (Blueprint $table) {
            if (!$this->indexExists('customers', 'idx_customers_email')) {
                $table->index('email', 'idx_customers_email');
            }
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$databaseName, $table, $indexName]
        );
        
        return $result[0]->count > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_customer_email');
            $table->dropIndex('idx_transactions_status');
            $table->dropIndex('idx_transactions_created_at');
            $table->dropIndex('idx_transactions_payment_method');
            $table->dropIndex('idx_transactions_status_created');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_closed');
        });

        if (Schema::hasTable('device_tokens')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                $table->dropIndex('idx_device_tokens_customer_active');
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_email');
        });
    }
};
