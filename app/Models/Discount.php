<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'percentage',
        'min_purchase',
        'active',
        'requires_code',
        'valid_from',
        'valid_until'
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
}
