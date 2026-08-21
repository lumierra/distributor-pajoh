<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    FileText,
    Receipt,
    RefreshCw,
    ShoppingBag,
    Truck,
    Wallet,
} from '@lucide/vue';
import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { confirm } from '@/Composables/useConfirm';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    invoice: { type: Object, required: true },
    canRegeneratePdf: { type: Boolean, default: false },
});

const regenForm = useForm({});

async function doRegenerate() {
    if (!(await confirm({ title: 'Re-generate PDF faktur?' }))) return;
    regenForm.post(route('invoices.regenerate-pdf', props.invoice.id), { preserveScroll: true });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}

function daysUntilDue(dueDate) {
    if (!dueDate) return null;
    return Math.ceil((new Date(dueDate) - new Date()) / (1000 * 60 * 60 * 24));
}
</script>

<template>
    <Head :title="`Faktur ${invoice.invoice_number}`" />

    <AppLayout>
        <PageHeader
            :title="`Faktur ${invoice.invoice_number}`"
            :description="`${invoice.customer?.name ?? '—'} · ${formatDate(invoice.invoice_date)}`"
            :icon="Receipt"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('invoices.index')">
                        <ArrowLeft class="size-4" /> Daftar Faktur
                    </Link>
                </Button>
                <Button as-child size="default" variant="outline" class="rounded-full">
                    <a :href="route('invoices.pdf', invoice.id)" target="_blank" rel="noopener">
                        <FileText class="size-4" /> Download PDF
                    </a>
                </Button>
                <Button v-if="canRegeneratePdf" size="default" variant="outline" class="rounded-full" @click="doRegenerate">
                    <RefreshCw class="size-4" /> Re-generate PDF
                </Button>
            </template>
        </PageHeader>

        <!-- Overdue banner -->
        <section v-if="invoice.status === 'overdue'" class="rounded-2xl bg-red-50 ring-1 ring-red-200 p-4 mb-4 flex items-start gap-3">
            <AlertTriangle class="size-5 text-red-700 mt-0.5 shrink-0" />
            <div class="text-sm text-red-900">
                <p class="font-medium">Faktur Overdue</p>
                <p class="text-xs mt-1">
                    Jatuh tempo {{ formatDate(invoice.due_date) }}
                    <span v-if="daysUntilDue(invoice.due_date) < 0"> · sudah {{ Math.abs(daysUntilDue(invoice.due_date)) }} hari lewat</span>.
                    Sisa outstanding <strong class="font-mono">{{ fmtRp(invoice.outstanding) }}</strong>.
                </p>
            </div>
        </section>

        <!-- Summary -->
        <section class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 mb-5">
            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-4 flex items-start gap-4">
                <div class="size-12 rounded-full bg-brand/10 text-brand flex items-center justify-center shrink-0">
                    <Receipt class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[12px] font-semibold uppercase tracking-wider text-muted-foreground">
                        FY {{ invoice.fiscal_year }} · {{ invoice.is_cash ? 'TUNAI' : 'KREDIT' }}
                    </p>
                    <h2 class="text-base font-bold tracking-tight text-foreground truncate font-mono">{{ invoice.invoice_number }}</h2>
                    <p class="text-xs text-muted-foreground mt-1">
                        Customer: <strong>{{ invoice.customer?.name }}</strong>
                        <span class="font-mono"> ({{ invoice.customer?.code }})</span>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <InvoiceStatusBadge :status="invoice.status" />
                    <p class="text-xs text-muted-foreground">{{ formatDate(invoice.invoice_date) }}</p>
                </div>
            </div>
        </section>

        <!-- Detail header -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                <header class="border-b border-foreground/5 px-5 py-3">
                    <h3 class="text-sm font-semibold">Tagihan</h3>
                </header>
                <dl class="px-5 py-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">Tgl Faktur</dt>
                        <dd class="mt-0.5">{{ formatDate(invoice.invoice_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">Jatuh Tempo</dt>
                        <dd class="mt-0.5">{{ formatDate(invoice.due_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">Payment Term</dt>
                        <dd class="mt-0.5">{{ invoice.payment_term_days }} hari</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">Jenis</dt>
                        <dd class="mt-0.5">{{ invoice.is_cash ? 'Tunai' : 'Kredit' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                <header class="border-b border-foreground/5 px-5 py-3">
                    <h3 class="text-sm font-semibold">Referensi</h3>
                </header>
                <dl class="px-5 py-3 text-sm space-y-1">
                    <p class="flex items-center gap-1">
                        <ShoppingBag class="size-3.5 text-muted-foreground" />
                        SO: <Link :href="route('sales-orders.show', invoice.sales_order_id)" class="font-mono hover:text-primary">{{ invoice.sales_order?.so_number }}</Link>
                    </p>
                    <p class="flex items-center gap-1">
                        <Truck class="size-3.5 text-muted-foreground" />
                        DO: <Link :href="route('delivery-orders.show', invoice.delivery_order_id)" class="font-mono hover:text-primary">{{ invoice.delivery_order?.do_number }}</Link>
                    </p>
                    <p v-if="invoice.delivery_order?.delivered_at" class="text-xs text-muted-foreground">
                        Delivered: {{ formatDate(invoice.delivery_order.delivered_at) }}
                    </p>
                    <p v-if="invoice.driver_name_snapshot" class="text-xs">
                        Driver: <strong>{{ invoice.driver_name_snapshot }}</strong>
                        <span v-if="invoice.vehicle_plate_snapshot" class="font-mono ml-1">{{ invoice.vehicle_plate_snapshot }}</span>
                    </p>
                </dl>
            </div>

            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                <header class="border-b border-foreground/5 px-5 py-3 flex items-center gap-2">
                    <Wallet class="size-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">Pembayaran</h3>
                </header>
                <dl class="px-5 py-3 text-sm space-y-1.5">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Total</span>
                        <span class="font-mono">{{ fmtRp(invoice.total) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Dibayar</span>
                        <span class="font-mono">{{ fmtRp(invoice.paid_amount) }}</span>
                    </div>
                    <div class="flex justify-between pt-1.5 border-t border-foreground/5 font-bold">
                        <span>Sisa</span>
                        <span :class="['font-mono', Number(invoice.outstanding) > 0 ? 'text-red-700' : 'text-emerald-700']">
                            {{ fmtRp(invoice.outstanding) }}
                        </span>
                    </div>
                    <p v-if="invoice.paid_at" class="text-[12px] text-emerald-700 text-right">
                        Lunas: {{ formatDate(invoice.paid_at) }}
                    </p>
                </dl>
            </div>
        </div>

        <!-- Customer info -->
        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
            <p class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Customer</p>
            <p class="font-medium">{{ invoice.customer?.name }}</p>
            <p class="text-xs text-muted-foreground font-mono">{{ invoice.customer?.code }}</p>
            <p v-if="invoice.customer?.phone" class="text-xs">Phone: {{ invoice.customer.phone }}</p>
            <p v-if="invoice.customer?.address" class="text-xs whitespace-pre-line mt-1">{{ invoice.customer.address }}</p>
            <p v-if="invoice.customer?.npwp" class="text-xs text-muted-foreground mt-1">NPWP: <span class="font-mono">{{ invoice.customer.npwp }}</span></p>
        </div>

        <!-- Items -->
        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
            <header class="border-b border-foreground/5 px-5 py-3">
                <h3 class="text-sm font-semibold">Items ({{ invoice.items?.length ?? 0 }})</h3>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                            <th class="text-left py-2.5 px-5">Produk</th>
                            <th class="text-left py-2.5 px-3">Unit</th>
                            <th class="text-right py-2.5 px-3">Qty</th>
                            <th class="text-right py-2.5 px-3">Harga</th>
                            <th class="text-right py-2.5 px-3">Diskon</th>
                            <th class="text-right py-2.5 px-5">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-foreground/5">
                        <tr v-for="i in invoice.items" :key="i.id" class="hover:bg-foreground/2.5 transition-colors">
                            <td class="py-2.5 px-5">
                                <p class="font-medium">
                                    {{ i.product_name_snapshot }}
                                    <span v-if="i.is_bonus" class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">BONUS</span>
                                </p>
                                <p class="text-[12px] text-muted-foreground font-mono">
                                    {{ i.product_sku_snapshot }}<span v-if="i.batch_code_snapshot"> · batch {{ i.batch_code_snapshot }}</span>
                                </p>
                            </td>
                            <td class="py-2.5 px-3">{{ i.product_unit_name_snapshot }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.is_bonus ? '—' : fmtRp(i.unit_price) }}</td>
                            <td class="py-2.5 px-3 text-right text-xs font-mono">
                                <span v-if="!i.is_bonus && i.discount_type && Number(i.discount_value) > 0">
                                    {{ i.discount_type === 'percent' ? `${Number(i.discount_value)}%` : fmtRp(i.discount_value) }}
                                </span>
                                <span v-else>—</span>
                            </td>
                            <td class="py-2.5 px-5 text-right font-mono">{{ fmtRp(i.line_subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-foreground/5 px-5 py-3 flex justify-end">
                <div class="w-full max-w-sm space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span class="font-mono">{{ fmtRp(invoice.subtotal) }}</span>
                    </div>
                    <div v-if="Number(invoice.header_discount_amount) > 0" class="flex justify-between text-muted-foreground">
                        <span>Diskon Header</span>
                        <span class="font-mono">− {{ fmtRp(invoice.header_discount_amount) }}</span>
                    </div>
                    <div v-if="Number(invoice.cashback_amount) > 0" class="flex justify-between text-emerald-700">
                        <span>Cashback</span>
                        <span class="font-mono">− {{ fmtRp(invoice.cashback_amount) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-foreground/5 font-bold">
                        <span>TOTAL</span>
                        <span class="font-mono">{{ fmtRp(invoice.total) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div v-if="invoice.notes || invoice.delivery_notes_snapshot" class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div v-if="invoice.notes" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5">
                <p class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan</p>
                <p class="text-sm whitespace-pre-line">{{ invoice.notes }}</p>
            </div>
            <div v-if="invoice.delivery_notes_snapshot" class="rounded-2xl bg-muted/40 p-5">
                <p class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan Pengiriman</p>
                <p class="text-sm whitespace-pre-line">{{ invoice.delivery_notes_snapshot }}</p>
            </div>
        </div>
    </AppLayout>
</template>
