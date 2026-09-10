<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tiny key/value store for shop-level settings that don't warrant their own
 * table (USD→PKR rate, auto-FX toggle, …). Values are stored as strings.
 */
class Setting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_by_user_id'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::find($key);

        return $row ? $row->value : $default;
    }

    public static function put(string $key, mixed $value, ?int $userId = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value, 'updated_by_user_id' => $userId],
        );
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = static::get($key);

        return $v === null ? $default : in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
