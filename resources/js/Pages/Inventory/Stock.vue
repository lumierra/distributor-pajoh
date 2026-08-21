<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowDown, ArrowUp, BookOpen, Clock, Gift, History, Package, PackageCheck, Ruler } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { breakdownUnits, formatBreakdown } from '@/lib/uom';
import { Button } from '@/Components/ui/button';
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
    product: { type: Object, required: true },
    balances: { type: Array, required: true },
    summary: { type: Object, default: null },
    ledger: { type: Array, default: () => [] },
});

const units = computed(() => props.product.units ?? []);
const baseUnitName = computed(() => props.product.base_unit?.name ?? '');

// Daftar satuan produk terurut besar→kecil untuk referensi konversi.
const unitLadder = computed(() =>
    [...units.value].sort((a, b) => (b.qty_to_base ?? 1) - (a.qty_to_base ?? 1)),
);

function breakdown(baseQty) {
    return breakdownUnits(baseQty, units.value);
}

const summaryTiles = computed(() => {
    const s = props.summary;
    if (!s) return [];
    return [
        { label: 'Stok Real', value: s.stock_real ?? 0, icon: PackageCheck },
        { label: 'Bonus', value: s.bonus ?? 0, icon: Gift },
        { label: 'Pending', value: s.pending ?? 0, icon: Clock, warn: true },
    ];
});

function formatDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

// ── Riwayat mutasi (selaras dengan halaman Stock Ledger) ──────────────
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
function typeClass(t) {
    if (t.endsWith('_in') || t === 'return_in') return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    if (t.endsWith('_out') || t === 'write_off') return 'bg-red-50 text-red-700 ring-red-200';
    return 'bg-muted text-muted-foreground';
}

// Qty mutasi dalam satuan terbesar (mis. 180 base → "15 KRT").
function qtyDisplay(baseQty) {
    return units.value.length ? formatBreakdown(baseQty, units.value) : Number(baseQty).toLocaleString('id-ID');
}

