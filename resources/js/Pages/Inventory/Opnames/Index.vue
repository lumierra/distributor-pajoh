<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ClipboardCheck,
    Eye,
    FileText,
    Plus,
    RotateCcw,
    Search,
    XCircle,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import OpnameStatusBadge from '@/Components/Inventory/OpnameStatusBadge.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
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
    opnames: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () => page.props.auth?.user?.is_superadmin || page.props.permissions?.['inventory.opname']?.create,
);

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('opnames.index'),
            { q: filters.q, status: filters.status === ALL ? '' : filters.status },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.status = ALL;
}

function formatDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

const statTiles = computed(() => {
    if (!props.stats) return [];
    return [
        { label: 'Total', value: props.stats.total ?? 0, icon: ClipboardCheck },
        { label: 'Draft', value: props.stats.draft ?? 0, icon: FileText },
        { label: 'Posted', value: props.stats.posted ?? 0, icon: CheckCircle2 },
        { label: 'Dibatalkan', value: props.stats.cancelled ?? 0, icon: XCircle },
    ];
});
</script>

<template>
    <Head title="Stock Opname" />

    <AppLayout>
        <PageHeader
            title="Stock Opname"
            description="Perhitungan fisik gudang — hasil hitung dibandingkan stok sistem, selisih otomatis dikoreksi."
            :icon="ClipboardCheck"
        >
            <template #actions>
                <Button
                    v-if="canCreate"
                    as-child
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                >
                    <Link :href="route('opnames.create')">
                        <Plus class="size-4" />
                        Mulai Opname
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
                        placeholder="Cari no. opname…"
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
                    <Button type="button" variant="ghost" size="default" class="rounded-full" @click="reset">
                        <RotateCcw class="size-3.5" />
                        Reset
                    </Button>
                </div>
            </div>

            <div v-if="opnames.data.length === 0" class="px-4 py-16">
                <div class="flex flex-col items-center gap-2 text-muted-foreground">
                    <ClipboardCheck class="size-7 opacity-40" />
                    <p class="text-sm">Belum ada sesi opname.</p>
                </div>
            </div>

            <Table v-else class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                        <TableHead class="pl-4">No. Opname</TableHead>
                        <TableHead>Tanggal</TableHead>
                        <TableHead class="text-right">Item</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-center pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow
                        v-for="o in opnames.data"
                        :key="o.id"
                        class="hover:bg-foreground/2.5 transition-colors border-foreground/5"
                    >
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <div class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                                    <ClipboardCheck class="size-4" />
                                </div>
                                <Link :href="route('opnames.show', o.id)" class="font-medium text-foreground font-mono hover:text-primary transition-colors">
                                    {{ o.opname_number }}
                                </Link>
                            </div>
                        </TableCell>
                        <TableCell class="text-xs text-muted-foreground">{{ formatDate(o.opname_date) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ o.items_count }}</TableCell>
                        <TableCell>
                            <OpnameStatusBadge :status="o.status" />
                        </TableCell>
                        <TableCell class="pr-4 text-center">
                            <div class="flex justify-center">
                                <ActionGroup class="rounded-full">
                                    <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                        <Link :href="route('opnames.show', o.id)">
                                            <Eye class="w-4 h-4" />
                                        </Link>
                                    </ActionButton>
                                </ActionGroup>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="opnames.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="opnames" />
            </div>
        </section>
    </AppLayout>
</template>
