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
        <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-md bg-indigo-50 text-indigo-700 flex items-center justify-center shrink-0 ring-1 ring-indigo-200">
                        <FileSpreadsheet class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <DialogTitle class="text-base font-bold tracking-tight">
                            Import Customer dari Excel
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Upload file .xlsx/.xls/.csv sesuai template. Maksimum 1000 baris per upload.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="px-5 py-4 space-y-4">
                <div class="rounded-md bg-muted/40 ring-1 ring-foreground/5 px-3.5 py-2.5 text-xs space-y-2">
                    <p class="font-semibold text-foreground">Cara pakai:</p>
                    <ol class="list-decimal pl-4 space-y-0.5 text-muted-foreground">
                        <li>Download template Excel di bawah.</li>
                        <li>Buka sheet <strong>Reference</strong> untuk lihat kode yang valid (type, tier, sales).</li>
                        <li>Isi data di sheet <strong>Customers</strong>, hapus contoh baris.</li>
                        <li>Upload file di bawah → sistem akan validasi tiap baris.</li>
                        <li>Baris invalid akan dilaporkan, baris valid langsung masuk.</li>
                    </ol>
                </div>

                <Button as-child variant="outline" size="default" class="w-full">
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
                        class="block w-full text-xs text-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-secondary file:text-secondary-foreground hover:file:bg-secondary/80 file:cursor-pointer"
                        @change="onFileChange"
                    />
                    <p v-if="form.errors.file" class="text-xs text-destructive">{{ form.errors.file }}</p>
                </div>
            </div>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="close">Batal</Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="default"
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
