<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    CheckCircle2,
    ClipboardCheck,
    Pencil,
    Send,
    ShieldCheck,
    ShoppingBag,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
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
});

const overrideOpen = ref(false);
const rejectOpen = ref(false);
const cancelOpen = ref(false);

const submitForm = useForm({});
const approveForm = useForm({});
const overrideForm = useForm({ reason: '' });
const rejectForm = useForm({ rejection_reason: '' });
const cancelForm = useForm({ cancel_reason: '' });

function doSubmit() {
    if (!window.confirm('Submit SO ini ke admin untuk review?')) return;
    submitForm.post(route('sales-orders.submit', props.salesOrder.id), { preserveScroll: true });
}

function doApprove() {
    if (!window.confirm('Approve SO ini? Stok akan ter-reserve.')) return;
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
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('sales-orders.index')">
                        <ArrowLeft class="size-4" /> Daftar SO
                    </Link>
                </Button>
                <Button v-if="canEdit" as-child size="default" variant="outline">
                    <Link :href="route('sales-orders.edit', salesOrder.id)">
                        <Pencil class="size-4" /> Edit
                    </Link>
                </Button>
                <Button v-if="canSubmit" size="default" variant="secondary" @click="doSubmit">
                    <Send class="size-4" /> Submit
                </Button>
                <Button v-if="canApprove" size="default" variant="secondary" @click="doApprove">
                    <CheckCircle2 class="size-4" /> Approve
                </Button>
                <Button v-if="canApproveOverride" size="default" variant="secondary" @click="overrideOpen = true">
                    <ShieldCheck class="size-4" /> Approve Override
                </Button>
                <Button v-if="canReject" size="default" variant="outline" @click="rejectOpen = true">
                    <X class="size-4" /> Reject
                </Button>
                <Button v-if="canCancel" size="default" variant="outline" @click="cancelOpen = true">
                    <Ban class="size-4" /> Cancel
                </Button>
            </template>
        </PageHeader>

        <!-- Credit review banner -->
        <section v-if="salesOrder.status === 'pending_credit_review'" class="rounded-lg bg-warning-soft ring-1 ring-warning/30 p-4 mb-4">
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

        <section v-if="salesOrder.status === 'rejected'" class="rounded-lg bg-red-50 ring-1 ring-red-200 p-4 mb-4 text-sm">
            <p class="text-red-900"><strong>SO ditolak.</strong> Alasan: {{ salesOrder.rejection_reason }}</p>
        </section>

        <!-- Summary -->
        <section class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 mb-5">
            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 flex items-start gap-4">
                <div class="size-12 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <ShoppingBag class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
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
            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">Detail</h3>
                </header>
                <dl class="px-5 py-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Tgl SO</dt>
                        <dd class="mt-0.5">{{ formatDate(salesOrder.so_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">ETA</dt>
                        <dd class="mt-0.5">{{ formatDate(salesOrder.eta_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Payment Term</dt>
                        <dd class="mt-0.5">{{ salesOrder.payment_term_days ? `${salesOrder.payment_term_days} hari` : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Jatuh Tempo</dt>
                        <dd class="mt-0.5">{{ formatDate(salesOrder.due_date) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                <header class="border-b border-border/70 px-5 py-3">
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
        <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden mb-4">
            <header class="border-b border-border/70 px-5 py-3">
                <h3 class="text-sm font-semibold">Items ({{ salesOrder.items?.length ?? 0 }})</h3>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-muted-foreground border-b border-border/70">
                            <th class="text-left py-2.5 px-5">Produk</th>
                            <th class="text-left py-2.5 px-3">Unit</th>
                            <th class="text-right py-2.5 px-3">Qty</th>
                            <th class="text-right py-2.5 px-3">Terkirim</th>
                            <th class="text-right py-2.5 px-3">Net/Unit</th>
                            <th class="text-right py-2.5 px-5">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr v-for="i in salesOrder.items" :key="i.id" class="hover:bg-muted/20">
                            <td class="py-2.5 px-5">
                                <p class="font-medium">
                                    {{ i.product_name_snapshot }}
                                    <span v-if="i.is_bonus" class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">
                                        BONUS
                                    </span>
                                </p>
                                <p class="text-[11px] text-muted-foreground font-mono">{{ i.product_sku_snapshot }}</p>
                            </td>
                            <td class="py-2.5 px-3">{{ i.product_unit_name_snapshot }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty }}</td>
                            <td class="py-2.5 px-3 text-right font-mono text-muted-foreground">{{ i.qty_delivered }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">
                                {{ fmtRp(i.unit_net_price) }}
                                <span v-if="Number(i.discount_z1_pct) > 0 || Number(i.discount_z2_pct) > 0" class="block text-[10px] text-muted-foreground">
                                    Z1 {{ Number(i.discount_z1_pct) }}% · Z2 {{ Number(i.discount_z2_pct) }}%
                                </span>
                            </td>
                            <td class="py-2.5 px-5 text-right font-mono">{{ fmtRp(i.line_subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border/70 px-5 py-3 flex justify-end">
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
                    <div class="flex justify-between pt-2 border-t border-border/70 font-bold">
                        <span>TOTAL</span>
                        <span class="font-mono">{{ fmtRp(salesOrder.total) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reservations panel -->
        <div v-if="salesOrder.reservations?.length" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden mb-4">
            <header class="border-b border-border/70 px-5 py-3">
                <h3 class="text-sm font-semibold">Reservasi Stok ({{ salesOrder.reservations.length }})</h3>
            </header>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[10px] uppercase tracking-wider text-muted-foreground border-b border-border/70">
                        <th class="text-left py-2 px-5">Batch</th>
                        <th class="text-left py-2 px-3">Expired</th>
                        <th class="text-right py-2 px-3">Qty Reserved</th>
                        <th class="text-left py-2 px-5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/40">
                    <tr v-for="r in salesOrder.reservations" :key="r.id">
                        <td class="py-2 px-5 font-mono text-xs">{{ r.batch?.batch_code ?? '—' }}</td>
                        <td class="py-2 px-3 text-xs">{{ formatDate(r.batch?.expired_date) }}</td>
                        <td class="py-2 px-3 text-right font-mono">{{ r.qty_reserved }}</td>
                        <td class="py-2 px-5">
                            <span :class="['inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold', {
                                'bg-blue-50 text-blue-700': r.status === 'active',
                                'bg-emerald-50 text-emerald-700': r.status === 'consumed',
                                'bg-muted text-muted-foreground': r.status === 'released',
                            }]">{{ r.status }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Notes & Audit -->
        <div v-if="salesOrder.notes" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4">
            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan</p>
            <p class="text-sm whitespace-pre-line">{{ salesOrder.notes }}</p>
        </div>

        <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5">
            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-2">Riwayat</p>
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
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                            <ShieldCheck class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">Approve dengan Credit Override</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Customer over limit. Aksi ini ter-log dengan alasan.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-5 py-4" @submit.prevent="doApproveOverride">
                    <Label class="text-xs font-medium">Alasan Override *</Label>
                    <Textarea v-model="overrideForm.reason" rows="3" required class="mt-1" />
                    <p v-if="overrideForm.errors.reason" class="text-xs text-destructive mt-1">{{ overrideForm.errors.reason }}</p>
                </form>
                <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                    <Button type="button" variant="outline" @click="overrideOpen = false">Batal</Button>
                    <Button type="button" variant="secondary" :disabled="overrideForm.processing" @click="doApproveOverride">Approve Override</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Reject Dialog -->
        <Dialog v-model:open="rejectOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-md bg-red-50 text-red-700 flex items-center justify-center shrink-0 ring-1 ring-red-200">
                            <X class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">Reject SO</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Sales akan dapat notif. SO final, tidak bisa di-resubmit.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-5 py-4" @submit.prevent="doReject">
                    <Label class="text-xs font-medium">Alasan *</Label>
                    <Textarea v-model="rejectForm.rejection_reason" rows="3" required class="mt-1" />
                    <p v-if="rejectForm.errors.rejection_reason" class="text-xs text-destructive mt-1">{{ rejectForm.errors.rejection_reason }}</p>
                </form>
                <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                    <Button type="button" variant="outline" @click="rejectOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" :disabled="rejectForm.processing" @click="doReject">Reject</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Cancel Dialog -->
        <Dialog v-model:open="cancelOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-md bg-red-50 text-red-700 flex items-center justify-center shrink-0 ring-1 ring-red-200">
                            <Ban class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">Cancel SO</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Reservasi stok akan ter-release (kalau approved).
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-5 py-4" @submit.prevent="doCancel">
                    <Label class="text-xs font-medium">Alasan *</Label>
                    <Textarea v-model="cancelForm.cancel_reason" rows="3" required class="mt-1" />
                    <p v-if="cancelForm.errors.cancel_reason" class="text-xs text-destructive mt-1">{{ cancelForm.errors.cancel_reason }}</p>
                </form>
                <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                    <Button type="button" variant="outline" @click="cancelOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" :disabled="cancelForm.processing" @click="doCancel">Cancel SO</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
