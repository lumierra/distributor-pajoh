<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    BarChart3,
    Boxes,
    ClipboardList,
    LayoutDashboard,
    PackageX,
    ReceiptText,
    ShoppingBag,
    Store,
    TrendingUp,
    Truck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    salesTrend: { type: Array, default: () => [] },
    recentSalesOrders: { type: Array, default: () => [] },
    lowStock: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user || null);
const firstName = computed(() => user.value?.name?.split(' ')[0] || 'Owner');

const today = computed(() =>
    new Date().toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

function rupiah(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(Number(v) || 0));
}

function rupiahShort(v) {
    const n = Number(v) || 0;
    if (n >= 1_000_000_000) return 'Rp ' + (n / 1_000_000_000).toFixed(1).replace('.0', '') + ' M';
    if (n >= 1_000_000) return 'Rp ' + (n / 1_000_000).toFixed(1).replace('.0', '') + ' jt';
    if (n >= 1_000) return 'Rp ' + Math.round(n / 1_000) + ' rb';
    return rupiah(n);
}

// ── Stat tiles (macOS soft-red) ──
const operationalTiles = computed(() => [
    { label: 'Penjualan Bulan Ini', value: rupiahShort(props.stats.sales_mtd), icon: TrendingUp },
    { label: 'Invoice Outstanding', value: rupiahShort(props.stats.outstanding), icon: ReceiptText, warn: (props.stats.outstanding ?? 0) > 0 },
    { label: 'SO Menunggu Approval', value: props.stats.so_pending ?? 0, icon: ClipboardList },
    { label: 'Invoice Jatuh Tempo', value: props.stats.invoice_overdue ?? 0, icon: AlertTriangle, warn: (props.stats.invoice_overdue ?? 0) > 0 },
]);

const masterTiles = computed(() => [
    { label: 'Customer Aktif', value: props.stats.customers_active ?? 0, icon: Users },
    { label: 'Supplier Aktif', value: props.stats.suppliers_active ?? 0, icon: Store },
    { label: 'PO Berjalan', value: props.stats.po_open ?? 0, icon: Truck },
    { label: 'Produk Stok Habis', value: props.stats.products_out_of_stock ?? 0, icon: PackageX, warn: (props.stats.products_out_of_stock ?? 0) > 0 },
]);

// ── Mini bar chart ──
const maxTrend = computed(() => Math.max(1, ...props.salesTrend.map((t) => t.total)));
const hasTrend = computed(() => props.salesTrend.some((t) => t.total > 0));

// ── SO status pill ──
const SO_STATUS = {
    draft: { label: 'Draft', dot: 'bg-muted-foreground' },
    submitted: { label: 'Submitted', dot: 'bg-blue-500' },
    pending_credit_review: { label: 'Review Kredit', dot: 'bg-amber-500' },
    approved: { label: 'Approved', dot: 'bg-emerald-500' },
    partially_delivered: { label: 'Terkirim Sebagian', dot: 'bg-indigo-500' },
    delivered: { label: 'Terkirim', dot: 'bg-emerald-600' },
    rejected: { label: 'Ditolak', dot: 'bg-red-500' },
    cancelled: { label: 'Dibatalkan', dot: 'bg-red-400' },
};
const soStatus = (s) => SO_STATUS[s] ?? { label: s, dot: 'bg-muted-foreground' };

function fmtDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <PageHeader title="Dashboard" :description="today" :icon="LayoutDashboard" />

        <!-- ── Welcome banner ── -->
        <section
            class="relative overflow-hidden rounded-3xl bg-brand text-white shadow-sm ring-1 ring-brand/20 px-6 py-6 sm:px-8 sm:py-7 mb-4"
        >
            <div
                class="absolute -right-10 -top-10 size-48 rounded-full bg-white/10 blur-2xl pointer-events-none"
            />
            <div class="relative flex flex-col sm:flex-row sm:items-end sm:justify-between gap-5">
                <div>
                    <p class="text-sm text-white/80">Selamat datang kembali,</p>
                    <h2 class="text-2xl font-semibold tracking-tight mt-0.5">{{ firstName }} 👋</h2>
                    <p class="text-[13px] text-white/75 mt-1.5">
                        Ringkasan operasional distributor tersaji di sini.
                    </p>
                </div>
                <div class="flex items-center gap-8">
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Penjualan Bln Ini</p>
                        <p class="text-xl font-bold mt-0.5">{{ rupiah(stats.sales_mtd) }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-white/70">Outstanding</p>
                        <p class="text-xl font-bold mt-0.5 text-amber-100">{{ rupiah(stats.outstanding) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Stat tiles: operasional ── -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
            <div
                v-for="tile in operationalTiles"
                :key="tile.label"
                :class="[
                    'rounded-2xl ring-1 shadow-sm px-4 py-3.5 flex items-center justify-between gap-3 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5',
                    tile.warn
                        ? 'bg-amber-50 ring-amber-200/70 hover:ring-amber-300'
                        : 'bg-brand-light/60 ring-brand/10 hover:ring-brand/25',
                ]"
            >
                <div class="min-w-0">
                    <p
                        :class="[
                            'text-lg font-semibold tracking-tight leading-tight truncate',
                            tile.warn ? 'text-amber-700' : 'text-brand-dark',
                        ]"
                    >
                        {{ tile.value }}
                    </p>
                    <p
                        :class="[
                            'text-[12px] truncate leading-tight mt-0.5',
                            tile.warn ? 'text-amber-700/70' : 'text-brand-dark/70',
                        ]"
                    >
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
        </section>

        <!-- ── Stat tiles: master ── -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div
                v-for="tile in masterTiles"
                :key="tile.label"
                :class="[
                    'rounded-2xl ring-1 shadow-sm px-4 py-3.5 flex items-center justify-between gap-3 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5',
                    tile.warn
                        ? 'bg-amber-50 ring-amber-200/70 hover:ring-amber-300'
                        : 'bg-brand-light/60 ring-brand/10 hover:ring-brand/25',
                ]"
            >
                <div class="min-w-0">
                    <p
                        :class="[
                            'text-lg font-semibold tracking-tight leading-tight',
                            tile.warn ? 'text-amber-700' : 'text-brand-dark',
                        ]"
                    >
                        {{ tile.value }}
                    </p>
                    <p
                        :class="[
                            'text-[12px] truncate leading-tight mt-0.5',
                            tile.warn ? 'text-amber-700/70' : 'text-brand-dark/70',
                        ]"
                    >
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
        </section>

        <!-- ── Lower grid ── -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Sales trend chart -->
            <article
                class="lg:col-span-2 rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden"
            >
                <header class="px-5 py-4 flex items-center gap-2 border-b border-foreground/5">
                    <span class="size-7 rounded-full bg-brand-light/70 text-brand flex items-center justify-center">
                        <BarChart3 class="size-4" />
                    </span>
                    <h2 class="text-sm font-semibold">Tren Penjualan 6 Bulan</h2>
                </header>

                <div v-if="hasTrend" class="px-5 pt-6 pb-4">
                    <div class="flex items-end justify-between gap-3 h-44">
                        <div
                            v-for="(m, i) in salesTrend"
                            :key="i"
                            class="flex-1 flex flex-col items-center gap-2 group"
                        >
                            <span
                                class="text-[10px] font-medium text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity"
                            >
                                {{ rupiahShort(m.total) }}
                            </span>
                            <div class="w-full flex items-end justify-center flex-1">
                                <div
                                    class="w-full max-w-[42px] rounded-t-lg bg-gradient-to-t from-brand/70 to-brand transition-all duration-300 group-hover:from-brand group-hover:to-brand-dark"
                                    :style="{ height: Math.max(4, (m.total / maxTrend) * 100) + '%' }"
                                />
                            </div>
                            <span class="text-[11px] text-muted-foreground">{{ m.label }}</span>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="px-5 py-16 flex flex-col items-center gap-2 text-center"
                >
                    <span class="size-12 rounded-full bg-muted/60 text-muted-foreground flex items-center justify-center">
                        <BarChart3 class="size-6" />
                    </span>
                    <p class="text-sm text-muted-foreground">Belum ada penjualan tercatat.</p>
                    <Link
                        :href="route('sales-orders.index')"
                        class="text-[13px] font-medium text-brand hover:text-brand-dark inline-flex items-center gap-1"
                    >
                        Buat Sales Order <ArrowRight class="size-3.5" />
                    </Link>
                </div>
            </article>

            <!-- Low stock -->
            <article
                class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden"
            >
                <header class="px-5 py-4 flex items-center gap-2 border-b border-foreground/5">
                    <span class="size-7 rounded-full bg-brand-light/70 text-brand flex items-center justify-center">
                        <Boxes class="size-4" />
                    </span>
                    <h2 class="text-sm font-semibold">Stok Terendah</h2>
                </header>

                <ul v-if="lowStock.length" class="divide-y divide-foreground/5">
                    <li
                        v-for="p in lowStock"
                        :key="p.id"
                        class="flex items-center justify-between gap-3 px-5 py-3"
                    >
                        <div class="min-w-0">
                            <p class="text-sm text-foreground truncate">{{ p.name }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">{{ p.sku }}</p>
                        </div>
                        <span
                            :class="[
                                'text-xs font-semibold rounded-full px-2 py-0.5 shrink-0 tabular-nums',
                                p.stock_real <= 0
                                    ? 'bg-red-50 text-red-600'
                                    : 'bg-emerald-50 text-emerald-700',
                            ]"
                        >
                            {{ p.stock_real }}
                        </span>
                    </li>
                </ul>
                <div v-else class="px-5 py-12 flex flex-col items-center gap-2 text-center">
                    <span class="size-11 rounded-full bg-muted/60 text-muted-foreground flex items-center justify-center">
                        <Boxes class="size-5" />
                    </span>
                    <p class="text-sm text-muted-foreground">Belum ada produk.</p>
                </div>
            </article>
        </section>

        <!-- ── Recent Sales Orders ── -->
        <section
            class="mt-4 rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden"
        >
            <header class="px-5 py-4 flex items-center justify-between gap-2 border-b border-foreground/5">
                <div class="flex items-center gap-2">
                    <span class="size-7 rounded-full bg-brand-light/70 text-brand flex items-center justify-center">
                        <ShoppingBag class="size-4" />
                    </span>
                    <h2 class="text-sm font-semibold">Sales Order Terbaru</h2>
                </div>
                <Link
                    :href="route('sales-orders.index')"
                    class="text-[13px] font-medium text-brand hover:text-brand-dark inline-flex items-center gap-1"
                >
                    Lihat semua <ArrowRight class="size-3.5" />
                </Link>
            </header>

            <ul v-if="recentSalesOrders.length" class="divide-y divide-foreground/5">
                <li
                    v-for="so in recentSalesOrders"
                    :key="so.id"
                    class="flex items-center gap-3 px-5 py-3 hover:bg-muted/30 transition-colors"
                >
                    <Link
                        :href="route('sales-orders.show', so.id)"
                        class="flex-1 min-w-0 flex items-center gap-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-foreground font-mono">{{ so.so_number }}</p>
                            <p class="text-[12px] text-muted-foreground truncate">{{ so.customer_name }}</p>
                        </div>
                        <span class="text-[12px] text-muted-foreground shrink-0 hidden sm:block">
                            {{ fmtDate(so.so_date) }}
                        </span>
                        <span class="text-sm font-semibold text-foreground shrink-0 tabular-nums w-28 text-right">
                            {{ rupiah(so.total) }}
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-muted/60 px-2.5 py-1 text-[11px] font-medium text-foreground shrink-0 w-40 justify-center"
                        >
                            <span :class="['size-1.5 rounded-full', soStatus(so.status).dot]" />
                            {{ soStatus(so.status).label }}
                        </span>
                    </Link>
                </li>
            </ul>
            <div v-else class="px-5 py-14 flex flex-col items-center gap-2 text-center">
                <span class="size-12 rounded-full bg-muted/60 text-muted-foreground flex items-center justify-center">
                    <ShoppingBag class="size-6" />
                </span>
                <p class="text-sm text-muted-foreground">Belum ada Sales Order.</p>
                <Link
                    :href="route('sales-orders.index')"
                    class="text-[13px] font-medium text-brand hover:text-brand-dark inline-flex items-center gap-1"
                >
                    Buat Sales Order <ArrowRight class="size-3.5" />
                </Link>
            </div>
        </section>
    </AppLayout>
</template>
