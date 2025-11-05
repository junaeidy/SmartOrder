<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class SecurityHelper
{
    /**
     * Sanitize input to prevent XSS attacks
     * Removes HTML tags, script tags, and other potentially dangerous content
     *
     * @param string|null $input
     * @param int $maxLength
     * @return string|null
     */
    public static function sanitizeInput(?string $input, int $maxLength = 1000): ?string
    {
        if (is_null($input)) {
            return null;
        }

        // Remove all HTML tags
        $sanitized = strip_tags($input);
        
        // Remove any remaining script content
        $sanitized = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $sanitized);
        
        // Remove javascript: protocol
        $sanitized = preg_replace('/javascript:/i', '', $sanitized);
        
        // Remove on* event handlers (onclick, onload, etc)
        $sanitized = preg_replace('/\bon\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $sanitized);
        
        // Convert special characters to HTML entities
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        
        // Trim whitespace
        $sanitized = trim($sanitized);
        
        // Limit length
        $sanitized = Str::limit($sanitized, $maxLength, '');
        
        return $sanitized;
    }

    /**
     * Sanitize customer name (allow only letters, spaces, and common punctuation)
     *
     * @param string|null $name
     * @return string|null
     */
    public static function sanitizeName(?string $name): ?string
    {
        if (is_null($name)) {
            return null;
        }

        // Remove HTML tags
        $sanitized = strip_tags($name);
        
        // Allow only letters (including Unicode), spaces, hyphens, apostrophes, and dots
        $sanitized = preg_replace('/[^\p{L}\s\-\'\.]/u', '', $sanitized);
        
        // Trim and limit to 255 characters
        $sanitized = Str::limit(trim($sanitized), 255, '');
        
        return $sanitized;
    }

    /**
     * Sanitize phone number (allow only numbers, +, -, (), and spaces)
     *
     * @param string|null $phone
     * @return string|null
     */
    public static function sanitizePhone(?string $phone): ?string
    {
        if (is_null($phone)) {
            return null;
        }

        // Remove all characters except digits, +, -, (), and spaces
        $sanitized = preg_replace('/[^0-9\+\-\(\)\s]/', '', $phone);
        
        // Trim
        $sanitized = trim($sanitized);
        
        return $sanitized;
    }

    /**
     * Validate and sanitize email
     *
     * @param string|null $email
     * @return string|null
     */
    public static function sanitizeEmail(?string $email): ?string
    {
        if (is_null($email)) {
            return null;
        }

        // Remove whitespace and convert to lowercase
        $sanitized = strtolower(trim($email));
        
        // Validate email format
        if (!filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        
        return $sanitized;
    }

    /**
     * Generate secure random API key
     *
     * @param int $length
     * @return string
     */
    public static function generateApiKey(int $length = 64): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Verify API key for webhooks
     *
     * @param string|null $providedKey
     * @param string $expectedKey
     * @return bool
     */
    public static function verifyApiKey(?string $providedKey, string $expectedKey): bool
    {
        if (empty($providedKey) || empty($expectedKey)) {
            return false;
        }

        // Use timing-attack safe comparison
        return hash_equals($expectedKey, $providedKey);
    }
}
