<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Edit,
    PackageOpen,
    Send,
    Wallet,
    XCircle,
} from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import SrStatusBadge from '@/Components/SupplierReturns/SrStatusBadge.vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    supplierReturn: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canApprove: { type: Boolean, default: false },
    canMarkSent: { type: Boolean, default: false },
    canSettle: { type: Boolean, default: false },
    canCancel: { type: Boolean, default: false },
});

const processing = ref(false);
const cancelOpen = ref(false);
const cancelReason = ref('');
const settleOpen = ref(false);
const settledAmount = ref(props.supplierReturn.claim_amount);
const settlementNotes = ref('');
const sentOpen = ref(false);
const sentDate = ref(new Date().toISOString().slice(0, 10));

function approve() {
    if (!confirm('Approve retur ini? Source tracking akan di-increment.')) return;
    processing.value = true;
    router.post(route('supplier-returns.approve', props.supplierReturn.id), {}, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}

function submitMarkSent() {
    processing.value = true;
    router.post(route('supplier-returns.mark-sent', props.supplierReturn.id), { sent_date: sentDate.value }, {
        preserveScroll: true,
        onSuccess: () => (sentOpen.value = false),
        onFinish: () => (processing.value = false),
    });
}

function submitSettle() {
    processing.value = true;
    router.post(route('supplier-returns.settle', props.supplierReturn.id), {
        settled_amount: settledAmount.value,
        settlement_notes: settlementNotes.value,
    }, {
        preserveScroll: true,
        onSuccess: () => (settleOpen.value = false),
        onFinish: () => (processing.value = false),
    });
}

function submitCancel() {
    processing.value = true;
    router.post(route('supplier-returns.cancel', props.supplierReturn.id), {
        cancel_reason: cancelReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => (cancelOpen.value = false),
        onFinish: () => (processing.value = false),
    });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}

function formatDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head :title="`Retur Supplier ${supplierReturn.return_number}`" />

    <AppLayout>
        <PageHeader :title="supplierReturn.return_number" :description="supplierReturn.supplier?.name" :icon="PackageOpen">
            <template #actions>
                <Button as-child variant="outline" size="default">
                    <Link :href="route('supplier-returns.index')">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
                <Button v-if="canEdit" as-child variant="outline" size="default">
                    <Link :href="route('supplier-returns.edit', supplierReturn.id)">
                        <Edit class="size-4" /> Edit
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div v-if="supplierReturn.status === 'cancelled'" class="mb-4 rounded-md ring-1 ring-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800 flex items-start gap-2">
            <AlertTriangle class="size-4 mt-0.5 shrink-0" />
            <div>
                <p class="font-semibold">SR dibatalkan</p>
                <p class="text-xs mt-0.5">{{ supplierReturn.cancel_reason || 'Tanpa alasan' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <section class="lg:col-span-2 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">Header</h3>
                    <SrStatusBadge :status="supplierReturn.status" />
                </div>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[11px] uppercase text-muted-foreground">Supplier</dt>
                        <dd class="font-medium">{{ supplierReturn.supplier?.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase text-muted-foreground">Tgl Retur</dt>
                        <dd>{{ formatDate(supplierReturn.return_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase text-muted-foreground">Reason</dt>
                        <dd>{{ supplierReturn.reason_code ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase text-muted-foreground">Claim</dt>
                        <dd class="font-mono font-semibold">{{ fmtRp(supplierReturn.claim_amount) }}</dd>
                    </div>
                    <div v-if="supplierReturn.settled_amount !== null">
                        <dt class="text-[11px] uppercase text-muted-foreground">Settled Amount</dt>
                        <dd class="font-mono">{{ fmtRp(supplierReturn.settled_amount) }}</dd>
                    </div>
                    <div v-if="supplierReturn.sent_date">
                        <dt class="text-[11px] uppercase text-muted-foreground">Tgl Kirim</dt>
                        <dd>{{ formatDate(supplierReturn.sent_date) }}</dd>
                    </div>
                </dl>
                <div v-if="supplierReturn.reason_notes">
                    <h4 class="text-xs font-semibold mb-1">Reason Notes</h4>
                    <p class="text-xs text-muted-foreground">{{ supplierReturn.reason_notes }}</p>
                </div>
            </section>

            <aside class="space-y-3">
                <section v-if="canApprove || canMarkSent || canSettle || canCancel" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-2">
                    <h3 class="text-sm font-semibold">Aksi</h3>
                    <Button v-if="canApprove" class="w-full" :disabled="processing" @click="approve">
                        <CheckCircle2 class="size-4" /> Approve
                    </Button>
                    <Button v-if="canMarkSent" class="w-full" :disabled="processing" @click="sentOpen = true">
                        <Send class="size-4" /> Mark Sent
                    </Button>
                    <Button v-if="canSettle" class="w-full" :disabled="processing" @click="settleOpen = true">
                        <Wallet class="size-4" /> Settle
                    </Button>
                    <Button v-if="canCancel" variant="outline" class="w-full text-red-700" @click="cancelOpen = true">
                        <XCircle class="size-4" /> Cancel
                    </Button>
                </section>

                <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 text-xs space-y-1">
                    <h3 class="text-sm font-semibold mb-1">Riwayat</h3>
                    <p v-if="supplierReturn.approver"><span class="text-muted-foreground">Approved oleh</span> {{ supplierReturn.approver.name }} • {{ formatDateTime(supplierReturn.approved_at) }}</p>
                    <p v-if="supplierReturn.sender"><span class="text-muted-foreground">Dikirim oleh</span> {{ supplierReturn.sender.name }} • {{ formatDateTime(supplierReturn.sent_at) }}</p>
                    <p v-if="supplierReturn.settler"><span class="text-muted-foreground">Settled oleh</span> {{ supplierReturn.settler.name }} • {{ formatDateTime(supplierReturn.settled_at) }}</p>
                </section>
            </aside>

            <section class="lg:col-span-3 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4">
                <h3 class="text-sm font-semibold mb-2">Items</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase text-muted-foreground border-b">
                            <th class="text-left py-2">Source</th>
                            <th class="text-left">Ref</th>
                            <th class="text-left">Produk</th>
                            <th class="text-left">Batch</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Cost</th>
                            <th class="text-right">Line Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in supplierReturn.items" :key="item.id" class="border-b">
                            <td class="py-2 text-[10px] uppercase font-semibold">{{ item.source_type }}</td>
                            <td class="font-mono text-xs">
                                <span v-if="item.source_type === 'grn_damaged'">{{ item.grn_item?.goods_receipt?.grn_number ?? '—' }}</span>
                                <span v-else-if="item.source_type === 'customer_return_bs'">{{ item.customer_return_item?.customer_return?.return_number ?? '—' }}</span>
                                <span v-else>—</span>
                            </td>
                            <td>{{ item.product_name_snapshot }}</td>
                            <td class="font-mono text-xs">{{ item.batch_code_snapshot ?? '—' }}</td>
                            <td class="text-right font-mono">{{ item.qty }}</td>
                            <td class="text-right font-mono">{{ fmtRp(item.cost_price) }}</td>
                            <td class="text-right font-mono font-semibold">{{ fmtRp(item.line_value) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <Dialog v-model:open="sentOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Mark Sent</DialogTitle>
                    <DialogDescription>Stok akan di-update untuk item source 'stock'.</DialogDescription>
                </DialogHeader>
                <div>
                    <Label>Tanggal Kirim</Label>
                    <Input v-model="sentDate" type="date" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="sentOpen = false">Batal</Button>
                    <Button :disabled="processing" @click="submitMarkSent">Mark Sent</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="settleOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Settle Retur</DialogTitle>
                    <DialogDescription>Supplier kasih kredit. Boleh partial.</DialogDescription>
                </DialogHeader>
                <div class="space-y-2">
                    <div>
                        <Label>Settled Amount</Label>
                        <Input v-model.number="settledAmount" type="number" min="0" step="0.01" />
                    </div>
                    <div>
                        <Label>Notes</Label>
                        <Textarea v-model="settlementNotes" rows="3" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="settleOpen = false">Batal</Button>
                    <Button :disabled="processing || settledAmount < 0" @click="submitSettle">Settle</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="cancelOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel Retur</DialogTitle>
                    <DialogDescription>Source tracking akan di-revert kalau sudah approved.</DialogDescription>
                </DialogHeader>
                <div>
                    <Label>Alasan</Label>
                    <Textarea v-model="cancelReason" rows="3" required />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="cancelOpen = false">Batal</Button>
                    <Button variant="destructive" :disabled="processing || !cancelReason.trim()" @click="submitCancel">Cancel</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
