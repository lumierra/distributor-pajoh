<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({
    meta: { type: Object, required: true }, // Laravel paginator object
});

const links = computed(() => props.meta?.links ?? []);
const total = computed(() => props.meta?.total ?? 0);
const from = computed(() => props.meta?.from ?? 0);
const to = computed(() => props.meta?.to ?? 0);

// Link pertama & terakhir dari paginator Laravel selalu "Previous"/"Next"
// (posisional, bukan berdasar isi label — labelnya sendiri berupa
// translation key mentah krn app ini tidak publish file lang/).
function edgeIcon(idx) {
    if (idx === 0) return ChevronLeft;
    if (idx === links.value.length - 1) return ChevronRight;
    return null;
}
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
                    :aria-label="edgeIcon(idx) ? (idx === 0 ? 'Sebelumnya' : 'Selanjutnya') : undefined"
                    :class="[
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-xs transition-colors',
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-background text-foreground hover:bg-muted',
                    ]"
                >
                    <component :is="edgeIcon(idx)" v-if="edgeIcon(idx)" class="size-3.5" />
                    <span v-else v-html="link.label" />
                </Link>
                <span
                    v-else
                    :class="[
                        'inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-dashed border-border px-2 text-xs text-muted-foreground',
                        edgeIcon(idx) && 'opacity-40',
                    ]"
                >
                    <component :is="edgeIcon(idx)" v-if="edgeIcon(idx)" class="size-3.5" />
                    <span v-else v-html="link.label" />
                </span>
            </li>
        </ul>
    </nav>
</template>
