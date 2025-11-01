<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    /**
     * Determine if the user can view any announcements.
     */
    public function viewAny(): bool
    {
        // Everyone can view announcements (public)
        return true;
    }

    /**
     * Determine if the user can view the announcement.
     */
    public function view(?User $user, Announcement $announcement): bool
    {
        // Everyone can view sent announcements
        return !is_null($announcement->sent_at);
    }

    /**
     * Determine if the user can create announcements.
     */
    public function create(User $user): bool
    {
        // Only kasir can create announcements
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can update the announcement.
     */
    public function update(User $user, Announcement $announcement): bool
    {
        // Only kasir can update announcements that haven't been sent
        return $user->role === 'kasir' && is_null($announcement->sent_at);
    }

    /**
     * Determine if the user can delete the announcement.
     */
    public function delete(User $user, Announcement $announcement): bool
    {
        // Only kasir can delete announcements
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can send the announcement.
     */
    public function send(User $user, Announcement $announcement): bool
    {
        // Only kasir can send announcements that haven't been sent yet
        return $user->role === 'kasir' && is_null($announcement->sent_at);
    }
}
