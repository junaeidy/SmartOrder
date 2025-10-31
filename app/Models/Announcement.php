<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'sent_by',
        'sent_at',
        'recipients_count',
        'success_count',
        'failed_count',
        'status',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * Get the user who sent this announcement
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Scope for sent announcements
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for draft announcements
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Get customers who have read this announcement
     */
    public function readByCustomers()
    {
        return $this->belongsToMany(Customer::class, 'customer_announcement_read')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    /**
     * Check if a specific customer has read this announcement
     */
    public function isReadBy($customerId)
    {
        return $this->readByCustomers()->where('customer_id', $customerId)->exists();
    }
}
