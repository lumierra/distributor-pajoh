<?php

namespace App\Observers;

use App\Models\Setting;
use App\Services\Setting\SettingManager;

class SettingObserver
{
    public function __construct(private SettingManager $manager) {}

    public function saved(Setting $setting): void
    {
        $this->manager->forgetCache();
    }

    public function deleted(Setting $setting): void
    {
        $this->manager->forgetCache();
    }

    public function restored(Setting $setting): void
    {
        $this->manager->forgetCache();
    }
}
