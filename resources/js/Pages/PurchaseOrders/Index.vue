<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Eye,
    FileText,
    Plus,
    RotateCcw,
    Search,
    ShoppingCart,
    Truck,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import StatCard from '@/Components/Shared/StatCard.vue';
import PoStatusBadge from '@/Components/PurchaseOrders/PoStatusBadge.vue';
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
    purchaseOrders: { type: Object, required: true },
    suppliers: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(() => page.props.auth?.user?.is_superadmin);

const ALL = 'all';

const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
    supplier_id: props.filters.supplier_id ? String(props.filters.supplier_id) : ALL,
    year: props.filters.year || ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('purchase-orders.index'),
            {
                q: filters.q,
                status: filters.status === ALL ? '' : filters.status,
                supplier_id: filters.supplier_id === ALL ? '' : filters.supplier_id,
                year: filters.year === ALL ? '' : filters.year,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.status = ALL;
    filters.supplier_id = ALL;
    filters.year = ALL;
}

const currentYear = new Date().getFullYear();
const years = computed(() => [currentYear, currentYear - 1, currentYear - 2]);

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head title="Purchase Order" />

    <AppLayout>
        <PageHeader
            title="Purchase Order"
            description="Pemesanan barang ke supplier. PO → GRN → stok masuk."
            :icon="ShoppingCart"
        >
            <template #actions>
                <Button v-if="canCreate" as-child size="default" variant="secondary">
                    <Link :href="route('purchase-orders.create')">
                        <Plus class="size-4" />
                        Buat PO
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <StatCard label="Total PO" :value="stats.total ?? 0" tone="brand">
                <template #icon><ShoppingCart class="size-5" /></template>
            </StatCard>
            <StatCard label="Draft" :value="stats.draft ?? 0" tone="brand">
                <template #icon><FileText class="size-5" /></template>
            </StatCard>
            <StatCard label="Approved" :value="stats.approved ?? 0" tone="brand">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
            <StatCard label="Partial Received" :value="stats.partial ?? 0" tone="brand-orange">
                <template #icon><Truck class="size-5" /></template>
            </StatCard>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        v-model="filters.q"
                        placeholder="Cari no. PO atau nama supplier…"
                        class="pl-8 h-9 rounded-md"
                    />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[150px] h-9 rounded-md">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="approved">Approved</SelectItem>
                            <SelectItem value="partial_received">Partial</SelectItem>
                            <SelectItem value="closed">Closed</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.supplier_id">
                        <SelectTrigger class="w-[180px] h-9 rounded-md">
                            <SelectValue placeholder="Supplier" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Supplier</SelectItem>
                            <SelectItem v-for="s in suppliers" :key="s.id" :value="String(s.id)">
                                {{ s.name }}
                            </SelectItem>
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
                        <TableHead class="pl-4">PO</TableHead>
                        <TableHead>Supplier</TableHead>
                        <TableHead>Tgl PO</TableHead>
                        <TableHead>ETA</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="purchaseOrders.data.length === 0">
                        <TableCell colspan="7" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <ShoppingCart class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada PO.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="po in purchaseOrders.data" :key="po.id" class="hover:bg-muted/30 transition-colors">
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="size-8 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <ShoppingCart class="size-4" />
                                </div>
                                <Link
                                    :href="route('purchase-orders.show', po.id)"
                                    class="font-medium text-foreground font-mono hover:text-primary transition-colors"
                                >
                                    {{ po.po_number }}
                                </Link>
                            </div>
                        </TableCell>
                        <TableCell>
                            <p class="font-medium">{{ po.supplier?.name ?? '—' }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">{{ po.supplier?.code }}</p>
                        </TableCell>
                        <TableCell class="text-xs">{{ formatDate(po.po_date) }}</TableCell>
                        <TableCell class="text-xs">{{ formatDate(po.eta_date) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ formatRp(po.total) }}</TableCell>
                        <TableCell>
                            <PoStatusBadge :status="po.status" />
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                    <Link :href="route('purchase-orders.show', po.id)">
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                </ActionButton>
                                <ActionButton v-if="po.pdf_path" :icon="FileText" label="PDF" as-child tone="blue">
                                    <a :href="route('purchase-orders.pdf', po.id)" target="_blank" rel="noopener">
                                        <FileText class="w-4 h-4" />
                                    </a>
                                </ActionButton>
                            </ActionGroup>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="purchaseOrders.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="purchaseOrders" />
            </div>
        </section>
    </AppLayout>
</template>
