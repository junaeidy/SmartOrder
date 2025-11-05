<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'code',
        'description',
        'percentage',
        'min_purchase',
        'active',
        'requires_code',
        'valid_from',
        'valid_until',
        'time_from',
        'time_until'
    ];
    
    protected $casts = [
        'percentage' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'active' => 'boolean',
        'requires_code' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];
    
    /**
     * Check if the discount is currently valid.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        if (!$this->active) {
            return false;
        }
        
        $now = now();
        
        // Check if within valid date range
        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }
        
        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }
        
        // Check if within valid time range (if time constraints are set)
        if ($this->time_from && $this->time_until) {
            $currentTime = $now->format('H:i:s');
            
            // Handle time range that crosses midnight
            if ($this->time_from > $this->time_until) {
                // Time range crosses midnight (e.g., 22:00 to 02:00)
                if (!($currentTime >= $this->time_from || $currentTime <= $this->time_until)) {
                    return false;
                }
            } else {
                // Normal time range (e.g., 08:00 to 17:00)
                if ($currentTime < $this->time_from || $currentTime > $this->time_until) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Calculate the discount amount for a given purchase total.
     *
     * @param float $purchaseTotal
     * @return float|null
     */
    public function calculateDiscount(float $purchaseTotal): ?float
    {
        if (!$this->isValid() || $purchaseTotal < $this->min_purchase) {
            return null;
        }
        
        return $purchaseTotal * ($this->percentage / 100);
    }
    
    /**
     * Get all usage records for this discount.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(DiscountUsage::class);
    }
    
    /**
     * Check if discount can be used by a customer.
     *
     * @param int|null $customerId
     * @param string|null $deviceId
     * @return bool
     */
    public function canBeUsedBy(?int $customerId, ?string $deviceId = null): bool
    {
        // If customer is logged in, check if they already used this discount
        if ($customerId) {
            $usedByCustomer = DiscountUsage::where('discount_id', $this->id)
                ->where('customer_id', $customerId)
                ->exists();
                
            if ($usedByCustomer) {
                return false;
            }
        }
        
        // Check if device already used this discount (even without login)
        if ($deviceId) {
            $usedByDevice = DiscountUsage::where('discount_id', $this->id)
                ->where('device_id', $deviceId)
                ->exists();
                
            if ($usedByDevice) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Record discount usage.
     *
     * @param int|null $customerId
     * @param string|null $deviceId
     * @param int|null $transactionId
     * @param float $discountAmount
     * @return DiscountUsage
     */
    public function recordUsage(?int $customerId, ?string $deviceId, ?int $transactionId, float $discountAmount): DiscountUsage
    {
        return DiscountUsage::create([
            'discount_id' => $this->id,
            'customer_id' => $customerId,
            'device_id' => $deviceId,
            'transaction_id' => $transactionId,
            'discount_amount' => $discountAmount,
            'used_at' => now(),
        ]);
    }
}
