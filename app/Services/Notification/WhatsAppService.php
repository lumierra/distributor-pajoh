<?php

namespace App\Services\Notification;

use App\Models\WaNotification;
use App\Models\WaTemplate;
use App\Services\Setting\SettingManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Stub WA gateway client.
 *
 * Per IMPLEMENTATION-ROADMAP.md tip #1, this is wired up at the end of
 * Fase 1 so that Fase 2+ listeners (e.g. PO approved, GRN posted) can
 * immediately invoke `send(category, recipient, context)`. Full gateway
 * integration + template editor UI are finalised in Fase 6 / Topic 18.
 *
 * For now, sending always records the row with status=`pending`. The
 * outbound HTTP call is left as a TODO to keep the surface minimal until
 * Topic 18 lands the chosen gateway provider config.
 */
class WhatsAppService
{
    public function __construct(private readonly SettingManager $settings) {}

    /**
     * Compose a message and enqueue (or send) a WA notification.
     *
     * @param  array{
     *     type:string, id?:int|null, name?:string|null, phone:string
     * }  $recipient
     * @param  array<string, mixed>  $context
     */
    public function send(
        string $category,
        array $recipient,
        array $context = [],
        ?Model $relatedRef = null,
        bool $manual = false,
    ): WaNotification {
        $skip = $this->shouldSkip($category, $recipient);

        $template = $this->resolveTemplate($category);
        $message = $template !== null
            ? $this->renderTemplate($template->body, $context)
            : '[Template untuk kategori '.$category.' belum diisi]';

        return WaNotification::create([
            'category' => $category,
            'recipient_type' => $recipient['type'] ?? WaNotification::RECIPIENT_CUSTOMER,
            'recipient_id' => $recipient['id'] ?? null,
            'recipient_name' => $recipient['name'] ?? null,
            'recipient_phone' => $recipient['phone'],
            'template_used' => $template?->category,
            'message' => $message,
            'context_data' => $context,
            'related_ref_type' => $relatedRef?::class,
            'related_ref_id' => $relatedRef?->getKey(),
            'status' => $skip ?? WaNotification::STATUS_PENDING,
            'skip_reason' => $skip !== null ? $this->lastSkipReason : null,
            'is_manual' => $manual,
            'triggered_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    private ?string $lastSkipReason = null;

    /**
     * Inspect settings + duplicate guard. Returns `WaNotification::STATUS_SKIPPED`
     * when the message should not be dispatched, otherwise null.
     *
     * @param  array<string, mixed>  $recipient
     */
    private function shouldSkip(string $category, array $recipient): ?string
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

        return null;
    }

    private function resolveTemplate(string $category): ?WaTemplate
    {
        return WaTemplate::query()->ofCategory($category)->active()->first();
    }

    /**
     * Replace `{placeholders}` in the template body. Unknown keys are kept
     * verbatim so missing data is obvious during testing.
     *
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
     * Placeholder for the outbound gateway dispatcher. Hook into the queue
     * worker once Topic 18 lands the chosen provider config.
     */
    public function dispatchPending(): void
    {
        Log::info('WhatsAppService::dispatchPending() is a stub until Topic 18 wires the gateway.');
    }
}
