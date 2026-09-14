<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    CheckCircle2,
    Inbox,
    Pencil,
    Receipt,
    Send,
    Wallet,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { confirm } from '@/Composables/useConfirm';
import PaymentRequestStatusBadge from '@/Components/Payments/PaymentRequestStatusBadge.vue';
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
    paymentRequest: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canSubmit: { type: Boolean, default: false },
    canCancel: { type: Boolean, default: false },
    canVerify: { type: Boolean, default: false },
    canReject: { type: Boolean, default: false },
});

const rejectOpen = ref(false);

const submitForm = useForm({});
const verifyForm = useForm({});
const cancelForm = useForm({});
const rejectForm = useForm({ rejection_reason: '' });

async function doSubmit() {
    if (!(await confirm({ title: 'Submit ke kasir untuk verifikasi?' }))) return;
    submitForm.post(route('payment-requests.submit', props.paymentRequest.id), { preserveScroll: true });
}

async function doVerify() {
    if (
        !(await confirm({
            title: 'Verify payment request?',
            description: 'Payment akan ter-create & apply ke invoice.',
        }))
    )
        return;
    verifyForm.post(route('payment-requests.verify', props.paymentRequest.id));
}

async function doCancel() {
    if (!(await confirm({ title: 'Batalkan request ini?' }))) return;
    cancelForm.post(route('payment-requests.cancel', props.paymentRequest.id), { preserveScroll: true });
}

function doReject() {
    rejectForm.post(route('payment-requests.reject', props.paymentRequest.id), {
        preserveScroll: true,
        onSuccess: () => (rejectOpen.value = false),
    });
}

