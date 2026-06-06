<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Fetch the value for a setting key. Returns the default when the
     * key is missing.
     *
     * Deliberately uncached — settings change rarely and the read cost
     * per sweep is negligible. Premature caching would also complicate
     * the D4 in-flight guardrail story; readers either snapshot at
     * clock-start or read live, and a cache layer would muddy that.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::query()->where('key', $key)->value('value');

        return $value ?? $default;
    }
}
