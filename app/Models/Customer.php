<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'fcm_token',
        'profile_photo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'phone' => 'encrypted', // Encrypt phone numbers
    ];

    /**
     * Get the customer's transactions
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'customer_email', 'email');
    }

    /**
     * Get the customer's favorite menus
     */
    public function favoriteMenus()
    {
        return $this->hasMany(FavoriteMenu::class);
    }

    /**
     * Get the customer's device tokens
     */
    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Get active device tokens
     */
    public function activeDeviceTokens()
    {
        return $this->hasMany(DeviceToken::class)->whereNull('revoked_at');
    }

    /**
     * Get announcements that have been read by this customer
     */
    public function readAnnouncements()
    {
        return $this->belongsToMany(Announcement::class, 'customer_announcement_read')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    /**
     * Check if customer has read a specific announcement
     */
    public function hasReadAnnouncement($announcementId)
    {
        return $this->readAnnouncements()->where('announcement_id', $announcementId)->exists();
    }

    /**
     * Mark an announcement as read
     */
    public function markAnnouncementAsRead($announcementId)
    {
        if (!$this->hasReadAnnouncement($announcementId)) {
            $this->readAnnouncements()->attach($announcementId, [
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Mark an announcement as unread
     */
    public function markAnnouncementAsUnread($announcementId)
    {
        $this->readAnnouncements()->detach($announcementId);
    }

    /**
     * Check if customer should see this announcement
     * Only show announcements that were sent after customer registration
     */
    public function shouldSeeAnnouncement($announcement)
    {
        // If announcement was sent before customer registered, don't show
        if ($announcement->sent_at && $announcement->sent_at < $this->created_at) {
            return false;
        }
        
        return true;
    }

    /**
     * Scope to filter announcements for this customer
     * Only announcements sent after customer registration
     */
    public function scopeAnnouncementsForCustomer($query, $customerId)
    {
        $customer = self::find($customerId);
        
        if (!$customer) {
            return $query;
        }

        return $query->where(function ($q) use ($customer) {
            $q->where('sent_at', '>=', $customer->created_at)
              ->orWhereNull('sent_at');
        });
    }
}
