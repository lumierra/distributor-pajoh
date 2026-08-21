<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowLeft, ArrowUp, BookOpen, Package, RotateCcw } from '@lucide/vue';
import { reactive, watch } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import { formatBreakdown } from '@/lib/uom';
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
    ledger: { type: Object, required: true },
    products: { type: Array, required: true },
    types: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const ALL = 'all';

const filters = reactive({
    product_id: props.filters.product_id ? String(props.filters.product_id) : ALL,
    type: props.filters.type || ALL,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('stock-ledger.index'),
            {
                product_id: filters.product_id === ALL ? '' : filters.product_id,
                type: filters.type === ALL ? '' : filters.type,
                from: filters.from || '',
                to: filters.to || '',
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.product_id = ALL;
    filters.type = ALL;
    filters.from = '';
    filters.to = '';
}

function formatDateTime(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function typeClass(t) {
    if (t.endsWith('_in') || t === 'return_in') return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    if (t.endsWith('_out') || t === 'write_off') return 'bg-red-50 text-red-700 ring-red-200';
    return 'bg-muted text-muted-foreground';
}

// Label tipe mutasi dalam Bahasa Indonesia.
const TYPE_LABEL = {
    purchase_in: 'Penerimaan (GRN)',
    bonus_in: 'Bonus Masuk',
    opening_in: 'Stok Awal',
    return_in: 'Retur Customer Masuk',
    adjustment_in: 'Penyesuaian (+)',
    opname_in: 'Opname (+)',
    sale_out: 'Penjualan',
    return_out_to_supplier: 'Retur ke Supplier',
    adjustment_out: 'Penyesuaian (−)',
    opname_out: 'Opname (−)',
    write_off: 'Write-off',
};
function typeLabel(t) {
    return TYPE_LABEL[t] ?? t;
}

// Qty dalam satuan terbesar (mis. 180 base → "15 KRT").
function qtyDisplay(l, base) {
    const units = l.product?.units ?? [];
    return units.length ? formatBreakdown(base, units) : Number(base).toLocaleString('id-ID');
}

// Ref → label ramah + route tujuan (kalau ada).
const REF_MAP = {
    GRN: { label: 'GRN', route: null },
    DO: { label: 'Surat Jalan', route: 'delivery-orders.show' },
    SO: { label: 'Sales Order', route: 'sales-orders.show' },
    PO: { label: 'Purchase Order', route: 'purchase-orders.show' },
    stock_opening: { label: 'Stok Awal', route: 'openings.show' },
    stock_adjustment: { label: 'Penyesuaian', route: 'adjustments.show' },
    stock_opname: { label: 'Stock Opname', route: 'opnames.show' },
    customer_return: { label: 'Retur Customer', route: null },
    supplier_return: { label: 'Retur Supplier', route: null },
    // ref_type sebenarnya ditulis PascalCase oleh service retur.
    CustomerReturn: { label: 'Retur Customer', route: null },
    SupplierReturn: { label: 'Retur Supplier', route: null },
};
function refLabel(l) {
    const m = REF_MAP[l.ref_type];
    return m ? `${m.label} #${l.ref_id}` : `${l.ref_type} #${l.ref_id}`;
}
function refRoute(l) {
    const m = REF_MAP[l.ref_type];
    if (!m?.route || !l.ref_id) return null;
    try {
        return route(m.route, l.ref_id);
    } catch {
        return null;
    }
}
</script>

<template>
    <Head title="Stock Ledger" />

    <AppLayout>
        <PageHeader title="Stock Ledger" description="Journal mutasi stok (immutable). Source of truth." :icon="BookOpen">
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('stocks.index')">
                        <ArrowLeft class="size-4" />
                        Stok per Produk
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-foreground/5 flex flex-col sm:flex-row sm:items-center gap-2">
                <Select v-model="filters.product_id">
                    <SelectTrigger class="h-9 rounded-full bg-muted/50 border-transparent w-full sm:w-[260px]">
                        <SelectValue placeholder="Pilih produk" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Produk</SelectItem>
                        <SelectItem v-for="p in products" :key="p.id" :value="String(p.id)">
                            {{ p.name }} <span class="text-xs text-muted-foreground font-mono ml-1">{{ p.sku }}</span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="filters.type">
                    <SelectTrigger class="w-[180px] h-9 rounded-full bg-muted/50 border-transparent">
                        <SelectValue placeholder="Tipe" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Tipe</SelectItem>
                        <SelectItem v-for="t in types" :key="t" :value="t">{{ typeLabel(t) }}</SelectItem>
                    </SelectContent>
                </Select>
                <Input v-model="filters.from" type="date" class="h-9 w-[150px] rounded-full bg-muted/50 border-transparent" placeholder="Dari" />
                <Input v-model="filters.to" type="date" class="h-9 w-[150px] rounded-full bg-muted/50 border-transparent" placeholder="Sampai" />
                <Button type="button" variant="ghost" size="default" class="rounded-full" @click="reset">
                    <RotateCcw class="size-3.5" /> Reset
                </Button>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Waktu</TableHead>
                        <TableHead>Produk</TableHead>
                        <TableHead>Batch</TableHead>
                        <TableHead>Tipe</TableHead>
                        <TableHead class="text-right">Masuk</TableHead>
                        <TableHead class="text-right">Keluar</TableHead>
                        <TableHead class="text-right">Cost/Unit</TableHead>
                        <TableHead>Ref</TableHead>
                        <TableHead class="pr-4">By</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="ledger.data.length === 0">
                        <TableCell colspan="9" class="text-center py-16 text-muted-foreground">
                            Tidak ada entri ledger.
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="l in ledger.data" :key="l.id" class="hover:bg-foreground/2.5 transition-colors border-foreground/5">
                        <TableCell class="pl-4 py-2 text-xs whitespace-nowrap">{{ formatDateTime(l.created_at) }}</TableCell>
                        <TableCell class="text-xs">
                            <p class="font-medium">{{ l.product?.name }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">{{ l.product?.sku }}</p>
                        </TableCell>
                        <TableCell class="text-xs font-mono">{{ l.batch?.batch_code ?? '—' }}</TableCell>
                        <TableCell>
                            <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium ring-1', typeClass(l.type)]">
                                <ArrowUp v-if="l.qty_in > 0" class="size-3" />
                                <ArrowDown v-else class="size-3" />
                                {{ typeLabel(l.type) }}
                            </span>
                            <span v-if="l.is_bonus_pool" class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                BONUS
                            </span>
                        </TableCell>
                        <TableCell class="text-right font-mono text-emerald-700">
                            {{ l.qty_in > 0 ? qtyDisplay(l, l.qty_in) : '—' }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-red-700">
                            {{ l.qty_out > 0 ? qtyDisplay(l, l.qty_out) : '—' }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-xs text-muted-foreground">
                            {{ Number(l.cost_price) > 0 ? 'Rp ' + Number(l.cost_price).toLocaleString('id-ID') : '—' }}
                        </TableCell>
                        <TableCell class="text-xs">
                            <Link v-if="refRoute(l)" :href="refRoute(l)" class="text-primary hover:underline">{{ refLabel(l) }}</Link>
                            <span v-else class="text-muted-foreground">{{ refLabel(l) }}</span>
                        </TableCell>
                        <TableCell class="pr-4 text-xs">{{ l.creator?.name ?? '—' }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="ledger.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="ledger" />
            </div>
        </section>
    </AppLayout>
</template>
