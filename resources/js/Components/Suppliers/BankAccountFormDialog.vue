<script setup>
import { useForm } from '@inertiajs/vue3';
import { Landmark, Loader2 } from '@lucide/vue';
import { computed, watch } from 'vue';
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

const props = defineProps({
    open: { type: Boolean, default: false },
    supplierId: { type: Number, required: true },
    /** null = create, object = edit */
    bank: { type: Object, default: null },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.bank);

const form = useForm({
    bank_name: '',
    bank_code: '',
    account_number: '',
    account_holder: '',
    branch: '',
    is_default: false,
    is_active: true,
    notes: '',
});

watch(
    () => [props.open, props.bank?.id],
    ([open]) => {
        if (!open) return;
        if (props.bank) {
            form.defaults({
                bank_name: props.bank.bank_name ?? '',
                bank_code: props.bank.bank_code ?? '',
                account_number: props.bank.account_number ?? '',
                account_holder: props.bank.account_holder ?? '',
                branch: props.bank.branch ?? '',
                is_default: !!props.bank.is_default,
                is_active: !!props.bank.is_active,
                notes: props.bank.notes ?? '',
            });
        } else {
            form.defaults({
                bank_name: '',
                bank_code: '',
                account_number: '',
                account_holder: '',
                branch: '',
                is_default: false,
                is_active: true,
                notes: '',
            });
        }
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

function close() {
    emit('update:open', false);
}

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            close();
            emit('saved');
        },
    };
    if (isEdit.value) {
        form.put(route('supplier-bank-accounts.update', props.bank.id), opts);
    } else {
        form.post(route('suppliers.bank-accounts.store', props.supplierId), opts);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div
                        class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200"
                    >
                        <Landmark class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <DialogTitle class="text-base font-bold tracking-tight">
                            {{ isEdit ? 'Edit Rekening Bank' : 'Tambah Rekening Bank' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Set sebagai default supaya otomatis dipilih di PO.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-3.5" @submit.prevent="submit">
                <div class="grid grid-cols-1 sm:grid-cols-[1fr_120px] gap-3">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Nama bank *</Label>
                        <Input v-model="form.bank_name" required class="h-9" placeholder="BCA, Mandiri, BRI…" />
                        <p v-if="form.errors.bank_name" class="text-xs text-destructive">
                            {{ form.errors.bank_name }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Kode</Label>
                        <Input v-model="form.bank_code" class="h-9 font-mono" placeholder="014" />
                    </div>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">No. Rekening *</Label>
                    <Input v-model="form.account_number" required class="h-9 font-mono" placeholder="1234567890" />
                    <p v-if="form.errors.account_number" class="text-xs text-destructive">
                        {{ form.errors.account_number }}
                    </p>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Atas Nama *</Label>
                    <Input v-model="form.account_holder" required class="h-9" />
                    <p v-if="form.errors.account_holder" class="text-xs text-destructive">
                        {{ form.errors.account_holder }}
                    </p>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Cabang</Label>
                    <Input v-model="form.branch" class="h-9" placeholder="opsional" />
                </div>
                <div
                    class="flex items-center justify-between rounded-md bg-muted/40 ring-1 ring-foreground/5 px-3.5 py-2.5"
                >
                    <div>
                        <Label class="text-xs font-medium block cursor-pointer">Default</Label>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            Jadikan rekening utama (hanya 1 per supplier).
                        </p>
                    </div>
                    <Switch v-model="form.is_default" />
                </div>
            </form>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="close">
                    Batal
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="default"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
