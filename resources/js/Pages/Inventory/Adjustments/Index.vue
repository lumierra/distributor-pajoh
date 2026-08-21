<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Eye,
    FileText,
    Plus,
    RotateCcw,
    Search,
    SlidersHorizontal,
    XCircle,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import AdjustmentStatusBadge from '@/Components/Inventory/AdjustmentStatusBadge.vue';
import { REASON_LABELS } from '@/Components/Inventory/adjustmentMeta';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    adjustments: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () => page.props.auth?.user?.is_superadmin || page.props.permissions?.['inventory.adjustment']?.create,
);

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
    reason_category: props.filters.reason_category || ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('adjustments.index'),
            {
                q: filters.q,
                status: filters.status === ALL ? '' : filters.status,
                reason_category: filters.reason_category === ALL ? '' : filters.reason_category,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.status = ALL;
    filters.reason_category = ALL;
}

function formatDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

const statTiles = computed(() => {
    if (!props.stats) return [];
    return [
        { label: 'Total', value: props.stats.total ?? 0, icon: SlidersHorizontal },
        { label: 'Draft', value: props.stats.draft ?? 0, icon: FileText },
        { label: 'Posted', value: props.stats.posted ?? 0, icon: CheckCircle2 },
        { label: 'Dibatalkan', value: props.stats.cancelled ?? 0, icon: XCircle },
    ];
});
</script>

<template>
    <Head title="Penyesuaian Stok" />

    <AppLayout>
        <PageHeader
            title="Penyesuaian Stok (Adjustment)"
            description="Koreksi stok manual — barang rusak, hilang, susut, salah hitung, temuan lebih."
            :icon="SlidersHorizontal"
        >
            <template #actions>
                <Button
                    v-if="canCreate"
                    as-child
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                >
                    <Link :href="route('adjustments.create')">
                        <Plus class="size-4" />
                        Buat Adjustment
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div
                v-for="tile in statTiles"
                :key="tile.label"
                class="rounded-2xl bg-brand-light/60 ring-1 ring-brand/10 shadow-sm px-4 py-3.5 flex items-center justify-between gap-3 transition-all duration-200 hover:ring-brand/25 hover:shadow-md hover:-translate-y-0.5"
            >
                <div class="min-w-0">
                    <p class="text-lg font-semibold tracking-tight leading-tight text-brand-dark">{{ tile.value }}</p>
                    <p class="text-[12px] text-brand-dark/70 truncate leading-tight mt-0.5">{{ tile.label }}</p>
                </div>
                <div class="size-9 rounded-full bg-brand/10 text-brand flex items-center justify-center shrink-0">
                    <component :is="tile.icon" class="size-4.5" />
                </div>
            </div>
        </section>

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        v-model="filters.q"
                        placeholder="Cari no. adjustment…"
                        class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card"
                    />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[140px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="posted">Posted</SelectItem>
                            <SelectItem value="cancelled">Dibatalkan</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.reason_category">
                        <SelectTrigger class="w-[160px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Alasan" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Alasan</SelectItem>
                            <SelectItem v-for="(label, key) in REASON_LABELS" :key="key" :value="key">
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="ghost" size="default" class="rounded-full" @click="reset">
                        <RotateCcw class="size-3.5" />
                        Reset
                    </Button>
                </div>
            </div>

            <div v-if="adjustments.data.length === 0" class="px-4 py-16">
                <div class="flex flex-col items-center gap-2 text-muted-foreground">
                    <SlidersHorizontal class="size-7 opacity-40" />
                    <p class="text-sm">Belum ada penyesuaian stok.</p>
                </div>
            </div>

            <Table v-else class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                        <TableHead class="pl-4">No. Adjustment</TableHead>
                        <TableHead>Tanggal</TableHead>
                        <TableHead>Alasan</TableHead>
                        <TableHead class="text-right">Item</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-center pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow
                        v-for="a in adjustments.data"
                        :key="a.id"
                        class="hover:bg-foreground/2.5 transition-colors border-foreground/5"
                    >
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <div class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                                    <SlidersHorizontal class="size-4" />
                                </div>
                                <Link :href="route('adjustments.show', a.id)" class="font-medium text-foreground font-mono hover:text-primary transition-colors">
                                    {{ a.adjustment_number }}
                                </Link>
                            </div>
                        </TableCell>
                        <TableCell class="text-xs text-muted-foreground">{{ formatDate(a.adjustment_date) }}</TableCell>
                        <TableCell>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[12px] font-medium bg-muted/70 text-muted-foreground">
                                {{ REASON_LABELS[a.reason_category] ?? a.reason_category }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right font-mono">{{ a.items_count }}</TableCell>
                        <TableCell>
                            <AdjustmentStatusBadge :status="a.status" />
                        </TableCell>
                        <TableCell class="pr-4 text-center">
                            <div class="flex justify-center">
                                <ActionGroup class="rounded-full">
                                    <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                        <Link :href="route('adjustments.show', a.id)">
                                            <Eye class="w-4 h-4" />
                                        </Link>
                                    </ActionButton>
                                </ActionGroup>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="adjustments.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="adjustments" />
            </div>
        </section>
    </AppLayout>
</template>
