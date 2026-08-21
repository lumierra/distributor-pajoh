<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    CheckCircle2,
    ClipboardCheck,
    Pencil,
    Plus,
    ReceiptText,
    Send,
    ShieldCheck,
    ShoppingBag,
    Truck,
    Undo2,
    Wrench,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { confirm } from '@/Composables/useConfirm';
import SoStatusBadge from '@/Components/SalesOrders/SoStatusBadge.vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    salesOrder: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canSubmit: { type: Boolean, default: false },
    canApprove: { type: Boolean, default: false },
    canApproveOverride: { type: Boolean, default: false },
    canReject: { type: Boolean, default: false },
    canCancel: { type: Boolean, default: false },
    canAdjust: { type: Boolean, default: false },
});

const overrideOpen = ref(false);
const rejectOpen = ref(false);
const cancelOpen = ref(false);
const adjustOpen = ref(false);

// Faktur pertama yang masih ada sisa tagihan (untuk prefill retur).
const invoiceForReturn = () => {
    const list = props.salesOrder.invoices ?? [];
    return list.find((i) => Number(i.outstanding) > 0) ?? list[0] ?? null;
};

// URL "kurangi tagihan / retur" — prefill customer + faktur bila ada.
function returnUrl() {
    const params = { customer_id: props.salesOrder.customer?.id };
    const inv = invoiceForReturn();
    if (inv) params.invoice_id = inv.id;
    return route('customer-returns.create', params);
}

// URL "tambah barang" — SO baru untuk customer sama.
function newSoUrl() {
    return route('sales-orders.create', { customer_id: props.salesOrder.customer?.id });
}

const DO_STATUS = {
    draft: 'Draft', picking: 'Picking', packed: 'Packed', in_transit: 'Dikirim',
    delivered: 'Terkirim', partial_returned: 'Sebagian Diretur', cancelled: 'Dibatalkan',
};
const INV_STATUS = {
    open: 'Open', partial_paid: 'Sebagian', paid: 'Lunas', overdue: 'Jatuh Tempo',
};

const submitForm = useForm({});
const approveForm = useForm({});
const overrideForm = useForm({ reason: '' });
const rejectForm = useForm({ rejection_reason: '' });
const cancelForm = useForm({ cancel_reason: '' });

async function doSubmit() {
    if (!(await confirm({ title: 'Submit SO ini ke admin untuk review?' }))) return;
    submitForm.post(route('sales-orders.submit', props.salesOrder.id), { preserveScroll: true });
}

async function doApprove() {
    if (!(await confirm({ title: 'Approve SO ini?', description: 'Stok akan ter-reserve.' }))) return;
    approveForm.post(route('sales-orders.approve', props.salesOrder.id), { preserveScroll: true });
}

function doApproveOverride() {
    overrideForm.post(route('sales-orders.approve-override', props.salesOrder.id), {
        preserveScroll: true,
        onSuccess: () => (overrideOpen.value = false),
    });
}

function doReject() {
    rejectForm.post(route('sales-orders.reject', props.salesOrder.id), {
        preserveScroll: true,
        onSuccess: () => (rejectOpen.value = false),
    });
}

