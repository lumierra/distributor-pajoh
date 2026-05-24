<?php

namespace App\Services\Notification;

use App\Models\WaNotification;
use App\Models\WaTemplate;
use App\Services\Setting\SettingManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Send WhatsApp notifications via configured gateway.
 *
 *  - Rate limit (default 5 per customer per day) via setting `notification.wa.rate_limit_per_day`
 *  - Dedup (24h) per (category, recipient_phone, related_ref) — skip identical sends
 *  - Per-category toggle via setting `notification.wa.category.{key}`
 *  - Manual send marked dgn `is_manual = true` (skips rate limit & dedup)
 *  - Retry up to 3 attempts (via retry_count column)
 *  - Outbound HTTP call best-effort: kalau gateway URL belum di-set, row tetap
 *    saved sebagai pending — dispatchPending() bisa dipanggil dari queue.
 */
class WhatsAppService
{
    public function __construct(private readonly SettingManager $settings) {}

    private ?string $lastSkipReason = null;

    /**
     * Compose a message and dispatch a WA notification.
     *
     * @param  array{type:string, id?:int|null, name?:string|null, phone:string}  $recipient
     * @param  array<string, mixed>  $context
     */
    public function send(
        string $category,
        array $recipient,
        array $context = [],
        ?Model $relatedRef = null,
        bool $manual = false,
    ): WaNotification {
        $skip = $manual ? null : $this->shouldSkip($category, $recipient, $relatedRef);

        $template = $this->resolveTemplate($category);
        $message = $template !== null
            ? $this->renderTemplate($template->body, $context)
            : '[Template untuk kategori '.$category.' belum diisi]';

        $notification = WaNotification::create([
            'category' => $category,
            'recipient_type' => $recipient['type'] ?? WaNotification::RECIPIENT_CUSTOMER,
            'recipient_id' => $recipient['id'] ?? null,
            'recipient_name' => $recipient['name'] ?? null,
            'recipient_phone' => $recipient['phone'],
            'template_used' => $template?->category,
            'message' => $message,
            'context_data' => $context,
            'related_ref_type' => $relatedRef !== null ? $relatedRef::class : null,
            'related_ref_id' => $relatedRef?->getKey(),
            'status' => $skip ?? WaNotification::STATUS_PENDING,
            'skip_reason' => $skip !== null ? $this->lastSkipReason : null,
            'is_manual' => $manual,
            'triggered_by' => auth()->id(),
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        if ($skip === null) {
            $this->attemptDispatch($notification);
        }

        return $notification;
    }

    /**
     * Retry sending pending or failed notifications (up to max 3 attempts).
     */
    public function retry(WaNotification $notification): WaNotification
    {
        if (! in_array($notification->status, [WaNotification::STATUS_PENDING, WaNotification::STATUS_FAILED], true)) {
            return $notification;
        }
        if ((int) $notification->retry_count >= 3) {
            return $notification;
        }

        $notification->increment('retry_count');
        $this->attemptDispatch($notification);

        return $notification->refresh();
    }

    /**
     * Dispatch all pending notifications (called from queue worker).
     */
    public function dispatchPending(): int
    {
        $count = 0;
        WaNotification::query()
            ->pending()
            ->chunkById(50, function ($chunk) use (&$count): void {
                foreach ($chunk as $notification) {
                    $this->attemptDispatch($notification);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Test connection to the configured WA gateway.
     *
     * @return array{success:bool, message:string}
     */
    public function testConnection(): array
    {
        $url = $this->settings->get('notification.wa.gateway_url');
        $token = $this->settings->get('notification.wa.gateway_token');

        if (! is_string($url) || $url === '') {
            return ['success' => false, 'message' => 'Gateway URL belum di-set.'];
        }
        if (! is_string($token) || $token === '') {
            return ['success' => false, 'message' => 'Gateway token belum di-set.'];
        }

        try {
            $response = Http::timeout(5)
                ->withToken($token)
                ->get($url.'/health');

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Connected. Gateway responding.'];
            }

            return ['success' => false, 'message' => "Gateway responded {$response->status()}."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Connection failed: '.$e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $recipient
     */
    private function shouldSkip(string $category, array $recipient, ?Model $relatedRef): ?string
    {
        $this->lastSkipReason = null;

        if (empty($recipient['phone'])) {
            $this->lastSkipReason = WaNotification::SKIP_NO_PHONE;

            return WaNotification::STATUS_SKIPPED;
        }

        $toggle = $this->settings->get("notification.wa.category.{$category}", true);
        if ($toggle === false) {
            $this->lastSkipReason = WaNotification::SKIP_SETTING_OFF;

            return WaNotification::STATUS_SKIPPED;
        }

        // Dedup 24h: identical (category, phone, related_ref)
        if ($relatedRef !== null) {
            $exists = WaNotification::query()
                ->where('category', $category)
                ->where('recipient_phone', $recipient['phone'])
                ->where('related_ref_type', $relatedRef::class)
                ->where('related_ref_id', $relatedRef->getKey())
                ->whereIn('status', [WaNotification::STATUS_PENDING, WaNotification::STATUS_SENT])
                ->where('created_at', '>=', now()->subDay())
                ->exists();
            if ($exists) {
                $this->lastSkipReason = WaNotification::SKIP_DUPLICATE;

                return WaNotification::STATUS_SKIPPED;
            }
        }

        // Rate limit per customer per day
        $limit = (int) $this->settings->get('notification.wa.rate_limit_per_day', 5);
        if ($limit > 0) {
            $sentToday = WaNotification::query()
                ->where('recipient_phone', $recipient['phone'])
                ->whereIn('status', [WaNotification::STATUS_PENDING, WaNotification::STATUS_SENT])
                ->whereDate('created_at', now()->toDateString())
                ->count();
            if ($sentToday >= $limit) {
                $this->lastSkipReason = WaNotification::SKIP_RATE_LIMITED;

                return WaNotification::STATUS_SKIPPED;
            }
        }

        return null;
    }

    private function resolveTemplate(string $category): ?WaTemplate
    {
        return WaTemplate::query()->ofCategory($category)->active()->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderTemplate(string $body, array $context): string
    {
        return preg_replace_callback(
            '/\{(\w+)\}/',
            fn (array $m) => array_key_exists($m[1], $context)
                ? (string) $context[$m[1]]
                : $m[0],
            $body,
        ) ?? $body;
    }

    /**
     * Attempt HTTP dispatch. Updates row status to sent/failed.
     */
    private function attemptDispatch(WaNotification $notification): void
    {
        $url = $this->settings->get('notification.wa.gateway_url');
        $token = $this->settings->get('notification.wa.gateway_token');
        $sender = $this->settings->get('notification.wa.sender_id');

        if (! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            // Gateway belum dikonfigurasi → biarkan status pending, akan
            // dikirim saat dispatchPending() dipanggil setelah setting filled.
            return;
        }

        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->post($url.'/send', [
                    'sender' => $sender,
                    'to' => $notification->recipient_phone,
                    'message' => $notification->message,
                ]);

            if ($response->successful()) {
                $payload = $response->json();
                $notification->update([
                    'status' => WaNotification::STATUS_SENT,
                    'sent_at' => now(),
                    'response_payload' => $payload,
                    'gateway_message_id' => $payload['message_id'] ?? null,
                ]);
            } else {
                $notification->update([
                    'status' => WaNotification::STATUS_FAILED,
                    'error_message' => "HTTP {$response->status()}: ".$response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WA dispatch error', ['id' => $notification->id, 'error' => $e->getMessage()]);
            $notification->update([
                'status' => WaNotification::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
