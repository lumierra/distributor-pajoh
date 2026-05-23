<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    CheckCircle2,
    FileText,
    PackageOpen,
    Pencil,
    Send,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import GrnStatusBadge from '@/Components/GoodsReceipts/GrnStatusBadge.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
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
    grn: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canSubmit: { type: Boolean, default: false },
    canCancel: { type: Boolean, default: false },
    canPost: { type: Boolean, default: false },
    canReject: { type: Boolean, default: false },
    hasOverReceive: { type: Boolean, default: false },
});

const cancelOpen = ref(false);
const rejectOpen = ref(false);
const postOpen = ref(false);

const submitForm = useForm({});
const cancelForm = useForm({ cancel_reason: '' });
const rejectForm = useForm({ rejection_reason: '' });
const postForm = useForm({ approve_over_receive: false });

function doSubmit() {
    if (!window.confirm('Submit GRN ini ke admin untuk review?')) return;
    submitForm.post(route('grns.submit', props.grn.id), { preserveScroll: true });
}

function doCancel() {
    cancelForm.post(route('grns.cancel', props.grn.id), {
        preserveScroll: true,
        onSuccess: () => (cancelOpen.value = false),
    });
}

function doReject() {
    rejectForm.post(route('grns.reject', props.grn.id), {
        preserveScroll: true,
        onSuccess: () => (rejectOpen.value = false),
    });
}