function doCancel() {
    cancelForm.post(route('sales-orders.cancel', props.salesOrder.id), {
        preserveScroll: true,
        onSuccess: () => (cancelOpen.value = false),
    });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head :title="`SO ${salesOrder.so_number}`" />

    <AppLayout>
        <PageHeader
            :title="`SO ${salesOrder.so_number}`"
            :description="`${salesOrder.customer?.name ?? '—'} · ${formatDate(salesOrder.so_date)}`"
            :icon="ShoppingBag"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('sales-orders.index')">
                        <ArrowLeft class="size-4" /> Daftar SO
                    </Link>
                </Button>
                <Button v-if="canEdit" as-child size="default" variant="outline" class="rounded-full">
                    <Link :href="route('sales-orders.edit', salesOrder.id)">
                        <Pencil class="size-4" /> Edit
                    </Link>
                </Button>
                <Button v-if="canSubmit" size="default" class="rounded-full bg-brand text-white hover:bg-brand-dark" @click="doSubmit">
                    <Send class="size-4" /> Submit
                </Button>
                <Button v-if="canApprove" size="default" class="rounded-full bg-brand text-white hover:bg-brand-dark" @click="doApprove">
                    <CheckCircle2 class="size-4" /> Approve
                </Button>
                <Button v-if="canApproveOverride" size="default" class="rounded-full bg-brand text-white hover:bg-brand-dark" @click="overrideOpen = true">
                    <ShieldCheck class="size-4" /> Approve Override
                </Button>
                <Button v-if="canReject" size="default" variant="outline" class="rounded-full" @click="rejectOpen = true">
                    <X class="size-4" /> Reject
                </Button>
                <Button v-if="canCancel" size="default" variant="outline" class="rounded-full" @click="cancelOpen = true">
                    <Ban class="size-4" /> Cancel
                </Button>
                <Button v-if="canAdjust" size="default" class="rounded-full bg-brand text-white hover:bg-brand-dark" @click="adjustOpen = true">
                    <Wrench class="size-4" /> Sesuaikan
                </Button>
            </template>
        </PageHeader>

        <!-- Credit review banner -->
        <section v-if="salesOrder.status === 'pending_credit_review'" class="rounded-2xl bg-warning-soft ring-1 ring-warning/30 p-4 mb-4">
            <div class="flex items-start gap-3 text-sm">
                <ClipboardCheck class="size-5 text-amber-700 mt-0.5 shrink-0" />
                <div class="flex-1">
                    <p class="font-medium text-amber-900">SO over credit limit — perlu approval superadmin.</p>
                    <p class="text-xs text-amber-800 mt-1">
                        Outstanding saat submit: <strong>{{ fmtRp(salesOrder.credit_outstanding_snapshot) }}</strong>
                        + SO ini <strong>{{ fmtRp(salesOrder.total) }}</strong>
                        <span v-if="salesOrder.customer?.credit_limit">
                            · Limit: <strong>{{ fmtRp(salesOrder.customer.credit_limit) }}</strong>
                        </span>
                    </p>
                </div>
            </div>
        </section>

        <section v-if="salesOrder.status === 'rejected'" class="rounded-2xl bg-red-50 ring-1 ring-red-200 p-4 mb-4 text-sm">
            <p class="text-red-900"><strong>SO ditolak.</strong> Alasan: {{ salesOrder.rejection_reason }}</p>
        </section>

        <!-- Summary -->
        <section class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 mb-5">
            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-4 flex items-start gap-4">
                <div class="size-12 rounded-full bg-brand/10 text-brand flex items-center justify-center shrink-0">
                    <ShoppingBag class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[12px] font-semibold uppercase tracking-wider text-muted-foreground">
                        FY {{ salesOrder.fiscal_year }}<span v-if="salesOrder.is_carry_over"> · Carry-over</span>
                    </p>
                    <h2 class="text-base font-bold tracking-tight text-foreground truncate font-mono">{{ salesOrder.so_number }}</h2>
                    <p class="text-xs text-muted-foreground mt-1">
                        Customer: <strong>{{ salesOrder.customer?.name }}</strong>
                        <span class="font-mono"> ({{ salesOrder.customer?.code }})</span>
                        <span v-if="salesOrder.sales"> · Sales: {{ salesOrder.sales.name }}</span>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <SoStatusBadge :status="salesOrder.status" />
                    <p class="text-xs text-muted-foreground">{{ formatDate(salesOrder.so_date) }}</p>
                </div>
            </div>
        </section>

        <!-- Detail header -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                <header class="border-b border-foreground/5 px-5 py-3">
                    <h3 class="text-sm font-semibold">Detail</h3>
                </header>
                <dl class="px-5 py-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">Tgl SO</dt>
                        <dd class="mt-0.5">{{ formatDate(salesOrder.so_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">ETA</dt>
                        <dd class="mt-0.5">{{ formatDate(salesOrder.eta_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">Payment Term</dt>
                        <dd class="mt-0.5">{{ salesOrder.payment_term_days ? `${salesOrder.payment_term_days} hari` : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">Jatuh Tempo</dt>
                        <dd class="mt-0.5">{{ formatDate(salesOrder.due_date) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                <header class="border-b border-foreground/5 px-5 py-3">
                    <h3 class="text-sm font-semibold">Customer</h3>
                </header>
                <dl class="px-5 py-3 text-sm space-y-1">
                    <p class="font-medium">{{ salesOrder.customer?.name }}</p>
                    <p class="text-xs text-muted-foreground font-mono">{{ salesOrder.customer?.code }}</p>
                    <p v-if="salesOrder.customer?.phone" class="text-xs">Phone: {{ salesOrder.customer.phone }}</p>
                    <p v-if="salesOrder.customer?.address" class="text-xs whitespace-pre-line">{{ salesOrder.customer.address }}</p>
                    <p v-if="salesOrder.customer?.credit_limit !== undefined" class="text-xs">
                        Limit: <strong class="font-mono">{{ fmtRp(salesOrder.customer.credit_limit) }}</strong>
                    </p>
                </dl>
            </div>
        </div>

        <!-- Items -->
        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
            <header class="border-b border-foreground/5 px-5 py-3">
                <h3 class="text-sm font-semibold">Items ({{ salesOrder.items?.length ?? 0 }})</h3>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                            <th class="text-left py-2.5 px-5">Produk</th>
                            <th class="text-left py-2.5 px-3">Unit</th>
                            <th class="text-right py-2.5 px-3">Qty</th>
                            <th class="text-right py-2.5 px-3">Terkirim</th>
                            <th class="text-right py-2.5 px-3">Net/Unit</th>
                            <th class="text-right py-2.5 px-5">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-foreground/5">
                        <tr v-for="i in salesOrder.items" :key="i.id" class="hover:bg-foreground/2.5 transition-colors">
                            <td class="py-2.5 px-5">
                                <p class="font-medium">
                                    {{ i.product_name_snapshot }}
                                    <span v-if="i.is_bonus" class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">
                                        BONUS
                                    </span>
                                </p>
                                <p class="text-[12px] text-muted-foreground font-mono">{{ i.product_sku_snapshot }}</p>
                            </td>
                            <td class="py-2.5 px-3">{{ i.product_unit_name_snapshot }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty }}</td>
                            <td class="py-2.5 px-3 text-right font-mono text-muted-foreground">{{ i.qty_delivered }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">
                                {{ fmtRp(i.unit_net_price) }}
                                <span v-if="i.discount_type && Number(i.discount_value) > 0" class="block text-[11px] text-amber-700">
                                    <template v-if="i.discount_type === 'percent'">Diskon {{ Number(i.discount_value) }}%</template>
                                    <template v-else>Diskon {{ fmtRp(i.discount_value) }}</template>
                                </span>
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
                        <span class="font-mono">{{ fmtRp(salesOrder.subtotal) }}</span>
                    </div>
                    <div v-if="Number(salesOrder.header_discount_amount) > 0" class="flex justify-between text-muted-foreground">
                        <span>
                            Diskon Header
                            <span v-if="salesOrder.header_discount_type === 'percent'">
                                ({{ Number(salesOrder.header_discount_value) }}%)
                            </span>
                        </span>
                        <span class="font-mono">− {{ fmtRp(salesOrder.header_discount_amount) }}</span>
                    </div>
                    <div v-if="Number(salesOrder.cashback) > 0" class="flex justify-between text-emerald-700">
                        <span>Cashback</span>
                        <span class="font-mono">− {{ fmtRp(salesOrder.cashback) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-foreground/5 font-bold">
                        <span>TOTAL</span>
                        <span class="font-mono">{{ fmtRp(salesOrder.total) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reservations panel -->
        <div v-if="salesOrder.reservations?.length" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
            <header class="border-b border-foreground/5 px-5 py-3">
                <h3 class="text-sm font-semibold">Reservasi Stok ({{ salesOrder.reservations.length }})</h3>
            </header>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                        <th class="text-left py-2 px-5">Batch</th>
                        <th class="text-left py-2 px-3">Expired</th>
                        <th class="text-right py-2 px-3">Qty Reserved</th>
                        <th class="text-left py-2 px-5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-foreground/5">
                    <tr v-for="r in salesOrder.reservations" :key="r.id">
                        <td class="py-2 px-5 font-mono text-xs">{{ r.batch?.batch_code ?? '—' }}</td>
                        <td class="py-2 px-3 text-xs">{{ formatDate(r.batch?.expired_date) }}</td>
                        <td class="py-2 px-3 text-right font-mono">{{ r.qty_reserved }}</td>
                        <td class="py-2 px-5">
                            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold', {
                                'bg-blue-50 text-blue-700': r.status === 'active',
                                'bg-emerald-50 text-emerald-700': r.status === 'consumed',
                                'bg-muted text-muted-foreground': r.status === 'released',
                            }]">{{ r.status }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Dokumen turunan: Surat Jalan & Faktur -->
        <div v-if="salesOrder.delivery_orders?.length || salesOrder.invoices?.length" class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <!-- Surat Jalan -->
            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
                <header class="border-b border-foreground/5 px-5 py-3 flex items-center gap-2">
                    <span class="size-7 rounded-full bg-brand-light/70 text-brand flex items-center justify-center"><Truck class="size-4" /></span>
                    <h3 class="text-sm font-semibold">Surat Jalan ({{ salesOrder.delivery_orders?.length ?? 0 }})</h3>
                </header>
                <ul v-if="salesOrder.delivery_orders?.length" class="divide-y divide-foreground/5">
                    <li v-for="d in salesOrder.delivery_orders" :key="d.id" class="px-5 py-2.5 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <Link :href="route('delivery-orders.show', d.id)" class="font-mono text-xs font-medium text-primary hover:underline">{{ d.do_number }}</Link>
                            <p class="text-[11px] text-muted-foreground">{{ formatDate(d.do_date) }}</p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-muted text-muted-foreground shrink-0">{{ DO_STATUS[d.status] ?? d.status }}</span>
                    </li>
                </ul>
                <p v-else class="px-5 py-4 text-xs text-muted-foreground">Belum ada Surat Jalan.</p>
            </div>

            <!-- Faktur -->
            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
                <header class="border-b border-foreground/5 px-5 py-3 flex items-center gap-2">
                    <span class="size-7 rounded-full bg-brand-light/70 text-brand flex items-center justify-center"><ReceiptText class="size-4" /></span>
                    <h3 class="text-sm font-semibold">Faktur ({{ salesOrder.invoices?.length ?? 0 }})</h3>
                </header>
                <ul v-if="salesOrder.invoices?.length" class="divide-y divide-foreground/5">
                    <li v-for="inv in salesOrder.invoices" :key="inv.id" class="px-5 py-2.5 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <Link :href="route('invoices.show', inv.id)" class="font-mono text-xs font-medium text-primary hover:underline">{{ inv.invoice_number }}</Link>
                            <p class="text-[11px] text-muted-foreground">
                                {{ fmtRp(inv.total) }} · sisa <span :class="Number(inv.outstanding) > 0 ? 'text-amber-700 font-medium' : 'text-emerald-700'">{{ fmtRp(inv.outstanding) }}</span>
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold shrink-0" :class="inv.status === 'paid' ? 'bg-emerald-50 text-emerald-700' : inv.status === 'overdue' ? 'bg-red-50 text-red-700' : 'bg-muted text-muted-foreground'">{{ INV_STATUS[inv.status] ?? inv.status }}</span>
                    </li>
                </ul>
                <p v-else class="px-5 py-4 text-xs text-muted-foreground">Belum ada Faktur.</p>
            </div>
        </div>

        <!-- Notes & Audit -->
        <div v-if="salesOrder.notes" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
            <p class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan</p>
            <p class="text-sm whitespace-pre-line">{{ salesOrder.notes }}</p>
        </div>

        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5">
            <p class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold mb-2">Riwayat</p>
            <ul class="text-xs space-y-1">
                <li>Dibuat <strong>{{ formatDate(salesOrder.created_at) }}</strong></li>
                <li v-if="salesOrder.submitted_at">Disubmit <strong>{{ formatDate(salesOrder.submitted_at) }}</strong></li>
                <li v-if="salesOrder.approved_at" class="text-emerald-700">
                    Approved <strong>{{ formatDate(salesOrder.approved_at) }}</strong>
                    <span v-if="salesOrder.approver"> · oleh {{ salesOrder.approver.name }}</span>
                </li>
                <li v-if="salesOrder.credit_override_approved_at" class="text-amber-700">
                    Credit Override <strong>{{ formatDate(salesOrder.credit_override_approved_at) }}</strong>
                    <span v-if="salesOrder.creditOverrideApprover"> · oleh {{ salesOrder.creditOverrideApprover.name }}</span>
                    <span v-if="salesOrder.credit_override_reason"> — "{{ salesOrder.credit_override_reason }}"</span>
                </li>
                <li v-if="salesOrder.rejected_at" class="text-red-700">
                    Rejected <strong>{{ formatDate(salesOrder.rejected_at) }}</strong>
                    <span v-if="salesOrder.rejecter"> · oleh {{ salesOrder.rejecter.name }}</span>
                </li>
                <li v-if="salesOrder.cancelled_at" class="text-red-700">
                    Cancelled <strong>{{ formatDate(salesOrder.cancelled_at) }}</strong>
                    <span v-if="salesOrder.cancel_reason"> — "{{ salesOrder.cancel_reason }}"</span>
                </li>
            </ul>
        </div>

        <!-- Override Dialog -->
        <Dialog v-model:open="overrideOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                            <ShieldCheck class="size-5" />
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <DialogTitle class="text-base font-semibold tracking-tight">Approve dengan Credit Override</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Customer over limit. Aksi ini ter-log dengan alasan.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-6 pb-2" @submit.prevent="doApproveOverride">
                    <Label class="text-xs font-medium">Alasan Override *</Label>
                    <Textarea v-model="overrideForm.reason" rows="3" required class="mt-1.5 rounded-xl" />
                    <p v-if="overrideForm.errors.reason" class="text-xs text-destructive mt-1">{{ overrideForm.errors.reason }}</p>
                </form>
                <DialogFooter class="px-6 py-4 gap-2">
                    <Button type="button" variant="outline" class="rounded-full" @click="overrideOpen = false">Batal</Button>
                    <Button type="button" class="rounded-full bg-brand text-white hover:bg-brand-dark" :disabled="overrideForm.processing" @click="doApproveOverride">Approve Override</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Reject Dialog -->
        <Dialog v-model:open="rejectOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-full bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                            <X class="size-5" />
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <DialogTitle class="text-base font-semibold tracking-tight">Reject SO</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Sales akan dapat notif. SO final, tidak bisa di-resubmit.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-6 pb-2" @submit.prevent="doReject">
                    <Label class="text-xs font-medium">Alasan *</Label>
                    <Textarea v-model="rejectForm.rejection_reason" rows="3" required class="mt-1.5 rounded-xl" />
                    <p v-if="rejectForm.errors.rejection_reason" class="text-xs text-destructive mt-1">{{ rejectForm.errors.rejection_reason }}</p>
                </form>
                <DialogFooter class="px-6 py-4 gap-2">
                    <Button type="button" variant="outline" class="rounded-full" @click="rejectOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" class="rounded-full" :disabled="rejectForm.processing" @click="doReject">Reject</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Cancel Dialog -->
        <Dialog v-model:open="cancelOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-full bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                            <Ban class="size-5" />
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <DialogTitle class="text-base font-semibold tracking-tight">Cancel SO</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Reservasi stok akan ter-release (kalau approved).
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-6 pb-2" @submit.prevent="doCancel">
                    <Label class="text-xs font-medium">Alasan *</Label>
                    <Textarea v-model="cancelForm.cancel_reason" rows="3" required class="mt-1.5 rounded-xl" />
                    <p v-if="cancelForm.errors.cancel_reason" class="text-xs text-destructive mt-1">{{ cancelForm.errors.cancel_reason }}</p>
                </form>
                <DialogFooter class="px-6 py-4 gap-2">
                    <Button type="button" variant="outline" class="rounded-full" @click="cancelOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" class="rounded-full" :disabled="cancelForm.processing" @click="doCancel">Cancel SO</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Sesuaikan Dialog -->
        <Dialog v-model:open="adjustOpen">
            <DialogContent class="sm:max-w-[520px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0">
                            <Wrench class="size-5" />
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <DialogTitle class="text-base font-semibold tracking-tight">Sesuaikan Pesanan</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Barang sudah keluar / faktur sudah terbit, jadi item tidak bisa ditimpa langsung.
                                Pilih penyesuaian yang sesuai:
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div class="px-6 pb-4 space-y-2.5">
                    <!-- Kurangi tagihan / retur -->
                    <Link :href="returnUrl()" class="block rounded-2xl ring-1 ring-foreground/8 hover:ring-brand/30 hover:bg-brand-light/30 transition-all px-4 py-3.5">
                        <div class="flex items-start gap-3">
                            <span class="size-9 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center shrink-0"><Undo2 class="size-4.5" /></span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">Kurangi tagihan / barang dikembalikan</p>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    Buat <strong>Retur</strong> → otomatis jadi Credit Note yang memotong tagihan faktur.
                                    Barang bagus kembali ke stok. Customer &amp; faktur sudah terisi.
                                </p>
                            </div>
                        </div>
                    </Link>

                    <!-- Tambah barang -->
                    <Link :href="newSoUrl()" class="block rounded-2xl ring-1 ring-foreground/8 hover:ring-brand/30 hover:bg-brand-light/30 transition-all px-4 py-3.5">
                        <div class="flex items-start gap-3">
                            <span class="size-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0"><Plus class="size-4.5" /></span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">Tambah barang</p>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    Buat <strong>SO baru</strong> untuk barang tambahan (pesanan lama tetap utuh).
                                    Customer sudah terisi.
                                </p>
                            </div>
                        </div>
                    </Link>
                </div>

                <DialogFooter class="px-6 py-4">
                    <Button type="button" variant="outline" class="rounded-full" @click="adjustOpen = false">Tutup</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
