<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Audit\ActivityLogger;
use App\Services\Setting\SettingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Profil Perusahaan (T01 — Setting/Config).
 *
 * Mengelola 3 group setting sekaligus dalam satu halaman ber-tab:
 * `company` (identitas + kontak), `company.assets`, `company.invoice_text`.
 */
class CompanySettingController extends Controller
{
    /** Field group `company` yang boleh diedit, beserta rule validasinya. */
    private const PROFILE_RULES = [
        'name' => 'required|string|max:255',
        'legal_form' => 'nullable|string|max:32',
        'npwp' => 'nullable|string|max:32',
        'nib' => 'nullable|string|max:32',
        'address' => 'nullable|string|max:1000',
        'city' => 'nullable|string|max:128',
        'province' => 'nullable|string|max:128',
        'postal_code' => 'nullable|string|max:16',
        'phone' => 'nullable|string|max:32',
        'whatsapp' => 'nullable|string|max:32',
        'email' => 'nullable|email|max:255',
        'website' => 'nullable|string|max:255',
    ];

    /** Field group `company.invoice_text`. */
    private const INVOICE_TEXT_RULES = [
        'header_text' => 'nullable|string|max:2000',
        'footer_text' => 'nullable|string|max:2000',
        'terms_and_conditions' => 'nullable|string|max:2000',
        'payment_instruction' => 'nullable|string|max:2000',
    ];

    /** Asset gambar: key setting => [prefix path di disk, max KB]. */
    private const ASSETS = [
        'logo_path' => ['prefix' => 'company/logo', 'max_kb' => 2048],
        'signature_path' => ['prefix' => 'company/signature', 'max_kb' => 1024],
        'stamp_path' => ['prefix' => 'company/stamp', 'max_kb' => 1024],
    ];

    public function edit(Request $request, SettingManager $settings): InertiaResponse
    {
        $this->authorizeView($request);

        $canManageAssets = $this->canManageAssets($request);

        $profile = [];
        foreach (array_keys(self::PROFILE_RULES) as $key) {
            $profile[$key] = $settings->get("company.{$key}", '');
        }

        $invoiceText = [];
        foreach (array_keys(self::INVOICE_TEXT_RULES) as $key) {
            $invoiceText[$key] = $settings->get("company.invoice_text.{$key}", '');
        }

        return Inertia::render('Settings/Company', [
            'profile' => $profile,
            'invoiceText' => $invoiceText,
            'assets' => $canManageAssets ? $this->assetPayload($settings) : null,
            'can' => [
                'update' => $this->canUpdate($request),
                'manageAssets' => $canManageAssets,
            ],
        ]);
    }

    /**
     * Simpan tab Identitas + Kontak (group `company`).
     */
    public function updateProfile(Request $request, SettingManager $settings, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeUpdate($request);

        $data = $request->validate(self::PROFILE_RULES);

        $this->applySettings($settings, $logger, 'company', $data);

        return back()->with('flash.success', 'Profil perusahaan tersimpan.');
    }

    /**
     * Simpan tab Teks Faktur (group `company.invoice_text`).
     */
    public function updateInvoiceText(Request $request, SettingManager $settings, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeUpdate($request);

        $data = $request->validate(self::INVOICE_TEXT_RULES);

        $this->applySettings($settings, $logger, 'company.invoice_text', $data);

        return back()->with('flash.success', 'Teks faktur tersimpan.');
    }

    /**
     * Simpan tab Asset — upload/hapus gambar + toggle tampil di faktur.
     */
    public function updateAssets(Request $request, SettingManager $settings, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeAssets($request);

        $validated = $request->validate([
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:'.self::ASSETS['logo_path']['max_kb'],
            'signature' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:'.self::ASSETS['signature_path']['max_kb'],
            'stamp' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:'.self::ASSETS['stamp_path']['max_kb'],
            'remove_logo' => 'boolean',
            'remove_signature' => 'boolean',
            'remove_stamp' => 'boolean',
            'show_signature_on_invoice' => 'boolean',
            'show_stamp_on_invoice' => 'boolean',
        ]);

        $fileMap = [
            'logo' => 'logo_path',
            'signature' => 'signature_path',
            'stamp' => 'stamp_path',
        ];

        foreach ($fileMap as $input => $settingKey) {
            $file = $request->file($input);

            if ($file instanceof UploadedFile) {
                $this->deleteAsset($settings->get("company.assets.{$settingKey}"));
                $path = $this->storeAsset($file, self::ASSETS[$settingKey]['prefix']);
                $this->applySettings($settings, $logger, 'company.assets', [$settingKey => $path]);

                continue;
            }

            if (($validated["remove_{$input}"] ?? false) === true) {
                $this->deleteAsset($settings->get("company.assets.{$settingKey}"));
                $this->applySettings($settings, $logger, 'company.assets', [$settingKey => '']);
            }
        }

        $toggles = [];
        foreach (['show_signature_on_invoice', 'show_stamp_on_invoice'] as $key) {
            if (array_key_exists($key, $validated)) {
                $toggles[$key] = (bool) $validated[$key];
            }
        }

        if ($toggles !== []) {
            $this->applySettings($settings, $logger, 'company.assets', $toggles);
        }

        return back()->with('flash.success', 'Asset perusahaan tersimpan.');
    }

