<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    tone: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'brand-orange', 'emerald', 'soft'].includes(v),
    },
});
</script>

<template>
    <div
        :class="[
            'group rounded-lg px-4 py-3 flex items-center gap-3 transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5',
            tone === 'brand' && 'gradient-brand text-white',
            tone === 'brand-orange' && 'gradient-brand-orange text-white',
            tone === 'emerald' && 'gradient-emerald-pos text-white',
            tone === 'soft' && 'bg-card ring-1 ring-foreground/10 text-foreground',
        ]"
    >
        <div class="flex-1 min-w-0">
            <p
                :class="[
                    'text-[11px] font-medium tracking-wide truncate',
                    tone === 'soft' ? 'text-muted-foreground' : 'text-white/85',
                ]"
            >
                {{ label }}
            </p>
            <p class="text-xl font-bold tracking-tight mt-0.5 truncate leading-tight">
                {{ value }}
            </p>
            <p
                v-if="hint"
                :class="[
                    'text-[10px] mt-0.5 truncate',
                    tone === 'soft' ? 'text-muted-foreground' : 'text-white/70',
                ]"
            >
                {{ hint }}
            </p>
        </div>
        <div
            v-if="$slots.icon"
            :class="[
                'size-9 rounded-md flex items-center justify-center shrink-0 transition-all group-hover:scale-105',
                tone === 'soft'
                    ? 'bg-primary/10 text-primary'
                    : 'bg-white/15 text-white backdrop-blur-sm',
            ]"
        >
            <slot name="icon" />
        </div>
    </div>
</template>
