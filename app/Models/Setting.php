<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'value', 'updated_at'];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    private static array $sensitiveKeys = [
        'telegram_bot_token',
        'api_key',
        'gemini_api_key',
        'telegram_webhook_secret',
    ];

    public static function getValue(string $key, $default = null): ?string
    {
        $value = static::where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        if (in_array($key, self::$sensitiveKeys)) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                return $value;
            }
        }

        return $value;
    }

    public static function setValue(string $key, ?string $value): void
    {
        $stored = $value;

        if ($value && in_array($key, self::$sensitiveKeys)) {
            $stored = encrypt($value);
        }

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'updated_at' => now()]
        );
    }
}
