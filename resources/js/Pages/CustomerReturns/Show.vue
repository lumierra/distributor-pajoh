<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Edit,
    PackageX,
    Save,
    XCircle,
} from '@lucide/vue';
import { ref } from 'vue';
import CrStatusBadge from '@/Components/CustomerReturns/CrStatusBadge.vue';
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
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    customerReturn: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canSort: { type: Boolean, default: false },
    canPost: { type: Boolean, default: false },
    canCancel: { type: Boolean, default: false },
});

const sortRows = ref(
    props.customerReturn.items.map((it) => ({
        id: it.id,
        product_name: it.product_name_snapshot,
        qty_total: it.qty_total,
        qty_good: it.qty_good,
        qty_bs: it.qty_bs,
    })),
);

const processing = ref(false);
const cancelOpen = ref(false);
const cancelReason = ref('');

function saveSort() {
    processing.value = true;
    router.post(
        route('customer-returns.sort', props.customerReturn.id),
        { items: sortRows.value.map((r) => ({ id: r.id, qty_good: r.qty_good, qty_bs: r.qty_bs })) },
        { preserveScroll: true, onFinish: () => (processing.value = false) },
    );
}

function post() {
    if (!confirm('Yakin posting? Stok akan di-update & credit note ter-generate.')) return;
    processing.value = true;
    router.post(route('customer-returns.post', props.customerReturn.id), {}, {
        preserveScroll: true, onFinish: () => (processing.value = false),
    });
}

function submitCancel() {
    processing.value = true;
    router.post(route('customer-returns.cancel', props.customerReturn.id), {
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
</script>

<template>
    <Head :title="`Retur ${customerReturn.return_number}`" />

    <AppLayout>
        <PageHeader :title="customerReturn.return_number" :description="customerReturn.customer?.name" :icon="PackageX">
            <template #actions>
                <Button as-child variant="outline" size="default">
                    <Link :href="route('customer-returns.index')">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
                <Button v-if="canEdit" as-child variant="outline" size="default">
                    <Link :href="route('customer-returns.edit', customerReturn.id)">
                        <Edit class="size-4" /> Edit
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div v-if="customerReturn.status === 'cancelled'" class="mb-4 rounded-md ring-1 ring-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800 flex items-start gap-2">
            <AlertTriangle class="size-4 mt-0.5 shrink-0" />
            <div>
                <p class="font-semibold">CR dibatalkan</p>
                <p class="text-xs mt-0.5">{{ customerReturn.cancel_reason || 'Tanpa alasan' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <section class="lg:col-span-2 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">Header</h3>
                    <CrStatusBadge :status="customerReturn.status" />
                </div>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Customer</dt>
                        <dd class="font-medium">{{ customerReturn.customer?.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Sales</dt>
                        <dd>{{ customerReturn.sales?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Invoice</dt>
                        <dd class="font-mono text-xs">{{ customerReturn.invoice?.invoice_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">DO</dt>
                        <dd class="font-mono text-xs">{{ customerReturn.delivery_order?.do_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Tgl Retur</dt>
                        <dd>{{ formatDate(customerReturn.return_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Brand</dt>
                        <dd>{{ customerReturn.brand_tag ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Reason</dt>
                        <dd>{{ customerReturn.reason_code ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Total Value</dt>
                        <dd class="font-mono font-semibold">{{ fmtRp(customerReturn.total_value) }}</dd>
                    </div>
                </dl>
            </section>

            <aside class="space-y-3">
                <section v-if="customerReturn.credit_note" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 text-sm space-y-1">
                    <h3 class="text-sm font-semibold mb-1">Credit Note</h3>
                    <p class="font-mono text-xs">
                        <Link :href="route('credit-notes.show', customerReturn.credit_note.id)" class="hover:text-primary">
                            {{ customerReturn.credit_note.cn_number }}
                        </Link>
                    </p>
                    <p class="text-xs"><span class="text-muted-foreground">Status:</span> {{ customerReturn.credit_note.status }}</p>
                    <p class="text-xs"><span class="text-muted-foreground">Remaining:</span> {{ fmtRp(customerReturn.credit_note.remaining_amount) }}</p>
                </section>

                <section v-if="canPost || canCancel" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-2">
                    <h3 class="text-sm font-semibold mb-1">Aksi</h3>
                    <Button v-if="canPost" class="w-full" :disabled="processing" @click="post">
                        <CheckCircle2 class="size-4" /> Posting
                    </Button>
                    <Button v-if="canCancel" variant="outline" class="w-full text-red-700" @click="cancelOpen = true">
                        <XCircle class="size-4" /> Cancel
                    </Button>
                </section>
            </aside>

            <section class="lg:col-span-3 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold">Item & Sortir</h3>
                    <Button v-if="canSort" size="sm" :disabled="processing" @click="saveSort">
                        <Save class="size-3.5" /> Simpan Sortir
                    </Button>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase text-muted-foreground border-b">
                            <th class="text-left py-2">Produk</th>
                            <th class="text-right">Qty Total</th>
                            <th class="text-right">Qty BAIK</th>
                            <th class="text-right">Qty BS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in sortRows" :key="row.id" class="border-b">
                            <td class="py-2">{{ row.product_name }}</td>
                            <td class="text-right font-mono">{{ row.qty_total }}</td>
                            <td class="text-right">
                                <Input v-if="canSort" v-model.number="row.qty_good" type="number" min="0" class="w-20 inline-block text-right" />
                                <span v-else class="font-mono">{{ row.qty_good }}</span>
                            </td>
                            <td class="text-right">
                                <Input v-if="canSort" v-model.number="row.qty_bs" type="number" min="0" class="w-20 inline-block text-right" />
                                <span v-else class="font-mono">{{ row.qty_bs }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="text-xs text-muted-foreground mt-2">Catatan: qty_good + qty_bs harus sama dengan qty_total.</p>
            </section>
        </div>

        <Dialog v-model:open="cancelOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel Retur</DialogTitle>
                    <DialogDescription>Berikan alasan pembatalan.</DialogDescription>
                </DialogHeader>
                <div class="space-y-2">
                    <Label for="cancel_reason">Alasan</Label>
                    <Textarea id="cancel_reason" v-model="cancelReason" rows="3" required />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="cancelOpen = false">Batal</Button>
                    <Button variant="destructive" :disabled="processing || !cancelReason.trim()" @click="submitCancel">Cancel CR</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
