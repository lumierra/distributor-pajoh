<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, FileMinus } from '@lucide/vue';
import { ref } from 'vue';
import CnStatusBadge from '@/Components/CreditNotes/CnStatusBadge.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    creditNote: { type: Object, required: true },
    openInvoices: { type: Array, default: () => [] },
    canApply: { type: Boolean, default: false },
});

const applyOpen = ref(false);
const invoiceId = ref('');
const amount = ref(0);
const processing = ref(false);

function openApply() {
    invoiceId.value = '';
    amount.value = 0;
    applyOpen.value = true;
}

function submitApply() {
    processing.value = true;
    router.post(route('credit-notes.apply', props.creditNote.id), {
        invoice_id: invoiceId.value,
        amount: amount.value,
    }, {
        preserveScroll: true,
        onSuccess: () => (applyOpen.value = false),
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
    <Head :title="`CN ${creditNote.cn_number}`" />

    <AppLayout>
        <PageHeader :title="creditNote.cn_number" :description="creditNote.customer?.name" :icon="FileMinus">
            <template #actions>
                <Button as-child variant="outline" size="default" class="rounded-full">
                    <Link :href="route('credit-notes.index')">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <section class="lg:col-span-2 rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">Detail</h3>
                    <CnStatusBadge :status="creditNote.status" />
                </div>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Customer</dt>
                        <dd class="font-medium">{{ creditNote.customer?.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Tgl CN</dt>
                        <dd>{{ formatDate(creditNote.cn_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">CR Source</dt>
                        <dd class="font-mono text-xs">
                            <Link v-if="creditNote.customer_return" :href="route('customer-returns.show', creditNote.customer_return.id)" class="hover:text-primary">
                                {{ creditNote.customer_return.return_number }}
                            </Link>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Created By</dt>
                        <dd>{{ creditNote.creator?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Amount</dt>
                        <dd class="font-mono font-semibold">{{ fmtRp(creditNote.amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Applied</dt>
                        <dd class="font-mono">{{ fmtRp(creditNote.applied_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Remaining</dt>
                        <dd class="font-mono font-semibold text-amber-700">{{ fmtRp(creditNote.remaining_amount) }}</dd>
                    </div>
                </dl>
            </section>

            <aside class="space-y-3">
                <section v-if="canApply" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-4">
                    <h3 class="text-sm font-semibold mb-2">Apply ke Invoice</h3>
                    <p class="text-xs text-muted-foreground mb-3">Sisa: {{ fmtRp(creditNote.remaining_amount) }}</p>
                    <Button class="w-full rounded-full bg-brand text-white hover:bg-brand-dark" :disabled="openInvoices.length === 0" @click="openApply">
                        Apply Sekarang
                    </Button>
                    <p v-if="openInvoices.length === 0" class="text-xs text-muted-foreground mt-2">
                        Customer tidak punya invoice dengan outstanding > 0.
                    </p>
                </section>
            </aside>

            <section class="lg:col-span-3 rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-4">
                <h3 class="text-sm font-semibold mb-2">Riwayat Aplikasi</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                            <th class="text-left py-2">Tanggal</th>
                            <th class="text-left">Invoice</th>
                            <th class="text-right">Amount</th>
                            <th class="text-left">Applied By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="creditNote.applications.length === 0">
                            <td colspan="4" class="text-center py-6 text-muted-foreground text-xs">Belum ada aplikasi.</td>
                        </tr>
                        <tr v-for="app in creditNote.applications" :key="app.id" class="border-b border-foreground/5 hover:bg-foreground/2.5 transition-colors">
                            <td class="py-2 text-xs">{{ formatDateTime(app.applied_at) }}</td>
                            <td class="font-mono text-xs">
                                <Link :href="route('invoices.show', app.invoice_id)" class="hover:text-primary">
                                    {{ app.invoice?.invoice_number ?? '—' }}
                                </Link>
                            </td>
                            <td class="text-right font-mono">{{ fmtRp(app.amount) }}</td>
                            <td class="text-xs">{{ app.applied_by?.name ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <Dialog v-model:open="applyOpen">
            <DialogContent class="rounded-3xl gap-0">
                <DialogHeader class="border-b border-foreground/5 pb-4">
                    <DialogTitle>Apply CN ke Invoice</DialogTitle>
                    <DialogDescription>Pilih invoice dan masukkan jumlah yang akan di-apply.</DialogDescription>
                </DialogHeader>
                <div class="space-y-3 pt-4">
                    <div>
                        <Label>Invoice</Label>
                        <Select v-model="invoiceId">
                            <SelectTrigger><SelectValue placeholder="Pilih invoice" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="i in openInvoices" :key="i.id" :value="String(i.id)">
                                    {{ i.invoice_number }} — outstanding {{ fmtRp(i.outstanding) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>Amount</Label>
                        <Input v-model.number="amount" type="number" min="0" step="0.01" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" class="rounded-full" @click="applyOpen = false">Batal</Button>
                    <Button class="rounded-full bg-brand text-white hover:bg-brand-dark" :disabled="processing || !invoiceId || amount <= 0" @click="submitApply">Apply</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