function formatDateTime(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head :title="`Payment Request #${paymentRequest.id}`" />

    <AppLayout>
        <PageHeader
            :title="`Payment Request #${paymentRequest.id}`"
            :description="`Invoice ${paymentRequest.invoice?.invoice_number ?? '—'} · ${paymentRequest.customer?.name ?? '—'}`"
            :icon="Inbox"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('payment-requests.index')">
                        <ArrowLeft class="size-4" /> Daftar Request
                    </Link>
                </Button>
                <Button v-if="canEdit" as-child size="default" variant="outline" class="rounded-full">
                    <Link :href="route('payment-requests.edit', paymentRequest.id)">
                        <Pencil class="size-4" /> Edit
                    </Link>
                </Button>
                <Button v-if="canSubmit" size="default" class="rounded-full bg-brand text-white hover:bg-brand-dark" @click="doSubmit">
                    <Send class="size-4" /> Submit
                </Button>
                <Button v-if="canVerify" size="default" class="rounded-full bg-brand text-white hover:bg-brand-dark" @click="doVerify">
                    <CheckCircle2 class="size-4" /> Verify
                </Button>
                <Button v-if="canReject" size="default" variant="outline" class="rounded-full" @click="rejectOpen = true">
                    <X class="size-4" /> Reject
                </Button>
                <Button v-if="canCancel" size="default" variant="outline" class="rounded-full" @click="doCancel">
                    <Ban class="size-4" /> Cancel
                </Button>
            </template>
        </PageHeader>

        <section v-if="paymentRequest.status === 'rejected'" class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-4 mb-4 text-sm">
            <p class="text-amber-900"><strong>Ditolak.</strong> {{ paymentRequest.rejection_reason }}</p>
            <p class="text-xs text-amber-800 mt-1">Edit & submit ulang setelah revisi.</p>
        </section>

        <section v-if="paymentRequest.payment" class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 p-4 mb-4 text-sm">
            <p class="text-emerald-900">
                <strong>Verified.</strong> Payment ter-create:
                <Link :href="route('payments.show', paymentRequest.payment.id)" class="font-mono hover:underline">
                    {{ paymentRequest.payment.payment_number }}
                </Link>
                · Status: <strong>{{ paymentRequest.payment.status }}</strong>
                · Applied: {{ fmtRp(paymentRequest.payment.applied_amount) }}
            </p>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-4 flex items-start gap-4 lg:col-span-2">
                <div class="size-12 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <Inbox class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[12px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ paymentRequest.method.toUpperCase() }}
                    </p>
                    <h2 class="text-base font-bold tracking-tight font-mono">{{ fmtRp(paymentRequest.amount) }}</h2>
                    <p class="text-xs text-muted-foreground mt-1">
                        Dibayar {{ formatDate(paymentRequest.paid_at) }}
                        <span v-if="paymentRequest.sales"> · oleh {{ paymentRequest.sales.name }}</span>
                    </p>
                </div>
                <PaymentRequestStatusBadge :status="paymentRequest.status" />
            </div>

            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                <header class="border-b border-foreground/5 px-5 py-3 flex items-center gap-2">
                    <Receipt class="size-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">Invoice</h3>
                </header>
                <div class="px-5 py-3 text-sm space-y-1">
                    <p class="font-mono">
                        <Link :href="route('invoices.show', paymentRequest.invoice_id)" class="hover:text-primary">
                            {{ paymentRequest.invoice?.invoice_number }}
                        </Link>
                    </p>
                    <p class="text-xs">Total: <span class="font-mono">{{ fmtRp(paymentRequest.invoice?.total) }}</span></p>
                    <p class="text-xs">Sisa: <span class="font-mono text-red-700">{{ fmtRp(paymentRequest.invoice?.outstanding) }}</span></p>
                    <p class="text-xs">Jatuh tempo: {{ formatDate(paymentRequest.invoice?.due_date) }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                <header class="border-b border-foreground/5 px-5 py-3">
                    <h3 class="text-sm font-semibold">Detail Pembayaran</h3>
                </header>
                <dl class="px-5 py-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">Metode</dt>
                        <dd class="mt-0.5">{{ paymentRequest.method }}</dd>
                    </div>
                    <div v-if="paymentRequest.bank_name">
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">Bank</dt>
                        <dd class="mt-0.5">{{ paymentRequest.bank_name }}</dd>
                    </div>
                    <div v-if="paymentRequest.reference_no" class="sm:col-span-2">
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">Referensi</dt>
                        <dd class="mt-0.5 font-mono">{{ paymentRequest.reference_no }}</dd>
                    </div>
                    <div v-if="paymentRequest.giro_due_date" class="sm:col-span-2">
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">Giro Jatuh Tempo</dt>
                        <dd class="mt-0.5">{{ formatDate(paymentRequest.giro_due_date) }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="paymentRequest.proof_image_path" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                <header class="border-b border-foreground/5 px-5 py-3">
                    <h3 class="text-sm font-semibold">Bukti Pembayaran</h3>
                </header>
                <div class="px-5 py-3">
                    <a :href="`/storage/${paymentRequest.proof_image_path}`" target="_blank">
                        <img :src="`/storage/${paymentRequest.proof_image_path}`" class="w-full h-48 object-cover rounded-md ring-1 ring-foreground/10" alt="proof" />
                    </a>
                </div>
            </div>
        </div>

        <div v-if="paymentRequest.notes" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
            <p class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan</p>
            <p class="text-sm whitespace-pre-line">{{ paymentRequest.notes }}</p>
        </div>

        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5">
            <p class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold mb-2">Riwayat</p>
            <ul class="text-xs space-y-1">
                <li>Dibuat <strong>{{ formatDateTime(paymentRequest.created_at) }}</strong></li>
                <li v-if="paymentRequest.submitted_at">Disubmit <strong>{{ formatDateTime(paymentRequest.submitted_at) }}</strong></li>
                <li v-if="paymentRequest.verified_at" class="text-emerald-700">
                    Verified <strong>{{ formatDateTime(paymentRequest.verified_at) }}</strong>
                    <span v-if="paymentRequest.verifier"> · oleh {{ paymentRequest.verifier.name }}</span>
                </li>
                <li v-if="paymentRequest.rejected_at" class="text-amber-700">
                    Rejected <strong>{{ formatDateTime(paymentRequest.rejected_at) }}</strong>
                    <span v-if="paymentRequest.rejecter"> · oleh {{ paymentRequest.rejecter.name }}</span>
                </li>
                <li v-if="paymentRequest.cancelled_at" class="text-red-700">
                    Cancelled <strong>{{ formatDateTime(paymentRequest.cancelled_at) }}</strong>
                </li>
            </ul>
        </div>

        <Dialog v-model:open="rejectOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-foreground/5">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-full bg-amber-50 text-amber-700 flex items-center justify-center shrink-0 ring-1 ring-amber-200">
                            <X class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">Reject Payment Request</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Sales akan dapat notif & bisa edit + submit ulang.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-5 py-4" @submit.prevent="doReject">
                    <Label class="text-xs font-medium">Alasan *</Label>
                    <Textarea v-model="rejectForm.rejection_reason" rows="3" required class="mt-1" />
                    <p v-if="rejectForm.errors.rejection_reason" class="text-xs text-destructive mt-1">{{ rejectForm.errors.rejection_reason }}</p>
                </form>
                <DialogFooter class="px-5 py-3 border-t border-foreground/5 bg-muted/30">
                    <Button type="button" variant="outline" class="rounded-full" @click="rejectOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" class="rounded-full" :disabled="rejectForm.processing" @click="doReject">Reject</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
