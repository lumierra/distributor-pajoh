<?php

namespace App\Services\Setting;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SettingManager
{
    private const CACHE_KEY = 'app:settings:all';

    private const CACHE_TTL_SECONDS = 86400;

    /**
     * Get a setting value by "group.key" dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Set a setting value by "group.key" dot notation.
     */
    public function set(string $key, mixed $value): void
    {
        [$group, $settingKey] = $this->splitKey($key);

        $setting = Setting::ofGroup($group)->where('key', $settingKey)->first();

        if (! $setting) {
            return;
        }

        $setting->value = $value;
        $setting->save();

        $this->forgetCache();
    }

    /**
     * Check whether a setting key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Forget a setting (rare; usually we just update value).
     */
    public function forget(string $key): void
    {
        [$group, $settingKey] = $this->splitKey($key);

        Setting::ofGroup($group)->where('key', $settingKey)->delete();
        $this->forgetCache();
    }

    /**
     * Reset a setting to its default_value.
     */
    public function reset(string $key): void
    {
        [$group, $settingKey] = $this->splitKey($key);

        $setting = Setting::ofGroup($group)->where('key', $settingKey)->first();

        if ($setting) {
            $setting->value = $setting->default_value;
            $setting->save();
            $this->forgetCache();
        }
    }

    /**
     * Get all settings as a flat keyed array (cached).
     *
     * @return array<string, mixed>
     */
    public function all(?string $group = null): array
    {
        $all = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->loadFromDatabase(),
        );

        if ($group === null) {
            return $all;
        }

        $prefix = $group.'.';
        $filtered = [];
        foreach ($all as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Get full Setting models, useful for UI display (with metadata).
     *
     * @return Collection<int, Setting>
     */
    public function modelsOfGroup(string $group): Collection
    {
        return Setting::ofGroup($group)->orderBy('sort_order')->get();
    }

    /**
     * Check if a setting key is marked sensitive.
     */
    public function isSensitive(string $key): bool
    {
        [$group, $settingKey] = $this->splitKey($key);

        return Setting::ofGroup($group)
            ->where('key', $settingKey)
            ->where('is_sensitive', true)
            ->exists();
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFromDatabase(): array
    {
        $items = Setting::query()->whereNull('deleted_at')->get();

        $result = [];
        foreach ($items as $setting) {
            $result["{$setting->group}.{$setting->key}"] = $setting->castedValue();
        }

        return $result;
    }

    /**
     * Split "group.subgroup.key" into [group, key] using the LAST dot,
     * so groups containing dots (e.g. "notification.wa") work correctly.
     *
     * @return array{0:string,1:string}
     */
    private function splitKey(string $key): array
    {
        $pos = strrpos($key, '.');
        if ($pos === false) {
            return ['', $key];
        }

        return [substr($key, 0, $pos), substr($key, $pos + 1)];
    }
}
