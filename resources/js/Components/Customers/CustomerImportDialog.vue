<script setup>
import { useForm } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, Loader2, Upload } from '@lucide/vue';
import { ref, watch } from 'vue';
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

defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'saved']);

const fileInput = ref(null);
const form = useForm({
    file: null,
});

watch(
    () => fileInput.value,
    () => {
        form.clearErrors();
    },
);

function onFileChange(e) {
    const file = e.target.files?.[0] ?? null;
    form.file = file;
}

function submit() {
    if (!form.file) {
        form.setError('file', 'Pilih file dulu.');
        return;
    }
    form.post(route('customers.import'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = '';
            emit('update:open', false);
            emit('saved');
        },
    });
}

function close() {
    emit('update:open', false);
    form.reset();
    if (fileInput.value) fileInput.value.value = '';
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
            <DialogHeader class="px-6 pt-6 pb-4">
                <div class="flex items-start gap-3.5">
                    <div class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0">
                        <FileSpreadsheet class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0 pt-0.5">
                        <DialogTitle class="text-base font-semibold tracking-tight">
                            Import Customer dari Excel
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Upload file .xlsx/.xls/.csv sesuai template. Maksimum 1000 baris per upload.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="px-6 pb-2 space-y-4">
                <div class="rounded-2xl bg-muted/40 px-4 py-3 text-xs space-y-2">
                    <p class="font-semibold text-foreground">Cara pakai:</p>
                    <ol class="list-decimal pl-4 space-y-0.5 text-muted-foreground">
                        <li>Download template Excel di bawah.</li>
                        <li>Buka sheet <strong>Reference</strong> untuk lihat kode yang valid (type, tier, sales).</li>
                        <li>Isi data di sheet <strong>Customers</strong>, hapus contoh baris.</li>
                        <li>Upload file di bawah → sistem akan validasi tiap baris.</li>
                        <li>Baris invalid akan dilaporkan, baris valid langsung masuk.</li>
                    </ol>
                </div>

                <Button as-child variant="outline" size="default" class="w-full rounded-full">
                    <a :href="route('customers.import.template')">
                        <Download class="size-4" />
                        Download Template
                    </a>
                </Button>

                <div class="space-y-1.5">
                    <Label class="text-xs font-medium">File Excel *</Label>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        class="block w-full text-xs text-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-brand file:text-white hover:file:bg-brand-dark file:cursor-pointer"
                        @change="onFileChange"
                    />
                    <p v-if="form.errors.file" class="text-xs text-destructive">{{ form.errors.file }}</p>
                </div>
            </div>

            <DialogFooter class="px-6 py-4 gap-2">
                <Button type="button" variant="outline" size="default" class="rounded-full" @click="close">
                    Batal
                </Button>
                <Button
                    type="button"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    :disabled="form.processing || !form.file"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <Upload v-else class="size-4" />
                    {{ form.processing ? 'Mengimport…' : 'Import' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
