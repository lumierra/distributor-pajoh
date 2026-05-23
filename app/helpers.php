<?php

declare(strict_types=1);

use App\Services\Setting\SettingManager;

if (! function_exists('setting')) {
    /**
     * Helper to read or interact with the application settings.
     *
     * Examples:
     *   setting('company.name')                   - read value
     *   setting('company.name', 'CV Default')     - read with default fallback
     *   setting()->set('company.name', 'CV Pajoh') - set value
     *   setting()->all('company')                  - all settings in a group
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        /** @var SettingManager $manager */
        $manager = app(SettingManager::class);

        if ($key === null) {
            return $manager;
        }

        return $manager->get($key, $default);
    }
}
