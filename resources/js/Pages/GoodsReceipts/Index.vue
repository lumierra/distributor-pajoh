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
import StatCard from '@/Components/Shared/StatCard.vue';
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
</script>

<template>
    <Head title="Goods Receipt" />

    <AppLayout>
        <PageHeader title="Goods Receipt (GRN)" description="Penerimaan barang fisik dari supplier. PO → GRN → stok aktual." :icon="PackageOpen">
            <template #actions>
                <Button v-if="canCreate" as-child size="default" variant="secondary">
                    <Link :href="route('grns.create')">
                        <Plus class="size-4" />
                        Buat GRN
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <StatCard label="Total GRN" :value="stats.total ?? 0" tone="brand">
                <template #icon><PackageOpen class="size-5" /></template>
            </StatCard>
            <StatCard label="Draft" :value="stats.draft ?? 0" tone="brand">
                <template #icon><FileText class="size-5" /></template>
            </StatCard>
            <StatCard label="Submitted" :value="stats.submitted ?? 0" tone="brand">
                <template #icon><Send class="size-5" /></template>
            </StatCard>
            <StatCard label="Posted" :value="stats.posted ?? 0" tone="brand">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
            <StatCard label="Discrepancy" :value="stats.discrepancy ?? 0" tone="brand-orange">
                <template #icon><AlertTriangle class="size-5" /></template>
            </StatCard>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input v-model="filters.q" placeholder="Cari no. GRN, no. PO, atau supplier…" class="pl-8 h-9 rounded-md" />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[150px] h-9 rounded-md">
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
                        <SelectTrigger class="w-[120px] h-9 rounded-md">
                            <SelectValue placeholder="Tahun" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Tahun</SelectItem>
                            <SelectItem v-for="y in years" :key="y" :value="String(y)">{{ y }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline" size="default" @click="reset">
                        <RotateCcw class="size-3.5" /> Reset
                    </Button>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">GRN</TableHead>
                        <TableHead>PO</TableHead>
                        <TableHead>Supplier</TableHead>
                        <TableHead>Tanggal</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="grns.data.length === 0">
                        <TableCell colspan="6" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <PackageOpen class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada GRN.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="g in grns.data" :key="g.id" class="hover:bg-muted/30 transition-colors">
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="size-8 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <PackageOpen class="size-4" />
                                </div>
                                <div>
                                    <Link :href="route('grns.show', g.id)" class="font-medium text-foreground font-mono hover:text-primary transition-colors">
                                        {{ g.grn_number }}
                                    </Link>
                                    <p v-if="g.has_discrepancy" class="text-[10px] text-amber-700 flex items-center gap-1">
                                        <AlertTriangle class="size-3" /> Discrepancy
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell class="font-mono text-xs">
                            <Link :href="route('purchase-orders.show', g.purchase_order_id)" class="hover:text-primary">
                                {{ g.purchase_order?.po_number }}
                            </Link>
                        </TableCell>
                        <TableCell>
                            <p class="font-medium">{{ g.supplier?.name ?? '—' }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">{{ g.supplier?.code }}</p>
                        </TableCell>
                        <TableCell class="text-xs">{{ formatDate(g.received_date) }}</TableCell>
                        <TableCell>
                            <GrnStatusBadge :status="g.status" />
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <ActionGroup>
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
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="grns.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="grns" />
            </div>
        </section>
    </AppLayout>
</template>
