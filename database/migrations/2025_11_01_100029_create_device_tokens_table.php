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
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('device_hash', 255)->comment('SHA256 hash of device_id + salt');
            $table->string('device_name')->nullable()->comment('Device model/name for display');
            $table->string('device_type')->nullable()->comment('android/ios');
            $table->text('access_token')->nullable()->comment('Sanctum token (optional reference)');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            
            // Unique constraint: one customer can only have one active token per device
            $table->unique(['customer_id', 'device_hash']);
            
            // Indexes for performance
            $table->index('customer_id');
            $table->index('device_hash');
            $table->index('revoked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
