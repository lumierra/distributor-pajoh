<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import {
    Activity,
    CreditCard,
    LayoutDashboard,
    PackageOpen,
    ReceiptText,
    ShoppingBag,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import StatCard from '@/Components/Shared/StatCard.vue';
import WelcomeBanner from '@/Components/Shared/WelcomeBanner.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    appName: { type: String, default: 'Pajoh Distributor' },
    laravelVersion: { type: String, default: '' },
    phpVersion: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user || null);

const today = computed(() => {
    return new Date().toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <PageHeader title="Dashboard" :description="today" :icon="LayoutDashboard" />

        <WelcomeBanner
            :title="`Selamat datang kembali, ${user?.name?.split(' ')[0] || 'Owner'} 👋`"
            subtitle="Semua data distributor tersaji di sini."
        >
            <template #right>
                <div class="flex items-center gap-6 sm:gap-8">
                    <div class="text-right">
                        <p class="text-[11px] uppercase tracking-wide text-white/80">
                            Total Penjualan
                        </p>
                        <p class="text-xl font-bold mt-0.5">Rp 0</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[11px] uppercase tracking-wide text-white/80">
                            Invoice Outstanding
                        </p>
                        <p class="text-xl font-bold mt-0.5 text-warning-light">Rp 0</p>
                    </div>
                </div>
            </template>
        </WelcomeBanner>

        <!-- Stat strip — 4 kolom -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <StatCard label="Total Sales Order" value="0" hint="semua waktu" tone="brand">
                <template #icon><ShoppingBag class="size-5" /></template>
            </StatCard>
            <StatCard label="Stok Kritis" value="0" hint="produk under min" tone="brand">
                <template #icon><PackageOpen class="size-5" /></template>
            </StatCard>
            <StatCard label="Customer Aktif" value="0" hint="bertransaksi 30 hari" tone="brand">
                <template #icon><Users class="size-5" /></template>
            </StatCard>
            <StatCard label="Invoice Belum Bayar" value="0" hint="butuh penagihan" tone="brand-orange">
                <template #icon><ReceiptText class="size-5" /></template>
            </StatCard>
        </section>

        <!-- Row 2: gradient stat cards -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard label="Total Pendapatan" value="Rp 0" hint="semua waktu" tone="brand">
                <template #icon><CreditCard class="size-5" /></template>
            </StatCard>
            <StatCard label="Total Outlet Aktif" value="0" hint="distributor aktif" tone="brand">
                <template #icon><Activity class="size-5" /></template>
            </StatCard>
            <StatCard label="Sales Visit" value="0" hint="kunjungan hari ini" tone="brand">
                <template #icon><TrendingUp class="size-5" /></template>
            </StatCard>
            <StatCard label="Retur Pending" value="0" hint="butuh review" tone="brand-orange">
                <template #icon><PackageOpen class="size-5" /></template>
            </StatCard>
        </section>

        <!-- Lower section: 2-col layout -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <article
                class="lg:col-span-2 rounded-xl bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden"
            >
                <header class="border-b border-border/70 px-5 py-3.5 flex items-center gap-2">
                    <CreditCard class="size-4 text-muted-foreground" />
                    <h2 class="text-sm font-semibold">Pendapatan 12 Bulan Terakhir</h2>
                </header>
                <div class="p-12 text-center text-sm text-muted-foreground">
                    Belum ada data pendapatan
                </div>
            </article>

            <article class="rounded-xl bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
                <header class="px-5 pt-4 pb-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Health Distribusi
                    </p>
                </header>
                <ul class="divide-y divide-border/60 px-2 pb-2">
                    <li
                        class="flex items-center justify-between px-3 py-3 rounded-md hover:bg-muted/30 transition-colors"
                    >
                        <span class="inline-flex items-center gap-2 text-sm">
                            <Activity class="size-4 text-destructive" />
                            <span class="text-foreground">Outlet Tidak Aktif</span>
                        </span>
                        <span class="text-xs font-bold text-destructive bg-destructive/10 rounded-full px-2 py-0.5">
                            0
                        </span>
                    </li>
                    <li
                        class="flex items-center justify-between px-3 py-3 rounded-md hover:bg-muted/30 transition-colors"
                    >
                        <span class="inline-flex items-center gap-2 text-sm">
                            <Users class="size-4 text-warning" />
                            <span class="text-foreground">Sales Belum Login Hari Ini</span>
                        </span>
                        <span class="text-xs font-bold text-warning bg-warning-soft rounded-full px-2 py-0.5">
                            0
                        </span>
                    </li>
                    <li
                        class="flex items-center justify-between px-3 py-3 rounded-md hover:bg-muted/30 transition-colors"
                    >
                        <span class="inline-flex items-center gap-2 text-sm">
                            <PackageOpen class="size-4 text-warning" />
                            <span class="text-foreground">Produk Stok Habis</span>
                        </span>
                        <span class="text-xs font-bold text-warning bg-warning-soft rounded-full px-2 py-0.5">
                            0
                        </span>
                    </li>
                    <li
                        class="flex items-center justify-between px-3 py-3 rounded-md hover:bg-muted/30 transition-colors"
                    >
                        <span class="inline-flex items-center gap-2 text-sm">
                            <LayoutDashboard class="size-4 text-success" />
                            <span class="text-foreground">Sistem Sehat</span>
                        </span>
                        <span class="text-xs font-bold text-success bg-success-soft rounded-full px-2 py-0.5">
                            OK
                        </span>
                    </li>
                </ul>
            </article>
        </section>

        <footer class="mt-6 rounded-xl bg-card ring-1 ring-foreground/5 shadow-sm px-5 py-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                Stack &amp; Status
            </p>
            <p class="text-sm text-foreground mt-1">
                Laravel {{ laravelVersion }} · PHP {{ phpVersion }} · Fase 1 — Foundation (auth, RBAC, settings)
            </p>
        </footer>
    </AppLayout>
</template>
