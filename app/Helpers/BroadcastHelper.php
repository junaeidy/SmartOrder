<?php

namespace App\Helpers;

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

class BroadcastHelper
{
    /**
     * Safely broadcast an event with error handling
     * 
     * @param object $event The event to broadcast
     * @return bool Returns true if successful, false otherwise
     */
    public static function safeBroadcast(object $event): bool
    {
        try {
            event($event);
            return true;
        } catch (BroadcastException $e) {
            Log::warning('Broadcasting failed (non-critical)', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Unexpected error during broadcasting', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Safely broadcast an event, but throw exception if it's critical
     * 
     * @param object $event The event to broadcast
     * @param bool $critical If true, will throw exception on failure
     * @return bool Returns true if successful
     * @throws \Exception If critical is true and broadcasting fails
     */
    public static function broadcast(object $event, bool $critical = false): bool
    {
        try {
            event($event);
            return true;
        } catch (BroadcastException $e) {
            if ($critical) {
                throw $e;
            }
            
            Log::warning('Broadcasting failed (non-critical)', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