function doPost() {
    postForm.post(route('grns.post', props.grn.id), {
        preserveScroll: true,
        onSuccess: () => (postOpen.value = false),
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
    <Head :title="`GRN ${grn.grn_number}`" />

    <AppLayout>
        <PageHeader
            :title="`GRN ${grn.grn_number}`"
            :description="`PO: ${grn.purchase_order?.po_number ?? '—'} · ${grn.supplier?.name ?? '—'}`"
            :icon="PackageOpen"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('grns.index')">
                        <ArrowLeft class="size-4" />
                        Daftar GRN
                    </Link>
                </Button>
                <Button v-if="canEdit" as-child size="default" variant="outline">
                    <Link :href="route('grns.edit', grn.id)">
                        <Pencil class="size-4" /> Edit
                    </Link>
                </Button>
                <Button v-if="canSubmit" size="default" variant="secondary" @click="doSubmit">
                    <Send class="size-4" /> Submit
                </Button>
                <Button v-if="canPost" size="default" variant="secondary" @click="postOpen = true">
                    <CheckCircle2 class="size-4" /> Posting
                </Button>
                <Button v-if="canReject" size="default" variant="outline" @click="rejectOpen = true">
                    <X class="size-4" /> Reject
                </Button>
                <Button v-if="canCancel" size="default" variant="outline" @click="cancelOpen = true">
                    <Ban class="size-4" /> Cancel
                </Button>
                <Button v-if="grn.pdf_path" as-child size="default" variant="outline">
                    <a :href="route('grns.pdf', grn.id)" target="_blank" rel="noopener">
                        <FileText class="size-4" /> PDF
                    </a>
                </Button>
            </template>
        </PageHeader>

        <!-- Banners -->
        <section v-if="hasOverReceive" class="rounded-lg bg-warning-soft ring-1 ring-warning/30 p-3 mb-4 flex items-center gap-3 text-sm">
            <AlertTriangle class="size-5 text-amber-700 shrink-0" />
            <p class="text-amber-900">
                <strong>Over-receive terdeteksi.</strong> GRN ini mengandung qty lebih dari sisa PO. Posting butuh approval.
            </p>
        </section>

        <section v-if="grn.status === 'rejected'" class="rounded-lg bg-red-50 ring-1 ring-red-200 p-3 mb-4 flex items-start gap-3 text-sm">
            <X class="size-5 text-red-700 shrink-0 mt-0.5" />
            <div class="text-red-900">
                <p><strong>GRN ditolak.</strong> Alasan:</p>
                <p class="text-xs mt-1 whitespace-pre-line">{{ grn.rejection_reason ?? '—' }}</p>
            </div>
        </section>

        <!-- Summary -->
        <section class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 mb-5">
            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 flex items-start gap-4">
                <div class="size-12 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <PackageOpen class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        FY {{ grn.fiscal_year }}
                    </p>
                    <h2 class="text-base font-bold tracking-tight text-foreground truncate font-mono">{{ grn.grn_number }}</h2>
                    <p class="text-xs text-muted-foreground mt-1">
                        PO: <Link :href="route('purchase-orders.show', grn.purchase_order_id)" class="font-mono hover:text-primary">{{ grn.purchase_order?.po_number }}</Link>
                        · Supplier: <strong>{{ grn.supplier?.name }}</strong>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <GrnStatusBadge :status="grn.status" />
                    <p class="text-xs text-muted-foreground">{{ formatDate(grn.received_date) }}</p>
                </div>
            </div>
        </section>

        <!-- Detail -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">Penerimaan</h3>
                </header>
                <dl class="px-5 py-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Tanggal</dt>
                        <dd class="mt-0.5">{{ formatDate(grn.received_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">No Surat Jalan</dt>
                        <dd class="mt-0.5 font-mono">{{ grn.supplier_delivery_no ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Kendaraan</dt>
                        <dd class="mt-0.5">{{ grn.supplier_vehicle_info ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Supir</dt>
                        <dd class="mt-0.5">{{ grn.supplier_driver_name ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">Riwayat</h3>
                </header>
                <ul class="px-5 py-3 text-xs space-y-1">
                    <li>Dibuat <strong>{{ formatDate(grn.created_at) }}</strong>{{ grn.receiver ? ` · oleh ${grn.receiver.name}` : '' }}</li>
                    <li v-if="grn.submitted_at">Disubmit <strong>{{ formatDate(grn.submitted_at) }}</strong>{{ grn.submitter ? ` · oleh ${grn.submitter.name}` : '' }}</li>
                    <li v-if="grn.posted_at" class="text-emerald-700">Diposting <strong>{{ formatDate(grn.posted_at) }}</strong>{{ grn.poster ? ` · oleh ${grn.poster.name}` : '' }}</li>
                    <li v-if="grn.rejected_at" class="text-red-700">Ditolak <strong>{{ formatDate(grn.rejected_at) }}</strong>{{ grn.rejecter ? ` · oleh ${grn.rejecter.name}` : '' }}</li>
                    <li v-if="grn.cancelled_at" class="text-red-700">Dibatalkan <strong>{{ formatDate(grn.cancelled_at) }}</strong></li>
                </ul>
            </div>
        </div>

        <!-- Items -->
        <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden mb-4">
            <header class="border-b border-border/70 px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Items ({{ grn.items?.length ?? 0 }})</h3>
                <span v-if="grn.has_discrepancy" class="inline-flex items-center gap-1 text-xs text-amber-700">
                    <AlertTriangle class="size-3.5" /> Discrepancy
                </span>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-muted-foreground border-b border-border/70">
                            <th class="text-left py-2.5 px-5">Produk · Unit</th>
                            <th class="text-left py-2.5 px-3">Batch</th>
                            <th class="text-left py-2.5 px-3">Expired</th>
                            <th class="text-right py-2.5 px-3">Reg</th>
                            <th class="text-right py-2.5 px-3">Bonus</th>
                            <th class="text-right py-2.5 px-3">Damaged</th>
                            <th class="text-right py-2.5 px-3">Cost</th>
                            <th class="py-2.5 px-5">Kondisi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr v-for="i in grn.items" :key="i.id" class="hover:bg-muted/20">
                            <td class="py-2.5 px-5">
                                <p class="font-medium">{{ i.product_name_snapshot }}</p>
                                <p class="text-[11px] text-muted-foreground font-mono">{{ i.product_sku_snapshot }} · {{ i.product_unit_name_snapshot }}</p>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-xs">{{ i.batch_code }}</td>
                            <td class="py-2.5 px-3 text-xs">{{ formatDate(i.expired_date) }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty_reguler }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty_bonus > 0 ? i.qty_bonus : '—' }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty_damaged > 0 ? i.qty_damaged : '—' }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">
                                {{ formatRp(i.cost_price) }}
                                <span v-if="i.cost_overridden" class="block text-[10px] text-amber-700">override</span>
                            </td>
                            <td class="py-2.5 px-5">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-muted text-muted-foreground">
                                    {{ i.condition }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="grn.notes || grn.discrepancy_notes" class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div v-if="grn.notes" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5">
                <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan</p>
                <p class="text-sm whitespace-pre-line">{{ grn.notes }}</p>
            </div>
            <div v-if="grn.discrepancy_notes" class="rounded-lg bg-warning-soft ring-1 ring-warning/30 p-5">
                <p class="text-[11px] uppercase tracking-wider text-amber-800 font-semibold mb-1">Catatan Discrepancy</p>
                <p class="text-sm whitespace-pre-line text-amber-900">{{ grn.discrepancy_notes }}</p>
            </div>
        </div>

        <!-- Reject Dialog -->
        <Dialog v-model:open="rejectOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-md bg-red-50 text-red-700 flex items-center justify-center shrink-0 ring-1 ring-red-200">
                            <X class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">Reject GRN</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Operator akan dapat notif & GRN balik ke draft untuk revisi.
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
                            <DialogTitle class="text-base font-bold tracking-tight">Cancel GRN</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Hanya draft yang bisa di-cancel. Aksi ini final.
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
                    <Button type="button" variant="destructive" :disabled="cancelForm.processing" @click="doCancel">Cancel GRN</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Post Dialog -->
        <Dialog v-model:open="postOpen">
            <DialogContent class="sm:max-w-[520px] p-0 overflow-hidden">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                            <CheckCircle2 class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">Posting GRN</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Stok masuk akan tercatat di ledger. Aksi ini final & tidak bisa diundo.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <div class="px-5 py-4 space-y-3">
                    <div v-if="hasOverReceive" class="rounded-md bg-warning-soft ring-1 ring-warning/30 p-3 text-xs">
                        <p class="font-medium text-amber-900 mb-1">⚠ Over-receive terdeteksi</p>
                        <p class="text-amber-800">Centang di bawah untuk approve & lanjut posting.</p>
                    </div>
                    <label v-if="hasOverReceive" class="flex items-center gap-2 text-sm">
                        <input v-model="postForm.approve_over_receive" type="checkbox" class="size-4" />
                        Approve over-receive & posting
                    </label>
                </div>
                <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                    <Button type="button" variant="outline" @click="postOpen = false">Batal</Button>
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="postForm.processing || (hasOverReceive && !postForm.approve_over_receive)"
                        @click="doPost"
                    >
                        Posting Sekarang
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
