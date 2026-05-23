<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    CheckCircle2,
    FileText,
    Lock,
    Pencil,
    RefreshCw,
    ShoppingCart,
} from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import PoStatusBadge from '@/Components/PurchaseOrders/PoStatusBadge.vue';
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
    purchaseOrder: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canApprove: { type: Boolean, default: false },
    canCancel: { type: Boolean, default: false },
    canClose: { type: Boolean, default: false },
});

const cancelOpen = ref(false);
const closeOpen = ref(false);

const approveForm = useForm({});
const cancelForm = useForm({ cancel_reason: '' });
const closeForm = useForm({ close_reason: '' });

function approve() {
    if (!window.confirm(`Approve PO ${props.purchaseOrder.po_number}? PDF akan di-generate.`)) return;
    approveForm.post(route('purchase-orders.approve', props.purchaseOrder.id), { preserveScroll: true });
}

function submitCancel() {
    cancelForm.post(route('purchase-orders.cancel', props.purchaseOrder.id), {
        preserveScroll: true,
        onSuccess: () => (cancelOpen.value = false),
    });
}

function submitClose() {
    closeForm.post(route('purchase-orders.close', props.purchaseOrder.id), {
        preserveScroll: true,
        onSuccess: () => (closeOpen.value = false),
    });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head :title="`PO ${purchaseOrder.po_number}`" />

    <AppLayout>
        <PageHeader
            :title="`PO ${purchaseOrder.po_number}`"
            :description="`${purchaseOrder.supplier?.name ?? '—'} · ${formatDate(purchaseOrder.po_date)}`"
            :icon="ShoppingCart"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('purchase-orders.index')">
                        <ArrowLeft class="size-4" />
                        Daftar PO
                    </Link>
                </Button>
                <Button v-if="canEdit" as-child size="default" variant="outline">
                    <Link :href="route('purchase-orders.edit', purchaseOrder.id)">
                        <Pencil class="size-4" />
                        Edit
                    </Link>
                </Button>
                <Button v-if="canApprove" size="default" variant="secondary" @click="approve">
                    <CheckCircle2 class="size-4" />
                    Approve
                </Button>
                <Button v-if="purchaseOrder.pdf_path" as-child size="default" variant="outline">
                    <a :href="route('purchase-orders.pdf', purchaseOrder.id)" target="_blank" rel="noopener">
                        <FileText class="size-4" />
                        Download PDF
                    </a>
                </Button>
                <Button v-if="canCancel" size="default" variant="outline" @click="cancelOpen = true">
                    <Ban class="size-4" />
                    Cancel
                </Button>
                <Button v-if="canClose" size="default" variant="outline" @click="closeOpen = true">
                    <Lock class="size-4" />
                    Close
                </Button>
            </template>
        </PageHeader>

        <!-- Summary -->
        <section class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 mb-5">
            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 flex items-start gap-4">
                <div class="size-12 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <ShoppingCart class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        FY {{ purchaseOrder.fiscal_year }}<span v-if="purchaseOrder.is_carry_over"> · Carry-over</span>
                    </p>
                    <h2 class="text-base font-bold tracking-tight text-foreground truncate font-mono">
                        {{ purchaseOrder.po_number }}
                    </h2>
                    <p class="text-xs text-muted-foreground mt-1">
                        Supplier: <strong>{{ purchaseOrder.supplier?.name ?? '—' }}</strong>
                        <span class="font-mono"> ({{ purchaseOrder.supplier?.code }})</span>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <PoStatusBadge :status="purchaseOrder.status" />
                    <p class="text-xs text-muted-foreground">{{ formatDate(purchaseOrder.po_date) }}</p>
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
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Tgl PO</dt>
                        <dd class="mt-0.5">{{ formatDate(purchaseOrder.po_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">ETA</dt>
                        <dd class="mt-0.5">{{ formatDate(purchaseOrder.eta_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Payment Term</dt>
                        <dd class="mt-0.5">{{ purchaseOrder.payment_term_days ? `${purchaseOrder.payment_term_days} hari` : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">PDF</dt>
                        <dd class="mt-0.5">
                            <span v-if="purchaseOrder.pdf_path">Generated {{ formatDate(purchaseOrder.pdf_generated_at) }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">Supplier</h3>
                </header>
                <dl class="px-5 py-3 text-sm space-y-1">
                    <p class="font-medium">{{ purchaseOrder.supplier?.name }}</p>
                    <p class="text-xs text-muted-foreground font-mono">{{ purchaseOrder.supplier?.code }}</p>
                    <p v-if="purchaseOrder.supplier?.phone" class="text-xs">Telp: {{ purchaseOrder.supplier.phone }}</p>
                    <p v-if="purchaseOrder.supplier?.email" class="text-xs">{{ purchaseOrder.supplier.email }}</p>
                </dl>
            </div>
        </div>

        <!-- Items -->
        <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden mb-4">
            <header class="border-b border-border/70 px-5 py-3">
                <h3 class="text-sm font-semibold">Items ({{ purchaseOrder.items?.length ?? 0 }})</h3>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-muted-foreground border-b border-border/70">
                            <th class="text-left py-2.5 px-5">Produk</th>
                            <th class="text-left py-2.5 px-3">Unit</th>
                            <th class="text-right py-2.5 px-3">Qty</th>
                            <th class="text-right py-2.5 px-3">Diterima</th>
                            <th class="text-right py-2.5 px-3">Bonus</th>
                            <th class="text-right py-2.5 px-3">Net/Unit</th>
                            <th class="text-right py-2.5 px-5">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr v-for="i in purchaseOrder.items" :key="i.id" class="hover:bg-muted/20">
                            <td class="py-2.5 px-5">
                                <p class="font-medium">{{ i.product_name_snapshot }}</p>
                                <p class="text-[11px] text-muted-foreground font-mono">{{ i.product_sku_snapshot }}</p>
                            </td>
                            <td class="py-2.5 px-3">{{ i.product_unit_name_snapshot }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty_ordered }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty_received }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">
                                {{ i.bonus_qty > 0 ? `${i.bonus_qty} / ${i.bonus_qty_received}` : '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono">
                                {{ formatRp(i.unit_net_cost) }}
                                <span v-if="Number(i.discount_z1_pct) > 0 || Number(i.discount_z2_pct) > 0" class="block text-[10px] text-muted-foreground">
                                    Z1 {{ Number(i.discount_z1_pct) }}% · Z2 {{ Number(i.discount_z2_pct) }}%
                                </span>
                            </td>
                            <td class="py-2.5 px-5 text-right font-mono">{{ formatRp(i.line_subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border/70 px-5 py-3 flex justify-end">
                <div class="w-full max-w-sm space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span class="font-mono">{{ formatRp(purchaseOrder.subtotal) }}</span>
                    </div>
                    <div v-if="Number(purchaseOrder.header_discount_amount) > 0" class="flex justify-between text-muted-foreground">
                        <span>
                            Diskon Header
                            <span v-if="purchaseOrder.header_discount_type === 'percent'">
                                ({{ Number(purchaseOrder.header_discount_value) }}%)
                            </span>
                        </span>
                        <span class="font-mono">− {{ formatRp(purchaseOrder.header_discount_amount) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-border/70 font-bold">
                        <span>TOTAL</span>
                        <span class="font-mono">{{ formatRp(purchaseOrder.total) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes & history -->
        <div v-if="purchaseOrder.notes" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4">
            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan</p>
            <p class="text-sm whitespace-pre-line">{{ purchaseOrder.notes }}</p>
        </div>

        <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5">
            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-2">Riwayat</p>
            <ul class="text-xs space-y-1">
                <li>
                    Dibuat <strong>{{ formatDate(purchaseOrder.created_at) }}</strong>
                </li>
                <li v-if="purchaseOrder.approved_at">
                    Approved <strong>{{ formatDate(purchaseOrder.approved_at) }}</strong>
                    <span v-if="purchaseOrder.approver"> · oleh {{ purchaseOrder.approver.name }}</span>
                </li>
                <li v-if="purchaseOrder.closed_at" class="text-amber-700">
                    Closed <strong>{{ formatDate(purchaseOrder.closed_at) }}</strong>
                    <span v-if="purchaseOrder.closer"> · oleh {{ purchaseOrder.closer.name }}</span>
                    <span v-if="purchaseOrder.close_reason"> — "{{ purchaseOrder.close_reason }}"</span>
                </li>
                <li v-if="purchaseOrder.cancelled_at" class="text-red-700">
                    Cancelled <strong>{{ formatDate(purchaseOrder.cancelled_at) }}</strong>
                    <span v-if="purchaseOrder.canceller"> · oleh {{ purchaseOrder.canceller.name }}</span>
                    <span v-if="purchaseOrder.cancel_reason"> — "{{ purchaseOrder.cancel_reason }}"</span>
                </li>
            </ul>
        </div>

        <!-- Cancel Dialog -->
        <Dialog v-model:open="cancelOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-md bg-red-50 text-red-700 flex items-center justify-center shrink-0 ring-1 ring-red-200">
                            <Ban class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">Cancel PO</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                PO yang sudah ada GRN posted tidak bisa di-cancel.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-5 py-4" @submit.prevent="submitCancel">
                    <Label class="text-xs font-medium">Alasan *</Label>
                    <Textarea v-model="cancelForm.cancel_reason" rows="3" required class="mt-1" />
                    <p v-if="cancelForm.errors.cancel_reason" class="text-xs text-destructive mt-1">{{ cancelForm.errors.cancel_reason }}</p>
                </form>
                <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                    <Button type="button" variant="outline" @click="cancelOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" :disabled="cancelForm.processing" @click="submitCancel">
                        <RefreshCw v-if="cancelForm.processing" class="size-4 animate-spin" />
                        Cancel PO
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Close Dialog -->
        <Dialog v-model:open="closeOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-md bg-amber-50 text-amber-700 flex items-center justify-center shrink-0 ring-1 ring-amber-200">
                            <Lock class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">Close PO (Manual)</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Untuk PO yang short-shipped — tutup walau belum 100% diterima.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-5 py-4" @submit.prevent="submitClose">
                    <Label class="text-xs font-medium">Alasan *</Label>
                    <Textarea v-model="closeForm.close_reason" rows="3" required class="mt-1" />
                    <p v-if="closeForm.errors.close_reason" class="text-xs text-destructive mt-1">{{ closeForm.errors.close_reason }}</p>
                </form>
                <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                    <Button type="button" variant="outline" @click="closeOpen = false">Batal</Button>
                    <Button type="button" variant="secondary" :disabled="closeForm.processing" @click="submitClose">
                        <RefreshCw v-if="closeForm.processing" class="size-4 animate-spin" />
                        Tutup PO
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
