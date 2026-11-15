<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin-editable values. Every change bumps the version, keeps the earlier
 * value in setting_versions and writes an audit entry (section 13).
 */
class Settings
{
    private const CACHE_KEY = 'esnad.settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember(self::CACHE_KEY, 300, fn () => Setting::query()->pluck('value', 'key')->all());

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, mixed $value, ?string $reason = null): Setting
    {
        $setting = DB::transaction(function () use ($key, $value, $reason) {
            $setting = Setting::query()->lockForUpdate()->firstOrNew(['key' => $key]);
            $before = $setting->exists ? ['value' => $setting->value, 'version' => $setting->version] : null;
            $setting->version = $setting->exists ? $setting->version + 1 : 1;
            $setting->value = $value;
            $setting->updated_by = auth()->id();
            $setting->save();

            DB::table('setting_versions')->insert([
                'key' => $key,
                'version' => $setting->version,
                'value' => json_encode($value),
                'changed_by' => auth()->id(),
                'created_at' => now(),
            ]);
            Audit::record('settings.updated', $setting, $before, ['value' => $value, 'version' => $setting->version], $reason);

            return $setting;
        });
        Cache::forget(self::CACHE_KEY);

        return $setting;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
