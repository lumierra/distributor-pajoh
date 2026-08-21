<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarCheck } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import StatCard from '@/Components/Shared/StatCard.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    closing: { type: Object, required: true },
});

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head :title="`Closing FY ${closing.fiscal_year}`" />

    <AppLayout>
        <PageHeader :title="`Year-End Closing FY ${closing.fiscal_year}`" :description="`Status: ${closing.status}`" :icon="CalendarCheck">
            <template #actions>
                <Button as-child variant="outline">
                    <Link :href="route('year-end-closings.index')">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <StatCard label="Revenue" :value="fmtRp(closing.total_revenue)" tone="brand" />
            <StatCard label="Cost" :value="fmtRp(closing.total_cost)" tone="brand" />
            <StatCard label="Margin" :value="fmtRp(closing.total_margin)" tone="brand" />
            <StatCard label="Margin %" :value="(closing.margin_percent ?? 0) + '%'" tone="brand-orange" />
        </section>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <StatCard label="Invoices" :value="closing.invoice_count ?? 0" tone="brand" />
            <StatCard label="Customers Aktif" :value="closing.customer_count_active ?? 0" tone="brand" />
            <StatCard label="Produk Terjual" :value="closing.product_count_sold ?? 0" tone="brand" />
            <StatCard label="Carry-Over Outstanding" :value="fmtRp(closing.total_outstanding_carry_over)" tone="brand-orange" />
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4">
                <h3 class="text-sm font-semibold mb-2">Top 5 Customers</h3>
                <ol class="text-sm space-y-1.5">
                    <li v-for="(c, idx) in closing.top_5_customers ?? []" :key="idx" class="flex justify-between gap-2">
                        <span>{{ idx + 1 }}. {{ c.name }}</span>
                        <span class="font-mono text-xs">{{ fmtRp(c.revenue) }}</span>
                    </li>
                </ol>
            </section>

            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4">
                <h3 class="text-sm font-semibold mb-2">Top 5 Products</h3>
                <ol class="text-sm space-y-1.5">
                    <li v-for="(p, idx) in closing.top_5_products ?? []" :key="idx" class="flex justify-between gap-2">
                        <span>{{ idx + 1 }}. {{ p.name }}</span>
                        <span class="font-mono text-xs">{{ fmtRp(p.revenue) }}</span>
                    </li>
                </ol>
            </section>

            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4">
                <h3 class="text-sm font-semibold mb-2">Top 3 Sales</h3>
                <ol class="text-sm space-y-1.5">
                    <li v-for="(s, idx) in closing.top_3_sales ?? []" :key="idx" class="flex justify-between gap-2">
                        <span>{{ idx + 1 }}. {{ s.name }}</span>
                        <span class="font-mono text-xs">{{ fmtRp(s.revenue) }}</span>
                    </li>
                </ol>
            </section>
        </div>

        <section v-if="closing.carry_over_summary" class="mt-4 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4">
            <h3 class="text-sm font-semibold mb-2">Carry-Over Summary</h3>
            <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                <div><dt class="text-[12px] uppercase text-muted-foreground">PO</dt><dd>{{ closing.carry_over_summary.po_count ?? 0 }}</dd></div>
                <div><dt class="text-[12px] uppercase text-muted-foreground">SO</dt><dd>{{ closing.carry_over_summary.so_count ?? 0 }}</dd></div>
                <div><dt class="text-[12px] uppercase text-muted-foreground">Invoice</dt><dd>{{ closing.carry_over_summary.invoice_count ?? 0 }}</dd></div>
                <div><dt class="text-[12px] uppercase text-muted-foreground">CN</dt><dd>{{ closing.carry_over_summary.cn_count ?? 0 }}</dd></div>
            </dl>
        </section>

        <section class="mt-4 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 text-xs space-y-1">
            <h3 class="text-sm font-semibold mb-2">Process Timeline</h3>
            <p><span class="text-muted-foreground">Pre-check passed:</span> {{ fmt(closing.pre_check_passed_at) }}</p>
            <p><span class="text-muted-foreground">Carry-over done:</span> {{ fmt(closing.carry_over_done_at) }}</p>
            <p><span class="text-muted-foreground">Sequence reset:</span> {{ fmt(closing.sequence_reset_done_at) }}</p>
            <p><span class="text-muted-foreground">Summary generated:</span> {{ fmt(closing.summary_generated_at) }}</p>
            <p><span class="text-muted-foreground">Closed at:</span> {{ fmt(closing.closed_at) }}</p>
            <p><span class="text-muted-foreground">By:</span> {{ closing.closer?.name ?? '—' }}</p>
        </section>
    </AppLayout>
</template>
