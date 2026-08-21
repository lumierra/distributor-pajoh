<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Eye,
    FileText,
    PackageOpen,
    Plus,
    RotateCcw,
    Search,
    Send,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import GrnStatusBadge from '@/Components/GoodsReceipts/GrnStatusBadge.vue';
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
    grns: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () =>
        page.props.auth?.user?.is_superadmin ||
        page.props.permissions?.['purchasing.grn']?.create,
);

const ALL = 'all';

const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
    year: props.filters.year || ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('grns.index'),
            {
                q: filters.q,
                status: filters.status === ALL ? '' : filters.status,
                year: filters.year === ALL ? '' : filters.year,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.status = ALL;
    filters.year = ALL;
}

const currentYear = new Date().getFullYear();
const years = computed(() => [currentYear, currentYear - 1, currentYear - 2]);

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

const statTiles = computed(() => {
    if (!props.stats) return [];
    return [
        { label: 'Total GRN', value: props.stats.total ?? 0, icon: PackageOpen },
        { label: 'Draft', value: props.stats.draft ?? 0, icon: FileText },
        { label: 'Submitted', value: props.stats.submitted ?? 0, icon: Send },
        { label: 'Posted', value: props.stats.posted ?? 0, icon: CheckCircle2 },
        { label: 'Discrepancy', value: props.stats.discrepancy ?? 0, icon: AlertTriangle },
    ];
});
</script>

<template>
    <Head title="Goods Receipt" />

    <AppLayout>
        <PageHeader
            title="Goods Receipt (GRN)"
            description="Penerimaan barang fisik dari supplier. PO → GRN → stok aktual."
            :icon="PackageOpen"
        >
            <template #actions>
                <Button
                    v-if="canCreate"
                    as-child
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                >
                    <Link :href="route('grns.create')">
                        <Plus class="size-4" />
                        Buat GRN
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- ── Stat tiles — soft red tint, angka+label kiri, ikon kanan ── -->
        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <div
                v-for="tile in statTiles"
                :key="tile.label"
                class="rounded-2xl bg-brand-light/60 ring-1 ring-brand/10 shadow-sm px-4 py-3.5 flex items-center justify-between gap-3 transition-all duration-200 hover:ring-brand/25 hover:shadow-md hover:-translate-y-0.5"
            >
                <div class="min-w-0">
                    <p class="text-lg font-semibold tracking-tight leading-tight text-brand-dark">
                        {{ tile.value }}
                    </p>
                    <p class="text-[12px] text-brand-dark/70 truncate leading-tight mt-0.5">
                        {{ tile.label }}
                    </p>
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
                        placeholder="Cari no. GRN, no. PO, atau supplier…"
                        class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card"
                    />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[150px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="submitted">Submitted</SelectItem>
                            <SelectItem value="rejected">Rejected</SelectItem>
                            <SelectItem value="posted">Posted</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.year">
                        <SelectTrigger class="w-[120px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Tahun" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Tahun</SelectItem>
                            <SelectItem v-for="y in years" :key="y" :value="String(y)">{{ y }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="ghost" size="default" class="rounded-full" @click="reset">
                        <RotateCcw class="size-3.5" />
                        Reset
                    </Button>
                </div>
            </div>

            <div v-if="grns.data.length === 0" class="px-4 py-16">
                <div class="flex flex-col items-center gap-2 text-muted-foreground">
                    <PackageOpen class="size-7 opacity-40" />
                    <p class="text-sm">Belum ada GRN.</p>
                </div>
            </div>

            <Table v-else class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                        <TableHead class="pl-4">GRN</TableHead>
                        <TableHead>PO</TableHead>
                        <TableHead>Supplier</TableHead>
                        <TableHead>Tanggal</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-center pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow
                        v-for="g in grns.data"
                        :key="g.id"
                        class="hover:bg-foreground/2.5 transition-colors border-foreground/5"
                    >
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <div class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                                    <PackageOpen class="size-4" />
                                </div>
                                <div>
                                    <Link :href="route('grns.show', g.id)" class="font-medium text-foreground font-mono hover:text-primary transition-colors">
                                        {{ g.grn_number }}
                                    </Link>
                                    <p v-if="g.has_discrepancy" class="text-[11px] text-amber-700 flex items-center gap-1 mt-0.5">
                                        <AlertTriangle class="size-3" /> Discrepancy
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell class="font-mono text-xs">
                            <Link
                                v-if="g.purchase_order_id"
                                :href="route('purchase-orders.show', g.purchase_order_id)"
                                class="hover:text-primary transition-colors"
                            >
                                {{ g.purchase_order?.po_number }}
                            </Link>
                            <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-brand/10 text-brand">
                                Langsung
                            </span>
                        </TableCell>
                        <TableCell>
                            <p class="font-medium">{{ g.supplier?.name ?? '—' }}</p>
                            <p class="text-[12px] text-muted-foreground font-mono">{{ g.supplier?.code }}</p>
                        </TableCell>
                        <TableCell class="text-xs text-muted-foreground">{{ formatDate(g.received_date) }}</TableCell>
                        <TableCell>
                            <GrnStatusBadge :status="g.status" />
                        </TableCell>
                        <TableCell class="pr-4 text-center">
                            <div class="flex justify-center">
                                <ActionGroup class="rounded-full">
                                    <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                        <Link :href="route('grns.show', g.id)">
                                            <Eye class="w-4 h-4" />
                                        </Link>
                                    </ActionButton>
                                    <ActionButton v-if="g.pdf_path" :icon="FileText" label="PDF" as-child tone="blue">
                                        <a :href="route('grns.pdf', g.id)" target="_blank" rel="noopener">
                                            <FileText class="w-4 h-4" />
                                        </a>
                                    </ActionButton>
                                </ActionGroup>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="grns.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="grns" />
            </div>
        </section>
    </AppLayout>
</template>
