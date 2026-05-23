<script setup>
/**
 * Pill-style action button untuk dipakai di dalam <ActionGroup>.
 *
 * - Tooltip muncul di atas saat hover via `group-hover`
 * - Scale-up 1.125 saat hover (subtle micro-interaction)
 * - Warna per tone (brand/blue/indigo/purple/amber/emerald/red)
 *
 * Props:
 *   icon       Lucide component
 *   label      string (untuk aria-label + tooltip)
 *   tone       brand|blue|indigo|purple|amber|emerald|red
 *   asChild    true = render <span>, pakai untuk wrap Inertia Link di dalam slot
 */

defineProps({
    icon: { type: [Object, Function], required: true },
    label: { type: String, required: true },
    tone: {
        type: String,
        default: 'brand',
        validator: (v) =>
            ['brand', 'blue', 'indigo', 'purple', 'amber', 'emerald', 'red'].includes(v),
    },
    asChild: { type: Boolean, default: false },
});
</script>

<template>
    <div class="relative group/abtn">
        <component
            :is="asChild ? 'span' : 'button'"
            :type="asChild ? undefined : 'button'"
            :aria-label="label"
            :class="[
                'inline-flex items-center justify-center px-1.5 py-1 rounded-md transition-all duration-200 group-hover/abtn:scale-110 cursor-pointer',
                tone === 'brand' && 'text-primary hover:bg-primary/10',
                tone === 'blue' && 'text-blue-600 hover:bg-blue-100',
                tone === 'indigo' && 'text-indigo-600 hover:bg-indigo-100',
                tone === 'purple' && 'text-purple-600 hover:bg-purple-100',
                tone === 'amber' && 'text-amber-600 hover:bg-amber-100',
                tone === 'emerald' && 'text-emerald-600 hover:bg-emerald-100',
                tone === 'red' && 'text-red-500 hover:bg-red-100',
            ]"
        >
            <slot>
                <component :is="icon" class="w-4 h-4" />
            </slot>
        </component>
        <!-- Tooltip -->
        <div
            class="absolute bottom-full mb-1 left-1/2 -translate-x-1/2 hidden group-hover/abtn:block bg-gray-800 text-white text-[11px] px-2 py-1 rounded shadow-lg whitespace-nowrap z-10 pointer-events-none"
        >
            {{ label }}
        </div>
    </div>
</template>
