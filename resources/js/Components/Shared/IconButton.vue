<script setup>
defineProps({
    /** Lucide icon component */
    icon: { type: [Object, Function], required: true },
    label: { type: String, required: true },
    tone: {
        type: String,
        default: 'default',
        validator: (v) =>
            ['default', 'primary', 'warning', 'destructive', 'info', 'success'].includes(v),
    },
    /** Render as <a> / Link wrapper via `as` slot prop, or render as button */
    asChild: { type: Boolean, default: false },
});
</script>

<template>
    <component
        :is="asChild ? 'span' : 'button'"
        :type="asChild ? undefined : 'button'"
        :title="label"
        :aria-label="label"
        :class="[
            'inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors',
            tone === 'default' && 'text-muted-foreground hover:bg-muted hover:text-foreground',
            tone === 'primary' && 'text-primary hover:bg-primary/10',
            tone === 'success' && 'text-emerald-700 hover:bg-emerald-50',
            tone === 'warning' && 'text-amber-700 hover:bg-amber-50',
            tone === 'info' && 'text-blue-700 hover:bg-blue-50',
            tone === 'destructive' && 'text-destructive hover:bg-destructive/10',
        ]"
    >
        <slot>
            <component :is="icon" class="size-4" />
        </slot>
    </component>
</template>
