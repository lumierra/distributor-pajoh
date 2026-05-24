<?php

namespace App\Services\Sales;

use App\Services\Setting\SettingManager;

class FakeGpsDetector
{
    public function __construct(private readonly SettingManager $settings) {}

    /**
     * Returns true kalau check di-enable & is_mock_location terdeteksi.
     * Bisa di-bypass kalau env=local + setting `sales.fake_gps.allow_in_dev_mode`.
     */
    public function shouldBlock(bool $isMockLocation): bool
    {
        if (! $isMockLocation) {
            return false;
        }
        if ((bool) $this->settings->get('sales.fake_gps.check_enabled', true) === false) {
            return false;
        }
        if (app()->environment('local') && (bool) $this->settings->get('sales.fake_gps.allow_in_dev_mode', false)) {
            return false;
        }

        return true;
    }
}
