<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'description'];
    
    public function getValueAttribute($value)
    {
        if ($this->type === 'boolean') {
            return (bool) $value;
        } elseif ($this->type === 'array' || $this->type === 'json') {
            return json_decode($value, true);
        }
        return $value;
    }
    
    public function setValueAttribute($value)
    {
        if ($this->type === 'array' || $this->type === 'json') {
            $this->attributes['value'] = json_encode($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }
    
    /**
     * Get a setting by key with caching
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }
            
            return $setting->value;
        });
    }
    
    /**
     * Set a setting and clear cache
     */
    public static function set(string $key, $value, string $type = 'string', $description = null)
    {
        $setting = self::firstOrNew(['key' => $key]);
        $setting->value = $value;
        $setting->type = $type;
        
        if ($description) {
            $setting->description = $description;
        }
        
        $setting->save();
        
        // Clear cache after updating
        Cache::forget("setting_{$key}");
        
        return $setting;
    }
}
