<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DeviceTokenService
{
    /**
     * Salt for device hash (should be stored in .env in production)
     */
    private const DEVICE_SALT = 'smartorder_device_salt_2025';

    /**
     * Generate device hash from device ID.
     * 
     * @param string $deviceId Raw device ID from client
     * @return string SHA256 hash
     */
    public function generateDeviceHash(string $deviceId): string
    {
        $salt = config('app.device_salt', self::DEVICE_SALT);
        return hash('sha256', $deviceId . $salt);
    }

    /**
     * Register or update device token for a customer.
     * 
     * @param Customer $customer
     * @param string $deviceId Raw device ID
     * @param string|null $deviceName Device model/name
     * @param string|null $deviceType android/ios
     * @param string|null $accessToken Sanctum token reference
     * @return DeviceToken
     */
    public function registerDevice(
        Customer $customer,
        string $deviceId,
        ?string $deviceName = null,
        ?string $deviceType = null,
        ?string $accessToken = null
    ): DeviceToken {
        $deviceHash = $this->generateDeviceHash($deviceId);

        // Find or create device token
        $deviceToken = DeviceToken::firstOrNew([
            'customer_id' => $customer->id,
            'device_hash' => $deviceHash,
        ]);

        // Update or set fields
        $deviceToken->device_name = $deviceName ?? $deviceToken->device_name;
        $deviceToken->device_type = $deviceType ?? $deviceToken->device_type;
        $deviceToken->access_token = $accessToken;
        $deviceToken->last_used_at = now();
        $deviceToken->revoked_at = null; // Reset revoke status if re-login
        $deviceToken->save();

        Log::info('Device registered/updated', [
            'customer_id' => $customer->id,
            'device_hash' => substr($deviceHash, 0, 16) . '...',
            'device_name' => $deviceName,
        ]);

        return $deviceToken;
    }

    /**
     * Revoke all other devices for a customer except the current one.
     * 
     * @param Customer $customer
     * @param string $currentDeviceId Device ID to keep active
     * @return int Number of devices revoked
     */
    public function revokeOtherDevices(Customer $customer, string $currentDeviceId): int
    {
        $currentDeviceHash = $this->generateDeviceHash($currentDeviceId);

        $revokedCount = DeviceToken::where('customer_id', $customer->id)
            ->where('device_hash', '!=', $currentDeviceHash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($revokedCount > 0) {
            Log::info('Revoked other devices', [
                'customer_id' => $customer->id,
                'revoked_count' => $revokedCount,
            ]);
        }

        return $revokedCount;
    }

    /**
     * Revoke specific device token.
     * 
     * @param Customer $customer
     * @param string $deviceId
     * @return bool
     */
    public function revokeDevice(Customer $customer, string $deviceId): bool
    {
        $deviceHash = $this->generateDeviceHash($deviceId);

        $deviceToken = DeviceToken::where('customer_id', $customer->id)
            ->where('device_hash', $deviceHash)
            ->active()
            ->first();

        if ($deviceToken) {
            $deviceToken->revoke();
            
            Log::info('Device revoked', [
                'customer_id' => $customer->id,
                'device_hash' => substr($deviceHash, 0, 16) . '...',
            ]);

            return true;
        }

        return false;
    }

    /**
     * Check if device is authorized for customer.
     * 
     * @param Customer $customer
     * @param string $deviceId
     * @return bool
     */
    public function isDeviceAuthorized(Customer $customer, string $deviceId): bool
    {
        $deviceHash = $this->generateDeviceHash($deviceId);

        return DeviceToken::where('customer_id', $customer->id)
            ->where('device_hash', $deviceHash)
            ->active()
            ->exists();
    }

    /**
     * Get all active devices for a customer.
     * 
     * @param Customer $customer
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveDevices(Customer $customer)
    {
        return DeviceToken::where('customer_id', $customer->id)
            ->active()
            ->orderBy('last_used_at', 'desc')
            ->get();
    }

    /**
     * Update last used timestamp for device.
     * 
     * @param Customer $customer
     * @param string $deviceId
     * @return void
     */
    public function updateLastUsed(Customer $customer, string $deviceId): void
    {
        $deviceHash = $this->generateDeviceHash($deviceId);

        DeviceToken::where('customer_id', $customer->id)
            ->where('device_hash', $deviceHash)
            ->active()
            ->update(['last_used_at' => now()]);
    }

    /**
     * Revoke all devices for a customer (e.g., on password change).
     * 
     * @param Customer $customer
     * @return int Number of devices revoked
     */
    public function revokeAllDevices(Customer $customer): int
    {
        $revokedCount = DeviceToken::where('customer_id', $customer->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($revokedCount > 0) {
            Log::info('All devices revoked for customer', [
                'customer_id' => $customer->id,
                'revoked_count' => $revokedCount,
            ]);
        }

        return $revokedCount;
    }
}
