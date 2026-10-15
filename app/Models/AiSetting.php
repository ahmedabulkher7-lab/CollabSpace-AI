<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'key',
    'value',
    'type',
    'description',
    'updated_by',
])]
class AiSetting extends Model
{
    use HasFactory;

    /**
     * User who last modified this setting.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Retrieve a typed configuration value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json' => json_decode($setting->value, true) ?? $default,
            default => $setting->value ?? $default,
        };
    }

    /**
     * Set a configuration value.
     */
    public static function set(string $key, mixed $value, ?string $type = null, ?int $userId = null): self
    {
        $type = $type ?? (is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : (is_array($value) ? 'json' : 'string')));
        $rawValue = is_array($value) ? json_encode($value) : (string) $value;

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $rawValue,
                'type' => $type,
                'updated_by' => $userId,
            ]
        );
    }
}
