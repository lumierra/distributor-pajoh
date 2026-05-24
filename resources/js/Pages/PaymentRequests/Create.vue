<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Inbox, Loader2 } from '@lucide/vue';
import { computed } from 'vue';
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
    openInvoices: { type: Array, required: true },
    selectedInvoice: { type: Object, default: null },
    salesUsers: { type: Array, default: () => [] },
});

const form = useForm({
    invoice_id: props.selectedInvoice?.id ?? null,
    sales_id: null,
    amount: props.selectedInvoice?.outstanding ?? 0,
    method: 'cash',
    reference_no: '',
    bank_name: '',
    giro_due_date: '',
    paid_at: new Date().toISOString().slice(0, 10),
    proof_image: null,
    notes: '',
});

const chosenInvoice = computed(() => {
    if (props.selectedInvoice && props.selectedInvoice.id === form.invoice_id) return props.selectedInvoice;
    return props.openInvoices.find((i) => i.id === form.invoice_id) ?? null;
});

function onInvoiceChange(v) {
    form.invoice_id = v ? Number(v) : null;
    if (chosenInvoice.value) {
        form.amount = chosenInvoice.value.outstanding;
    }
}

function onProofChange(e) {
    form.proof_image = e.target.files?.[0] ?? null;
}

function submit() {
    form.post(route('payment-requests.store'), { forceFormData: true });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head title="Buat Payment Request" />

    <AppLayout>
        <PageHeader title="Buat Payment Request" description="Lapor pembayaran dari customer. Kasir akan verifikasi sebelum apply ke invoice." :icon="Inbox">
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('payment-requests.index')">
                        <ArrowLeft class="size-4" /> Daftar Request
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form @submit.prevent="submit">
            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4 space-y-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Invoice</p>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Pilih Invoice *</Label>
                    <Select
                        :model-value="form.invoice_id ? String(form.invoice_id) : ''"
                        @update:model-value="onInvoiceChange"
                    >
                        <SelectTrigger class="h-9"><SelectValue placeholder="Pilih invoice yang akan dibayar" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="inv in openInvoices" :key="inv.id" :value="String(inv.id)">
                                {{ inv.invoice_number }} · {{ inv.customer?.name }}
                                <span class="text-[10px] text-muted-foreground ml-1">
                                    Sisa: {{ fmtRp(inv.outstanding) }}
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.invoice_id" class="text-xs text-destructive">{{ form.errors.invoice_id }}</p>
                </div>
                <div v-if="chosenInvoice" class="rounded-md bg-muted/40 px-3 py-2 text-xs space-y-0.5">
                    <p><strong>Customer:</strong> {{ chosenInvoice.customer?.name }}</p>
                    <p><strong>Total invoice:</strong> {{ fmtRp(chosenInvoice.total) }}</p>
                    <p><strong>Outstanding:</strong> <span class="font-mono text-red-700">{{ fmtRp(chosenInvoice.outstanding) }}</span></p>
                </div>
            </section>

            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-3">Detail Pembayaran</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div v-if="salesUsers.length > 0" class="space-y-1">
                        <Label class="text-xs font-medium">Sales (atas nama)</Label>
                        <Select
                            :model-value="form.sales_id ? String(form.sales_id) : ''"
                            @update:model-value="(v) => (form.sales_id = v ? Number(v) : null)"
                        >
                            <SelectTrigger class="h-9"><SelectValue placeholder="(diri sendiri)" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="u in salesUsers" :key="u.id" :value="String(u.id)">{{ u.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Tanggal Dibayar *</Label>
                        <Input v-model="form.paid_at" type="date" required class="h-9" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Amount *</Label>
                        <Input v-model="form.amount" type="number" step="0.01" min="0.01" required class="h-9 text-right font-mono" />
                        <p v-if="form.errors.amount" class="text-xs text-destructive">{{ form.errors.amount }}</p>
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
                        <Input v-model="form.bank_name" class="h-9" placeholder="Nama bank" />
                    </div>
                    <div v-if="form.method !== 'cash'" class="space-y-1">
                        <Label class="text-xs font-medium">No. Referensi</Label>
                        <Input v-model="form.reference_no" class="h-9 font-mono" placeholder="No transaksi / no giro" />
                    </div>
                    <div v-if="form.method === 'giro'" class="space-y-1 sm:col-span-2">
                        <Label class="text-xs font-medium">Tanggal Jatuh Tempo Giro *</Label>
                        <Input v-model="form.giro_due_date" type="date" required class="h-9" />
                        <p v-if="form.errors.giro_due_date" class="text-xs text-destructive">{{ form.errors.giro_due_date }}</p>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <Label class="text-xs font-medium">Foto Bukti (opsional, max 5MB)</Label>
                        <Input type="file" accept="image/jpeg,image/png,application/pdf" class="h-9" @change="onProofChange" />
                        <p v-if="form.errors.proof_image" class="text-xs text-destructive">{{ form.errors.proof_image }}</p>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <Label class="text-xs font-medium">Catatan</Label>
                        <Textarea v-model="form.notes" rows="2" />
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <Button as-child type="button" variant="outline" size="default">
                    <Link :href="route('payment-requests.index')">Batal</Link>
                </Button>
                <Button type="submit" variant="secondary" size="default" :disabled="form.processing || !form.invoice_id">
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    Simpan Draft
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
