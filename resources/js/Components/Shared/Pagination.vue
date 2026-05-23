<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    meta: { type: Object, required: true }, // Laravel paginator object
});

const links = computed(() => props.meta?.links ?? []);
const total = computed(() => props.meta?.total ?? 0);
const from = computed(() => props.meta?.from ?? 0);
const to = computed(() => props.meta?.to ?? 0);
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-4">
        <p class="text-xs text-muted-foreground">
            Menampilkan {{ from }}–{{ to }} dari {{ total }} baris
        </p>
        <ul class="flex flex-wrap items-center gap-1">
            <li v-for="(link, idx) in links" :key="idx">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    :class="[
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-xs transition-colors',
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-background text-foreground hover:bg-muted',
                    ]"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-dashed border-border px-2 text-xs text-muted-foreground"
                    v-html="link.label"
                />
            </li>
        </ul>
    </nav>
</template>
