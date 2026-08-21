<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Banknote,
    CheckCircle2,
    XCircle,
} from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import PaymentStatusBadge from '@/Components/Payments/PaymentStatusBadge.vue';
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
    payment: { type: Object, required: true },
    canClear: { type: Boolean, default: false },
    canBounce: { type: Boolean, default: false },
});

const bounceOpen = ref(false);
const bounceReason = ref('');
const processing = ref(false);

function clearGiro() {
    if (!confirm('Yakin mencairkan giro ini? Pembayaran akan ter-apply ke invoice.')) return;
    processing.value = true;
    router.post(route('payments.clear-giro', props.payment.id), {}, {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}

function submitBounce() {
    processing.value = true;
    router.post(route('payments.bounce-giro', props.payment.id), {
        bounce_reason: bounceReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            bounceOpen.value = false;
            bounceReason.value = '';
        },
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
    <Head :title="`Payment ${payment.payment_number}`" />

    <AppLayout>
        <PageHeader :title="`Payment ${payment.payment_number}`" :description="`Invoice ${payment.invoice?.invoice_number ?? '—'}`" :icon="Banknote">
            <template #actions>
                <Button as-child variant="outline" size="default">
                    <Link :href="route('payments.index')">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div v-if="payment.status === 'bounced'" class="mb-4 rounded-md ring-1 ring-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800 flex items-start gap-2">
            <AlertTriangle class="size-4 mt-0.5 shrink-0" />
            <div>
                <p class="font-semibold">Giro Bounced</p>
                <p class="text-xs mt-0.5">{{ payment.bounce_reason || 'Tanpa alasan' }}</p>
            </div>
        </div>

        <div v-if="payment.status === 'pending_clearing'" class="mb-4 rounded-md ring-1 ring-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900 flex items-start gap-2">
            <AlertTriangle class="size-4 mt-0.5 shrink-0" />
            <div>
                <p class="font-semibold">Menunggu pencairan giro</p>
                <p class="text-xs mt-0.5">Jatuh tempo giro: {{ formatDate(payment.giro_due_date) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <section class="lg:col-span-2 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">Detail Pembayaran</h3>
                    <PaymentStatusBadge :status="payment.status" />
                </div>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Customer</dt>
                        <dd class="font-medium">{{ payment.customer?.name }}</dd>
                        <dd class="text-xs text-muted-foreground font-mono">{{ payment.customer?.code }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Sales</dt>
                        <dd>{{ payment.payment_request?.sales?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Method</dt>
                        <dd class="uppercase font-semibold text-xs">{{ payment.method }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Tanggal Bayar</dt>
                        <dd>{{ formatDate(payment.paid_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Amount</dt>
                        <dd class="font-mono font-semibold">{{ fmtRp(payment.amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Applied to Invoice</dt>
                        <dd class="font-mono">{{ fmtRp(payment.applied_amount) }}</dd>
                    </div>
                    <div v-if="payment.overpayment_amount > 0">
                        <dt class="text-[12px] uppercase text-muted-foreground">Overpayment</dt>
                        <dd class="font-mono text-amber-700">{{ fmtRp(payment.overpayment_amount) }}</dd>
                    </div>
                    <div v-if="payment.reference_no">
                        <dt class="text-[12px] uppercase text-muted-foreground">Referensi</dt>
                        <dd class="font-mono text-xs">{{ payment.reference_no }}</dd>
                    </div>
                    <div v-if="payment.bank_name">
                        <dt class="text-[12px] uppercase text-muted-foreground">Bank</dt>
                        <dd>{{ payment.bank_name }}</dd>
                    </div>
                    <div v-if="payment.giro_due_date">
                        <dt class="text-[12px] uppercase text-muted-foreground">Giro Due Date</dt>
                        <dd>{{ formatDate(payment.giro_due_date) }}</dd>
                    </div>
                </dl>
            </section>

            <aside class="space-y-4">
                <section v-if="canClear || canBounce" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-2">
                    <h3 class="text-sm font-semibold mb-1">Aksi Giro</h3>
                    <Button v-if="canClear" class="w-full" :disabled="processing" @click="clearGiro">
                        <CheckCircle2 class="size-4" /> Cairkan Giro
                    </Button>
                    <Button v-if="canBounce" variant="destructive" class="w-full" :disabled="processing" @click="bounceOpen = true">
                        <XCircle class="size-4" /> Bounce Giro
                    </Button>
                </section>

                <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-2 text-xs">
                    <h3 class="text-sm font-semibold mb-2">Riwayat</h3>
                    <p v-if="payment.recorder"><span class="text-muted-foreground">Direkam oleh</span> {{ payment.recorder.name }} • {{ formatDateTime(payment.created_at) }}</p>
                    <p v-if="payment.cleared_at"><span class="text-muted-foreground">Dicairkan oleh</span> {{ payment.clearer?.name }} • {{ formatDateTime(payment.cleared_at) }}</p>
                    <p v-if="payment.bounced_at"><span class="text-muted-foreground">Di-bounce oleh</span> {{ payment.bouncer?.name }} • {{ formatDateTime(payment.bounced_at) }}</p>
                </section>

                <section v-if="payment.invoice" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-2 text-sm">
                    <h3 class="text-sm font-semibold mb-1">Invoice Terkait</h3>
                    <p class="font-mono text-xs">
                        <Link :href="route('invoices.show', payment.invoice.id)" class="hover:text-primary">
                            {{ payment.invoice.invoice_number }}
                        </Link>
                    </p>
                    <p class="text-xs"><span class="text-muted-foreground">Total:</span> {{ fmtRp(payment.invoice.total) }}</p>
                    <p class="text-xs"><span class="text-muted-foreground">Outstanding:</span> {{ fmtRp(payment.invoice.outstanding) }}</p>
                </section>
            </aside>
        </div>

        <Dialog v-model:open="bounceOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Bounce Giro</DialogTitle>
                    <DialogDescription>Pembayaran akan dibatalkan & invoice di-restore outstanding-nya.</DialogDescription>
                </DialogHeader>
                <div class="space-y-2">
                    <Label for="bounce_reason">Alasan Bounce</Label>
                    <Textarea id="bounce_reason" v-model="bounceReason" rows="3" required />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="bounceOpen = false">Batal</Button>
                    <Button variant="destructive" :disabled="processing || !bounceReason.trim()" @click="submitBounce">Bounce</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