// Ref → label ramah + route tujuan (kalau ada). ref_type retur ditulis PascalCase.
const REF_MAP = {
    GRN: { label: 'GRN', route: null },
    DO: { label: 'Surat Jalan', route: 'delivery-orders.show' },
    SO: { label: 'Sales Order', route: 'sales-orders.show' },
    PO: { label: 'Purchase Order', route: 'purchase-orders.show' },
    stock_opening: { label: 'Stok Awal', route: 'openings.show' },
    stock_adjustment: { label: 'Penyesuaian', route: 'adjustments.show' },
    stock_opname: { label: 'Stock Opname', route: 'opnames.show' },
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

function expiredBadge(b) {
    if (!b.batch?.expired_date) return null;
    const days = Math.ceil((new Date(b.batch.expired_date) - new Date()) / (1000 * 60 * 60 * 24));
    if (days < 0) return { label: `Expired ${Math.abs(days)}h lalu`, text: 'text-red-700', dot: 'bg-red-500' };
    if (days <= 30) return { label: `Expire ${days}h lagi`, text: 'text-amber-700', dot: 'bg-amber-500' };
    return null;
}
</script>

<template>
    <Head :title="`Stok: ${product.name}`" />

    <AppLayout>
        <PageHeader
            :title="`Stok — ${product.name}`"
            :description="`${product.sku} · ${product.category?.name ?? 'Tanpa kategori'}`"
            :icon="Package"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('stocks.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Stok
                    </Link>
                </Button>
                <Button as-child variant="outline" size="default" class="rounded-full">
                    <Link :href="route('stock-ledger.index', { product_id: product.id })">
                        <BookOpen class="size-4" />
                        Ledger Produk
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Ringkasan stok (base unit) + breakdown antar-satuan -->
        <section v-if="summary" class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-3">
            <div
                v-for="tile in summaryTiles"
                :key="tile.label"
                :class="[
                    'rounded-2xl ring-1 shadow-sm px-4 py-3.5 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5',
                    tile.warn ? 'bg-amber-50 ring-amber-200/70 hover:ring-amber-300' : 'bg-brand-light/60 ring-brand/10 hover:ring-brand/25',
                ]"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p :class="['text-lg font-semibold tracking-tight leading-tight', tile.warn ? 'text-amber-700' : 'text-brand-dark']">
                            {{ Number(tile.value).toLocaleString('id-ID') }}
                            <span :class="['text-[11px] font-normal', tile.warn ? 'text-amber-700/70' : 'text-brand-dark/60']">{{ baseUnitName }}</span>
                        </p>
                        <p :class="['text-[12px] truncate leading-tight mt-0.5', tile.warn ? 'text-amber-700/80' : 'text-brand-dark/70']">
                            {{ tile.label }}
                        </p>
                    </div>
                    <div
                        :class="[
                            'size-9 rounded-full flex items-center justify-center shrink-0',
                            tile.warn ? 'bg-amber-500/15 text-amber-600' : 'bg-brand/10 text-brand',
                        ]"
                    >
                        <component :is="tile.icon" class="size-4.5" />
                    </div>
                </div>
                <!-- Breakdown antar-satuan -->
                <p
                    v-if="breakdown(tile.value).length > 1"
                    :class="['text-[11px] font-mono mt-1.5 leading-tight', tile.warn ? 'text-amber-700/70' : 'text-brand-dark/60']"
                >
                    = {{ breakdown(tile.value).map((p) => `${p.qty} ${p.unit}`).join(' + ') }}
                </p>
            </div>
        </section>

        <p v-if="summary && summary.pending > 0" class="text-[12px] text-muted-foreground mb-4">
            <Clock class="size-3 inline -mt-0.5" />
            Pending = barang dipesan / menurut surat jalan yang belum datang. Belum jadi stok & belum punya batch.
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            <!-- Stok per batch -->
            <section class="lg:col-span-3 rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
                <header class="border-b border-foreground/5 px-5 py-3.5 flex items-center justify-between">
                    <h3 class="text-sm font-semibold">Stok per Batch</h3>
                    <span class="text-[12px] text-muted-foreground">Angka dalam satuan dasar: <strong>{{ baseUnitName }}</strong></span>
                </header>
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                                <TableHead class="pl-5">Batch</TableHead>
                                <TableHead>Supplier</TableHead>
                                <TableHead>Expired</TableHead>
                                <TableHead class="text-right pr-5">On Hand</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody class="text-sm">
                            <TableRow v-if="balances.length === 0">
                                <TableCell colspan="4" class="text-center py-16 text-muted-foreground">
                                    Belum ada batch — tunggu GRN posted.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="b in balances" :key="b.id" class="hover:bg-foreground/2.5 transition-colors border-foreground/5 align-top">
                                <TableCell class="pl-5 py-3">
                                    <p class="font-mono text-xs font-medium">{{ b.batch?.batch_code }}</p>
                                    <span
                                        v-if="expiredBadge(b)"
                                        :class="['mt-1 inline-flex items-center gap-1.5 text-[11px] font-medium', expiredBadge(b).text]"
                                    >
                                        <span :class="['size-1.5 rounded-full', expiredBadge(b).dot]" />
                                        {{ expiredBadge(b).label }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-xs">
                                    <span v-if="b.batch?.supplier">
                                        {{ b.batch.supplier.name }}
                                        <span class="text-muted-foreground font-mono">({{ b.batch.supplier.code }})</span>
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="text-xs">{{ formatDate(b.batch?.expired_date) }}</TableCell>
                                <TableCell class="pr-5 text-right">
                                    <p class="font-mono font-bold">{{ Number(b.qty_on_hand).toLocaleString('id-ID') }}</p>
                                    <p v-if="breakdown(b.qty_on_hand).length" class="text-[11px] text-muted-foreground font-mono leading-tight">
                                        {{ breakdown(b.qty_on_hand).map((p) => `${p.qty} ${p.unit}`).join(' + ') }}
                                    </p>
                                    <p v-if="Number(b.qty_bonus_pool) > 0" class="text-[11px] text-emerald-600 leading-tight">
                                        (termasuk bonus {{ Number(b.qty_bonus_pool).toLocaleString('id-ID') }})
                                    </p>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </section>

            <!-- Referensi konversi satuan -->
            <aside class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden self-start">
                <header class="border-b border-foreground/5 px-5 py-3.5 flex items-center gap-2">
                    <span class="size-7 rounded-full bg-brand-light/70 text-brand flex items-center justify-center">
                        <Ruler class="size-4" />
                    </span>
                    <h3 class="text-sm font-semibold">Konversi Satuan</h3>
                </header>
                <ul class="divide-y divide-foreground/5">
                    <li v-for="u in unitLadder" :key="u.id" class="px-5 py-3 flex items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium">{{ u.name }}</p>
                            <p v-if="Number(u.qty_to_base) === 1" class="text-[11px] text-emerald-700">Satuan dasar</p>
                        </div>
                        <p class="text-xs text-muted-foreground font-mono">
                            1 = {{ Number(u.qty_to_base).toLocaleString('id-ID') }} {{ baseUnitName }}
                        </p>
                    </li>
                </ul>
            </aside>
        </div>

        <!-- Riwayat mutasi stok (25 terakhir) -->
        <section class="mt-4 rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <header class="border-b border-foreground/5 px-5 py-3.5 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="size-7 rounded-full bg-brand-light/70 text-brand flex items-center justify-center">
                        <History class="size-4" />
                    </span>
                    <h3 class="text-sm font-semibold">Riwayat Mutasi</h3>
                    <span class="text-[11px] text-muted-foreground">25 terakhir</span>
                </div>
                <Button as-child variant="ghost" size="sm" class="rounded-full">
                    <Link :href="route('stock-ledger.index', { product_id: product.id })">
                        Lihat semua
                    </Link>
                </Button>
            </header>
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                            <TableHead class="pl-5">Waktu</TableHead>
                            <TableHead>Tipe</TableHead>
                            <TableHead>Batch</TableHead>
                            <TableHead class="text-right">Masuk</TableHead>
                            <TableHead class="text-right">Keluar</TableHead>
                            <TableHead>Ref</TableHead>
                            <TableHead class="pr-5">Oleh</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody class="text-sm">
                        <TableRow v-if="ledger.length === 0">
                            <TableCell colspan="7" class="text-center py-12 text-muted-foreground">
                                Belum ada mutasi untuk produk ini.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="l in ledger" :key="l.id" class="hover:bg-foreground/2.5 transition-colors border-foreground/5">
                            <TableCell class="pl-5 py-2 text-xs whitespace-nowrap">{{ formatDateTime(l.created_at) }}</TableCell>
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
                            <TableCell class="text-xs font-mono">{{ l.batch?.batch_code ?? '—' }}</TableCell>
                            <TableCell class="text-right font-mono text-emerald-700">
                                {{ l.qty_in > 0 ? qtyDisplay(l.qty_in) : '—' }}
                            </TableCell>
                            <TableCell class="text-right font-mono text-red-700">
                                {{ l.qty_out > 0 ? qtyDisplay(l.qty_out) : '—' }}
                            </TableCell>
                            <TableCell class="text-xs">
                                <Link v-if="refRoute(l)" :href="refRoute(l)" class="text-primary hover:underline">{{ refLabel(l) }}</Link>
                                <span v-else class="text-muted-foreground">{{ refLabel(l) }}</span>
                            </TableCell>
                            <TableCell class="pr-5 text-xs">{{ l.creator?.name ?? '—' }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </section>
    </AppLayout>
</template>
