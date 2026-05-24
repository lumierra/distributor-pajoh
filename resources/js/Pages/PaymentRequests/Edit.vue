<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Inbox, Loader2 } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    paymentRequest: { type: Object, required: true },
});

const form = useForm({
    amount: Number(props.paymentRequest.amount),
    method: props.paymentRequest.method,
    reference_no: props.paymentRequest.reference_no ?? '',
    bank_name: props.paymentRequest.bank_name ?? '',
    giro_due_date: props.paymentRequest.giro_due_date
        ? String(props.paymentRequest.giro_due_date).slice(0, 10)
        : '',
    paid_at: props.paymentRequest.paid_at
        ? String(props.paymentRequest.paid_at).slice(0, 10)
        : new Date().toISOString().slice(0, 10),
    proof_image: null,
    notes: props.paymentRequest.notes ?? '',
});

function onProofChange(e) {
    form.proof_image = e.target.files?.[0] ?? null;
}

function submit() {
    form
        .transform((data) => {
            const out = { ...data };
            // Inertia v3 putForm dengan file: pakai _method=put + post
            out._method = 'put';
            return out;
        })
        .post(route('payment-requests.update', props.paymentRequest.id), { forceFormData: true });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head :title="`Edit Payment Request #${paymentRequest.id}`" />

    <AppLayout>
        <PageHeader :title="`Edit Payment Request #${paymentRequest.id}`" :description="`Invoice: ${paymentRequest.invoice?.invoice_number}`" :icon="Inbox">
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('payment-requests.show', paymentRequest.id)">
                        <ArrowLeft class="size-4" /> Kembali ke Detail
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form @submit.prevent="submit">
            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4">
                <p class="text-xs text-muted-foreground mb-3">
                    Invoice: <strong>{{ paymentRequest.invoice?.invoice_number }}</strong>
                    · Sisa: <span class="font-mono">{{ fmtRp(paymentRequest.invoice?.outstanding) }}</span>
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Amount *</Label>
                        <Input v-model="form.amount" type="number" step="0.01" min="0.01" required class="h-9 text-right font-mono" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Tanggal Dibayar *</Label>
                        <Input v-model="form.paid_at" type="date" required class="h-9" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Metode *</Label>
                        <Select v-model="form.method">
                            <SelectTrigger class="h-9"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash">Cash</SelectItem>
                                <SelectItem value="transfer">Transfer</SelectItem>
                                <SelectItem value="giro">Giro</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div v-if="form.method !== 'cash'" class="space-y-1">
                        <Label class="text-xs font-medium">Bank</Label>
                        <Input v-model="form.bank_name" class="h-9" />
                    </div>
                    <div v-if="form.method !== 'cash'" class="space-y-1 sm:col-span-2">
                        <Label class="text-xs font-medium">No. Referensi</Label>
                        <Input v-model="form.reference_no" class="h-9 font-mono" />
                    </div>
                    <div v-if="form.method === 'giro'" class="space-y-1 sm:col-span-2">
                        <Label class="text-xs font-medium">Tanggal Jatuh Tempo Giro *</Label>
                        <Input v-model="form.giro_due_date" type="date" required class="h-9" />
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <Label class="text-xs font-medium">Foto Bukti (ganti, opsional)</Label>
                        <Input type="file" accept="image/jpeg,image/png,application/pdf" class="h-9" @change="onProofChange" />
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <Label class="text-xs font-medium">Catatan</Label>
                        <Textarea v-model="form.notes" rows="2" />
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <Button as-child type="button" variant="outline" size="default">
                    <Link :href="route('payment-requests.show', paymentRequest.id)">Batal</Link>
                </Button>
                <Button type="submit" variant="secondary" size="default" :disabled="form.processing">
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    Simpan
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
