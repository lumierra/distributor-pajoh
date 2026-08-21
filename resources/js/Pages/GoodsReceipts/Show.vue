<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    Check,
    CheckCircle2,
    FileText,
    Image,
    Loader2,
    PackageCheck,
    PackageOpen,
    Paperclip,
    Pencil,
    Send,
    Trash2,
    Upload,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import GrnStatusBadge from '@/Components/GoodsReceipts/GrnStatusBadge.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { confirm } from '@/Composables/useConfirm';
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
    canManageAttachments: { type: Boolean, default: false },
    hasOverReceive: { type: Boolean, default: false },
    canManagePending: { type: Boolean, default: false },
});

const cancelOpen = ref(false);
const rejectOpen = ref(false);
const postOpen = ref(false);

const submitForm = useForm({});
const cancelForm = useForm({ cancel_reason: '' });
const rejectForm = useForm({ rejection_reason: '' });
const postForm = useForm({ approve_over_receive: false });
const settleForm = useForm({});

// Settle pending PER ITEM (susulan datang per produk).
async function settleItem(item) {
    if (!(await confirm({
        title: `Tandai pending "${item.product_name_snapshot}" selesai?`,
        description: 'Pending item ini berhenti dihitung di halaman stok. Bisa dibatalkan.',
    }))) return;
    settleForm.post(route('grn-items.settle-pending', item.id), { preserveScroll: true });
}

async function unsettleItem(item) {
    if (!(await confirm({ title: `Batalkan penandaan "${item.product_name_snapshot}"?`, description: 'Pending item ini muncul lagi di stok.' }))) return;
    settleForm.post(route('grn-items.unsettle-pending', item.id), { preserveScroll: true });
}

// Item penerimaan langsung yang punya pending (surat jalan > diterima).
function itemHasPending(item) {
    return !item.po_item && (Number(item.qty_delivery_note) || 0) > Number(item.qty_reguler);
}

// ── Lampiran (multi-file) ──
const attachForm = useForm({ files: [] });

function pickFiles(e) {
    const files = Array.from(e.target.files ?? []);
    if (files.length === 0) return;
    attachForm.files = files;
    attachForm.post(route('grns.attachments.store', props.grn.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => attachForm.reset('files'),
        onFinish: () => {
            e.target.value = '';
        },
    });
}

async function deleteAttachment(att) {
    if (!(await confirm({ title: `Hapus lampiran "${att.original_name}"?`, destructive: true }))) return;
    useForm({}).delete(route('grn-attachments.destroy', att.id), { preserveScroll: true });
}

function isImage(mime) {
    return typeof mime === 'string' && mime.startsWith('image/');
}

