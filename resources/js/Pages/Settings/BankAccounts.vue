<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { Landmark, Pencil, Plus, Receipt, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
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
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({ manage: false }) },
});

const dialogOpen = ref(false);
const editingId = ref(null);

const form = useForm({
    bank_name: '',
    bank_code: '',
    account_number: '',
    account_holder: '',
    branch: '',
    is_active: true,
    show_on_invoice: true,
    sort_order: 0,
});

function openCreate() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(acc) {
    editingId.value = acc.id;
    form.clearErrors();
    form.bank_name = acc.bank_name;
    form.bank_code = acc.bank_code ?? '';
    form.account_number = acc.account_number;
    form.account_holder = acc.account_holder;
    form.branch = acc.branch ?? '';
    form.is_active = acc.is_active;
    form.show_on_invoice = acc.show_on_invoice;
    form.sort_order = acc.sort_order ?? 0;
    dialogOpen.value = true;
}

function save() {
    const opts = { preserveScroll: true, onSuccess: () => (dialogOpen.value = false) };
    if (editingId.value) {
        form.put(route('settings.bank-accounts.update', editingId.value), opts);
    } else {
        form.post(route('settings.bank-accounts.store'), opts);
    }
}

async function destroy(acc) {
    if (!(await confirm({ title: `Hapus rekening ${acc.bank_name}?`, destructive: true }))) return;
    router.delete(route('settings.bank-accounts.destroy', acc.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Rekening Bank" />

    <AppLayout>
        <PageHeader
            title="Rekening Bank"
            description="Rekening perusahaan untuk penerimaan pembayaran; bisa ditampilkan di faktur."
            :icon="Landmark"
        >
            <template #actions>
                <Button v-if="can.manage" size="default" class="rounded-full bg-brand text-white hover:bg-brand-dark" @click="openCreate">
                    <Plus class="size-4" /> Tambah Rekening
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div v-if="accounts.length === 0" class="px-4 py-16 flex flex-col items-center gap-2 text-muted-foreground">
                <Landmark class="size-7 opacity-40" />
                <p class="text-sm">Belum ada rekening bank.</p>
            </div>

            <ul v-else class="divide-y divide-foreground/5">
                <li v-for="acc in accounts" :key="acc.id" class="flex items-center gap-4 px-5 py-3.5 hover:bg-foreground/2.5 transition-colors">
                    <div class="size-10 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0">
                        <Landmark class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold">{{ acc.bank_name }}</p>
                            <span v-if="acc.show_on_invoice" class="inline-flex items-center gap-1 text-[11px] text-emerald-700 bg-emerald-50 rounded-full px-2 py-0.5">
                                <Receipt class="size-3" /> Di faktur
                            </span>
                            <span v-if="!acc.is_active" class="text-[11px] text-muted-foreground bg-muted rounded-full px-2 py-0.5">Nonaktif</span>
                        </div>
                        <p class="text-[13px] text-muted-foreground font-mono mt-0.5">
                            {{ acc.account_number }} · a.n. {{ acc.account_holder }}
                            <span v-if="acc.branch"> · {{ acc.branch }}</span>
                        </p>
                    </div>
                    <div v-if="can.manage" class="flex items-center gap-1 shrink-0">
                        <button
                            type="button"
                            class="size-8 rounded-full hover:bg-foreground/5 text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors"
                            @click="openEdit(acc)"
                        >
                            <Pencil class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="size-8 rounded-full hover:bg-destructive/10 text-muted-foreground hover:text-destructive flex items-center justify-center transition-colors"
                            @click="destroy(acc)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Add/Edit dialog -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-[520px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0">
                            <Landmark class="size-5" />
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <DialogTitle class="text-base font-semibold tracking-tight">{{ editingId ? 'Edit Rekening' : 'Tambah Rekening' }}</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">Data rekening bank perusahaan.</DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form class="px-6 pb-2 space-y-3" @submit.prevent="save">
                    <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Nama Bank *</Label>
                            <Input v-model="form.bank_name" class="h-10 rounded-xl" placeholder="mis. BCA" />
                            <p v-if="form.errors.bank_name" class="text-xs text-destructive">{{ form.errors.bank_name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Kode Bank</Label>
                            <Input v-model="form.bank_code" class="h-10 rounded-xl" placeholder="opsional" />
                        </div>
                        <div class="space-y-1.5 col-span-2">
                            <Label class="text-xs font-medium">No. Rekening *</Label>
                            <Input v-model="form.account_number" class="h-10 rounded-xl font-mono" />
                            <p v-if="form.errors.account_number" class="text-xs text-destructive">{{ form.errors.account_number }}</p>
                        </div>
                        <div class="space-y-1.5 col-span-2">
                            <Label class="text-xs font-medium">Atas Nama *</Label>
                            <Input v-model="form.account_holder" class="h-10 rounded-xl" />
                            <p v-if="form.errors.account_holder" class="text-xs text-destructive">{{ form.errors.account_holder }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Cabang</Label>
                            <Input v-model="form.branch" class="h-10 rounded-xl" placeholder="opsional" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Urutan</Label>
                            <Input v-model="form.sort_order" type="number" min="0" class="h-10 rounded-xl font-mono" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between rounded-xl bg-muted/40 px-3.5 py-2.5">
                        <span class="text-sm">Aktif</span>
                        <Switch v-model="form.is_active" />
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-muted/40 px-3.5 py-2.5">
                        <span class="text-sm">Tampilkan di faktur</span>
                        <Switch v-model="form.show_on_invoice" />
                    </div>
                </form>

                <DialogFooter class="px-6 py-4 gap-2">
                    <Button type="button" variant="outline" class="rounded-full" @click="dialogOpen = false">Batal</Button>
                    <Button type="button" class="rounded-full bg-brand text-white hover:bg-brand-dark" :disabled="form.processing" @click="save">
                        {{ editingId ? 'Simpan Perubahan' : 'Simpan' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
