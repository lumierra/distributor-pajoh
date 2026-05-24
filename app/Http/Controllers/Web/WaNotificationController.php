<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\WaNotification;
use App\Models\WaTemplate;
use App\Services\Notification\WhatsAppService;
use App\Services\Setting\SettingManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class WaNotificationController extends Controller
{
    public function __construct(private readonly WhatsAppService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->ensureAdmin($request);

        $query = WaNotification::query()->orderByDesc('created_at');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('recipient_phone', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        $stats = WaNotification::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', [WaNotification::STATUS_PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS sent', [WaNotification::STATUS_SENT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS failed', [WaNotification::STATUS_FAILED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS skipped', [WaNotification::STATUS_SKIPPED])
            ->first();

        return Inertia::render('WaNotifications/Index', [
            'notifications' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'category' => $request->input('category'),
            ],
            'stats' => [
                'total' => (int) ($stats->total ?? 0),
                'pending' => (int) ($stats->pending ?? 0),
                'sent' => (int) ($stats->sent ?? 0),
                'failed' => (int) ($stats->failed ?? 0),
                'skipped' => (int) ($stats->skipped ?? 0),
            ],
        ]);
    }

    public function show(WaNotification $waNotification): InertiaResponse
    {
        $this->ensureAdmin(request());

        return Inertia::render('WaNotifications/Show', [
            'notification' => $waNotification,
        ]);
    }

    public function retry(WaNotification $waNotification): RedirectResponse
    {
        $this->ensureAdmin(request());

        $this->service->retry($waNotification);

        return back()->with('flash.success', 'Retry dispatched.');
    }

    /**
     * Manual send WA dari detail modul (invoice, SO, customer, dll).
     */
    public function manualSend(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'category' => 'required|string|max:64',
            'phone' => 'required|string|max:32',
            'recipient_name' => 'nullable|string|max:128',
            'recipient_type' => 'nullable|string|in:customer,user',
            'recipient_id' => 'nullable|integer',
            'message' => 'nullable|string|max:2000',
            'context' => 'nullable|array',
        ]);

        $recipient = [
            'phone' => $data['phone'],
            'name' => $data['recipient_name'] ?? null,
            'type' => $data['recipient_type'] ?? WaNotification::RECIPIENT_CUSTOMER,
            'id' => $data['recipient_id'] ?? null,
        ];

        $this->service->send(
            $data['category'],
            $recipient,
            $data['context'] ?? [],
            null,
            manual: true,
        );

        return back()->with('flash.success', 'WA terkirim (manual).');
    }

    public function testConnection(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($this->service->testConnection());
    }

    public function templatesIndex(Request $request): InertiaResponse
    {
        $this->ensureAdmin($request);

        return Inertia::render('WaTemplates/Index', [
            'templates' => WaTemplate::query()->orderBy('category')->paginate(50)->withQueryString(),
        ]);
    }

    public function settingsIndex(Request $request, SettingManager $settings): InertiaResponse
    {
        $this->ensureAdmin($request);

        return Inertia::render('WaNotifications/Settings', [
            'settings' => [
                'gateway_url' => $settings->get('notification.wa.gateway_url'),
                'gateway_token' => $settings->get('notification.wa.gateway_token'),
                'sender_id' => $settings->get('notification.wa.sender_id'),
                'rate_limit_per_day' => $settings->get('notification.wa.rate_limit_per_day', 5),
            ],
        ]);
    }

    public function templatesUpdate(Request $request, WaTemplate $waTemplate): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:255',
        ]);

        $waTemplate->update($data);

        return back()->with('flash.success', 'Template diperbarui.');
    }

    public function settingsUpdate(Request $request, SettingManager $settings): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'gateway_url' => 'nullable|string|max:255',
            'gateway_token' => 'nullable|string|max:255',
            'sender_id' => 'nullable|string|max:64',
            'rate_limit_per_day' => 'nullable|integer|min:0|max:1000',
        ]);

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $settings->set("notification.wa.{$key}", $value);
            }
        }

        return back()->with('flash.success', 'Setting WA tersimpan.');
    }

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        if ($user === null || (! $user->isSuperadmin() && ! $user->canUpdate('settings.notif'))) {
            abort(403);
        }
    }
}