function formatSize(bytes) {
    const n = Number(bytes) || 0;
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(0)} KB`;
    return `${(n / 1024 / 1024).toFixed(1)} MB`;
}

async function doSubmit() {
    if (!(await confirm({ title: 'Submit GRN ini ke admin untuk review?' }))) return;
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

const CONDITION_LABELS = { good: 'Baik', damaged: 'Rusak', mixed: 'Campuran' };
function conditionLabel(c) {
    return CONDITION_LABELS[c] ?? c;
}

// Pending per item.
// - Dari PO: sisa PO belum datang = qty_ordered − qty_received (kumulatif
//   setelah GRN ini kalau sudah posted).
// - Penerimaan langsung (tanpa PO): dari surat jalan = qty_delivery_note −
//   qty_reguler. Kalau surat jalan tidak diisi/0, tidak ada pending → null.
function pendingOf(item) {
    const po = item.po_item;
    if (po) {
        return Math.max(0, Number(po.qty_ordered) - Number(po.qty_received));
    }
    const sj = Number(item.qty_delivery_note) || 0;
    if (sj <= 0) return null;
    return Math.max(0, sj - Number(item.qty_reguler));
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
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('grns.index')">
                        <ArrowLeft class="size-4" />
                        Daftar GRN
                    </Link>
                </Button>
                <Button v-if="canEdit" as-child size="default" variant="outline" class="rounded-full">
                    <Link :href="route('grns.edit', grn.id)">
                        <Pencil class="size-4" /> Edit
                    </Link>
                </Button>
                <Button
                    v-if="canSubmit"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="doSubmit"
                >
                    <Send class="size-4" /> Submit
                </Button>
                <Button
                    v-if="canPost"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="postOpen = true"
                >
                    <CheckCircle2 class="size-4" /> Posting
                </Button>
                <Button v-if="canReject" size="default" variant="outline" class="rounded-full" @click="rejectOpen = true">
                    <X class="size-4" /> Reject
                </Button>
                <Button v-if="canCancel" size="default" variant="outline" class="rounded-full" @click="cancelOpen = true">
                    <Ban class="size-4" /> Cancel
                </Button>
                <Button v-if="grn.pdf_path" as-child size="default" variant="outline" class="rounded-full">
                    <a :href="route('grns.pdf', grn.id)" target="_blank" rel="noopener">
                        <FileText class="size-4" /> PDF
                    </a>
                </Button>
            </template>
        </PageHeader>

        <!-- Banners -->
        <section v-if="hasOverReceive" class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-4 mb-4 flex items-center gap-3 text-sm">
            <AlertTriangle class="size-5 text-amber-700 shrink-0" />
            <p class="text-amber-900">
                <strong>Over-receive terdeteksi.</strong> GRN ini mengandung qty lebih dari sisa PO. Posting butuh approval.
            </p>
        </section>

        <section v-if="grn.status === 'rejected'" class="rounded-2xl bg-red-50 ring-1 ring-red-200 p-4 mb-4 flex items-start gap-3 text-sm">
            <X class="size-5 text-red-700 shrink-0 mt-0.5" />
            <div class="text-red-900">
                <p><strong>GRN ditolak.</strong> Alasan:</p>
                <p class="text-xs mt-1 whitespace-pre-line">{{ grn.rejection_reason ?? '—' }}</p>
            </div>
        </section>

        <!-- Summary -->
        <section class="mb-4">
            <div class="rounded-2xl bg-brand-light/60 ring-1 ring-brand/10 shadow-sm p-4 flex items-start gap-4">
                <div class="size-12 rounded-full bg-brand/10 text-brand flex items-center justify-center shrink-0">
                    <PackageOpen class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-brand-dark/70">
                        FY {{ grn.fiscal_year }}
                    </p>
                    <h2 class="text-base font-bold tracking-tight text-brand-dark truncate font-mono">{{ grn.grn_number }}</h2>
                    <p class="text-xs text-brand-dark/70 mt-1">
                        <template v-if="grn.purchase_order_id">
                            PO: <Link :href="route('purchase-orders.show', grn.purchase_order_id)" class="font-mono hover:underline">{{ grn.purchase_order?.po_number }}</Link>
                        </template>
                        <template v-else>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-brand/10 text-brand">Penerimaan Langsung</span>
                        </template>
                        · Supplier: <strong>{{ grn.supplier?.name }}</strong>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <GrnStatusBadge :status="grn.status" />
                    <p class="text-xs text-brand-dark/70">{{ formatDate(grn.received_date) }}</p>
                </div>
            </div>
        </section>

        <!-- Detail -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                <header class="px-5 py-3 border-b border-foreground/5">
                    <h3 class="text-sm font-semibold">Penerimaan</h3>
                </header>
                <dl class="px-5 py-3.5 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Tanggal</dt>
                        <dd class="mt-0.5">{{ formatDate(grn.received_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">No Surat Jalan</dt>
                        <dd class="mt-0.5 font-mono">{{ grn.supplier_delivery_no ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                <header class="px-5 py-3 border-b border-foreground/5">
                    <h3 class="text-sm font-semibold">Riwayat</h3>
                </header>
                <ul class="px-5 py-3.5 text-xs space-y-1">
                    <li>Dibuat <strong>{{ formatDate(grn.created_at) }}</strong>{{ grn.receiver ? ` · oleh ${grn.receiver.name}` : '' }}</li>
                    <li v-if="grn.submitted_at">Disubmit <strong>{{ formatDate(grn.submitted_at) }}</strong>{{ grn.submitter ? ` · oleh ${grn.submitter.name}` : '' }}</li>
                    <li v-if="grn.posted_at" class="text-emerald-700">Diposting <strong>{{ formatDate(grn.posted_at) }}</strong>{{ grn.poster ? ` · oleh ${grn.poster.name}` : '' }}</li>
                    <li v-if="grn.rejected_at" class="text-red-700">Ditolak <strong>{{ formatDate(grn.rejected_at) }}</strong>{{ grn.rejecter ? ` · oleh ${grn.rejecter.name}` : '' }}</li>
                    <li v-if="grn.cancelled_at" class="text-red-700">Dibatalkan <strong>{{ formatDate(grn.cancelled_at) }}</strong></li>
                </ul>
            </div>
        </div>

        <!-- Items -->
        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
            <header class="px-5 py-3 border-b border-foreground/5 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Items ({{ grn.items?.length ?? 0 }})</h3>
                <span v-if="grn.has_discrepancy" class="inline-flex items-center gap-1 text-xs text-amber-700">
                    <AlertTriangle class="size-3.5" /> Discrepancy
                </span>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                            <th class="text-left py-2.5 px-5 font-semibold">Produk · Unit</th>
                            <th class="text-left py-2.5 px-3 font-semibold">Batch</th>
                            <th class="text-left py-2.5 px-3 font-semibold">Expired</th>
                            <th class="text-right py-2.5 px-3 font-semibold">Reg</th>
                            <th class="text-right py-2.5 px-3 font-semibold">Pending</th>
                            <th class="text-right py-2.5 px-3 font-semibold">Bonus</th>
                            <th class="text-right py-2.5 px-3 font-semibold">Rusak</th>
                            <th class="text-right py-2.5 px-3 font-semibold">Cost</th>
                            <th class="py-2.5 px-5 font-semibold">Kondisi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-foreground/5">
                        <tr v-for="i in grn.items" :key="i.id" class="hover:bg-foreground/2.5 transition-colors">
                            <td class="py-2.5 px-5">
                                <p class="font-medium">{{ i.product_name_snapshot }}</p>
                                <p class="text-[12px] text-muted-foreground font-mono">{{ i.product_sku_snapshot }} · {{ i.product_unit_name_snapshot }}</p>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-xs">{{ i.batch_code }}</td>
                            <td class="py-2.5 px-3 text-xs">{{ formatDate(i.expired_date) }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty_reguler }}</td>
                            <td class="py-2.5 px-3">
                                <!-- Sudah ditandai selesai (item penerimaan langsung) -->
                                <div v-if="itemHasPending(i) && i.pending_settled_at" class="flex items-center justify-end gap-1.5">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700">
                                        <PackageCheck class="size-3" /> Selesai
                                    </span>
                                    <button
                                        v-if="canManagePending"
                                        type="button"
                                        :disabled="settleForm.processing"
                                        class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-medium bg-muted/70 text-muted-foreground hover:bg-muted transition-colors disabled:opacity-50"
                                        @click="unsettleItem(i)"
                                    >
                                        Batal
                                    </button>
                                </div>
                                <!-- Masih pending & belum ditandai -->
                                <div v-else-if="itemHasPending(i)" class="flex items-center justify-end gap-1.5">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold font-mono bg-amber-50 text-amber-700">
                                        {{ (Number(i.qty_delivery_note) || 0) - Number(i.qty_reguler) }}
                                    </span>
                                    <button
                                        v-if="canManagePending"
                                        type="button"
                                        :disabled="settleForm.processing"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-emerald-600 text-white hover:bg-emerald-700 transition-colors disabled:opacity-50"
                                        @click="settleItem(i)"
                                    >
                                        <Check class="size-3" /> Selesai
                                    </button>
                                </div>
                                <!-- Pending dari PO (read-only, sisa PO) atau tidak ada pending -->
                                <div v-else class="text-right font-mono">
                                    <span v-if="pendingOf(i) > 0" class="text-amber-700">{{ pendingOf(i) }}</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty_bonus > 0 ? i.qty_bonus : '—' }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty_damaged > 0 ? i.qty_damaged : '—' }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">
                                {{ formatRp(i.cost_price) }}
                                <span v-if="i.cost_overridden" class="block text-[11px] text-amber-700">override</span>
                            </td>
                            <td class="py-2.5 px-5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-muted/70 text-muted-foreground">
                                    {{ conditionLabel(i.condition) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Lampiran surat penerimaan (multi-file) -->
        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
            <header class="px-5 py-3 border-b border-foreground/5 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold flex items-center gap-2">
                    <Paperclip class="size-4 text-muted-foreground" />
                    Lampiran ({{ grn.attachments?.length ?? 0 }})
                </h3>
                <label
                    v-if="canManageAttachments"
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium cursor-pointer transition-colors',
                        attachForm.processing ? 'bg-muted text-muted-foreground' : 'bg-brand text-white hover:bg-brand-dark',
                    ]"
                >
                    <Loader2 v-if="attachForm.processing" class="size-3.5 animate-spin" />
                    <Upload v-else class="size-3.5" />
                    {{ attachForm.processing ? 'Mengunggah…' : 'Unggah File' }}
                    <input type="file" multiple class="sr-only" :disabled="attachForm.processing" @change="pickFiles" />
                </label>
            </header>

            <p v-if="attachForm.errors.files || attachForm.errors['files.0']" class="text-xs text-destructive px-5 pt-2">
                {{ attachForm.errors.files || attachForm.errors['files.0'] }}
            </p>

            <div v-if="!grn.attachments || grn.attachments.length === 0" class="px-5 py-8 text-center text-xs text-muted-foreground">
                Belum ada lampiran.
                <span v-if="canManageAttachments">Unggah foto atau PDF surat penerimaan.</span>
            </div>

            <ul v-else class="divide-y divide-foreground/5">
                <li v-for="att in grn.attachments" :key="att.id" class="flex items-center gap-3 px-5 py-3">
                    <div class="size-9 rounded-lg bg-muted/70 text-muted-foreground flex items-center justify-center shrink-0">
                        <component :is="isImage(att.file_mime) ? Image : FileText" class="size-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <a
                            :href="route('grn-attachments.show', att.id)"
                            target="_blank"
                            rel="noopener"
                            class="text-sm font-medium text-foreground hover:text-primary transition-colors truncate block"
                        >
                            {{ att.original_name }}
                        </a>
                        <p class="text-[11px] text-muted-foreground">
                            {{ formatSize(att.file_size) }}
                            <span v-if="att.uploader"> · oleh {{ att.uploader.name }}</span>
                            · {{ formatDate(att.created_at) }}
                        </p>
                    </div>
                    <button
                        v-if="canManageAttachments"
                        type="button"
                        class="size-8 rounded-lg hover:bg-destructive/10 text-muted-foreground hover:text-destructive flex items-center justify-center transition-colors shrink-0"
                        @click="deleteAttachment(att)"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </li>
            </ul>
        </div>

        <div v-if="grn.notes || grn.discrepancy_notes" class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div v-if="grn.notes" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5">
                <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan</p>
                <p class="text-sm whitespace-pre-line">{{ grn.notes }}</p>
            </div>
            <div v-if="grn.discrepancy_notes" class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5">
                <p class="text-[11px] uppercase tracking-wider text-amber-800 font-semibold mb-1">Catatan Discrepancy</p>
                <p class="text-sm whitespace-pre-line text-amber-900">{{ grn.discrepancy_notes }}</p>
            </div>
        </div>

        <!-- Reject Dialog -->
        <Dialog v-model:open="rejectOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-full bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                            <X class="size-5" />
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <DialogTitle class="text-base font-semibold tracking-tight">Reject GRN</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Operator akan dapat notif & GRN balik ke draft untuk revisi.
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
                            <DialogTitle class="text-base font-semibold tracking-tight">Cancel GRN</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Hanya draft yang bisa di-cancel. Aksi ini final.
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
                    <Button type="button" variant="destructive" class="rounded-full" :disabled="cancelForm.processing" @click="doCancel">Cancel GRN</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Post Dialog -->
        <Dialog v-model:open="postOpen">
            <DialogContent class="sm:max-w-[520px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <CheckCircle2 class="size-5" />
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <DialogTitle class="text-base font-semibold tracking-tight">Posting GRN</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Stok masuk akan tercatat di ledger. Aksi ini final & tidak bisa diundo.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <div class="px-6 pb-2 space-y-3">
                    <div v-if="hasOverReceive" class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-3.5 text-xs">
                        <p class="font-medium text-amber-900 mb-1">⚠ Over-receive terdeteksi</p>
                        <p class="text-amber-800">Centang di bawah untuk approve & lanjut posting.</p>
                    </div>
                    <label v-if="hasOverReceive" class="flex items-center gap-2 text-sm">
                        <input v-model="postForm.approve_over_receive" type="checkbox" class="size-4" />
                        Approve over-receive & posting
                    </label>
                </div>
                <DialogFooter class="px-6 py-4 gap-2">
                    <Button type="button" variant="outline" class="rounded-full" @click="postOpen = false">Batal</Button>
                    <Button
                        type="button"
                        class="rounded-full bg-brand text-white hover:bg-brand-dark"
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