    /**
     * Tulis setting satu group lalu catat perubahannya ke activity log.
     *
     * Value setting sensitif (TTD, stempel) di-mask supaya tidak bocor ke log,
     * sesuai aturan 4.5 pada dokumentasi T01.
     *
     * @param  array<string, mixed>  $data
     */
    private function applySettings(
        SettingManager $settings,
        ActivityLogger $logger,
        string $group,
        array $data,
    ): void {
        $before = [];
        $after = [];

        foreach ($data as $key => $value) {
            $fullKey = "{$group}.{$key}";
            $old = $settings->get($fullKey);
            $new = $value ?? '';

            if ($old === $new) {
                continue;
            }

            $settings->set($fullKey, $new);

            $masked = $settings->isSensitive($fullKey);
            $before[$key] = $masked ? '***' : $old;
            $after[$key] = $masked ? '***' : $new;
        }

        if ($after === []) {
            return;
        }

        $logger->logAction('settings.updated', [
            'group' => $group,
            'before' => $before,
            'after' => $after,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function assetPayload(SettingManager $settings): array
    {
        $payload = [];

        foreach (array_keys(self::ASSETS) as $key) {
            $path = (string) $settings->get("company.assets.{$key}", '');
            $payload[$key] = $path;
            $payload[str_replace('_path', '_url', $key)] = $this->assetUrl($path);
        }

        $payload['show_signature_on_invoice'] = (bool) $settings->get('company.assets.show_signature_on_invoice', true);
        $payload['show_stamp_on_invoice'] = (bool) $settings->get('company.assets.show_stamp_on_invoice', true);

        return $payload;
    }

    /**
     * Resolve path setting jadi URL yang bisa dirender <img>.
     *
     * Logo bawaan (`images/logo.png`) tinggal di `public/`, sedangkan hasil
     * upload user tersimpan di disk `public` — keduanya butuh URL berbeda.
     */
    private function assetUrl(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'company/')) {
            return Storage::disk('public')->exists($path)
                ? Storage::disk('public')->url($path)
                : null;
        }

        return file_exists(public_path($path)) ? asset($path) : null;
    }

    private function storeAsset(UploadedFile $file, string $prefix): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'png';
        $filename = Str::uuid()->toString().'.'.$ext;
        $path = "{$prefix}/{$filename}";

        Storage::disk('public')->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    /**
     * Hapus file asset lama. Hanya menyentuh hasil upload (`company/*`) supaya
     * asset bawaan yang di-commit ke `public/` tidak ikut terhapus.
     */
    private function deleteAsset(mixed $path): void
    {
        if (! is_string($path) || ! str_starts_with($path, 'company/')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function authorizeView(Request $request): void
    {
        $user = $request->user();

        if ($user === null || (! $user->isSuperadmin() && ! $user->canView('settings.company'))) {
            abort(403);
        }
    }

    private function authorizeUpdate(Request $request): void
    {
        if (! $this->canUpdate($request)) {
            abort(403);
        }
    }

    private function authorizeAssets(Request $request): void
    {
        if (! $this->canManageAssets($request)) {
            abort(403);
        }
    }

    private function canUpdate(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && ($user->isSuperadmin() || $user->canUpdate('settings.company'));
    }

    /**
     * Tab Asset memuat setting sensitif (TTD & stempel) sehingga dibatasi ke
     * superadmin saja — lihat aturan 3 pada dokumentasi T01.
     */
    private function canManageAssets(Request $request): bool
    {
        return $request->user()?->isSuperadmin() === true;
    }
}
