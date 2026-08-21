<script setup>
/**
 * Stat tile bergaya macOS System Settings — angka + label KIRI, ikon bulat
 * KANAN. Dipakai di header halaman list. Default tint merah brand; set
 * `tone="warn"` (amber) untuk metrik yang butuh perhatian.
 *
 * <StatTile label="Total" :value="10" :icon="ShoppingBag" />
 */
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: [Object, Function], default: null },
    tone: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'warn'].includes(v),
    },
});
</script>

<template>
    <div
        :class="[
            'rounded-2xl ring-1 shadow-sm px-4 py-3.5 flex items-center justify-between gap-3 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5',
            tone === 'warn'
                ? 'bg-amber-50 ring-amber-200/70 hover:ring-amber-300'
                : 'bg-brand-light/60 ring-brand/10 hover:ring-brand/25',
        ]"
    >
        <div class="min-w-0">
            <p
                :class="[
                    'text-lg font-semibold tracking-tight leading-tight truncate',
                    tone === 'warn' ? 'text-amber-700' : 'text-brand-dark',
                ]"
            >
                {{ value }}
            </p>
            <p
                :class="[
                    'text-[12px] truncate leading-tight mt-0.5',
                    tone === 'warn' ? 'text-amber-700/70' : 'text-brand-dark/70',
                ]"
            >
                {{ label }}
            </p>
        </div>
        <div
            v-if="icon"
            :class="[
                'size-9 rounded-full flex items-center justify-center shrink-0',
                tone === 'warn' ? 'bg-amber-500/15 text-amber-600' : 'bg-brand/10 text-brand',
            ]"
        >
            <component :is="icon" class="size-4.5" />
        </div>
    </div>
</template>
