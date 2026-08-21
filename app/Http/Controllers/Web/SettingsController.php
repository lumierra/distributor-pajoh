<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\NumberingSequence;
use App\Models\Setting;
use App\Services\Audit\ActivityLogger;
use App\Services\Setting\SettingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Halaman pengaturan berbasis-key (schema-driven). Tiap halaman menampilkan
 * satu/beberapa group setting; field-nya dirender otomatis dari metadata baris
 * `settings` (type/label/description/options/readonly). Simpan lewat
 * SettingManager + catat perubahan ke activity log (value sensitif di-mask).
 *
 * Menu code dipakai untuk otorisasi via canView/canUpdate.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingManager $settings,
        private readonly ActivityLogger $logger,
    ) {}

    /** Penomoran Dokumen — group `numbering`. */
    public function numbering(Request $request): InertiaResponse
    {
        return $this->renderPage($request, 'settings.numbering', 'Settings/Numbering', ['numbering'], [
            'sequences' => NumberingSequence::query()
                ->orderBy('doc_type')
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->get(['doc_type', 'period_year', 'period_month', 'last_number', 'updated_at']),
        ]);
    }

    public function updateNumbering(Request $request): RedirectResponse
    {
        return $this->saveGroups($request, 'settings.numbering', ['numbering'], 'Penomoran dokumen tersimpan.');
    }

    /** Sales & Mobile — group `sales`. */
    public function sales(Request $request): InertiaResponse
    {
        return $this->renderPage($request, 'settings.sales', 'Settings/Sales', ['sales']);
    }

    public function updateSales(Request $request): RedirectResponse
    {
        return $this->saveGroups($request, 'settings.sales', ['sales'], 'Pengaturan sales & mobile tersimpan.');
    }

    /** Inventory & Customer — group `inventory`, `customer`, `vehicle`. */
    public function inventory(Request $request): InertiaResponse
    {
        return $this->renderPage($request, 'settings.inventory', 'Settings/Inventory', ['inventory', 'customer', 'vehicle']);
    }

    public function updateInventory(Request $request): RedirectResponse
    {
        return $this->saveGroups($request, 'settings.inventory', ['inventory', 'customer', 'vehicle'], 'Pengaturan inventory & customer tersimpan.');
    }

    /** Sistem & Backup — group `system`, `audit`, `closing`. */
    public function system(Request $request): InertiaResponse
    {
        return $this->renderPage($request, 'settings.system', 'Settings/System', ['system', 'audit', 'closing']);
    }

    public function updateSystem(Request $request): RedirectResponse
    {
        return $this->saveGroups($request, 'settings.system', ['system', 'audit', 'closing'], 'Pengaturan sistem tersimpan.');
    }

    /**
     * Aksi yang dianggap sensitif untuk halaman Log Sensitif — difilter dari
     * activity_logs (tanpa tabel khusus). Mencakup penghapusan, perubahan
     * setting, dan aksi pada entitas keamanan/keuangan kritis.
     */
    private const SENSITIVE_ACTIONS = ['deleted', 'force_deleted', 'restored', 'settings.updated'];

    /** Substring model_type yang tergolong sensitif walau aksinya update biasa. */
    private const SENSITIVE_MODELS = ['User', 'Role', 'Menu', 'CreditNote', 'CustomerSupplierCreditLimit', 'CompanyBankAccount'];

    /** Log Sensitif — daftar aksi sensitif dari activity_logs. */
    public function sensitiveLog(Request $request): InertiaResponse
    {
        $this->authorizeView($request, 'settings.sensitive_log');

        $query = ActivityLog::query()
            ->with('user:id,name')
            ->where(function ($q): void {
                $q->whereIn('action', self::SENSITIVE_ACTIONS);
                foreach (self::SENSITIVE_MODELS as $model) {
                    $q->orWhere('model_type', 'like', "%{$model}");
                }
            })
            ->orderByDesc('created_at');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('model_label', 'like', "%{$search}%")
                    ->orWhere('user_name_snapshot', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }
        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return Inertia::render('Settings/SensitiveLog', [
            'logs' => $query->paginate(50)->withQueryString(),
            'filters' => [
                'q' => $request->input('q'),
                'action' => $request->input('action'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ],
            'actions' => self::SENSITIVE_ACTIONS,
        ]);
    }

    /**
     * Render halaman: kirim definisi field per group + flag izin.
     *
     * @param  array<int, string>  $groups
     * @param  array<string, mixed>  $extra  prop tambahan (mis. sequences)
     */
    private function renderPage(Request $request, string $menu, string $component, array $groups, array $extra = []): InertiaResponse
    {
        $this->authorizeView($request, $menu);

        $canUpdate = $this->canUpdate($request, $menu);

        $payload = [];
        foreach ($groups as $group) {
            $payload[$group] = $this->settings->modelsOfGroup($group)
                ->map(fn (Setting $s) => $this->fieldPayload($s))
                ->values();
        }

        return Inertia::render($component, array_merge([
            'groups' => $payload,
            'can' => ['update' => $canUpdate],
        ], $extra));
    }

    /**
     * Simpan beberapa group sekaligus dari satu request. Field yang readonly
     * atau tidak dikenal diabaikan.
     *
     * @param  array<int, string>  $groups
     */
    private function saveGroups(Request $request, string $menu, array $groups, string $message): RedirectResponse
    {
        $this->authorizeUpdate($request, $menu);

        foreach ($groups as $group) {
            $models = $this->settings->modelsOfGroup($group)->keyBy('key');
            $incoming = (array) $request->input($group, []);

            $data = [];
            foreach ($incoming as $key => $value) {
                $model = $models->get($key);
                if ($model === null || $model->is_readonly) {
                    continue;
                }
                $data[$key] = $this->coerce($model, $value);
            }

            if ($data !== []) {
                $this->applyGroup($group, $data);
            }
        }

        return back()->with('flash.success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldPayload(Setting $s): array
    {
        return [
            'key' => $s->key,
            'label' => $s->label ?? $s->key,
            'description' => $s->description,
            'type' => $s->type,
            'options' => $s->options,
            'is_readonly' => (bool) $s->is_readonly,
            'is_sensitive' => (bool) $s->is_sensitive,
            'value' => $s->castedValue(),
        ];
    }

    /**
     * Paksa value dari request ke tipe yang benar sesuai definisi setting.
     */
    private function coerce(Setting $model, mixed $value): mixed
    {
        return match ($model->type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => is_string($value) ? $value : (string) ($value ?? ''),
        };
    }

    /**
     * Tulis setting satu group langsung ke baris (group, key) — TIDAK lewat
     * SettingManager::set() yang memisah key di titik terakhir (rusak untuk key
     * bertitik seperti `geofence.radius_meter`). Catat perubahan ke activity log
     * dengan value sensitif di-mask.
     *
     * @param  array<string, mixed>  $data  [settingKey => value]
     */
    private function applyGroup(string $group, array $data): void
    {
        $models = Setting::ofGroup($group)->get()->keyBy('key');

        $before = [];
        $after = [];
        $changed = false;

        foreach ($data as $key => $value) {
            /** @var Setting|null $model */
            $model = $models->get($key);
            if ($model === null || $model->is_readonly) {
                continue;
            }

            $old = $model->castedValue();
            if ($old === $value) {
                continue;
            }

            $model->value = $value;
            $model->save();
            $changed = true;

            $masked = (bool) $model->is_sensitive;
            $before[$key] = $masked ? '***' : $old;
            $after[$key] = $masked ? '***' : $value;
        }

        if (! $changed) {
            return;
        }

        $this->settings->forgetCache();

        $this->logger->logAction('settings.updated', [
            'group' => $group,
            'before' => $before,
            'after' => $after,
        ]);
    }

    private function authorizeView(Request $request, string $menu): void
    {
        $user = $request->user();
        if ($user === null || (! $user->isSuperadmin() && ! $user->canView($menu))) {
            abort(403);
        }
    }

    private function authorizeUpdate(Request $request, string $menu): void
    {
        if (! $this->canUpdate($request, $menu)) {
            abort(403);
        }
    }

    private function canUpdate(Request $request, string $menu): bool
    {
        $user = $request->user();

        return $user !== null && ($user->isSuperadmin() || $user->canUpdate($menu));
    }
}
