<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: This migration encrypts existing customer phone numbers.
     * Laravel will automatically encrypt new phone numbers going forward
     * because of the 'encrypted' cast in Customer model.
     */
    public function up(): void
    {
        // Get all customers with non-encrypted phone numbers
        $customers = DB::table('customers')->whereNotNull('phone')->get();
        
        foreach ($customers as $customer) {
            // Check if phone is already encrypted (contains base64 characters)
            // Encrypted data in Laravel starts with "eyJpdiI6..." (base64 of JSON)
            if (!str_starts_with($customer->phone, 'eyJpdiI6')) {
                try {
                    // Encrypt the phone number
                    $encryptedPhone = Crypt::encryptString($customer->phone);
                    
                    // Update the record
                    DB::table('customers')
                        ->where('id', $customer->id)
                        ->update(['phone' => $encryptedPhone]);
                        
                    echo "Encrypted phone for customer ID: {$customer->id}\n";
                } catch (\Exception $e) {
                    echo "Failed to encrypt phone for customer ID: {$customer->id} - {$e->getMessage()}\n";
                }
            }
        }
        
        echo "Phone encryption completed!\n";
    }

    /**
     * Reverse the migrations.
     *
     * WARNING: This will decrypt all phone numbers back to plain text.
     * Only run this if you need to rollback the encryption.
     */
    public function down(): void
    {
        $customers = DB::table('customers')->whereNotNull('phone')->get();
        
        foreach ($customers as $customer) {
            // Check if phone is encrypted
            if (str_starts_with($customer->phone, 'eyJpdiI6')) {
                try {
                    // Decrypt the phone number
                    $decryptedPhone = Crypt::decryptString($customer->phone);
                    
                    // Update the record
                    DB::table('customers')
                        ->where('id', $customer->id)
                        ->update(['phone' => $decryptedPhone]);
                        
                    echo "Decrypted phone for customer ID: {$customer->id}\n";
                } catch (\Exception $e) {
                    echo "Failed to decrypt phone for customer ID: {$customer->id} - {$e->getMessage()}\n";
                }
            }
        }
        
        echo "Phone decryption completed!\n";
    }
};
