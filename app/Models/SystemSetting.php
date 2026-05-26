<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = ['setting_key', 'setting_value', 'description'];

    public static function get($key, $default = null)
    {
        if (app()->runningUnitTests()) {
            return self::where('setting_key', $key)->value('setting_value') ?? $default;
        }

        $value = Cache::remember("system_settings.{$key}", 120, function () use ($key) {
            return self::where('setting_key', $key)->value('setting_value');
        });

        return $value ?? $default;
    }

    public static function set($key, $value, $description = null)
    {
        Cache::forget("system_settings.{$key}");

        return self::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value, 'description' => $description]
        );
    }
}
